<?php

namespace App\Console\Commands;

use App\Jobs\SendInvitationWhatsAppJob;
use App\Models\Invitee;
use App\Models\MessageTemplate;
use App\Services\AuditLogService;
use App\Support\WhatsAppPhone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class RecoverOneFailedWhatsAppInvitation extends Command
{
    protected $signature = 'whatsapp:recover-one-failed-invitation
        {--event= : Event ID}
        {--template= : Invitation template ID}
        {--invitee= : Explicit invitee ID}
        {--execute : Queue one message after explicit confirmation}
        {--provider-verified : Operator has verified this recipient was not accepted by Meta}';

    protected $description = 'Dry-run by default; optionally queue ONE verified failed-config WhatsApp invitation';

    private const DISABLED_ERROR = 'WhatsApp sending is disabled. Set WHATSAPP_ENABLED=true.';

    public function handle(): int
    {
        $event = filter_var($this->option('event'), FILTER_VALIDATE_INT);
        $templateId = filter_var($this->option('template'), FILTER_VALIDATE_INT);
        $inviteeId = filter_var($this->option('invitee'), FILTER_VALIDATE_INT);
        if (! $event || ! $templateId || ! $inviteeId
            || $event < 1 || $templateId < 1 || $inviteeId < 1) {
            $this->error('Provide positive --event, --template and --invitee IDs.');
            return self::FAILURE;
        }

        if (! Schema::hasTable('whatsapp_template_sends')
            || ! Schema::hasTable('message_logs')
            || ! Schema::hasTable('jobs')) {
            $this->error('Tracking, message log or database jobs table is missing.');
            return self::FAILURE;
        }

        $template = MessageTemplate::query()
            ->whereKey($templateId)->forEventOrGlobal($event)
            ->where('channel', MessageTemplate::CHANNEL_WHATSAPP)
            ->where('type', MessageTemplate::TYPE_INVITATION)
            ->where('status', MessageTemplate::STATUS_ACTIVE)
            ->first();

        if (! $template || blank($template->whatsapp_template_name)) {
            $this->error('Active WhatsApp invitation template with a Meta name is required.');
            return self::FAILURE;
        }

        try {
            $this->verifyCandidate($event, $templateId, $inviteeId);

            $this->line("Eligible single-recipient recovery: event {$event}, template {$templateId}, invitee {$inviteeId}.");
            if (! $this->option('execute')) {
                $this->warn('DRY RUN: No tracking changes, no queue job, no WhatsApp send.');
                return self::SUCCESS;
            }

            if (! $this->option('provider-verified')) {
                $this->error('--provider-verified is required after verifying this specific recipient with Meta.');
                return self::FAILURE;
            }

            if (config('queue.default') !== 'database'
                || ! config('services.whatsapp.enabled')
                || config('services.whatsapp.driver') !== 'meta_cloud_api'
                || blank(config('services.whatsapp.access_token'))
                || blank(config('services.whatsapp.phone_number_id'))) {
                $this->error('A working database queue and fully configured Meta Cloud API are required.');
                return self::FAILURE;
            }

            if (! $this->confirm(
                "Queue exactly ONE WhatsApp invitation for invitee {$inviteeId}? Meta/provider records must already be checked.",
                false
            )) {
                return self::FAILURE;
            }

            $queue = (string) config('messaging.whatsapp_queue', SendInvitationWhatsAppJob::QUEUE);
            $jobQueued = false;
            DB::transaction(function () use ($event, $templateId, $inviteeId, $template, $queue, &$jobQueued): void {
                $claim = DB::table('whatsapp_template_sends')
                    ->where('event_id', $event)
                    ->where('message_template_id', $templateId)
                    ->where('invitee_id', $inviteeId)
                    ->lockForUpdate()->first();

                if (! $claim || $claim->status !== 'failed'
                    || $claim->last_error !== self::DISABLED_ERROR) {
                    throw new RuntimeException('Failed-config claim changed; refusing recovery.');
                }

                $candidate = $this->verifyCandidate($event, $templateId, $inviteeId);
                if ((int) $claim->phone_generation !== (int) $candidate->invitation_phone_generation) {
                    throw new RuntimeException('Phone generation changed; refusing recovery.');
                }

                // Audit before dispatch; rollback the transaction if the audit record cannot be written.
                $audit = AuditLogService::system(
                    action: 'whatsapp.failed_invitation_recovery_queued',
                    description: 'Operator authorized single-recipient recovery of a disabled-configuration failure.',
                    eventId: $event,
                    metadata: [
                        'invitee_id' => $inviteeId,
                        'template_id' => $templateId,
                        'tracking_id' => $claim->id,
                        'previous_error' => self::DISABLED_ERROR,
                    ],
                );
                if (! $audit) {
                    throw new RuntimeException('Audit-log persistence failed; recovery cancelled.');
                }

                $changed = DB::table('whatsapp_template_sends')
                    ->where('id', $claim->id)
                    ->where('status', 'failed')
                    ->where('last_error', self::DISABLED_ERROR)
                    ->update([
                        'status' => 'pending',
                        // Preserve the original failure reason until the job writes its final result.
                        'last_attempt_at' => now(),
                        'attempts' => DB::raw('attempts + 1'),
                        'updated_at' => now(),
                    ]);
                if ($changed !== 1) {
                    throw new RuntimeException('Tracking claim was modified concurrently.');
                }

                $before = DB::table('jobs')->where('queue', $queue)->count();
                SendInvitationWhatsAppJob::dispatch(
                    inviteeId: $inviteeId,
                    languageCode: filled($template->whatsapp_language_code)
                        ? (string) $template->whatsapp_language_code
                        : MessageTemplate::LANGUAGE_ENGLISH,
                    messageTemplateId: $templateId,
                    phoneGeneration: (int) $candidate->invitation_phone_generation,
                )->onConnection('database')->onQueue($queue)->beforeCommit();

                if (DB::table('jobs')->where('queue', $queue)->count() !== $before + 1) {
                    throw new RuntimeException('Job was not inserted; possible unique lock. Rolled back.');
                }
                $jobQueued = true;
            }, 3);

            if ($jobQueued) {
                $this->info("ONE invitation job queued for invitee {$inviteeId}. Provider delivery is not yet confirmed.");
            }
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Recovery refused: '.$e->getMessage());
            return self::FAILURE;
        }
    }

    private function verifyCandidate(int $event, int $templateId, int $inviteeId): Invitee
    {
        $invitee = Invitee::query()
            ->whereKey($inviteeId)->where('event_id', $event)->first();
        if (! $invitee || $invitee->card_status !== Invitee::CARD_STATUS_ACTIVE) {
            throw new RuntimeException('Invitee missing or card inactive.');
        }

        $claim = DB::table('whatsapp_template_sends')
            ->where('event_id', $event)
            ->where('message_template_id', $templateId)
            ->where('invitee_id', $inviteeId)->first();

        if (! $claim || $claim->status !== 'failed'
            || $claim->last_error !== self::DISABLED_ERROR
            || (int) $claim->phone_generation !== (int) $invitee->invitation_phone_generation) {
            throw new RuntimeException('No matching current disabled-configuration failure.');
        }

        $phone = WhatsAppPhone::normalize($invitee->phone);
        if (blank($phone)) {
            throw new RuntimeException('Invalid normalized phone number.');
        }

        if ($invitee->whatsapp_sent_at
            || in_array($invitee->whatsapp_status, ['submitted', 'accepted', 'sent', 'delivered', 'read'], true)) {
            throw new RuntimeException('Invitee has a sent WhatsApp state.');
        }

        if (DB::table('whatsapp_template_sends')
            ->where('event_id', $event)
            ->where('invitee_id', $inviteeId)
            ->where('id', '!=', $claim->id)
            ->whereIn('status', ['submitted', 'pending'])->exists()) {
            throw new RuntimeException('Another submitted or pending claim exists.');
        }

        $successful = DB::table('message_logs')
            ->where('event_id', $event)
            ->where('channel', 'whatsapp')
            ->where('type', MessageTemplate::TYPE_INVITATION)
            ->whereIn('status', ['submitted', 'accepted', 'sent', 'delivered', 'read'])
            ->get(['invitee_id', 'phone']);

        $sentIds = $successful->pluck('invitee_id')->filter()->unique()->all();
        $otherPhones = DB::table('invitees')->where('event_id', $event)
            ->whereIn('id', $sentIds)->pluck('phone');

        if ($successful->contains(fn ($log) => (int) ($log->invitee_id ?? 0) === $inviteeId)
            || $successful->pluck('phone')->merge($otherPhones)
                ->contains(fn ($stored) => WhatsAppPhone::normalize($stored) === $phone)) {
            throw new RuntimeException('A successful invitation log matches the invitee or recipient phone.');
        }

        // Identical numbers may be stored under other invitees; require human review.
        if (DB::table('invitees')->where('event_id', $event)
            ->where('id', '!=', $inviteeId)->pluck('phone')
            ->contains(fn ($stored) => WhatsAppPhone::normalize($stored) === $phone)) {
            throw new RuntimeException('Another invitee has the same normalized phone; manual review needed.');
        }

        return $invitee;
    }
}
