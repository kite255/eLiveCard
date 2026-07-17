@extends('layouts.public')

@section('title', 'eLive Card | Digital Invitations, RSVP & QR Check-in')
@section('description', 'Create personalized digital invitations, manage invitees, track RSVP responses, send cards through WhatsApp or SMS, and validate guests using secure QR check-in.')

@section('content')
@php
    use Illuminate\Support\Facades\Route;

    $loginUrl = Route::has('filament.admin.auth.login')
        ? route('filament.admin.auth.login')
        : url('/admin/login');

    $contactUrl = Route::has('contact')
        ? route('contact')
        : url('/contact');

    $eventTypes = [
        ['name' => 'Wedding', 'icon' => 'rings'],
        ['name' => 'Send-off', 'icon' => 'sparkles'],
        ['name' => 'Kitchen Party', 'icon' => 'gift'],
        ['name' => 'Engagement', 'icon' => 'heart'],
        ['name' => 'Birthday', 'icon' => 'cake'],
        ['name' => 'Graduation', 'icon' => 'graduation'],
        ['name' => 'Anniversary', 'icon' => 'calendar'],
        ['name' => 'Baby Shower', 'icon' => 'baby'],
        ['name' => 'Religious Celebration', 'icon' => 'church'],
        ['name' => 'Private Family Event', 'icon' => 'home'],
    ];

    $features = [
        [
            'title' => 'Personalized Invitation Cards',
            'description' => 'Generate a unique invitation card for every invitee using names, guest limits, table numbers, serial numbers, and secure QR codes.',
            'icon' => 'card',
        ],
        [
            'title' => 'RSVP Tracking',
            'description' => 'Know who is attending, who declined, and how many guests are expected before the event day.',
            'icon' => 'check',
        ],
        [
            'title' => 'WhatsApp & SMS Sending',
            'description' => 'Send invitation cards, RSVP links, venue links, and reminders directly to invitees.',
            'icon' => 'message',
        ],
        [
            'title' => 'Invitee Digital Page',
            'description' => 'Give each invitee a private page for RSVP, card viewing, countdown, program, wishes, photos, venue, and organizer contact.',
            'icon' => 'phone',
        ],
        [
            'title' => 'Secure QR Check-in',
            'description' => 'Validate invitation status, QR token, guest limit, event ownership, and previous check-in records at the gate.',
            'icon' => 'qr',
        ],
        [
            'title' => 'Reports & Audit Logs',
            'description' => 'Review RSVP, attendance, message delivery, check-in activity, approved content, and administrative actions.',
            'icon' => 'chart',
        ],
    ];
@endphp

{{-- Hero --}}
<section class="bg-[#213B73]">
    <div class="container-shell flex min-h-[520px] items-center py-16 sm:py-20 lg:py-24">
        <div class="mx-auto max-w-4xl text-center">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-[#FD9618]">
                Digital Invitation Management
            </p>

            <h1 class="mt-5 text-4xl font-black leading-[1.08] tracking-[-0.04em] text-white sm:text-5xl lg:text-6xl">
                Manage invitations, RSVP, and guest check-in in one place.
            </h1>

            <p class="mx-auto mt-6 max-w-2xl text-base font-medium leading-8 text-white/75 sm:text-lg">
                Create personalized invitation cards, send them through WhatsApp or SMS,
                track responses, and validate guests securely using QR codes.
            </p>

            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                <a
                    href="{{ $contactUrl }}"
                    class="btn bg-[#FD9618] text-white shadow-lg shadow-black/10 transition hover:bg-[#e8870f]"
                >
                    Contact Us

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
                            d="M13 7l5 5-5 5M6 12h12"
                        />
                    </svg>
                </a>

                <a
                    href="#how-it-works"
                    class="btn border border-white/25 bg-transparent text-white transition hover:bg-white/10"
                >
                    See How It Works
                </a>
            </div>

            <div class="mt-9 flex flex-wrap justify-center gap-x-6 gap-y-3 text-xs font-bold text-white/60">
                <span>Personalized Cards</span>
                <span>RSVP Tracking</span>
                <span>WhatsApp &amp; SMS</span>
                <span>Secure QR Check-in</span>
            </div>
        </div>
    </div>
</section>

{{-- Intro --}}
<section class="border-b border-slate-200 bg-white py-12 sm:py-14">
    <div class="container-shell">
        <div class="grid gap-8 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
            <div>
                <p class="section-kicker">One Organized Workflow</p>

                <h2 class="section-title mt-3 max-w-2xl text-3xl sm:text-4xl">
                    Replace scattered guest lists and manual event entry.
                </h2>
            </div>

            <p class="text-base font-medium leading-8 text-slate-600">
                eLive Card brings invitation design, invitee management, communication, RSVP, QR validation, and reporting into one professional social-event platform.
            </p>
        </div>
    </div>
</section>

{{-- Supported events --}}
<section id="events" class="bg-[#F8FAFC] py-16 sm:py-20">
    <div class="container-shell">
        <div class="mx-auto max-w-3xl text-center">
            <p class="section-kicker">Supported Social Events</p>

            <h2 class="section-title mt-3 text-3xl sm:text-4xl">
                Built for meaningful celebrations.
            </h2>

            <p class="mt-4 text-base font-medium leading-7 text-slate-600">
                Manage private and social events where RSVP, guest limits, personalized invitations, and controlled entry are important.
            </p>
        </div>

        <div class="mt-10 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($eventTypes as $eventType)
                <article class="group rounded-2xl border border-slate-200 bg-white p-5 text-center shadow-sm transition duration-200 hover:-translate-y-1 hover:border-[#213B73]/20 hover:shadow-lg">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#213B73]/[0.07] text-[#213B73] transition group-hover:bg-[#213B73] group-hover:text-white">
                        @switch($eventType['icon'])
                            @case('rings')
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle cx="9" cy="12" r="4" stroke-width="1.8"/>
                                    <circle cx="15" cy="12" r="4" stroke-width="1.8"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.5 7.8L12 5l1.5 2.8"/>
                                </svg>
                                @break

                            @case('sparkles')
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3l1.4 4.1L17.5 8.5l-4.1 1.4L12 14l-1.4-4.1L6.5 8.5l4.1-1.4L12 3zM18 14l.8 2.2L21 17l-2.2.8L18 20l-.8-2.2L15 17l2.2-.8L18 14z"/>
                                </svg>
                                @break

                            @case('gift')
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 10h16v10H4V10zm-1-4h18v4H3V6zm9 0v14M8.5 6C7 6 6 5.2 6 4.2S7 2.5 8.2 3.1C9.6 3.8 11 6 11 6m4.5 0C17 6 18 5.2 18 4.2S17 2.5 15.8 3.1C14.4 3.8 13 6 13 6"/>
                                </svg>
                                @break

                            @case('heart')
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 20s-7-4.4-7-10a4 4 0 017-2.6A4 4 0 0119 10c0 5.6-7 10-7 10z"/>
                                </svg>
                                @break

                            @case('cake')
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 10h14v10H5V10zm-2 4h18M9 10V7m6 3V7M9 4h.01M15 4h.01"/>
                                </svg>
                                @break

                            @case('graduation')
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 9l9-5 9 5-9 5-9-5zm4 2.5V16c3 2 7 2 10 0v-4.5M21 9v6"/>
                                </svg>
                                @break

                            @case('calendar')
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <rect x="4" y="5" width="16" height="15" rx="2" stroke-width="1.8"/>
                                    <path stroke-linecap="round" stroke-width="1.8" d="M8 3v4M16 3v4M4 9h16"/>
                                </svg>
                                @break

                            @case('baby')
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle cx="12" cy="9" r="4" stroke-width="1.8"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 14c1.2-1 2.5-1.5 4-1.5s2.8.5 4 1.5v5H8v-5zM10 8h.01M14 8h.01M10.5 10.5c1 .8 2 .8 3 0"/>
                                </svg>
                                @break

                            @case('church')
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v5m-2-3h4M6 10l6-4 6 4v10H6V10zm4 10v-5h4v5"/>
                                </svg>
                                @break

                            @default
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 11l9-7 9 7M5 10v10h14V10M9 20v-6h6v6"/>
                                </svg>
                        @endswitch
                    </div>

                    <h3 class="mt-4 text-sm font-black leading-5 text-[#213B73]">
                        {{ $eventType['name'] }}
                    </h3>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- How it works --}}
<section id="how-it-works" class="bg-white py-16 sm:py-20">
    <div class="container-shell">
        <div class="mx-auto max-w-3xl text-center">
            <p class="section-kicker">How It Works</p>

            <h2 class="section-title mt-3 text-3xl sm:text-4xl">
                A simple process from setup to check-in.
            </h2>

            <p class="mt-4 text-base font-medium leading-7 text-slate-600">
                Complete the main event workflow in four clear steps.
            </p>
        </div>

        <div class="mt-12 grid gap-5 lg:grid-cols-4">
            @foreach ([
                ['number' => '01', 'title' => 'Event Setup', 'text' => 'Our team configures the event details, date, time, venue, dress code, program, and organizer contact.'],
                ['number' => '02', 'title' => 'Add Invitees', 'text' => 'Add guests manually or import them from Excel with card type and guest limits.'],
                ['number' => '03', 'title' => 'Generate & Send', 'text' => 'Generate personalized cards and send them through WhatsApp or SMS.'],
                ['number' => '04', 'title' => 'Track & Check In', 'text' => 'Monitor RSVP responses, scan QR codes, and review attendance reports.'],
            ] as $step)
                <article class="relative rounded-3xl border border-slate-200 bg-[#F8FAFC] p-6">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#FD9618] text-sm font-black text-white">
                        {{ $step['number'] }}
                    </div>

                    <h3 class="mt-5 text-xl font-black text-[#213B73]">
                        {{ $step['title'] }}
                    </h3>

                    <p class="mt-3 text-sm font-medium leading-7 text-slate-600">
                        {{ $step['text'] }}
                    </p>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- Features --}}
<section id="features" class="bg-[#F8FAFC] py-16 sm:py-20">
    <div class="container-shell">
        <div class="grid gap-8 lg:grid-cols-[0.8fr_1.2fr] lg:items-end">
            <div>
                <p class="section-kicker">Main Features</p>

                <h2 class="section-title mt-3 text-3xl sm:text-4xl">
                    Everything needed to manage invitees professionally.
                </h2>
            </div>

            <p class="max-w-2xl text-base font-medium leading-8 text-slate-600 lg:justify-self-end">
                From personalized invitations to secure gate entry, every feature supports a clear and controlled event workflow.
            </p>
        </div>

        <div class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($features as $feature)
                <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-[#213B73]/20 hover:shadow-lg">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#213B73] text-white">
                        @switch($feature['icon'])
                            @case('card')
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/></svg>
                                @break
                            @case('check')
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @break
                            @case('message')
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h8M8 14h5m8-2a9 9 0 11-4-7.5L21 4l-.5 4A9 9 0 0121 12z"/></svg>
                                @break
                            @case('phone')
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 2h10a2 2 0 012 2v16a2 2 0 01-2 2H7a2 2 0 01-2-2V4a2 2 0 012-2zm3 17h4"/></svg>
                                @break
                            @case('qr')
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 0h2v2h-2v-2zm4 0h2v6h-6v-2h4v-4z"/></svg>
                                @break
                            @default
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 19V9m5 10V5m5 14v-7m5 7V3"/></svg>
                        @endswitch
                    </div>

                    <h3 class="mt-5 text-xl font-black text-[#213B73]">
                        {{ $feature['title'] }}
                    </h3>

                    <p class="mt-3 text-sm font-medium leading-7 text-slate-600">
                        {{ $feature['description'] }}
                    </p>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- Benefits --}}
<section class="bg-white py-16 sm:py-20">
    <div class="container-shell">
        <div class="rounded-[30px] border border-slate-200 bg-white p-6 shadow-sm sm:p-8 lg:p-10">
            <div class="grid gap-10 lg:grid-cols-[0.85fr_1.15fr] lg:items-center">
                <div>
                    <p class="section-kicker">Organizer Benefits</p>

                    <h2 class="section-title mt-3 text-3xl sm:text-4xl">
                        Better control before, during, and after the event.
                    </h2>

                    <p class="mt-5 text-base font-medium leading-8 text-slate-600">
                        Replace scattered spreadsheets, printed invitation lists, and slow manual checks with one organized system.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        'Reduce printing and distribution costs',
                        'Know expected attendance before the event',
                        'Control allowed guests for every card',
                        'Prevent duplicate and invalid invitations',
                        'Speed up entrance validation with QR scanning',
                        'Generate reliable attendance and activity reports',
                    ] as $benefit)
                        <div class="flex items-start gap-3 rounded-2xl bg-[#F8FAFC] p-4">
                            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#FD9618]/15 text-[#FD9618]">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </span>

                            <p class="text-sm font-bold leading-6 text-slate-700">
                                {{ $benefit }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Security --}}
<section class="border-y border-slate-200 bg-[#F8FAFC] py-16 sm:py-20">
    <div class="container-shell">
        <div class="mx-auto max-w-3xl text-center">
            <p class="section-kicker">Secure Event Access</p>

            <h2 class="section-title mt-3 text-3xl sm:text-4xl">
                Designed to protect every invitation and check-in.
            </h2>
        </div>

        <div class="mt-10 grid gap-5 md:grid-cols-3">
            @foreach ([
                ['title' => 'Secure QR Tokens', 'text' => 'Each invitation uses a unique token that is validated on the server.'],
                ['title' => 'Guest Limit Control', 'text' => 'Allowed guest counts are checked before every successful entry.'],
                ['title' => 'Activity Records', 'text' => 'RSVP changes, messages, approvals, and check-ins can be reviewed.'],
            ] as $security)
                <article class="rounded-3xl border border-slate-200 bg-white p-6">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#213B73]/10 text-[#213B73]">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3l7 3v5c0 4.8-2.9 8-7 10-4.1-2-7-5.2-7-10V6l7-3zm-3 9l2 2 4-4"/>
                        </svg>
                    </div>

                    <h3 class="mt-5 text-lg font-black text-[#213B73]">
                        {{ $security['title'] }}
                    </h3>

                    <p class="mt-3 text-sm font-medium leading-7 text-slate-600">
                        {{ $security['text'] }}
                    </p>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- CTA --}}
<section class="bg-white py-16 sm:py-20">
    <div class="container-shell">
        <div class="rounded-[32px] bg-[#213B73] px-6 py-12 text-center sm:px-10 sm:py-14">
            <div class="mx-auto max-w-3xl">
                <p class="text-xs font-black uppercase tracking-[0.2em] text-[#FD9618]">
                    Start Your Event
                </p>

                <h2 class="mt-4 text-3xl font-black tracking-tight text-white sm:text-4xl">
                    Need the service for your event?
                </h2>

                <p class="mx-auto mt-4 max-w-2xl text-base font-medium leading-7 text-white/70">
                    Contact eLive to discuss your event, invitation requirements, guest management, RSVP, messaging, QR check-in, and reporting needs.
                </p>

                <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                    <a href="{{ $contactUrl }}" class="btn bg-[#FD9618] text-white hover:bg-[#e8870f]">
                        Contact Us
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
