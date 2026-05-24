<x-filament-panels::page>
    <div
        x-data="{
            focusSearch() {
                this.$nextTick(() => {
                    const inputs = document.querySelectorAll('input');
                    const searchInput = Array.from(inputs).find(input =>
                        input.placeholder?.toLowerCase().includes('scan') ||
                        input.placeholder?.toLowerCase().includes('serial') ||
                        input.placeholder?.toLowerCase().includes('phone')
                    );

                    if (searchInput) {
                        searchInput.focus();
                        searchInput.select();
                    }
                });
            }
        }"
        x-init="focusSearch()"
        class="space-y-6"
    >
        @php
            $totalInvitees = (int) ($stats['total_invitees'] ?? 0);
            $checkedInvitees = (int) ($stats['checked_invitees'] ?? 0);
            $allowedGuests = (int) ($stats['allowed_guests'] ?? 0);
            $checkedGuests = (int) ($stats['checked_guests'] ?? 0);

            $inviteeProgress = $totalInvitees > 0 ? round(($checkedInvitees / $totalInvitees) * 100) : 0;
            $guestProgress = $allowedGuests > 0 ? round(($checkedGuests / $allowedGuests) * 100) : 0;
        @endphp

        {{-- Header --}}
        <div
            class="relative overflow-hidden rounded-3xl p-6 shadow-sm md:p-8"
            style="background: linear-gradient(135deg, #213B73 0%, #172A52 100%); color: #FFFFFF;"
        >
            <div
                class="absolute -right-10 -top-10 h-44 w-44 rounded-full blur-3xl"
                style="background: rgba(253, 150, 24, 0.25);"
            ></div>

            <div
                class="absolute -bottom-12 -left-10 h-40 w-40 rounded-full blur-3xl"
                style="background: rgba(255, 255, 255, 0.12);"
            ></div>

            <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div
                        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                        style="background: rgba(255,255,255,0.12); color: #FFFFFF; border: 1px solid rgba(255,255,255,0.20);"
                    >
                        Event Day Access Control
                    </div>

                    <h1 class="mt-4 text-2xl font-extrabold tracking-tight md:text-3xl">
                        eLive Card Gate Check-in
                    </h1>

                    <p class="mt-2 max-w-2xl text-sm leading-6" style="color: rgba(255,255,255,0.82);">
                        Scan QR codes using phone or laptop camera, validate invitees, enforce guest limits, and record attendance quickly at the gate.
                    </p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <button
                        type="button"
                        wire:click="searchInvitee"
                        class="inline-flex items-center justify-center rounded-2xl px-5 py-3 text-sm font-bold shadow-sm transition hover:opacity-90"
                        style="background: #FFFFFF; color: #213B73;"
                    >
                        Validate
                    </button>

                    <button
                        type="button"
                        x-on:click="$dispatch('open-camera-scanner')"
                        class="inline-flex items-center justify-center rounded-2xl px-5 py-3 text-sm font-bold shadow-sm transition hover:opacity-90"
                        style="background: #FD9618; color: #FFFFFF;"
                    >
                        Open Camera
                    </button>

                    <button
                        type="button"
                        wire:click="resetScanner"
                        x-on:click="focusSearch()"
                        class="inline-flex items-center justify-center rounded-2xl px-5 py-3 text-sm font-bold shadow-sm transition hover:opacity-90"
                        style="background: rgba(255,255,255,0.12); color: #FFFFFF; border: 1px solid rgba(255,255,255,0.24);"
                    >
                        New Scan
                    </button>
                </div>
            </div>
        </div>

        {{-- Statistics --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Invitees</p>
                        <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">
                            {{ number_format($totalInvitees) }}
                        </p>
                    </div>

                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl"
                        style="background: rgba(33,59,115,0.10); color: #213B73;"
                    >
                        <x-heroicon-o-users class="h-6 w-6" />
                    </div>
                </div>
            </div>

            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Checked Invitees</p>
                        <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">
                            {{ number_format($checkedInvitees) }}
                        </p>
                    </div>

                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-green-100 text-green-700">
                        <x-heroicon-o-check-circle class="h-6 w-6" />
                    </div>
                </div>

                <div class="mt-4">
                    <div class="mb-1 flex items-center justify-between text-xs font-semibold text-gray-500 dark:text-gray-400">
                        <span>Invitee Progress</span>
                        <span>{{ $inviteeProgress }}%</span>
                    </div>

                    <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                        <div class="h-full rounded-full bg-green-500" style="width: {{ min($inviteeProgress, 100) }}%"></div>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Allowed Guests</p>
                        <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">
                            {{ number_format($allowedGuests) }}
                        </p>
                    </div>

                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl"
                        style="background: rgba(253,150,24,0.12); color: #FD9618;"
                    >
                        <x-heroicon-o-ticket class="h-6 w-6" />
                    </div>
                </div>
            </div>

            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Checked Guests</p>
                        <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">
                            {{ number_format($checkedGuests) }}
                        </p>
                    </div>

                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl"
                        style="background: rgba(33,59,115,0.10); color: #213B73;"
                    >
                        <x-heroicon-o-clipboard-document-check class="h-6 w-6" />
                    </div>
                </div>

                <div class="mt-4">
                    <div class="mb-1 flex items-center justify-between text-xs font-semibold text-gray-500 dark:text-gray-400">
                        <span>Guest Entry</span>
                        <span>{{ $guestProgress }}%</span>
                    </div>

                    <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                        <div class="h-full rounded-full" style="width: {{ min($guestProgress, 100) }}%; background: #213B73;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-12">
            {{-- Main Content --}}
            <div class="space-y-6 xl:col-span-8">
                {{-- Search Form --}}
                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h2 class="text-xl font-extrabold text-gray-900 dark:text-white">
                                Scan or Search Invitee
                            </h2>

                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Select the event, scan QR using camera, or search by serial number, phone number, or invitee name.
                            </p>
                        </div>

                        <div class="inline-flex w-fit items-center rounded-full bg-green-50 px-3 py-1 text-xs font-bold text-green-700 ring-1 ring-green-200">
                            <span class="mr-2 h-2 w-2 rounded-full bg-green-500"></span>
                            Scanner Ready
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 md:p-5 dark:border-gray-800 dark:bg-gray-950">
                        {{ $this->form }}
                    </div>

                    <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                        <button
                            type="button"
                            wire:click="searchInvitee"
                            class="inline-flex items-center justify-center rounded-2xl px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:opacity-90"
                            style="background: #213B73;"
                        >
                            <x-heroicon-o-shield-check class="mr-2 h-5 w-5" />
                            Search / Validate
                        </button>

                        <button
                            type="button"
                            x-on:click="$dispatch('open-camera-scanner')"
                            class="inline-flex items-center justify-center rounded-2xl px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:opacity-90"
                            style="background: #FD9618;"
                        >
                            <x-heroicon-o-camera class="mr-2 h-5 w-5" />
                            Open Camera Scanner
                        </button>

                        <button
                            type="button"
                            wire:click="resetScanner"
                            x-on:click="focusSearch()"
                            class="inline-flex items-center justify-center rounded-2xl border border-gray-300 bg-white px-6 py-3 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                        >
                            <x-heroicon-o-arrow-path class="mr-2 h-5 w-5" />
                            Clear / New Scan
                        </button>
                    </div>

                    {{-- Camera QR Scanner --}}
                    <div
                        x-data="{
                            cameraOpen: false,
                            scanner: null,
                            isScanning: false,

                            async startCameraScanner() {
                                this.cameraOpen = true;

                                await this.$nextTick();

                                if (!window.Html5Qrcode) {
                                    alert('QR scanner library is not loaded. Refresh the page and try again.');
                                    return;
                                }

                                if (this.scanner || this.isScanning) {
                                    return;
                                }

                                const readerId = 'elive-qr-reader';

                                this.scanner = new Html5Qrcode(readerId);

                                this.scanner.start(
                                    { facingMode: 'environment' },
                                    {
                                        fps: 10,
                                        qrbox: {
                                            width: 260,
                                            height: 260,
                                        },
                                        aspectRatio: 1.0,
                                    },
                                    async (decodedText) => {
                                        if (this.isScanning) {
                                            return;
                                        }

                                        this.isScanning = true;

                                        @this.set('data.search', decodedText);
                                        await @this.call('searchInvitee');

                                        await this.stopCameraScanner();

                                        this.isScanning = false;
                                    },
                                    () => {
                                        // Normal scanning errors are ignored while camera is open.
                                    }
                                ).catch((error) => {
                                    console.error(error);

                                    alert('Unable to start camera scanner. Allow camera permission and make sure you are using HTTPS.');

                                    this.cameraOpen = false;
                                    this.scanner = null;
                                    this.isScanning = false;
                                });
                            },

                            async stopCameraScanner() {
                                if (this.scanner) {
                                    try {
                                        await this.scanner.stop();
                                        this.scanner.clear();
                                    } catch (error) {
                                        console.error(error);
                                    }

                                    this.scanner = null;
                                }

                                this.cameraOpen = false;
                                this.isScanning = false;
                            }
                        }"
                        x-on:open-camera-scanner.window="startCameraScanner()"
                        class="mt-5"
                    >
                        <div
                            x-show="cameraOpen"
                            x-cloak
                            class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950"
                        >
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">
                                        Camera QR Scanner
                                    </p>

                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Allow camera access, then point the camera to the invitee QR code.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    x-on:click="stopCameraScanner()"
                                    class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-xs font-bold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                                >
                                    Close
                                </button>
                            </div>

                            <div class="flex justify-center">
                                <div
                                    id="elive-qr-reader"
                                    class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800"
                                    style="width: 100%; max-width: 430px;"
                                ></div>
                            </div>

                            <div
                                class="mt-4 rounded-xl p-3 text-xs"
                                style="background: rgba(33,59,115,0.06); color: #213B73; border: 1px solid rgba(33,59,115,0.12);"
                            >
                                For phone scanning, open this page using HTTPS. Browsers usually block camera access on normal HTTP pages.
                            </div>
                        </div>
                    </div>

                    <div
                        class="mt-5 rounded-2xl p-4"
                        style="background: rgba(33,59,115,0.06); border: 1px solid rgba(33,59,115,0.14);"
                    >
                        <div class="flex gap-3">
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-white"
                                style="background: #213B73;"
                            >
                                <x-heroicon-o-light-bulb class="h-5 w-5" />
                            </div>

                            <div>
                                <p class="text-sm font-bold text-gray-900 dark:text-white">
                                    Gate scanning tip
                                </p>

                                <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-400">
                                    You can use phone/laptop camera scanner or a USB QR scanner. A USB scanner works like a keyboard and fills the search field automatically.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Multiple Results --}}
                @if ($results->count() > 1 && ! $invitee)
                    <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="text-xl font-extrabold text-gray-900 dark:text-white">
                                    Multiple Invitees Found
                                </h2>

                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Select the correct invitee from the matching records.
                                </p>
                            </div>

                            <span
                                class="w-fit rounded-full px-3 py-1 text-xs font-bold"
                                style="background: rgba(253,150,24,0.12); color: #FD9618;"
                            >
                                {{ $results->count() }} Results
                            </span>
                        </div>

                        <div class="mt-5 space-y-3">
                            @foreach ($results as $result)
                                @php
                                    $resultAllowedGuests = (int) ($result->final_allowed_guests ?? $result->allowed_guests ?? 1);
                                    $resultCheckedCount = (int) ($result->checked_in_count ?? 0);
                                    $resultRemainingGuests = max($resultAllowedGuests - $resultCheckedCount, 0);
                                @endphp

                                <button
                                    type="button"
                                    wire:click="selectInvitee({{ $result->id }})"
                                    class="group w-full rounded-2xl border border-gray-200 bg-gray-50 p-4 text-left transition hover:bg-white hover:shadow-sm dark:border-gray-800 dark:bg-gray-950 dark:hover:bg-gray-900"
                                >
                                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                        <div>
                                            <p class="font-bold text-gray-900 dark:text-white">
                                                {{ $result->name }}
                                            </p>

                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                {{ $result->phone ?? 'No phone' }} • {{ $result->serial_number }}
                                            </p>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-gray-600 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:ring-gray-700">
                                                {{ $result->cardType?->name ?? 'Card' }}
                                            </span>

                                            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $resultRemainingGuests > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                                Remaining: {{ $resultRemainingGuests }}
                                            </span>
                                        </div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Selected Invitee --}}
                @if ($invitee)
                    @php
                        $allowedGuests = (int) ($invitee->final_allowed_guests ?? $invitee->allowed_guests ?? 1);
                        $checkedCount = (int) ($invitee->checked_in_count ?? 0);
                        $remainingGuests = max($allowedGuests - $checkedCount, 0);
                        $cardStatus = $invitee->card_status ?? 'active';

                        $cardStatusClass = $cardStatus === 'active'
                            ? 'bg-green-100 text-green-700 ring-green-200'
                            : 'bg-red-100 text-red-700 ring-red-200';

                        $checkStatusClass = $remainingGuests <= 0
                            ? 'bg-green-100 text-green-700 ring-green-200'
                            : ($checkedCount > 0
                                ? 'text-orange-700 ring-orange-200'
                                : 'bg-gray-100 text-gray-700 ring-gray-200');

                        $checkStatusStyle = ($checkedCount > 0 && $remainingGuests > 0)
                            ? 'background: rgba(253,150,24,0.12);'
                            : '';

                        $checkStatusLabel = $remainingGuests <= 0
                            ? 'Fully Checked In'
                            : ($checkedCount > 0 ? 'Partially Checked In' : 'Not Checked In');

                        $guestUsagePercentage = $allowedGuests > 0 ? round(($checkedCount / $allowedGuests) * 100) : 0;
                        $eventTitle = $invitee->event?->title ?? $invitee->event?->name ?? 'Event';
                    @endphp

                    <div class="overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                        <div class="border-b border-gray-100 bg-gray-50 p-6 dark:border-gray-800 dark:bg-gray-950">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            class="rounded-full px-3 py-1 text-xs font-bold ring-1 {{ $checkStatusClass }}"
                                            style="{{ $checkStatusStyle }}"
                                        >
                                            {{ $checkStatusLabel }}
                                        </span>

                                        <span class="rounded-full px-3 py-1 text-xs font-bold ring-1 {{ $cardStatusClass }}">
                                            Card {{ strtoupper($cardStatus) }}
                                        </span>
                                    </div>

                                    <h2 class="mt-4 text-2xl font-extrabold text-gray-900 dark:text-white md:text-3xl">
                                        {{ $invitee->name }}
                                    </h2>

                                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                                        <span>{{ $invitee->phone ?? 'No phone number' }}</span>
                                        <span>{{ $eventTitle }}</span>
                                    </div>
                                </div>

                                <div class="rounded-2xl px-5 py-4 text-white shadow-sm" style="background: #213B73;">
                                    <p class="text-xs font-medium" style="color: rgba(255,255,255,0.72);">
                                        Serial Number
                                    </p>

                                    <p class="mt-1 text-xl font-extrabold tracking-wide">
                                        {{ $invitee->serial_number }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="p-6">
                            <div class="grid gap-4 md:grid-cols-4">
                                <div class="rounded-2xl bg-gray-50 p-4 ring-1 ring-gray-200 dark:bg-gray-950 dark:ring-gray-800">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Card Type</p>
                                    <p class="mt-2 text-lg font-extrabold text-gray-900 dark:text-white">
                                        {{ $invitee->cardType?->name ?? 'N/A' }}
                                    </p>
                                </div>

                                <div class="rounded-2xl bg-gray-50 p-4 ring-1 ring-gray-200 dark:bg-gray-950 dark:ring-gray-800">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Allowed Guests</p>
                                    <p class="mt-2 text-lg font-extrabold text-gray-900 dark:text-white">
                                        {{ $allowedGuests }}
                                    </p>
                                </div>

                                <div class="rounded-2xl bg-gray-50 p-4 ring-1 ring-gray-200 dark:bg-gray-950 dark:ring-gray-800">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Checked In</p>
                                    <p class="mt-2 text-lg font-extrabold text-gray-900 dark:text-white">
                                        {{ $checkedCount }}
                                    </p>
                                </div>

                                <div class="rounded-2xl bg-gray-50 p-4 ring-1 ring-gray-200 dark:bg-gray-950 dark:ring-gray-800">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Remaining</p>
                                    <p class="mt-2 text-lg font-extrabold {{ $remainingGuests > 0 ? 'text-green-700' : 'text-red-700' }}">
                                        {{ $remainingGuests }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-5 rounded-2xl bg-gray-50 p-4 ring-1 ring-gray-200 dark:bg-gray-950 dark:ring-gray-800">
                                <div class="mb-2 flex items-center justify-between text-sm">
                                    <span class="font-bold text-gray-900 dark:text-white">Guest usage</span>
                                    <span class="font-semibold text-gray-500 dark:text-gray-400">{{ $guestUsagePercentage }}%</span>
                                </div>

                                <div class="h-3 overflow-hidden rounded-full bg-white dark:bg-gray-800">
                                    <div
                                        class="h-full rounded-full"
                                        style="width: {{ min($guestUsagePercentage, 100) }}%; background: {{ $remainingGuests > 0 ? '#213B73' : '#22C55E' }};"
                                    ></div>
                                </div>
                            </div>

                            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                                <button
                                    type="button"
                                    wire:click="checkIn"
                                    @disabled($remainingGuests <= 0 || $cardStatus !== 'active')
                                    class="inline-flex items-center justify-center rounded-2xl px-6 py-3 text-sm font-extrabold text-white shadow-sm transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                                    style="background: #FD9618;"
                                >
                                    Confirm Check-in
                                </button>

                                <button
                                    type="button"
                                    wire:click="resetScanner"
                                    x-on:click="focusSearch()"
                                    class="inline-flex items-center justify-center rounded-2xl border border-gray-300 bg-white px-6 py-3 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                                >
                                    Finish / New Scan
                                </button>
                            </div>

                            @if ($remainingGuests <= 0)
                                <div class="mt-5 rounded-2xl bg-red-50 p-4 text-sm text-red-700 ring-1 ring-red-200">
                                    This card has already used all allowed guest entries.
                                </div>
                            @endif

                            @if ($cardStatus !== 'active')
                                <div class="mt-5 rounded-2xl bg-red-50 p-4 text-sm text-red-700 ring-1 ring-red-200">
                                    This card is not active and cannot be used for check-in.
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6 xl:col-span-4">
                {{-- Gate Process --}}
                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                    <h3 class="text-lg font-extrabold text-gray-900 dark:text-white">
                        Gate Process
                    </h3>

                    <div class="mt-5 space-y-4">
                        <div class="flex gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white" style="background: #213B73;">
                                1
                            </div>

                            <div>
                                <p class="text-sm font-bold text-gray-900 dark:text-white">Select event</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Confirm you are checking the correct event.</p>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white" style="background: #213B73;">
                                2
                            </div>

                            <div>
                                <p class="text-sm font-bold text-gray-900 dark:text-white">Scan or search</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Use camera QR scanner, serial number, phone number, or name.</p>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white" style="background: #FD9618;">
                                3
                            </div>

                            <div>
                                <p class="text-sm font-bold text-gray-900 dark:text-white">Confirm entry</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Validate card status and remaining guest limit.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Recent Check-ins --}}
                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-extrabold text-gray-900 dark:text-white">
                                Recent Check-ins
                            </h3>

                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Latest gate entries
                            </p>
                        </div>

                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-green-100 text-green-700">
                            <x-heroicon-o-clock class="h-5 w-5" />
                        </div>
                    </div>

                    <div class="mt-5 space-y-3">
                        @forelse ($this->recentCheckIns as $checkIn)
                            <div class="rounded-2xl bg-gray-50 p-4 ring-1 ring-gray-200 dark:bg-gray-950 dark:ring-gray-800">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate font-bold text-gray-900 dark:text-white">
                                            {{ $checkIn->invitee?->name ?? 'Unknown Invitee' }}
                                        </p>

                                        <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">
                                            {{ $checkIn->invitee?->serial_number }}
                                        </p>
                                    </div>

                                    <span class="shrink-0 rounded-full bg-green-100 px-2.5 py-1 text-xs font-bold text-green-700">
                                        +{{ $checkIn->guests_checked_in }}
                                    </span>
                                </div>

                                <div class="mt-3 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <span>{{ $checkIn->methodLabel() }}</span>
                                    <span>{{ optional($checkIn->checked_in_at)->format('H:i') }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl bg-gray-50 p-5 text-center ring-1 ring-gray-200 dark:bg-gray-950 dark:ring-gray-800">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-gray-400 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                                    <x-heroicon-o-clipboard-document-list class="h-6 w-6" />
                                </div>

                                <p class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">
                                    No check-ins yet
                                </p>

                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Recent entries will appear here.
                                </p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>

@push('scripts')
    <script src="https://unpkg.com/html5-qrcode"></script>
@endpush