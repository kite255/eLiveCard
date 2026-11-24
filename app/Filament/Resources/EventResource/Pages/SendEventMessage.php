<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Jobs\GenerateInviteeCardJob;
use App\Jobs\SendInvitationSmsJob;
use App\Jobs\SendEventSmsJob;
use App\Jobs\SendInvitationWhatsAppJob;
use App\Models\MessageLog;
use Illuminate\Support\Facades\Cache;
use App\Models\MessageTemplate;
use App\Services\SmsService;
use App\Support\EliveMessagePlaceholders;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
                ->color('primary')
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
                ->modalDescription('This will queue real WhatsApp invitations for background delivery using WhatsApp Cloud API.')
                ->modalSubmitActionLabel('Queue WhatsApp')
                ->disabled(fn (): bool => $this->unsentEligibleWhatsappInviteesCount === 0)
                ->action(fn () => $this->sendWhatsappInvitations()),

            Action::make('sendFailedWhatsappSms')
                ->label('SMS Fallback for Failed WhatsApp')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Send SMS to failed WhatsApp recipients')
                ->modalDescription('Queue one invitation SMS per eligible invitee whose latest WhatsApp invitation failed. Pending, submitted, delivered, or read WhatsApp messages are excluded. Existing SMS invitations will not be resent.')
                ->modalSubmitActionLabel('Queue Fallback SMS')
                ->disabled(fn (): bool => $this->failedWhatsappSmsEligibleCount === 0)
                ->action(fn () => $this->sendFailedWhatsappSms()),

            Action::make('sendRsvpReminderSms')
                ->label('RSVP Reminder')
                ->icon('heroicon-o-bell-alert')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Send RSVP Reminder SMS')
                ->modalDescription('Queue reminder SMS for invitees whose RSVP is still pending.')
                ->modalSubmitActionLabel('Send Reminder')
                ->disabled(fn (): bool => $this->pendingRsvpCount === 0)
                ->action(fn () => $this->sendRsvpReminderSms()),

            Action::make('sendEventDayReminderSms')
                ->label('Event Day Reminder')
                ->icon('heroicon-o-calendar-days')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Send Event Day Reminder SMS')
                ->modalDescription('Queue final reminder SMS for invitees who are attending.')
                ->modalSubmitActionLabel('Send Final Reminder')
                ->disabled(fn (): bool => $this->attendingCount === 0)
                ->action(fn () => $this->sendEventDayReminderSms()),

            Action::make('sendThankYouSms')
                ->label('Thank You SMS')
                ->icon('heroicon-o-heart')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Send Thank You SMS')
                ->modalDescription('Queue thank-you SMS for checked-in invitees only.')
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
        if (! (bool) config('services.whatsapp.enabled', false)) {
            Notification::make()
                ->title('WhatsApp not configured')
                ->body('Set WHATSAPP_ENABLED=true and configure the WhatsApp Cloud API credentials.')
                ->danger()
                ->send();

            return;
        }

        if (
            config('services.whatsapp.driver') !== 'meta_cloud_api'
            || blank(config('services.whatsapp.access_token'))
            || blank(config('services.whatsapp.phone_number_id'))
        ) {
            Notification::make()
                ->title('WhatsApp configuration incomplete')
                ->body('Set WHATSAPP_DRIVER=meta_cloud_api, WHATSAPP_ACCESS_TOKEN, and WHATSAPP_PHONE_NUMBER_ID.')
                ->danger()
                ->send();

            return;
        }

        $template = MessageTemplate::query()
            ->where('channel', MessageTemplate::CHANNEL_WHATSAPP)
            ->where('type', MessageTemplate::TYPE_INVITATION)
            ->where('status', MessageTemplate::STATUS_ACTIVE)
            ->whereNotNull('whatsapp_template_name')
            ->where('whatsapp_template_name', '!=', '')
            ->whereNotNull('whatsapp_language_code')
            ->where('whatsapp_language_code', '!=', '')
            ->where(function (Builder $query): void {
                $query
                    ->where('event_id', $this->record->id)
                    ->orWhereNull('event_id');
            })
            ->orderByRaw(
                'CASE WHEN event_id = ? THEN 0 ELSE 1 END',
                [$this->record->id]
            )
            ->latest('updated_at')
            ->first();

        if (! $template) {
            Notification::make()
                ->title('WhatsApp template missing')
                ->body('Create and activate a WhatsApp invitation template for this event before sending.')
                ->danger()
                ->send();

            return;
        }

        $languageCode = filled($template->whatsapp_language_code)
            ? (string) $template->whatsapp_language_code
            : (string) config('services.whatsapp.template_language', MessageTemplate::LANGUAGE_ENGLISH);

        $invitees = $this->eligibleInviteesQuery()
            ->whereDoesntHave('messageLogs', function ($query): void {
                $query
                    ->where('event_id', $this->record->id)
                    ->whereIn('channel', ['whatsapp', 'WhatsApp'])
                    ->whereIn('type', ['invitation', 'event_invitation', 'whatsapp_invitation'])
                    ->whereIn('status', ['queued', 'pending', 'sending', 'sent', 'accepted', 'delivered', 'read']);
            })
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('whatsapp_status')
                    ->orWhereNotIn('whatsapp_status', [
                        'queued',
                        'submitted',
                        'accepted',
                        'sent',
                        'delivered',
                        'read',
                    ]);
            })
            ->get();

        if ($invitees->isEmpty()) {
            Notification::make()
                ->title('No WhatsApp messages queued')
                ->body('No eligible invitees found, or WhatsApp invitations were already sent.')
                ->warning()
                ->send();

            return;
        }

        if (! Schema::hasTable('whatsapp_template_sends')) {
            Notification::make()->title('WhatsApp tracking unavailable')
                ->body('Run the WhatsApp tracking migrations before sending invitations.')
                ->danger()->send();

            return;
        }

        $queued = 0;
        $skipped = 0;

        DB::transaction(function () use ($invitees, $languageCode, $template, &$queued, &$skipped): void {
            foreach ($invitees as $invitee) {
                $claimed = DB::table('whatsapp_template_sends')->insertOrIgnore([
                    'event_id' => $invitee->event_id,
                    'invitee_id' => $invitee->id,
                    'message_template_id' => $template->id,
                    'phone_generation' => (int) $invitee->invitation_phone_generation,
                    'status' => 'pending',
                    'attempts' => 1,
                    'last_attempt_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($claimed !== 1) {
                    $skipped++;

                    continue;
                }

                SendInvitationWhatsAppJob::dispatch(
                    inviteeId: $invitee->id,
                    languageCode: $languageCode,
                    messageTemplateId: $template->id,
                    phoneGeneration: (int) $invitee->invitation_phone_generation,
                )->afterCommit();

                $invitee->forceFill([
                    'last_message_channel' => 'whatsapp',
                    'last_message_status' => 'queued',
                    'message_status' => 'queued',
                    'whatsapp_status' => 'queued',
                ])->saveQuietly();

                $queued++;
            }
        });

        Notification::make()
            ->title($queued ? 'WhatsApp invitations queued' : 'No new WhatsApp invitations queued')
            ->body($queued . ' queued; ' . $skipped . ' already claimed.')
            ->success()
            ->send();
    }

    /**
     * An older failed attempt is not a fallback candidate if a newer
     * WhatsApp invitation is pending or has been submitted/delivered.
     * A failed status must come from the persisted provider/log status;
     * we never infer that the number is unregistered on WhatsApp.
     */
    protected function latestFailedWhatsappInvitationLogs(): \Illuminate\Support\Collection
    {
        $invitationTypes = ['invitation', 'event_invitation', 'whatsapp_invitation'];

        $latestIds = MessageLog::query()
            ->selectRaw('MAX(id)')
            ->where('event_id', $this->record->id)
            ->where('channel', MessageLog::CHANNEL_WHATSAPP)
            ->whereIn('type', $invitationTypes)
            ->whereNotNull('invitee_id')
            ->groupBy('invitee_id');

        return MessageLog::query()
            ->whereIn('id', $latestIds)
            ->where('status', MessageLog::STATUS_FAILED)
            ->orderByDesc('id')
            ->get();
    }

    protected function failedWhatsappSmsRecipients(): \Illuminate\Support\Collection
    {
        $failedLogs = $this->latestFailedWhatsappInvitationLogs()
            ->keyBy('invitee_id');

        if ($failedLogs->isEmpty()) {
            return collect();
        }

        return $this->eligibleInviteesQuery()
            ->whereIn('id', $failedLogs->keys()->all())
            ->where('card_status', \App\Models\Invitee::CARD_STATUS_ACTIVE)
            ->where('whatsapp_status', 'failed')
            ->whereDoesntHave('smsLogs', function (Builder $query): void {
                $query->where('event_id', $this->record->id)
                    ->whereIn('sms_type', ['invitation', 'invitation_card'])
                    ->whereIn('status', ['queued', 'pending', 'sending', 'sent', 'logged', 'accepted', 'delivered']);
            })
            ->get()
            ->map(function ($invitee) use ($failedLogs) {
                $invitee->setRelation('failedWhatsappLog', $failedLogs->get($invitee->id));

                return $invitee;
            });
    }

    public function getFailedWhatsappSmsEligibleCountProperty(): int
    {
        return $this->failedWhatsappSmsRecipients()->count();
    }

    public function getFailedWhatsappSmsPreviewProperty(): \Illuminate\Support\Collection
    {
        return $this->failedWhatsappSmsRecipients()->take(10);
    }

    public function sendFailedWhatsappSms(): void
    {
        $queued = 0;
        $skipped = 0;

        foreach ($this->failedWhatsappSmsRecipients() as $invitee) {
            // An atomic, short-lived claim protects against rapid double-clicks
            // and concurrent administrative fallback actions.
            $claim = Cache::lock(
                'whatsapp-sms-fallback-'.$this->record->id.'-'.$invitee->id,
                600
            );

            if (! $claim->get()) {
                $skipped++;
                continue;
            }

            $dispatched = false;
            try {
                // Re-evaluate the latest WhatsApp status before dispatch.
                $latest = MessageLog::query()
                    ->where('event_id', $this->record->id)
                    ->where('invitee_id', $invitee->id)
                    ->where('channel', MessageLog::CHANNEL_WHATSAPP)
                    ->whereIn('type', ['invitation', 'event_invitation', 'whatsapp_invitation'])
                    ->latest('id')
                    ->first();

                if (! $latest || $latest->status !== MessageLog::STATUS_FAILED
                    || $invitee->fresh()?->whatsapp_status !== 'failed'
                    || $invitee->smsLogs()
                        ->where('event_id', $this->record->id)
                        ->whereIn('sms_type', ['invitation', 'invitation_card'])
                        ->whereIn('status', ['queued', 'pending', 'sending', 'sent', 'logged', 'accepted', 'delivered'])
                        ->exists()) {
                    $skipped++;
                    continue;
                }

                SendInvitationSmsJob::dispatch(
                    eventId: $this->record->id,
                    inviteeId: $invitee->id,
                );
                $dispatched = true;
                $queued++;
            } finally {
                // Only retain the temporary claim after a successful dispatch.
                // Failed or skipped attempts must remain recoverable.
                if (! $dispatched) {
                    $claim->release();
                }
            }
        }

        Notification::make()
            ->title('WhatsApp fallback SMS queued')
            ->body($queued.' invitation SMS job(s) queued. '.$skipped.' skipped. SMS delivery is not yet confirmed.')
            ->color($queued > 0 ? 'success' : 'warning')
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

    /**
     * Queue one job per invitee so bulk sends do not block the admin request.
     * Actual submitted/delivered totals belong to SMS logs, not this response.
     */
    protected function sendBulkSms($invitees, string $template, string $smsType, array $duplicateTypes = []): array
    {
        $queued = 0;
        $skipped = 0;

        foreach ($invitees as $invitee) {
            if ($this->hasAlreadyReceivedSms($invitee, $duplicateTypes)) {
                $skipped++;
                continue;
            }

            SendEventSmsJob::dispatch(
                eventId: $this->record->id,
                inviteeId: $invitee->id,
                message: $template,
                smsType: $smsType,
            );
            $queued++;
        }

        return [
            'queued' => $queued,
            'skipped' => $skipped,
        ];
    }

    protected function notifyBulkSmsResult(string $title, array $result): void
    {
        Notification::make()
            ->title(str_replace('completed', 'queued', $title))
            ->body($result['queued'].' SMS job(s) queued; '.$result['skipped'].' skipped. Delivery is not yet confirmed.')
            ->color($result['queued'] > 0 ? 'success' : 'warning')
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
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('whatsapp_status')
                    ->orWhereNotIn('whatsapp_status', [
                        'queued',
                        'submitted',
                        'accepted',
                        'sent',
                        'delivered',
                        'read',
                    ]);
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
