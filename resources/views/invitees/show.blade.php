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

    $formattedDate = $eventDate ? Carbon::parse($eventDate)->format('d M Y') : 'Date will be shared';
    $formattedTime = $eventTime ? Carbon::parse($eventTime)->format('h:i A') : 'Time will be shared';
    $formattedEndTime = $eventEndTime ? Carbon::parse($eventEndTime)->format('h:i A') : null;
    $timeDisplay = $formattedEndTime ? $formattedTime . ' - ' . $formattedEndTime : $formattedTime;

    $venue = $event?->venue_name
        ?? $event?->venue
        ?? $event?->location
        ?? 'Venue will be shared';

    $venueAddress = $event?->venue_address ?? null;
    $dressCode = $event?->dress_code ?? null;
    $googleMapsLink = $event?->google_maps_link ?? $event?->map_link ?? null;

    $cardTypeName = $invitee->cardType->name ?? $invitee->card_type ?? $invitee->card_type_name ?? 'Invitation';

    $allowedGuests = (int) (
        $allowedGuests
        ?? $invitee->final_allowed_guests
        ?? $invitee->allowed_guests
        ?? $invitee->cardType->allowed_guests
        ?? $invitee->cardType->allowed_people
        ?? 1
    );

    $allowedGuests = max(1, $allowedGuests);

    $savedConfirmedGuests = (int) ($invitee->confirmed_guests ?? 0);

    $guestSummaryLabel = match ($invitee->rsvp_status ?? 'pending') {
        'attending', 'confirmed' => $allowedGuests . ' / ' . $allowedGuests . ' guest(s)',
        'not_attending', 'declined' => '0 / ' . $allowedGuests . ' guest(s)',
        default => 'Not confirmed',
    };

    $isAttending = in_array($invitee->rsvp_status ?? 'pending', ['attending', 'confirmed'], true);

    $tableNumber = $invitee->table_number ?? null;
    $serialNumber = $invitee->serial_number ?? null;

    $rsvpStatus = $invitee->rsvp_status ?? 'pending';

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

    $eventDateTime = null;

    if ($eventDate) {
        try {
            $eventDateTime = Carbon::parse($eventDate);

            if ($eventTime) {
                $time = Carbon::parse($eventTime);
                $eventDateTime->setTime($time->hour, $time->minute, 0);
            }
        } catch (\Throwable $e) {
            $eventDateTime = null;
        }
    }

    $countdownTarget = $eventDateTime ? $eventDateTime->toIso8601String() : null;

    $generatedCardUrl = $generatedCardUrl ?? null;

    if (! $generatedCardUrl && isset($invitee->generated_card_url) && filled($invitee->generated_card_url)) {
        $generatedCardUrl = $invitee->generated_card_url;
    }

    if (! $generatedCardUrl && filled($invitee->generated_card_path ?? null)) {
        $generatedCardUrl = Storage::disk('public')->url($invitee->generated_card_path);
    }

    if (! $generatedCardUrl && filled($invitee->card_path ?? null)) {
        $generatedCardUrl = Storage::disk('public')->url($invitee->card_path);
    }

    if (! $generatedCardUrl && method_exists($invitee, 'generatedCards')) {
        $latestGeneratedCard = $invitee->generatedCards()
            ->whereNotNull('file_path')
            ->latest()
            ->first();

        if ($latestGeneratedCard && filled($latestGeneratedCard->file_path)) {
            $generatedCardUrl = Storage::disk('public')->url($latestGeneratedCard->file_path);
        }
    }

    $publicCardUrl = $generatedCardUrl;
    $downloadCardUrl = $generatedCardUrl;

    if ($serialNumber && Route::has('public.card.show')) {
        $publicCardUrl = route('public.card.show', $serialNumber);
    }

    if ($serialNumber && Route::has('public.card.download')) {
        $downloadCardUrl = route('public.card.download', $serialNumber);
    }

    $canViewCard = filled($publicCardUrl) || filled($generatedCardUrl);
    $canDownloadCard = filled($downloadCardUrl) || filled($generatedCardUrl);

    $programItems = $programItems ?? [];

    if (empty($programItems) && $event && filled($event->program ?? null)) {
        $programItems = collect(preg_split('/\r\n|\r|\n/', $event->program))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    if (empty($programItems)) {
        $programItems = [
            'Guest Arrival',
            'Opening Prayer',
            'Welcome Remarks',
            'Main Ceremony',
            'Photos',
            'Closing',
        ];
    }

    $organizerPhone = $organizerPhone
        ?? $event->organizer_phone
        ?? $event->contact_phone
        ?? $event->phone
        ?? config('services.elive.contact_phone')
        ?? null;

    $organizerPhoneClean = $organizerPhone ? preg_replace('/\D+/', '', $organizerPhone) : null;
    $whatsAppOrganizerUrl = $whatsAppOrganizerUrl ?? ($organizerPhoneClean ? 'https://wa.me/' . $organizerPhoneClean : null);

    $coverImageUrl = $coverImageUrl ?? null;

    if (! $coverImageUrl && $event && filled($event->cover_image ?? null)) {
        $coverImageUrl = Storage::disk('public')->url($event->cover_image);
    }

    $showCoverImage = (bool) (
        $showCoverImage
        ?? $event?->show_cover_image
        ?? true
    );

    $showLoveStory = (bool) (
        $showLoveStory
        ?? $event?->show_love_story
        ?? false
    );

    $showProgram = (bool) (
        $showProgram
        ?? $event?->show_program
        ?? true
    );

    $showCountdown = (bool) (
        $showCountdown
        ?? $event?->show_countdown
        ?? true
    );

    $showWishes = (bool) (
        $showWishes
        ?? $event?->show_wishes
        ?? true
    );

    $showPhotoUpload = (bool) (
        $showPhotoUpload
        ?? $event?->show_photo_upload
        ?? true
    );

    $showOrganizerContact = (bool) (
        $showOrganizerContact
        ?? $event?->show_organizer_contact
        ?? true
    );

    $logoUrl = asset('images/elive-cardw-logo.png');

    $canSubmitWish = $showWishes
        && Route::has('invitee.wish');

    $canSubmitPhoto = $showPhotoUpload
        && Route::has('invitee.photo');

    $approvedWishes = collect($approvedWishes ?? []);
    $myWishes = collect($myWishes ?? []);
    $approvedPhotos = collect($approvedPhotos ?? []);
    $myPhotos = collect($myPhotos ?? []);
@endphp

<!DOCTYPE html>
{{-- eLive professional invitee page v3 loaded --}}
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $eventName }} - Invitation</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#213B73">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="description" content="Private invitation for {{ $eventName }}">

    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        :root {
            --elive-blue: #213B73;
            --elive-orange: #FD9618;
            --elive-dark: #111827;
            --elive-bg: #F8FAFC;
            --elive-border: #E5E7EB;
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

        .brand-blue { color: var(--elive-blue); }
        .brand-orange { color: var(--elive-orange); }
        .bg-brand-blue { background: var(--elive-blue); }
        .bg-brand-orange { background: var(--elive-orange); }

        .page-shell {
            width: min(100%, 760px);
            margin: 0 auto;
        }

        .safe-x {
            padding-left: max(1rem, env(safe-area-inset-left));
            padding-right: max(1rem, env(safe-area-inset-right));
        }

        .hero-meta-grid,
        .details-grid,
        .countdown-grid {
            display: grid;
            gap: 0.75rem;
        }

        .hero-meta-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .details-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .countdown-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .full-span {
            grid-column: 1 / -1;
        }

        .soft-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.95);
            box-shadow: 0 14px 35px rgba(15, 23, 42, 0.07);
        }

        .hero-card {
            background: var(--elive-blue);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 48px;
            border-radius: 18px;
            padding: 0.75rem 1rem;
            font-size: 0.92rem;
            font-weight: 800;
            transition: transform 0.16s ease, opacity 0.16s ease, box-shadow 0.16s ease;
        }

        .btn:active {
            transform: scale(0.985);
        }

        .btn:focus-visible,
        a:focus-visible,
        button:focus-visible,
        input:focus-visible,
        textarea:focus-visible,
        select:focus-visible {
            outline: 3px solid rgba(253, 150, 24, 0.45);
            outline-offset: 3px;
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
        }

        .section-title {
            font-size: 1.05rem;
            line-height: 1.3;
            font-weight: 900;
            color: var(--elive-blue);
            letter-spacing: -0.02em;
        }

        .muted {
            color: #64748B;
        }

        .detail-tile {
            background: #F8FAFC;
            border: 1px solid #EEF2F7;
            border-radius: 20px;
            padding: 1rem;
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

        .sticky-rsvp {
            position: sticky;
            top: 0;
            z-index: 30;
            backdrop-filter: blur(14px);
            background: rgba(248, 250, 252, 0.88);
            border-bottom: 1px solid rgba(226, 232, 240, 0.85);
        }

        .rsvp-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.65rem;
        }

        @media (max-width: 520px) {
            .sticky-rsvp .page-shell {
                gap: 0.75rem;
            }

            .hero-card {
                border-radius: 26px;
                padding: 1.5rem 1rem;
            }

            .hero-card h1 {
                font-size: clamp(1.65rem, 8vw, 2.25rem);
            }

            .soft-card {
                border-radius: 24px;
            }

            .detail-tile {
                padding: 0.85rem;
                border-radius: 18px;
            }

            .btn {
                width: 100%;
                min-height: 46px;
                border-radius: 16px;
            }
        }

        @media (max-width: 390px) {
            .hero-meta-grid,
            .details-grid {
                grid-template-columns: 1fr;
            }

            .countdown-grid {
                gap: 0.45rem;
            }

            .countdown-grid > div {
                padding: 0.75rem 0.45rem;
                border-radius: 18px;
            }

            .countdown-grid p:first-child {
                font-size: 1.3rem;
            }
        }

        @media (min-width: 640px) {
            .page-shell {
                width: min(100%, 720px);
            }

            .hero-card {
                padding: 2.25rem;
            }

            .section-title {
                font-size: 1.22rem;
            }
        }

        @media (min-width: 430px) {
            .rsvp-grid {
                grid-template-columns: 1fr 1fr;
            }

            .rsvp-grid form:first-child {
                grid-column: span 2;
            }
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.85rem;
        }

        .gallery-card {
            overflow: hidden;
            border-radius: 22px;
            border: 1px solid #E5E7EB;
            background: #FFFFFF;
        }

        .gallery-card img {
            width: 100%;
            aspect-ratio: 1 / 1;
            object-fit: cover;
            display: block;
            transition: transform 0.25s ease;
        }

        .gallery-card:hover img {
            transform: scale(1.035);
        }

        .wish-card {
            border-radius: 22px;
            border: 1px solid #EEF2F7;
            background: #F8FAFC;
            padding: 1rem;
        }

        @media (min-width: 640px) {
            .gallery-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
    </style>
</head>

<body>
<header class="sticky top-0 z-40 border-b border-white/10 bg-[#213B73] shadow-sm">
    <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-3 px-4 py-2.5 sm:px-6 sm:py-3 lg:px-8">
        <div class="flex min-w-0 items-center gap-3 sm:gap-4">
            <a href="#top" class="flex shrink-0 items-center" aria-label="Back to invitation top">
                <img
                    src="{{ $logoUrl }}"
                    alt="eLive Card"
                    class="h-7 w-auto sm:h-8"
                >
            </a>

            <div class="hidden h-7 w-px bg-white/35 sm:block"></div>

            <div class="min-w-0">
                <h1 class="truncate text-lg font-black tracking-tight text-white sm:text-xl">
                    Private Invitation
                </h1>

                <p class="mt-0.5 truncate text-xs font-semibold text-white/70 sm:hidden">
                    {{ $invitee->name }}
                </p>
            </div>
        </div>

        <a href="#rsvp"
           class="inline-flex shrink-0 items-center justify-center rounded-full bg-white/15 px-4 py-2 text-xs font-black text-white ring-1 ring-white/15 transition hover:bg-[#FD9618] active:scale-95 sm:px-5 sm:text-sm">
            RSVP
        </a>
    </div>
</header>

<main id="top" class="page-shell safe-x pb-8 pt-2 sm:pb-12 sm:pt-3">
    {{-- Hero --}}
    <section class="hero-card relative overflow-hidden rounded-[32px] px-5 py-6 text-white shadow-xl sm:py-7">
        <div class="absolute -right-14 -top-14 h-44 w-44 rounded-full bg-white/[0.08]"></div>
        <div class="absolute -bottom-20 -left-20 h-56 w-56 rounded-full bg-[#FD9618]/15"></div>

        <div class="relative text-center">
            <p class="text-xs font-black uppercase tracking-[0.24em] text-[#FD9618]">You are invited</p>
            <h1 class="mt-3 text-2xl font-black leading-tight sm:text-3xl">{{ $eventName }}</h1>
            <p class="mt-3 text-sm font-semibold text-white/78">Hello {{ $invitee->name }}</p>

            <div class="hero-meta-grid mt-5 text-left">
                <div class="rounded-3xl bg-white/10 p-3.5 ring-1 ring-white/10">
                    <p class="text-[11px] font-black uppercase tracking-wide text-white/70">Date</p>
                    <p class="mt-1 text-sm font-black">{{ $formattedDate }}</p>
                </div>

                <div class="rounded-3xl bg-white/10 p-3.5 ring-1 ring-white/10">
                    <p class="text-[11px] font-black uppercase tracking-wide text-white/70">Time</p>
                    <p class="mt-1 text-sm font-black">{{ $timeDisplay }}</p>
                </div>
            </div>

            <div class="mt-4 rounded-3xl bg-white/10 p-4 text-left ring-1 ring-white/10">
                <p class="text-[11px] font-black uppercase tracking-wide text-white/70">Venue</p>
                <p class="mt-1 text-base font-black">{{ $venue }}</p>

                @if ($venueAddress)
                    <p class="mt-1 text-xs font-semibold text-white/65">{{ $venueAddress }}</p>
                @endif
            </div>
        </div>
    </section>

    {{-- Alerts --}}
    <div class="mt-4 space-y-3">
        @if (session('success'))
            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if (session('info'))
            <div class="rounded-3xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-bold text-blue-800">
                {{ session('info') }}
            </div>
        @endif

        @if (session('warning'))
            <div class="rounded-3xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800">
                {{ session('warning') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-3xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-black">Please check the form and try again.</p>

                <ul class="mt-2 list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    {{-- Cover Photo / Welcome Message --}}
    @if (($showCoverImage && $coverImageUrl) || ($event && filled($event->welcome_message ?? null)))
        <section class="soft-card mt-4 overflow-hidden rounded-[28px]">
            @if ($showCoverImage && $coverImageUrl)
                <img
                    src="{{ $coverImageUrl }}"
                    alt="Cover image for {{ $eventName }}"
                    class="h-40 w-full object-cover sm:h-56"
                    loading="eager"
                    decoding="async"
                >
            @endif

            @if ($event && filled($event->welcome_message ?? null))
                <div class="px-5 py-4">
                    <p class="text-xs font-black uppercase tracking-[0.22em] text-[#FD9618]">
                        Welcome
                    </p>

                    <h2 class="section-title mt-1">
                        Welcome Message
                    </h2>

                    <p class="mt-2 whitespace-pre-line text-sm font-semibold leading-6 text-slate-600">
                        {{ $event->welcome_message }}
                    </p>
                </div>
            @endif
        </section>
    @endif

    {{-- RSVP --}}
    <section id="rsvp" class="soft-card mt-4 rounded-[28px] p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.22em] text-[#FD9618]">
                    RSVP
                </p>

                <h2 class="section-title mt-1">Confirm Attendance</h2>

                <p class="mt-1 text-sm leading-6 muted">
                    Confirming attendance automatically reserves all guest places allowed on this invitation.
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
                <p class="text-[11px] font-black uppercase tracking-wide text-slate-500">
                    Allowed Guests
                </p>

                <p class="mt-2 text-2xl font-black text-[#213B73]">
                    {{ $allowedGuests }}
                </p>
            </div>

            <div class="rounded-[22px] bg-slate-50 p-4 ring-1 ring-slate-100">
                <p class="text-[11px] font-black uppercase tracking-wide text-slate-500">
                    Confirmed Guests
                </p>

                <p class="mt-2 text-2xl font-black text-[#111827]">
                    {{ $guestSummaryLabel }}
                </p>
            </div>
        </div>

        @if ($errors->has('status') || $errors->has('rsvp_status'))
            <div class="mt-4 rounded-[20px] border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
                {{ $errors->first('status') ?: $errors->first('rsvp_status') }}
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('invitee.rsvp', $invitee->short_code) }}"
            class="mt-5 space-y-4"
            aria-label="RSVP form"
        >
            @csrf

            <input
                type="hidden"
                name="confirmed_guests"
                value="{{ $allowedGuests }}"
            >

            <div class="rounded-[26px] border border-[#213B73]/10 bg-[#213B73]/[0.03] p-4">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-sm font-black text-slate-900">
                            {{ $isAttending ? 'Attendance Confirmed' : 'Yes, I will attend' }}
                        </p>

                        <p class="mt-1 text-xs font-semibold leading-5 text-slate-500">
                            All {{ $allowedGuests }} {{ $allowedGuests === 1 ? 'guest place' : 'guest places' }}
                            will be confirmed automatically, including you.
                        </p>
                    </div>

                    <span class="w-fit shrink-0 rounded-full bg-[#213B73]/10 px-3 py-1.5 text-[11px] font-black text-[#213B73]">
                        {{ $allowedGuests }} {{ $allowedGuests === 1 ? 'Guest' : 'Guests' }}
                    </span>
                </div>

                <button
                    type="submit"
                    name="status"
                    value="attending"
                    class="btn mt-4 w-full bg-[#213B73] text-white shadow-lg shadow-blue-950/10"
                >
                    {{ $isAttending ? 'Keep Attendance Confirmed' : 'Confirm Attendance' }}
                </button>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <button
                    type="submit"
                    name="status"
                    value="not_attending"
                    formnovalidate
                    class="btn w-full bg-white text-red-700 ring-1 ring-red-200"
                >
                    I will not attend
                </button>

                <button
                    type="submit"
                    name="status"
                    value="pending"
                    formnovalidate
                    class="btn w-full bg-[#FD9618] text-white shadow-lg shadow-orange-500/10"
                >
                    Not sure
                </button>
            </div>
        </form>
    </section>

    {{-- Invitation Card --}}
    <section class="soft-card mt-4 rounded-[28px] p-5">
        <div>
            <h2 class="section-title">Invitation Card</h2>
            <p class="mt-1 text-sm muted">Your personalized card is ready to view or download.</p>
        </div>

        @if ($generatedCardUrl)
            <div class="mt-5 overflow-hidden rounded-[24px] bg-slate-50 ring-1 ring-slate-200">
                <img
                    src="{{ $generatedCardUrl }}"
                    alt="Personalized invitation card for {{ $invitee->name }}"
                    class="h-auto w-full object-contain"
                    loading="lazy"
                    decoding="async"
                >
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                @if ($canViewCard)
                    <a href="{{ $publicCardUrl ?: $generatedCardUrl }}"
                       target="_blank"
                       rel="noopener"
                       class="btn bg-[#213B73] text-white hover:bg-[#1b315f] active:scale-95">
                        View Card
                    </a>
                @else
                    <button type="button"
                            disabled
                            class="btn cursor-not-allowed bg-slate-200 text-slate-500">
                        View Card
                    </button>
                @endif

                @if ($canDownloadCard)
                    <a href="{{ $downloadCardUrl ?: $generatedCardUrl }}"
                       class="btn bg-[#FD9618] text-white hover:bg-[#e28412] active:scale-95"
                       @if (! $serialNumber) download @endif>
                        Download Card
                    </a>
                @else
                    <button type="button"
                            disabled
                            class="btn cursor-not-allowed bg-slate-200 text-slate-500">
                        Download Card
                    </button>
                @endif
            </div>
        @else
            <div class="mt-5 rounded-[24px] bg-slate-50 p-5 text-center ring-1 ring-slate-200">
                <p class="font-black text-slate-800">Your invitation card is being prepared.</p>
                <p class="mt-1 text-sm muted">Please check again later or contact the organizer.</p>
            </div>
        @endif
    </section>

    {{-- Event Details --}}
    <section class="soft-card mt-4 rounded-[28px] p-5">
        <h2 class="section-title">Event Details</h2>

        <div class="details-grid mt-5">
            <div class="detail-tile">
                <p class="detail-label">Date</p>
                <p class="detail-value">{{ $formattedDate }}</p>
            </div>

            <div class="detail-tile">
                <p class="detail-label">Time</p>
                <p class="detail-value">{{ $timeDisplay }}</p>
            </div>

            <div class="detail-tile full-span">
                <p class="detail-label">Venue</p>
                <p class="detail-value">{{ $venue }}</p>

                @if ($venueAddress)
                    <p class="mt-1 text-sm muted">{{ $venueAddress }}</p>
                @endif
            </div>

            <div class="detail-tile">
                <p class="detail-label">Card</p>
                <p class="detail-value">{{ $cardTypeName }}</p>
            </div>

            <div class="detail-tile">
                <p class="detail-label">Guests</p>
                <p class="detail-value">{{ $allowedGuests }}</p>
            </div>

            @if ($tableNumber)
                <div class="detail-tile">
                    <p class="detail-label">Table</p>
                    <p class="detail-value">{{ $tableNumber }}</p>
                </div>
            @endif

            @if ($dressCode)
                <div class="detail-tile">
                    <p class="detail-label">Dress Code</p>
                    <p class="detail-value">{{ $dressCode }}</p>
                </div>
            @endif

            @if ($serialNumber)
                <div class="detail-tile full-span">
                    <p class="detail-label">Serial Number</p>
                    <p class="detail-value">{{ $serialNumber }}</p>
                </div>
            @endif
        </div>

        @if ($googleMapsLink)
            <a
                href="{{ $googleMapsLink }}"
                target="_blank"
                rel="noopener noreferrer"
                class="btn mt-4 w-full bg-[#213B73] text-white"
            >
                Open Venue Location
            </a>
        @endif
    </section>

    {{-- Countdown --}}
    @if ($showCountdown && $countdownTarget)
        <section class="mt-4 rounded-[28px] bg-[#213B73] p-5 text-white shadow-xl shadow-blue-950/10">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black">Countdown</h2>
                    <p class="mt-1 text-sm text-white/65">Event starts in</p>
                </div>

                <span class="rounded-2xl bg-[#FD9618] px-3 py-2 text-right text-xs font-black text-white">
                    <span class="block">{{ $formattedDate }}</span>
                    <span class="mt-0.5 block text-[11px] text-white/85">
                        {{ $timeDisplay }}
                    </span>
                </span>
            </div>

            <div id="countdownBox" data-target="{{ $countdownTarget }}" class="countdown-grid mt-5">
                <div class="rounded-3xl bg-white/10 p-4 text-center ring-1 ring-white/10">
                    <p id="countdownDays" class="text-2xl font-black">--</p>
                    <p class="mt-1 text-[11px] font-bold text-white/55">Days</p>
                </div>

                <div class="rounded-3xl bg-white/10 p-4 text-center ring-1 ring-white/10">
                    <p id="countdownHours" class="text-2xl font-black">--</p>
                    <p class="mt-1 text-[11px] font-bold text-white/55">Hours</p>
                </div>

                <div class="rounded-3xl bg-white/10 p-4 text-center ring-1 ring-white/10">
                    <p id="countdownMinutes" class="text-2xl font-black">--</p>
                    <p class="mt-1 text-[11px] font-bold text-white/55">Minutes</p>
                </div>
            </div>

            <p id="countdownMessage" class="mt-4 text-center text-sm font-bold text-white/75"></p>
        </section>
    @endif

    {{-- Love Story --}}
    @if ($showLoveStory && filled($event->love_story ?? null))
        <section class="soft-card mt-4 rounded-[28px] p-5">
            <p class="text-xs font-black uppercase tracking-[0.22em] text-[#FD9618]">
                Our Story
            </p>

            <h2 class="section-title mt-1">Love Story</h2>

            <div class="mt-4 space-y-3">
                @foreach (preg_split('/\r\n|\r|\n/', $event->love_story) as $paragraph)
                    @if (trim($paragraph) !== '')
                        <p class="text-sm font-semibold leading-7 text-slate-600">
                            {{ trim($paragraph) }}
                        </p>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    {{-- Program --}}
    @if ($showProgram)
        <section class="soft-card mt-4 rounded-[28px] p-5">
            <h2 class="section-title">Program</h2>
            <p class="mt-1 text-sm muted">Event flow for the day.</p>

            <div class="mt-5 space-y-3">
                @forelse ($programItems as $index => $item)
                    <div class="flex items-start gap-3 rounded-[22px] bg-slate-50 p-4 ring-1 ring-slate-100">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#213B73] text-sm font-black text-white">
                            {{ $index + 1 }}
                        </div>

                        <p class="pt-1 font-bold text-slate-800">
                            {{ is_array($item) ? ($item['title'] ?? json_encode($item)) : $item }}
                        </p>
                    </div>
                @empty
                    <div class="rounded-[22px] bg-slate-50 p-4 text-sm muted ring-1 ring-slate-100">
                        Program will be shared soon.
                    </div>
                @endforelse
            </div>
        </section>
    @endif

    {{-- Invitee's Own Wishes --}}
    @if ($showWishes && $myWishes->isNotEmpty())
        <section class="soft-card mt-4 rounded-[28px] p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.22em] text-[#FD9618]">
                        Your Submissions
                    </p>

                    <h2 class="section-title mt-1">
                        Your Wishes
                    </h2>

                    <p class="mt-1 text-sm muted">
                        Pending wishes can be edited before the organizer approves them.
                    </p>
                </div>

                <span class="shrink-0 rounded-full bg-[#213B73]/10 px-3 py-1 text-xs font-black text-[#213B73]">
                    {{ $myWishes->count() }}
                </span>
            </div>

            <div class="mt-5 space-y-4">
                @foreach ($myWishes as $myWish)
                    @php
                        $myWishStatus = $myWish->status ?? 'pending';

                        $myWishStatusLabel = match ($myWishStatus) {
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                            default => 'Pending',
                        };

                        $myWishStatusClasses = match ($myWishStatus) {
                            'approved' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                            'rejected' => 'border-red-200 bg-red-50 text-red-700',
                            default => 'border-orange-200 bg-orange-50 text-orange-700',
                        };
                    @endphp

                    <article class="rounded-[22px] border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-slate-400">
                                    Submitted {{ optional($myWish->created_at)->format('d M Y, h:i A') }}
                                </p>
                            </div>

                            <span class="shrink-0 rounded-full border px-3 py-1 text-[11px] font-black {{ $myWishStatusClasses }}">
                                {{ $myWishStatusLabel }}
                            </span>
                        </div>

                        @if ($myWishStatus === 'pending' && Route::has('invitee.wish.update'))
                            <form
                                method="POST"
                                action="{{ route('invitee.wish.update', [
                                    'shortCode' => $invitee->short_code,
                                    'wish' => $myWish,
                                ]) }}"
                                class="mt-4 space-y-3"
                            >
                                @csrf
                                @method('PUT')

                                <label
                                    for="wish-message-{{ $myWish->id }}"
                                    class="block text-sm font-black text-slate-700"
                                >
                                    Edit Wish
                                </label>

                                <textarea
                                    id="wish-message-{{ $myWish->id }}"
                                    name="message"
                                    rows="4"
                                    required
                                    minlength="3"
                                    maxlength="1000"
                                    class="w-full rounded-[18px] border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 outline-none focus:border-[#213B73] focus:ring-4 focus:ring-[#213B73]/10"
                                >{{ old('message', $myWish->message) }}</textarea>

                                <button
                                    type="submit"
                                    class="btn w-full bg-[#213B73] text-white"
                                >
                                    Save Wish Changes
                                </button>
                            </form>
                        @else
                            <p class="mt-4 whitespace-pre-line text-sm font-semibold leading-7 text-slate-700">
                                {{ $myWish->message }}
                            </p>

                            @if ($myWishStatus === 'approved')
                                <p class="mt-3 text-xs font-bold text-emerald-700">
                                    This wish has been approved and can no longer be edited.
                                </p>
                            @elseif ($myWishStatus === 'rejected')
                                <p class="mt-3 text-xs font-bold text-red-700">
                                    This wish was reviewed and can no longer be edited.
                                </p>
                            @endif
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Wishes --}}
    @if ($showWishes)
        <section class="soft-card mt-4 rounded-[28px] p-5">
            <h2 class="section-title">Send Wishes</h2>
            <p class="mt-1 text-sm muted">Send your message to the organizer. It will be reviewed before public display.</p>

            @if ($canSubmitWish)
                <form method="POST" action="{{ route('invitee.wish', $invitee->short_code) }}" class="mt-5 space-y-4">
                    @csrf

                    <div>
                        <label for="name" class="text-sm font-black text-slate-700">Name</label>

                        <input id="name"
                               type="text"
                               name="name"
                               value="{{ old('name', $invitee->name) }}"
                               class="mt-2 w-full rounded-[20px] border border-slate-200 bg-white px-4 py-3 text-sm font-semibold outline-none focus:ring-4 focus:ring-[#213B73]/15"
                               placeholder="Your name"
                               autocomplete="name">
                    </div>

                    <div>
                        <label for="message" class="text-sm font-black text-slate-700">Message</label>

                        <textarea id="message"
                                  name="message"
                                  rows="4"
                                  required
                                  minlength="3"
                                  maxlength="1000"
                                  class="mt-2 w-full rounded-[20px] border border-slate-200 bg-white px-4 py-3 text-sm font-semibold outline-none focus:ring-4 focus:ring-[#213B73]/15"
                                  placeholder="Write your wishes here...">{{ old('message') }}</textarea>
                    </div>

                    <button type="submit" class="btn w-full bg-[#FD9618] text-white">
                        Submit Wishes
                    </button>
                </form>
            @else
                <div class="mt-5 rounded-[22px] bg-slate-50 p-4 text-sm muted ring-1 ring-slate-100">
                    Wishes submission will be enabled soon.
                </div>
            @endif
        </section>
    @endif

    {{-- Approved Wishes --}}
    @if ($showWishes && $approvedWishes->isNotEmpty())
        <section class="soft-card mt-4 rounded-[28px] p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.22em] text-[#FD9618]">
                        Messages from Guests
                    </p>

                    <h2 class="section-title mt-1">
                        Approved Wishes
                    </h2>

                    <p class="mt-1 text-sm muted">
                        Wishes reviewed and approved by the event organizer.
                    </p>
                </div>

                <span class="shrink-0 rounded-full bg-[#213B73]/10 px-3 py-1 text-xs font-black text-[#213B73]">
                    {{ $approvedWishes->count() }}
                </span>
            </div>

            <div class="mt-5 space-y-3">
                @foreach ($approvedWishes as $wish)
                    <article class="wish-card">
                        <p class="whitespace-pre-line text-sm font-semibold leading-7 text-slate-700">
                            “{{ $wish->message }}”
                        </p>

                        <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-3">
                            <span class="font-black text-[#213B73]">
                                {{ $wish->display_name ?? $wish->invitee?->name ?? 'Guest' }}
                            </span>

                            <span class="text-xs font-semibold text-slate-400">
                                {{ optional($wish->created_at)->format('d M Y') }}
                            </span>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Invitee's Own Photos --}}
    @if ($showPhotoUpload && $myPhotos->isNotEmpty())
        <section class="soft-card mt-4 rounded-[28px] p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.22em] text-[#FD9618]">
                        Your Submissions
                    </p>

                    <h2 class="section-title mt-1">
                        Your Photos
                    </h2>

                    <p class="mt-1 text-sm muted">
                        Pending photos can be replaced, updated, or deleted before approval.
                    </p>
                </div>

                <span class="shrink-0 rounded-full bg-[#213B73]/10 px-3 py-1 text-xs font-black text-[#213B73]">
                    {{ $myPhotos->count() }}
                </span>
            </div>

            <div class="mt-5 space-y-5">
                @foreach ($myPhotos as $myPhoto)
                    @php
                        $myPhotoStatus = $myPhoto->status ?? 'pending';

                        $myPhotoStatusLabel = match ($myPhotoStatus) {
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                            default => 'Pending',
                        };

                        $myPhotoStatusClasses = match ($myPhotoStatus) {
                            'approved' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                            'rejected' => 'border-red-200 bg-red-50 text-red-700',
                            default => 'border-orange-200 bg-orange-50 text-orange-700',
                        };

                        $myPhotoUrl = $myPhoto->file_url ?? (
                            filled($myPhoto->file_path)
                                ? Storage::disk('public')->url($myPhoto->file_path)
                                : null
                        );
                    @endphp

                    <article class="overflow-hidden rounded-[24px] border border-slate-200 bg-slate-50">
                        @if ($myPhotoUrl)
                            <a
                                href="{{ $myPhotoUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="block overflow-hidden bg-slate-100"
                            >
                                <img
                                    src="{{ $myPhotoUrl }}"
                                    alt="{{ $myPhoto->message ?: 'Your submitted event photo' }}"
                                    class="h-56 w-full object-cover sm:h-72"
                                    loading="lazy"
                                    decoding="async"
                                >
                            </a>
                        @endif

                        <div class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-slate-400">
                                        Submitted {{ optional($myPhoto->created_at)->format('d M Y, h:i A') }}
                                    </p>
                                </div>

                                <span class="shrink-0 rounded-full border px-3 py-1 text-[11px] font-black {{ $myPhotoStatusClasses }}">
                                    {{ $myPhotoStatusLabel }}
                                </span>
                            </div>

                            @if (
                                $myPhotoStatus === 'pending'
                                && Route::has('invitee.photo.update')
                            )
                                <form
                                    method="POST"
                                    action="{{ route('invitee.photo.update', [
                                        'shortCode' => $invitee->short_code,
                                        'photo' => $myPhoto,
                                    ]) }}"
                                    enctype="multipart/form-data"
                                    class="mt-4 space-y-4"
                                >
                                    @csrf
                                    @method('PUT')

                                    <div>
                                        <label
                                            for="photo-caption-{{ $myPhoto->id }}"
                                            class="block text-sm font-black text-slate-700"
                                        >
                                            Edit Caption
                                        </label>

                                        <input
                                            id="photo-caption-{{ $myPhoto->id }}"
                                            type="text"
                                            name="caption"
                                            value="{{ old('caption', $myPhoto->message) }}"
                                            maxlength="255"
                                            class="mt-2 w-full rounded-[18px] border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 outline-none focus:border-[#213B73] focus:ring-4 focus:ring-[#213B73]/10"
                                            placeholder="Optional caption"
                                        >
                                    </div>

                                    <div>
                                        <label
                                            for="replacement-photo-{{ $myPhoto->id }}"
                                            class="block text-sm font-black text-slate-700"
                                        >
                                            Replace Photo
                                        </label>

                                        <input
                                            id="replacement-photo-{{ $myPhoto->id }}"
                                            type="file"
                                            name="photo"
                                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                            class="mt-2 w-full rounded-[18px] border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 outline-none focus:border-[#213B73] focus:ring-4 focus:ring-[#213B73]/10"
                                        >

                                        <p class="mt-2 text-xs font-semibold text-slate-500">
                                            Leave this empty to update only the caption. Maximum file size: 5MB.
                                        </p>
                                    </div>

                                    <button
                                        type="submit"
                                        class="btn w-full bg-[#213B73] text-white"
                                    >
                                        Save Photo Changes
                                    </button>
                                </form>

                                @if (Route::has('invitee.photo.delete'))
                                    <form
                                        method="POST"
                                        action="{{ route('invitee.photo.delete', [
                                            'shortCode' => $invitee->short_code,
                                            'photo' => $myPhoto,
                                        ]) }}"
                                        class="mt-3"
                                        onsubmit="return confirm('Delete this pending photo? This action cannot be undone.');"
                                    >
                                        @csrf
                                        @method('DELETE')

                                        <button
                                            type="submit"
                                            class="btn w-full bg-white text-red-700 ring-1 ring-red-200"
                                        >
                                            Delete Pending Photo
                                        </button>
                                    </form>
                                @endif
                            @else
                                @if (filled($myPhoto->message))
                                    <p class="mt-4 text-sm font-semibold leading-6 text-slate-700">
                                        {{ $myPhoto->message }}
                                    </p>
                                @endif

                                @if ($myPhotoStatus === 'approved')
                                    <p class="mt-3 text-xs font-bold text-emerald-700">
                                        This photo has been approved and can no longer be edited or deleted.
                                    </p>
                                @elseif ($myPhotoStatus === 'rejected')
                                    <p class="mt-3 text-xs font-bold text-red-700">
                                        This photo was reviewed and can no longer be edited or deleted.
                                    </p>
                                @endif
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Photo Upload --}}
    @if ($showPhotoUpload)
        <section class="soft-card mt-4 rounded-[28px] p-5">
            <h2 class="section-title">Upload Photo</h2>
            <p class="mt-1 text-sm muted">
                Share a photo with the organizer. It will be reviewed before public display.
            </p>

            @if ($canSubmitPhoto)
                <form
                    method="POST"
                    action="{{ route('invitee.photo', $invitee->short_code) }}"
                    enctype="multipart/form-data"
                    class="mt-5 space-y-4"
                >
                    @csrf

                    <div>
                        <label for="photo" class="text-sm font-black text-slate-700">
                            Photo
                        </label>

                        <input
                            id="photo"
                            type="file"
                            name="photo"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            required
                            class="mt-2 w-full rounded-[20px] border border-slate-200 bg-white px-4 py-3 text-sm font-semibold outline-none focus:ring-4 focus:ring-[#213B73]/15"
                        >

                        <p class="mt-2 text-xs font-semibold text-slate-500">
                            Accepted formats: JPG, JPEG, PNG, WEBP. Maximum size: 5MB.
                        </p>
                    </div>

                    <div>
                        <label for="caption" class="text-sm font-black text-slate-700">
                            Caption
                        </label>

                        <input
                            id="caption"
                            type="text"
                            name="caption"
                            value="{{ old('caption') }}"
                            maxlength="255"
                            class="mt-2 w-full rounded-[20px] border border-slate-200 bg-white px-4 py-3 text-sm font-semibold outline-none focus:ring-4 focus:ring-[#213B73]/15"
                            placeholder="Optional caption"
                        >
                    </div>

                    <button type="submit" class="btn w-full bg-[#213B73] text-white">
                        Upload Photo
                    </button>
                </form>
            @else
                <div class="mt-5 rounded-[22px] bg-slate-50 p-4 text-sm muted ring-1 ring-slate-100">
                    Photo upload will be enabled soon.
                </div>
            @endif
        </section>
    @endif

    {{-- Approved Photo Gallery --}}
    @if ($showPhotoUpload && $approvedPhotos->isNotEmpty())
        <section class="soft-card mt-4 rounded-[28px] p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.22em] text-[#FD9618]">
                        Shared Memories
                    </p>

                    <h2 class="section-title mt-1">
                        Photo Gallery
                    </h2>

                    <p class="mt-1 text-sm muted">
                        Photos reviewed and approved by the event organizer.
                    </p>
                </div>

                <span class="shrink-0 rounded-full bg-[#213B73]/10 px-3 py-1 text-xs font-black text-[#213B73]">
                    {{ $approvedPhotos->count() }}
                </span>
            </div>

            <div class="gallery-grid mt-5">
                @foreach ($approvedPhotos as $photo)
                    <article class="gallery-card">
                        <a
                            href="{{ $photo->file_url }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="block overflow-hidden bg-slate-100"
                        >
                            <img
                                src="{{ $photo->file_url }}"
                                alt="{{ $photo->message ?: 'Approved event photo' }}"
                                loading="lazy"
                                decoding="async"
                            >
                        </a>

                        @if (filled($photo->message) || filled($photo->display_name ?? null))
                            <div class="p-3">
                                @if (filled($photo->message))
                                    <p class="text-sm font-semibold leading-5 text-slate-700">
                                        {{ $photo->message }}
                                    </p>
                                @endif

                                <div class="mt-2 flex items-center justify-between gap-2">
                                    <span class="text-xs font-black text-[#213B73]">
                                        {{ $photo->display_name ?? $photo->invitee?->name ?? 'Guest' }}
                                    </span>

                                    <span class="text-[11px] font-semibold text-slate-400">
                                        {{ optional($photo->created_at)->format('d M Y') }}
                                    </span>
                                </div>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Organizer Contact --}}
    @if ($showOrganizerContact)
        <section class="soft-card mt-4 rounded-[28px] p-5">
            <h2 class="section-title">Organizer Contact</h2>
            <p class="mt-1 text-sm muted">Need help with this invitation? Contact the organizer.</p>

            @if ($organizerPhone)
                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <a href="tel:{{ $organizerPhoneClean }}" class="btn bg-[#213B73] text-white" rel="nofollow">
                        Call Organizer
                    </a>

                    @if ($whatsAppOrganizerUrl)
                        <a
                            href="{{ $whatsAppOrganizerUrl }}"
                            target="_blank"
                            rel="noopener noreferrer nofollow"
                            class="btn bg-emerald-600 text-white"
                        >
                            WhatsApp Organizer
                        </a>
                    @endif
                </div>
            @else
                <div class="mt-5 rounded-[22px] bg-slate-50 p-4 text-sm muted ring-1 ring-slate-100">
                    Organizer contact will be shared soon.
                </div>
            @endif
        </section>
    @endif

    <footer class="py-6 text-center">
        <p class="text-xs font-bold text-slate-400">
            Powered by <span class="brand-blue">eLive</span> <span class="brand-orange">Card</span>
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
            const now = Date.now();
            const distance = target - now;

            if (Number.isNaN(target)) {
                message.textContent = 'Event date will be shared soon.';
                return;
            }

            if (distance <= 0) {
                daysEl.textContent = '00';
                hoursEl.textContent = '00';
                minutesEl.textContent = '00';

                const today = new Date();
                const eventDate = new Date(target);

                const sameDay = today.getFullYear() === eventDate.getFullYear()
                    && today.getMonth() === eventDate.getMonth()
                    && today.getDate() === eventDate.getDate();

                message.textContent = sameDay ? 'Today is the event day.' : 'This event has ended.';
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance / (1000 * 60 * 60)) % 24);
            const minutes = Math.floor((distance / (1000 * 60)) % 60);

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