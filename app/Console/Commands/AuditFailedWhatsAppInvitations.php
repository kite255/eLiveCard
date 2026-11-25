<?php

namespace App\Console\Commands;

use App\Models\Invitee;
use App\Models\MessageTemplate;
use App\Support\WhatsAppPhone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditFailedWhatsAppInvitations extends Command
{
    protected $signature = 'whatsapp:audit-failed-invitations {--event= : Event ID} {--template= : Template ID}';

    protected $description = 'Read-only safety audit for WhatsApp invitation claims failed due to disabled configuration';

    public function handle(): int
    {
        $eventId = filter_var($this->option('event'), FILTER_VALIDATE_INT);
        $templateId = filter_var($this->option('template'), FILTER_VALIDATE_INT);

        if (! $eventId || ! $templateId || $eventId < 1 || $templateId < 1) {
            $this->error('Positive --event and --template values are required.');
            return self::FAILURE;
        }

        if (! Schema::hasTable('whatsapp_template_sends') || ! Schema::hasTable('message_logs')) {
            $this->error('Required WhatsApp tracking or message log table is missing.');
            return self::FAILURE;
        }

        $template = MessageTemplate::query()
            ->whereKey($templateId)
            ->forEventOrGlobal($eventId)
            ->where('channel', 'whatsapp')
            ->where('type', MessageTemplate::TYPE_INVITATION)
            ->first();

        if (! $template) {
            $this->error('The requested WhatsApp invitation template is not available for this event.');
            return self::FAILURE;
        }

        $rows = DB::table('whatsapp_template_sends as w')
            ->leftJoin('invitees as i', function ($join): void {
                $join->on('i.id', '=', 'w.invitee_id')
                    ->on('i.event_id', '=', 'w.event_id');
            })
            ->where('w.event_id', $eventId)
            ->where('w.message_template_id', $templateId)
            ->where('w.status', 'failed')
            ->select([
                'w.id', 'w.invitee_id', 'w.phone_generation', 'w.last_error',
                'i.id as current_invitee_id', 'i.phone', 'i.card_status',
                'i.invitation_phone_generation', 'i.whatsapp_status', 'i.whatsapp_sent_at',
            ])->get();

        $successIds = DB::table('message_logs')
            ->where('event_id', $eventId)
            ->where('channel', 'whatsapp')
            ->where('type', MessageTemplate::TYPE_INVITATION)
            ->whereIn('status', ['submitted', 'accepted', 'sent', 'delivered', 'read'])
            ->pluck('invitee_id')
            ->filter(fn ($id) => is_int($id) || (is_string($id) && $id !== ''))
            ->mapWithKeys(fn ($id) => [(string) $id => true]);

        $otherClaims = DB::table('whatsapp_template_sends')
            ->where('event_id', $eventId)
            ->whereIn('status', ['submitted', 'pending'])
            ->get(['id', 'invitee_id', 'status'])
            ->groupBy('invitee_id');

        $otherSuccessfulPhoneIds = DB::table('message_logs')
            ->where('event_id', $eventId)
            ->where('channel', 'whatsapp')
            ->where('type', MessageTemplate::TYPE_INVITATION)
            ->whereIn('status', ['submitted', 'accepted', 'sent', 'delivered', 'read'])
            ->pluck('invitee_id')->filter()->unique()->values()->all();

        $successfulPhones = DB::table('invitees')->where('event_id', $eventId)
            ->whereIn('id', $otherSuccessfulPhoneIds)->pluck('phone')
            ->map(fn ($phone) => WhatsAppPhone::normalize($phone))
            ->filter(fn ($phone) => is_string($phone) && $phone !== '')
            ->mapWithKeys(fn ($phone) => [$phone => true]);

        $counters = [
            'Failed claims' => $rows->count(),
            'Disabled-configuration failures' => 0,
            'Different failure reason' => 0,
            'Missing or inactive invitee' => 0,
            'Changed phone generation' => 0,
            'Missing or invalid normalized phone' => 0,
            'Existing successful invitation log' => 0,
            'Other submitted or pending claim' => 0,
            'Other recorded WhatsApp sent state' => 0,
            'Phone matches another successfully logged invitee' => 0,
            'Candidates before normalized-phone deduplication' => 0,
            'Normalized-phone collisions among candidates' => 0,
        ];

        $phones = [];
        foreach ($rows as $row) {
            if ($row->last_error === 'WhatsApp sending is disabled. Set WHATSAPP_ENABLED=true.') {
                $counters['Disabled-configuration failures']++;
            } else {
                $counters['Different failure reason']++;
                continue;
            }

            if (! $row->current_invitee_id || $row->card_status !== Invitee::CARD_STATUS_ACTIVE) {
                $counters['Missing or inactive invitee']++;
                continue;
            }

            if ((int) $row->phone_generation !== (int) $row->invitation_phone_generation) {
                $counters['Changed phone generation']++;
                continue;
            }

            $phone = WhatsAppPhone::normalize($row->phone);
            if (blank($phone)) {
                $counters['Missing or invalid normalized phone']++;
                continue;
            }

            if (isset($successIds[$row->invitee_id])) {
                $counters['Existing successful invitation log']++;
                continue;
            }

            if (($otherClaims->get($row->invitee_id) ?? collect())
                ->contains(fn ($claim) => (int) $claim->id !== (int) $row->id)) {
                $counters['Other submitted or pending claim']++;
                continue;
            }

            if ($row->whatsapp_sent_at !== null
                || in_array($row->whatsapp_status, ['submitted', 'accepted', 'sent', 'delivered', 'read'], true)) {
                $counters['Other recorded WhatsApp sent state']++;
                continue;
            }

            if (isset($successfulPhones[$phone])) {
                $counters['Phone matches another successfully logged invitee']++;
                continue;
            }

            $counters['Candidates before normalized-phone deduplication']++;
            $phones[$phone] = ($phones[$phone] ?? 0) + 1;
        }

        $counters['Normalized-phone collisions among candidates'] =
            array_sum(array_map(fn (int $count): int => max(0, $count - 1), $phones));

        $this->info("Read-only audit — event {$eventId}, template {$templateId}");
        $this->table(['Check', 'Count'], collect($counters)
            ->map(fn ($count, $label) => [$label, $count])->values()->all());
        $this->warn('No claims were modified and no messages were sent. This audit does not verify Meta delivery or authorize bulk retries.');

        return self::SUCCESS;
    }
}
