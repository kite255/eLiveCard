@props([
    'title' => '',
    'value' => 0,
    'description' => '',
    'color' => '#213B73',
    'icon' => 'chart',
])

<div class="elive-dashboard-card rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-base font-semibold text-gray-500 dark:text-gray-400">
                {{ $title }}
            </p>

            <p class="mt-4 text-4xl font-extrabold text-gray-900 dark:text-white">
                {{ $value }}
            </p>

            <p class="mt-4 flex items-center text-base font-semibold" style="color: {{ $color }};">
                {{ $description }}
            </p>
        </div>

        <div
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl"
            style="background: {{ $color }}1A; color: {{ $color }};"
        >
            @switch($icon)
                @case('calendar')
                    <x-heroicon-o-calendar-days class="h-6 w-6" />
                    @break

                @case('users')
                    <x-heroicon-o-users class="h-6 w-6" />
                    @break

                @case('check')
                @case('success')
                    <x-heroicon-o-check-circle class="h-6 w-6" />
                    @break

                @case('warning')
                    <x-heroicon-o-exclamation-triangle class="h-6 w-6" />
                    @break

                @case('clock')
                    <x-heroicon-o-clock class="h-6 w-6" />
                    @break

                @case('mail')
                    <x-heroicon-o-envelope class="h-6 w-6" />
                    @break

                @case('bell')
                    <x-heroicon-o-bell-alert class="h-6 w-6" />
                    @break

                @case('qr')
                    <x-heroicon-o-qr-code class="h-6 w-6" />
                    @break

                @case('chat')
                    <x-heroicon-o-chat-bubble-left-right class="h-6 w-6" />
                    @break

                @default
                    <x-heroicon-o-chart-bar class="h-6 w-6" />
            @endswitch
        </div>
    </div>
</div>