@extends('layouts.public')

@section('title', 'Events | eLive Card')
@section('description', 'View upcoming and completed public events managed through eLive Card.')

@section('content')
<section class="bg-[#213B73] py-16 text-white sm:py-20">
    <div class="container-shell">
        <div class="mx-auto max-w-3xl text-center">
            <p class="text-xs font-black uppercase tracking-[0.2em] text-[#FD9618]">
                Public Events
            </p>

            <h1 class="mt-4 text-4xl font-black tracking-tight sm:text-5xl">
                Discover events managed with eLive Card.
            </h1>

            <p class="mx-auto mt-5 max-w-2xl text-base font-medium leading-8 text-white/75">
                Browse upcoming and completed events that organizers have approved
                for public display. Private, draft, and cancelled events remain hidden.
            </p>
        </div>
    </div>
</section>

<section class="bg-[#F8FAFC] py-16 sm:py-20">
    <div class="container-shell">
        <section>
            <div class="max-w-3xl">
                <div>
                    <p class="section-kicker">
                        Upcoming Events
                    </p>

                    <h2 class="section-title mt-3 text-3xl sm:text-4xl">
                        Events coming next
                    </h2>
                    <p class="mt-3 text-sm font-medium leading-7 text-slate-600">
                        Events scheduled for today or a future date.
                    </p>
                </div>
            </div>

            @if ($upcomingEvents->count())
                <div class="mx-auto mt-10 grid max-w-5xl gap-8 md:grid-cols-2">
                    @foreach ($upcomingEvents as $event)
                        <article class="flex h-full flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                            @if ($event->cover_image_url)
                                <a href="{{ route('events.show', $event) }}" class="block overflow-hidden">
                                    <img
                                        src="{{ $event->cover_image_url }}"
                                        alt="{{ $event->title }}"
                                        class="h-52 w-full object-cover transition duration-300 hover:scale-[1.02]"
                                    >
                                </a>
                            @endif

                            <div class="border-b border-slate-200 bg-[#213B73]/[0.05] p-6">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-xs font-black uppercase tracking-[0.16em] text-[#FD9618]">
                                                {{ $event->event_type_display }}
                                            </p>

                                            <span @class([
                                                'rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wide',
                                                'bg-orange-100 text-orange-700' => $event->isHappeningNow(),
                                                'bg-emerald-50 text-emerald-700' => ! $event->isHappeningNow(),
                                            ])>
                                                {{ $event->public_status_label }}
                                            </span>
                                        </div>

                                        <h3 class="mt-2 text-2xl font-black leading-tight text-[#213B73]">
                                            {{ $event->title }}
                                        </h3>
                                    </div>

                                    <div class="shrink-0 rounded-2xl bg-[#213B73] px-4 py-3 text-center text-white">
                                        <p class="text-xl font-black leading-none">
                                            {{ $event->event_date?->format('d') }}
                                        </p>

                                        <p class="mt-1 text-[10px] font-black uppercase tracking-wide text-white/75">
                                            {{ $event->event_date?->format('M') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-1 flex-col space-y-5 p-6">
                                <div class="space-y-4">
                                    <div class="flex items-start gap-3">
                                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-[#FD9618]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3M4 10h16M5 5h14a1 1 0 011 1v14H4V6a1 1 0 011-1z"/>
                                        </svg>

                                        <div>
                                            <p class="text-xs font-black uppercase tracking-wide text-slate-400">
                                                Date & Time
                                            </p>

                                            <p class="mt-1 text-sm font-bold text-slate-700">
                                                {{ $event->event_date?->format('d M Y') }}

                                                @if ($event->start_time)
                                                    · {{ $event->start_time->format('h:i A') }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-start gap-3">
                                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-[#FD9618]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21s6-5.3 6-11a6 6 0 10-12 0c0 5.7 6 11 6 11z"/>
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
                                    class="btn mt-auto w-full bg-[#213B73] text-white hover:bg-[#182d59]"
                                >
                                    View Event

                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5-5 5M6 12h12"/>
                                    </svg>
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="mt-10">
                    {{ $upcomingEvents->links() }}
                </div>
            @else
                <div class="mt-10 rounded-3xl border border-slate-200 bg-white p-10 text-center shadow-sm">
                    <h3 class="text-2xl font-black text-[#213B73]">
                        No upcoming public events
                    </h3>

                    <p class="mt-3 text-sm font-medium leading-7 text-slate-600">
                        Upcoming events will appear here after they are published.
                    </p>
                </div>
            @endif
        </section>

        <section class="mt-12 border-t border-slate-200 pt-12">
            <div class="max-w-3xl">
                <div>
                    <p class="section-kicker">
                        Completed Events
                    </p>

                    <h2 class="section-title mt-3 text-3xl sm:text-4xl">
                        Previous public events
                    </h2>
                    <p class="mt-3 text-sm font-medium leading-7 text-slate-600">
                        Completed events remain visible when the organizer keeps public visibility enabled.
                    </p>
                </div>
            </div>

            @if ($completedEvents->count())
                <div class="mx-auto mt-10 grid max-w-5xl gap-8 md:grid-cols-2">
                    @foreach ($completedEvents as $event)
                        <article class="flex h-full flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                            @if ($event->cover_image_url)
                                <a href="{{ route('events.show', $event) }}" class="block overflow-hidden">
                                    <img
                                        src="{{ $event->cover_image_url }}"
                                        alt="{{ $event->title }}"
                                        class="h-52 w-full object-cover grayscale-[20%] transition duration-300 hover:scale-[1.02]"
                                    >
                                </a>
                            @endif

                            <div class="border-b border-slate-200 bg-slate-50 p-6">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-xs font-black uppercase tracking-[0.16em] text-[#FD9618]">
                                                {{ $event->event_type_display }}
                                            </p>

                                            <span class="rounded-full bg-slate-200 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-slate-600">
                                                Completed
                                            </span>
                                        </div>

                                        <h3 class="mt-2 text-2xl font-black leading-tight text-[#213B73]">
                                            {{ $event->title }}
                                        </h3>
                                    </div>

                                    <div class="shrink-0 rounded-2xl bg-slate-600 px-4 py-3 text-center text-white">
                                        <p class="text-xl font-black leading-none">
                                            {{ $event->event_date?->format('d') }}
                                        </p>

                                        <p class="mt-1 text-[10px] font-black uppercase tracking-wide text-white/75">
                                            {{ $event->event_date?->format('M') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-1 flex-col space-y-5 p-6">
                                <div class="space-y-4">
                                    <div class="flex items-start gap-3">
                                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-[#FD9618]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3M4 10h16M5 5h14a1 1 0 011 1v14H4V6a1 1 0 011-1z"/>
                                        </svg>

                                        <div>
                                            <p class="text-xs font-black uppercase tracking-wide text-slate-400">
                                                Date & Time
                                            </p>

                                            <p class="mt-1 text-sm font-bold text-slate-700">
                                                {{ $event->event_date?->format('d M Y') }}

                                                @if ($event->start_time)
                                                    · {{ $event->start_time->format('h:i A') }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-start gap-3">
                                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-[#FD9618]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21s6-5.3 6-11a6 6 0 10-12 0c0 5.7 6 11 6 11z"/>
                                            <circle cx="12" cy="10" r="2" stroke-width="2"/>
                                        </svg>

                                        <div>
                                            <p class="text-xs font-black uppercase tracking-wide text-slate-400">
                                                Venue
                                            </p>

                                            <p class="mt-1 text-sm font-bold text-slate-700">
                                                {{ $event->venue_name ?: 'Venue not available' }}
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
                                    class="btn mt-auto w-full border border-slate-200 bg-white text-[#213B73] hover:bg-slate-50"
                                >
                                    View Event

                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5-5 5M6 12h12"/>
                                    </svg>
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="mt-10">
                    {{ $completedEvents->links() }}
                </div>
            @else
                <div class="mt-10 rounded-3xl border border-slate-200 bg-white p-10 text-center shadow-sm">
                    <h3 class="text-2xl font-black text-[#213B73]">
                        No completed public events
                    </h3>

                    <p class="mt-3 text-sm font-medium leading-7 text-slate-600">
                        Completed events will appear here when they remain marked as public.
                    </p>
                </div>
            @endif
        </section>
    </div>
</section>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const refreshInterval = 60000;
        let refreshTimer = null;

        function scheduleRefresh() {
            refreshTimer = window.setTimeout(function () {
                if (document.visibilityState === 'visible') {
                    window.location.reload();
                    return;
                }

                scheduleRefresh();
            }, refreshInterval);
        }

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                window.location.reload();
            }
        });

        scheduleRefresh();

        window.addEventListener('beforeunload', function () {
            if (refreshTimer) {
                window.clearTimeout(refreshTimer);
            }
        });
    });
</script>
@endpush

