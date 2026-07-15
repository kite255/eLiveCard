<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Models\Event;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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
        'statusFilter' => [
            'except' => 'all',
        ],
        'typeFilter' => [
            'except' => 'all',
        ],
        'search' => [
            'except' => '',
        ],
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
        return [
            'events' => $this->getEvents(),
            'eventTypes' => Event::eventTypes(),
            'statusCounts' => $this->getStatusCounts(),
            'hasActiveFilters' => $this->statusFilter !== 'all'
                || $this->typeFilter !== 'all'
                || filled($this->search),
        ];
    }

    protected function getEvents(): LengthAwarePaginator
    {
        return $this->baseEventQuery()
            ->when(
                $this->statusFilter === 'upcoming',
                fn (Builder $query): Builder => $query
                    ->whereDate('event_date', '>=', today())
                    ->whereNotIn('status', [
                        Event::STATUS_COMPLETED,
                        Event::STATUS_CANCELLED,
                    ]),
            )
            ->when(
                ! in_array($this->statusFilter, ['all', 'upcoming'], true),
                fn (Builder $query): Builder => $query
                    ->where('status', $this->statusFilter),
            )
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
            ->orderByRaw(
                'CASE WHEN event_date IS NULL THEN 1 ELSE 0 END',
            )
            ->orderBy('event_date')
            ->orderByRaw(
                'CASE WHEN start_time IS NULL THEN 1 ELSE 0 END',
            )
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

    protected function getStatusCounts(): array
    {
        $query = EventResource::getEloquentQuery();

        return [
            'all' => (clone $query)->count(),

            'active' => (clone $query)
                ->where('status', Event::STATUS_ACTIVE)
                ->count(),

            'upcoming' => (clone $query)
                ->whereDate('event_date', '>=', today())
                ->whereNotIn('status', [
                    Event::STATUS_COMPLETED,
                    Event::STATUS_CANCELLED,
                ])
                ->count(),

            'draft' => (clone $query)
                ->where('status', Event::STATUS_DRAFT)
                ->count(),

            'completed' => (clone $query)
                ->where('status', Event::STATUS_COMPLETED)
                ->count(),
        ];
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
