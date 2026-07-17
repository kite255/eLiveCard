@extends('layouts.public')

@section('title', 'About eLive Card')
@section('description', 'Learn how eLive Card helps social-event organizers manage invitations, RSVP, QR check-in, and reporting.')

@section('content')
@php
    use Illuminate\Support\Facades\Route;

    $contactUrl = Route::has('contact')
        ? route('contact')
        : url('/contact');
@endphp

<section class="bg-[#213B73] py-16 text-white sm:py-20">
    <div class="container-shell">
        <div class="max-w-3xl">
            <p class="text-xs font-black uppercase tracking-[0.2em] text-[#FD9618]">
                About eLive Card
            </p>

            <h1 class="mt-4 text-4xl font-black tracking-tight sm:text-5xl">
                Simple digital invitation and guest management.
            </h1>

            <p class="mt-5 text-base font-medium leading-8 text-white/75">
                eLive Card helps organizers manage invitations, RSVP responses,
                guest communication, QR check-in, and event reporting.
            </p>
        </div>
    </div>
</section>

<section class="bg-white py-16">
    <div class="container-shell grid gap-6 lg:grid-cols-2">
        <article class="rounded-3xl border border-slate-200 bg-[#F8FAFC] p-7">
            <p class="section-kicker">Our Purpose</p>

            <h2 class="section-title mt-3 text-2xl">
                Make event guest management easier.
            </h2>

            <p class="mt-4 text-sm font-medium leading-7 text-slate-600">
                The platform brings invitations, invitee records, RSVP,
                messaging, check-in, and reporting into one organized workflow.
            </p>
        </article>

        <article class="rounded-3xl border border-slate-200 bg-[#F8FAFC] p-7">
            <p class="section-kicker">Our Focus</p>

            <h2 class="section-title mt-3 text-2xl">
                Social events first.
            </h2>

            <p class="mt-4 text-sm font-medium leading-7 text-slate-600">
                We support weddings, send-offs, kitchen parties, engagements,
                birthdays, graduations, anniversaries, baby showers,
                religious celebrations, and private family events.
            </p>
        </article>
    </div>
</section>

<section class="bg-[#F8FAFC] py-16">
    <div class="container-shell">
        <div class="rounded-3xl bg-[#213B73] px-6 py-10 text-center sm:px-10">
            <h2 class="text-3xl font-black text-white">
                Need eLive Card for your event?
            </h2>

            <p class="mx-auto mt-4 max-w-2xl text-sm font-medium leading-7 text-white/70">
                Contact us to discuss your invitations, RSVP, guest communication,
                QR check-in, and reporting needs.
            </p>

            <a
                href="{{ $contactUrl }}"
                class="btn mt-7 bg-[#FD9618] text-white hover:bg-[#e8870f]"
            >
                Contact Us
            </a>
        </div>
    </div>
</section>
@endsection
