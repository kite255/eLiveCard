<?php

namespace App\Console\Commands;

use App\Models\CardTemplate;
use App\Models\ContributionCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InspectCardQueueHealth extends Command
{
    protected $signature = 'elive:card-queue-health {--limit=30 : Maximum missing files and recent failed jobs to show}';

    protected $description = 'Read-only diagnosis of missing card templates, failed jobs, and queue configuration; does not retry or send anything.';

    public function handle(): int
    {
        $limit = max(1, min(200, (int) $this->option('limit')));
        $disk = Storage::disk('public');

        $this->info('eLive Card queue and template storage health (read only)');
        $this->line('Queue driver: '.config('queue.default'));
        $this->line('Public storage root: '.$disk->path(''));
        $this->line('Public storage driver: '.config('filesystems.disks.public.driver'));

        foreach ([
            'Contribution campaign templates' => [ContributionCampaign::class, 'template_image'],
            'Event invitation templates' => [CardTemplate::class, 'template_image'],
        ] as $label => [$model, $column]) {
            $this->newLine();
            $this->info($label);

            $missing = 0;
            $checked = 0;
            $displayed = 0;

            $model::query()->select(['id', $column])->orderBy('id')->chunkById(200, function ($items) use ($disk, $column, $limit, &$missing, &$checked, &$displayed): void {
                foreach ($items as $item) {
                    $checked++;
                    $path = trim((string) ($item->{$column} ?? ''));

                    try {
                        $exists = $path !== '' && $disk->exists($path);
                    } catch (Throwable $exception) {
                        $exists = false;
                        if ($displayed < $limit) {
                            $this->warn('Storage error for record '.$item->getKey().': '.$exception->getMessage());
                        }
                    }

                    if ($exists) {
                        continue;
                    }

                    $missing++;
                    if ($displayed++ < $limit) {
                        $this->warn('Missing: record #'.$item->getKey().' | '.($path ?: '(empty template path)'));
                    }
                }
            });

            $this->line("Checked: {$checked}; missing template files: {$missing}");
        }

        $this->newLine();
        $this->info('Database queue records');
        if (Schema::hasTable('jobs')) {
            DB::table('jobs')
                ->selectRaw('queue, COUNT(*) as total')
                ->groupBy('queue')
                ->orderBy('queue')
                ->get()
                ->each(fn ($row) => $this->line($row->queue.': '.$row->total));
        } else {
            $this->warn('No jobs table found (the queue may use Redis or another backend).');
        }

        $this->newLine();
        $this->info('Historical failed jobs');
        if (Schema::hasTable('failed_jobs')) {
            $this->line('Recorded failures: '.DB::table('failed_jobs')->count());

            DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit($limit)
                ->get(['id', 'queue', 'payload', 'exception', 'failed_at'])
                ->each(function ($job): void {
                    $payload = json_decode($job->payload, true);
                    $name = is_array($payload) ? ($payload['displayName'] ?? 'Unknown job') : 'Unknown job';
                    $reason = trim(strtok((string) $job->exception, "\n") ?: 'No exception supplied');
                    $this->line("#{$job->id} | {$job->failed_at} | {$job->queue} | {$name}");
                    $this->warn('  '.mb_strimwidth($reason, 0, 260, '...'));
                });
        } else {
            $this->warn('No failed_jobs table found.');
        }

        $this->newLine();
        $this->comment('No jobs were retried, deleted, or dispatched. Restore missing uploads on the persistent public storage volume before regenerating cards.');

        return self::SUCCESS;
    }
}
