<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invitee->name }} Invitation Card</title>

    <style>
        :root {
            --elive-blue: #213B73;
            --elive-orange: #FD9618;
            --dark-text: #111827;
            --soft-bg: #F4F1EA;
            --white: #FFFFFF;
            --muted: #64748B;
            --border: #E5E7EB;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--soft-bg);
            color: var(--dark-text);
        }

        .page {
            min-height: 100vh;
            padding: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
        }

        .wrapper {
            width: 100%;
            max-width: 560px;
            background: var(--white);
            border-radius: 22px;
            padding: 24px;
            box-shadow: 0 18px 45px rgba(17, 24, 39, 0.10);
            text-align: center;
            box-sizing: border-box;
            border: 1px solid var(--border);
        }

        .logo {
            max-width: 210px;
            height: auto;
            margin-bottom: 18px;
        }

        h1 {
            margin: 0;
            color: var(--elive-blue);
            font-size: 23px;
            line-height: 1.3;
        }

        .subtitle {
            margin: 8px 0 18px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .meta {
            margin: 0 auto 18px;
            display: grid;
            gap: 8px;
            max-width: 420px;
            text-align: left;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 12px;
            background: #F8FAFC;
            font-size: 13px;
        }

        .meta-row span {
            color: var(--muted);
        }

        .meta-row strong {
            color: var(--dark-text);
            text-align: right;
        }

        .card-image {
            width: 100%;
            border-radius: 16px;
            border: 1px solid var(--border);
            margin: 18px 0;
            display: block;
            background: #F8FAFC;
        }

        .button {
            display: block;
            width: 100%;
            padding: 14px 18px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 800;
            box-sizing: border-box;
            margin-top: 12px;
            font-size: 14px;
        }

        .download {
            background: var(--elive-blue);
            color: var(--white);
        }

        .view {
            background: var(--white);
            color: var(--elive-orange);
            border: 1px solid var(--elive-orange);
        }

        .note {
            margin: 16px 0 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        @media (max-width: 520px) {
            .page {
                padding: 14px;
            }

            .wrapper {
                padding: 18px;
                border-radius: 18px;
            }

            h1 {
                font-size: 20px;
            }

            .meta-row {
                display: block;
                text-align: center;
            }

            .meta-row strong {
                display: block;
                margin-top: 4px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="wrapper">
            @if (file_exists(public_path('images/elive-card-logo.png')))
                <img src="{{ asset('images/elive-card-logo.png') }}" class="logo" alt="eLive Card">
            @endif

            <h1>{{ $invitee->event?->name ?? 'Invitation Card' }}</h1>

            <p class="subtitle">
                Hello {{ $invitee->name }}, your personalized invitation card is ready.
            </p>

            <div class="meta">
                <div class="meta-row">
                    <span>Serial Number</span>
                    <strong>{{ $invitee->serial_number }}</strong>
                </div>

                <div class="meta-row">
                    <span>Card Type</span>
                    <strong>{{ $invitee->cardType?->name ?? $invitee->card_type ?? 'Standard' }}</strong>
                </div>

                <div class="meta-row">
                    <span>Allowed Guests</span>
                    <strong>{{ $invitee->final_allowed_guests ?? $invitee->allowed_guests ?? 1 }}</strong>
                </div>
            </div>

            <img src="{{ $cardUrl }}" class="card-image" alt="Invitation Card">

            <a href="{{ route('public.card.download', $invitee->serial_number) }}" class="button download">
                Download Card
            </a>

            <a href="{{ $invitee->private_invitation_url }}" class="button view">
                View Invitation Page
            </a>

            <p class="note">
                Keep this card safe. It may be required at the event entrance for QR verification.
            </p>
        </div>
    </div>
</body>
</html>