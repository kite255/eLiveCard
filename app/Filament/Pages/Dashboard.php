<?php

namespace App\Filament\Pages;

use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Invitee;
use App\Models\SmsLog;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class Dashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = '';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.dashboard';

    public ?int $selectedEventId = null;

    protected ?array $accessibleEventIdsCache = null;

    public function mount(): void
    {
        $storedEventId = session('elive_dashboard_event_id');

        if (
            filled($storedEventId)
            && $this->eventQuery()->whereKey($storedEventId)->exists()
        ) {
            $this->selectedEventId = (int) $storedEventId;
        }

        if ($this->isCheckInOfficer() && blank($this->selectedEventId)) {
            $this->selectedEventId = $this->eventQuery()
                ->when(
                    Schema::hasColumn('events', 'event_date'),
                    fn (Builder $query) => $query->orderBy('event_date'),
                )
                ->value('id');
        }
    }

    public function updatedSelectedEventId($value): void
    {
        $eventId = filled($value) ? (int) $value : null;

        if (
            $eventId !== null
            && ! $this->eventQuery()->whereKey($eventId)->exists()
        ) {
            $eventId = null;
        }

        $this->selectedEventId = $eventId;

        session([
            'elive_dashboard_event_id' => $this->selectedEventId,
        ]);
    }

    public function clearEventFilter(): void
    {
        $this->selectedEventId = null;

        session()->forget('elive_dashboard_event_id');
    }

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
        $user = Auth::user();
        $isSuperAdmin = $this->isSuperAdmin();
        $isEventManager = $this->isEventManager();
        $isCheckInOfficer = $this->isCheckInOfficer();

        $totalEvents = $this->eventQuery()->count();
        $eventsThisMonth = $this->countEventsThisMonth();

        $totalInvitees = $this->countInvitees();

        $attending = $this->countInviteesByRsvp([
            'attending',
            'yes',
            'confirmed',
        ]);

        $notAttending = $this->countInviteesByRsvp([
            'not_attending',
            'no',
            'declined',
        ]);

        $rsvpPending = $this->countPendingRsvpInvitees();

        $checkedInInvitees = $this->countCheckedInInvitees();
        $remainingInvitees = max($totalInvitees - $checkedInInvitees, 0);

        $responseRate = $totalInvitees > 0
            ? round((($attending + $notAttending) / $totalInvitees) * 100)
            : 0;

        $checkedInPercent = $totalInvitees > 0
            ? round(($checkedInInvitees / $totalInvitees) * 100)
            : 0;

        // Feature 2: enhanced RSVP progress.
        $respondedInvitees = $attending + $notAttending;
        $confirmedGuests = $this->sumConfirmedGuests();
        $rsvpProgress = $totalInvitees > 0
            ? round(($respondedInvitees / $totalInvitees) * 100)
            : 0;

        // Feature 3: combined SMS and WhatsApp overview.
        $whatsAppTotal = $this->countWhatsAppMessages();

        // Keep message states mutually exclusive so totals are not overstated.
        $whatsAppSent = $this->countWhatsAppByStatus([
            'accepted',
            'submitted',
            'sent',
            'success',
        ]);
        $whatsAppDelivered = $this->countWhatsAppByStatus([
            'delivered',
        ]);
        $whatsAppRead = $this->countWhatsAppByStatus([
            'read',
        ]);
        $whatsAppReplied = $this->countWhatsAppByStatus([
            'replied',
        ]);
        $whatsAppFailed = $this->countWhatsAppByStatus([
            'failed',
            'error',
            'rejected',
            'undelivered',
            'expired',
        ]);
        $whatsAppPending = $this->countWhatsAppByStatus([
            'pending',
            'queued',
            'processing',
            'sending',
        ]);

        // Feature 4: guest attendance and live check-in.
        $totalAllowedGuests = $this->sumAllowedGuests();
        $checkedInGuests = $this->sumCheckedInGuests();
        $remainingExpectedGuests = max($confirmedGuests - $checkedInGuests, 0);
        $guestCheckInPercent = $confirmedGuests > 0
            ? min(100, round(($checkedInGuests / $confirmedGuests) * 100))
            : 0;

        // Feature 5: alerts and pending actions.
        $systemAlerts = $this->getSystemAlerts();

        return [
            'userName' => $user?->name ?? 'eLive Admin',
            'userRoleLabel' => $this->getRoleLabel(),
            'isSuperAdmin' => $isSuperAdmin,
            'isEventManager' => $isEventManager,
            'isCheckInOfficer' => $isCheckInOfficer,
            'dashboardMode' => $isCheckInOfficer ? 'check_in' : 'management',
            'accessibleEvents' => $this->getAccessibleEvents(),
            'officerRecentCheckIns' => $this->getOfficerRecentCheckIns(),
            'checkInTransactions' => $this->countCheckInTransactions(),
            'fullyCheckedInInvitees' => $this->countFullyCheckedInInvitees(),
            'partiallyCheckedInInvitees' => $this->countPartiallyCheckedInInvitees(),

            'selectedEventId' => $this->selectedEventId,
            'selectedEvent' => $this->getSelectedEvent(),
            'eventOptions' => $this->getEventOptions(),

            'activeEvent' => $this->getActiveEvent(),
            'upcomingEvent' => $this->getNextUpcomingEvent(),

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
            'respondedInvitees' => $respondedInvitees,
            'confirmedGuests' => $confirmedGuests,
            'rsvpProgress' => $rsvpProgress,

            'totalAllowedGuests' => $totalAllowedGuests,
            'checkedInGuests' => $checkedInGuests,
            'remainingExpectedGuests' => $remainingExpectedGuests,
            'guestCheckInPercent' => $guestCheckInPercent,
            'recentCheckIns' => $this->getRecentCheckIns(),

            'whatsAppTotal' => $whatsAppTotal,
            'whatsAppSent' => $whatsAppSent,
            'whatsAppDelivered' => $whatsAppDelivered,
            'whatsAppRead' => $whatsAppRead,
            'whatsAppReplied' => $whatsAppReplied,
            'whatsAppFailed' => $whatsAppFailed,
            'whatsAppPending' => $whatsAppPending,

            'systemAlerts' => $systemAlerts,
            'pendingPhotos' => $this->countPendingPhotos(),
            'pendingWishes' => $this->countPendingWishes(),

            'smsBalance' => $this->getSmsBalance(),

            'smsTotal' => $this->countSms(),
            // These groups are mutually exclusive to avoid double counting.
            'smsSent' => $this->countSmsByStatus([
                'accepted',
                'submitted',
                'sent',
                'success',
            ]),
            'smsDelivered' => $this->countSmsByStatus([
                'delivered',
            ]),
            'smsFailed' => $this->countSmsByStatus([
                'failed',
                'error',
                'rejected',
                'undelivered',
                'expired',
            ]),
            'smsPending' => $this->countSmsByStatus([
                'pending',
                'queued',
                'processing',
                'sending',
            ]),

            'invitationSms' => $this->countSmsByType([
                'invitation',
                'first_invitation',
                'invitation_card',
            ]),
            'rsvpReminders' => $this->countSmsByType([
                'rsvp_reminder',
                'rsvp_pending_reminder',
                'reminder',
            ]),
            'oneDayBeforeSms' => $this->countSmsByType([
                'one_day_before',
                'day_before',
                'attending_reminder',
            ]),
            'eventDaySms' => $this->countSmsByType([
                'event_day',
                'event_day_reminder',
                'final_reminder',
            ]),

            'upcomingEvents' => $this->getUpcomingEvents(),
            'recentSmsLogs' => $this->getRecentSmsLogs(),
        ];
    }

    protected function getEventOptions(): array
    {
        try {
            if (! class_exists(Event::class) || ! Schema::hasTable('events')) {
                return [];
            }

            $labelColumn = $this->eventLabelColumn();

            if ($labelColumn === null) {
                return [];
            }

            return $this->eventQuery()
                ->orderBy($labelColumn)
                ->pluck($labelColumn, 'id')
                ->mapWithKeys(fn ($label, $id) => [
                    (int) $id => filled($label) ? (string) $label : "Event #{$id}",
                ])
                ->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    protected function getSelectedEvent(): ?Event
    {
        try {
            if (
                blank($this->selectedEventId)
                || ! class_exists(Event::class)
                || ! Schema::hasTable('events')
            ) {
                return null;
            }

            return $this->eventQuery()->find($this->selectedEventId);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    protected function getActiveEvent(): ?Event
    {
        try {
            if (! class_exists(Event::class) || ! Schema::hasTable('events')) {
                return null;
            }

            /*
             * When an event is selected, show it as the active dashboard event.
             * Otherwise, prefer an event explicitly marked active.
             */
            if (filled($this->selectedEventId)) {
                return $this->eventQuery()->find($this->selectedEventId);
            }

            if (Schema::hasColumn('events', 'status')) {
                $activeEvent = $this->eventQuery()
                    ->whereIn('status', [
                        'active',
                        'ongoing',
                        'in_progress',
                        'published',
                    ])
                    ->when(
                        Schema::hasColumn('events', 'event_date'),
                        fn (Builder $query) => $query->orderBy('event_date'),
                    )
                    ->first();

                if ($activeEvent) {
                    return $activeEvent;
                }
            }

            /*
             * Fallback: treat today's event as active when no status-based
             * active event exists.
             */
            $dateColumn = $this->eventDateColumn();

            if ($dateColumn) {
                return $this->eventQuery()
                    ->whereDate($dateColumn, today())
                    ->orderBy($dateColumn)
                    ->first();
            }

            return null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    protected function getNextUpcomingEvent(): ?Event
    {
        try {
            if (! class_exists(Event::class) || ! Schema::hasTable('events')) {
                return null;
            }

            $dateColumn = $this->eventDateColumn();

            if (! $dateColumn) {
                return null;
            }

            $query = $this->eventQuery();

            /*
             * When an event is selected, find the next event after the selected
             * event's date. Otherwise, find the closest future event.
             */
            if (filled($this->selectedEventId)) {
                $selectedEvent = $this->eventQuery()->find($this->selectedEventId);

                if ($selectedEvent && filled($selectedEvent->{$dateColumn})) {
                    $query->whereDate(
                        $dateColumn,
                        '>',
                        \Illuminate\Support\Carbon::parse(
                            $selectedEvent->{$dateColumn},
                        )->toDateString(),
                    );
                } else {
                    $query->whereDate($dateColumn, '>', today());
                }

                $query->whereKeyNot($this->selectedEventId);
            } else {
                $activeEvent = $this->getActiveEvent();

                if ($activeEvent && filled($activeEvent->{$dateColumn})) {
                    $query->whereDate(
                        $dateColumn,
                        '>',
                        \Illuminate\Support\Carbon::parse(
                            $activeEvent->{$dateColumn},
                        )->toDateString(),
                    );
                } else {
                    $query->whereDate($dateColumn, '>', today());
                }
            }

            if (Schema::hasColumn('events', 'status')) {
                $query->whereNotIn('status', [
                    'cancelled',
                    'canceled',
                    'archived',
                    'completed',
                ]);
            }

            return $query
                ->orderBy($dateColumn)
                ->when(
                    Schema::hasColumn('events', 'start_time'),
                    fn (Builder $query) => $query->orderBy('start_time'),
                )
                ->first();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    protected function eventDateColumn(): ?string
    {
        foreach (['event_date', 'date', 'starts_at'] as $column) {
            if (Schema::hasColumn('events', $column)) {
                return $column;
            }
        }

        return null;
    }

    protected function eventLabelColumn(): ?string
    {
        foreach (['title', 'name', 'event_name'] as $column) {
            if (Schema::hasColumn('events', $column)) {
                return $column;
            }
        }

        return null;
    }

    protected function inviteeQuery(): Builder
    {
        $query = Invitee::query();

        if (Schema::hasColumn('invitees', 'event_id')) {
            if (filled($this->selectedEventId)) {
                $query->where('event_id', $this->selectedEventId);
            } else {
                $query->whereIn('event_id', $this->accessibleEventIds());
            }
        }

        return $query;
    }

    protected function smsQuery(): Builder
    {
        $query = SmsLog::query();

        if (Schema::hasColumn('sms_logs', 'event_id')) {
            if (filled($this->selectedEventId)) {
                $query->where('event_id', $this->selectedEventId);
            } else {
                $query->whereIn('event_id', $this->accessibleEventIds());
            }
        }

        return $query;
    }

    protected function checkInQuery(): Builder
    {
        $query = CheckIn::query();

        if (Schema::hasColumn('check_ins', 'event_id')) {
            if (filled($this->selectedEventId)) {
                $query->where('event_id', $this->selectedEventId);
            } else {
                $query->whereIn('event_id', $this->accessibleEventIds());
            }
        }

        return $query;
    }

    protected function safeCount(string $model): int
    {
        try {
            if (! class_exists($model)) {
                return 0;
            }

            return $model::query()->count();
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    protected function countInvitees(): int
    {
        try {
            if (! class_exists(Invitee::class) || ! Schema::hasTable('invitees')) {
                return 0;
            }

            return $this->inviteeQuery()->count();
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    protected function countSms(): int
    {
        try {
            if (! class_exists(SmsLog::class) || ! Schema::hasTable('sms_logs')) {
                return 0;
            }

            return $this->smsQuery()->count();
        } catch (\Throwable $e) {
            report($e);

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
                return $this->eventQuery()
                    ->whereBetween('event_date', [
                        now()->startOfMonth()->toDateString(),
                        now()->endOfMonth()->toDateString(),
                    ])
                    ->count();
            }

            if (Schema::hasColumn('events', 'date')) {
                return $this->eventQuery()
                    ->whereBetween('date', [
                        now()->startOfMonth()->toDateString(),
                        now()->endOfMonth()->toDateString(),
                    ])
                    ->count();
            }

            if (Schema::hasColumn('events', 'created_at')) {
                return $this->eventQuery()
                    ->whereBetween('created_at', [
                        now()->startOfMonth(),
                        now()->endOfMonth(),
                    ])
                    ->count();
            }

            return 0;
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    protected function countInviteesByRsvp(array $statuses): int
    {
        try {
            if (
                ! class_exists(Invitee::class)
                || ! Schema::hasTable('invitees')
                || ! Schema::hasColumn('invitees', 'rsvp_status')
            ) {
                return 0;
            }

            return $this->inviteeQuery()
                ->whereIn('rsvp_status', $statuses)
                ->count();
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    protected function countPendingRsvpInvitees(): int
    {
        try {
            if (
                ! class_exists(Invitee::class)
                || ! Schema::hasTable('invitees')
                || ! Schema::hasColumn('invitees', 'rsvp_status')
            ) {
                return 0;
            }

            return $this->inviteeQuery()
                ->where(function (Builder $query): void {
                    $query
                        ->whereNull('rsvp_status')
                        ->orWhere('rsvp_status', '')
                        ->orWhere('rsvp_status', 'pending');
                })
                ->count();
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    protected function countCheckedInInvitees(): int
    {
        try {
            if (class_exists(Invitee::class) && Schema::hasTable('invitees')) {
                if (Schema::hasColumn('invitees', 'checked_in_count')) {
                    return $this->inviteeQuery()
                        ->where('checked_in_count', '>', 0)
                        ->count();
                }

                if (Schema::hasColumn('invitees', 'check_in_status')) {
                    return $this->inviteeQuery()
                        ->whereIn('check_in_status', [
                            'checked_in',
                            'partial',
                        ])
                        ->count();
                }

                if (Schema::hasColumn('invitees', 'checked_in_at')) {
                    return $this->inviteeQuery()
                        ->whereNotNull('checked_in_at')
                        ->count();
                }
            }

            if (
                class_exists(CheckIn::class)
                && Schema::hasTable('check_ins')
                && Schema::hasColumn('check_ins', 'invitee_id')
            ) {
                return $this->checkInQuery()
                    ->whereNotNull('invitee_id')
                    ->distinct()
                    ->count('invitee_id');
            }

            return 0;
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    protected function countSmsByStatus(array $statuses): int
    {
        try {
            if (
                ! class_exists(SmsLog::class)
                || ! Schema::hasTable('sms_logs')
                || ! Schema::hasColumn('sms_logs', 'status')
            ) {
                return 0;
            }

            return $this->smsQuery()
                ->whereIn('status', $statuses)
                ->count();
        } catch (\Throwable $e) {
            report($e);

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
                    return $this->smsQuery()
                        ->whereIn($column, $types)
                        ->count();
                }
            }

            return 0;
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    protected function getUpcomingEvents()
    {
        try {
            if (! class_exists(Event::class) || ! Schema::hasTable('events')) {
                return collect();
            }

            if (filled($this->selectedEventId)) {
                return $this->eventQuery()
                    ->whereKey($this->selectedEventId)
                    ->get();
            }

            if (Schema::hasColumn('events', 'event_date')) {
                return $this->eventQuery()
                    ->whereDate('event_date', '>=', now()->toDateString())
                    ->orderBy('event_date')
                    ->limit(5)
                    ->get();
            }

            if (Schema::hasColumn('events', 'date')) {
                return $this->eventQuery()
                    ->whereDate('date', '>=', now()->toDateString())
                    ->orderBy('date')
                    ->limit(5)
                    ->get();
            }

            return $this->eventQuery()
                ->latest()
                ->limit(5)
                ->get();
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    protected function getRecentSmsLogs()
    {
        try {
            if (! class_exists(SmsLog::class) || ! Schema::hasTable('sms_logs')) {
                return collect();
            }

            return $this->smsQuery()
                ->latest()
                ->limit(6)
                ->get();
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    protected function sumAllowedGuests(): int
    {
        try {
            if (! Schema::hasTable('invitees')) {
                return 0;
            }

            foreach (['allowed_guests', 'guest_limit', 'allowed_people'] as $column) {
                if (Schema::hasColumn('invitees', $column)) {
                    return (int) $this->inviteeQuery()->sum($column);
                }
            }

            // Every invitee represents at least one expected person.
            return $this->countInvitees();
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    protected function sumConfirmedGuests(): int
    {
        try {
            if (! Schema::hasTable('invitees')) {
                return 0;
            }

            $query = $this->inviteeQuery();

            if (Schema::hasColumn('invitees', 'rsvp_status')) {
                $query->whereIn('rsvp_status', [
                    'attending',
                    'yes',
                    'confirmed',
                ]);
            }

            foreach (['confirmed_guests', 'rsvp_guest_count', 'guest_count'] as $column) {
                if (Schema::hasColumn('invitees', $column)) {
                    return (int) $query->sum($column);
                }
            }

            // When confirmed guest count is unavailable, attending invitees are
            // treated as one confirmed person each.
            return $this->countInviteesByRsvp([
                'attending',
                'yes',
                'confirmed',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    protected function sumCheckedInGuests(): int
    {
        try {
            if (Schema::hasTable('invitees')) {
                foreach (['checked_in_count', 'checked_in_guests'] as $column) {
                    if (Schema::hasColumn('invitees', $column)) {
                        return (int) $this->inviteeQuery()->sum($column);
                    }
                }
            }

            if (Schema::hasTable('check_ins')) {
                $query = $this->checkInQuery();

                foreach ([
                    'guest_count',
                    'guests_count',
                    'guests_checked_in',
                    'checked_in_count',
                    'quantity',
                ] as $column) {
                    if (Schema::hasColumn('check_ins', $column)) {
                        return (int) $query->sum($column);
                    }
                }

                return $query->count();
            }

            return 0;
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    protected function whatsAppQuery()
    {
        if (! Schema::hasTable('message_logs')) {
            return null;
        }

        $query = DB::table('message_logs');

        if (Schema::hasColumn('message_logs', 'channel')) {
            $query->where('channel', 'whatsapp');
        }

        if (Schema::hasColumn('message_logs', 'event_id')) {
            if (filled($this->selectedEventId)) {
                $query->where('event_id', $this->selectedEventId);
            } else {
                $query->whereIn('event_id', $this->accessibleEventIds());
            }
        }

        return $query;
    }

    protected function countWhatsAppMessages(): int
    {
        try {
            $query = $this->whatsAppQuery();

            return $query ? $query->count() : 0;
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    protected function countWhatsAppByStatus(array $statuses): int
    {
        try {
            if (
                ! Schema::hasTable('message_logs')
                || ! Schema::hasColumn('message_logs', 'status')
            ) {
                return 0;
            }

            $query = $this->whatsAppQuery();

            return $query
                ? $query->whereIn('status', $statuses)->count()
                : 0;
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    protected function getRecentCheckIns()
    {
        try {
            if (! Schema::hasTable('check_ins')) {
                return collect();
            }

            $query = $this->checkInQuery();

            $dateColumn = Schema::hasColumn('check_ins', 'checked_in_at')
                ? 'checked_in_at'
                : 'created_at';

            $records = $query
                ->orderByDesc($dateColumn)
                ->limit(8)
                ->get();

            $inviteeIds = $records
                ->pluck('invitee_id')
                ->filter()
                ->unique()
                ->values();

            $invitees = collect();

            if (
                $inviteeIds->isNotEmpty()
                && Schema::hasTable('invitees')
            ) {
                $nameColumn = Schema::hasColumn('invitees', 'name')
                    ? 'name'
                    : (Schema::hasColumn('invitees', 'full_name') ? 'full_name' : null);

                if ($nameColumn) {
                    $invitees = DB::table('invitees')
                        ->whereIn('id', $inviteeIds)
                        ->pluck($nameColumn, 'id');
                }
            }

            return $records->map(function ($record) use ($invitees, $dateColumn) {
                $guestCount = $record->guest_count
                    ?? $record->guests_count
                    ?? $record->guests_checked_in
                    ?? $record->checked_in_count
                    ?? $record->quantity
                    ?? 1;

                return (object) [
                    'invitee_name' => $invitees[$record->invitee_id ?? 0]
                        ?? 'Unknown invitee',
                    'guest_count' => (int) $guestCount,
                    'method' => $record->method
                        ?? $record->check_in_method
                        ?? $record->checkin_method
                        ?? 'manual',
                    'status' => $record->status ?? 'successful',
                    'gate' => $record->gate_name
                        ?? $record->gate
                        ?? null,
                    'checked_in_at' => $record->{$dateColumn} ?? null,
                ];
            });
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

    protected function countInviteesWithoutPhone(): int
    {
        try {
            if (! Schema::hasTable('invitees')) {
                return 0;
            }

            $phoneColumn = Schema::hasColumn('invitees', 'phone')
                ? 'phone'
                : (Schema::hasColumn('invitees', 'phone_number') ? 'phone_number' : null);

            if (! $phoneColumn) {
                return 0;
            }

            return $this->inviteeQuery()
                ->where(function (Builder $query) use ($phoneColumn): void {
                    $query
                        ->whereNull($phoneColumn)
                        ->orWhere($phoneColumn, '');
                })
                ->count();
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    protected function countInviteesWithoutTable(): int
    {
        try {
            if (
                ! Schema::hasTable('invitees')
                || ! Schema::hasColumn('invitees', 'table_number')
            ) {
                return 0;
            }

            return $this->inviteeQuery()
                ->where(function (Builder $query): void {
                    $query
                        ->whereNull('table_number')
                        ->orWhere('table_number', '');
                })
                ->count();
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    protected function countInviteesWithoutCards(): int
    {
        try {
            if (
                ! Schema::hasTable('invitees')
                || ! Schema::hasTable('generated_cards')
                || ! Schema::hasColumn('generated_cards', 'invitee_id')
            ) {
                return 0;
            }

            $inviteeIds = $this->inviteeQuery()->pluck('id');

            if ($inviteeIds->isEmpty()) {
                return 0;
            }

            $generatedInviteeIds = DB::table('generated_cards')
                ->whereIn('invitee_id', $inviteeIds)
                ->when(
                    filled($this->selectedEventId)
                    && Schema::hasColumn('generated_cards', 'event_id'),
                    fn ($query) => $query->where('event_id', $this->selectedEventId),
                )
                ->when(
                    Schema::hasColumn('generated_cards', 'status'),
                    fn ($query) => $query->whereIn('status', ['generated', 'sent']),
                )
                ->distinct()
                ->pluck('invitee_id');

            return $inviteeIds->diff($generatedInviteeIds)->count();
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    protected function countPendingPhotos(): int
    {
        try {
            if (! Schema::hasTable('invitee_uploads')) {
                return 0;
            }

            $query = DB::table('invitee_uploads');

            if (
                filled($this->selectedEventId)
                && Schema::hasColumn('invitee_uploads', 'event_id')
            ) {
                $query->where('event_id', $this->selectedEventId);
            }

            if (Schema::hasColumn('invitee_uploads', 'status')) {
                $query->where('status', 'pending');
            }

            if (Schema::hasColumn('invitee_uploads', 'type')) {
                $query->whereIn('type', ['photo', 'image']);
            }

            return $query->count();
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    protected function countPendingWishes(): int
    {
        try {
            if (Schema::hasTable('invitee_wishes')) {
                $query = DB::table('invitee_wishes');

                if (
                    filled($this->selectedEventId)
                    && Schema::hasColumn('invitee_wishes', 'event_id')
                ) {
                    $query->where('event_id', $this->selectedEventId);
                }

                if (Schema::hasColumn('invitee_wishes', 'status')) {
                    $query->where('status', 'pending');
                }

                return $query->count();
            }

            if (
                Schema::hasTable('invitee_uploads')
                && Schema::hasColumn('invitee_uploads', 'type')
            ) {
                $query = DB::table('invitee_uploads')
                    ->whereIn('type', ['wish', 'message']);

                if (
                    filled($this->selectedEventId)
                    && Schema::hasColumn('invitee_uploads', 'event_id')
                ) {
                    $query->where('event_id', $this->selectedEventId);
                }

                if (Schema::hasColumn('invitee_uploads', 'status')) {
                    $query->where('status', 'pending');
                }

                return $query->count();
            }

            return 0;
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    protected function getSystemAlerts(): array
    {
        $alerts = [];

        $failedMessages = $this->countSmsByStatus(['failed', 'error'])
            + $this->countWhatsAppByStatus(['failed', 'error']);

        $missingPhones = $this->countInviteesWithoutPhone();
        $missingCards = $this->countInviteesWithoutCards();
        $missingTables = $this->countInviteesWithoutTable();
        $pendingPhotos = $this->countPendingPhotos();
        $pendingWishes = $this->countPendingWishes();

        if ($failedMessages > 0) {
            $alerts[] = [
                'level' => 'danger',
                'title' => number_format($failedMessages).' messages failed',
                'description' => 'Review failed SMS and WhatsApp delivery attempts.',
                'url' => url('/admin/sms-logs'),
            ];
        }

        if ($missingPhones > 0) {
            $alerts[] = [
                'level' => 'warning',
                'title' => number_format($missingPhones).' invitees have no phone number',
                'description' => 'Add phone numbers before sending invitations.',
                'url' => url('/admin/events'),
            ];
        }

        if ($missingCards > 0) {
            $alerts[] = [
                'level' => 'warning',
                'title' => number_format($missingCards).' invitees have no generated card',
                'description' => 'Generate their personalized invitation cards.',
                'url' => url('/admin/events'),
            ];
        }

        if ($missingTables > 0) {
            $alerts[] = [
                'level' => 'info',
                'title' => number_format($missingTables).' invitees have no table number',
                'description' => 'Complete table allocation before the event.',
                'url' => url('/admin/events'),
            ];
        }

        if (($pendingPhotos + $pendingWishes) > 0) {
            $alerts[] = [
                'level' => 'info',
                'title' => number_format($pendingPhotos + $pendingWishes).' submissions need approval',
                'description' => 'Review pending invitee photos and wishes.',
                'url' => url('/admin/invitee-uploads'),
            ];
        }

        if ($alerts === []) {
            $alerts[] = [
                'level' => 'success',
                'title' => 'No urgent issues detected',
                'description' => 'The selected dashboard scope is operating normally.',
                'url' => null,
            ];
        }

        return array_slice($alerts, 0, 6);
    }

    protected function getSmsBalance(): string
    {
        return Cache::remember(
            'elive.dashboard.sms_balance',
            now()->addMinutes(5),
            function (): string {
                try {
                    $url = config('services.sms_balance.url');
                    $apiKey = config('services.sms_balance.api_key');
                    $apiSecret = config('services.sms_balance.api_secret');
                    $timeout = (int) config('services.sms_balance.timeout', 30);

                    if (blank($url) || blank($apiKey) || blank($apiSecret)) {
                        return 'Not configured';
                    }

                    $response = Http::timeout($timeout)
                        ->retry(2, 300, throw: false)
                        ->acceptJson()
                        ->withHeaders([
                            'api_key' => (string) $apiKey,
                            'api_secret' => (string) $apiSecret,
                        ])
                        ->get($url);

                    if (! $response->successful()) {
                        Log::warning('SMS balance request failed', [
                            'status' => $response->status(),
                            'body' => str($response->body())
                                ->limit(500)
                                ->toString(),
                        ]);

                        return 'Unavailable';
                    }

                    $payload = $response->json();

                    // eLive returns the balance as data.totalSms.
                    // Read that exact field first so the response code (200)
                    // is never mistaken for the SMS balance.
                    $balance = data_get($payload, 'data.totalSms')
                        ?? data_get($payload, 'data.total_sms')
                        ?? $this->extractSmsBalance($payload);

                    if ($balance === null) {
                        Log::warning(
                            'SMS balance value was not found in provider response',
                            [
                                'response' => $response->json(),
                            ],
                        );

                        return 'Check provider';
                    }

                    return number_format((float) $balance, 0);
                } catch (\Throwable $e) {
                    report($e);

                    return 'Unavailable';
                }
            },
        );
    }

    protected function extractSmsBalance(mixed $data): int|float|string|null
    {
        if (is_numeric($data)) {
            return $data;
        }

        if (! is_array($data)) {
            return null;
        }

      foreach ([
    'balance',
    'sms_balance',
    'totalSms',
    'total_sms',
    'credits',
    'credit',
    'remaining',
    'remaining_balance',
    'available_balance',
] as $key) {
            if (array_key_exists($key, $data) && is_numeric($data[$key])) {
                return $data[$key];
            }
        }

        foreach ($data as $key => $value) {
            // Ignore HTTP/provider metadata such as code => 200.
            if (in_array((string) $key, ['code', 'status', 'status_code'], true)) {
                continue;
            }

            $balance = $this->extractSmsBalance($value);

            if ($balance !== null) {
                return $balance;
            }
        }

        return null;
    }


    protected function eventQuery(): Builder
    {
        $query = Event::query();
        $user = Auth::user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->isSuperAdmin()) {
            return $query;
        }

        if ($this->isCheckInOfficer()) {
            return $query->whereHas('assignedUsers', function (Builder $assigned) use ($user): void {
                $assigned
                    ->where('users.id', $user->getKey())
                    ->where('event_user.role', User::ROLE_CHECK_IN_OFFICER)
                    ->where('event_user.is_active', true);
            });
        }

        if ($this->isEventManager()) {
            return $query->where(function (Builder $eventQuery) use ($user): void {
                if (Schema::hasColumn('events', 'user_id')) {
                    $eventQuery->where('events.user_id', $user->getKey());
                } else {
                    $eventQuery->whereRaw('1 = 0');
                }

                $eventQuery->orWhereHas('assignedUsers', function (Builder $assigned) use ($user): void {
                    $assigned
                        ->where('users.id', $user->getKey())
                        ->where('event_user.is_active', true)
                        ->whereIn('event_user.role', [
                            User::ROLE_EVENT_ADMIN,
                            'event_manager',
                        ]);
                });
            });
        }

        return $query->whereRaw('1 = 0');
    }

    protected function accessibleEventIds(): array
    {
        if ($this->accessibleEventIdsCache !== null) {
            return $this->accessibleEventIdsCache;
        }

        return $this->accessibleEventIdsCache = $this->eventQuery()
            ->pluck('events.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function getAccessibleEvents()
    {
        return $this->eventQuery()
            ->withCount('invitees')
            ->when(
                Schema::hasColumn('events', 'event_date'),
                fn (Builder $query) => $query->orderBy('event_date'),
            )
            ->limit($this->isCheckInOfficer() ? 12 : 8)
            ->get();
    }

    protected function isSuperAdmin(): bool
    {
        $user = Auth::user();

        return $user
            && (
                (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin())
                || ($user->role ?? null) === User::ROLE_SUPER_ADMIN
            );
    }

    protected function isEventManager(): bool
    {
        $user = Auth::user();

        return $user
            && ! $this->isCheckInOfficer()
            && (
                (method_exists($user, 'isEventAdmin') && $user->isEventAdmin())
                || in_array($user->role ?? null, [
                    User::ROLE_EVENT_ADMIN,
                    'event_manager',
                ], true)
            );
    }

    protected function isCheckInOfficer(): bool
    {
        $user = Auth::user();

        return $user
            && (
                (method_exists($user, 'isCheckInOfficer') && $user->isCheckInOfficer())
                || ($user->role ?? null) === User::ROLE_CHECK_IN_OFFICER
            );
    }

    protected function getRoleLabel(): string
    {
        return match (true) {
            $this->isSuperAdmin() => 'Super Admin',
            $this->isCheckInOfficer() => 'Check-in Officer',
            $this->isEventManager() => 'Event Manager',
            default => 'User',
        };
    }

    protected function countCheckInTransactions(): int
    {
        try {
            return Schema::hasTable('check_ins')
                ? $this->checkInQuery()->count()
                : 0;
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    protected function countFullyCheckedInInvitees(): int
    {
        try {
            if (! Schema::hasTable('invitees')) {
                return 0;
            }

            return $this->inviteeQuery()
                ->whereColumn('checked_in_count', '>=', 'allowed_guests')
                ->where('checked_in_count', '>', 0)
                ->count();
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    protected function countPartiallyCheckedInInvitees(): int
    {
        try {
            if (! Schema::hasTable('invitees')) {
                return 0;
            }

            return $this->inviteeQuery()
                ->where('checked_in_count', '>', 0)
                ->whereColumn('checked_in_count', '<', 'allowed_guests')
                ->count();
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    protected function getOfficerRecentCheckIns()
    {
        try {
            if (! Schema::hasTable('check_ins')) {
                return collect();
            }

            $query = $this->checkInQuery();

            if (
                $this->isCheckInOfficer()
                && Schema::hasColumn('check_ins', 'checked_in_by')
            ) {
                $query->where('checked_in_by', Auth::id());
            }

            return $query
                ->with(['invitee', 'event'])
                ->orderByDesc(
                    Schema::hasColumn('check_ins', 'checked_in_at')
                        ? 'checked_in_at'
                        : 'created_at'
                )
                ->limit(10)
                ->get();
        } catch (\Throwable $e) {
            report($e);

            return collect();
        }
    }

}
