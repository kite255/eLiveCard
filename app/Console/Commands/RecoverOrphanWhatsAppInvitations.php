<?php

namespace App\Console\Commands;

use App\Jobs\SendInvitationWhatsAppJob;
use App\Models\Invitee;
use App\Models\MessageTemplate;
use App\Support\WhatsAppPhone;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class RecoverOrphanWhatsAppInvitations extends Command
{
    protected $signature = 'whatsapp:recover-orphan-invitations
        {--event= : Event ID}
        {--template= : WhatsApp template ID}
        {--execute : Enqueue verified orphan claims instead of performing a dry run}
        {--provider-verified : Confirm Meta/provider records have been checked for every intended recipient}
        {--expected= : Expected exact number of eligible claims to enqueue}';

    protected $description = 'Review orphan WhatsApp invitation claims and explicitly recover verified unsent claims without resetting them';

    public function handle(): int
    {
        $eventId = filter_var($this->option('event'), FILTER_VALIDATE_INT);
        $templateId = filter_var($this->option('template'), FILTER_VALIDATE_INT);

        if (! $eventId || ! $templateId || $eventId < 1 || $templateId < 1) {
            $this->error('Provide a positive --event and --template.');
            return self::FAILURE;
        }

        if (! Schema::hasTable('whatsapp_template_sends') || ! Schema::hasTable('message_logs')) {
            $this->error('Required tracking tables are missing.');
            return self::FAILURE;
        }

        $template = MessageTemplate::query()
            ->whereKey($templateId)->forEventOrGlobal($eventId)
            ->where('channel', 'whatsapp')->where('type', MessageTemplate::TYPE_INVITATION)
            ->first();
        if (! $template) {
            $this->error('Matching event invitation template not found.');
            return self::FAILURE;
        }

        $queue = (string) config('messaging.whatsapp_queue', SendInvitationWhatsAppJob::QUEUE);
        if ($this->option('execute') && config('queue.default') !== 'database') {
            $this->error('Recovery requires the database queue connection for atomic job creation.');
            return self::FAILURE;
        }

        $claims = $this->candidateQuery($eventId, $templateId)->orderBy('w.id')->get();
        $eligible = $claims->filter(fn ($row) => filled(WhatsAppPhone::normalize($row->phone)));
        $this->line("Event {$eventId}; template {$templateId}; queue {$queue}");
        $this->line('Eligible orphan claims: '.$eligible->count().'; invalid phone numbers: '.($claims->count() - $eligible->count()));
        $this->table(['Claim', 'Invitee'], $eligible->map(fn ($row) => [$row->claim_id, $row->invitee_id])->all());

        if (! $this->option('execute')) {
            $this->warn('DRY RUN only. No claims or jobs changed. Missing local logs do not prove provider non-delivery.');
            return self::SUCCESS;
        }

        $expected = filter_var($this->option('expected'), FILTER_VALIDATE_INT);
        if (! $this->option('provider-verified') || $expected === false || $expected < 1
            || $expected !== $eligible->count() || $eligible->count() !== $claims->count()) {
            $this->error('Execution requires --provider-verified, --expected matching the eligible count, and no invalid recipients.');
            return self::FAILURE;
        }

        if (! $this->confirm('Provider delivery records verified for all '.$expected.' invitees and safe to enqueue exactly once?', false)) {
            return self::FAILURE;
        }

        $queued = 0;
        foreach ($eligible as $candidate) {
            try {
                DB::transaction(function () use ($candidate, $eventId, $templateId, $template, $queue, &$queued): void {
                    $claim = DB::table('whatsapp_template_sends')
                        ->where('id', $candidate->claim_id)->where('event_id', $eventId)
                        ->where('message_template_id', $templateId)->lockForUpdate()->first();

                    if (! $claim || $claim->status !== 'pending' || filled($claim->last_error)) {
                        throw new RuntimeException('Claim no longer recoverable; stopped.');
                    }

                    $latest = $this->candidateQuery($eventId, $templateId)
                        ->where('w.id', $candidate->claim_id)->first();
                    if (! $latest || blank(WhatsAppPhone::normalize($latest->phone))) {
                        throw new RuntimeException('Eligibility changed; stopped.');
                    }

                    // The marker prevents another operator from trying this recovery again.
                    DB::table('whatsapp_template_sends')->where('id', $claim->id)->update([
                        'last_error' => 'Manual orphan recovery queued at '.now()->toDateTimeString(),
                        'last_attempt_at' => now(),
                        'attempts' => DB::raw('attempts + 1'),
                        'updated_at' => now(),
                    ]);

                    $before = DB::table('jobs')->where('queue', $queue)->count();

                    // Insert into the same database transaction so a failed job
                    // insert rolls back the recovery marker.
                    SendInvitationWhatsAppJob::dispatch(
                        inviteeId: (int) $latest->invitee_id,
                        languageCode: filled($template->whatsapp_language_code)
                            ? (string) $template->whatsapp_language_code
                            : MessageTemplate::LANGUAGE_ENGLISH,
                        messageTemplateId: $templateId,
                        phoneGeneration: (int) $latest->phone_generation,
                    )->onConnection('database')->onQueue($queue)->beforeCommit();

                    if (DB::table('jobs')->where('queue', $queue)->count() !== $before + 1) {
                        throw new RuntimeException('No job was inserted (possibly held by a unique lock); stopped.');
                    }
                    $queued++;
                });
            } catch (\Throwable $e) {
                $this->error("Stopped after {$queued} queued: ".$e->getMessage());
                return self::FAILURE;
            }
        }

        $this->info("Queued {$queued} verified orphan invitations. This is not proof of WhatsApp delivery.");
        return self::SUCCESS;
    }

    private function candidateQuery(int $eventId, int $templateId): \Illuminate\Database\Query\Builder
    {
        return DB::table('whatsapp_template_sends as w')
            ->join('invitees as i', function ($join): void {
                $join->on('i.id', '=', 'w.invitee_id')
                    ->on('i.event_id', '=', 'w.event_id');
            })
            ->where('w.event_id', $eventId)
            ->where('w.message_template_id', $templateId)
            ->where('w.status', 'pending')
            ->whereNull('w.last_error')
            ->whereColumn('w.phone_generation', 'i.invitation_phone_generation')
            ->where('i.card_status', Invitee::CARD_STATUS_ACTIVE)
            ->where('i.whatsapp_status', 'not_sent')
            ->whereNull('i.whatsapp_sent_at')
            ->whereNotNull('i.phone')
            ->whereNotNull('i.short_code')
            ->whereNotExists(function ($sub): void {
                $sub->selectRaw('1')->from('message_logs as ml')
                    ->whereColumn('ml.invitee_id', 'w.invitee_id')
                    ->where('ml.channel', 'whatsapp')
                    ->where('ml.type', MessageTemplate::TYPE_INVITATION);
            })
            ->whereNotExists(function ($sub) use ($templateId): void {
                $sub->selectRaw('1')->from('whatsapp_template_sends as other')
                    ->whereColumn('other.invitee_id', 'w.invitee_id')
                    ->whereColumn('other.phone_generation', 'w.phone_generation')
                    ->where('other.id', '!=', DB::raw('w.id'))
                    ->whereIn('other.status', ['submitted', 'pending']);
            })
            ->select('w.id as claim_id', 'w.invitee_id', 'w.phone_generation', 'i.phone');
    }
}
