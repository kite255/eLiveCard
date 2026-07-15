<x-filament-panels::page>
    @php
        $event = $this->record;
        $eventImageUrl = $this->getEventImageUrl();
        $stats = $this->getWorkspaceStatCards();
        $quickActions = $this->getWorkspaceQuickActions();
        $digitalPageSettings = $this->getInviteeDigitalPageSettings();
    @endphp

    <style>
        .elive-workspace {
            --elive-blue: #213B73;
            --elive-orange: #FD9618;
            --elive-text: #111827;
            --elive-muted: #64748B;
            --elive-border: #E5E7EB;
            --elive-soft: #F8FAFC;
        }

        .elive-card {
            border: 1px solid var(--elive-border);
            border-radius: 18px;
            background: #FFFFFF;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
        }

        .elive-event-summary {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 24px;
            padding: 18px;
        }

        .elive-event-main {
            display: flex;
            min-width: 0;
            gap: 18px;
        }

        .elive-event-image {
            width: 150px;
            height: 118px;
            flex-shrink: 0;
            overflow: hidden;
            border-radius: 14px;
            background: #EEF2F7;
        }

        .elive-event-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .elive-event-image-placeholder {
            width: 100%;
            height: 100%;
            display: grid;
            place-items: center;
            color: #94A3B8;
        }

        .elive-event-image-placeholder svg {
            width: 38px;
            height: 38px;
        }

        .elive-event-copy {
            min-width: 0;
            padding: 4px 0;
        }

        .elive-event-heading {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .elive-event-name {
            margin: 0;
            color: var(--elive-text);
            font-size: 22px;
            font-weight: 900;
            line-height: 1.25;
        }

        .elive-badge {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 900;
        }

        .elive-badge-type {
            color: #1D4ED8;
            background: #DBEAFE;
        }

        .elive-badge-status {
            color: #475569;
            background: #F1F5F9;
        }

        .elive-status-success {
            color: #15803D;
            background: #DCFCE7;
        }

        .elive-status-info {
            color: #0369A1;
            background: #E0F2FE;
        }

        .elive-status-danger {
            color: #B91C1C;
            background: #FEE2E2;
        }

        .elive-status-warning {
            color: #C2410C;
            background: #FFEDD5;
        }

        .elive-status-gray {
            color: #475569;
            background: #F1F5F9;
        }

        .elive-event-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 20px;
            margin-top: 15px;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
        }

        .elive-event-meta-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .elive-event-meta-item svg {
            width: 16px;
            height: 16px;
            color: #64748B;
        }

        .elive-event-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(140px, 1fr));
            align-content: center;
            gap: 10px;
            padding-left: 22px;
            border-left: 1px solid #E5E7EB;
        }

        .elive-action-button {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 14px;
            border: 1px solid #DDE4EE;
            border-radius: 9px;
            color: var(--elive-text);
            background: #FFFFFF;
            font-size: 11px;
            font-weight: 900;
            text-decoration: none;
            transition: .18s ease;
        }

        .elive-action-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 7px 16px rgba(15, 23, 42, .08);
        }

        .elive-action-button svg {
            width: 16px;
            height: 16px;
        }

        .elive-action-primary {
            color: #FFFFFF;
            border-color: var(--elive-blue);
            background: var(--elive-blue);
        }

        .elive-action-success {
            color: #15803D;
            border-color: #86EFAC;
        }

        .elive-action-warning {
            color: #EA580C;
            border-color: #FDBA74;
        }

        .elive-stats-grid {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 12px;
        }

        .elive-stat {
            display: flex;
            align-items: center;
            gap: 13px;
            min-width: 0;
            padding: 16px;
        }

        .elive-stat-icon,
        .elive-quick-icon {
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 999px;
        }

        .elive-stat-icon {
            width: 46px;
            height: 46px;
        }

        .elive-stat-icon svg {
            width: 23px;
            height: 23px;
        }

        .elive-tone-blue { color: #2563EB; background: #E8F0FF; }
        .elive-tone-green { color: #16A34A; background: #E8F8ED; }
        .elive-tone-amber { color: #F59E0B; background: #FFF3D6; }
        .elive-tone-purple { color: #7C3AED; background: #F0E8FF; }
        .elive-tone-sky { color: #0284C7; background: #E5F5FF; }
        .elive-tone-orange { color: #EA580C; background: #FFF0E4; }
        .elive-tone-red { color: #DC2626; background: #FEE2E2; }

        .elive-stat-copy {
            min-width: 0;
        }

        .elive-stat-label {
            color: #475569;
            font-size: 10px;
            font-weight: 800;
        }

        .elive-stat-value {
            margin-top: 2px;
            color: var(--elive-text);
            font-size: 22px;
            font-weight: 900;
            line-height: 1;
        }

        .elive-stat-description {
            margin-top: 5px;
            color: #64748B;
            font-size: 10px;
            font-weight: 700;
        }

        .elive-quick-wrap {
            overflow: hidden;
        }

        .elive-section-header {
            padding: 15px 17px 10px;
        }

        .elive-section-title {
            margin: 0;
            color: var(--elive-text);
            font-size: 14px;
            font-weight: 900;
        }

        .elive-section-description {
            margin-top: 3px;
            color: var(--elive-muted);
            font-size: 10px;
            font-weight: 600;
        }

        .elive-quick-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 9px;
            padding: 0 14px 14px;
        }

        .elive-quick-action {
            min-width: 0;
            min-height: 72px;
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 11px;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            color: inherit;
            background: #FFFFFF;
            text-decoration: none;
            transition: .18s ease;
        }

        .elive-quick-action:hover {
            transform: translateY(-1px);
            border-color: #CBD5E1;
            box-shadow: 0 8px 18px rgba(15, 23, 42, .06);
        }

        .elive-quick-icon {
            width: 38px;
            height: 38px;
        }

        .elive-quick-icon svg {
            width: 19px;
            height: 19px;
        }

        .elive-quick-copy {
            min-width: 0;
        }

        .elive-quick-title {
            color: var(--elive-text);
            font-size: 11px;
            font-weight: 900;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .elive-quick-description {
            margin-top: 3px;
            color: var(--elive-muted);
            font-size: 9px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .elive-quick-hint {
            display: inline-flex;
            margin-top: 5px;
            padding: 3px 6px;
            border-radius: 7px;
            color: #475569;
            background: #F1F5F9;
            font-size: 8px;
            font-weight: 900;
            line-height: 1;
        }

        .elive-bottom-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1.15fr;
            gap: 12px;
        }

        .elive-info-card {
            padding: 16px;
        }

        .elive-info-heading {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--elive-text);
            font-size: 12px;
            font-weight: 900;
        }

        .elive-info-heading svg {
            width: 17px;
            height: 17px;
            color: var(--elive-blue);
        }

        .elive-info-list {
            display: grid;
            gap: 12px;
            margin-top: 16px;
        }

        .elive-info-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            font-size: 10px;
        }

        .elive-info-label {
            color: var(--elive-muted);
        }

        .elive-info-value {
            color: var(--elive-text);
            font-weight: 800;
            text-align: right;
        }

        .elive-enabled {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #15803D;
            font-size: 10px;
            font-weight: 800;
        }

        .elive-enabled-dot,
        .elive-disabled-dot {
            width: 7px;
            height: 7px;
            border-radius: 999px;
        }

        .elive-enabled-dot {
            background: #22C55E;
        }

        .elive-disabled {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #64748B;
            font-size: 10px;
            font-weight: 800;
        }

        .elive-disabled-dot {
            background: #94A3B8;
        }

        .elive-relations {
            min-width: 0;
        }

        @media (max-width: 1450px) {
            .elive-stats-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .elive-quick-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 1050px) {
            .elive-event-summary {
                grid-template-columns: 1fr;
            }

            .elive-event-actions {
                grid-template-columns: repeat(4, minmax(0, 1fr));
                padding: 16px 0 0;
                border-top: 1px solid #E5E7EB;
                border-left: 0;
            }

            .elive-bottom-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .elive-relations {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 760px) {
            .elive-event-main {
                flex-direction: column;
            }

            .elive-event-image {
                width: 100%;
                height: 190px;
            }

            .elive-event-actions,
            .elive-stats-grid,
            .elive-quick-grid,
            .elive-bottom-grid {
                grid-template-columns: 1fr;
            }

            .elive-event-actions {
                gap: 8px;
            }
        }

        .dark .elive-card,
        .dark .elive-quick-action {
            border-color: #374151;
            background: #111827;
        }

        .dark .elive-event-name,
        .dark .elive-stat-value,
        .dark .elive-section-title,
        .dark .elive-quick-title,
        .dark .elive-info-heading,
        .dark .elive-info-value {
            color: #FFFFFF;
        }

        .dark .elive-event-actions {
            border-color: #374151;
        }

        .dark .elive-action-button {
            border-color: #4B5563;
            color: #E5E7EB;
            background: #111827;
        }

        .dark .elive-action-primary {
            color: #FFFFFF;
            border-color: #31589F;
            background: #213B73;
        }

        .dark .elive-quick-hint {
            color: #CBD5E1;
            background: #1F2937;
        }
    </style>

    <div class="elive-workspace space-y-4">
        <section class="elive-card elive-event-summary">
            <div class="elive-event-main">
                <div class="elive-event-image">
                    @if ($eventImageUrl)
                        <img
                            src="{{ $eventImageUrl }}"
                            alt="{{ $this->getEventName() }}"
                        >
                    @else
                        <div class="elive-event-image-placeholder">
                            @svg('heroicon-o-photo')
                        </div>
                    @endif
                </div>

                <div class="elive-event-copy">
                    <div class="elive-event-heading">
                        <h2 class="elive-event-name">
                            {{ $this->getEventName() }}
                        </h2>

                        <span class="elive-badge elive-badge-status elive-status-{{ $this->getStatusColor() }}">
                            {{ $this->getStatusLabel() }}
                        </span>
                    </div>

                    <div class="mt-2">
                        <span class="elive-badge elive-badge-type">
                            {{ $this->getEventTypeLabel() }}
                        </span>
                    </div>

                    <div class="elive-event-meta">
                        <span class="elive-event-meta-item">
                            @svg('heroicon-o-calendar-days')
                            {{ $this->getFormattedEventDate() }}
                        </span>

                        <span class="elive-event-meta-item">
                            @svg('heroicon-o-clock')
                            {{ $this->getFormattedEventTime() }}
                        </span>

                        <span class="elive-event-meta-item">
                            @svg('heroicon-o-map-pin')
                            {{ $this->getVenueName() }}
                        </span>

                        <span class="elive-event-meta-item">
                            @svg('heroicon-o-user')
                            Event Owner: {{ $this->getEventOwnerName() }}
                        </span>

                        <span class="elive-event-meta-item">
                            @svg('heroicon-o-phone')
                            {{ $this->getOrganizerPhone() }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="elive-event-actions">
                @if ($this->canEditEvent())
                    <a
                        href="{{ $this->getEditEventUrl() }}"
                        class="elive-action-button"
                    >
                        @svg('heroicon-o-pencil-square')
                        Edit Event
                    </a>
                @endif

                @if ($this->canSendEventMessages())
                    <a
                        href="{{ $this->getMessageCenterUrl() }}"
                        class="elive-action-button elive-action-primary"
                    >
                        @svg('heroicon-o-envelope')
                        Message Center
                    </a>
                @endif

                <a
                    href="{{ $this->getInviteeResponsesUrl() }}"
                    class="elive-action-button elive-action-success"
                >
                    @svg('heroicon-o-chat-bubble-left-right')
                    RSVP Responses
                </a>

                <a
                    href="{{ $this->getGateCheckInUrl() }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="elive-action-button elive-action-warning"
                >
                    @svg('heroicon-o-qr-code')
                    Gate Check-in
                </a>
            </div>
        </section>

        <section class="elive-stats-grid">
            @foreach ($stats as $stat)
                <article class="elive-card elive-stat">
                    <span class="elive-stat-icon elive-tone-{{ $stat['tone'] }}">
                        @svg($stat['icon'])
                    </span>

                    <div class="elive-stat-copy">
                        <div class="elive-stat-label">
                            {{ $stat['label'] }}
                        </div>

                        <div class="elive-stat-value">
                            {{ $stat['value'] }}
                        </div>

                        <div class="elive-stat-description">
                            {{ $stat['description'] }}
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="elive-card elive-quick-wrap">
            <div class="elive-section-header">
                <h3 class="elive-section-title">Quick Actions</h3>
                <p class="elive-section-description">
                    Manage the complete event workflow from one place.
                </p>
            </div>

            <div class="elive-quick-grid">
                @foreach ($quickActions as $action)
                    <a
                        href="{{ $action['url'] }}"
                        @if (($action['new_tab'] ?? false) === true)
                            target="_blank"
                            rel="noopener noreferrer"
                        @endif
                        class="elive-quick-action"
                    >
                        <span class="elive-quick-icon elive-tone-{{ $action['tone'] }}">
                            @svg($action['icon'])
                        </span>

                        <span class="elive-quick-copy">
                            <span class="elive-quick-title">
                                {{ $action['title'] }}
                            </span>

                            <span class="elive-quick-description">
                                {{ $action['description'] }}
                            </span>

                            @if (filled($action['hint'] ?? null))
                                <span class="elive-quick-hint">
                                    {{ $action['hint'] }}
                                </span>
                            @endif
                        </span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="elive-bottom-grid">
            <article class="elive-card elive-info-card">
                <div class="elive-info-heading">
                    @svg('heroicon-o-information-circle')
                    Event Profile
                </div>

                <div class="elive-info-list">
                    <div class="elive-info-row">
                        <span class="elive-info-label">Event Type</span>
                        <span class="elive-info-value">
                            {{ $this->getEventTypeLabel() }}
                        </span>
                    </div>

                    <div class="elive-info-row">
                        <span class="elive-info-label">Event Date</span>
                        <span class="elive-info-value">
                            {{ $this->getFormattedEventDate() }}
                        </span>
                    </div>

                    <div class="elive-info-row">
                        <span class="elive-info-label">Event Time</span>
                        <span class="elive-info-value">
                            {{ $this->getFormattedEventTime() }}
                        </span>
                    </div>

                    <div class="elive-info-row">
                        <span class="elive-info-label">Status</span>
                        <span class="elive-badge elive-badge-status elive-status-{{ $this->getStatusColor() }}">
                            {{ $this->getStatusLabel() }}
                        </span>
                    </div>
                </div>
            </article>

            <article class="elive-card elive-info-card">
                <div class="elive-info-heading">
                    @svg('heroicon-o-map-pin')
                    Venue & Organizer
                </div>

                <div class="elive-info-list">
                    <div class="elive-info-row">
                        <span class="elive-info-label">Venue Name</span>
                        <span class="elive-info-value">
                            {{ $this->getVenueName() }}
                        </span>
                    </div>

                    <div class="elive-info-row">
                        <span class="elive-info-label">Address</span>
                        <span class="elive-info-value">
                            {{ $this->getVenueAddress() }}
                        </span>
                    </div>

                    <div class="elive-info-row">
                        <span class="elive-info-label">Dress Code</span>
                        <span class="elive-info-value">
                            {{ $this->getDressCode() }}
                        </span>
                    </div>

                    <div class="elive-info-row">
                        <span class="elive-info-label">Organizer Phone</span>
                        <span class="elive-info-value">
                            {{ $this->getOrganizerPhone() }}
                        </span>
                    </div>

                    @if ($this->getLocationUrl())
                        <div class="elive-info-row">
                            <span class="elive-info-label">Google Maps</span>
                            <a
                                href="{{ $this->getLocationUrl() }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="font-bold text-primary-600"
                            >
                                Open Map
                            </a>
                        </div>
                    @endif
                </div>
            </article>

            <article class="elive-card elive-info-card">
                <div class="elive-info-heading">
                    @svg('heroicon-o-globe-alt')
                    Invitee Digital Page
                </div>

                <div class="elive-info-list">
                    @foreach ($digitalPageSettings as $label => $enabled)
                        <div class="elive-info-row">
                            <span class="elive-info-label">
                                {{ $label }}
                            </span>

                            <span class="{{ $enabled ? 'elive-enabled' : 'elive-disabled' }}">
                                <span class="{{ $enabled ? 'elive-enabled-dot' : 'elive-disabled-dot' }}"></span>
                                {{ $enabled ? 'Enabled' : 'Disabled' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </article>
        </section>

        {{ $this->infolist }}

        @if (count($relationManagers = $this->getRelationManagers()))
            <section class="elive-relations">
                <x-filament-panels::resources.relation-managers
                    :active-manager="$this->activeRelationManager"
                    :managers="$relationManagers"
                    :owner-record="$record"
                    :page-class="static::class"
                />
            </section>
        @endif
    </div>
</x-filament-panels::page>
