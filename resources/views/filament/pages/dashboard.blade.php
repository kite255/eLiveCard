<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Top Welcome Section --}}
        <div class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-16 w-16 items-center justify-center rounded-full text-xl font-extrabold text-white"
                            style="background: #213B73;"
                        >
                            EA
                        </div>

                        <div>
                            <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white">
                                Welcome
                            </h2>

                            <p class="mt-1 text-lg text-gray-500 dark:text-gray-400">
                                {{ $this->userName }}
                            </p>
                        </div>
                    </div>

                    <form method="POST" action="{{ filament()->getLogoutUrl() }}">
                        @csrf

                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-2xl border border-gray-300 bg-white px-5 py-3 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                        >
                            <x-heroicon-o-arrow-left-on-rectangle class="mr-2 h-5 w-5" />
                            Sign out
                        </button>
                    </form>
                </div>
            </div>

            <div
                class="relative overflow-hidden rounded-3xl p-6 shadow-sm"
                style="background: linear-gradient(135deg, #213B73 0%, #172A52 100%); color: #FFFFFF;"
            >
                <div
                    class="absolute -right-10 -top-10 h-40 w-40 rounded-full blur-3xl"
                    style="background: rgba(253,150,24,0.25);"
                ></div>

                <div class="relative">
                    <p class="text-sm font-semibold uppercase tracking-wide" style="color: rgba(255,255,255,0.75);">
                        eLive Card
                    </p>

                    <h1 class="mt-2 text-3xl font-extrabold">
                        Event Management Dashboard
                    </h1>

                    <p class="mt-3 max-w-xl text-sm leading-6" style="color: rgba(255,255,255,0.80);">
                        Monitor events, invitation SMS, RSVP reminders, guest check-ins, and event-day activity from one place.
                    </p>
                </div>
            </div>
        </div>

        {{-- Main Summary Cards --}}
        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            <x-dashboard-card
                title="Total Events"
                value="{{ number_format($stats['events'] ?? 0) }}"
                description="Created social events"
                color="#213B73"
                icon="calendar"
            />

            <x-dashboard-card
                title="Total Invitees"
                value="{{ number_format($stats['invitees'] ?? 0) }}"
                description="All invited guests"
                color="#FD9618"
                icon="users"
            />

            <x-dashboard-card
                title="Checked In"
                value="{{ number_format($stats['checked_in'] ?? 0) }}"
                description="Invitees already checked in"
                color="#22C55E"
                icon="check"
            />
        </div>

        {{-- SMS Cards --}}
        <div>
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-extrabold text-gray-900 dark:text-white">
                        SMS Overview
                    </h2>

                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Invitation, reminders, delivery, and failed SMS summary.
                    </p>
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                <x-dashboard-card
                    title="Total SMS Logs"
                    value="{{ number_format($stats['total_sms'] ?? 0) }}"
                    description="All SMS attempts"
                    color="#FD9618"
                    icon="chat"
                />

                <x-dashboard-card
                    title="Sent SMS"
                    value="{{ number_format($stats['sent_sms'] ?? 0) }}"
                    description="Successfully submitted"
                    color="#22C55E"
                    icon="success"
                />

                <x-dashboard-card
                    title="Failed SMS"
                    value="{{ number_format($stats['failed_sms'] ?? 0) }}"
                    description="Needs attention"
                    color="#EF4444"
                    icon="warning"
                />

                <x-dashboard-card
                    title="Pending SMS"
                    value="{{ number_format($stats['pending_sms'] ?? 0) }}"
                    description="Waiting for provider response"
                    color="#F59E0B"
                    icon="clock"
                />

                <x-dashboard-card
                    title="Invitation SMS"
                    value="{{ number_format($stats['invitation_sms'] ?? 0) }}"
                    description="First invitation messages"
                    color="#3B82F6"
                    icon="mail"
                />

                <x-dashboard-card
                    title="RSVP Reminders"
                    value="{{ number_format($stats['rsvp_reminders'] ?? 0) }}"
                    description="Pending RSVP reminders"
                    color="#FD9618"
                    icon="bell"
                />

                <x-dashboard-card
                    title="One Day Before"
                    value="{{ number_format($stats['one_day_before'] ?? 0) }}"
                    description="Attending invitees reminders"
                    color="#3B82F6"
                    icon="calendar"
                />

                <x-dashboard-card
                    title="Event Day SMS"
                    value="{{ number_format($stats['event_day_sms'] ?? 0) }}"
                    description="Final event reminders"
                    color="#22C55E"
                    icon="qr"
                />

                <x-dashboard-card
                    title="RSVP Pending"
                    value="{{ number_format($stats['rsvp_pending'] ?? 0) }}"
                    description="Invitees yet to confirm"
                    color="#EF4444"
                    icon="warning"
                />
            </div>
        </div>
    </div>

    {{-- Inline card component --}}
    @once
        @push('styles')
            <style>
                .elive-dashboard-card {
                    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
                }

                .elive-dashboard-card:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.14);
                }
            </style>
        @endpush
    @endonce

    @php
        /**
         * This component-like macro works only visually here because Blade anonymous component
         * files are cleaner. If your Laravel complains about x-dashboard-card, create the component below.
         */
    @endphp
</x-filament-panels::page>