<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Jobs\GenerateInviteeCardJob;
use App\Jobs\SendInvitationSmsJob;
use App\Services\SmsService;
use App\Support\EliveMessagePlaceholders;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SendEventMessage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.send-event-message';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return 'Message Center';
    }

    public function getHeading(): string
    {
        return 'Message Center';
    }

    public function getSubheading(): ?string
    {
        return 'Send invitations, reminders, thank-you messages, generate missing cards, and monitor delivery for this event.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToEvent')
                ->label('Back to Event')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => EventResource::getUrl('view', ['record' => $this->record])),

            Action::make('openInvitees')
                ->label('Open Invitees')
                ->icon('heroicon-o-users')
                ->color('primary')
                ->url(fn (): string => EventResource::getUrl('view', ['record' => $this->record])),

            Action::make('generateMissingCards')
                ->label('Generate Missing Cards')
                ->icon('heroicon-o-qr-code')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Generate Missing Cards')
                ->modalDescription('This will queue card generation only for invitees who do not already have generated cards.')
                ->modalSubmitActionLabel('Generate Cards')
                ->disabled(fn (): bool => $this->missingCardsCount === 0)
                ->action(fn () => $this->generateMissingCards()),

            Action::make('sendSmsInvitations')
                ->label('Send SMS Invitations')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Send SMS Invitations')
                ->modalDescription('This will queue SMS invitations only for invitees with phone number, private link, and generated card.')
                ->modalSubmitActionLabel('Queue SMS')
                ->disabled(fn (): bool => $this->unsentEligibleSmsInviteesCount === 0)
                ->action(fn () => $this->sendSmsInvitations()),

            Action::make('sendWhatsappInvitations')
                ->label('Send WhatsApp')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Send WhatsApp Invitations')
                ->modalDescription('This will send real WhatsApp invitations using WhatsApp Cloud API.')
                ->modalSubmitActionLabel('Send WhatsApp')
                ->disabled(fn (): bool => $this->unsentEligibleWhatsappInviteesCount === 0)
                ->action(fn () => $this->sendWhatsappInvitations()),

            Action::make('sendRsvpReminderSms')
                ->label('RSVP Reminder')
                ->icon('heroicon-o-bell-alert')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Send RSVP Reminder SMS')
                ->modalDescription('This will send reminder SMS to invitees whose RSVP is still pending.')
                ->modalSubmitActionLabel('Send Reminder')
                ->disabled(fn (): bool => $this->pendingRsvpCount === 0)
                ->action(fn () => $this->sendRsvpReminderSms()),

            Action::make('sendEventDayReminderSms')
                ->label('Event Day Reminder')
                ->icon('heroicon-o-calendar-days')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Send Event Day Reminder SMS')
                ->modalDescription('This will send final reminder SMS to invitees who are attending.')
                ->modalSubmitActionLabel('Send Final Reminder')
                ->disabled(fn (): bool => $this->attendingCount === 0)
                ->action(fn () => $this->sendEventDayReminderSms()),

            Action::make('sendThankYouSms')
                ->label('Thank You SMS')
                ->icon('heroicon-o-heart')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Send Thank You SMS')
                ->modalDescription('This will send thank-you SMS to checked-in invitees only.')
                ->modalSubmitActionLabel('Send Thank You')
                ->disabled(fn (): bool => $this->checkedInInviteesCount === 0)
                ->action(fn () => $this->sendThankYouSms()),
        ];
    }

    public function generateMissingCards(): void
    {
        $invitees = $this->record
            ->invitees()
            ->whereDoesntHave('generatedCards', fn (Builder $query): Builder => $query->where('status', 'generated'))
            ->get();

        if ($invitees->isEmpty()) {
            Notification::make()
                ->title('No missing cards')
                ->body('All invitees already have generated cards.')
                ->warning()
                ->send();

            return;
        }

        foreach ($invitees as $invitee) {
            GenerateInviteeCardJob::dispatch($invitee->id);
        }

        Notification::make()
            ->title('Card generation queued')
            ->body($invitees->count() . ' invitee card(s) queued.')
            ->success()
            ->send();
    }

    public function sendSmsInvitations(): void
    {
        $invitees = $this->eligibleInviteesQuery()->get();

        if ($invitees->isEmpty()) {
            Notification::make()
                ->title('No eligible invitees')
                ->body('No invitees have phone number, private link, and generated card.')
                ->danger()
                ->send();

            return;
        }

        $queued = 0;
        $skipped = 0;

        DB::transaction(function () use ($invitees, &$queued, &$skipped): void {
            foreach ($invitees as $invitee) {
                $alreadySentOrQueued = method_exists($invitee, 'smsLogs')
                    ? $invitee->smsLogs()
                        ->where('event_id', $this->record->id)
                        ->whereIn('sms_type', ['invitation', 'invitation_card'])
                        ->whereIn('status', ['queued', 'pending', 'sending', 'sent', 'logged', 'accepted', 'delivered'])
                        ->exists()
                    : false;

                if ($alreadySentOrQueued) {
                    $skipped++;
                    continue;
                }

                SendInvitationSmsJob::dispatch(eventId: $this->record->id, inviteeId: $invitee->id);
                $queued++;
            }
        });

        Notification::make()
            ->title($queued > 0 ? 'SMS invitations queued' : 'No new SMS queued')
            ->body($queued . ' SMS queued. ' . $skipped . ' skipped.')
            ->success()
            ->send();
    }

    public function sendWhatsappInvitations(): void
    {
        $accessToken = config('services.whatsapp.access_token') ?: env('WHATSAPP_ACCESS_TOKEN');
        $phoneNumberId = config('services.whatsapp.phone_number_id') ?: env('WHATSAPP_PHONE_NUMBER_ID');

        if (blank($accessToken) || blank($phoneNumberId)) {
            Notification::make()
                ->title('WhatsApp not configured')
                ->body('Set WHATSAPP_ACCESS_TOKEN and WHATSAPP_PHONE_NUMBER_ID in production environment.')
                ->danger()
                ->send();

            return;
        }

        $invitees = $this->eligibleInviteesQuery()
            ->whereDoesntHave('messageLogs', function ($query): void {
                $query
                    ->where('event_id', $this->record->id)
                    ->whereIn('channel', ['whatsapp', 'WhatsApp'])
                    ->whereIn('type', ['invitation', 'event_invitation', 'whatsapp_invitation'])
                    ->whereIn('status', ['queued', 'pending', 'sending', 'sent', 'accepted', 'delivered', 'read']);
            })
            ->get();

        if ($invitees->isEmpty()) {
            Notification::make()
                ->title('No WhatsApp messages sent')
                ->body('No eligible invitees found, or WhatsApp invitations were already sent.')
                ->warning()
                ->send();

            return;
        }

        $sent = 0;
        $failed = 0;

        foreach ($invitees as $invitee) {
            $phone = $this->normalizePhone($invitee->phone);
            $message = $this->buildWhatsappMessage($invitee);
            $logId = $this->createMessageLog($invitee, $phone, $message);

            try {
                $response = Http::withToken($accessToken)
                    ->acceptJson()
                    ->post("https://graph.facebook.com/v23.0/{$phoneNumberId}/messages", [
                        'messaging_product' => 'whatsapp',
                        'to' => $phone,
                        'type' => 'text',
                        'text' => [
                            'preview_url' => true,
                            'body' => $message,
                        ],
                    ]);

                $json = $response->json();

                if ($response->successful() && isset($json['messages'][0]['id'])) {
                    $this->updateMessageLog($logId, [
                        'status' => 'sent',
                        'provider_message_id' => $json['messages'][0]['id'],
                        'response' => $json,
                        'sent_at' => now(),
                    ]);

                    $sent++;
                } else {
                    $this->updateMessageLog($logId, [
                        'status' => 'failed',
                        'response' => $json ?: $response->body(),
                        'failed_at' => now(),
                    ]);

                    $failed++;
                }
            } catch (Throwable $exception) {
                $this->updateMessageLog($logId, [
                    'status' => 'failed',
                    'response' => $exception->getMessage(),
                    'failed_at' => now(),
                ]);

                Log::error('WhatsApp invitation failed from Message Center', [
                    'event_id' => $this->record->id,
                    'invitee_id' => $invitee->id,
                    'error' => $exception->getMessage(),
                ]);

                $failed++;
            }
        }

        Notification::make()
            ->title('WhatsApp sending completed')
            ->body($sent . ' sent. ' . $failed . ' failed.')
            ->success()
            ->send();
    }

    public function sendRsvpReminderSms(): void
    {
        $template = 'Habari #NAME#, tunakukumbusha kuthibitisha mahudhurio yako kwenye #EVENT_NAME#. Tarehe: #EVENT_DATE#, muda: #EVENT_TIME#, ukumbi #VENUE#. Thibitisha hapa: #RSVP_URL#';

        $invitees = $this->basicSmsInviteesQuery()
            ->where(function ($query): void {
                $query
                    ->whereNull('rsvp_status')
                    ->orWhere('rsvp_status', '')
                    ->orWhere('rsvp_status', 'pending');
            })
            ->get();

        $result = $this->sendBulkSms(
            invitees: $invitees,
            template: $template,
            smsType: 'rsvp_pending_reminder',
            duplicateTypes: ['rsvp_pending_reminder'],
        );

        $this->notifyBulkSmsResult('RSVP reminder SMS completed', $result);
    }

    public function sendEventDayReminderSms(): void
    {
        $template = 'Habari #NAME#, leo ni #EVENT_NAME#. Muda: #EVENT_TIME#. Ukumbi: #VENUE#. Ramani: #LOCATION_LINK#. Tafadhali fika na kadi yako: #PRIVATE_INVITATION_URL#';

        $invitees = $this->basicSmsInviteesQuery()
            ->where('rsvp_status', 'attending')
            ->get();

        $result = $this->sendBulkSms(
            invitees: $invitees,
            template: $template,
            smsType: 'event_day_reminder',
            duplicateTypes: ['event_day_reminder'],
        );

        $this->notifyBulkSmsResult('Event day reminder SMS completed', $result);
    }

    public function sendThankYouSms(): void
    {
        $template = 'Asante #NAME# kwa kuhudhuria #EVENT_NAME#. Uwepo wako umefanya tukio hili kuwa la kipekee. eLive Card';

        $invitees = $this->basicSmsInviteesQuery()
            ->where(function ($query): void {
                $query
                    ->whereNotNull('checked_in_at')
                    ->orWhere('checked_in_count', '>', 0);
            })
            ->get();

        $result = $this->sendBulkSms(
            invitees: $invitees,
            template: $template,
            smsType: 'thank_you_sms',
            duplicateTypes: ['thank_you_sms'],
        );

        $this->notifyBulkSmsResult('Thank you SMS completed', $result);
    }

    protected function sendBulkSms($invitees, string $template, string $smsType, array $duplicateTypes = []): array
    {
        if ($invitees->isEmpty()) {
            return [
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
                'message' => 'No eligible invitees found.',
            ];
        }

        $smsService = app(SmsService::class);
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($invitees as $invitee) {
            if ($this->hasAlreadyReceivedSms($invitee, $duplicateTypes)) {
                $skipped++;
                continue;
            }

            try {
                $message = EliveMessagePlaceholders::render($template, $invitee);

                $result = $smsService->sendCustomMessage(
                    invitee: $invitee,
                    message: $message,
                    type: $smsType,
                );

                if ((bool) ($result['success'] ?? false)) {
                    $sent++;
                } else {
                    $failed++;
                }
            } catch (Throwable $exception) {
                Log::error('Bulk SMS failed from Message Center', [
                    'event_id' => $this->record->id,
                    'invitee_id' => $invitee->id,
                    'sms_type' => $smsType,
                    'error' => $exception->getMessage(),
                ]);

                $failed++;
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'message' => null,
        ];
    }

    protected function notifyBulkSmsResult(string $title, array $result): void
    {
        $message = $result['message']
            ?: ($result['sent'] . ' sent/logged. ' . $result['failed'] . ' failed. ' . $result['skipped'] . ' skipped.');

        Notification::make()
            ->title($title)
            ->body($message)
            ->color(($result['failed'] ?? 0) > 0 ? 'warning' : 'success')
            ->send();
    }

    protected function hasAlreadyReceivedSms($invitee, array $types): bool
    {
        if ($types === [] || ! method_exists($invitee, 'smsLogs')) {
            return false;
        }

        return $invitee->smsLogs()
            ->where('event_id', $this->record->id)
            ->whereIn('sms_type', $types)
            ->whereIn('status', ['logged', 'accepted', 'sent', 'delivered'])
            ->exists();
    }

    protected function eligibleInviteesQuery(): Builder
    {
        return $this->record
            ->invitees()
            ->getQuery()
            ->with(['event', 'cardType'])
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->whereNotNull('short_code')
            ->where('short_code', '!=', '')
            ->whereHas(
                'generatedCards',
                fn (Builder $query): Builder => $query->where('status', 'generated')
            );
    }

    protected function basicSmsInviteesQuery(): Builder
    {
        return $this->record
            ->invitees()
            ->getQuery()
            ->with(['event', 'cardType'])
            ->whereNotNull('phone')
            ->where('phone', '!=', '');
    }

    protected function normalizePhone(?string $phone): string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);

        if (Str::startsWith($phone, '00255')) {
            return '255' . substr($phone, 5);
        }

        if (Str::startsWith($phone, '2550')) {
            return '255' . substr($phone, 4);
        }

        if (Str::startsWith($phone, '0')) {
            return '255' . substr($phone, 1);
        }

        if (Str::startsWith($phone, '7') || Str::startsWith($phone, '6')) {
            return '255' . $phone;
        }

        return $phone;
    }

    protected function buildWhatsappMessage($invitee): string
    {
        $template = "Habari #NAME#,\n\n"
            . "Umealikwa kwenye #EVENT_NAME#.\n\n"
            . "Tarehe: #EVENT_DATE#\n"
            . "Muda: #EVENT_TIME#\n"
            . "Ukumbi: #VENUE#\n\n"
            . "Fungua kadi yako hapa:\n#PRIVATE_INVITATION_URL#\n\n"
            . "Tafadhali thibitisha mahudhurio yako kupitia link hiyo.\n\n"
            . "eLive Card";

        return EliveMessagePlaceholders::render($template, $invitee);
    }

    protected function createMessageLog($invitee, string $phone, string $message): ?int
    {
        if (! Schema::hasTable('message_logs')) {
            return null;
        }

        $data = $this->filterColumns('message_logs', [
            'event_id' => $this->record->id,
            'invitee_id' => $invitee->id,
            'channel' => 'whatsapp',
            'type' => 'invitation',
            'phone' => $phone,
            'recipient' => $phone,
            'to' => $phone,
            'message' => $message,
            'body' => $message,
            'status' => 'sending',
            'payload' => json_encode(['message' => $message]),
            'provider_request' => json_encode(['message' => $message]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($data === []) {
            return null;
        }

        return DB::table('message_logs')->insertGetId($data);
    }

    protected function updateMessageLog(?int $logId, array $data): void
    {
        if (! $logId || ! Schema::hasTable('message_logs')) {
            return;
        }

        foreach (['response', 'provider_response', 'meta'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $data[$key] = json_encode($data[$key]);
            }
        }

        if (isset($data['response']) && ! isset($data['provider_response'])) {
            $data['provider_response'] = $data['response'];
        }

        $data['updated_at'] = now();

        DB::table('message_logs')
            ->where('id', $logId)
            ->update($this->filterColumns('message_logs', $data));
    }

    protected function filterColumns(string $table, array $data): array
    {
        return collect($data)
            ->filter(fn ($value, $column) => Schema::hasColumn($table, $column))
            ->all();
    }

    public function getInviteesCountProperty(): int
    {
        return $this->record->invitees()->count();
    }

    public function getGeneratedCardsCountProperty(): int
    {
        return $this->record->generatedCards()->where('status', 'generated')->count();
    }

    public function getMissingCardsCountProperty(): int
    {
        return $this->record->invitees()->whereDoesntHave('generatedCards', fn (Builder $query): Builder => $query->where('status', 'generated'))->count();
    }

    public function getEligibleSmsInviteesCountProperty(): int
    {
        return $this->eligibleInviteesQuery()->count();
    }

    public function getUnsentEligibleSmsInviteesCountProperty(): int
    {
        return $this->eligibleInviteesQuery()
            ->whereDoesntHave('smsLogs', function ($query): void {
                $query
                    ->where('event_id', $this->record->id)
                    ->whereIn('sms_type', ['invitation', 'invitation_card'])
                    ->whereIn('status', ['queued', 'pending', 'sending', 'sent', 'logged', 'accepted', 'delivered']);
            })
            ->count();
    }

    public function getUnsentEligibleWhatsappInviteesCountProperty(): int
    {
        return $this->eligibleInviteesQuery()
            ->whereDoesntHave('messageLogs', function ($query): void {
                $query
                    ->where('event_id', $this->record->id)
                    ->whereIn('channel', ['whatsapp', 'WhatsApp'])
                    ->whereIn('type', ['invitation', 'event_invitation', 'whatsapp_invitation'])
                    ->whereIn('status', ['queued', 'pending', 'sending', 'sent', 'accepted', 'delivered', 'read']);
            })
            ->count();
    }

    public function getAttendingCountProperty(): int
    {
        return $this->record->invitees()->where('rsvp_status', 'attending')->count();
    }

    public function getPendingRsvpCountProperty(): int
    {
        return $this->record->invitees()
            ->where(fn ($query) => $query->whereNull('rsvp_status')->orWhere('rsvp_status', '')->orWhere('rsvp_status', 'pending'))
            ->count();
    }

    public function getNotAttendingCountProperty(): int
    {
        return $this->record->invitees()->where('rsvp_status', 'not_attending')->count();
    }

    public function getCheckedInInviteesCountProperty(): int
    {
        return $this->record->invitees()
            ->where(function ($query): void {
                $query
                    ->whereNotNull('checked_in_at')
                    ->orWhere('checked_in_count', '>', 0);
            })
            ->count();
    }

    public function getSentSmsCountProperty(): int
    {
        return method_exists($this->record, 'smsLogs')
            ? $this->record->smsLogs()->whereIn('status', ['sent', 'logged', 'accepted', 'delivered'])->count()
            : 0;
    }

    public function getQueuedSmsCountProperty(): int
    {
        return method_exists($this->record, 'smsLogs')
            ? $this->record->smsLogs()->whereIn('status', ['queued', 'pending', 'sending'])->count()
            : 0;
    }

    public function getFailedSmsCountProperty(): int
    {
        return method_exists($this->record, 'smsLogs') ? $this->record->smsLogs()->where('status', 'failed')->count() : 0;
    }

    public function getSentMessagesCountProperty(): int
    {
        return method_exists($this->record, 'messageLogs')
            ? $this->record->messageLogs()->whereIn('status', ['sent', 'accepted', 'delivered', 'read'])->count()
            : 0;
    }

    public function getFailedMessagesCountProperty(): int
    {
        return method_exists($this->record, 'messageLogs')
            ? $this->record->messageLogs()->where('status', 'failed')->count()
            : 0;
    }

    public function getEventNameProperty(): string
    {
        return $this->record->title ?? $this->record->name ?? 'Event';
    }

    public function getEventDateProperty(): string
    {
        return $this->record->event_date ? $this->record->event_date->format('d M Y') : '-';
    }

    public function getEventVenueProperty(): string
    {
        return $this->record->venue_name ?? $this->record->venue ?? $this->record->venue_address ?? '-';
    }
}
