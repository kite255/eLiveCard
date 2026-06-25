<x-filament-panels::page>
    <style>
        [x-cloak] {
            display: none !important;
        }

        .elive-btn {
            min-height: 52px;
        }

        .elive-header-logo {
            width: 48px;
            height: 48px;
            object-fit: contain;
            border-radius: 14px;
            background: #FFFFFF;
            padding: 5px;
        }

        .elive-logo-fallback {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: #FFFFFF;
            color: #213B73;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 18px;
        }

        #qr-reader video {
            border-radius: 14px;
        }
    </style>

    <div class="mx-auto max-w-md space-y-4 pb-10">
        @php
            $totalInvitees = (int) ($stats['total_invitees'] ?? 0);
            $checkedInvitees = (int) ($stats['checked_invitees'] ?? 0);
            $checkedGuests = (int) ($stats['checked_guests'] ?? 0);
            $logoUrl = asset('images/elive-card-logo.png');
        @endphp

        {{-- Header --}}
        <div class="rounded-2xl bg-[#213B73] p-5 text-white shadow">
            <div class="flex items-center gap-3">
                <img
                    src="{{ $logoUrl }}"
                    alt="eLive Card Logo"
                    class="elive-header-logo"
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                >

                <div class="elive-logo-fallback" style="display: none;">
                    eL
                </div>

                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-white/70">
                        eLive Card
                    </p>

                    <h1 class="mt-1 text-2xl font-black leading-tight">
                        Gate Check-in
                    </h1>
                </div>
            </div>

            <p class="mt-4 text-sm text-white/80">
                Scan QR code or search invitee manually.
            </p>
        </div>

        {{-- Simple Stats --}}
        <div class="grid grid-cols-3 gap-2">
            <div class="rounded-2xl bg-white p-3 text-center shadow ring-1 ring-gray-200">
                <p class="text-xs text-gray-500">Invitees</p>
                <p class="text-xl font-black text-[#213B73]">{{ $totalInvitees }}</p>
            </div>

            <div class="rounded-2xl bg-white p-3 text-center shadow ring-1 ring-gray-200">
                <p class="text-xs text-gray-500">Checked</p>
                <p class="text-xl font-black text-green-600">{{ $checkedInvitees }}</p>
            </div>

            <div class="rounded-2xl bg-white p-3 text-center shadow ring-1 ring-gray-200">
                <p class="text-xs text-gray-500">Guests</p>
                <p class="text-xl font-black text-green-600">{{ $checkedGuests }}</p>
            </div>
        </div>

        {{-- Search / Scanner --}}
        <div class="rounded-2xl bg-white p-4 shadow ring-1 ring-gray-200">
            <h2 class="text-base font-black text-gray-900">
                Scan or Search
            </h2>

            <div class="mt-4">
                {{ $this->form }}
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3">
                <button
                    type="button"
                    x-data
                    x-on:click="$dispatch('open-camera-scanner')"
                    class="elive-btn w-full rounded-2xl bg-[#FD9618] px-5 py-4 text-base font-black text-white shadow"
                >
                    Scan QR Code
                </button>

                <button
                    type="button"
                    wire:click="searchInvitee"
                    class="elive-btn w-full rounded-2xl bg-[#213B73] px-5 py-4 text-base font-black text-white shadow"
                >
                    Search / Validate
                </button>

                <button
                    type="button"
                    wire:click="resetScanner"
                    class="elive-btn w-full rounded-2xl border border-gray-300 bg-white px-5 py-4 text-base font-black text-gray-700"
                >
                    Clear
                </button>
            </div>

            {{-- Camera Scanner --}}
            <div
                x-data="{
                    cameraOpen: false,
                    scanner: null,
                    processing: false,

                    async startScanner() {
                        this.cameraOpen = true;
                        await this.$nextTick();

                        if (!window.Html5Qrcode) {
                            alert('Scanner not loaded. Refresh the page.');
                            this.cameraOpen = false;
                            return;
                        }

                        if (this.scanner) return;

                        this.scanner = new Html5Qrcode('qr-reader');

                        const onSuccess = async (decodedText) => {
                            if (this.processing) return;

                            this.processing = true;

                            @this.set('data.search', decodedText);
                            await @this.call('searchInvitee');

                            await this.stopScanner();
                            this.processing = false;
                        };

                        try {
                            await this.scanner.start(
                                { facingMode: 'environment' },
                                {
                                    fps: 10,
                                    qrbox: { width: 240, height: 240 },
                                },
                                onSuccess,
                                () => {}
                            );
                        } catch (error) {
                            alert('Camera failed. Allow camera permission and use HTTPS.');
                            this.scanner = null;
                            this.cameraOpen = false;
                            this.processing = false;
                        }
                    },

                    async stopScanner() {
                        if (this.scanner) {
                            try {
                                await this.scanner.stop();
                                this.scanner.clear();
                            } catch (error) {}

                            this.scanner = null;
                        }

                        this.cameraOpen = false;
                    }
                }"
                x-on:open-camera-scanner.window="startScanner()"
                class="mt-4"
            >
                <div x-show="cameraOpen" x-cloak class="rounded-2xl border border-gray-200 bg-gray-50 p-3">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="font-black text-gray-900">
                            Camera
                        </p>

                        <button
                            type="button"
                            x-on:click="stopScanner()"
                            class="rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white"
                        >
                            Close
                        </button>
                    </div>

                    <div id="qr-reader" class="mx-auto overflow-hidden rounded-xl bg-white"></div>

                    <p class="mt-3 text-center text-xs text-gray-500">
                        Point camera to QR code.
                    </p>
                </div>
            </div>
        </div>

        {{-- Multiple Results --}}
        @if ($results->count() > 1 && ! $invitee)
            <div class="rounded-2xl bg-white p-4 shadow ring-1 ring-gray-200">
                <h2 class="text-base font-black text-gray-900">
                    Select Invitee
                </h2>

                <div class="mt-3 space-y-2">
                    @foreach ($results as $result)
                        <button
                            type="button"
                            wire:click="selectInvitee({{ $result->id }})"
                            class="w-full rounded-2xl border border-gray-200 bg-gray-50 p-4 text-left"
                        >
                            <p class="text-base font-black text-[#213B73]">
                                {{ $result->name }}
                            </p>

                            <p class="mt-1 text-sm text-gray-600">
                                {{ $result->phone ?? 'No phone' }}
                            </p>

                            <p class="mt-1 break-all text-xs font-bold text-gray-500">
                                {{ $result->serial_number }}
                            </p>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Selected Invitee --}}
        @if ($invitee)
            @php
                $allowedGuestsForInvitee = (int) ($invitee->final_allowed_guests ?? $invitee->allowed_guests ?? 1);
                $checkedCount = (int) ($invitee->checked_in_count ?? 0);
                $remainingGuests = max($allowedGuestsForInvitee - $checkedCount, 0);

                $cardStatus = $invitee->card_status ?? 'active';
                $allowedCardStatuses = ['active', 'generated', 'sent'];

                $canCheckIn = $remainingGuests > 0 && in_array($cardStatus, $allowedCardStatuses, true);
            @endphp

            <div class="rounded-2xl bg-white p-4 shadow ring-1 ring-gray-200">
                {{-- Big Status --}}
                @if ($canCheckIn)
                    <div class="rounded-2xl bg-green-600 p-4 text-center text-white">
                        <p class="text-3xl font-black">
                            VALID
                        </p>

                        <p class="mt-1 text-sm text-white/90">
                            Allow entry after confirmation.
                        </p>
                    </div>
                @else
                    <div class="rounded-2xl bg-red-600 p-4 text-center text-white">
                        <p class="text-3xl font-black">
                            NOT ALLOWED
                        </p>

                        <p class="mt-1 text-sm text-white/90">
                            Check details before entry.
                        </p>
                    </div>
                @endif

                {{-- Invitee Info --}}
                <div class="mt-4 text-center">
                    <h2 class="text-2xl font-black text-[#213B73]">
                        {{ $invitee->name }}
                    </h2>

                    <p class="mt-1 text-sm text-gray-500">
                        {{ $invitee->phone ?? 'No phone number' }}
                    </p>

                    <p class="mt-2 break-all rounded-xl bg-gray-50 px-3 py-2 text-sm font-black text-gray-700">
                        {{ $invitee->serial_number }}
                    </p>
                </div>

                {{-- Guest Info --}}
                <div class="mt-4 grid grid-cols-3 gap-2">
                    <div class="rounded-2xl bg-gray-50 p-3 text-center">
                        <p class="text-xs text-gray-500">Allowed</p>
                        <p class="text-2xl font-black text-[#213B73]">
                            {{ $allowedGuestsForInvitee }}
                        </p>
                    </div>

                    <div class="rounded-2xl bg-gray-50 p-3 text-center">
                        <p class="text-xs text-gray-500">In</p>
                        <p class="text-2xl font-black text-green-600">
                            {{ $checkedCount }}
                        </p>
                    </div>

                    <div class="rounded-2xl bg-gray-50 p-3 text-center">
                        <p class="text-xs text-gray-500">Left</p>
                        <p class="text-2xl font-black {{ $remainingGuests > 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $remainingGuests }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 rounded-2xl bg-gray-50 p-3">
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <p class="text-xs text-gray-500">Card Type</p>
                            <p class="font-black text-gray-900">
                                {{ $invitee->cardType?->name ?? 'N/A' }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs text-gray-500">Card Status</p>
                            <p class="font-black {{ in_array($cardStatus, $allowedCardStatuses, true) ? 'text-green-600' : 'text-red-600' }}">
                                {{ strtoupper($cardStatus) }}
                            </p>
                        </div>

                        @if ($invitee->table_number)
                            <div>
                                <p class="text-xs text-gray-500">Table</p>
                                <p class="font-black text-gray-900">
                                    {{ $invitee->table_number }}
                                </p>
                            </div>
                        @endif

                        <div>
                            <p class="text-xs text-gray-500">RSVP</p>
                            <p class="font-black text-gray-900">
                                {{ strtoupper(str_replace('_', ' ', $invitee->rsvp_status ?? 'pending')) }}
                            </p>
                        </div>
                    </div>
                </div>

                @if ($remainingGuests <= 0)
                    <div class="mt-4 rounded-2xl bg-red-50 p-4 text-center text-sm font-bold text-red-700">
                        This card has already used all allowed entries.
                    </div>
                @endif

                @if (! in_array($cardStatus, $allowedCardStatuses, true))
                    <div class="mt-4 rounded-2xl bg-red-50 p-4 text-center text-sm font-bold text-red-700">
                        This card status is {{ strtoupper($cardStatus) }}.
                    </div>
                @endif

                {{-- Actions --}}
                <div class="mt-5 grid grid-cols-1 gap-3">
                    <button
                        type="button"
                        wire:click="checkIn"
                        @disabled(! $canCheckIn)
                        class="elive-btn w-full rounded-2xl bg-[#FD9618] px-5 py-4 text-lg font-black text-white shadow disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Confirm Check-in
                    </button>

                    <button
                        type="button"
                        wire:click="resetScanner"
                        class="elive-btn w-full rounded-2xl bg-[#213B73] px-5 py-4 text-lg font-black text-white shadow"
                    >
                        New Scan
                    </button>
                </div>
            </div>
        @endif

        {{-- Recent Check-ins --}}
        <div class="rounded-2xl bg-white p-4 shadow ring-1 ring-gray-200">
            <h2 class="text-base font-black text-gray-900">
                Recent Check-ins
            </h2>

            <div class="mt-3 space-y-2">
                @forelse ($this->recentCheckIns as $checkIn)
                    <div class="flex items-center justify-between rounded-2xl bg-gray-50 p-3">
                        <div class="min-w-0">
                            <p class="truncate font-black text-gray-900">
                                {{ $checkIn->invitee?->name ?? 'Unknown Invitee' }}
                            </p>

                            <p class="truncate text-xs text-gray-500">
                                {{ $checkIn->invitee?->serial_number }}
                            </p>
                        </div>

                        <div class="shrink-0 text-right">
                            <p class="font-black text-green-600">
                                +{{ $checkIn->guests_checked_in }}
                            </p>

                            <p class="text-xs text-gray-500">
                                {{ optional($checkIn->checked_in_at)->format('H:i') }}
                            </p>
                        </div>
                    </div>
                @empty
                    <p class="rounded-2xl bg-gray-50 p-4 text-center text-sm text-gray-500">
                        No check-ins yet.
                    </p>
                @endforelse
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="https://unpkg.com/html5-qrcode"></script>
    @endpush
</x-filament-panels::page>