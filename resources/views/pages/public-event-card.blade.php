@php
    $isCompleted = $event->status === \App\Models\Event::STATUS_COMPLETED
        || $event->isPast();

    $isHappeningNow = ! $isCompleted && $event->isHappeningNow();

    $statusLabel = $isCompleted
        ? 'Completed'
        : ($isHappeningNow
            ? 'Happening Now'
            : ($event->event_date?->isToday() ? 'Today' : 'Upcoming'));
@endphp

<article class="flex h-full flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
    @if ($event->cover_image_url)
        <a
            href="{{ route('events.show', $event) }}"
            class="block overflow-hidden"
        >
            <img
                src="{{ $event->cover_image_url }}"
                alt="{{ $event->title }}"
                @class([
                    'h-56 w-full object-cover transition duration-300 hover:scale-[1.02]',
                    'grayscale-[20%]' => $isCompleted,
                ])
            >
        </a>
    @else
        <a
            href="{{ route('events.show', $event) }}"
            class="flex h-44 items-center justify-center bg-[#213B73]/[0.06]"
            aria-label="View {{ $event->title }}"
        >
            <svg
                class="h-14 w-14 text-[#213B73]/35"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.5"
                    d="M8 7V3m8 4V3M4 10h16M5 5h14a1 1 0 011 1v14H4V6a1 1 0 011-1z"
                />
            </svg>
        </a>
    @endif

    <div @class([
        'border-b border-slate-200 p-6',
        'bg-[#213B73]/[0.05]' => ! $isCompleted,
        'bg-slate-50' => $isCompleted,
    ])>
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-[#FD9618]">
                        {{ $event->event_type_display }}
                    </p>

                    <span @class([
                        'rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide',
                        'bg-orange-100 text-orange-700' => $isHappeningNow,
                        'bg-emerald-50 text-emerald-700' => ! $isCompleted && ! $isHappeningNow,
                        'bg-slate-200 text-slate-600' => $isCompleted,
                    ])>
                        {{ $statusLabel }}
                    </span>
                </div>

                <h3 class="mt-2 text-2xl font-black leading-tight text-[#213B73]">
                    <a
                        href="{{ route('events.show', $event) }}"
                        class="transition hover:text-[#FD9618]"
                    >
                        {{ $event->title }}
                    </a>
                </h3>
            </div>

            <div @class([
                'shrink-0 rounded-2xl px-4 py-3 text-center text-white',
                'bg-[#213B73]' => ! $isCompleted,
                'bg-slate-600' => $isCompleted,
            ])>
                <p class="text-xl font-black leading-none">
                    {{ $event->event_date?->format('d') ?? '--' }}
                </p>

                <p class="mt-1 text-[10px] font-black uppercase tracking-wide text-white/75">
                    {{ $event->event_date?->format('M') ?? 'TBA' }}
                </p>
            </div>
        </div>
    </div>

    <div class="flex flex-1 flex-col space-y-5 p-6">
        <div class="space-y-4">
            <div class="flex items-start gap-3">
                <svg
                    class="mt-0.5 h-5 w-5 shrink-0 text-[#FD9618]"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M8 7V3m8 4V3M4 10h16M5 5h14a1 1 0 011 1v14H4V6a1 1 0 011-1z"
                    />
                </svg>

                <div>
                    <p class="text-xs font-black uppercase tracking-wide text-slate-400">
                        Date & Time
                    </p>

                    <p class="mt-1 text-sm font-bold text-slate-700">
                        {{ $event->event_date?->format('d M Y') ?? 'Date to be announced' }}

                        @if ($event->start_time)
                            · {{ $event->start_time->format('h:i A') }}
                        @endif

                        @if ($event->end_time)
                            – {{ $event->end_time->format('h:i A') }}
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex items-start gap-3">
                <svg
                    class="mt-0.5 h-5 w-5 shrink-0 text-[#FD9618]"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 21s6-5.3 6-11a6 6 0 10-12 0c0 5.7 6 11 6 11z"
                    />
                    <circle cx="12" cy="10" r="2" stroke-width="2"/>
                </svg>

                <div>
                    <p class="text-xs font-black uppercase tracking-wide text-slate-400">
                        Venue
                    </p>

                    <p class="mt-1 text-sm font-bold text-slate-700">
                        {{ $event->venue_name ?: 'Venue to be announced' }}
                    </p>
                </div>
            </div>
        </div>

        @if ($event->public_summary)
            <p class="line-clamp-3 text-sm font-medium leading-7 text-slate-600">
                {{ $event->public_summary }}
            </p>
        @endif

        <a
            href="{{ route('events.show', $event) }}"
            @class([
                'btn mt-auto w-full',
                'bg-[#213B73] text-white hover:bg-[#182d59]' => ! $isCompleted,
                'border border-slate-200 bg-white text-[#213B73] hover:bg-slate-50' => $isCompleted,
            ])
        >
            View Event

            <svg
                class="h-4 w-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M13 7l5 5-5 5M6 12h12"
                />
            </svg>
        </a>
    </div>
</article>
