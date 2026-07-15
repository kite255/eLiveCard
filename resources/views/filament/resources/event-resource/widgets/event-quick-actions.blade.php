<x-filament-widgets::widget>
    <style>
        .elive-quick-actions {
            overflow: hidden;
            border: 1px solid #E5E7EB;
            border-radius: 20px;
            background: #FFFFFF;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        }

        .elive-quick-actions-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 20px 22px;
            border-bottom: 1px solid #EEF2F7;
            background:
                linear-gradient(
                    135deg,
                    rgba(33, 59, 115, 0.04),
                    rgba(253, 150, 24, 0.03)
                );
        }

        .elive-quick-actions-heading {
            min-width: 0;
        }

        .elive-quick-actions-title {
            margin: 0;
            color: #111827;
            font-size: 17px;
            font-weight: 900;
            line-height: 1.25;
        }

        .elive-quick-actions-description {
            margin-top: 5px;
            color: #64748B;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.5;
        }

        .elive-quick-actions-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            height: 30px;
            padding: 0 11px;
            border-radius: 999px;
            color: #213B73;
            background: #EEF4FF;
            font-size: 11px;
            font-weight: 900;
            flex-shrink: 0;
        }

        .elive-quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            padding: 18px;
        }

        .elive-quick-action {
            --elive-accent: #213B73;
            --elive-accent-soft: rgba(33, 59, 115, 0.10);

            position: relative;
            min-width: 0;
            min-height: 150px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 16px;
            padding: 18px;
            overflow: hidden;
            border: 1px solid #E5E7EB;
            border-radius: 17px;
            background: #FFFFFF;
            text-decoration: none;
            transition:
                transform 0.2s ease,
                border-color 0.2s ease,
                box-shadow 0.2s ease,
                background 0.2s ease;
        }

        .elive-quick-action::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            width: 90px;
            height: 90px;
            border-radius: 0 0 0 100%;
            background: var(--elive-accent-soft);
            opacity: 0.55;
            pointer-events: none;
        }

        .elive-quick-action:hover {
            transform: translateY(-3px);
            border-color: var(--elive-accent);
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.10);
        }

        .elive-quick-action-primary {
            border-color: #D7E1F3;
            background: #FCFDFF;
        }

        .elive-quick-action-attention {
            border-color: #FED7AA;
            background: #FFFCF7;
        }

        .elive-quick-action-red {
            border-color: #FECACA;
            background: #FFFBFB;
        }

        .elive-quick-action-main {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: flex-start;
            gap: 13px;
        }

        .elive-quick-action-icon {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 14px;
            color: var(--elive-accent);
            background: var(--elive-accent-soft);
        }

        .elive-quick-action-icon svg {
            width: 24px;
            height: 24px;
        }

        .elive-quick-action-content {
            min-width: 0;
            flex: 1;
        }

        .elive-quick-action-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
        }

        .elive-quick-action-title {
            min-width: 0;
            color: #111827;
            font-size: 14px;
            font-weight: 900;
            line-height: 1.35;
        }

        .elive-quick-action-arrow {
            position: relative;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            flex-shrink: 0;
            border-radius: 999px;
            color: var(--elive-accent);
            background: var(--elive-accent-soft);
            font-size: 20px;
            font-weight: 900;
            line-height: 1;
            transition:
                transform 0.2s ease,
                background 0.2s ease;
        }

        .elive-quick-action:hover .elive-quick-action-arrow {
            transform: translateX(2px);
        }

        .elive-quick-action-description {
            display: block;
            margin-top: 6px;
            color: #64748B;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.55;
        }

        .elive-quick-action-footer {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .elive-quick-action-hint {
            display: inline-flex;
            align-items: center;
            min-height: 25px;
            padding: 5px 9px;
            border-radius: 9px;
            color: #475569;
            background: #F1F5F9;
            font-size: 10px;
            font-weight: 900;
            line-height: 1;
        }

        .elive-quick-action-attention .elive-quick-action-hint {
            color: #C2410C;
            background: #FFEDD5;
        }

        .elive-quick-action-red .elive-quick-action-hint {
            color: #B91C1C;
            background: #FEE2E2;
        }

        .elive-quick-action-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 25px;
            height: 25px;
            padding: 0 7px;
            border-radius: 999px;
            color: var(--elive-accent);
            background: var(--elive-accent-soft);
            font-size: 10px;
            font-weight: 900;
        }

        .elive-quick-actions-empty {
            margin: 18px;
            padding: 38px 22px;
            border: 1px dashed #CBD5E1;
            border-radius: 16px;
            color: #64748B;
            background: #F8FAFC;
            text-align: center;
            font-size: 12px;
            font-weight: 700;
        }

        .elive-quick-actions-empty-icon {
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            margin: 0 auto 12px;
            border-radius: 14px;
            color: #94A3B8;
            background: #E2E8F0;
        }

        .elive-quick-actions-empty-icon svg {
            width: 24px;
            height: 24px;
        }

        @media (max-width: 1280px) {
            .elive-quick-actions-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 760px) {
            .elive-quick-actions-header {
                align-items: flex-start;
                padding: 17px;
            }

            .elive-quick-actions-grid {
                grid-template-columns: 1fr;
                gap: 12px;
                padding: 14px;
            }

            .elive-quick-action {
                min-height: auto;
                padding: 16px;
            }
        }

        .dark .elive-quick-actions {
            border-color: #374151;
            background: #111827;
        }

        .dark .elive-quick-actions-header {
            border-color: #374151;
            background: #111827;
        }

        .dark .elive-quick-action {
            border-color: #374151;
            background: #111827;
        }

        .dark .elive-quick-action-primary {
            border-color: #334155;
            background: #111827;
        }

        .dark .elive-quick-action-attention {
            border-color: #92400E;
            background: #1F1710;
        }

        .dark .elive-quick-action-red {
            border-color: #7F1D1D;
            background: #1F1212;
        }

        .dark .elive-quick-actions-title,
        .dark .elive-quick-action-title {
            color: #FFFFFF;
        }

        .dark .elive-quick-actions-description,
        .dark .elive-quick-action-description {
            color: #9CA3AF;
        }

        .dark .elive-quick-action-hint {
            color: #CBD5E1;
            background: #1F2937;
        }

        .dark .elive-quick-actions-empty {
            border-color: #475569;
            color: #94A3B8;
            background: #111827;
        }
    </style>

    <div class="elive-quick-actions">
        <div class="elive-quick-actions-header">
            <div class="elive-quick-actions-heading">
                <h2 class="elive-quick-actions-title">
                    Event Quick Actions
                </h2>

                <div class="elive-quick-actions-description">
                    Manage invitees, invitation cards, messages, RSVP responses,
                    approvals, check-ins, and reports.
                </div>
            </div>

            <span class="elive-quick-actions-count">
                {{ count($actions ?? []) }}
            </span>
        </div>

        @if (! empty($actions))
            <div class="elive-quick-actions-grid">
                @foreach ($actions as $action)
                    @php
                        $priority = $action['priority'] ?? 'secondary';
                        $accent = $action['accent'] ?? 'blue';

                        $priorityClass = match ($priority) {
                            'primary' => 'elive-quick-action-primary',
                            'attention' => 'elive-quick-action-attention',
                            default => '',
                        };

                        $accentClass = match ($accent) {
                            'red' => 'elive-quick-action-red',
                            default => '',
                        };

                        [$accentColor, $accentSoft] = match ($accent) {
                            'orange' => [
                                '#FD9618',
                                'rgba(253, 150, 24, 0.13)',
                            ],
                            'red' => [
                                '#DC2626',
                                'rgba(220, 38, 38, 0.11)',
                            ],
                            'green' => [
                                '#16A34A',
                                'rgba(22, 163, 74, 0.11)',
                            ],
                            default => [
                                '#213B73',
                                'rgba(33, 59, 115, 0.11)',
                            ],
                        };

                        $badge = $action['badge'] ?? null;
                    @endphp

                    <a
                        href="{{ $action['url'] ?? '#' }}"
                        @if (($action['new_tab'] ?? false) === true)
                            target="_blank"
                            rel="noopener noreferrer"
                        @endif
                        class="
                            elive-quick-action
                            {{ $priorityClass }}
                            {{ $accentClass }}
                        "
                        style="
                            --elive-accent: {{ $accentColor }};
                            --elive-accent-soft: {{ $accentSoft }};
                        "
                    >
                        <span class="elive-quick-action-main">
                            <span class="elive-quick-action-icon">
                                @svg(
                                    $action['icon']
                                        ?? 'heroicon-o-squares-2x2'
                                )
                            </span>

                            <span class="elive-quick-action-content">
                                <span class="elive-quick-action-top">
                                    <span class="elive-quick-action-title">
                                        {{ $action['title'] ?? 'Action' }}
                                    </span>

                                    <span class="elive-quick-action-arrow">
                                        ›
                                    </span>
                                </span>

                                <span class="elive-quick-action-description">
                                    {{ $action['description'] ?? '' }}
                                </span>
                            </span>
                        </span>

                        <span class="elive-quick-action-footer">
                            @if (filled($action['hint'] ?? null))
                                <span class="elive-quick-action-hint">
                                    {{ $action['hint'] }}
                                </span>
                            @else
                                <span></span>
                            @endif

                            @if ($badge !== null)
                                <span class="elive-quick-action-badge">
                                    {{ number_format((int) $badge) }}
                                </span>
                            @endif
                        </span>
                    </a>
                @endforeach
            </div>
        @else
            <div class="elive-quick-actions-empty">
                <div class="elive-quick-actions-empty-icon">
                    @svg('heroicon-o-squares-2x2')
                </div>

                No event actions are available for your account.
            </div>
        @endif
    </div>
</x-filament-widgets::widget>