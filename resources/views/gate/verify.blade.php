<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gate Verification - eLive Card</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 min-h-screen text-white">
    <div class="max-w-xl mx-auto p-4">
        <div class="bg-slate-900 rounded-2xl shadow-xl border border-slate-800 overflow-hidden">
            <div class="p-5 border-b border-slate-800">
                <p class="text-sm text-yellow-400 font-semibold">eLive Card Gate Verification</p>
                <h1 class="text-2xl font-bold mt-1">{{ $invitee->name }}</h1>
                <p class="text-slate-400 mt-1">{{ $invitee->event?->title }}</p>
            </div>

            <div class="p-5 space-y-4">
                @if (session('success'))
                    <div class="bg-green-900/50 border border-green-700 text-green-200 rounded-xl p-3">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="bg-red-900/50 border border-red-700 text-red-200 rounded-xl p-3">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-slate-800 rounded-xl p-3">
                        <div class="text-xs text-slate-400">Phone</div>
                        <div class="font-semibold">{{ $invitee->phone }}</div>
                    </div>

                    <div class="bg-slate-800 rounded-xl p-3">
                        <div class="text-xs text-slate-400">Serial</div>
                        <div class="font-semibold">{{ $invitee->serial_number }}</div>
                    </div>

                    <div class="bg-slate-800 rounded-xl p-3">
                        <div class="text-xs text-slate-400">Card Type</div>
                        <div class="font-semibold">{{ $invitee->cardType?->name }}</div>
                    </div>

                    <div class="bg-slate-800 rounded-xl p-3">
                        <div class="text-xs text-slate-400">Card Status</div>
                        <div class="font-semibold uppercase">{{ $invitee->card_status }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 text-center">
                    <div class="bg-slate-800 rounded-xl p-4">
                        <div class="text-xs text-slate-400">Allowed</div>
                        <div class="text-3xl font-bold">{{ $invitee->final_allowed_guests }}</div>
                    </div>

                    <div class="bg-slate-800 rounded-xl p-4">
                        <div class="text-xs text-slate-400">In</div>
                        <div class="text-3xl font-bold">{{ $invitee->checked_in_count }}</div>
                    </div>

                    <div class="bg-slate-800 rounded-xl p-4">
                        <div class="text-xs text-slate-400">Remain</div>
                        <div class="text-3xl font-bold">{{ $invitee->remaining_guests }}</div>
                    </div>
                </div>

                @auth
                    @if ($invitee->remaining_guests > 0 && $invitee->card_status !== 'blocked')
                        <form method="POST" action="{{ route('gate.verify.check-in', $token) }}" class="space-y-3">
                            @csrf

                            <div>
                                <label class="block text-sm text-slate-300 mb-1">
                                    Guests entering now
                                </label>
                                <input
                                    type="number"
                                    name="guests_to_check_in"
                                    value="1"
                                    min="1"
                                    max="{{ $invitee->remaining_guests }}"
                                    class="w-full rounded-xl bg-slate-800 border border-slate-700 px-4 py-3 text-white"
                                    required
                                >
                            </div>

                            <button
                                type="submit"
                                class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-xl"
                            >
                                Check In
                            </button>
                        </form>
                    @else
                        <div class="bg-red-900/50 border border-red-700 text-red-200 rounded-xl p-3 text-center font-semibold">
                            No remaining guests or card blocked.
                        </div>
                    @endif
                @else
                    <a
                        href="/admin/login"
                        class="block text-center bg-yellow-500 hover:bg-yellow-600 text-black font-bold py-3 rounded-xl"
                    >
                        Login to Check In
                    </a>
                @endauth
            </div>
        </div>
    </div>
</body>
</html>