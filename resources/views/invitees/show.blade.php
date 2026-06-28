@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Facades\Storage;

    $event = $event ?? $invitee->event ?? null;

    $eventName = $event->name ?? $event->title ?? 'eLive Event';
    $eventDate = $event->event_date ?? $event->date ?? null;
    $eventTime = $event->start_time ?? $event->time ?? null;
    $eventEndTime = $event->end_time ?? null;

    $formattedDate = $eventDate ? Carbon::parse($eventDate)->format('d M Y') : 'Date will be shared';
    $formattedTime = $eventTime ? Carbon::parse($eventTime)->format('h:i A') : 'Time will be shared';
    $formattedEndTime = $eventEndTime ? Carbon::parse($eventEndTime)->format('h:i A') : null;
    $timeDisplay = $formattedEndTime ? $formattedTime . ' - ' . $formattedEndTime : $formattedTime;

    $venue = $event->venue_name
        ?? $event->venue
        ?? $event->location
        ?? 'Venue will be shared';

    $venueAddress = $event->venue_address ?? null;
    $dressCode = $event->dress_code ?? null;
    $googleMapsLink = $event->google_maps_link ?? $event->map_link ?? null;

    $cardTypeName = $invitee->cardType->name ?? $invitee->card_type ?? $invitee->card_type_name ?? 'Invitation';

    /*
    |--------------------------------------------------------------------------
    | RSVP Guest Count
    |--------------------------------------------------------------------------
    |
    | The controller may pass $allowedGuests. If not, we safely resolve it from
    | the invitee/card type. This controls how many guests the invitee can confirm.
    |
    */
    $allowedGuests = (int) (
        $allowedGuests
        ?? $invitee->final_allowed_guests
        ?? $invitee->allowed_guests
        ?? $invitee->cardType->allowed_guests
        ?? $invitee->cardType->allowed_people
        ?? 1
    );

    $allowedGuests = max(1, $allowedGuests);

    $confirmedGuests = (int) old(
        'confirmed_guests',
        ((int) ($invitee->confirmed_guests ?? 0) > 0)
            ? (int) $invitee->confirmed_guests
            : min($allowedGuests, 1)
    );

    $confirmedGuests = max(1, min($confirmedGuests, $allowedGuests));

    $savedConfirmedGuests = (int) ($invitee->confirmed_guests ?? 0);

    $guestSummaryLabel = match ($invitee->rsvp_status ?? 'pending') {
        'attending', 'confirmed' => $savedConfirmedGuests > 0
            ? $savedConfirmedGuests . ' / ' . $allowedGuests . ' guest(s)'
            : 'Awaiting guest count',
        'not_attending', 'declined' => '0 / ' . $allowedGuests . ' guest(s)',
        default => 'Not confirmed',
    };

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

    /*
    |--------------------------------------------------------------------------
    | Invitation Card Links
    |--------------------------------------------------------------------------
    |
    | These URLs power the "View Card" and "Download Card" buttons.
    | Priority:
    | 1. Controller-passed generated card URL
    | 2. Invitee accessor generated_card_url
    | 3. Invitee stored paths
    | 4. Latest GeneratedCard relation
    | 5. Public card routes using serial number
    |
    */
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

    $showCoverImage = (bool) ($event->show_cover_image ?? true);
    $showLoveStory = (bool) ($event->show_love_story ?? false);
    $showProgram = (bool) ($event->show_program ?? true);
    $showCountdown = (bool) ($event->show_countdown ?? true);
    $showWishes = (bool) ($event->show_wishes ?? true);
    $showOrganizerContact = (bool) ($event->show_organizer_contact ?? true);

    $logoUrl = asset('images/elive-card-logo.png');
    $canSubmitWish = Route::has('invitee.wish');
@endphp

<!DOCTYPE html>
{{-- eLive professional invitee page v2 loaded --}}
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $eventName }} - Invitation</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#213B73">

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
            background:
                radial-gradient(circle at top left, rgba(253, 150, 24, 0.14), transparent 28%),
                radial-gradient(circle at top right, rgba(33, 59, 115, 0.14), transparent 28%),
                var(--elive-bg);
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
            background:
                linear-gradient(135deg, rgba(33, 59, 115, 0.98), rgba(33, 59, 115, 0.9)),
                radial-gradient(circle at bottom right, rgba(253, 150, 24, 0.5), transparent 34%);
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
            body {
                background:
                    radial-gradient(circle at top left, rgba(253, 150, 24, 0.10), transparent 30%),
                    var(--elive-bg);
            }

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
    </style>
</head>

<body>
<header class="sticky top-0 z-40 border-b border-white/10 bg-[#213B73] shadow-sm">
    <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-3 px-4 py-2.5 sm:px-6 sm:py-3 lg:px-8">
        {{-- Brand + Page Title --}}
        <div class="flex min-w-0 items-center gap-3 sm:gap-4">
            <a href="{{ route('invitee.page', $invitee->short_code) }}" class="flex shrink-0 items-center">
                <img
                    src="{{ asset('images/elive-cardw-logo.png') }}"
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

        {{-- RSVP Action --}}
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
        @if (($showCoverImage && $coverImageUrl) || filled($event->welcome_message ?? null))
            <section class="soft-card mt-4 overflow-hidden rounded-[28px]">
                @if ($showCoverImage && $coverImageUrl)
                    <img src="{{ $coverImageUrl }}"
                        alt="{{ $eventName }}"
                        class="h-56 w-full object-cover sm:h-80">
                @endif

                @if (filled($event->welcome_message ?? null))
                    <div class="p-5">
                        <p class="text-xs font-black uppercase tracking-[0.22em] text-[#FD9618]">
                            Welcome
                        </p>
                        <h2 class="section-title mt-1">A Special Invitation</h2>
                        <p class="mt-3 whitespace-pre-line text-sm font-semibold leading-7 text-slate-600">
                            {{ $event->welcome_message }}
                        </p>
                    </div>
                @endif
            </section>
        @endif

        {{-- RSVP --}}
        <section id="rsvp" class="soft-card mt-4 rounded-[28px] p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="section-title">Confirm Attendance</h2>
                    <p class="mt-1 text-sm muted">Please confirm if you will attend and the number of guests coming.</p>
                </div>

                <span class="shrink-0 rounded-full border px-3 py-1 text-[11px] font-black" style="{{ $rsvpColorClass }}">
                    {{ $rsvpLabel }}
                </span>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 rounded-[24px] bg-slate-50 p-4 ring-1 ring-slate-100 sm:grid-cols-2">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-wide text-slate-500">Allowed Guests</p>
                    <p class="mt-1 text-lg font-black text-[#213B73]">{{ $allowedGuests }}</p>
                </div>

                <div>
                    <p class="text-[11px] font-black uppercase tracking-wide text-slate-500">Confirmed Guests</p>
                    <p class="mt-1 text-lg font-black text-[#111827]">{{ $guestSummaryLabel }}</p>
                </div>
            </div>

            @if ($errors->has('status') || $errors->has('confirmed_guests'))
                <div class="mt-4 rounded-[20px] border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
                    {{ $errors->first('confirmed_guests') ?: $errors->first('status') }}
                </div>
            @endif

            {{-- 
                Important:
                Keep all RSVP actions inside one POST form.
                Do not use links for RSVP actions because GET /i/{shortCode}/rsvp does not exist.
            --}}
            <form method="POST" action="{{ route('invitee.rsvp', $invitee->short_code) }}" class="mt-5 space-y-4">
                @csrf

                <div class="rounded-[26px] border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-black text-slate-900">Yes, I will attend</p>
                            <p class="mt-1 text-xs font-semibold text-slate-500">
                                Select how many guests will attend, including you.
                            </p>
                        </div>

                        <span class="rounded-full bg-[#213B73]/10 px-3 py-1 text-[11px] font-black text-[#213B73]">
                            Max {{ $allowedGuests }}
                        </span>
                    </div>

                    @if ($allowedGuests <= 6)
                        <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @for ($guestNumber = 1; $guestNumber <= $allowedGuests; $guestNumber++)
                                <label class="cursor-pointer">
                                    <input
                                        type="radio"
                                        name="confirmed_guests"
                                        value="{{ $guestNumber }}"
                                        class="peer sr-only"
                                        @checked((int) old('confirmed_guests', $confirmedGuests ?: 1) === (int) $guestNumber)
                                    >

                                    <span class="flex h-12 items-center justify-center rounded-[18px] border border-slate-200 bg-white text-sm font-black text-slate-700 transition peer-checked:border-[#213B73] peer-checked:bg-[#213B73] peer-checked:text-white">
                                        {{ $guestNumber }}
                                    </span>
                                </label>
                            @endfor
                        </div>
                    @else
                        <div class="mt-4">
                            <label for="confirmed_guests" class="mb-2 block text-xs font-black uppercase tracking-wide text-slate-500">
                                Select number of guests
                            </label>

                            <select
                                id="confirmed_guests"
                                name="confirmed_guests"
                                class="w-full rounded-[18px] border border-slate-200 bg-white px-4 py-4 text-base font-black text-[#111827] outline-none transition focus:border-[#213B73] focus:ring-4 focus:ring-[#213B73]/10"
                            >
                                @for ($guestNumber = 1; $guestNumber <= $allowedGuests; $guestNumber++)
                                    <option
                                        value="{{ $guestNumber }}"
                                        @selected((int) old('confirmed_guests', $confirmedGuests ?: 1) === (int) $guestNumber)
                                    >
                                        {{ $guestNumber }} {{ $guestNumber === 1 ? 'Guest' : 'Guests' }}
                                    </option>
                                @endfor
                            </select>

                            <p class="mt-2 text-xs font-semibold text-slate-500">
                                Choose a number from 1 to {{ $allowedGuests }}. You cannot exceed your invitation limit.
                            </p>
                        </div>
                    @endif

                    <button
                        type="submit"
                        name="status"
                        value="attending"
                        class="btn mt-4 w-full bg-[#213B73] text-white shadow-lg shadow-blue-950/10"
                    >
                        Confirm Attendance
                    </button>
                </div>

                <div class="rsvp-grid">
                    <button
                        type="submit"
                        name="status"
                        value="not_attending"
                        formnovalidate
                        class="btn w-full bg-white text-red-700 ring-1 ring-red-100"
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
                    <img src="{{ $generatedCardUrl }}" alt="Invitation Card" class="h-auto w-full object-cover" loading="lazy">
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
                <a href="{{ $googleMapsLink }}" target="_blank" class="btn mt-4 w-full bg-[#213B73] text-white">
                    Open Venue Location
                </a>
            @endif
        </section>

        {{-- Countdown --}}
        @if ($showCountdown)
        <section class="mt-4 rounded-[28px] bg-[#213B73] p-5 text-white shadow-xl shadow-blue-950/10">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black">Countdown</h2>
                    <p class="mt-1 text-sm text-white/65">Event starts in</p>
                </div>
                <span class="rounded-2xl bg-[#FD9618] px-3 py-2 text-xs font-black text-white">
                    {{ $formattedDate }}
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
                        <p class="pt-1 font-bold text-slate-800">{{ $item }}</p>
                    </div>
                @empty
                    <div class="rounded-[22px] bg-slate-50 p-4 text-sm muted ring-1 ring-slate-100">
                        Program will be shared soon.
                    </div>
                @endforelse
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
                        <input id="name" type="text" name="name" value="{{ old('name', $invitee->name) }}"
                            class="mt-2 w-full rounded-[20px] border border-slate-200 bg-white px-4 py-3 text-sm font-semibold outline-none focus:ring-4 focus:ring-[#213B73]/15"
                            placeholder="Your name">
                    </div>

                    <div>
                        <label for="message" class="text-sm font-black text-slate-700">Message</label>
                        <textarea id="message" name="message" rows="4" required
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

        {{-- Organizer Contact --}}
        @if ($showOrganizerContact)
        <section class="soft-card mt-4 rounded-[28px] p-5">
            <h2 class="section-title">Organizer Contact</h2>
            <p class="mt-1 text-sm muted">Need help with this invitation? Contact the organizer.</p>

            @if ($organizerPhone)
                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <a href="tel:{{ $organizerPhoneClean }}" class="btn bg-[#213B73] text-white">
                        Call Organizer
                    </a>

                    @if ($whatsAppOrganizerUrl)
                        <a href="{{ $whatsAppOrganizerUrl }}" target="_blank" class="btn bg-emerald-600 text-white">
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
