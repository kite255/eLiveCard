<x-filament-panels::page>
    <style>
        .fi-header {
            display: none !important;
        }

        .events-page {
            width: 100%;
            min-height: auto;
            padding: 8px 4px 30px;
            background: #F8FAFC;
        }

        .events-shell {
            width: 100%;
            max-width: 1380px;
            margin: 0 auto;
        }

        .events-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 18px;
        }

        .events-title {
            margin: 0;
            color: #111827;
            font-size: 28px;
            font-weight: 900;
            letter-spacing: -.03em;
        }

        .events-subtitle {
            margin-top: 6px;
            color: #64748B;
            font-size: 13px;
            font-weight: 600;
        }

        .events-create {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 42px;
            padding: 0 15px;
            border-radius: 12px;
            color: #FFFFFF;
            background: #213B73;
            font-size: 13px;
            font-weight: 850;
            text-decoration: none;
            box-shadow: 0 8px 20px rgba(33, 59, 115, .18);
        }

        .events-create svg {
            width: 18px;
            height: 18px;
        }

        .events-toolbar {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 290px 230px;
            gap: 12px;
            align-items: center;
            margin-bottom: 18px;
        }

        .events-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .events-tab {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            min-height: 38px;
            padding: 0 12px;
            border: 1px solid #E5E7EB;
            border-radius: 11px;
            color: #475569;
            background: #FFFFFF;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
        }

        .events-tab-active {
            color: #FFFFFF;
            border-color: #213B73;
            background: #213B73;
        }

        .events-tab-count {
            display: inline-flex;
            min-width: 20px;
            height: 20px;
            align-items: center;
            justify-content: center;
            padding: 0 6px;
            border-radius: 999px;
            background: rgba(148, 163, 184, .18);
            font-size: 10px;
            font-weight: 900;
        }

        .events-tab-active .events-tab-count {
            background: rgba(255, 255, 255, .18);
        }

        .events-input,
        .events-select {
            width: 100%;
            min-height: 42px;
            border: 1px solid #CBD5E1;
            border-radius: 11px;
            color: #111827;
            font-size: 12px;
            font-weight: 700;
            outline: none;
            box-sizing: border-box;
        }

        .events-input {
            padding: 0 13px;
            background: #FFFFFF;
        }

        .events-select {
            min-width: 0;
            padding: 0 42px 0 13px;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-color: #FFFFFF;
            background-image:
                linear-gradient(45deg, transparent 50%, #64748B 50%),
                linear-gradient(135deg, #64748B 50%, transparent 50%);
            background-position:
                calc(100% - 20px) 17px,
                calc(100% - 14px) 17px;
            background-size: 6px 6px, 6px 6px;
            background-repeat: no-repeat;
        }

        .events-input:focus,
        .events-select:focus {
            border-color: #94A3B8;
            box-shadow: 0 0 0 3px rgba(33, 59, 115, .08);
        }

        .events-filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }

        .events-clear {
            min-height: 42px;
            padding: 0 12px;
            border: 1px solid #CBD5E1;
            border-radius: 11px;
            color: #475569;
            background: #FFFFFF;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
            transition: .18s ease;
        }

        .events-clear:hover {
            color: #213B73;
            border-color: #B8C5DD;
            background: #F8FAFC;
        }

        .events-list {
            display: grid;
            gap: 12px;
        }

        .event-card {
            display: grid;
            grid-template-columns: 112px minmax(0, 1fr) auto;
            gap: 18px;
            align-items: center;
            min-height: 126px;
            padding: 14px;
            border: 1px solid #E5E7EB;
            border-radius: 18px;
            background: #FFFFFF;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
            transition: transform .18s ease, box-shadow .18s ease;
        }

        .event-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 28px rgba(15, 23, 42, .08);
        }

        .event-date-box {
            min-height: 98px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            color: #213B73;
            background: #EEF4FF;
            text-align: center;
        }

        .event-date-box-active {
            color: #15803D;
            background: #EAFBF0;
        }

        .event-date-box-upcoming {
            color: #C2410C;
            background: #FFF3E4;
        }

        .event-date-label {
            font-size: 9px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .09em;
        }

        .event-date-day {
            margin-top: 3px;
            font-size: 22px;
            line-height: 1;
            font-weight: 900;
        }

        .event-date-month {
            margin-top: 3px;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .event-date-time {
            margin-top: 6px;
            font-size: 11px;
            font-weight: 850;
        }

        .event-content {
            min-width: 0;
        }

        .event-topline {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }

        .event-name {
            min-width: 0;
            margin: 0;
            color: #111827;
            font-size: 16px;
            font-weight: 900;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .event-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            flex-shrink: 0;
            padding: 5px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 900;
        }

        .event-status::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 999px;
            background: currentColor;
        }

        .status-active {
            color: #15803D;
            background: #DCFCE7;
        }

        .status-upcoming {
            color: #C2410C;
            background: #FFEDD5;
        }

        .status-draft {
            color: #475569;
            background: #E2E8F0;
        }

        .status-completed {
            color: #1D4ED8;
            background: #DBEAFE;
        }

        .status-cancelled {
            color: #B91C1C;
            background: #FEE2E2;
        }

        .event-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px 14px;
            margin-top: 7px;
            color: #64748B;
            font-size: 11px;
            font-weight: 650;
        }

        .event-meta-item {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .event-meta-item svg {
            width: 14px;
            height: 14px;
        }

        .event-description {
            margin-top: 7px;
            color: #475569;
            font-size: 11px;
            line-height: 1.45;
            font-weight: 600;
        }

        .event-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin-top: 9px;
        }

        .event-stat {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 8px;
            border: 1px solid #E5E7EB;
            border-radius: 9px;
            color: #475569;
            background: #F8FAFC;
            font-size: 10px;
            font-weight: 800;
        }

        .event-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            min-width: 126px;
            padding-right: 2px;
        }

        .event-open {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 13px;
            border-radius: 11px;
            color: #FFFFFF;
            background: #213B73;
            font-size: 11px;
            font-weight: 850;
            text-decoration: none;
            white-space: nowrap;
        }

        .event-menu {
            display: inline-flex;
            width: 38px;
            height: 38px;
            align-items: center;
            justify-content: center;
            border: 1px solid #E5E7EB;
            border-radius: 11px;
            color: #475569;
            background: #FFFFFF;
            text-decoration: none;
        }

        .event-menu svg {
            width: 18px;
            height: 18px;
        }

        .events-tab:hover,
        .events-create:hover,
        .event-open:hover,
        .event-menu:hover {
            transform: translateY(-1px);
        }

        .events-create,
        .event-open,
        .event-menu,
        .events-tab {
            transition: .18s ease;
        }

        .event-menu:hover {
            color: #213B73;
            border-color: #B8C5DD;
            background: #F8FAFC;
        }

        .events-empty {
            padding: 46px 24px;
            border: 1px dashed #CBD5E1;
            border-radius: 18px;
            color: #64748B;
            background: #FFFFFF;
            text-align: center;
        }

        .events-empty-title {
            color: #111827;
            font-size: 15px;
            font-weight: 900;
        }

        .events-empty-text {
            margin-top: 5px;
            font-size: 12px;
            font-weight: 650;
        }

        .events-pagination {
            margin-top: 18px;
        }

        @media (max-width: 1100px) {
            .events-toolbar {
                grid-template-columns: minmax(0, 1fr) 240px;
            }

            .events-tabs {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 760px) {
            .events-page {
                padding: 4px 0 18px;
            }

            .events-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .events-toolbar {
                grid-template-columns: 1fr;
            }

            .events-tabs {
                grid-column: auto;
            }

            .events-filter-group {
                width: 100%;
            }

            .event-card {
                grid-template-columns: 84px minmax(0, 1fr);
                gap: 12px;
                padding: 12px;
            }

            .event-date-box {
                min-height: 84px;
            }

            .event-actions {
                grid-column: 1 / -1;
                justify-content: flex-end;
                padding-top: 2px;
            }
        }
    </style>

    <div class="events-page">
        <div class="events-shell">
            <div class="events-header">
                <div>
                    <h1 class="events-title">Events</h1>
                    <div class="events-subtitle">
                        Manage active, upcoming, draft and completed social events.
                    </div>
                </div>

                @if (\App\Filament\Resources\EventResource::canCreate())
                    <a href="{{ \App\Filament\Resources\EventResource::getUrl('create') }}" class="events-create">
                        <x-heroicon-o-plus />
                        Create New Event
                    </a>
                @endif
            </div>

            <div class="events-toolbar">
                <div class="events-tabs">
                    @foreach ([
                        'all' => 'All Events',
                        'active' => 'Active',
                        'upcoming' => 'Upcoming',
                        'draft' => 'Draft',
                        'completed' => 'Completed',
                    ] as $filter => $label)
                        <button
                            type="button"
                            wire:click="setStatusFilter('{{ $filter }}')"
                            class="events-tab {{ $statusFilter === $filter ? 'events-tab-active' : '' }}"
                        >
                            {{ $label }}
                            <span class="events-tab-count">
                                {{ number_format($statusCounts[$filter] ?? 0) }}
                            </span>
                        </button>
                    @endforeach
                </div>

                <input
                    type="search"
                    wire:model.live.debounce.400ms="search"
                    class="events-input"
                    placeholder="Search event or venue..."
                >

                <div class="events-filter-group">
                    <select wire:model.live="typeFilter" class="events-select">
                        <option value="all">All Event Types</option>

                        @foreach ($eventTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    @if ($hasActiveFilters ?? false)
                        <button type="button" wire:click="clearFilters" class="events-clear">
                            Clear
                        </button>
                    @endif
                </div>
            </div>

            <div class="events-list">
                @forelse ($events as $event)
                    @php
                        $eventDate = $event->event_date
                            ? \Illuminate\Support\Carbon::parse($event->event_date)
                            : null;

                        $eventTime = $event->start_time
                            ? \Illuminate\Support\Carbon::parse($event->start_time)->format('h:i A')
                            : 'Time not set';

                        $isToday = $eventDate?->isToday() ?? false;
                        $isTomorrow = $eventDate?->isTomorrow() ?? false;
                        $isFuture = $eventDate?->isFuture() ?? false;
                        $isActive = $event->status === \App\Models\Event::STATUS_ACTIVE;

                        $dateBoxClass = $isActive
                            ? 'event-date-box-active'
                            : ($isFuture ? 'event-date-box-upcoming' : '');

                        $status = strtolower($event->status ?? 'draft');

                        $dateLabel = match (true) {
                            $status === 'completed' => 'Completed',
                            in_array($status, ['cancelled', 'canceled'], true) => 'Cancelled',
                            $isToday => 'Today',
                            $isTomorrow => 'Tomorrow',
                            $isFuture && $eventDate !== null =>
                                'In '.now()->startOfDay()->diffInDays($eventDate->copy()->startOfDay()).' days',
                            default => 'Past Event',
                        };

                        $statusClass = match ($status) {
                            'active' => 'status-active',
                            'completed' => 'status-completed',
                            'cancelled', 'canceled' => 'status-cancelled',
                            'draft' => 'status-draft',
                            default => $isFuture ? 'status-upcoming' : 'status-draft',
                        };

                        $responded = (int) ($event->rsvp_attending_count ?? 0)
                            + (int) ($event->rsvp_not_attending_count ?? 0);

                        $responseRate = $event->invitees_count > 0
                            ? round(($responded / $event->invitees_count) * 100)
                            : 0;
                    @endphp

                    <article class="event-card">
                        <div class="event-date-box {{ $dateBoxClass }}">
                            <div class="event-date-label">
                                {{ $dateLabel }}
                            </div>
                            <div class="event-date-day">{{ $eventDate?->format('d') ?? '--' }}</div>
                            <div class="event-date-month">{{ $eventDate?->format('M') ?? 'Date' }}</div>
                            <div class="event-date-time">{{ $eventTime }}</div>
                        </div>

                        <div class="event-content">
                            <div class="event-topline">
                                <h2 class="event-name">
                                    {{ $event->title ?? $event->name ?? 'Untitled Event' }}
                                </h2>

                                <span class="event-status {{ $statusClass }}">
                                    {{ str($status)->replace('_', ' ')->title() }}
                                </span>
                            </div>

                            <div class="event-meta">
                                <span class="event-meta-item">
                                    <x-heroicon-o-map-pin />
                                    {{ $event->venue_name ?? $event->venue ?? 'Venue not set' }}
                                </span>

                                <span class="event-meta-item">
                                    <x-heroicon-o-tag />
                                    {{ \App\Models\Event::eventTypes()[$event->event_type] ?? str($event->event_type ?? 'social_event')->replace('_', ' ')->title() }}
                                </span>

                                @if ($event->user)
                                    <span class="event-meta-item">
                                        <x-heroicon-o-user-circle />
                                        {{ $event->user->name }}
                                    </span>
                                @endif
                            </div>

                            @if (filled($event->description ?? null))
                                <div class="event-description">
                                    {{ \Illuminate\Support\Str::limit(strip_tags($event->description), 145) }}
                                </div>
                            @endif

                            <div class="event-stats">
                                <span class="event-stat">
                                    {{ number_format($event->invitees_count) }} invitees
                                </span>

                                <span class="event-stat">
                                    {{ number_format($event->rsvp_attending_count) }} attending
                                </span>

                                <span class="event-stat">
                                    {{ $responseRate }}% RSVP
                                </span>

                                <span class="event-stat">
                                    {{ number_format($event->generated_cards_ready_count) }} cards ready
                                </span>

                                <span class="event-stat">
                                    {{ number_format($event->check_ins_count) }} check-ins
                                </span>
                            </div>
                        </div>

                        <div class="event-actions">
                            <a
                                href="{{ \App\Filament\Resources\EventResource::getUrl('view', ['record' => $event]) }}"
                                class="event-open"
                            >
                                Open Event
                            </a>

                            @if (\App\Filament\Resources\EventResource::canEdit($event))
                                <a
                                    href="{{ \App\Filament\Resources\EventResource::getUrl('edit', ['record' => $event]) }}"
                                    class="event-menu"
                                    title="Edit event"
                                >
                                    <x-heroicon-o-pencil-square />
                                </a>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="events-empty">
                        <div class="events-empty-title">No events found</div>
                        <div class="events-empty-text">
                            Try changing the filter or create a new social event.
                        </div>
                    </div>
                @endforelse
            </div>

            @if ($events->hasPages())
                <div class="events-pagination">
                    {{ $events->links() }}
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
