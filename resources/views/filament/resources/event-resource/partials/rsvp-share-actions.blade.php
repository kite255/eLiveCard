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
        copied: false,
        enabled: @js($record->hasValidRsvpShareLink()),
        url: @js($record->rsvp_share_url),
        showPhone: @js((bool) $record->rsvp_share_show_phone),
        expiresInDays: '',

        notify(title, type = 'success') {
            try {
                if (typeof FilamentNotification !== 'undefined') {
                    const notification = new FilamentNotification().title(title);

                    if (type === 'danger') {
                        notification.danger();
                    } else if (type === 'warning') {
                        notification.warning();
                    } else {
                        notification.success();
                    }

                    notification.send();
                    return;
                }
            } catch (error) {
                console.warn('Filament notification unavailable:', error);
            }

            alert(title);
        },

        async request(method, endpoint, payload = null) {
            this.loading = true;

            try {
                const response = await fetch(endpoint, {
                    method,
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                    },
                    body: payload ? JSON.stringify(payload) : null,
                });

                let data = {};

                try {
                    data = await response.json();
                } catch (error) {
                    data = {};
                }

                if (! response.ok) {
                    throw new Error(
                        data.message
                        ?? `The request failed with status ${response.status}.`
                    );
                }

                return data;
            } finally {
                this.loading = false;
            }
        },

        async generate(regenerate = false) {
            try {
                const data = await this.request(
                    'POST',
                    @js($rsvpShareGenerateUrl),
                    {
                        regenerate,
                        show_phone: this.showPhone,
                        expires_in_days: this.expiresInDays || null,
                    }
                );

                if (! data.url) {
                    throw new Error('The server did not return a client RSVP link.');
                }

                this.url = data.url;
                this.enabled = true;

                await this.copy(false);
            } catch (error) {
                console.error('Unable to generate RSVP link:', error);

                this.notify(
                    error.message ?? 'Unable to generate the client RSVP link.',
                    'danger'
                );
            }
        },

        async copyToClipboard(value) {
            if (
                navigator.clipboard
                && typeof navigator.clipboard.writeText === 'function'
                && window.isSecureContext
            ) {
                await navigator.clipboard.writeText(value);
                return;
            }

            const textarea = document.createElement('textarea');

            textarea.value = value;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.top = '-9999px';
            textarea.style.left = '-9999px';
            textarea.style.opacity = '0';
            textarea.style.pointerEvents = 'none';

            document.body.appendChild(textarea);

            textarea.focus();
            textarea.select();
            textarea.setSelectionRange(0, textarea.value.length);

            let copiedSuccessfully = false;

            try {
                copiedSuccessfully = document.execCommand('copy');
            } finally {
                document.body.removeChild(textarea);
            }

            if (! copiedSuccessfully) {
                throw new Error('Browser clipboard access was denied.');
            }
        },

        async copy(generateWhenMissing = true) {
            if (! this.url) {
                if (generateWhenMissing) {
                    await this.generate(false);
                }

                return;
            }

            try {
                await this.copyToClipboard(this.url);

                this.copied = true;
                this.notify('Client RSVP link copied');

                window.setTimeout(() => {
                    this.copied = false;
                }, 2200);
            } catch (error) {
                console.error('Unable to copy RSVP link:', error);

                window.prompt(
                    'Copy this client RSVP link:',
                    this.url
                );
            }
        },

        async disable() {
            if (! confirm('Disable this client RSVP link?')) {
                return;
            }

            try {
                await this.request(
                    'DELETE',
                    @js($rsvpShareDisableUrl)
                );

                this.enabled = false;
                this.url = '';
                this.copied = false;

                this.notify('Client RSVP link disabled');
            } catch (error) {
                console.error('Unable to disable RSVP link:', error);

                this.notify(
                    error.message ?? 'Unable to disable the client RSVP link.',
                    'danger'
                );
            }
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
                x-on:click.prevent.stop="copy()"
                x-bind:disabled="loading"
            >
                <span x-text="copied ? 'Copied!' : (loading ? 'Working...' : 'Copy Client Link')">
                    Copy Client Link
                </span>
            </x-filament::button>

            <x-filament::button
                type="button"
                color="warning"
                outlined
                icon="heroicon-o-arrow-path"
                x-on:click.prevent.stop="generate(true)"
                x-bind:disabled="loading"
            >
                <span x-text="loading ? 'Working...' : 'Regenerate'">
                    Regenerate
                </span>
            </x-filament::button>

            <x-filament::button
                type="button"
                color="danger"
                outlined
                icon="heroicon-o-link-slash"
                x-show="enabled"
                x-cloak
                x-on:click.prevent.stop="disable()"
                x-bind:disabled="loading"
            >
                Disable
            </x-filament::button>
        </div>
    </div>

    <div
        x-show="url"
        x-cloak
        class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5"
    >
        <div class="flex items-start justify-between gap-3">
            <p
                class="min-w-0 flex-1 break-all text-xs font-medium text-gray-600 dark:text-gray-300"
                x-text="url"
            ></p>

            <button
                type="button"
                class="shrink-0 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-bold text-[#213B73] transition hover:border-[#213B73] hover:bg-[#F8FAFC] dark:border-white/10 dark:bg-gray-900"
                x-on:click.prevent.stop="copy(false)"
                x-bind:disabled="loading"
            >
                <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
            </button>
        </div>
    </div>
</div>
