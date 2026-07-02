<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Jobs\GenerateInviteeCardJob;
use App\Jobs\SendInvitationSmsJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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
        return 'Send invitations, generate missing cards, monitor SMS delivery, and send real WhatsApp invitations for this event.';
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
        ];
    }

    public function generateMissingCards(): void
    {
        $invitees = $this->record
            ->invitees()
            ->whereDoesntHave('generatedCards', fn ($query) => $query->where('status', 'generated'))
            ->get();

        if ($invitees->isEmpty()) {
            Notification::make()->title('No missing cards')->body('All invitees already have generated cards.')->warning()->send();
            return;
        }

        foreach ($invitees as $invitee) {
            GenerateInviteeCardJob::dispatch($invitee->id);
        }

        Notification::make()->title('Card generation queued')->body($invitees->count() . ' invitee card(s) queued.')->success()->send();
    }

    public function sendSmsInvitations(): void
    {
        $invitees = $this->eligibleInviteesQuery()->get();

        if ($invitees->isEmpty()) {
            Notification::make()->title('No eligible invitees')->body('No invitees have phone number, private link, and generated card.')->danger()->send();
            return;
        }

        $queued = 0;
        $skipped = 0;

        DB::transaction(function () use ($invitees, &$queued, &$skipped): void {
            foreach ($invitees as $invitee) {
                $alreadySentOrQueued = method_exists($invitee, 'smsLogs')
                    ? $invitee->smsLogs()
                        ->where('event_id', $this->record->id)
                        ->where('sms_type', 'invitation')
                        ->whereIn('status', ['queued', 'pending', 'sending', 'sent'])
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
            $link = url('/i/' . $invitee->short_code);

            $message = $this->buildWhatsappMessage($invitee, $link);

            $logId = $this->createMessageLog($invitee, $phone, $message);

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
        }

        Notification::make()
            ->title('WhatsApp sending completed')
            ->body($sent . ' sent. ' . $failed . ' failed.')
            ->success()
            ->send();
    }

    protected function eligibleInviteesQuery()
    {
        return $this->record
            ->invitees()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->whereNotNull('short_code')
            ->where('short_code', '!=', '')
            ->whereHas('generatedCards', fn ($query) => $query->where('status', 'generated'));
    }

    protected function normalizePhone(?string $phone): string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);

        if (Str::startsWith($phone, '0')) {
            return '255' . substr($phone, 1);
        }

        if (Str::startsWith($phone, '7') || Str::startsWith($phone, '6')) {
            return '255' . $phone;
        }

        return $phone;
    }

    protected function buildWhatsappMessage($invitee, string $link): string
    {
        $eventName = $this->eventName;
        $eventDate = $this->eventDate;
        $venue = $this->eventVenue;

        return "Habari {$invitee->name},\n\n"
            . "Umealikwa kwenye {$eventName}.\n\n"
            . "Tarehe: {$eventDate}\n"
            . "Ukumbi: {$venue}\n\n"
            . "Fungua kadi yako hapa:\n{$link}\n\n"
            . "Tafadhali thibitisha mahudhurio yako kupitia link hiyo.\n\n"
            . "eLive Card";
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
            'message' => $message,
            'status' => 'sending',
            'payload' => json_encode(['message' => $message]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('message_logs')->insertGetId($data);
    }

    protected function updateMessageLog(?int $logId, array $data): void
    {
        if (! $logId || ! Schema::hasTable('message_logs')) {
            return;
        }

        if (isset($data['response']) && is_array($data['response'])) {
            $data['response'] = json_encode($data['response']);
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

    public function sendRsvpReminderSms(): void
    {
        Notification::make()->title('RSVP reminder coming next')->body('This button will send SMS reminders to pending RSVP invitees.')->info()->send();
    }

    public function sendEventDayReminderSms(): void
    {
        Notification::make()->title('Event day reminder coming next')->body('This button will send final SMS reminders.')->info()->send();
    }

    public function sendThankYouSms(): void
    {
        Notification::make()->title('Thank you SMS coming next')->body('This button will send thank-you SMS after the event.')->info()->send();
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
        return $this->record->invitees()->whereDoesntHave('generatedCards', fn ($query) => $query->where('status', 'generated'))->count();
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
                    ->where('sms_type', 'invitation')
                    ->whereIn('status', ['queued', 'pending', 'sending', 'sent']);
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

    public function getSentSmsCountProperty(): int
    {
        return method_exists($this->record, 'smsLogs') ? $this->record->smsLogs()->where('status', 'sent')->count() : 0;
    }

    public function getQueuedSmsCountProperty(): int
    {
        return method_exists($this->record, 'smsLogs') ? $this->record->smsLogs()->whereIn('status', ['queued', 'pending', 'sending'])->count() : 0;
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