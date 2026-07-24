<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <title>Invalid QR Code - eLive Card</title>

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover"
    >

    <meta
        name="theme-color"
        content="#B91C1C"
    >

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen overflow-x-hidden bg-[#F8FAFC] text-[#111827] antialiased">
    <main class="flex min-h-screen w-full items-center justify-center px-3 py-4 sm:px-4 sm:py-8">
        <section class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl sm:rounded-3xl">
            <header class="relative overflow-hidden bg-red-600 px-5 py-7 text-center text-white sm:px-6 sm:py-8">
                <div
                    class="absolute -right-12 -top-12 h-36 w-36 rounded-full bg-white/10"
                    aria-hidden="true"
                ></div>

                <div
                    class="absolute -bottom-16 -left-12 h-40 w-40 rounded-full bg-white/5"
                    aria-hidden="true"
                ></div>

                <div class="relative z-10">
                    <p class="text-xs font-black uppercase tracking-[0.22em] text-red-100">
                        eLive Card
                    </p>

                    <div class="mx-auto mt-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-red-600 shadow-sm sm:h-20 sm:w-20">
                        <svg
                            class="h-8 w-8 sm:h-10 sm:w-10"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.4"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="M12 7v6"></path>
                            <path d="M12 17h.01"></path>
                        </svg>
                    </div>

                    <h1 class="mt-4 break-words text-2xl font-black leading-tight sm:text-3xl">
                        Invalid QR Code
                    </h1>

                    <p class="mx-auto mt-2 max-w-sm text-sm font-semibold leading-6 text-red-100">
                        This invitation card could not be verified for gate check-in.
                    </p>
                </div>
            </header>

            <div class="space-y-5 p-4 text-center sm:p-6">
                <section
                    class="rounded-2xl border border-red-200 bg-red-50 p-4 text-left"
                    role="alert"
                    aria-live="polite"
                >
                    <p class="text-xs font-black uppercase tracking-wide text-red-500">
                        Verification message
                    </p>

                    <p class="mt-2 break-words text-sm font-bold leading-6 text-red-800">
                        {{ $message
                            ?? 'This QR code is not valid or does not belong to this event.'
                        }}
                    </p>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-[#F8FAFC] p-4 text-left">
                    <h2 class="text-sm font-black text-[#213B73]">
                        What to do next
                    </h2>

                    <ul class="mt-3 space-y-3 text-sm font-semibold leading-6 text-slate-600">
                        <li class="flex gap-3">
                            <span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-[#FD9618]"></span>
                            <span>Confirm the guest is presenting the correct invitation card.</span>
                        </li>

                        <li class="flex gap-3">
                            <span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-[#FD9618]"></span>
                            <span>Confirm the invitation belongs to the currently selected event.</span>
                        </li>

                        <li class="flex gap-3">
                            <span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-[#FD9618]"></span>
                            <span>Use manual search by serial number, phone number, or invitee name.</span>
                        </li>
                    </ul>
                </section>

                <div class="grid grid-cols-1 gap-3">
                    <button
                        type="button"
                        onclick="history.back()"
                        class="min-h-12 w-full rounded-2xl bg-[#213B73] px-5 py-3 text-base font-black text-white shadow-sm transition hover:bg-[#1B3160] focus:outline-none focus:ring-4 focus:ring-[#213B73]/20"
                    >
                        Back to Scanner
                    </button>

                    <button
                        type="button"
                        onclick="window.location.reload()"
                        class="min-h-12 w-full rounded-2xl border border-slate-300 bg-white px-5 py-3 text-base font-black text-[#111827] transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-200"
                    >
                        Try Again
                    </button>

                    <a
                        href="{{ url('/admin/gate-check-in') }}"
                        class="inline-flex min-h-12 w-full items-center justify-center rounded-2xl border border-[#213B73]/20 bg-[#F8FAFC] px-5 py-3 text-base font-black text-[#213B73] transition hover:bg-slate-100 focus:outline-none focus:ring-4 focus:ring-[#213B73]/10"
                    >
                        Assigned Events
                    </a>
                </div>

                <p class="pt-1 text-xs font-semibold text-slate-400">
                    eLive Card Gate Verification
                </p>
            </div>
        </section>
    </main>
</body>
</html>
