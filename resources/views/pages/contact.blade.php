@extends('layouts.public')

@section('title', 'Contact Us | eLive Card')
@section('description', 'Contact eLive Card for digital invitations, RSVP management, guest communication, secure QR check-in, and event reporting services.')

@section('content')
@php
    $contactPhone = '+255 745 939 140';
    $contactPhoneClean = '255745939140';
    $contactEmail = 'info@elive.co.tz';
    $whatsAppUrl = 'https://wa.me/' . $contactPhoneClean;
@endphp

{{-- Hero --}}
<section class="bg-[#213B73] py-16 text-white sm:py-20">
    <div class="container-shell">
        <div class="mx-auto max-w-3xl text-center">
            <p class="text-xs font-black uppercase tracking-[0.2em] text-[#FD9618]">
                Contact eLive
            </p>

            <h1 class="mt-4 text-4xl font-black leading-tight tracking-[-0.04em] sm:text-5xl">
                Let us help you manage your event professionally.
            </h1>

            <p class="mx-auto mt-5 max-w-2xl text-base font-medium leading-8 text-white/75">
                Contact our team for digital invitations, RSVP management,
                guest communication, QR check-in, and event reporting.
            </p>

            
        </div>
    </div>
</section>

{{-- Contact options --}}
<section class="bg-[#F8FAFC] py-16 sm:py-20">
    <div class="container-shell">
        <div class="mx-auto max-w-3xl text-center">
            <p class="section-kicker">
                Contact Options
            </p>

            <h2 class="section-title mt-3 text-3xl sm:text-4xl">
                Choose the easiest way to reach us.
            </h2>
        </div>

        <div class="mt-10 grid gap-5 md:grid-cols-3">
            @if ($whatsAppUrl)
                <a
                    href="{{ $whatsAppUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-[#213B73]/20 hover:shadow-lg"
                >
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#213B73] text-white">
                        <svg
                            class="h-6 w-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M8 10h8M8 14h5m8-2a9 9 0 11-4-7.5L21 4l-.5 4A9 9 0 0121 12z"
                            />
                        </svg>
                    </div>

                    <h3 class="mt-5 text-xl font-black text-[#213B73]">
                        WhatsApp
                    </h3>

                    <p class="mt-3 text-sm font-medium leading-7 text-slate-600">
                        Chat with our team about your event and service requirements.
                    </p>

                    <p class="mt-4 text-sm font-black text-[#FD9618]">
                        {{ $contactPhone }}
                    </p>
                </a>
            @endif

            @if ($contactPhone)
                <a
                    href="tel:{{ $contactPhoneClean }}"
                    class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-[#213B73]/20 hover:shadow-lg"
                >
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#213B73] text-white">
                        <svg
                            class="h-6 w-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M5 4h4l2 5-3 2a16 16 0 007 7l2-3 5 2v4c0 1.1-.9 2-2 2C11.7 23 1 12.3 1 4c0-1.1.9-2 2-2h2z"
                            />
                        </svg>
                    </div>

                    <h3 class="mt-5 text-xl font-black text-[#213B73]">
                        Call Us
                    </h3>

                    <p class="mt-3 text-sm font-medium leading-7 text-slate-600">
                        Speak directly with our team for event setup assistance.
                    </p>

                    <p class="mt-4 text-sm font-black text-[#FD9618]">
                        {{ $contactPhone }}
                    </p>
                </a>
            @endif

            <a
                href="mailto:{{ $contactEmail }}"
                class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-[#213B73]/20 hover:shadow-lg"
            >
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#213B73] text-white">
                    <svg
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M3 6h18v12H3V6zm0 1l9 6 9-6"
                        />
                    </svg>
                </div>

                <h3 class="mt-5 text-xl font-black text-[#213B73]">
                    Email
                </h3>

                <p class="mt-3 text-sm font-medium leading-7 text-slate-600">
                    Send your event details and requirements by email.
                </p>

                <p class="mt-4 break-all text-sm font-black text-[#FD9618]">
                    {{ $contactEmail }}
                </p>
            </a>
        </div>
    </div>
</section>

{{-- Event details checklist --}}
<section class="bg-white py-16 sm:py-20">
    <div class="container-shell">
        <div class="rounded-3xl border border-slate-200 bg-[#F8FAFC] p-7 sm:p-10">
            <div class="grid gap-8 lg:grid-cols-[0.8fr_1.2fr] lg:items-center">
                <div>
                    <p class="section-kicker">
                        Before You Contact Us
                    </p>

                    <h2 class="section-title mt-3 text-3xl">
                        Share the main details of your event.
                    </h2>

                    <p class="mt-4 text-sm font-medium leading-7 text-slate-600">
                        These details will help our team understand your requirements
                        and respond more quickly.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        'Event type',
                        'Event date',
                        'Expected invitees',
                        'Invitation card requirements',
                        'WhatsApp or SMS requirements',
                        'QR check-in requirements',
                    ] as $detail)
                        <div class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white p-4">
                            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#FD9618]/15 text-[#FD9618]">
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
                                        stroke-width="2.2"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                            </span>

                            <p class="text-sm font-bold leading-6 text-slate-700">
                                {{ $detail }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
