<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Models\Invitee;
use App\Models\SmsLog;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class Dashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $view = 'filament.pages.dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Dashboard';

    protected static ?int $navigationSort = 0;

    public array $stats = [];

    public function mount(): void
    {
        $this->loadStats();
    }

    public function loadStats(): void
    {
        $this->stats = [
            'events' => $this->safeCount(Event::class),
            'invitees' => $this->safeCount(Invitee::class),

            'total_sms' => $this->safeCount(SmsLog::class),
            'sent_sms' => $this->countSmsByStatus(['sent', 'submitted', 'delivered']),
            'delivered_sms' => $this->countSmsByStatus(['delivered']),
            'failed_sms' => $this->countSmsByStatus(['failed', 'error']),
            'pending_sms' => $this->countSmsByStatus(['pending', 'queued']),

            'invitation_sms' => $this->countSmsByType(['invitation', 'first_invitation']),
            'rsvp_reminders' => $this->countSmsByType(['rsvp_reminder', 'reminder']),
            'one_day_before' => $this->countSmsByType(['one_day_before', 'day_before']),
            'event_day_sms' => $this->countSmsByType(['event_day', 'final_reminder']),

            'rsvp_pending' => $this->countInviteesByRsvp(['pending']),
            'rsvp_attending' => $this->countInviteesByRsvp(['attending', 'yes', 'confirmed']),
            'checked_in' => $this->countCheckedInInvitees(),
        ];
    }

    protected function safeCount(string $model): int
    {
        try {
            return $model::query()->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function countSmsByStatus(array $statuses): int
    {
        try {
            if (! class_exists(SmsLog::class) || ! Schema::hasTable('sms_logs')) {
                return 0;
            }

            if (! Schema::hasColumn('sms_logs', 'status')) {
                return 0;
            }

            return SmsLog::query()
                ->whereIn('status', $statuses)
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function countSmsByType(array $types): int
    {
        try {
            if (! class_exists(SmsLog::class) || ! Schema::hasTable('sms_logs')) {
                return 0;
            }

            foreach (['message_type', 'sms_type', 'type', 'category'] as $column) {
                if (Schema::hasColumn('sms_logs', $column)) {
                    return SmsLog::query()
                        ->whereIn($column, $types)
                        ->count();
                }
            }

            return 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function countInviteesByRsvp(array $statuses): int
    {
        try {
            if (! Schema::hasTable('invitees')) {
                return 0;
            }

            if (! Schema::hasColumn('invitees', 'rsvp_status')) {
                return 0;
            }

            return Invitee::query()
                ->whereIn('rsvp_status', $statuses)
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function countCheckedInInvitees(): int
    {
        try {
            if (! Schema::hasTable('invitees')) {
                return 0;
            }

            if (Schema::hasColumn('invitees', 'checked_in_count')) {
                return Invitee::query()
                    ->where('checked_in_count', '>', 0)
                    ->count();
            }

            if (Schema::hasColumn('invitees', 'check_in_status')) {
                return Invitee::query()
                    ->whereIn('check_in_status', ['checked_in', 'partial'])
                    ->count();
            }

            return 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function getUserNameProperty(): string
    {
        return Auth::user()?->name ?? 'eLive Admin';
    }
}