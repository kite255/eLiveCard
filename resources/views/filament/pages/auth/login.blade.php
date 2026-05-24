<x-filament-panels::page.simple>
    <div class="elive-login-page">
        <div class="elive-login-card">

            {{-- Left Brand Section --}}
            <div class="elive-login-left">
                <div class="elive-left-content">
                    @if (file_exists(public_path('images/elive-cardw-logo.png')))
                        <img
                            src="{{ asset('images/elive-cardw-logo.png') }}"
                            alt="eLive Card"
                            class="elive-logo"
                        >
                    @else
                        <div class="elive-logo-text">
                            eLive <span>Card</span>
                        </div>
                    @endif

                    <h1>Welcome Back</h1>

                    <p>
                        Manage invitations, RSVP responses, invitee lists,
                        QR check-ins, card sending, and event reports in one secure platform.
                    </p>
                </div>
            </div>

            {{-- Right Login Section --}}
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
        /*
        |--------------------------------------------------------------------------
        | Hide Filament Default Header
        |--------------------------------------------------------------------------
        */
        .fi-simple-header,
        .fi-logo,
        .fi-simple-header-heading,
        .fi-simple-header-subheading {
            display: none !important;
        }

        .fi-simple-layout {
            min-height: 100vh !important;
            background: #F4F1EA !important;
        }

        .fi-simple-main {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
        }

        .fi-simple-page {
            width: 100% !important;
            max-width: 100% !important;
        }

        /*
        |--------------------------------------------------------------------------
        | Page Layout
        |--------------------------------------------------------------------------
        */
        .elive-login-page {
            min-height: 100vh;
            width: 100%;
            background: #F4F1EA;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px;
        }

        .elive-login-card {
            width: 100%;
            max-width: 900px;
            min-height: 500px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: #FFFFFF;
            border-radius: 22px;
            overflow: hidden;
            border: 1px solid #E5E7EB;
            box-shadow: 0 18px 45px rgba(17, 24, 39, 0.08);
        }

        /*
        |--------------------------------------------------------------------------
        | Left Section
        |--------------------------------------------------------------------------
        */
        .elive-login-left {
            background: #213B73;
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 56px;
        }

        .elive-left-content {
            width: 100%;
            max-width: 340px;
        }

        .elive-logo {
            width: 220px;
            height: auto;
            object-fit: contain;
            display: block;
            margin-bottom: 48px;
        }

        .elive-logo-text {
            font-size: 28px;
            font-weight: 900;
            color: #FFFFFF;
            margin-bottom: 48px;
        }

        .elive-logo-text span {
            color: #FD9618;
        }

        .elive-login-left h1 {
            margin: 0 0 16px 0;
            color: #FFFFFF;
            font-size: 34px;
            line-height: 1.15;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .elive-login-left p {
            margin: 0;
            color: rgba(255, 255, 255, 0.84);
            font-size: 15px;
            line-height: 1.85;
        }

        /*
        |--------------------------------------------------------------------------
        | Right Section
        |--------------------------------------------------------------------------
        */
        .elive-login-right {
            background: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 56px;
        }

        .elive-form-box {
            width: 100%;
            max-width: 340px;
        }

        .elive-form-box h2 {
            margin: 0 0 10px 0;
            color: #111827;
            font-size: 34px;
            line-height: 1.15;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .elive-subtitle {
            margin: 0 0 30px 0;
            color: #64748B;
            font-size: 14px;
            line-height: 1.6;
        }

        .elive-login-form {
            text-align: left;
        }

        /*
        |--------------------------------------------------------------------------
        | Filament Form Styling
        |--------------------------------------------------------------------------
        */
        .elive-form-box .fi-fo-field-wrp {
            margin-bottom: 20px !important;
        }

        .elive-form-box .fi-fo-field-wrp-label span,
        .elive-form-box label {
            color: #111827 !important;
            font-size: 13px !important;
            font-weight: 700 !important;
        }

        .elive-form-box .fi-input-wrp {
            border-radius: 12px !important;
            border: 1px solid #CBD5E1 !important;
            background: #FFFFFF !important;
            box-shadow: none !important;
            transition: border-color 0.2s ease !important;
        }

        .elive-form-box .fi-input-wrp:hover {
            border-color: #94A3B8 !important;
        }

        .elive-form-box .fi-input-wrp:focus-within {
            border-color: #213B73 !important;
            box-shadow: none !important;
            outline: none !important;
        }

        .elive-form-box input {
            min-height: 46px !important;
            border: none !important;
            background: transparent !important;
            color: #111827 !important;
            font-size: 14px !important;
            box-shadow: none !important;
            outline: none !important;
        }

        .elive-form-box input:focus {
            box-shadow: none !important;
            outline: none !important;
        }

        .elive-form-box input::placeholder {
            color: #94A3B8 !important;
        }

        /*
        |--------------------------------------------------------------------------
        | Remember Me Checkbox
        |--------------------------------------------------------------------------
        */
        .elive-form-box .fi-checkbox-input {
            width: 18px !important;
            height: 18px !important;
            min-height: 18px !important;
            border-radius: 5px !important;
            border: 1px solid #CBD5E1 !important;
            background-color: #FFFFFF !important;
            box-shadow: none !important;
            outline: none !important;
        }

        .elive-form-box .fi-checkbox-input:checked {
            background-color: #213B73 !important;
            border-color: #213B73 !important;
        }

        .elive-form-box .fi-checkbox-input:focus,
        .elive-form-box .fi-checkbox-input:focus-visible {
            box-shadow: none !important;
            outline: none !important;
        }

        .elive-form-box .fi-fo-field-wrp:has(.fi-checkbox-input) {
            margin-top: -4px !important;
            margin-bottom: 18px !important;
        }

        .elive-form-box .fi-fo-field-wrp:has(.fi-checkbox-input) label,
        .elive-form-box .fi-fo-field-wrp:has(.fi-checkbox-input) span {
            font-size: 13px !important;
            font-weight: 700 !important;
            color: #111827 !important;
        }

        /*
        |--------------------------------------------------------------------------
        | Button
        |--------------------------------------------------------------------------
        */
        .elive-login-button {
            width: 100%;
            height: 46px;
            margin-top: 8px;
            border: none;
            border-radius: 12px;
            background: #213B73;
            color: #FFFFFF;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            transition: background 0.2s ease, color 0.2s ease;
            box-shadow: none !important;
            outline: none !important;
        }

        .elive-login-button:hover {
            background: #FD9618;
            color: #111827;
            box-shadow: none !important;
            transform: none !important;
        }

        .elive-login-button:focus,
        .elive-login-button:focus-visible,
        .elive-login-button:active {
            box-shadow: none !important;
            outline: none !important;
        }

        .elive-footer-text {
            margin: 26px 0 0 0;
            padding-top: 18px;
            border-top: 1px solid #E5E7EB;
            color: #64748B;
            font-size: 12px;
            line-height: 1.6;
            text-align: center;
        }

        /*
        |--------------------------------------------------------------------------
        | Mobile
        |--------------------------------------------------------------------------
        */
        @media (max-width: 820px) {
            .elive-login-page {
                padding: 20px;
            }

            .elive-login-card {
                max-width: 430px;
                min-height: auto;
                grid-template-columns: 1fr;
            }

            .elive-login-left {
                padding: 36px 30px;
                text-align: center;
            }

            .elive-left-content {
                max-width: 100%;
            }

            .elive-logo,
            .elive-logo-text {
                margin-left: auto;
                margin-right: auto;
                margin-bottom: 28px;
            }

            .elive-logo {
                width: 210px;
            }

            .elive-login-left h1 {
                font-size: 30px;
            }

            .elive-login-right {
                padding: 36px 30px;
            }

            .elive-form-box h2 {
                font-size: 28px;
                text-align: center;
            }

            .elive-subtitle {
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .elive-login-page {
                padding: 14px;
            }

            .elive-login-card {
                border-radius: 16px;
            }

            .elive-login-left,
            .elive-login-right {
                padding: 30px 22px;
            }

            .elive-logo {
                width: 190px;
            }
        }
    </style>
</x-filament-panels::page.simple>