@php
    use Illuminate\Support\Facades\Route;

    $pageTitle = trim($__env->yieldContent('title', 'eLive Card'));
    $pageDescription = trim($__env->yieldContent(
        'description',
        'Digital invitations, RSVP management, invitee engagement, QR check-in, and event reporting for social events.'
    ));

    $loginUrl = Route::has('filament.admin.auth.login')
        ? route('filament.admin.auth.login')
        : url('/admin/login');

    $homeUrl = Route::has('home')
        ? route('home')
        : url('/');

    $contactUrl = Route::has('contact')
        ? route('contact')
        : url('/contact');

    $eventsUrl = Route::has('events.index')
        ? route('events.index')
        : url('/events');

    $aboutUrl = Route::has('about')
        ? route('about')
        : url('/about');

    $privacyUrl = Route::has('privacy-policy')
        ? route('privacy-policy')
        : url('/privacy-policy');

    $termsUrl = Route::has('terms')
        ? route('terms')
        : url('/terms');

    $showHomepageHero = request()->routeIs('home') || request()->is('/');
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <title>{{ $pageTitle }}</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#213B73">
    <meta name="description" content="{{ $pageDescription }}">
    <meta name="robots" content="index,follow">

    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">

    @if ($showHomepageHero)
        <link rel="preload" as="image" href="{{ asset('images/hero-BG.gif') }}">
        <meta property="og:image" content="{{ asset('images/hero-BG.gif') }}">
    @endif

    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        :root {
            --elive-blue: #213B73;
            --elive-orange: #FD9618;
            --elive-dark: #111827;
            --elive-bg: #F8FAFC;
            --elive-white: #FFFFFF;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            background: var(--elive-bg);
            color: var(--elive-dark);
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            text-rendering: optimizeLegibility;
        }

        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        a {
            text-decoration: none;
        }

        .container-shell {
            width: min(100% - 2rem, 1180px);
            margin-inline: auto;
        }

        .soft-card {
            background: var(--elive-white);
            border: 1px solid #E5E7EB;
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.07);
        }

        .section-kicker {
            color: var(--elive-orange);
            font-size: 0.75rem;
            font-weight: 900;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .section-title {
            color: var(--elive-blue);
            font-weight: 900;
            letter-spacing: -0.035em;
        }

        .btn {
            display: inline-flex;
            min-height: 48px;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            border-radius: 14px;
            padding: 0.8rem 1.15rem;
            font-weight: 900;
            line-height: 1;
            transition:
                transform .16s ease,
                box-shadow .16s ease,
                background-color .16s ease,
                color .16s ease,
                border-color .16s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn:active {
            transform: translateY(0) scale(.985);
        }

        a:focus-visible,
        button:focus-visible {
            outline: 3px solid rgba(253, 150, 24, .45);
            outline-offset: 3px;
        }

        .homepage-hero {
            position: relative;
            isolation: isolate;
            min-height: clamp(560px, 72vh, 700px);
            overflow: hidden;
            background-color: #07152C;
            background-image: url('{{ asset('images/hero-BG.gif') }}');
            background-position: center;
            background-repeat: no-repeat;
            background-size: cover;
        }

        .homepage-hero::before {
            position: absolute;
            inset: 0;
            z-index: -2;
            content: "";
            background:
                linear-gradient(
                    90deg,
                    rgba(5, 17, 39, .74) 0%,
                    rgba(5, 17, 39, .84) 50%,
                    rgba(5, 17, 39, .74) 100%
                );
        }

        .homepage-hero::after {
            position: absolute;
            inset: auto 0 0;
            z-index: -1;
            height: 42%;
            content: "";
            background: linear-gradient(to top, rgba(5, 17, 39, .72), transparent);
        }

        .hero-glow {
            position: absolute;
            top: 50%;
            left: 8%;
            z-index: -1;
            width: 460px;
            height: 460px;
            border-radius: 9999px;
            background: rgba(33, 59, 115, .34);
            filter: blur(90px);
            transform: translateY(-50%);
            pointer-events: none;
        }

        .hero-title {
            width: 100%;
            max-width: 720px;
            margin-inline: auto;
            font-size: clamp(2.25rem, 4.5vw, 3.7rem);
            line-height: 1.08;
            letter-spacing: -0.038em;
            overflow-wrap: normal;
            word-break: normal;
        }

        .hero-description {
            width: 100%;
            max-width: 650px;
            margin-inline: auto;
        }

        .hero-kicker {
            display: block;
            max-width: min(100%, 420px);
            margin-inline: auto;
            white-space: normal;
            text-align: center;
        }

        .hero-trust-item {
            display: flex;
            align-items: center;
            gap: .65rem;
            color: rgba(255, 255, 255, .78);
            font-size: .82rem;
            font-weight: 700;
        }

        .hero-trust-dot {
            display: inline-flex;
            width: 1.65rem;
            height: 1.65rem;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 9999px;
            background: rgba(255, 255, 255, .10);
            color: #FD9618;
        }

        @media (max-width: 768px) {
            .homepage-hero {
                min-height: 680px;
                background-position: 62% center;
            }

            .homepage-hero::before {
                background:
                    linear-gradient(
                        180deg,
                        rgba(5, 17, 39, .88) 0%,
                        rgba(5, 17, 39, .80) 60%,
                        rgba(5, 17, 39, .92) 100%
                    );
            }

            .hero-glow {
                left: -160px;
                width: 380px;
                height: 380px;
            }

            .hero-title {
                max-width: 94vw;
                font-size: clamp(2.15rem, 10vw, 3.15rem);
                line-height: 1.1;
                letter-spacing: -0.035em;
            }

            .hero-description {
                max-width: 92vw;
            }
        }

        @media (max-width: 640px) {
            .container-shell {
                width: min(100% - 1.25rem, 1180px);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

            *,
            *::before,
            *::after {
                transition-duration: 0.01ms !important;
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
            }
        }
    </style>

    @stack('styles')
</head>

<body>
<header class="sticky top-0 z-50 border-b border-slate-200/90 bg-white/95 backdrop-blur">
    <div class="container-shell flex min-h-[72px] items-center justify-between gap-4">
        <a href="{{ $homeUrl }}" class="flex min-w-0 items-center" aria-label="eLive Card home">
            <img
                src="{{ asset('images/elive-card-logo.png') }}"
                alt="eLive Card"
                class="h-11 w-auto max-w-[180px] object-contain sm:h-12"
            >
        </a>

        <nav class="hidden items-center gap-7 lg:flex" aria-label="Primary navigation">
            <a href="{{ $homeUrl }}#features" class="text-sm font-bold text-slate-600 transition hover:text-[#213B73]">
                Features
            </a>

            <a href="{{ $eventsUrl }}" class="text-sm font-bold text-slate-600 transition hover:text-[#213B73]">
                Events
            </a>

            <a href="{{ $homeUrl }}#events" class="text-sm font-bold text-slate-600 transition hover:text-[#213B73]">
                Supported Events
            </a>

            <a href="{{ $homeUrl }}#how-it-works" class="text-sm font-bold text-slate-600 transition hover:text-[#213B73]">
                How It Works
            </a>

            <a href="{{ $aboutUrl }}" class="text-sm font-bold text-slate-600 transition hover:text-[#213B73]">
                About
            </a>
        </nav>

        <div class="hidden items-center gap-3 lg:flex">
            <a
                href="{{ $loginUrl }}"
                class="text-sm font-bold text-slate-500 transition hover:text-[#213B73]"
            >
                Staff Login
            </a>

            <a
                href="{{ $contactUrl }}"
                class="btn bg-[#FD9618] text-white shadow-lg shadow-orange-950/10 hover:bg-[#e8870f]"
            >
                Contact Us
            </a>
        </div>

        <button
            id="mobileMenuButton"
            type="button"
            class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-[#213B73] transition hover:bg-slate-50 lg:hidden"
            aria-controls="mobileMenu"
            aria-expanded="false"
            aria-label="Open navigation menu"
        >
            <svg id="menuOpenIcon" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h16"/>
            </svg>

            <svg id="menuCloseIcon" class="hidden h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6l12 12M18 6L6 18"/>
            </svg>
        </button>
    </div>

    <div id="mobileMenu" class="hidden border-t border-slate-200 bg-white lg:hidden">
        <nav class="container-shell py-4" aria-label="Mobile navigation">
            <div class="grid gap-1">
                <a href="{{ $homeUrl }}#features" class="rounded-xl px-3 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 hover:text-[#213B73]">
                    Features
                </a>

                <a href="{{ $eventsUrl }}" class="rounded-xl px-3 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 hover:text-[#213B73]">
                    Events
                </a>

                <a href="{{ $homeUrl }}#events" class="rounded-xl px-3 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 hover:text-[#213B73]">
                    Supported Events
                </a>

                <a href="{{ $homeUrl }}#how-it-works" class="rounded-xl px-3 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 hover:text-[#213B73]">
                    How It Works
                </a>

                <a href="{{ $aboutUrl }}" class="rounded-xl px-3 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50 hover:text-[#213B73]">
                    About
                </a>
            </div>

            <div class="mt-4 grid gap-3 border-t border-slate-200 pt-4">
                <a href="{{ $contactUrl }}" class="btn bg-[#FD9618] text-white hover:bg-[#e8870f]">
                    Contact Us
                </a>

                <a href="{{ $loginUrl }}" class="btn border border-slate-200 bg-white text-[#213B73] hover:bg-slate-50">
                    Staff Login
                </a>
            </div>
        </nav>
    </div>
</header>

<main>
    @if ($showHomepageHero)
        <section class="homepage-hero" aria-labelledby="homepage-hero-title">
            <div class="hero-glow" aria-hidden="true"></div>

            <div class="container-shell flex min-h-[inherit] items-center justify-center py-16 text-center sm:py-20 lg:py-24">
                <div class="mx-auto w-full max-w-[780px] px-1 sm:px-0">
                    <p class="hero-kicker text-[11px] font-black uppercase tracking-[0.18em] text-white/90">
                        Digital invitation and guest management
                    </p>

                    <h1
                        id="homepage-hero-title"
                        class="hero-title mt-6 font-black text-white"
                    >
                        Make every invitation personal and every event organized.
                    </h1>

                    <p class="hero-description mt-6 text-base font-medium leading-8 text-white/80 sm:text-lg">
                        Create personalized invitation cards, manage invitees, collect RSVP responses,
                        communicate with guests, and check attendees in securely from one platform.
                    </p>

                    <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row sm:flex-wrap">
                        <a
                            href="{{ $contactUrl }}"
                            class="btn bg-[#FD9618] px-6 text-white shadow-[0_16px_36px_rgba(0,0,0,.28)] hover:bg-[#e8870f]"
                        >
                            Request Our Service

                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5-5 5M6 12h12"/>
                            </svg>
                        </a>

                        <a
                            href="{{ $eventsUrl }}"
                            class="btn border border-white/25 bg-white/[0.08] px-6 text-white backdrop-blur-md hover:border-white/40 hover:bg-white/[0.14]"
                        >
                            View Public Events
                        </a>
                    </div>

                    <div class="mt-8 flex flex-col items-center justify-center gap-4 border-t border-white/15 pt-6 sm:flex-row sm:flex-wrap sm:gap-x-8 sm:gap-y-4">
                        @foreach ([
                            'Personalized invitation cards',
                            'RSVP and guest tracking',
                            'Secure QR check-in',
                        ] as $heroPoint)
                            <div class="hero-trust-item">
                                <span class="hero-trust-dot">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.4" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </span>

                                <span>{{ $heroPoint }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endif

    @yield('content')
</main>

<footer class="border-t border-slate-200 bg-white">
    <div class="container-shell py-12 sm:py-14">
        <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-[1.5fr_0.8fr_0.8fr_1fr]">
            <div class="max-w-md">
                <a href="{{ $homeUrl }}" class="inline-flex items-center" aria-label="eLive Card home">
                    <img
                        src="{{ asset('images/elive-card-logo.png') }}"
                        alt="eLive Card"
                        class="h-10 w-auto max-w-[170px] object-contain"
                    >
                </a>

                <p class="mt-4 text-sm font-medium leading-7 text-slate-600">
                    Digital invitations, RSVP management, invitee engagement,
                    QR check-in, and event reporting for professional social events.
                </p>

                <a
                    href="{{ $contactUrl }}"
                    class="mt-6 inline-flex items-center gap-2 rounded-xl bg-[#FD9618] px-5 py-3 text-sm font-black text-white transition hover:bg-[#e8870f]"
                >
                    Contact Us

                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5-5 5M6 12h12"/>
                    </svg>
                </a>
            </div>

            <div>
                <h3 class="text-xs font-black uppercase tracking-[0.16em] text-[#111827]">
                    Platform
                </h3>

                <nav class="mt-5 flex flex-col gap-3">
                    <a href="{{ $homeUrl }}#features" class="text-sm font-medium text-slate-600 transition hover:text-[#213B73]">
                        Features
                    </a>

                    <a href="{{ $eventsUrl }}" class="text-sm font-medium text-slate-600 transition hover:text-[#213B73]">
                        Events
                    </a>

                    <a href="{{ $homeUrl }}#events" class="text-sm font-medium text-slate-600 transition hover:text-[#213B73]">
                        Supported Events
                    </a>

                    <a href="{{ $homeUrl }}#how-it-works" class="text-sm font-medium text-slate-600 transition hover:text-[#213B73]">
                        How It Works
                    </a>
                </nav>
            </div>

            <div>
                <h3 class="text-xs font-black uppercase tracking-[0.16em] text-[#111827]">
                    Company
                </h3>

                <nav class="mt-5 flex flex-col gap-3">
                    <a href="{{ $aboutUrl }}" class="text-sm font-medium text-slate-600 transition hover:text-[#213B73]">
                        About eLive
                    </a>

                    <a href="{{ $contactUrl }}" class="text-sm font-medium text-slate-600 transition hover:text-[#213B73]">
                        Contact Us
                    </a>

                    <a href="{{ $loginUrl }}" class="text-sm font-medium text-slate-600 transition hover:text-[#213B73]">
                        Staff Login
                    </a>
                </nav>
            </div>

            <div>
                <h3 class="text-xs font-black uppercase tracking-[0.16em] text-[#111827]">
                    Legal
                </h3>

                <nav class="mt-5 flex flex-col gap-3">
                    <a href="{{ $privacyUrl }}" class="text-sm font-medium text-slate-600 transition hover:text-[#213B73]">
                        Privacy Policy
                    </a>

                    <a href="{{ $termsUrl }}" class="text-sm font-medium text-slate-600 transition hover:text-[#213B73]">
                        Terms of Service
                    </a>
                </nav>
            </div>
        </div>
    </div>

    <div class="border-t border-slate-200">
        <div class="container-shell flex flex-col gap-3 py-5 text-center text-xs font-medium text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:text-left">
            <p>
                &copy; {{ now()->year }} eLive Card. All rights reserved.
            </p>

            <p>
                Digital invitations and guest management for social events.
            </p>
        </div>
    </div>
</footer>

<script>
    (function () {
        const button = document.getElementById('mobileMenuButton');
        const menu = document.getElementById('mobileMenu');
        const openIcon = document.getElementById('menuOpenIcon');
        const closeIcon = document.getElementById('menuCloseIcon');

        if (!button || !menu) {
            return;
        }

        function setMenuState(isOpen) {
            menu.classList.toggle('hidden', !isOpen);
            button.setAttribute('aria-expanded', String(isOpen));
            button.setAttribute(
                'aria-label',
                isOpen ? 'Close navigation menu' : 'Open navigation menu'
            );

            openIcon?.classList.toggle('hidden', isOpen);
            closeIcon?.classList.toggle('hidden', !isOpen);
        }

        button.addEventListener('click', function () {
            const isOpen = button.getAttribute('aria-expanded') === 'true';
            setMenuState(!isOpen);
        });

        menu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                setMenuState(false);
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setMenuState(false);
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth >= 1024) {
                setMenuState(false);
            }
        });
    })();
</script>

@stack('scripts')
</body>
</html>
