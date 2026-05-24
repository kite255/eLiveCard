@props([
    'title',
    'value',
    'subtitle' => null,
])

<div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
        {{ $title }}
    </p>

    <p class="mt-3 text-3xl font-bold text-gray-950 dark:text-white">
        {{ $value }}
    </p>

    @if ($subtitle)
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ $subtitle }}
        </p>
    @endif
</div>