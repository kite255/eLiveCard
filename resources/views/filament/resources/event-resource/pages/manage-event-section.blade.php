<x-filament-panels::page>
    @php
        $relationManagers = $this->getRelationManagers();
    @endphp

    <style>
        .elive-section-page {
            --elive-blue: #213B73;
            --elive-orange: #FD9618;
            --elive-text: #111827;
            --elive-muted: #64748B;
            --elive-border: #E5E7EB;
            width: 100%;
            min-width: 0;
        }

        .elive-section-page,
        .elive-section-page *,
        .elive-section-page *::before,
        .elive-section-page *::after {
            box-sizing: border-box;
        }

        .elive-section-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 16px;
            padding: 14px 16px;
            border: 1px solid var(--elive-border);
            border-radius: 14px;
            background: #FFFFFF;
            box-shadow: 0 6px 18px rgba(15, 23, 42, .04);
        }

        .elive-section-event {
            min-width: 0;
        }

        .elive-section-event-label {
            color: var(--elive-muted);
            font-size: 10px;
            font-weight: 700;
        }

        .elive-section-event-name {
            margin-top: 3px;
            color: var(--elive-text);
            font-size: 14px;
            font-weight: 900;
            overflow-wrap: anywhere;
        }

        .elive-workspace-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            gap: 7px;
            min-height: 40px;
            padding: 9px 14px;
            border: 1px solid var(--elive-blue);
            border-radius: 9px;
            color: #FFFFFF;
            background: var(--elive-blue);
            font-size: 11px;
            font-weight: 900;
            text-decoration: none;
        }

        .elive-workspace-button svg {
            width: 16px;
            height: 16px;
        }

        .elive-relation-container {
            min-width: 0;
            padding: 16px;
            border: 1px solid var(--elive-border);
            border-radius: 16px;
            background: #FFFFFF;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
            overflow-x: auto;
        }

        @media (max-width: 640px) {
            .elive-section-toolbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .elive-workspace-button {
                width: 100%;
            }

            .elive-relation-container {
                padding: 10px;
            }
        }

        .dark .elive-section-toolbar,
        .dark .elive-relation-container {
            border-color: #374151;
            background: #111827;
        }

        .dark .elive-section-event-name {
            color: #FFFFFF;
        }
    </style>

    <div class="elive-section-page">
        <div class="elive-section-toolbar">
            <div class="elive-section-event">
                <div class="elive-section-event-label">Event Workspace</div>
                <div class="elive-section-event-name">
                    {{ $this->getEventName() }}
                </div>
            </div>

            <a href="{{ $this->getWorkspaceUrl() }}" class="elive-workspace-button">
                @svg('heroicon-o-arrow-left')
                Back to Event Workspace
            </a>
        </div>

        <section class="elive-relation-container">
            @if (count($relationManagers))
                <x-filament-panels::resources.relation-managers
                    :active-manager="$this->activeRelationManager"
                    :managers="$relationManagers"
                    :owner-record="$record"
                    :page-class="static::class"
                />
            @else
                <div class="p-6 text-center text-sm text-gray-500">
                    This event section is not available.
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
