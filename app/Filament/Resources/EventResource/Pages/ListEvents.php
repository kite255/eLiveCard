<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Models\Event;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ListEvents extends ListRecords
{
    protected static string $resource = EventResource::class;

    protected static string $view = 'filament.resources.event-resource.pages.list-events';

    protected static ?string $title = 'Events';

    protected ?string $heading = '';

    protected ?string $subheading = '';

    public string $statusFilter = 'all';

    public string $typeFilter = 'all';

    public string $search = '';

    protected $queryString = [
        'statusFilter' => ['except' => 'all'],
        'typeFilter' => ['except' => 'all'],
        'search' => ['except' => ''],
    ];

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create New Event')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->visible(fn (): bool => EventResource::canCreate()),
        ];
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    public function mount(): void
    {
        parent::mount();

        $this->normaliseFilters();
        $this->synchroniseFinishedEvents();
    }

    public function updatedStatusFilter(): void
    {
        $this->normaliseFilters();
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->normaliseFilters();
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->search = trim($this->search);
        $this->resetPage();
    }

    public function setStatusFilter(string $status): void
    {
        $allowedStatuses = [
            'all',
            'active',
            'upcoming',
            'draft',
            'completed',
        ];

        $this->statusFilter = in_array($status, $allowedStatuses, true)
            ? $status
            : 'all';

        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->statusFilter = 'all';
        $this->typeFilter = 'all';
        $this->search = '';

        $this->resetPage();
    }

    protected function getViewData(): array
    {
        $this->synchroniseFinishedEvents();

        $statusIds = $this->getStatusIds();

        return [
            'events' => $this->getEvents($statusIds),
            'eventTypes' => Event::eventTypes(),
            'statusCounts' => [
                'all' => $statusIds['all']->count(),
                'active' => $statusIds['active']->count(),
                'upcoming' => $statusIds['upcoming']->count(),
                'draft' => $statusIds['draft']->count(),
                'completed' => $statusIds['completed']->count(),
            ],
            'hasActiveFilters' => $this->statusFilter !== 'all'
                || $this->typeFilter !== 'all'
                || filled($this->search),
        ];
    }

    protected function getEvents(array $statusIds): LengthAwarePaginator
    {
        $query = $this->baseEventQuery();

        if ($this->statusFilter !== 'all') {
            $query->whereIn(
                $query->getModel()->getQualifiedKeyName(),
                $statusIds[$this->statusFilter] ?? collect(),
            );
        }

        return $query
            ->when(
                $this->typeFilter !== 'all',
                fn (Builder $query): Builder => $query
                    ->where('event_type', $this->typeFilter),
            )
            ->when(
                filled($this->search),
                fn (Builder $query): Builder => $this->applySearch(
                    $query,
                    trim($this->search),
                ),
            )
            ->orderByRaw(
                '
                    CASE
                        WHEN status = ? THEN 0
                        WHEN event_date >= CURRENT_DATE THEN 1
                        WHEN status = ? THEN 3
                        ELSE 2
                    END
                ',
                [
                    Event::STATUS_ACTIVE,
                    Event::STATUS_COMPLETED,
                ],
            )
            ->orderByRaw('CASE WHEN event_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('event_date')
            ->orderByRaw('CASE WHEN start_time IS NULL THEN 1 ELSE 0 END')
            ->orderBy('start_time')
            ->orderByDesc('id')
            ->paginate(8)
            ->withQueryString();
    }

    protected function baseEventQuery(): Builder
    {
        return EventResource::getEloquentQuery()
            ->with('user')
            ->withCount([
                'invitees',
                'generatedCards',
                'checkIns',

                'invitees as rsvp_attending_count' => fn (Builder $query): Builder =>
                    $query->where('rsvp_status', 'attending'),

                'invitees as rsvp_not_attending_count' => fn (Builder $query): Builder =>
                    $query->where('rsvp_status', 'not_attending'),

                'invitees as rsvp_pending_count' => fn (Builder $query): Builder =>
                    $query->where(function (Builder $query): void {
                        $query
                            ->whereNull('rsvp_status')
                            ->orWhere('rsvp_status', '')
                            ->orWhere('rsvp_status', 'pending');
                    }),

                'generatedCards as generated_cards_ready_count' => fn (Builder $query): Builder =>
                    $query->whereIn('status', [
                        'generated',
                        'sent',
                    ]),

                'messageLogs as sms_sent_count' => fn (Builder $query): Builder =>
                    $query
                        ->where('channel', 'sms')
                        ->whereIn('status', [
                            'sent',
                            'delivered',
                        ]),

                'messageLogs as whatsapp_sent_count' => fn (Builder $query): Builder =>
                    $query
                        ->where('channel', 'whatsapp')
                        ->whereIn('status', [
                            'sent',
                            'delivered',
                            'read',
                        ]),
            ]);
    }

    protected function getStatusIds(): array
    {
        $groups = [
            'all' => collect(),
            'active' => collect(),
            'upcoming' => collect(),
            'draft' => collect(),
            'completed' => collect(),
        ];

        EventResource::getEloquentQuery()
            ->select([
                'id',
                'status',
                'event_date',
                'start_time',
                'end_time',
            ])
            ->orderBy('id')
            ->chunkById(250, function (Collection $events) use (&$groups): void {
                foreach ($events as $event) {
                    $groups['all']->push($event->getKey());

                    $classification = $this->classifyEvent($event);

                    if (isset($groups[$classification])) {
                        $groups[$classification]->push($event->getKey());
                    }
                }
            });

        return $groups;
    }

    public function eventPresentation(Event $event): array
    {
        $classification = $this->classifyEvent($event);
        $eventDate = $this->eventDate($event);
        $eventStart = $this->eventStart($event);
        $eventEnd = $this->eventEnd($event);
        $now = now();

        $isHappeningNow = $classification === 'active'
            && $eventStart
            && $eventEnd
            && $now->between($eventStart, $eventEnd);

        $label = match (true) {
            $classification === 'completed' => 'Completed',
            $classification === 'draft' => 'Draft',
            $isHappeningNow => 'Happening Now',
            $classification === 'active' => 'Active',
            $classification === 'upcoming' => 'Upcoming',
            default => 'Unknown',
        };

        $statusClass = match ($classification) {
            'completed' => 'status-completed',
            'draft' => 'status-draft',
            'active' => 'status-happening',
            'upcoming' => 'status-upcoming',
            default => 'status-draft',
        };

        $dateBoxClass = match ($classification) {
            'completed' => 'event-date-box-completed',
            'active' => 'event-date-box-happening',
            'upcoming' => 'event-date-box-upcoming',
            default => '',
        };

        $dateLabel = match (true) {
            $classification === 'completed' => 'Completed',
            $classification === 'draft' => 'Draft',
            $isHappeningNow => 'Happening Now',
            $eventDate?->isToday() => 'Today',
            $eventDate?->isTomorrow() => 'Tomorrow',
            $classification === 'upcoming' && $eventDate !== null =>
                'In '.now()->startOfDay()->diffInDays($eventDate).' days',
            $classification === 'active' => 'Active',
            default => 'Date',
        };

        return [
            'classification' => $classification,
            'label' => $label,
            'statusClass' => $statusClass,
            'dateBoxClass' => $dateBoxClass,
            'dateLabel' => $dateLabel,
            'eventDate' => $eventDate,
            'eventTime' => $eventStart?->format('h:i A') ?? 'Time not set',
        ];
    }

    protected function classifyEvent(Event $event): string
    {
        $storedStatus = strtolower(
            (string) ($event->status ?? Event::STATUS_DRAFT),
        );

        if ($storedStatus === Event::STATUS_DRAFT) {
            return 'draft';
        }

        if ($storedStatus === Event::STATUS_COMPLETED) {
            return 'completed';
        }

        if (in_array($storedStatus, ['cancelled', 'canceled'], true)) {
            return 'completed';
        }

        $now = now();
        $eventStart = $this->eventStart($event);
        $eventEnd = $this->eventEnd($event);

        if ($eventEnd && $now->greaterThan($eventEnd)) {
            return 'completed';
        }

        if ($eventStart && $now->lessThan($eventStart)) {
            return 'upcoming';
        }

        return 'active';
    }

    protected function synchroniseFinishedEvents(): void
    {
        EventResource::getEloquentQuery()
            ->where('status', Event::STATUS_ACTIVE)
            ->whereNotNull('event_date')
            ->select([
                'id',
                'status',
                'event_date',
                'start_time',
                'end_time',
            ])
            ->orderBy('id')
            ->chunkById(250, function (Collection $events): void {
                foreach ($events as $event) {
                    $eventEnd = $this->eventEnd($event);

                    if (! $eventEnd || now()->lessThanOrEqualTo($eventEnd)) {
                        continue;
                    }

                    Event::query()
                        ->whereKey($event->getKey())
                        ->where('status', Event::STATUS_ACTIVE)
                        ->update([
                            'status' => Event::STATUS_COMPLETED,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    protected function eventDate(Event $event): ?Carbon
    {
        if (blank($event->event_date)) {
            return null;
        }

        return Carbon::parse($event->event_date)->startOfDay();
    }

    protected function eventStart(Event $event): ?Carbon
    {
        $eventDate = $this->eventDate($event);

        if (! $eventDate) {
            return null;
        }

        if (blank($event->start_time)) {
            return $eventDate;
        }

        $startTime = Carbon::parse($event->start_time);

        return $eventDate->setTime(
            $startTime->hour,
            $startTime->minute,
            $startTime->second,
        );
    }

    protected function eventEnd(Event $event): ?Carbon
    {
        $eventDate = $this->eventDate($event);

        if (! $eventDate) {
            return null;
        }

        if (filled($event->end_time)) {
            $endTime = Carbon::parse($event->end_time);

            return $eventDate->setTime(
                $endTime->hour,
                $endTime->minute,
                $endTime->second,
            );
        }

        if (filled($event->start_time)) {
            return $this->eventStart($event)?->addHours(6);
        }

        return $eventDate->endOfDay();
    }

    protected function applySearch(
        Builder $query,
        string $search,
    ): Builder {
        if ($search === '') {
            return $query;
        }

        $searchableColumns = collect([
            'title',
            'venue_name',
            'venue_address',
            'event_type',
            'status',
        ])->filter(
            fn (string $column): bool => Schema::hasColumn('events', $column),
        )->values();

        if ($searchableColumns->isEmpty()) {
            return $query;
        }

        return $query->where(
            function (Builder $query) use ($search, $searchableColumns): void {
                foreach ($searchableColumns as $index => $column) {
                    if ($index === 0) {
                        $query->whereLike(
                            $column,
                            "%{$search}%",
                            caseSensitive: false,
                        );

                        continue;
                    }

                    $query->orWhereLike(
                        $column,
                        "%{$search}%",
                        caseSensitive: false,
                    );
                }
            },
        );
    }

    protected function normaliseFilters(): void
    {
        $allowedStatuses = [
            'all',
            'active',
            'upcoming',
            'draft',
            'completed',
        ];

        if (! in_array($this->statusFilter, $allowedStatuses, true)) {
            $this->statusFilter = 'all';
        }

        $allowedTypes = array_keys(Event::eventTypes());

        if (
            $this->typeFilter !== 'all'
            && ! in_array($this->typeFilter, $allowedTypes, true)
        ) {
            $this->typeFilter = 'all';
        }

        $this->search = trim($this->search);
    }
}
