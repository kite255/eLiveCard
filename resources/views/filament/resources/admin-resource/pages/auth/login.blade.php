<x-filament-panels::page.simple>
    <div class="elive-login-page">
        <div class="elive-login-card">

            {{-- Left Brand Section --}}
            <div class="elive-login-left">
                <div class="elive-left-content">
                    <img
                        src="{{ asset('images/elive-cardw-logo.png') }}"
                        alt="eLive Card"
                        class="elive-logo"
                    >

                    <h1>Welcome Back</h1>

                    <p>
                        Manage digital invitations, RSVP responses, invitee lists,
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

        .elive-login-page {
            min-height: 100vh;
            width: 100%;
            background: #F8FAFC;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px;
        }

        .elive-login-card {
            width: 100%;
            max-width: 860px;
            min-height: 500px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: #FFFFFF;
            border-radius: 18px;
            overflow: hidden;
            border: 1px solid #E5E7EB;
            box-shadow: 0 22px 55px rgba(17, 24, 39, 0.10);
        }

        .elive-login-left {
            background: #213B73;
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 50px;
        }

        .elive-left-content {
            width: 100%;
            max-width: 340px;
        }

        .elive-logo {
            width: 230px;
            height: auto;
            display: block;
            margin-bottom: 42px;
        }

        .elive-login-left h1 {
            margin: 0 0 18px 0;
            color: #FFFFFF;
            font-size: 34px;
            line-height: 1.15;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .elive-login-left p {
            margin: 0;
            color: rgba(255, 255, 255, 0.82);
            font-size: 15px;
            line-height: 1.8;
        }

        .elive-login-right {
            background: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 50px;
        }

        .elive-form-box {
            width: 100%;
            max-width: 330px;
        }

        .elive-form-box h2 {
            margin: 0 0 10px 0;
            color: #111827;
            font-size: 32px;
            line-height: 1.2;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .elive-subtitle {
            margin: 0 0 28px 0;
            color: #64748B;
            font-size: 14px;
            line-height: 1.6;
        }

        .elive-login-form {
            text-align: left;
        }

        .elive-form-box .fi-fo-field-wrp {
            margin-bottom: 18px !important;
        }

        .elive-form-box .fi-fo-field-wrp-label span,
        .elive-form-box label {
            color: #111827 !important;
            font-size: 13px !important;
            font-weight: 700 !important;
        }

        .elive-form-box .fi-input-wrp {
            border-radius: 10px !important;
            border: 1px solid #CBD5E1 !important;
            background: #FFFFFF !important;
            box-shadow: none !important;
        }

        .elive-form-box .fi-input-wrp:focus-within {
            border-color: #213B73 !important;
            box-shadow: 0 0 0 3px rgba(33, 59, 115, 0.12) !important;
        }

        .elive-form-box input {
            min-height: 44px !important;
            border: none !important;
            background: transparent !important;
            color: #111827 !important;
            font-size: 14px !important;
            box-shadow: none !important;
        }

        .elive-form-box .fi-checkbox-input:checked {
            background-color: #213B73 !important;
            border-color: #213B73 !important;
        }

        .elive-login-button {
            width: 100%;
            height: 46px;
            margin-top: 8px;
            border: none;
            border-radius: 10px;
            background: #213B73;
            color: #FFFFFF;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .elive-login-button:hover {
            background: #FD9618;
            color: #111827;
        }

        .elive-footer-text {
            margin: 24px 0 0 0;
            padding-top: 18px;
            border-top: 1px solid #E5E7EB;
            color: #64748B;
            font-size: 12px;
            text-align: center;
        }

        @media (max-width: 820px) {
            .elive-login-page {
                padding: 20px;
            }

            .elive-login-card {
                max-width: 430px;
                grid-template-columns: 1fr;
            }

            .elive-login-left {
                padding: 36px 30px;
                text-align: center;
            }

            .elive-logo {
                width: 210px;
                margin-left: auto;
                margin-right: auto;
                margin-bottom: 28px;
            }

            .elive-login-left h1 {
                font-size: 28px;
            }

            .elive-login-right {
                padding: 36px 30px;
            }

            .elive-form-box h2,
            .elive-subtitle {
                text-align: center;
            }
        }
    </style>
</x-filament-panels::page.simple>