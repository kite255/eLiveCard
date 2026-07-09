<?php

namespace App\Filament\Pages;

use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\SmsLog;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class Dashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = '';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.dashboard';

    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        return '';
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }

    protected function getViewData(): array
    {
        $totalEvents = $this->safeCount(Event::class);
        $totalInvitees = $this->safeCount(Invitee::class);

        $eventsThisMonth = $this->countEventsThisMonth();

        $attending = $this->countInviteesByRsvp(['attending', 'yes', 'confirmed']);
        $notAttending = $this->countInviteesByRsvp(['not_attending', 'no', 'declined']);
        $rsvpPending = $this->countPendingRsvpInvitees();

        $checkedInInvitees = $this->countCheckedInInvitees();
        $remainingInvitees = max($totalInvitees - $checkedInInvitees, 0);

        $responseRate = $totalInvitees > 0
            ? round((($attending + $notAttending) / $totalInvitees) * 100)
            : 0;

        $checkedInPercent = $totalInvitees > 0
            ? round(($checkedInInvitees / $totalInvitees) * 100)
            : 0;

        return [
            'userName' => Auth::user()?->name ?? 'eLive Admin',

            'totalEvents' => $totalEvents,
            'eventsThisMonth' => $eventsThisMonth,

            'totalInvitees' => $totalInvitees,
            'checkedInInvitees' => $checkedInInvitees,
            'remainingInvitees' => $remainingInvitees,
            'checkedInPercent' => $checkedInPercent,

            'attending' => $attending,
            'notAttending' => $notAttending,
            'rsvpPending' => $rsvpPending,
            'responseRate' => $responseRate,

            'smsBalance' => $this->getSmsBalance(),

            'smsTotal' => $this->safeCount(SmsLog::class),
            'smsSent' => $this->countSmsByStatus(['sent', 'delivered', 'submitted', 'success']),
            'smsDelivered' => $this->countSmsByStatus(['delivered']),
            'smsFailed' => $this->countSmsByStatus(['failed', 'error']),
            'smsPending' => $this->countSmsByStatus(['pending', 'queued']),

            'invitationSms' => $this->countSmsByType(['invitation', 'first_invitation', 'invitation_card']),
            'rsvpReminders' => $this->countSmsByType(['rsvp_reminder', 'rsvp_pending_reminder', 'reminder']),
            'oneDayBeforeSms' => $this->countSmsByType(['one_day_before', 'day_before', 'attending_reminder']),
            'eventDaySms' => $this->countSmsByType(['event_day', 'event_day_reminder', 'final_reminder']),

            'upcomingEvents' => $this->getUpcomingEvents(),
            'recentSmsLogs' => $this->getRecentSmsLogs(),
        ];
    }

    protected function safeCount(string $model): int
    {
        try {
            if (! class_exists($model)) {
                return 0;
            }

            return $model::query()->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function countEventsThisMonth(): int
    {
        try {
            if (! class_exists(Event::class) || ! Schema::hasTable('events')) {
                return 0;
            }

            if (Schema::hasColumn('events', 'event_date')) {
                return Event::query()
                    ->whereBetween('event_date', [
                        now()->startOfMonth()->toDateString(),
                        now()->endOfMonth()->toDateString(),
                    ])
                    ->count();
            }

            if (Schema::hasColumn('events', 'created_at')) {
                return Event::query()
                    ->whereBetween('created_at', [
                        now()->startOfMonth(),
                        now()->endOfMonth(),
                    ])
                    ->count();
            }

            return 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function countInviteesByRsvp(array $statuses): int
    {
        try {
            if (! class_exists(Invitee::class) || ! Schema::hasTable('invitees')) {
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

    protected function countPendingRsvpInvitees(): int
    {
        try {
            if (! class_exists(Invitee::class) || ! Schema::hasTable('invitees')) {
                return 0;
            }

            if (! Schema::hasColumn('invitees', 'rsvp_status')) {
                return 0;
            }

            return Invitee::query()
                ->where(function ($query) {
                    $query
                        ->whereNull('rsvp_status')
                        ->orWhere('rsvp_status', '')
                        ->orWhere('rsvp_status', 'pending');
                })
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function countCheckedInInvitees(): int
    {
        try {
            if (class_exists(Invitee::class) && Schema::hasTable('invitees')) {
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

                if (Schema::hasColumn('invitees', 'checked_in_at')) {
                    return Invitee::query()
                        ->whereNotNull('checked_in_at')
                        ->count();
                }
            }

            if (class_exists(CheckIn::class) && Schema::hasTable('check_ins')) {
                if (Schema::hasColumn('check_ins', 'invitee_id')) {
                    return CheckIn::query()
                        ->distinct('invitee_id')
                        ->count('invitee_id');
                }
            }

            return 0;
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

            foreach (['sms_type', 'message_type', 'type', 'category'] as $column) {
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

    protected function getUpcomingEvents()
    {
        try {
            if (! class_exists(Event::class) || ! Schema::hasTable('events')) {
                return collect();
            }

            if (Schema::hasColumn('events', 'event_date')) {
                return Event::query()
                    ->whereDate('event_date', '>=', now()->toDateString())
                    ->orderBy('event_date')
                    ->limit(5)
                    ->get();
            }

            if (Schema::hasColumn('events', 'date')) {
                return Event::query()
                    ->whereDate('date', '>=', now()->toDateString())
                    ->orderBy('date')
                    ->limit(5)
                    ->get();
            }

            return Event::query()
                ->latest()
                ->limit(5)
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    protected function getRecentSmsLogs()
    {
        try {
            if (! class_exists(SmsLog::class) || ! Schema::hasTable('sms_logs')) {
                return collect();
            }

            return SmsLog::query()
                ->latest()
                ->limit(6)
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    protected function getSmsBalance(): string
    {
        try {
            $url = config('services.sms_balance.url');

            if (blank($url)) {
                return 'N/A';
            }

            $request = Http::timeout(10);

            $token = config('services.sms_balance.token');
            $username = config('services.sms_balance.username');
            $password = config('services.sms_balance.password');

            if (filled($token)) {
                $request = $request->withToken($token);
            }

            if (filled($username) && filled($password)) {
                $request = $request->withBasicAuth($username, $password);
            }

            $response = $request->get($url);

            if (! $response->successful()) {
                return 'Unavailable';
            }

            $data = $response->json();

            $balance = $data['balance']
                ?? $data['sms_balance']
                ?? $data['credits']
                ?? $data['credit']
                ?? $data['remaining']
                ?? null;

            if ($balance === null) {
                return 'Check provider';
            }

            return number_format((float) $balance);
        } catch (\Throwable $e) {
            return 'Unavailable';
        }
    }
}