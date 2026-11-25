<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class PublicRsvpReportController extends Controller
{
    private const PER_PAGE = 50;

    public function show(Request $request, string $token): View
    {
        abort_unless(
            preg_match('/^[A-Za-z0-9]{40,128}$/', $token) === 1,
            Response::HTTP_NOT_FOUND
        );

        $event = Event::query()
            ->where('rsvp_share_token_hash', hash('sha256', $token))
            ->where('rsvp_share_enabled', true)
            ->firstOrFail();

        return $this->renderReport($request, $event);
    }

    public function showShort(Request $request, string $share): View
    {
        abort_unless(
            preg_match('/^[a-z0-9-]+-[A-Za-z0-9]{10}$/', $share) === 1,
            Response::HTTP_NOT_FOUND
        );

        $separatorPosition = strrpos($share, '-');

        abort_if(
            $separatorPosition === false,
            Response::HTTP_NOT_FOUND
        );

        $slug = substr($share, 0, $separatorPosition);
        $plainShortCode = substr($share, $separatorPosition + 1);

        $event = Event::query()
            ->where('rsvp_share_short_code_hash', hash('sha256', $plainShortCode))
            ->where('rsvp_share_enabled', true)
            ->firstOrFail();

        abort_unless(
            hash_equals(
                (string) $event->rsvp_share_slug,
                $slug
            ),
            Response::HTTP_NOT_FOUND
        );

        return $this->renderReport($request, $event);
    }

    private function renderReport(Request $request, Event $event): View
    {
        abort_if(
            $event->rsvpShareHasExpired(),
            Response::HTTP_GONE,
            'This RSVP report link has expired.'
        );

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => [
                'nullable',
                'string',
                'in:attending,not_attending,declined,pending,maybe',
            ],
            'sms_delivery' => ['nullable', 'string', 'in:not_sent,queued,submitted,delivered,failed,logged_only,unknown'],
            'whatsapp_delivery' => ['nullable', 'string', 'in:not_sent,queued,submitted,sent,delivered,read,failed,logged_only,unknown'],
            'overall_delivery' => ['nullable', 'string', 'in:delivered,not_sent,unconfirmed'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));
        $smsDelivery = trim((string) ($validated['sms_delivery'] ?? ''));
        $whatsappDelivery = trim((string) ($validated['whatsapp_delivery'] ?? ''));
        $overallDelivery = (string) ($validated['overall_delivery'] ?? '');
        $perPage = (int) ($validated['per_page'] ?? self::PER_PAGE);
        // Always display invitee phone numbers in the shared RSVP report.
        $showPhoneNumbers = true;

        $smsExpression = $this->deliveryExpression('sms');
        $whatsappExpression = $this->deliveryExpression('whatsapp');
        $invitees = $event->invitees()
            ->select('invitees.*')
            ->selectRaw("({$smsExpression}) AS report_sms_status")
            ->selectRaw("({$whatsappExpression}) AS report_whatsapp_status")
            ->with('cardType')
            ->when(
                $search !== '',
                function (Builder $query) use ($search, $showPhoneNumbers): void {
                    $term = $this->escapeLikeTerm(mb_strtolower($search));
                    $like = "%{$term}%";

                    $query->where(function (Builder $query) use ($like, $showPhoneNumbers): void {
                        $query
                            ->whereRaw(
                                "LOWER(COALESCE(name, '')) LIKE ? ESCAPE '!'",
                                [$like]
                            )
                            ->orWhereRaw(
                                "LOWER(COALESCE(category, '')) LIKE ? ESCAPE '!'",
                                [$like]
                            )
                            ->orWhereRaw(
                                "LOWER(COALESCE(table_number, '')) LIKE ? ESCAPE '!'",
                                [$like]
                            )
                            ->orWhereHas('cardType', function (Builder $cardTypeQuery) use ($like): void {
                                $cardTypeQuery->whereRaw(
                                    "LOWER(COALESCE(name, '')) LIKE ? ESCAPE '!'",
                                    [$like]
                                );
                            });

                        if ($showPhoneNumbers) {
                            $query->orWhereRaw(
                                "LOWER(COALESCE(phone, '')) LIKE ? ESCAPE '!'",
                                [$like]
                            );
                        }
                    });
                }
            )
            ->when(
                $status !== '',
                function (Builder $query) use ($status): void {
                    if ($status === 'pending') {
                        $query->where(function (Builder $query): void {
                            $query
                                ->whereNull('rsvp_status')
                                ->orWhere('rsvp_status', '')
                                ->orWhere('rsvp_status', 'pending');
                        });

                        return;
                    }

                    $query->where('rsvp_status', $status);
                }
            )
            ->when($smsDelivery !== '', fn (Builder $query) => $this->filterDelivery($query, 'sms', $smsDelivery))
            ->when($whatsappDelivery !== '', fn (Builder $query) => $this->filterDelivery($query, 'whatsapp', $whatsappDelivery))
            ->when($overallDelivery !== '', function (Builder $query) use ($overallDelivery, $smsExpression, $whatsappExpression): void {
                $query->whereRaw(match ($overallDelivery) {
                    'delivered' => "(({$smsExpression}) = 'delivered' OR ({$whatsappExpression}) IN ('delivered', 'read'))",
                    'not_sent' => "(({$smsExpression}) = 'not_sent' AND ({$whatsappExpression}) = 'not_sent')",
                    default => "(({$smsExpression}) <> 'delivered' AND ({$whatsappExpression}) NOT IN ('delivered', 'read'))",
                });
            })
            // First added, first shown; ID breaks ties for imported records.
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        // Retrieve only invitation delivery records for the invitees on this page.
        // Batch queries avoid an additional query for every invitee.
        $inviteeIds = $invitees->getCollection()->pluck('id')->all();
        $deliveryStatuses = [];
        $deliveryTimes = [];
        $smsDeliveryNotes = [];

        foreach ($inviteeIds as $id) {
            $deliveryTimes[$id] = ['whatsapp' => null, 'sms' => null];
            $deliveryStatuses[$id] = [
                'whatsapp' => 'Not Sent',
                'sms' => 'Not Sent',
            ];
        }

        if ($inviteeIds !== [] && Schema::hasTable('message_logs')) {
            $messageTimeColumns = array_values(array_intersect(
                ['sent_at', 'delivered_at', 'read_at', 'failed_at'],
                Schema::getColumnListing('message_logs')
            ));
            $latestWhatsApp = \Illuminate\Support\Facades\DB::table('message_logs')
                ->where('event_id', $event->id)
                ->whereIn('invitee_id', $inviteeIds)
                ->whereExists(function ($query): void {
                    $query->selectRaw('1')->from('invitees')
                        ->whereColumn('invitees.id', 'message_logs.invitee_id')
                        ->where(function ($query): void {
                            $query->whereNull('invitees.invitation_phone_changed_at')
                                ->orWhereRaw('message_logs.id > COALESCE(invitees.invitation_message_reset_log_id, 0)');
                        });
                })
                ->where('channel', 'whatsapp')
                ->where('type', 'invitation')
                ->orderByDesc('id')
                ->get(array_merge(['invitee_id', 'status'], $messageTimeColumns))
                ->unique('invitee_id');

            foreach ($latestWhatsApp as $log) {
                $deliveryStatuses[$log->invitee_id]['whatsapp'] =
                    $log->status === 'sent' ? 'Sent' : $this->deliveryLabel($log->status);
                $log->channel = 'whatsapp';
                $deliveryTimes[$log->invitee_id]['whatsapp'] = $this->deliveryTime($log);
            }
        }

        // SMS logs have their own invitation type. Fall back to message_logs
        // for installations where the dedicated SMS table has no records.
        if ($inviteeIds !== [] && Schema::hasTable('message_logs')) {
            $latestSmsMessages = \Illuminate\Support\Facades\DB::table('message_logs')
                ->where('event_id', $event->id)
                ->whereIn('invitee_id', $inviteeIds)
                ->whereExists(function ($query): void {
                    $query->selectRaw('1')->from('invitees')
                        ->whereColumn('invitees.id', 'message_logs.invitee_id')
                        ->where(function ($query): void {
                            $query->whereNull('invitees.invitation_phone_changed_at')
                                ->orWhereRaw('message_logs.id > COALESCE(invitees.invitation_message_reset_log_id, 0)');
                        });
                })
                ->where('channel', 'sms')
                ->where('type', 'invitation')
                ->orderByDesc('id')
                ->get(array_merge(['invitee_id', 'status'], array_values(array_intersect(
                    ['sent_at', 'delivered_at', 'read_at', 'failed_at', 'provider_status'],
                    Schema::getColumnListing('message_logs')
                ))))
                ->unique('invitee_id');

            foreach ($latestSmsMessages as $log) {
                $deliveryStatuses[$log->invitee_id]['sms'] =
                    $this->deliveryLabel($log->status);
                $deliveryTimes[$log->invitee_id]['sms'] = $this->deliveryTime($log);
                $smsDeliveryNotes[$log->invitee_id] = $this->smsDeliveryNote($log);
            }
        }

        if ($inviteeIds !== [] && Schema::hasTable('sms_logs')) {
            $latestSms = \Illuminate\Support\Facades\DB::table('sms_logs')
                ->where('event_id', $event->id)
                ->whereIn('invitee_id', $inviteeIds)
                ->whereExists(function ($query): void {
                    $query->selectRaw('1')->from('invitees')
                        ->whereColumn('invitees.id', 'sms_logs.invitee_id')
                        ->where(function ($query): void {
                            $query->whereNull('invitees.invitation_phone_changed_at')
                                ->orWhereRaw('sms_logs.id > COALESCE(invitees.invitation_sms_reset_log_id, 0)');
                        });
                })
                ->whereIn('sms_type', ['invitation', 'invitation_card'])
                ->orderByDesc('id')
                ->get(['invitee_id', 'status', 'sent_at', 'delivered_at', 'failed_at', 'provider_status'])
                ->unique('invitee_id');

            foreach ($latestSms as $log) {
                $deliveryStatuses[$log->invitee_id]['sms'] =
                    $this->deliveryLabel($log->status);
                $deliveryTimes[$log->invitee_id]['sms'] = $this->deliveryTime($log);
                $smsDeliveryNotes[$log->invitee_id] = $this->smsDeliveryNote($log);
            }
        }

        foreach ($invitees as $invitee) {
            foreach (['sms', 'whatsapp'] as $channel) {
                $statusKey = $invitee->{'report_'.$channel.'_status'};
                $label = match ($statusKey) {
                    'not_sent' => 'Not Sent',
                    'unknown' => 'Unverified',
                    'logged_only' => 'Logged Only',
                    default => ucfirst($statusKey),
                };
                if ($deliveryStatuses[$invitee->id][$channel] !== $label) {
                    $deliveryTimes[$invitee->id][$channel] = null;
                    if ($channel === 'sms') unset($smsDeliveryNotes[$invitee->id]);
                }
                $deliveryStatuses[$invitee->id][$channel] = $label;
                if ($channel === 'whatsapp' && !$deliveryTimes[$invitee->id][$channel]) {
                    $at = match ($label) {
                        'Read' => $invitee->whatsapp_read_at,
                        'Delivered' => $invitee->whatsapp_delivered_at,
                        'Failed' => $invitee->whatsapp_failed_at,
                        'Submitted', 'Sent' => $invitee->whatsapp_sent_at,
                        default => null,
                    };
                    $deliveryTimes[$invitee->id][$channel] = $at?->timezone('Africa/Dar_es_Salaam')->format('d M Y H:i');
                }
            }
        }

        $stats = $event->invitees()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("
                SUM(
                    CASE
                        WHEN rsvp_status = 'attending' THEN 1
                        ELSE 0
                    END
                ) AS attending
            ")
            ->selectRaw("
                SUM(
                    CASE
                        WHEN rsvp_status IN ('not_attending', 'declined') THEN 1
                        ELSE 0
                    END
                ) AS not_attending
            ")
            ->selectRaw("
                SUM(
                    CASE
                        WHEN rsvp_status IS NULL
                            OR rsvp_status = ''
                            OR rsvp_status IN ('pending', 'maybe')
                        THEN 1
                        ELSE 0
                    END
                ) AS pending
            ")
            ->selectRaw("
                SUM(
                    CASE
                        WHEN rsvp_status = 'attending'
                        THEN COALESCE(confirmed_guests, 0)
                        ELSE 0
                    END
                ) AS confirmed_guests
            ")
            ->first();

        $total = (int) ($stats?->total ?? 0);
        $attending = (int) ($stats?->attending ?? 0);
        $notAttending = (int) ($stats?->not_attending ?? 0);
        $pending = (int) ($stats?->pending ?? 0);
        $confirmedGuests = (int) ($stats?->confirmed_guests ?? 0);
        $responded = $attending + $notAttending;

        $responseRate = $total > 0
            ? (int) round(($responded / $total) * 100)
            : 0;

        // Count invitations by card type across the entire event. Inactive
        // types are included if used, but unused types are omitted.
        $usedCardTypes = DB::table('invitees')
            ->join('card_types', function ($join) use ($event): void {
                $join->on('card_types.id', '=', 'invitees.card_type_id')
                    ->where('card_types.event_id', '=', $event->id);
            })
            ->where('invitees.event_id', $event->id)
            ->select('card_types.id', 'card_types.name')
            ->selectRaw('COUNT(invitees.id) AS card_count')
            ->groupBy('card_types.id', 'card_types.name')
            ->orderBy('card_types.name')
            ->orderBy('card_types.id')
            ->get();
        $cardTypesUsed = $usedCardTypes->count();
        $cardTypeCounts = $usedCardTypes->map(fn ($row): array => [
            'name' => (string) $row->name,
            'count' => (int) $row->card_count,
        ])->all();

        /*
        |--------------------------------------------------------------------------
        | Only show report columns that are actually used by this event
        |--------------------------------------------------------------------------
        |
        | These checks run against the complete event guest list, not only the
        | current pagination page. Invitee IDs and serial numbers are never
        | exposed to the client report.
        |
        */
        $visibleFields = [
            'card_type' => $event->invitees()
                ->whereNotNull('card_type_id')
                ->exists(),

            'category' => $event->invitees()
                ->whereNotNull('category')
                ->where('category', '<>', '')
                ->exists(),

            'table_number' => $event->invitees()
                ->whereNotNull('table_number')
                ->where('table_number', '<>', '')
                ->exists(),

            'guests' => $this->eventUsesGuestCounts($event),

            'response_date' => $event->invitees()
                ->whereNotNull('rsvp_confirmed_at')
                ->exists(),

            'comment' => $event->invitees()
                ->whereNotNull('last_reply_message')
                ->where('last_reply_message', '<>', '')
                ->exists(),
        ];

        return view('public.rsvp-report', [
            'event' => $event,
            'invitees' => $invitees,
            'perPage' => $perPage,
            'total' => $total,
            'attending' => $attending,
            'notAttending' => $notAttending,
            'pending' => $pending,
            'responded' => $responded,
            'responseRate' => $responseRate,
            'confirmedGuests' => $confirmedGuests,
            'cardTypesUsed' => $cardTypesUsed,
            'cardTypeCounts' => $cardTypeCounts,
            'search' => $search,
            'status' => $status,
            'smsDelivery' => $smsDelivery,
            'overallDelivery' => $overallDelivery,
            'whatsappDelivery' => $whatsappDelivery,
            'showPhoneNumbers' => $showPhoneNumbers,
            'visibleFields' => $visibleFields,
            'deliveryStatuses' => $deliveryStatuses,
            'deliveryTimes' => $deliveryTimes,
            'smsDeliveryNotes' => $smsDeliveryNotes,
        ]);
    }

    /**
     * Match only the latest invitation attempt per channel (not reminders),
     * using the same SMS-log preference as the displayed report status.
     * The correlated subqueries preserve event isolation and pagination.
     */
    private function filterDelivery(Builder $query, string $channel, string $wanted): Builder
    {
        return $query->whereRaw('('.$this->deliveryExpression($channel).') = ?', [$wanted]);
    }

    private function deliveryExpression(string $channel): string
    {
        return $channel === 'whatsapp'
            ? app(\App\Services\InvitationWhatsAppDeliveryStatusService::class)->expression()
            : app(\App\Services\InvitationSmsReportStatusService::class)->expression();
    }

    /**
     * Return the timestamp for the recorded delivery state, not the last polling
     * time or an unrelated reminder. Do not invent a time from created_at.
     */
    private function deliveryTime(object $log): ?string
    {
        $label = ($log->status ?? null) === 'sent' && ($log->channel ?? null) === 'whatsapp' ? 'Sent' : $this->deliveryLabel($log->status ?? null);
        $field = match ($label) {
            'Read' => 'read_at',
            'Delivered' => 'delivered_at',
            'Failed' => 'failed_at',
            'Submitted', 'Sent' => 'sent_at',
            default => null,
        };

        if ($field === null || empty($log->{$field})) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($log->{$field})
            ->timezone('Africa/Dar_es_Salaam')
            ->format('d M Y H:i');
    }

    private function deliveryLabel(?string $status): string
    {
        return match (strtolower(trim((string) $status))) {
            'delivered' => 'Delivered',
            'read' => 'Read',
            'failed', 'undelivered', 'expired', 'rejected' => 'Failed',
            'queued', 'pending', 'sending' => 'Queued',
            'submitted', 'accepted', 'sent', 'success' => 'Submitted',
            'logged' => 'Logged Only',
            default => 'Unknown',
        };
    }

    private function smsDeliveryNote(object $log): ?string
    {
        if ($this->deliveryLabel($log->status) === 'Submitted') {
            return in_array(strtolower(trim($log->provider_status ?? '')), [
                'delivery_report_unavailable', 'report_failed',
            ], true) ? 'Delivery report unavailable' : 'Awaiting delivery';
        }

        return match (strtolower(trim((string) $log->status))) {
            'undelivered' => 'Undelivered',
            'expired' => 'Expired',
            'rejected' => 'Rejected',
            default => null,
        };
    }

    private function eventUsesGuestCounts(Event $event): bool
    {
        $inviteeColumns = Schema::getColumnListing('invitees');

        $guestColumns = array_values(array_intersect(
            [
                'confirmed_guests',
                'final_allowed_guests',
                'allowed_guests',
            ],
            $inviteeColumns
        ));

        if ($guestColumns !== []) {
            $hasInviteeGuestValues = $event->invitees()
                ->where(function (Builder $query) use ($guestColumns): void {
                    foreach ($guestColumns as $index => $column) {
                        if ($index === 0) {
                            $query->whereNotNull($column);
                            continue;
                        }

                        $query->orWhereNotNull($column);
                    }
                })
                ->exists();

            if ($hasInviteeGuestValues) {
                return true;
            }
        }

        return $event->invitees()
            ->whereHas('cardType', function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query
                        ->whereNotNull('allowed_guests')
                        ->orWhereNotNull('allowed_people');
                });
            })
            ->exists();
    }

    private function escapeLikeTerm(string $value): string
    {
        return str_replace(
            ['!', '%', '_'],
            ['!!', '!%', '!_'],
            $value
        );
    }
}
