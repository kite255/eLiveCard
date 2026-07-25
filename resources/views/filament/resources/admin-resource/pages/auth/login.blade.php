<x-filament-panels::page.simple>
    <div class="elive-login-page">
        <div class="elive-login-card">

            <div class="elive-login-left">
                <div class="elive-left-content">
                    <img
                        src="{{ asset('images/elive-cardw-logo.png') }}"
                        alt="eLive Card"
                        class="elive-logo"
                    >

                    <h1>Welcome Back</h1>

                    <p>
                        Manage invitations, RSVP responses, invitees,
                        QR check-ins, card sending, and event reports
                        in one secure platform.
                    </p>
                </div>
            </div>

            <div class="elive-login-right">
                <div class="elive-form-box">
                    <h2>Sign in</h2>

                    <p class="elive-subtitle">
                        Access your eLive Card dashboard.
                    </p>

                    <form wire:submit="authenticate" class="elive-login-form">
                        {{ $this->form }}

                        <button type="submit" class="elive-login-button">
                            Sign in
                        </button>
                    </form>

                    <p class="elive-footer-text">
                        Authorized users only.
                    </p>
                </div>
            </div>

        </div>
    </div>

    <style>
        .fi-simple-header,
        .fi-logo,
        .fi-simple-header-heading,
        .fi-simple-header-subheading {
            display: none !important;
        }

        .fi-simple-layout {
            min-height: 100vh !important;
            background: #F8FAFC !important;
        }

        .fi-simple-main,
        .fi-simple-page {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
        }

        .elive-login-page {
            width: 100%;
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #F8FAFC;
        }

        .elive-login-card {
            width: 100%;
            max-width: 680px;
            min-height: 390px;
            display: grid;
            grid-template-columns: 0.95fr 1.05fr;
            overflow: hidden;
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            box-shadow: 0 16px 40px rgba(17, 24, 39, 0.08);
        }

        .elive-login-left {
            padding: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #213B73;
            color: #FFFFFF;
        }

        .elive-left-content {
            width: 100%;
            max-width: 245px;
        }

        .elive-logo {
            width: 165px;
            height: auto;
            display: block;
            margin-bottom: 24px;
        }

        .elive-login-left h1 {
            margin: 0 0 12px;
            color: #FFFFFF;
            font-size: 25px;
            line-height: 1.2;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .elive-login-left p {
            margin: 0;
            color: rgba(255, 255, 255, 0.82);
            font-size: 12px;
            line-height: 1.65;
        }

        .elive-login-right {
            padding: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #FFFFFF;
        }

        .elive-form-box {
            width: 100%;
            max-width: 270px;
        }

        .elive-form-box h2 {
            margin: 0 0 6px;
            color: #111827;
            font-size: 25px;
            line-height: 1.2;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .elive-subtitle {
            margin: 0 0 18px;
            color: #64748B;
            font-size: 12px;
            line-height: 1.5;
        }

        .elive-login-form {
            text-align: left;
        }

        .elive-form-box .fi-fo-field-wrp {
            margin-bottom: 12px !important;
        }

        .elive-form-box .fi-fo-field-wrp-label span,
        .elive-form-box label {
            color: #111827 !important;
            font-size: 11px !important;
            font-weight: 700 !important;
        }

        .elive-form-box .fi-input-wrp {
            min-height: 38px !important;
            overflow: hidden;
            background: #FFFFFF !important;
            border: 1px solid #CBD5E1 !important;
            border-radius: 8px !important;
            box-shadow: none !important;
        }

        .elive-form-box .fi-input-wrp:focus-within {
            border-color: #213B73 !important;
            box-shadow: 0 0 0 3px rgba(33, 59, 115, 0.10) !important;
        }

        .elive-form-box input {
            min-height: 38px !important;
            padding-top: 7px !important;
            padding-bottom: 7px !important;
            border: none !important;
            background: transparent !important;
            color: #111827 !important;
            font-size: 12px !important;
            box-shadow: none !important;
        }

        .elive-form-box .fi-checkbox-input {
            width: 15px !important;
            height: 15px !important;
        }

        .elive-form-box .fi-checkbox-input:checked {
            background-color: #213B73 !important;
            border-color: #213B73 !important;
        }

        .elive-login-button {
            width: 100%;
            height: 40px;
            margin-top: 4px;
            border: none;
            border-radius: 8px;
            background: #213B73;
            color: #FFFFFF;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            transition:
                background-color 0.2s ease,
                color 0.2s ease,
                transform 0.2s ease;
        }

        .elive-login-button:hover {
            background: #FD9618;
            color: #111827;
        }

        .elive-login-button:active {
            transform: translateY(1px);
        }

        .elive-footer-text {
            margin: 16px 0 0;
            padding-top: 13px;
            border-top: 1px solid #E5E7EB;
            color: #64748B;
            font-size: 10px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .elive-login-page {
                padding: 16px;
                align-items: flex-start;
            }

            .elive-login-card {
                max-width: 370px;
                min-height: auto;
                grid-template-columns: 1fr;
            }

            .elive-login-left {
                padding: 24px 22px;
                text-align: center;
            }

            .elive-left-content {
                max-width: 285px;
            }

            .elive-logo {
                width: 150px;
                margin: 0 auto 16px;
            }

            .elive-login-left h1 {
                margin-bottom: 8px;
                font-size: 22px;
            }

            .elive-login-left p {
                font-size: 11px;
                line-height: 1.55;
            }

            .elive-login-right {
                padding: 26px 24px;
            }

            .elive-form-box {
                max-width: 100%;
            }

            .elive-form-box h2 {
                font-size: 23px;
            }

            .elive-form-box h2,
            .elive-subtitle {
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .elive-login-page {
                padding: 0;
                align-items: stretch;
                background: #FFFFFF;
            }

            .elive-login-card {
                max-width: 100%;
                min-height: 100vh;
                border: none;
                border-radius: 0;
                box-shadow: none;
            }

            .elive-login-left {
                min-height: 205px;
                padding: 22px 20px;
            }

            .elive-logo {
                width: 140px;
                margin-bottom: 14px;
            }

            .elive-login-left h1 {
                font-size: 21px;
            }

            .elive-login-right {
                padding: 24px 22px 30px;
                align-items: flex-start;
            }
        }

        @media (max-width: 360px) {
            .elive-login-left {
                min-height: 185px;
                padding: 20px 18px;
            }

            .elive-login-right {
                padding: 22px 18px 28px;
            }

            .elive-logo {
                width: 128px;
            }

            .elive-login-left h1,
            .elive-form-box h2 {
                font-size: 20px;
            }
        }

        @media (max-height: 650px) and (min-width: 769px) {
            .elive-login-page {
                padding: 12px;
            }

            .elive-login-card {
                min-height: 360px;
            }

            .elive-login-left,
            .elive-login-right {
                padding: 24px 28px;
            }

            .elive-logo {
                width: 150px;
                margin-bottom: 18px;
            }

            .elive-footer-text {
                margin-top: 12px;
                padding-top: 10px;
            }
        }
    </style>
</x-filament-panels::page.simple>