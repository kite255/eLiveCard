<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Access Denied | eLive Card</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])

    <style>
        :root {
            --elive-blue: #213B73;
            --elive-orange: #FD9618;
            --elive-text: #111827;
            --elive-background: #F8FAFC;
            --elive-white: #FFFFFF;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            color: var(--elive-text);
            background: var(--elive-background);
        }

        .error-page {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 32px 20px;
            position: relative;
            overflow: hidden;
        }

        .error-page::before {
            content: "";
            position: absolute;
            width: 360px;
            height: 360px;
            border-radius: 999px;
            background: rgba(33, 59, 115, 0.08);
            top: -170px;
            right: -120px;
        }

        .error-page::after {
            content: "";
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 999px;
            background: rgba(253, 150, 24, 0.09);
            bottom: -160px;
            left: -100px;
        }

        .error-card {
            width: min(100%, 680px);
            position: relative;
            z-index: 1;
            background: var(--elive-white);
            border: 1px solid #E5E7EB;
            border-radius: 28px;
            padding: 48px;
            text-align: center;
            box-shadow:
                0 30px 70px rgba(15, 23, 42, 0.10);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 32px;
        }

        .brand-mark {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            background: var(--elive-blue);
            color: var(--elive-white);
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .brand-name {
            font-size: 22px;
            font-weight: 800;
            color: var(--elive-blue);
            letter-spacing: -0.03em;
        }

        .icon-wrapper {
            width: 92px;
            height: 92px;
            margin: 0 auto 26px;
            border-radius: 28px;
            display: grid;
            place-items: center;
            background: #FFF7ED;
            border: 1px solid #FED7AA;
            color: var(--elive-orange);
        }

        .icon-wrapper svg {
            width: 44px;
            height: 44px;
        }

        .error-code {
            margin: 0 0 8px;
            font-size: 15px;
            font-weight: 800;
            color: var(--elive-orange);
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .title {
            margin: 0;
            font-size: clamp(32px, 6vw, 48px);
            line-height: 1.08;
            color: var(--elive-blue);
            letter-spacing: -0.04em;
        }

        .message {
            max-width: 520px;
            margin: 20px auto 0;
            color: #64748B;
            font-size: 16px;
            line-height: 1.75;
        }

        .role-note {
            margin: 26px auto 0;
            padding: 16px 18px;
            max-width: 520px;
            border-radius: 16px;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            color: #475569;
            font-size: 14px;
            line-height: 1.6;
        }

        .actions {
            margin-top: 32px;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .button {
            min-height: 48px;
            padding: 0 22px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            transition:
                transform 160ms ease,
                box-shadow 160ms ease,
                background 160ms ease;
        }

        .button:hover {
            transform: translateY(-1px);
        }

        .button-primary {
            background: var(--elive-blue);
            color: var(--elive-white);
            box-shadow:
                0 12px 26px rgba(33, 59, 115, 0.20);
        }

        .button-primary:hover {
            background: #1B3264;
        }

        .button-secondary {
            background: var(--elive-white);
            color: var(--elive-blue);
            border: 1px solid #CBD5E1;
        }

        .button-secondary:hover {
            background: #F8FAFC;
        }

        .footer {
            margin-top: 34px;
            padding-top: 24px;
            border-top: 1px solid #E5E7EB;
            color: #94A3B8;
            font-size: 13px;
        }

        @media (max-width: 640px) {
            .error-card {
                padding: 34px 22px;
                border-radius: 22px;
            }

            .actions {
                flex-direction: column;
            }

            .button {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    @php
        $user = auth()->user();

        $dashboardUrl = url('/admin');

        if ($user?->isCheckInOfficer()) {
            $dashboardUrl = url('/admin/gate-check-in');
        } elseif ($user?->isEventAdmin()) {
            $dashboardUrl = \App\Filament\Resources\EventResource::getUrl();
        }
    @endphp

    <main class="error-page">
        <section class="error-card">
            <div class="brand">
                <div class="brand-mark">
                    eL
                </div>

                <div class="brand-name">
                    eLive Card
                </div>
            </div>

            <div class="icon-wrapper">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.7"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M16.5 10.5V6.75a4.5 4.5 0 0 0-9 0v3.75m-.75 0h10.5A2.25 2.25 0 0 1 19.5 12.75v6A2.25 2.25 0 0 1 17.25 21H6.75A2.25 2.25 0 0 1 4.5 18.75v-6A2.25 2.25 0 0 1 6.75 10.5Z"
                    />
                </svg>
            </div>

            <p class="error-code">
                Error 403
            </p>

            <h1 class="title">
                Access denied
            </h1>

            <p class="message">
                You do not have permission to access this page or event.
                Your account may not be assigned to this event, or your
                current role does not include this feature.
            </p>

            @auth
                <div class="role-note">
                    Signed in as
                    <strong>{{ $user->name }}</strong>

                    @if (method_exists($user, 'roleLabel'))
                        with the
                        <strong>{{ $user->roleLabel() }}</strong>
                        role.
                    @endif
                </div>
            @endauth

            <div class="actions">
                <a
                    href="{{ $dashboardUrl }}"
                    class="button button-primary"
                >
                    Return to Dashboard
                </a>

                <button
                    type="button"
                    class="button button-secondary"
                    onclick="history.back()"
                >
                    Go Back
                </button>
            </div>

            <div class="footer">
                eLive Card · Secure digital invitations and event check-in
            </div>
        </section>
    </main>
</body>
</html>