<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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
            'whatsapp_delivery' => ['nullable', 'string', 'in:not_sent,queued,submitted,delivered,read,failed,logged_only,unknown'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));
        $smsDelivery = trim((string) ($validated['sms_delivery'] ?? ''));
        $whatsappDelivery = trim((string) ($validated['whatsapp_delivery'] ?? ''));
        $showPhoneNumbers = (bool) $event->rsvp_share_show_phone;

        $invitees = $event->invitees()
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
            ->orderByRaw("
                CASE
                    WHEN rsvp_status = 'attending' THEN 1
                    WHEN rsvp_status IN ('not_attending', 'declined') THEN 2
                    WHEN rsvp_status = 'maybe' THEN 3
                    ELSE 4
                END
            ")
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // Retrieve only invitation delivery records for the invitees on this page.
        // Batch queries avoid an additional query for every invitee.
        $inviteeIds = $invitees->getCollection()->pluck('id')->all();
        $deliveryStatuses = [];

        foreach ($inviteeIds as $id) {
            $deliveryStatuses[$id] = [
                'whatsapp' => 'Not Sent',
                'sms' => 'Not Sent',
            ];
        }

        if ($inviteeIds !== [] && Schema::hasTable('message_logs')) {
            $latestWhatsApp = \Illuminate\Support\Facades\DB::table('message_logs')
                ->where('event_id', $event->id)
                ->whereIn('invitee_id', $inviteeIds)
                ->where('channel', 'whatsapp')
                ->where('type', 'invitation')
                ->orderByDesc('id')
                ->get(['invitee_id', 'status'])
                ->unique('invitee_id');

            foreach ($latestWhatsApp as $log) {
                $deliveryStatuses[$log->invitee_id]['whatsapp'] =
                    $this->deliveryLabel($log->status);
            }
        }

        // SMS logs have their own invitation type. Fall back to message_logs
        // for installations where the dedicated SMS table has no records.
        if ($inviteeIds !== [] && Schema::hasTable('message_logs')) {
            $latestSmsMessages = \Illuminate\Support\Facades\DB::table('message_logs')
                ->where('event_id', $event->id)
                ->whereIn('invitee_id', $inviteeIds)
                ->where('channel', 'sms')
                ->where('type', 'invitation')
                ->orderByDesc('id')
                ->get(['invitee_id', 'status'])
                ->unique('invitee_id');

            foreach ($latestSmsMessages as $log) {
                $deliveryStatuses[$log->invitee_id]['sms'] =
                    $this->deliveryLabel($log->status);
            }
        }

        if ($inviteeIds !== [] && Schema::hasTable('sms_logs')) {
            $latestSms = \Illuminate\Support\Facades\DB::table('sms_logs')
                ->where('event_id', $event->id)
                ->whereIn('invitee_id', $inviteeIds)
                ->whereIn('sms_type', ['invitation', 'invitation_card'])
                ->orderByDesc('id')
                ->get(['invitee_id', 'status'])
                ->unique('invitee_id');

            foreach ($latestSms as $log) {
                $deliveryStatuses[$log->invitee_id]['sms'] =
                    $this->deliveryLabel($log->status);
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
            'total' => $total,
            'attending' => $attending,
            'notAttending' => $notAttending,
            'pending' => $pending,
            'responded' => $responded,
            'responseRate' => $responseRate,
            'confirmedGuests' => $confirmedGuests,
            'search' => $search,
            'status' => $status,
            'smsDelivery' => $smsDelivery,
            'whatsappDelivery' => $whatsappDelivery,
            'showPhoneNumbers' => $showPhoneNumbers,
            'visibleFields' => $visibleFields,
            'deliveryStatuses' => $deliveryStatuses,
        ]);
    }

    /**
     * Match only the latest invitation attempt per channel (not reminders),
     * using the same SMS-log preference as the displayed report status.
     * The correlated subqueries preserve event isolation and pagination.
     */
    private function filterDelivery(Builder $query, string $channel, string $wanted): Builder
    {
        $latestMessage = Schema::hasTable('message_logs')
            ? "(SELECT ml.status FROM message_logs ml
                WHERE ml.event_id = invitees.event_id
                AND ml.invitee_id = invitees.id
                AND ml.channel = '".$channel."'
                AND ml.type = 'invitation'
                ORDER BY ml.id DESC LIMIT 1)"
            : 'NULL';

        $raw = $latestMessage;

        if ($channel === 'sms' && Schema::hasTable('sms_logs')) {
            $latestSms = "(SELECT sl.status FROM sms_logs sl
                WHERE sl.event_id = invitees.event_id
                AND sl.invitee_id = invitees.id
                AND sl.sms_type IN ('invitation', 'invitation_card')
                ORDER BY sl.id DESC LIMIT 1)";
            $raw = "COALESCE({$latestSms}, {$latestMessage})";
        }

        // Canonical labels mirror deliveryLabel(), not invitees.sms_status.
        $normalized = "LOWER(TRIM(COALESCE({$raw}, 'not_sent')))";
        $sql = "CASE
            WHEN {$normalized} = 'delivered' THEN 'delivered'
            WHEN {$normalized} = 'read' THEN 'read'
            WHEN {$normalized} IN ('failed', 'undelivered', 'expired', 'rejected') THEN 'failed'
            WHEN {$normalized} IN ('queued', 'pending', 'sending') THEN 'queued'
            WHEN {$normalized} IN ('submitted', 'accepted', 'sent', 'success') THEN 'submitted'
            WHEN {$normalized} = 'logged' THEN 'logged_only'
            WHEN {$normalized} = 'not_sent' THEN 'not_sent'
            ELSE 'unknown'
        END";

        return $query->whereRaw("({$sql}) = ?", [$wanted]);
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
