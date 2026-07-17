@extends('layouts.public')

@section('title', $event->title . ' | eLive Card')
@section('description', $event->public_summary ?: 'View public event details on eLive Card.')

@php
    $startsAt = null;
    $endsAt = null;

    if ($event->event_date) {
        $startsAt = $event->event_date->copy();

        if ($event->start_time) {
            $startsAt->setTime(
                $event->start_time->hour,
                $event->start_time->minute,
                $event->start_time->second
            );
        } else {
            $startsAt->startOfDay();
        }

        $endsAt = $event->event_date->copy();

        if ($event->end_time) {
            $endsAt->setTime(
                $event->end_time->hour,
                $event->end_time->minute,
                $event->end_time->second
            );
        } elseif ($event->start_time) {
            $endsAt = $startsAt->copy()->addHours(6);
        } else {
            $endsAt->endOfDay();
        }
    }

    $now = now();

    $isCompleted = $event->status === \App\Models\Event::STATUS_COMPLETED
        || ($endsAt && $now->greaterThan($endsAt));

    $isHappeningNow = ! $isCompleted
        && $startsAt
        && $endsAt
        && $now->betweenIncluded($startsAt, $endsAt);

    $statusLabel = match (true) {
        $isCompleted => 'Completed',
        $isHappeningNow => 'Happening Now',
        $event->event_date?->isToday() => 'Today',
        default => 'Upcoming',
    };

    $organizerPhone = $event->effective_organizer_phone;

    $organizerPhoneClean = $organizerPhone
        ? preg_replace('/\D+/', '', $organizerPhone)
        : null;
@endphp

@section('content')
<section class="bg-[#213B73] py-12 text-white sm:py-16">
    <div class="container-shell">
        <a
            href="{{ route('events.index') }}"
            class="inline-flex items-center gap-2 text-sm font-black text-white/75 transition hover:text-white"
        >
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
                    d="M11 19l-7-7 7-7M4 12h16"
                />
            </svg>

            Back to Events
        </a>

        <div class="mt-8 grid items-center gap-10 lg:grid-cols-[1.1fr_0.9fr]">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="rounded-full bg-[#FD9618] px-3 py-1.5 text-xs font-black uppercase tracking-[0.14em] text-white">
                        {{ $event->event_type_display }}
                    </span>

                    <span @class([
                        'rounded-full px-3 py-1.5 text-xs font-black uppercase tracking-wide',
                        'bg-orange-100 text-orange-700' => $isHappeningNow,
                        'bg-white/10 text-white' => ! $isCompleted && ! $isHappeningNow,
                        'bg-slate-200 text-slate-700' => $isCompleted,
                    ])>
                        {{ $statusLabel }}
                    </span>
                </div>

                <h1 class="mt-5 max-w-3xl text-4xl font-black tracking-tight sm:text-5xl">
                    {{ $event->title }}
                </h1>

                @if ($event->public_summary)
                    <p class="mt-5 max-w-2xl text-base font-medium leading-8 text-white/75">
                        {{ $event->public_summary }}
                    </p>
                @endif
            </div>

            @if ($event->cover_image_url)
                <div class="overflow-hidden rounded-3xl border border-white/15 bg-white/5 shadow-2xl">
                    <img
                        src="{{ $event->cover_image_url }}"
                        alt="{{ $event->title }}"
                        @class([
                            'h-72 w-full object-cover sm:h-80',
                            'grayscale-[20%]' => $isCompleted,
                        ])
                    >
                </div>
            @endif
        </div>
    </div>
</section>

<section class="bg-[#F8FAFC] py-14 sm:py-20">
    <div class="container-shell">
        <div class="grid gap-8 lg:grid-cols-[1fr_360px]">
            <div class="space-y-8">
                @if (
                    $startsAt
                    && ! $isCompleted
                    && ! $isHappeningNow
                    && $event->shouldShowCountdown()
                )
                    <section
                        id="eventCountdown"
                        class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8"
                        data-event-time="{{ $startsAt->toIso8601String() }}"
                    >
                        <p class="section-kicker">
                            Event Countdown
                        </p>

                        <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                            <h2 class="section-title text-3xl">
                                Time remaining
                            </h2>

                            <p class="text-sm font-medium text-slate-500">
                                Until {{ $startsAt->format('d M Y, h:i A') }}
                            </p>
                        </div>

                        <div class="mt-7 grid grid-cols-2 gap-4 sm:grid-cols-4">
                            @foreach ([
                                'days' => 'Days',
                                'hours' => 'Hours',
                                'minutes' => 'Minutes',
                                'seconds' => 'Seconds',
                            ] as $key => $label)
                                <div class="rounded-2xl bg-[#213B73] p-5 text-center text-white shadow-sm">
                                    <p
                                        id="countdown-{{ $key }}"
                                        class="text-3xl font-black tabular-nums"
                                    >
                                        00
                                    </p>

                                    <p class="mt-2 text-[11px] font-black uppercase tracking-[0.12em] text-white/65">
                                        {{ $label }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </section>

                @elseif ($isHappeningNow)
                    <section class="rounded-3xl border border-orange-200 bg-orange-50 p-6 shadow-sm sm:p-8">
                        <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-[#FD9618] text-white">
                                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>

                            <div>
                                <p class="section-kicker">
                                    Event Status
                                </p>

                                <h2 class="section-title mt-2 text-3xl">
                                    This event is happening now.
                                </h2>

                                <p class="mt-2 text-sm font-medium leading-7 text-slate-600">
                                    The event is currently in progress
                                    @if ($endsAt)
                                        and is scheduled to end at {{ $endsAt->format('h:i A') }}.
                                    @endif
                                </p>
                            </div>
                        </div>
                    </section>

                @elseif ($isCompleted)
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                        <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-[#213B73]">
                                <svg
                                    class="h-7 w-7"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p class="section-kicker">
                                    Event Status
                                </p>

                                <h2 class="section-title mt-2 text-3xl">
                                    This event has been completed.
                                </h2>

                                <p class="mt-2 text-sm font-medium leading-7 text-slate-600">
                                    Event information remains available because the organizer has kept it publicly visible.
                                </p>
                            </div>
                        </div>
                    </section>
                @endif

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    <p class="section-kicker">
                        Event Information
                    </p>

                    <h2 class="section-title mt-3 text-3xl">
                        Date, time and venue
                    </h2>

                    <div class="mt-8 grid gap-5 sm:grid-cols-2">
                        <div class="rounded-2xl bg-slate-50 p-5">
                            <p class="text-xs font-black uppercase tracking-wide text-slate-400">
                                Event Date
                            </p>

                            <p class="mt-2 text-lg font-black text-[#213B73]">
                                {{ $event->event_date_display }}
                            </p>
                        </div>

                        <div class="rounded-2xl bg-slate-50 p-5">
                            <p class="text-xs font-black uppercase tracking-wide text-slate-400">
                                Event Time
                            </p>

                            <p class="mt-2 text-lg font-black text-[#213B73]">
                                {{ $event->time_display }}
                            </p>
                        </div>

                        <div class="rounded-2xl bg-slate-50 p-5 sm:col-span-2">
                            <p class="text-xs font-black uppercase tracking-wide text-slate-400">
                                Venue
                            </p>

                            <p class="mt-2 text-lg font-black text-[#213B73]">
                                {{ $event->full_venue_display }}
                            </p>
                        </div>

                        @if ($event->dress_code)
                            <div class="rounded-2xl bg-slate-50 p-5 sm:col-span-2">
                                <p class="text-xs font-black uppercase tracking-wide text-slate-400">
                                    Dress Code
                                </p>

                                <p class="mt-2 text-lg font-black text-[#213B73]">
                                    {{ $event->dress_code }}
                                </p>
                            </div>
                        @endif
                    </div>
                </section>

                @if ($event->welcome_message)
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                        <p class="section-kicker">
                            Welcome Message
                        </p>

                        <h2 class="section-title mt-3 text-3xl">
                            You are warmly welcome
                        </h2>

                        <p class="mt-5 whitespace-pre-line text-base font-medium leading-8 text-slate-600">
                            {{ $event->welcome_message }}
                        </p>
                    </section>
                @endif

                @if ($event->program)
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                        <p class="section-kicker">
                            Event Program
                        </p>

                        <h2 class="section-title mt-3 text-3xl">
                            Program overview
                        </h2>

                        <div class="mt-6 space-y-3">
                            @foreach ($event->program_items as $item)
                                <div class="flex items-start gap-3 rounded-2xl bg-slate-50 px-4 py-4">
                                    <span class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#FD9618] text-xs font-black text-white">
                                        {{ $loop->iteration }}
                                    </span>

                                    <p class="font-bold leading-7 text-slate-700">
                                        {{ $item }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            <aside class="lg:sticky lg:top-24 lg:self-start">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-black text-[#213B73]">
                        Event Actions
                    </h2>

                    <p class="mt-2 text-sm font-medium leading-6 text-slate-600">
                        Use the available options below for directions or organizer assistance.
                    </p>

                    <div class="mt-6 grid gap-3">
                        @if ($event->google_maps_link)
                            <a
                                href="{{ $event->google_maps_link }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="btn w-full bg-[#213B73] text-white hover:bg-[#182d59]"
                            >
                                <svg
                                    class="h-5 w-5"
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

                                View Location
                            </a>
                        @endif

                        @if ($organizerPhoneClean)
                            <a
                                href="tel:+{{ $organizerPhoneClean }}"
                                class="btn w-full border border-slate-200 bg-white text-[#213B73] hover:bg-slate-50"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3 5a2 2 0 012-2h2l2 5-2 1a16 16 0 008 8l1-2 5 2v2a2 2 0 01-2 2h-1C9.7 21 3 14.3 3 6V5z"
                                    />
                                </svg>

                                Contact Organizer
                            </a>
                        @endif
                    </div>

                    <div class="mt-6 border-t border-slate-200 pt-6">
                        <p class="text-xs font-black uppercase tracking-wide text-slate-400">
                            Privacy Notice
                        </p>

                        <p class="mt-2 text-sm font-medium leading-6 text-slate-600">
                            Only information approved for public display is shown on this page.
                        </p>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>
@endsection

@if (
    $startsAt
    && ! $isCompleted
    && ! $isHappeningNow
    && $event->shouldShowCountdown()
)
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const countdown = document.getElementById('eventCountdown');

                if (!countdown) {
                    return;
                }

                const eventTime = new Date(countdown.dataset.eventTime).getTime();

                const elements = {
                    days: document.getElementById('countdown-days'),
                    hours: document.getElementById('countdown-hours'),
                    minutes: document.getElementById('countdown-minutes'),
                    seconds: document.getElementById('countdown-seconds'),
                };

                let countdownInterval = null;

                function setValue(key, value) {
                    if (elements[key]) {
                        elements[key].textContent = String(value).padStart(2, '0');
                    }
                }

                function stopCountdown() {
                    if (countdownInterval) {
                        window.clearInterval(countdownInterval);
                        countdownInterval = null;
                    }
                }

                function updateCountdown() {
                    const distance = eventTime - Date.now();

                    if (!Number.isFinite(eventTime) || distance <= 0) {
                        setValue('days', 0);
                        setValue('hours', 0);
                        setValue('minutes', 0);
                        setValue('seconds', 0);
                        stopCountdown();

                        return;
                    }

                    setValue(
                        'days',
                        Math.floor(distance / (1000 * 60 * 60 * 24))
                    );

                    setValue(
                        'hours',
                        Math.floor(
                            (distance % (1000 * 60 * 60 * 24))
                            / (1000 * 60 * 60)
                        )
                    );

                    setValue(
                        'minutes',
                        Math.floor(
                            (distance % (1000 * 60 * 60))
                            / (1000 * 60)
                        )
                    );

                    setValue(
                        'seconds',
                        Math.floor(
                            (distance % (1000 * 60))
                            / 1000
                        )
                    );
                }

                updateCountdown();
                countdownInterval = window.setInterval(updateCountdown, 1000);
            });
        </script>
    @endpush
@endif
