<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="inline-flex rounded-full bg-[#213B73]/10 px-3 py-1 text-xs font-semibold text-[#213B73] dark:bg-[#FD9618]/10 dark:text-[#FD9618]">
                        eLive Card Communication
                    </div>

                    <h2 class="mt-3 text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                        Reminder SMS
                    </h2>

                    <p class="mt-2 max-w-2xl text-sm text-gray-600 dark:text-gray-400">
                        Send invitation SMS, RSVP pending reminders, one-day-before reminders, and event-day reminders to selected invitees.
                    </p>
                </div>

                <div class="rounded-2xl bg-[#213B73] px-5 py-4 text-white shadow-sm">
                    <div class="text-xs font-medium opacity-80">
                        Current Module
                    </div>

                    <div class="mt-1 text-sm font-bold">
                        Communication
                    </div>
                </div>
            </div>
        </div>

        @if ($missingTrackingColumns)
            <div class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                <strong>SMS tracking columns are missing.</strong>
                Run the missing invitee SMS tracking migration before sending reminders.
            </div>
        @endif

        <form wire:submit.prevent="send" class="space-y-6">
            {{ $this->form }}

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                <x-filament::button
                    type="button"
                    color="gray"
                    wire:click="updatePreview"
                    icon="heroicon-o-arrow-path"
                >
                    Refresh Preview
                </x-filament::button>

                <x-filament::button
                    type="submit"
                    color="warning"
                    icon="heroicon-o-paper-airplane"
                    wire:confirm="Are you sure you want to send SMS to the selected invitees?"
                    :disabled="$missingTrackingColumns"
                >
                    Send Reminder SMS
                </x-filament::button>
            </div>
        </form>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#213B73]/10 text-[#213B73] dark:bg-[#213B73]/30 dark:text-blue-300">
                    <x-heroicon-o-envelope class="h-5 w-5" />
                </div>

                <div class="mt-4 text-sm font-semibold text-gray-950 dark:text-white">
                    Invitation SMS
                </div>

                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    First SMS sent when inviting guests.
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#FD9618]/10 text-[#FD9618] dark:bg-[#FD9618]/20">
                    <x-heroicon-o-bell-alert class="h-5 w-5" />
                </div>

                <div class="mt-4 text-sm font-semibold text-gray-950 dark:text-white">
                    RSVP Reminder
                </div>

                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Sent only to invitees who have not confirmed.
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#213B73]/10 text-[#213B73] dark:bg-[#213B73]/30 dark:text-blue-300">
                    <x-heroicon-o-calendar-days class="h-5 w-5" />
                </div>

                <div class="mt-4 text-sm font-semibold text-gray-950 dark:text-white">
                    One Day Before
                </div>

                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Sent to confirmed attending invitees.
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#FD9618]/10 text-[#FD9618] dark:bg-[#FD9618]/20">
                    <x-heroicon-o-qr-code class="h-5 w-5" />
                </div>

                <div class="mt-4 text-sm font-semibold text-gray-950 dark:text-white">
                    Event Day
                </div>

                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Final reminder with venue and serial number.
                </p>
            </div>
        </div>
    </div>
</x-filament-panels::page>