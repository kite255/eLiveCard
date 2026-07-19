@php
    $rsvpShareShowUrl = route(
        'admin.events.rsvp-share.show',
        ['event' => $record]
    );

    $rsvpShareGenerateUrl = route(
        'admin.events.rsvp-share.generate',
        ['event' => $record]
    );

    $rsvpShareDisableUrl = route(
        'admin.events.rsvp-share.disable',
        ['event' => $record]
    );
@endphp

<div
    x-data="{
        loading: false,
        enabled: @js($record->hasValidRsvpShareLink()),
        url: @js($record->rsvp_share_url),
        showPhone: @js((bool) $record->rsvp_share_show_phone),
        expiresInDays: '',
        async request(method, endpoint, payload = null) {
            this.loading = true;

            try {
                const response = await fetch(endpoint, {
                    method,
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                    },
                    body: payload ? JSON.stringify(payload) : null,
                });

                const data = await response.json();

                if (! response.ok) {
                    throw new Error(
                        data.message ?? 'The request could not be completed.'
                    );
                }

                return data;
            } finally {
                this.loading = false;
            }
        },
        async generate(regenerate = false) {
            const data = await this.request(
                'POST',
                @js($rsvpShareGenerateUrl),
                {
                    regenerate,
                    show_phone: this.showPhone,
                    expires_in_days: this.expiresInDays || null,
                }
            );

            this.url = data.url;
            this.enabled = true;

            await this.copy();
        },
        async copy() {
            if (! this.url) {
                await this.generate(false);
                return;
            }

            try {
                await navigator.clipboard.writeText(this.url);
                new FilamentNotification()
                    .title('Client RSVP link copied')
                    .success()
                    .send();
            } catch (error) {
                window.prompt('Copy this RSVP link:', this.url);
            }
        },
        async disable() {
            if (! confirm('Disable this client RSVP link?')) {
                return;
            }

            await this.request(
                'DELETE',
                @js($rsvpShareDisableUrl)
            );

            this.enabled = false;

            new FilamentNotification()
                .title('Client RSVP link disabled')
                .success()
                .send();
        },
    }"
    class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900"
>
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h3 class="text-sm font-bold text-gray-950 dark:text-white">
                Client RSVP Report Link
            </h3>

            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Share a secure, read-only RSVP dashboard with the client.
            </p>

            <div class="mt-3 flex flex-wrap gap-4">
                <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-600 dark:text-gray-300">
                    <input
                        type="checkbox"
                        x-model="showPhone"
                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                    >
                    Show phone numbers
                </label>

                <label class="flex items-center gap-2 text-xs font-medium text-gray-600 dark:text-gray-300">
                    Expires after
                    <input
                        type="number"
                        min="1"
                        max="365"
                        x-model="expiresInDays"
                        placeholder="No expiry"
                        class="w-28 rounded-lg border-gray-300 text-xs dark:border-white/10 dark:bg-gray-800"
                    >
                    days
                </label>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <x-filament::button
                type="button"
                color="primary"
                icon="heroicon-o-clipboard"
                x-on:click="copy()"
                x-bind:disabled="loading"
            >
                Copy Client Link
            </x-filament::button>

            <x-filament::button
                type="button"
                color="warning"
                outlined
                icon="heroicon-o-arrow-path"
                x-on:click="generate(true)"
                x-bind:disabled="loading"
            >
                Regenerate
            </x-filament::button>

            <x-filament::button
                type="button"
                color="danger"
                outlined
                icon="heroicon-o-link-slash"
                x-show="enabled"
                x-on:click="disable()"
                x-bind:disabled="loading"
            >
                Disable
            </x-filament::button>
        </div>
    </div>

    <div
        x-show="url"
        x-cloak
        class="mt-4 rounded-xl bg-gray-50 p-3 dark:bg-white/5"
    >
        <p class="break-all text-xs font-medium text-gray-600 dark:text-gray-300" x-text="url"></p>
    </div>
</div>
