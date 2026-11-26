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

    // Protected instance properties are not persisted across Livewire requests.
    // Cache only schema metadata, never event data or authorization decisions.
    protected array $dashboardTableChecks = [];

    protected array $dashboardColumnChecks = [];

    protected array $dashboardMessageCounts = [];

    /**
     * Reuse grouped counts within the current render and event scope.
     * No message counts are persisted in the application cache.
     */
    protected function countGroupedMessages($query, string $column, string $source, array $values): int
    {
        $key = $source.':'.$column.':'.(Auth::id() ?? 'guest').':'.($this->selectedEventId ?? 'all');

        if (! array_key_exists($key, $this->dashboardMessageCounts)) {
            $this->dashboardMessageCounts[$key] = $query
                ->whereNotNull($column)
                ->select($column)
                ->selectRaw('COUNT(*) AS total')
                ->groupBy($column)
                ->pluck('total', $column)
                ->all();
        }

        return (int) array_sum(array_intersect_key(
            $this->dashboardMessageCounts[$key],
            array_fill_keys($values, true),
        ));
    }

    protected function dashboardHasTable(string $table): bool
    {
        return $this->dashboardTableChecks[$table] ??= Schema::hasTable($table);
    }

    protected function dashboardHasColumn(string $table, string $column): bool
    {
        return $this->dashboardColumnChecks[$table][$column] ??= Schema::hasColumn($table, $column);
    }

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
                    $this->dashboardHasColumn('events', 'event_date'),
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
        $this->dashboardMessageCounts = [];

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

        $invitationDelivery = $this->invitationDeliveryCounts();

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

            'invitationDelivery' => $invitationDelivery,
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

            'smsBalance' => $isSuperAdmin ? $this->getSmsBalance() : 'Restricted',

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
            if (! class_exists(Event::class) || ! $this->dashboardHasTable('events')) {
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
                || ! $this->dashboardHasTable('events')
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
        $query = $this->eventQuery()
            ->whereDate('event_date', today())
            ->whereIn('status', ['active', 'ongoing', 'in_progress', 'published']);

        if ($this->selectedEventId !== null) {
            $query->whereKey($this->selectedEventId);
        }

        return $query->orderBy('start_time')->first();
    }

    protected function getNextUpcomingEvent(): ?Event
    {
        return $this->eventQuery()
            ->whereDate('event_date', '>', today())
            ->whereNotIn('status', ['cancelled', 'canceled', 'archived', 'completed', 'draft'])
            ->orderBy('event_date')->orderBy('start_time')->first();
    }

    protected function eventDateColumn(): ?string
    {
        foreach (['event_date', 'date', 'starts_at'] as $column) {
            if ($this->dashboardHasColumn('events', $column)) {
                return $column;
            }
        }

        return null;
    }

    protected function eventLabelColumn(): ?string
    {
        foreach (['title', 'name', 'event_name'] as $column) {
            if ($this->dashboardHasColumn('events', $column)) {
                return $column;
            }
        }

        return null;
    }

    /** Selected event is always intersected with the authenticated user's scope. */
    protected function dashboardEventIds(): array
    {
        $ids = $this->accessibleEventIds();

        return $this->selectedEventId === null
            ? $ids
            : array_values(array_intersect($ids, [$this->selectedEventId]));
    }

    protected function inviteeQuery(): Builder
    {
        $query = Invitee::query();

        if ($this->dashboardHasColumn('invitees', 'event_id')) {
            $query->whereIn('event_id', $this->dashboardEventIds());
        }

        return $query;
    }

    protected function smsQuery(): Builder
    {
        $query = SmsLog::query();

        if ($this->dashboardHasColumn('sms_logs', 'event_id')) {
            $query->whereIn('event_id', $this->dashboardEventIds());
        }

        return $query;
    }

    protected function checkInQuery(): Builder
    {
        $query = CheckIn::query();

        if ($this->dashboardHasColumn('check_ins', 'event_id')) {
            $query->whereIn('event_id', $this->dashboardEventIds());
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
            if (! class_exists(Invitee::class) || ! $this->dashboardHasTable('invitees')) {
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
            if (! class_exists(SmsLog::class) || ! $this->dashboardHasTable('sms_logs')) {
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
            if (! class_exists(Event::class) || ! $this->dashboardHasTable('events')) {
                return 0;
            }

            if ($this->dashboardHasColumn('events', 'event_date')) {
                return $this->eventQuery()
                    ->whereBetween('event_date', [
                        now()->startOfMonth()->toDateString(),
                        now()->endOfMonth()->toDateString(),
                    ])
                    ->count();
            }

            if ($this->dashboardHasColumn('events', 'date')) {
                return $this->eventQuery()
                    ->whereBetween('date', [
                        now()->startOfMonth()->toDateString(),
                        now()->endOfMonth()->toDateString(),
                    ])
                    ->count();
            }

            if ($this->dashboardHasColumn('events', 'created_at')) {
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
                || ! $this->dashboardHasTable('invitees')
                || ! $this->dashboardHasColumn('invitees', 'rsvp_status')
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
                || ! $this->dashboardHasTable('invitees')
                || ! $this->dashboardHasColumn('invitees', 'rsvp_status')
            ) {
                return 0;
            }

            return $this->inviteeQuery()
                ->where(function (Builder $query): void {
                    $query
                        ->whereNull('rsvp_status')
                        ->orWhere('rsvp_status', '')
                        ->orWhereIn('rsvp_status', ['pending', 'maybe']);
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
            if (class_exists(Invitee::class) && $this->dashboardHasTable('invitees')) {
                if ($this->dashboardHasColumn('invitees', 'checked_in_count')) {
                    return $this->inviteeQuery()
                        ->where('checked_in_count', '>', 0)
                        ->count();
                }

                if ($this->dashboardHasColumn('invitees', 'check_in_status')) {
                    return $this->inviteeQuery()
                        ->whereIn('check_in_status', [
                            'checked_in',
                            'partial',
                        ])
                        ->count();
                }

                if ($this->dashboardHasColumn('invitees', 'checked_in_at')) {
                    return $this->inviteeQuery()
                        ->whereNotNull('checked_in_at')
                        ->count();
                }
            }

            if (
                class_exists(CheckIn::class)
                && $this->dashboardHasTable('check_ins')
                && $this->dashboardHasColumn('check_ins', 'invitee_id')
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
                || ! $this->dashboardHasTable('sms_logs')
                || ! $this->dashboardHasColumn('sms_logs', 'status')
            ) {
                return 0;
            }

            return $this->countGroupedMessages($this->smsQuery(), 'status', 'sms', $statuses);
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    protected function countSmsByType(array $types): int
    {
        try {
            if (! class_exists(SmsLog::class) || ! $this->dashboardHasTable('sms_logs')) {
                return 0;
            }

            foreach (['sms_type', 'message_type', 'type', 'category'] as $column) {
                if ($this->dashboardHasColumn('sms_logs', $column)) {
                    return $this->countGroupedMessages($this->smsQuery(), $column, 'sms', $types);
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
            if (! class_exists(Event::class) || ! $this->dashboardHasTable('events')) {
                return collect();
            }

            if (filled($this->selectedEventId)) {
                return $this->eventQuery()
                    ->whereKey($this->selectedEventId)
                    ->get();
            }

            if ($this->dashboardHasColumn('events', 'event_date')) {
                return $this->eventQuery()
                    ->whereDate('event_date', '>=', now()->toDateString())
                    ->orderBy('event_date')
                    ->limit(5)
                    ->get();
            }

            if ($this->dashboardHasColumn('events', 'date')) {
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
            if (! class_exists(SmsLog::class) || ! $this->dashboardHasTable('sms_logs')) {
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
            if (! $this->dashboardHasTable('invitees')) {
                return 0;
            }

            foreach (['allowed_guests', 'guest_limit', 'allowed_people'] as $column) {
                if ($this->dashboardHasColumn('invitees', $column)) {
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
            if (! $this->dashboardHasTable('invitees')) {
                return 0;
            }

            $query = $this->inviteeQuery();

            if ($this->dashboardHasColumn('invitees', 'rsvp_status')) {
                $query->whereIn('rsvp_status', [
                    'attending',
                    'yes',
                    'confirmed',
                ]);
            }

            foreach (['confirmed_guests', 'rsvp_guest_count', 'guest_count'] as $column) {
                if ($this->dashboardHasColumn('invitees', $column)) {
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
            if ($this->dashboardHasTable('invitees')) {
                foreach (['checked_in_count', 'checked_in_guests'] as $column) {
                    if ($this->dashboardHasColumn('invitees', $column)) {
                        return (int) $this->inviteeQuery()->sum($column);
                    }
                }
            }

            if ($this->dashboardHasTable('check_ins')) {
                $query = $this->checkInQuery();

                foreach ([
                    'guest_count',
                    'guests_count',
                    'guests_checked_in',
                    'checked_in_count',
                    'quantity',
                ] as $column) {
                    if ($this->dashboardHasColumn('check_ins', $column)) {
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
        if (! $this->dashboardHasTable('message_logs')) {
            return null;
        }

        $query = DB::table('message_logs');

        if ($this->dashboardHasColumn('message_logs', 'channel')) {
            $query->where('channel', 'whatsapp');
        }

        if ($this->dashboardHasColumn('message_logs', 'event_id')) {
            $query->whereIn('event_id', $this->dashboardEventIds());
        }

        return $query;
    }

    protected function invitationDeliveryCounts(): array
    {
        $result = ['whatsapp' => [], 'sms' => []];
        $whatsapp = app(\App\Services\InvitationWhatsAppDeliveryStatusService::class)->expression();
        $sms = app(\App\Services\InvitationSmsReportStatusService::class)->expression();
        foreach (['whatsapp' => $whatsapp, 'sms' => $sms] as $channel => $expression) {
            $resolved = $this->inviteeQuery()->selectRaw("({$expression}) AS delivery_status");
            $result[$channel] = DB::query()->fromSub($resolved, 'resolved_invitees')
                ->select('delivery_status')->selectRaw('COUNT(*) AS total')
                ->groupBy('delivery_status')->pluck('total', 'delivery_status')->map(fn ($count) => (int) $count)->all();
        }

        return $result;
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
                ! $this->dashboardHasTable('message_logs')
                || ! $this->dashboardHasColumn('message_logs', 'status')
            ) {
                return 0;
            }

            $query = $this->whatsAppQuery();

            return $query
                ? $this->countGroupedMessages($query, 'status', 'whatsapp', $statuses)
                : 0;
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    protected function getRecentCheckIns()
    {
        try {
            if (! $this->dashboardHasTable('check_ins')) {
                return collect();
            }

            $query = $this->checkInQuery();

            $dateColumn = $this->dashboardHasColumn('check_ins', 'checked_in_at')
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
                && $this->dashboardHasTable('invitees')
            ) {
                $nameColumn = $this->dashboardHasColumn('invitees', 'name')
                    ? 'name'
                    : ($this->dashboardHasColumn('invitees', 'full_name') ? 'full_name' : null);

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
            if (! $this->dashboardHasTable('invitees')) {
                return 0;
            }

            $phoneColumn = $this->dashboardHasColumn('invitees', 'phone')
                ? 'phone'
                : ($this->dashboardHasColumn('invitees', 'phone_number') ? 'phone_number' : null);

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
                ! $this->dashboardHasTable('invitees')
                || ! $this->dashboardHasColumn('invitees', 'table_number')
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
                ! $this->dashboardHasTable('invitees')
                || ! $this->dashboardHasTable('generated_cards')
                || ! $this->dashboardHasColumn('generated_cards', 'invitee_id')
            ) {
                return 0;
            }

            $hasCardStatus = $this->dashboardHasColumn('generated_cards', 'status');
            $hasCardEvent = $this->dashboardHasColumn('generated_cards', 'event_id');

            // Keep the entire computation in PostgreSQL; do not materialize all
            // invitee and card IDs in PHP for dashboard alerts.
            return $this->inviteeQuery()
                ->whereNotExists(function ($query) use ($hasCardStatus, $hasCardEvent): void {
                    $query->selectRaw('1')
                        ->from('generated_cards')
                        ->whereColumn('generated_cards.invitee_id', 'invitees.id');

                    if (filled($this->selectedEventId) && $hasCardEvent) {
                        $query->where('generated_cards.event_id', $this->selectedEventId);
                    }

                    if ($hasCardStatus) {
                        $query->whereIn('generated_cards.status', ['generated', 'sent']);
                    }
                })
                ->count();
        } catch (\Throwable $exception) {
            report($exception);

            return 0;
        }
    }

    protected function countPendingPhotos(): int
    {
        try {
            if (! $this->dashboardHasTable('invitee_uploads')) {
                return 0;
            }

            $query = DB::table('invitee_uploads');

            if (! $this->dashboardHasColumn('invitee_uploads', 'event_id')) {
                return 0;
            }
            $query->whereIn('event_id', $this->dashboardEventIds());

            if ($this->dashboardHasColumn('invitee_uploads', 'status')) {
                $query->where('status', 'pending');
            }

            if ($this->dashboardHasColumn('invitee_uploads', 'type')) {
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
            if ($this->dashboardHasTable('invitee_wishes')) {
                $query = DB::table('invitee_wishes');

                if (! $this->dashboardHasColumn('invitee_wishes', 'event_id')) {
                    return 0;
                }
                $query->whereIn('event_id', $this->dashboardEventIds());

                if ($this->dashboardHasColumn('invitee_wishes', 'status')) {
                    $query->where('status', 'pending');
                }

                return $query->count();
            }

            if (
                $this->dashboardHasTable('invitee_uploads')
                && $this->dashboardHasColumn('invitee_uploads', 'type')
            ) {
                $query = DB::table('invitee_uploads')
                    ->whereIn('type', ['wish', 'message']);

                if (! $this->dashboardHasColumn('invitee_uploads', 'event_id')) {
                    return 0;
                }
                $query->whereIn('event_id', $this->dashboardEventIds());

                if ($this->dashboardHasColumn('invitee_uploads', 'status')) {
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
        // Dashboard rendering must not depend on the availability of the SMS provider.
        return app(\App\Services\DashboardSmsBalanceService::class)->cached();
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
                if ($this->dashboardHasColumn('events', 'user_id')) {
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
                $this->dashboardHasColumn('events', 'event_date'),
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
            return $this->dashboardHasTable('check_ins')
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
            if (! $this->dashboardHasTable('invitees')) {
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
            if (! $this->dashboardHasTable('invitees')) {
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
            if (! $this->dashboardHasTable('check_ins')) {
                return collect();
            }

            $query = $this->checkInQuery();

            if (
                $this->isCheckInOfficer()
                && $this->dashboardHasColumn('check_ins', 'checked_in_by')
            ) {
                $query->where('checked_in_by', Auth::id());
            }

            return $query
                ->with(['invitee', 'event'])
                ->orderByDesc(
                    $this->dashboardHasColumn('check_ins', 'checked_in_at')
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
