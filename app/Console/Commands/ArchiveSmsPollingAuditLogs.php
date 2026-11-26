<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ArchiveSmsPollingAuditLogs extends Command
{
    protected $signature = 'audit:archive-sms-polling
        {--days=90 : Retain at least this many days in the candidate selection}
        {--export : Write a private gzip JSON Lines archive; never delete rows}';

    protected $description = 'Inspect or export historical system SMS polling audit records without deleting anything';

    public function handle(): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);
        if ($days === false || $days < 30 || $days > 36500) {
            $this->error('Specify --days between 30 and 36500.');
            return self::FAILURE;
        }
        if (! Schema::hasTable('audit_logs')) {
            $this->error('Audit table is missing.');
            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $query = DB::table('audit_logs')
            ->whereIn('action', ['sms.delivery_report_requested', 'sms.delivery_report_unavailable'])
            ->whereNull('user_id')->whereNull('subject_type')->whereNull('subject_id')
            ->whereNull('event_id')->whereNull('ip_address')
            ->where('user_agent', 'Laravel Console / Queue')
            ->where('created_at', '<', $cutoff);
        $maxId = (clone $query)->max('id');
        $query->where('id', '<=', $maxId ?? 0);
        $count = (clone $query)->count();
        $this->line('Cutoff: '.$cutoff->toDateTimeString());
        $this->line('Candidate records: '.$count);

        if (! $this->option('export') || $count === 0) {
            $this->info('No records modified or deleted. No archive written.');
            return self::SUCCESS;
        }

        // Explicit CLI lookups without an authenticated user cannot be reliably
        // distinguished from older scheduled lookups. Export only; never purge.
        $directory = storage_path('app/private/audit-archives');
        $path = $directory.'/sms-polling-'.now()->format('Ymd-His').'-'.Str::uuid().'.jsonl.gz';
        $handle = null;
        $previousUmask = umask(0077);
        try {
            if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new RuntimeException('Cannot create private archive directory.');
            }
            if (! chmod($directory, 0700)) {
                throw new RuntimeException('Cannot restrict archive directory.');
            }
            $handle = gzopen($path, 'wb6');
            if ($handle === false || ! chmod($path, 0600)) {
                throw new RuntimeException('Cannot create restricted archive file.');
            }
            $written = 0;
            $hash = hash_init('sha256');
            $query->chunkById(1000, function ($rows) use ($handle, &$written, $hash): void {
                foreach ($rows as $row) {
                    $line = json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n";
                    if (gzwrite($handle, $line) !== strlen($line)) {
                        throw new RuntimeException('Incomplete archive write.');
                    }
                    hash_update($hash, $line);
                    $written++;
                }
            });
            if (! gzclose($handle)) {
                $handle = null;
                throw new RuntimeException('Cannot finalize archive.');
            }
            $handle = null;
            $expectedHash = hash_final($hash);
            $reader = gzopen($path, 'rb');
            if ($reader === false) {
                throw new RuntimeException('Cannot verify archive.');
            }
            $verified = hash_init('sha256');
            $lines = 0;
            try {
                while (! gzeof($reader)) {
                    $line = gzgets($reader);
                    if ($line === false) {
                        if (! gzeof($reader)) {
                            throw new RuntimeException('Archive read failed.');
                        }
                        break;
                    }
                    json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    hash_update($verified, $line);
                    $lines++;
                }
            } finally {
                gzclose($reader);
            }
            if ($written !== $count || $lines !== $written || ! hash_equals($expectedHash, hash_final($verified))) {
                throw new RuntimeException('Archive verification failed or source changed.');
            }
            $this->info('Verified archive: '.$path);
            $this->line('Records: '.$written);
            $this->line('Archive SHA-256: '.hash_file('sha256', $path));
            $this->warn('Archive contains sensitive data; copy to encrypted restricted backup storage. No database rows deleted.');
            return self::SUCCESS;
        } catch (Throwable $e) {
            if (is_resource($handle)) {
                gzclose($handle);
            }
            if (is_file($path)) {
                unlink($path);
            }
            $this->error('Export failed: '.$e->getMessage());
            return self::FAILURE;
        } finally {
            umask($previousUmask);
        }
    }
}
