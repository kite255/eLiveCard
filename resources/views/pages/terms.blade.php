@extends('layouts.public')

@section('title', 'Terms of Service | eLive Card')
@section('description', 'Terms governing the use of eLive Card for digital invitations, RSVP, messaging, QR check-in, and event reporting.')

@section('content')
<section class="bg-[#213B73] py-14 text-white">
    <div class="container-shell">
        <p class="text-xs font-black uppercase tracking-[0.2em] text-[#FD9618]">
            Legal
        </p>

        <h1 class="mt-4 text-4xl font-black tracking-tight">
            Terms of Service
        </h1>

        <p class="mt-4 text-sm font-medium text-white/70">
            Last updated: {{ now()->format('d M Y') }}
        </p>
    </div>
</section>

<section class="bg-[#F8FAFC] py-14">
    <article class="container-shell max-w-4xl rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
        <div class="space-y-8 text-sm font-medium leading-7 text-slate-600">
            <section>
                <h2 class="text-xl font-black text-[#213B73]">
                    1. Platform Use
                </h2>

                <p class="mt-3">
                    eLive Card may only be used for lawful event invitation, RSVP,
                    guest communication, invitee engagement, check-in, and reporting activities.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-black text-[#213B73]">
                    2. Organizer Responsibilities
                </h2>

                <p class="mt-3">
                    Organizers are responsible for event details, invitee records,
                    message content, invitation templates, approval decisions,
                    and access granted to event staff.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-black text-[#213B73]">
                    3. Invitee Content
                </h2>

                <p class="mt-3">
                    Invitees must not submit unlawful, harmful, offensive, or unauthorized
                    content. Organizers may approve, reject, hide, or remove submitted
                    wishes and photos.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-black text-[#213B73]">
                    4. Messaging Services
                </h2>

                <p class="mt-3">
                    WhatsApp and SMS delivery may depend on external providers,
                    account verification, template approval, network availability,
                    valid phone numbers, and sufficient messaging credit.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-black text-[#213B73]">
                    5. Service Availability
                </h2>

                <p class="mt-3">
                    Reasonable efforts are made to maintain availability, but uninterrupted
                    service cannot be guaranteed during maintenance, provider outages,
                    network interruptions, or circumstances outside operational control.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-black text-[#213B73]">
                    6. Prohibited Access
                </h2>

                <p class="mt-3">
                    Users must not bypass permissions, guess private invitation links,
                    reuse invalid QR codes, access another event's information,
                    or interfere with platform security.
                </p>
            </section>
        </div>
    </article>
</section>
@endsection
