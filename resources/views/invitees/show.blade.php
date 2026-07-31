@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Facades\Storage;

    $event = $event ?? $invitee->event ?? null;

    $eventName = $event?->title
        ?? $event?->name
        ?? 'eLive Event';

    $eventDate = $event?->event_date ?? $event?->date ?? null;
    $eventTime = $event?->start_time ?? $event?->time ?? null;
    $eventEndTime = $event?->end_time ?? null;

    $formattedDate = $eventDate
        ? Carbon::parse($eventDate)->format('d M Y')
        : 'Date will be shared';

    $formattedTime = $eventTime
        ? Carbon::parse($eventTime)->format('h:i A')
        : 'Time will be shared';

    $formattedEndTime = $eventEndTime
        ? Carbon::parse($eventEndTime)->format('h:i A')
        : null;

    $timeDisplay = $formattedEndTime
        ? $formattedTime.' - '.$formattedEndTime
        : $formattedTime;

    $venue = $event?->venue_name
        ?? $event?->venue
        ?? $event?->location
        ?? 'Venue will be shared';

    $venueAddress = $event?->venue_address ?? null;
    $dressCode = $event?->dress_code ?? null;
    $googleMapsLink = $event?->google_maps_link
        ?? $event?->map_link
        ?? null;

    $cardTypeName = $invitee->cardType->name
        ?? $invitee->card_type
        ?? $invitee->card_type_name
        ?? 'Invitation';

    $allowedGuests = (int) (
        $allowedGuests
        ?? $invitee->final_allowed_guests
        ?? $invitee->allowed_guests
        ?? $invitee->cardType->allowed_guests
        ?? $invitee->cardType->allowed_people
        ?? 1
    );

    $allowedGuests = max(1, $allowedGuests);

    $confirmedGuests = (int) ($invitee->confirmed_guests ?? 0);
    $rsvpStatus = $invitee->rsvp_status ?? 'pending';
    $isAttending = in_array($rsvpStatus, ['attending', 'confirmed'], true);

    $rsvpLabel = match ($rsvpStatus) {
        'attending', 'confirmed' => 'Attending',
        'not_attending', 'declined' => 'Not Attending',
        default => 'Pending Response',
    };

    $rsvpColorClass = match ($rsvpStatus) {
        'attending', 'confirmed' => 'background:#ECFDF5;color:#047857;border-color:#A7F3D0;',
        'not_attending', 'declined' => 'background:#FEF2F2;color:#B91C1C;border-color:#FECACA;',
        default => 'background:#FFF7ED;color:#C2410C;border-color:#FED7AA;',
    };

    $guestSummaryLabel = match ($rsvpStatus) {
        'attending', 'confirmed' => max(1, $confirmedGuests ?: $allowedGuests).' confirmed',
        'not_attending', 'declined' => '0 confirmed',
        default => 'Not confirmed',
    };

    $tableNumber = $invitee->table_number ?? null;

    $serialNumber = $invitee->serial_number
        ?? $invitee->serial
        ?? $invitee->short_code
        ?? null;

    $generatedCardUrl = $generatedCardUrl ?? null;

    if (! $generatedCardUrl && filled($invitee->generated_card_url ?? null)) {
        $generatedCardUrl = $invitee->generated_card_url;
    }

    if (! $generatedCardUrl && filled($invitee->generated_card_path ?? null)) {
        $generatedCardUrl = Storage::disk('public')->url(
            $invitee->generated_card_path
        );
    }

    if (! $generatedCardUrl && filled($invitee->card_path ?? null)) {
        $generatedCardUrl = Storage::disk('public')->url(
            $invitee->card_path
        );
    }

    if (! $generatedCardUrl && method_exists($invitee, 'generatedCards')) {
        $latestGeneratedCard = $invitee->generatedCards()
            ->whereNotNull('file_path')
            ->latest()
            ->first();

        if ($latestGeneratedCard && filled($latestGeneratedCard->file_path)) {
            $generatedCardUrl = Storage::disk('public')->url(
                $latestGeneratedCard->file_path
            );
        }
    }

    $publicCardUrl = $generatedCardUrl;

    if ($serialNumber && Route::has('public.card.show')) {
        $publicCardUrl = route('public.card.show', $serialNumber);
    }

    $organizerPhone = $organizerPhone
        ?? $event?->organizer_phone
        ?? $event?->contact_person_phone
        ?? $event?->contact_phone
        ?? $event?->phone
        ?? config('services.elive.contact_phone')
        ?? null;

    $organizerPhoneClean = $organizerPhone
        ? preg_replace('/\D+/', '', $organizerPhone)
        : null;

    $whatsAppOrganizerUrl = $whatsAppOrganizerUrl
        ?? ($organizerPhoneClean
            ? 'https://wa.me/'.$organizerPhoneClean
            : null);

    $coverImageUrl = $coverImageUrl ?? null;

    if (! $coverImageUrl && $event && filled($event->cover_image ?? null)) {
        $coverImageUrl = Storage::disk('public')->url(
            $event->cover_image
        );
    }

    $showCoverImage = (bool) (
        $showCoverImage
        ?? $event?->show_cover_image
        ?? true
    );

    $showCountdown = (bool) (
        $showCountdown
        ?? $event?->show_countdown
        ?? true
    );

    $showOrganizerContact = (bool) (
        $showOrganizerContact
        ?? $event?->show_organizer_contact
        ?? true
    );

    $showLoveStory = (bool) (
        $showLoveStory
        ?? $event?->show_love_story
        ?? false
    );

    $eventDateTime = null;

    if ($eventDate) {
        try {
            $eventDateTime = Carbon::parse($eventDate);

            if ($eventTime) {
                $time = Carbon::parse($eventTime);

                $eventDateTime->setTime(
                    $time->hour,
                    $time->minute,
                    0
                );
            }
        } catch (\Throwable) {
            $eventDateTime = null;
        }
    }

    $countdownTarget = $eventDateTime
        ? $eventDateTime->toIso8601String()
        : null;

    $language = in_array(($language ?? 'en'), ['en', 'sw'], true)
        ? $language
        : 'en';

    $loveStory = $loveStory
        ?? ($language === 'sw'
            ? ($event?->love_story_sw
                ?? $event?->love_story
                ?? $event?->love_story_en)
            : ($event?->love_story_en
                ?? $event?->love_story
                ?? $event?->love_story_sw));

    $translations = [
        'en' => [
            'private_invitation' => 'Private Invitation',
            'you_are_invited' => 'You are invited',
            'hello' => 'Hello',
            'date' => 'Date',
            'time' => 'Time',
            'venue' => 'Venue',
            'welcome' => 'Welcome',
            'rsvp' => 'RSVP',
            'confirm_attendance' => 'Confirm Attendance',
            'confirm_or_decline' => 'Confirm or decline this invitation.',
            'allowed_guests' => 'Allowed Guests',
            'rsvp_result' => 'RSVP Result',
            'attendance_confirmed' => 'Attendance Confirmed',
            'confirm_button' => 'Confirm Attendance',
            'decline_button' => 'I Will Not Attend',
            'invitation_card' => 'Invitation Card',
            'view_card_help' => 'View your personalized invitation card.',
            'view_invitation_card' => 'View Invitation Card',
            'card_preparing' => 'Your invitation card is being prepared.',
            'check_later' => 'Please check again later or contact the organizer.',
            'event_details' => 'Event Details',
            'card_type' => 'Card Type',
            'table' => 'Table',
            'dress_code' => 'Dress Code',
            'serial_number' => 'Serial Number',
            'not_assigned' => 'Not assigned',
            'open_location' => 'Open Venue Location',
            'our_story' => 'Our Story',
            'love_story' => 'Love Story',
            'countdown' => 'Countdown',
            'event_starts_in' => 'Event starts in',
            'days' => 'Days',
            'hours' => 'Hours',
            'minutes' => 'Minutes',
            'organizer_contact' => 'Organizer Contact',
            'contact_help' => 'Contact the organizer for assistance.',
            'call_organizer' => 'Call Organizer',
            'whatsapp_organizer' => 'WhatsApp Organizer',
            'contact_soon' => 'Organizer contact will be shared soon.',
            'powered_by' => 'Powered by',
        ],
        'sw' => [
            'private_invitation' => 'Mwaliko Binafsi',
            'you_are_invited' => 'Umealikwa',
            'hello' => 'Habari',
            'date' => 'Tarehe',
            'time' => 'Muda',
            'venue' => 'Mahali',
            'welcome' => 'Karibu',
            'rsvp' => 'THIBITISHA',
            'confirm_attendance' => 'Thibitisha Ushiriki',
            'confirm_or_decline' => 'Thibitisha au kataa mwaliko huu.',
            'allowed_guests' => 'Idadi ya Wageni',
            'rsvp_result' => 'Hali ya Uthibitisho',
            'attendance_confirmed' => 'Ushiriki Umethibitishwa',
            'confirm_button' => 'Nitahudhuria',
            'decline_button' => 'Sitahudhuria',
            'invitation_card' => 'Kadi ya Mwaliko',
            'view_card_help' => 'Fungua kadi yako binafsi ya mwaliko.',
            'view_invitation_card' => 'Fungua Kadi ya Mwaliko',
            'card_preparing' => 'Kadi yako ya mwaliko inaandaliwa.',
            'check_later' => 'Tafadhali angalia tena baadaye au wasiliana na mratibu.',
            'event_details' => 'Taarifa za Tukio',
            'card_type' => 'Aina ya Kadi',
            'table' => 'Meza',
            'dress_code' => 'Mavazi',
            'serial_number' => 'Namba ya Kadi',
            'not_assigned' => 'Haijapangwa',
            'open_location' => 'Fungua Mahali kwenye Ramani',
            'our_story' => 'Hadithi Yetu',
            'love_story' => 'Simulizi Yetu',
            'countdown' => 'Muda Uliobaki',
            'event_starts_in' => 'Tukio linaanza baada ya',
            'days' => 'Siku',
            'hours' => 'Saa',
            'minutes' => 'Dakika',
            'organizer_contact' => 'Mawasiliano ya Mratibu',
            'contact_help' => 'Wasiliana na mratibu kwa msaada.',
            'call_organizer' => 'Mpigie Mratibu',
            'whatsapp_organizer' => 'WhatsApp Mratibu',
            'contact_soon' => 'Mawasiliano ya mratibu yatashirikishwa hivi karibuni.',
            'powered_by' => 'Inaendeshwa na',
        ],
    ];

    $t = $translations[$language] ?? $translations['en'];

    $logoUrl = asset('images/elive-cardw-logo.png');
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $eventName }} - Invitation</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#213B73">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="description" content="Private invitation for {{ $eventName }}">

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        :root {
            --elive-blue: #213B73;
            --elive-orange: #FD9618;
            --elive-dark: #111827;
            --elive-bg: #F8FAFC;
        }

        * {
            -webkit-tap-highlight-color: transparent;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            background: var(--elive-bg);
            color: var(--elive-dark);
        }

        .page-shell {
            width: min(100%, 720px);
            margin: 0 auto;
        }

        .safe-x {
            padding-left: max(1rem, env(safe-area-inset-left));
            padding-right: max(1rem, env(safe-area-inset-right));
        }

        .soft-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            box-shadow: 0 14px 35px rgba(15, 23, 42, 0.07);
        }

        .hero-card {
            background: var(--elive-blue);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            border-radius: 16px;
            padding: 0.75rem 1rem;
            font-size: 0.92rem;
            font-weight: 800;
            text-align: center;
            transition: transform 0.16s ease, opacity 0.16s ease;
        }

        .btn:active {
            transform: scale(0.985);
        }

        .section-title {
            color: var(--elive-blue);
            font-size: 1.08rem;
            font-weight: 900;
            line-height: 1.3;
        }

        .muted {
            color: #64748B;
        }

        .details-grid,
        .countdown-grid {
            display: grid;
            gap: 0.75rem;
        }

        .details-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .countdown-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .detail-tile {
            padding: 1rem;
            border: 1px solid #EEF2F7;
            border-radius: 18px;
            background: #F8FAFC;
        }

        .detail-label {
            color: #94A3B8;
            font-size: 0.68rem;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .detail-value {
            margin-top: 0.3rem;
            color: #0F172A;
            font-weight: 900;
            line-height: 1.25;
        }

        @media (max-width: 390px) {
            .details-grid {
                grid-template-columns: 1fr;
            }

            .countdown-grid {
                gap: 0.45rem;
            }
        }
    </style>
</head>

<body>
<header class="sticky top-0 z-40 border-b border-white/10 bg-[#213B73] shadow-sm">
    <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-3 px-4 py-2.5 sm:px-6">
        <a href="#top" class="flex items-center">
            <img
                src="{{ $logoUrl }}"
                alt="eLive Card"
                class="h-7 w-auto sm:h-8"
            >
        </a>

        <div class="min-w-0 flex-1 px-3">
            <h1 class="truncate text-lg font-black text-white">
                {{ $t['private_invitation'] }}
            </h1>

            <p class="truncate text-xs font-semibold text-white/70">
                {{ $invitee->name }}
            </p>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <div class="flex rounded-full bg-white/10 p-1 ring-1 ring-white/15">
                <a
                    href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}"
                    class="rounded-full px-2.5 py-1.5 text-xs font-black transition
                        {{ $language === 'en'
                            ? 'bg-white text-[#213B73]'
                            : 'text-white'
                        }}"
                >
                    EN
                </a>

                <a
                    href="{{ request()->fullUrlWithQuery(['lang' => 'sw']) }}"
                    class="rounded-full px-2.5 py-1.5 text-xs font-black transition
                        {{ $language === 'sw'
                            ? 'bg-white text-[#213B73]'
                            : 'text-white'
                        }}"
                >
                    SW
                </a>
            </div>

            <a
                href="#rsvp"
                class="rounded-full bg-[#FD9618] px-3 py-2 text-xs font-black text-white"
            >
                {{ $t['rsvp'] }}
            </a>
        </div>
    </div>
</header>

<main id="top" class="page-shell safe-x pb-10 pt-3">
    <section class="hero-card relative overflow-hidden rounded-[30px] px-5 py-6 text-white shadow-xl">
        <div class="absolute -right-14 -top-14 h-44 w-44 rounded-full bg-white/[0.08]"></div>
        <div class="absolute -bottom-20 -left-20 h-56 w-56 rounded-full bg-[#FD9618]/15"></div>

        <div class="relative text-center">
            <p class="text-xs font-black uppercase tracking-[0.24em] text-[#FD9618]">
                {{ $t['you_are_invited'] }}
            </p>

            <h1 class="mt-3 text-2xl font-black leading-tight sm:text-3xl">
                {{ $eventName }}
            </h1>

            <p class="mt-3 text-sm font-semibold text-white/80">
                {{ $t['hello'] }} {{ $invitee->name }}
            </p>

            <div class="mt-5 grid grid-cols-1 gap-3 text-left sm:grid-cols-2">
                <div class="rounded-3xl bg-white/10 p-4 ring-1 ring-white/10">
                    <p class="text-[11px] font-black uppercase text-white/70">
                        Date
                    </p>

                    <p class="mt-1 text-sm font-black">
                        {{ $formattedDate }}
                    </p>
                </div>

                <div class="rounded-3xl bg-white/10 p-4 ring-1 ring-white/10">
                    <p class="text-[11px] font-black uppercase text-white/70">
                        Time
                    </p>

                    <p class="mt-1 text-sm font-black">
                        {{ $timeDisplay }}
                    </p>
                </div>
            </div>

            <div class="mt-3 rounded-3xl bg-white/10 p-4 text-left ring-1 ring-white/10">
                <p class="text-[11px] font-black uppercase text-white/70">
                    Venue
                </p>

                <p class="mt-1 text-base font-black">
                    {{ $venue }}
                </p>

                @if ($venueAddress)
                    <p class="mt-1 text-xs font-semibold text-white/65">
                        {{ $venueAddress }}
                    </p>
                @endif
            </div>
        </div>
    </section>

    <div class="mt-4 space-y-3">
        @if (session('success'))
            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if (session('warning'))
            <div class="rounded-3xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800">
                {{ session('warning') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-3xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-black">
                    Please check the form and try again.
                </p>

                <ul class="mt-2 list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    @if (
        ($showCoverImage && $coverImageUrl)
        || filled($event?->welcome_message)
    )
        <section class="soft-card mt-4 overflow-hidden rounded-[28px]">
            @if ($showCoverImage && $coverImageUrl)
                <img
                    src="{{ $coverImageUrl }}"
                    alt="Cover image for {{ $eventName }}"
                    class="h-44 w-full object-cover sm:h-56"
                >
            @endif

            @if (filled($event?->welcome_message))
                <div class="px-5 py-4">
                    <p class="text-xs font-black uppercase tracking-[0.22em] text-[#FD9618]">
                        Welcome
                    </p>

                    <p class="mt-2 whitespace-pre-line text-sm font-semibold leading-6 text-slate-600">
                        {{ $event->welcome_message }}
                    </p>
                </div>
            @endif
        </section>
    @endif

    <section id="rsvp" class="soft-card mt-4 rounded-[28px] p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.22em] text-[#FD9618]">
                    RSVP
                </p>

                <h2 class="section-title mt-1">
                    {{ $t['confirm_attendance'] }}
                </h2>

                <p class="mt-1 text-sm leading-6 muted">
                    {{ $t['confirm_or_decline'] }}
                </p>
            </div>

            <span
                class="w-fit shrink-0 rounded-full border px-3 py-1 text-[11px] font-black"
                style="{{ $rsvpColorClass }}"
            >
                {{ $rsvpLabel }}
            </span>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="rounded-[22px] bg-slate-50 p-4 ring-1 ring-slate-100">
                <p class="text-[11px] font-black uppercase text-slate-500">
                    {{ $t['allowed_guests'] }}
                </p>

                <p class="mt-2 text-2xl font-black text-[#213B73]">
                    {{ $allowedGuests }}
                </p>
            </div>

            <div class="rounded-[22px] bg-slate-50 p-4 ring-1 ring-slate-100">
                <p class="text-[11px] font-black uppercase text-slate-500">
                    {{ $t['rsvp_result'] }}
                </p>

                <p class="mt-2 text-lg font-black text-[#111827]">
                    {{ $guestSummaryLabel }}
                </p>
            </div>
        </div>

        <form
            method="POST"
            action="{{ route('invitee.rsvp', $invitee->short_code) }}"
            class="mt-5"
        >
            @csrf

            <input
                type="hidden"
                name="lang"
                value="{{ $language }}"
            >

            <input
                type="hidden"
                name="confirmed_guests"
                value="{{ $allowedGuests }}"
            >

            <button
                type="submit"
                name="status"
                value="attending"
                class="btn w-full bg-[#213B73] text-white"
            >
                {{ $isAttending
                    ? $t['attendance_confirmed']
                    : $t['confirm_button']
                }}
            </button>

            <button
                type="submit"
                name="status"
                value="not_attending"
                formnovalidate
                class="btn mt-3 w-full bg-white text-red-700 ring-1 ring-red-200"
            >
                {{ $t['decline_button'] }}
            </button>
        </form>
    </section>

    <section class="soft-card mt-4 rounded-[28px] p-5">
        <h2 class="section-title">
            {{ $t['invitation_card'] }}
        </h2>

        <p class="mt-1 text-sm muted">
            {{ $t['view_card_help'] }}
        </p>

        @if ($generatedCardUrl)
            <div class="mt-5 overflow-hidden rounded-[24px] bg-slate-50 ring-1 ring-slate-200">
                <img
                    src="{{ $generatedCardUrl }}"
                    alt="Personalized invitation card for {{ $invitee->name }}"
                    class="h-auto w-full object-contain"
                    loading="lazy"
                >
            </div>

            <a
                href="{{ $publicCardUrl ?: $generatedCardUrl }}"
                target="_blank"
                rel="noopener noreferrer"
                class="btn mt-4 w-full bg-[#213B73] text-white"
            >
                View {{ $t['invitation_card'] }}
            </a>
        @else
            <div class="mt-5 rounded-[24px] bg-slate-50 p-5 text-center ring-1 ring-slate-200">
                <p class="font-black text-slate-800">
                    {{ $t['card_preparing'] }}
                </p>

                <p class="mt-1 text-sm muted">
                    {{ $t['check_later'] }}
                </p>
            </div>
        @endif
    </section>

    <section class="soft-card mt-4 rounded-[28px] p-5">
        <h2 class="section-title">
            {{ $t['event_details'] }}
        </h2>

        <div class="details-grid mt-5">
            <div class="detail-tile">
                <p class="detail-label">{{ $t['card_type'] }}</p>
                <p class="detail-value">{{ $cardTypeName }}</p>
            </div>

            <div class="detail-tile">
                <p class="detail-label">{{ $t['allowed_guests'] }}</p>
                <p class="detail-value">{{ $allowedGuests }}</p>
            </div>

            @if ($tableNumber)
                <div class="detail-tile">
                    <p class="detail-label">{{ $t['table'] }}</p>
                    <p class="detail-value">{{ $tableNumber }}</p>
                </div>
            @endif

            @if ($dressCode)
                <div class="detail-tile">
                    <p class="detail-label">{{ $t['dress_code'] }}</p>
                    <p class="detail-value">{{ $dressCode }}</p>
                </div>
            @endif

            <div class="detail-tile sm:col-span-2">
                <p class="detail-label">{{ $t['serial_number'] }}</p>

                <p class="detail-value font-mono tracking-wide">
                    {{ filled($serialNumber) ? $serialNumber : $t['not_assigned'] }}
                </p>
            </div>
        </div>

        @if ($googleMapsLink)
            <a
                href="{{ $googleMapsLink }}"
                target="_blank"
                rel="noopener noreferrer"
                class="btn mt-4 w-full bg-[#FD9618] text-white"
            >
                {{ $t['open_location'] }}
            </a>
        @endif
    </section>

    @if (
        $showLoveStory
        && filled($loveStory)
    )
        <section class="soft-card mt-4 rounded-[28px] p-5">
            <p class="text-xs font-black uppercase tracking-[0.22em] text-[#FD9618]">
                {{ $t['our_story'] }}
            </p>

            <h2 class="section-title mt-1">
                {{ $t['love_story'] }}
            </h2>

            <div class="mt-4 space-y-3">
                @foreach (preg_split('/\r\n|\r|\n/', $loveStory) as $paragraph)
                    @if (trim($paragraph) !== '')
                        <p class="text-sm font-semibold leading-7 text-slate-600">
                            {{ trim($paragraph) }}
                        </p>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    @if ($showCountdown && $countdownTarget)
        <section class="mt-4 rounded-[28px] bg-[#213B73] p-5 text-white shadow-xl">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black">
                        Countdown
                    </h2>

                    <p class="mt-1 text-sm text-white/65">
                        {{ $t['event_starts_in'] }}
                    </p>
                </div>

                <span class="rounded-2xl bg-[#FD9618] px-3 py-2 text-right text-xs font-black text-white">
                    <span class="block">{{ $formattedDate }}</span>
                    <span class="block text-[11px] text-white/85">
                        {{ $timeDisplay }}
                    </span>
                </span>
            </div>

            <div
                id="countdownBox"
                data-target="{{ $countdownTarget }}"
                class="countdown-grid mt-5"
            >
                <div class="rounded-3xl bg-white/10 p-4 text-center ring-1 ring-white/10">
                    <p id="countdownDays" class="text-2xl font-black">--</p>
                    <p class="mt-1 text-[11px] font-bold text-white/55">{{ $t['days'] }}</p>
                </div>

                <div class="rounded-3xl bg-white/10 p-4 text-center ring-1 ring-white/10">
                    <p id="countdownHours" class="text-2xl font-black">--</p>
                    <p class="mt-1 text-[11px] font-bold text-white/55">{{ $t['hours'] }}</p>
                </div>

                <div class="rounded-3xl bg-white/10 p-4 text-center ring-1 ring-white/10">
                    <p id="countdownMinutes" class="text-2xl font-black">--</p>
                    <p class="mt-1 text-[11px] font-bold text-white/55">{{ $t['minutes'] }}</p>
                </div>
            </div>

            <p
                id="countdownMessage"
                class="mt-4 text-center text-sm font-bold text-white/75"
            ></p>
        </section>
    @endif

    @if ($showOrganizerContact)
        <section class="soft-card mt-4 rounded-[28px] p-5">
            <h2 class="section-title">
                {{ $t['organizer_contact'] }}
            </h2>

            <p class="mt-1 text-sm muted">
                {{ $t['contact_help'] }}
            </p>

            @if ($organizerPhone)
                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <a
                        href="tel:{{ $organizerPhoneClean }}"
                        class="btn bg-[#213B73] text-white"
                        rel="nofollow"
                    >
                        {{ $t['call_organizer'] }}
                    </a>

                    @if ($whatsAppOrganizerUrl)
                        <a
                            href="{{ $whatsAppOrganizerUrl }}"
                            target="_blank"
                            rel="noopener noreferrer nofollow"
                            class="btn bg-emerald-600 text-white"
                        >
                            {{ $t['whatsapp_organizer'] }}
                        </a>
                    @endif
                </div>
            @else
                <div class="mt-5 rounded-[22px] bg-slate-50 p-4 text-sm muted ring-1 ring-slate-100">
                    {{ $t['contact_soon'] }}
                </div>
            @endif
        </section>
    @endif

    <footer class="py-7 text-center">
        <p class="text-xs font-bold text-slate-400">
            {{ $t['powered_by'] }}
            <span class="text-[#213B73]">eLive</span>
            <span class="text-[#FD9618]">Card</span>
        </p>
    </footer>
</main>

<script>
    (function () {
        const countdownBox = document.getElementById('countdownBox');
        const message = document.getElementById('countdownMessage');

        if (!countdownBox || !message) {
            return;
        }

        const targetValue = countdownBox.dataset.target;
        const daysEl = document.getElementById('countdownDays');
        const hoursEl = document.getElementById('countdownHours');
        const minutesEl = document.getElementById('countdownMinutes');

        if (!targetValue) {
            message.textContent = 'Event date will be shared soon.';
            return;
        }

        const target = new Date(targetValue).getTime();

        function twoDigits(value) {
            return String(value).padStart(2, '0');
        }

        function updateCountdown() {
            const distance = target - Date.now();

            if (Number.isNaN(target)) {
                message.textContent = 'Event date will be shared soon.';
                return;
            }

            if (distance <= 0) {
                daysEl.textContent = '00';
                hoursEl.textContent = '00';
                minutesEl.textContent = '00';
                message.textContent = 'This event has started or ended.';
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor(
                (distance / (1000 * 60 * 60)) % 24
            );
            const minutes = Math.floor(
                (distance / (1000 * 60)) % 60
            );

            daysEl.textContent = twoDigits(days);
            hoursEl.textContent = twoDigits(hours);
            minutesEl.textContent = twoDigits(minutes);
            message.textContent = 'We look forward to seeing you.';
        }

        updateCountdown();
        setInterval(updateCountdown, 60000);
    })();
</script>
</body>
</html>
