<?php

namespace App\Console\Commands;

use App\Services\InvitationWhatsAppDeliveryStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only investigation of pending WhatsApp invitation claims.
 *
 * Never retries, releases claims, or changes any message or invitee state.
 */
class AuditWhatsAppInvitationQueue extends Command
{
    protected $signature = 'whatsapp:audit-invitations {--event= : Limit to an event ID} {--limit=20 : Maximum pending rows to inspect}';
    protected $description = 'Inspect WhatsApp invitation claims and queue evidence without sending messages';

    public function handle(): int
    {
        if (! Schema::hasTable('whatsapp_template_sends')) {
            $this->error('WhatsApp template tracking table is not installed.');
            return self::FAILURE;
        }

        $eventId = $this->option('event');
        if ($eventId !== null && (! ctype_digit((string) $eventId) || (int) $eventId < 1)) {
            $this->error('--event must be a positive integer.');
            return self::INVALID;
        }

        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100],
        ]);
        if ($limit === false) {
            $this->error('--limit must be between 1 and 100.');
            return self::INVALID;
        }

        $claims = DB::table('whatsapp_template_sends as w')
            ->when($eventId !== null, fn ($q) => $q->where('w.event_id', (int) $eventId));

        $this->info('WhatsApp invitation claim statuses (historical totals)');
        $this->table(['Status', 'Total'], (clone $claims)
            ->select('w.status')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('w.status')
            ->orderBy('w.status')
            ->get()
            ->map(fn ($r) => [$r->status, $r->total])
            ->all());

        $stale = (clone $claims)->where('w.status', 'pending')
            ->where('w.updated_at', '<', now()->subDay())
            ->count();
        $recent = (clone $claims)->where('w.status', 'pending')
            ->where('w.updated_at', '>=', now()->subDay())
            ->count();

        $this->line("Pending over 24 hours: {$stale}; Pending within 24 hours: {$recent}.");
        $queue = (string) config('messaging.whatsapp_queue', 'communications');
        $this->line("Configured WhatsApp queue: {$queue}; connection: ".config('queue.default').'.');
        if (Schema::hasTable('jobs')) {
            $this->line('Waiting database jobs on that queue: '.DB::table('jobs')->where('queue', $queue)->count().'.');
        }
        if (Schema::hasTable('failed_jobs')) {
            $this->line('Historical failed jobs on that queue (all job types): '.DB::table('failed_jobs')->where('queue', $queue)->count().'.');
        }

        $pending = (clone $claims)
            ->join('invitees as i', function ($join): void {
                $join->on('i.id', '=', 'w.invitee_id')
                    ->on('i.event_id', '=', 'w.event_id');
            })
            ->where('w.status', 'pending')
            ->orderBy('w.updated_at')
            ->limit($limit)
            ->get(['w.id', 'w.event_id', 'w.invitee_id', 'i.name', 'w.updated_at']);

        $hasLogs = Schema::hasTable('message_logs');
        $rows = $pending->map(function ($claim) use ($hasLogs): array {
            $latest = $hasLogs
                ? DB::table('message_logs')
                    ->where('event_id', $claim->event_id)
                    ->where('invitee_id', $claim->invitee_id)
                    ->where('channel', 'whatsapp')
                    ->where('type', 'invitation')
                    ->orderByDesc('id')
                    ->value('status')
                : null;

            return [
                $claim->event_id,
                $claim->invitee_id,
                $claim->name,
                $claim->updated_at,
                $latest ?: 'none',
            ];
        })->all();

        $this->table(['Event', 'Invitee ID', 'Name', 'Claim Updated', 'Latest Invitation Log'], $rows);
        $this->warn('Read-only audit. Pending is not proof of an active job or an unsent message. Do not bulk retry without reconciling provider evidence.');
        return self::SUCCESS;
    }
}
