<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArchiveSmsPollingAuditLogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_and_export_preserve_all_rows_and_exclude_protected_records(): void
    {
        $originalStorage = storage_path();
        $temporaryStorage = sys_get_temp_dir().'/elive-audit-test-'.Str::uuid();
        $this->app->useStoragePath($temporaryStorage);

        try {
            $old = [
                'action' => 'sms.delivery_report_requested',
                'user_agent' => 'Laravel Console / Queue',
                'created_at' => now()->subDays(40),
                'metadata' => json_encode(['synthetic' => true]),
            ];
            $first = DB::table('audit_logs')->insertGetId($old);
            $second = DB::table('audit_logs')->insertGetId(array_replace($old, [
                'action' => 'sms.delivery_report_unavailable',
            ]));
            DB::table('audit_logs')->insert(array_replace($old, [
                'action' => 'sms.delivery_status_updated',
            ]));
            DB::table('audit_logs')->insert(array_replace($old, [
                'created_at' => now()->subDays(5),
            ]));
            DB::table('audit_logs')->insert(array_replace($old, [
                'user_agent' => 'Browser',
                'ip_address' => '127.0.0.1',
            ]));
            $before = DB::table('audit_logs')->orderBy('id')->get()->toJson();
            $directory = storage_path('app/private/audit-archives');

            $this->artisan('audit:archive-sms-polling', ['--days' => 30])
                ->expectsOutput('Candidate records: 2')
                ->assertSuccessful();
            $this->assertDirectoryDoesNotExist($directory);

            $this->artisan('audit:archive-sms-polling', ['--days' => 30, '--export' => true])
                ->expectsOutput('Records: 2')
                ->assertSuccessful();

            $files = glob($directory.'/*.jsonl.gz');
            $this->assertCount(1, $files);
            $this->assertSame(0600, fileperms($files[0]) & 0777);
            $this->assertSame(0700, fileperms($directory) & 0777);
            $contents = gzdecode(file_get_contents($files[0]));
            $this->assertIsString($contents);
            $rows = array_map(fn ($line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR), explode("\n", rtrim($contents, "\n")));
            $this->assertSame([$first, $second], array_column($rows, 'id'));
            $this->assertSame($before, DB::table('audit_logs')->orderBy('id')->get()->toJson());
        } finally {
            $this->app->useStoragePath($originalStorage);
            File::deleteDirectory($temporaryStorage);
        }
    }

    public function test_invalid_retention_is_rejected(): void
    {
        $this->artisan('audit:archive-sms-polling', ['--days' => 0])
            ->assertFailed();
    }
}
