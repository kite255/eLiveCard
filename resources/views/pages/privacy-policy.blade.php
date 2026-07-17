@extends('layouts.public')

@section('title', 'Privacy Policy | eLive Card')
@section('description', 'Privacy policy for eLive Card invitee information, RSVP responses, uploaded content, QR validation, and event reporting.')

@section('content')
<section class="bg-[#213B73] py-14 text-white">
    <div class="container-shell">
        <p class="text-xs font-black uppercase tracking-[0.2em] text-[#FD9618]">
            Legal
        </p>

        <h1 class="mt-4 text-4xl font-black tracking-tight">
            Privacy Policy
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
                    1. Information We Process
                </h2>

                <p class="mt-3">
                    We may process invitee names, phone numbers, card types, guest limits,
                    table numbers, RSVP responses, invitation status, check-in records,
                    wishes, photos, and messaging activity required to manage an event.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-black text-[#213B73]">
                    2. How We Use Information
                </h2>

                <p class="mt-3">
                    Information is used to prepare personalized invitations, send event messages,
                    collect RSVP responses, provide private invitee pages, validate entry,
                    manage submitted content, and prepare event reports.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-black text-[#213B73]">
                    3. Access and Security
                </h2>

                <p class="mt-3">
                    Access is limited to authorized users. QR codes are validated on the server,
                    user permissions are controlled by roles, and important administrative
                    actions may be recorded.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-black text-[#213B73]">
                    4. Photos and Wishes
                </h2>

                <p class="mt-3">
                    Photos and wishes submitted by invitees may require organizer approval
                    before they are displayed publicly.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-black text-[#213B73]">
                    5. Data Retention
                </h2>

                <p class="mt-3">
                    Event information should be retained only for as long as it is needed
                    for event management, reporting, legal requirements, or legitimate
                    operational purposes.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-black text-[#213B73]">
                    6. Contact
                </h2>

                <p class="mt-3">
                    Questions about personal information should be directed to the event
                    organizer or the eLive support contact shown on this website.
                </p>
            </section>
        </div>
    </article>
</section>
@endsection
