<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MonitorWhatsAppInvitationQueue extends Command
{
    protected $signature = 'whatsapp:monitor-invitations
        {--minutes=30 : Pending claim age threshold in minutes}
        {--event= : Optional event filter}
        {--alert : Write a structured warning to application logs when pending claims are stale}';

    protected $description = 'Read-only monitoring for stale WhatsApp invitation claims and communications queue backlog';

    public function handle(): int
    {
        $minutes = filter_var($this->option('minutes'), FILTER_VALIDATE_INT);
        $event = $this->option('event');
        $eventId = $event === null ? null : filter_var($event, FILTER_VALIDATE_INT);

        if ($minutes === false || $minutes < 1 || ($event !== null && ($eventId === false || $eventId < 1))) {
            $this->error('Use a positive --minutes and an optional positive --event.');
            return self::FAILURE;
        }

        if (! Schema::hasTable('whatsapp_template_sends') || ! Schema::hasTable('jobs')) {
            $this->error('WhatsApp tracking or database queue tables are missing.');
            return self::FAILURE;
        }

        $queue = (string) config('messaging.whatsapp_queue', 'communications');
        $stale = DB::table('whatsapp_template_sends')
            ->where('status', 'pending')
            ->where('updated_at', '<=', now()->subMinutes($minutes))
            ->when($eventId, fn ($query) => $query->where('event_id', $eventId));

        $staleCount = (clone $stale)->count();
        $perEvent = (clone $stale)->select('event_id')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('event_id')->orderByDesc('total')->get();

        $waiting = DB::table('jobs')->where('queue', $queue)->whereNull('reserved_at')->count();
        $reserved = DB::table('jobs')->where('queue', $queue)->whereNotNull('reserved_at')->count();
        $this->line("WhatsApp queue: {$queue}; stale threshold: {$minutes} minutes");
        $this->line("Stale pending claims: {$staleCount}; waiting jobs: {$waiting}; reserved jobs: {$reserved}");
        $this->table(['Event', 'Stale claims'], $perEvent->map(fn ($row) => [$row->event_id, $row->total])->all());
        $this->warn('This is read-only. Queue jobs include other communications; a stale claim is not proof that Meta did not receive it.');

        if ($staleCount > 0 && $this->option('alert')) {
            Log::warning('WhatsApp invitation claims require reconciliation', [
                'stale_pending_claims' => $staleCount,
                'threshold_minutes' => $minutes,
                'event_id' => $eventId,
                'communications_waiting' => $waiting,
                'communications_reserved' => $reserved,
                'event_counts' => $perEvent->pluck('total', 'event_id')->all(),
                'action' => 'Run whatsapp:audit-invitations and verify provider evidence before retrying.',
            ]);
        }

        return self::SUCCESS;
    }
}
