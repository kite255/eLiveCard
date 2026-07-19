<x-filament-panels::page>
    @include(
        'filament.resources.event-resource.partials.rsvp-share-actions',
        ['record' => $record]
    )

    @php
        $stats = $this->stats;
        $invitees = $this->invitees;
        $event = $record;

        $canSendMessages = auth()->user()?->canSendMessages() ?? false;

        $formatStatus = fn (?string $value): string =>
            $value ? str($value)->replace('_', ' ')->title()->toString() : 'Not Sent';

        $normalizeDeliveryStatus = function (?string $status, ?string $channel = null): string {
            $status = $status
                ? str($status)->lower()->replace([' ', '-'], '_')->toString()
                : 'not_sent';

            $map = [
                'accepted' => 'sent',
                'submitted' => 'sent',
                'submitted_to_provider' => 'sent',
                'success' => 'sent',
                'successful' => 'delivered',
                'ok' => 'sent',
                'pending' => 'queued',
                'in_queue' => 'queued',
                'processing' => 'sending',
                'processing_now' => 'sending',
                'send_failed' => 'failed',
                'error' => 'failed',
                'bounce' => 'undelivered',
                'bounced' => 'undelivered',
                'not_delivered' => 'undelivered',
                'delivery_failed' => 'undelivered',
                'timeout' => 'expired',
                'timed_out' => 'expired',
                'invalid' => 'rejected',
                'invalid_number' => 'rejected',
                'blocked' => 'rejected',
                'denied' => 'rejected',
            ];

            $status = $map[$status] ?? $status;

            if ($channel === 'sms' && in_array($status, ['read', 'replied'], true)) {
                return 'delivered';
            }

            $allowed = $channel === 'whatsapp'
                ? ['not_sent', 'queued', 'sending', 'sent', 'delivered', 'read', 'replied', 'failed', 'unknown']
                : ['not_sent', 'queued', 'sending', 'sent', 'delivered', 'failed', 'undelivered', 'expired', 'rejected', 'unknown'];

            return in_array($status, $allowed, true) ? $status : 'unknown';
        };

        $deliveryPriority = fn (?string $status): int => match ($status) {
            'replied' => 90,
            'read' => 80,
            'delivered' => 70,
            'sent' => 60,
            'sending' => 50,
            'queued' => 40,
            'failed' => 30,
            'undelivered', 'expired', 'rejected' => 20,
            'not_sent' => 10,
            default => 0,
        };

        $actualProviderStatus = function ($log, ?string $channel = null) use ($normalizeDeliveryStatus): string {
            if (! $log) {
                return 'not_sent';
            }

            return $normalizeDeliveryStatus(
                $log->provider_status
                    ?? $log->delivery_status
                    ?? $log->status
                    ?? 'unknown',
                $channel,
            );
        };

        $actualProviderTime = function ($log, ?string $status = null) {
            if (! $log) {
                return null;
            }

            return match ($status) {
                'read' => $log->read_at ?? $log->delivered_at ?? $log->sent_at ?? $log->created_at,
                'delivered' => $log->delivered_at ?? $log->sent_at ?? $log->created_at,
                'failed', 'undelivered', 'expired', 'rejected' => $log->failed_at ?? $log->updated_at ?? $log->created_at,
                default => $log->sent_at ?? $log->created_at,
            };
        };

        $legacySmsSent = fn ($invitee): bool =>
            filled($invitee->sms_sent_at)
            || filled($invitee->sms_message_id)
            || in_array($invitee->sms_status, ['sent', 'delivered', 'queued', 'failed'], true)
            || in_array($invitee->invitation_sms_status, ['sent', 'delivered', 'queued', 'failed'], true);

        $legacyWhatsappSent = fn ($invitee): bool =>
            filled($invitee->last_whatsapp_sent_at)
            || filled($invitee->whatsapp_message_id)
            || $invitee->last_message_channel === 'whatsapp';

        $inviteeAllowedGuests = function ($invitee): int {
            $value = $invitee->final_allowed_guests
                ?? $invitee->allowed_guests
                ?? $invitee->cardType?->allowed_guests
                ?? $invitee->cardType?->allowed_people
                ?? 1;

            return max(1, (int) $value);
        };

        $inviteeConfirmedGuests = function ($invitee): int {
            if (in_array($invitee->rsvp_status, ['not_attending', 'declined'], true)) {
                return 0;
            }

            return max(0, (int) ($invitee->confirmed_guests ?? 0));
        };

        $totalInvitees = (int) ($stats['total'] ?? 0);
        $notAttendingCount = (int) ($stats['not_attending'] ?? $stats['declined'] ?? 0);
        $finalResponses = (int) ($stats['attending'] ?? 0) + $notAttendingCount;
        $responseRate = $totalInvitees > 0
            ? (int) round(($finalResponses / $totalInvitees) * 100)
            : 0;

        $summaryCards = [
            ['label' => 'Total Invitees', 'value' => $totalInvitees, 'icon' => 'heroicon-o-users', 'tone' => 'blue'],
            ['label' => 'Attending', 'value' => (int) ($stats['attending'] ?? 0), 'icon' => 'heroicon-o-check-circle', 'tone' => 'green'],
            ['label' => 'Not Attending', 'value' => $notAttendingCount, 'icon' => 'heroicon-o-x-circle', 'tone' => 'red'],
            ['label' => 'Pending RSVP', 'value' => (int) ($stats['pending'] ?? 0), 'icon' => 'heroicon-o-clock', 'tone' => 'amber'],
            ['label' => 'Opened', 'value' => (int) ($stats['opened'] ?? 0), 'icon' => 'heroicon-o-eye', 'tone' => 'sky'],
            ['label' => 'Unopened', 'value' => (int) ($stats['not_opened'] ?? 0), 'icon' => 'heroicon-o-eye-slash', 'tone' => 'gray'],
            ['label' => 'Failed Delivery', 'value' => (int) ($stats['failed'] ?? 0), 'icon' => 'heroicon-o-exclamation-triangle', 'tone' => 'red'],
            ['label' => 'Response Rate', 'value' => $responseRate.'%', 'icon' => 'heroicon-o-arrow-trending-up', 'tone' => 'blue'],
        ];

        $toneClasses = [
            'blue' => 'background:#EEF2FF;color:#213B73;',
            'green' => 'background:#DCFCE7;color:#15803D;',
            'red' => 'background:#FEE2E2;color:#B91C1C;',
            'amber' => 'background:#FFF7ED;color:#FD9618;',
            'sky' => 'background:#DBEAFE;color:#1D4ED8;',
            'gray' => 'background:#F1F5F9;color:#64748B;',
        ];

        $statusStyles = [
            'not_sent' => 'background:#F1F5F9;color:#475569;border-color:#E2E8F0;',
            'queued' => 'background:#E0E7FF;color:#4338CA;border-color:#C7D2FE;',
            'sending' => 'background:#FEF3C7;color:#B45309;border-color:#FDE68A;',
            'sent' => 'background:#DBEAFE;color:#1D4ED8;border-color:#BFDBFE;',
            'delivered' => 'background:#DCFCE7;color:#15803D;border-color:#BBF7D0;',
            'read' => 'background:#D1FAE5;color:#047857;border-color:#A7F3D0;',
            'replied' => 'background:#FEF3C7;color:#B45309;border-color:#FDE68A;',
            'failed' => 'background:#FEE2E2;color:#B91C1C;border-color:#FECACA;',
            'undelivered' => 'background:#FFE4E6;color:#BE123C;border-color:#FECDD3;',
            'expired' => 'background:#F3E8FF;color:#7E22CE;border-color:#E9D5FF;',
            'rejected' => 'background:#FEE2E2;color:#991B1B;border-color:#FCA5A5;',
            'unknown' => 'background:#F1F5F9;color:#334155;border-color:#CBD5E1;',
        ];

        $rsvpStyles = [
            'attending' => 'background:#DCFCE7;color:#15803D;border-color:#BBF7D0;',
            'not_attending' => 'background:#FEE2E2;color:#B91C1C;border-color:#FECACA;',
            'declined' => 'background:#FEE2E2;color:#B91C1C;border-color:#FECACA;',
            'maybe' => 'background:#FEF3C7;color:#B45309;border-color:#FDE68A;',
            'pending' => 'background:#FFF7ED;color:#C2410C;border-color:#FED7AA;',
        ];
    @endphp

    <style>
        .rsvp-shell{overflow:hidden;border:1px solid #E5E7EB;border-radius:22px;background:#fff;box-shadow:0 10px 25px rgba(15,23,42,.04)}
        .rsvp-header{padding:18px 20px;background:#213B73;color:#fff}
        .rsvp-summary{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));gap:10px;padding:16px}
        .rsvp-card{display:flex;align-items:center;gap:10px;padding:12px;border:1px solid #E5E7EB;border-radius:14px;background:#fff}
        .rsvp-icon{display:flex;width:38px;height:38px;align-items:center;justify-content:center;border-radius:12px;flex:0 0 38px}
        .rsvp-icon svg{width:18px;height:18px}
        .rsvp-card-value{font-size:22px;font-weight:900;line-height:1;color:#111827}
        .rsvp-card-label{margin-top:4px;font-size:11px;font-weight:800;color:#64748B}
        .rsvp-filters{display:grid;grid-template-columns:minmax(260px,2fr) repeat(3,minmax(150px,1fr)) auto;gap:10px;align-items:end;padding:0 16px 16px}
        .rsvp-label{display:block;margin-bottom:6px;font-size:11px;font-weight:900;text-transform:uppercase;color:#64748B}
        .rsvp-input,.rsvp-select{width:100%;height:42px;border:1px solid #CBD5E1;border-radius:12px;background:#fff;color:#111827;font-size:13px;outline:none}
        .rsvp-input{padding:0 13px}
        .rsvp-select{
            appearance:none;
            -webkit-appearance:none;
            -moz-appearance:none;
            padding:0 42px 0 13px;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M6 8l4 4 4-4' stroke='%2364748B' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat:no-repeat;
            background-position:right 12px center;
            background-size:18px 18px;
        }
        .rsvp-select::-ms-expand{display:none}
        .rsvp-input:focus,.rsvp-select:focus{border-color:#213B73;box-shadow:0 0 0 3px rgba(33,59,115,.10)}
        .rsvp-clear{height:42px;border:0;border-radius:12px;background:#F1F5F9;padding:0 14px;color:#334155;font-size:12px;font-weight:800;cursor:pointer}
        .rsvp-table-wrap{margin:0 16px 16px;overflow-x:auto;border:1px solid #E5E7EB;border-radius:16px;background:#fff}
        .rsvp-table{width:1240px;min-width:1240px;table-layout:fixed;border-collapse:separate;border-spacing:0}
        .rsvp-table th{padding:11px 10px;background:#F8FAFC;border-bottom:1px solid #E5E7EB;color:#111827;font-size:11px;font-weight:900;text-align:left;white-space:nowrap}
        .rsvp-table td{padding:11px 10px;border-bottom:1px solid #E5E7EB;color:#111827;font-size:12px;vertical-align:middle;overflow:hidden;white-space:nowrap}
        .rsvp-table tbody tr:last-child td{border-bottom:0}
        .rsvp-table tbody tr:hover td{background:#F8FAFC}
        .rsvp-table th:nth-child(1),.rsvp-table td:nth-child(1){width:44px;text-align:center}
        .rsvp-table th:nth-child(2),.rsvp-table td:nth-child(2){width:175px}
        .rsvp-table th:nth-child(3),.rsvp-table td:nth-child(3){width:175px}
        .rsvp-table th:nth-child(4),.rsvp-table td:nth-child(4){width:96px}
        .rsvp-table th:nth-child(5),.rsvp-table td:nth-child(5){width:58px}
        .rsvp-table th:nth-child(6),.rsvp-table td:nth-child(6){width:130px}
        .rsvp-table th:nth-child(7),.rsvp-table td:nth-child(7){width:112px}
        .rsvp-table th:nth-child(8),.rsvp-table td:nth-child(8){width:86px}
        .rsvp-table th:nth-child(9),.rsvp-table td:nth-child(9){width:110px}
        .rsvp-table th:nth-child(10),.rsvp-table td:nth-child(10){width:168px}
        .rsvp-table th:nth-child(11),.rsvp-table td:nth-child(11){width:106px;text-align:right}
        .rsvp-name{font-size:13px;font-weight:850;color:#0F172A}
        .rsvp-meta{margin-top:3px;font-size:10.5px;font-weight:650;color:#64748B}
        .rsvp-badge{display:inline-flex;align-items:center;justify-content:center;border:1px solid transparent;border-radius:999px;padding:5px 8px;font-size:11px;font-weight:850;line-height:1;white-space:nowrap}
        .rsvp-delivery{display:flex;flex-direction:column;gap:5px}
        .rsvp-delivery-line{display:flex;align-items:center;gap:7px;min-width:0}
        .rsvp-channel{display:flex;width:27px;height:27px;align-items:center;justify-content:center;border:1px solid #E5E7EB;border-radius:9px;background:#fff;flex:0 0 27px}
        .rsvp-channel svg{width:14px;height:14px}
        .rsvp-time{font-size:10px;font-weight:700;color:#64748B}
        .rsvp-comment{max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#475569}
        .rsvp-actions{display:flex;justify-content:flex-end;gap:6px}
        .rsvp-action{display:flex;width:31px;height:31px;align-items:center;justify-content:center;border:1px solid #E5E7EB;border-radius:9px;background:#fff;cursor:pointer}
        .rsvp-action svg{width:14px;height:14px}
        .rsvp-action:disabled{opacity:.5;cursor:not-allowed}
        .rsvp-pagination{border-top:1px solid #E5E7EB;padding:12px 16px}
        .rsvp-empty{padding:48px 20px;text-align:center}
        @media(max-width:1180px){.rsvp-summary{grid-template-columns:repeat(4,minmax(0,1fr))}.rsvp-filters{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:720px){.rsvp-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.rsvp-filters{grid-template-columns:1fr}}
    </style>

    <div class="mt-4 rsvp-shell">
        <div class="rsvp-header">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="text-sm font-black">RSVP Tracker</div>
                    <div class="mt-1 text-xs font-semibold text-white/75">
                        {{ $event->name ?? $event->title ?? 'Event' }}
                    </div>
                </div>

                <div class="text-left md:text-right">
                    <div class="text-sm font-black">
                        {{ (int) ($stats['sent'] ?? 0) }} sent ·
                        {{ (int) ($stats['opened'] ?? 0) }} opened ·
                        {{ $finalResponses }} responded
                    </div>
                    <div class="mt-1 text-xs font-semibold text-white/70">
                        {{ $responseRate }}% response rate
                    </div>
                </div>
            </div>
        </div>

        <div class="rsvp-summary">
            @foreach ($summaryCards as $card)
                <div class="rsvp-card">
                    <div class="rsvp-icon" style="{{ $toneClasses[$card['tone']] }}">
                        <x-filament::icon :icon="$card['icon']" />
                    </div>

                    <div>
                        <div class="rsvp-card-value">{{ $card['value'] }}</div>
                        <div class="rsvp-card-label">{{ $card['label'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="rsvp-filters">
            <div>
                <label class="rsvp-label">Search Invitee</label>
                <input
                    type="text"
                    wire:model.live.debounce.500ms="search"
                    class="rsvp-input"
                    placeholder="Name, phone, serial number, or short code"
                >
            </div>

            <div>
                <label class="rsvp-label">Status</label>
                <select wire:model.live="statusFilter" class="rsvp-select">
                    <option value="">All Statuses</option>
                    <option value="not_sent">Not Sent</option>
                    <option value="queued">Queued</option>
                    <option value="sending">Sending</option>
                    <option value="sent">Sent</option>
                    <option value="delivered">Delivered</option>
                    <option value="read">Read / WhatsApp</option>
                    <option value="replied">Replied / WhatsApp</option>
                    <option value="failed">Failed Delivery</option>
                    <option value="undelivered">Undelivered / SMS</option>
                    <option value="expired">Expired / SMS</option>
                    <option value="rejected">Rejected / SMS</option>
                    <option value="unknown">Unknown</option>
                    <option value="rsvp_pending">RSVP Pending</option>
                    <option value="attending">Attending</option>
                    <option value="not_attending">Not Attending</option>
                    <option value="maybe">Maybe</option>
                    <option value="opened">Opened Invitation</option>
                    <option value="not_opened">Not Opened</option>
                    <option value="recent_opens">Opened Recently</option>
                </select>
            </div>

            <div>
                <label class="rsvp-label">Channel</label>
                <select wire:model.live="channelFilter" class="rsvp-select">
                    <option value="">All Channels</option>
                    <option value="sms">SMS</option>
                    <option value="whatsapp">WhatsApp</option>
                </select>
            </div>

            <div>
                <label class="rsvp-label">Delivery Status</label>
                <select wire:model.live="deliveryFilter" class="rsvp-select">
                    <option value="">All Delivery Statuses</option>
                    <option value="sent">Sent</option>
                    <option value="delivered">Delivered</option>
                    <option value="pending">Pending</option>
                    <option value="failed">Failed</option>
                    <option value="not_sent">Not Sent</option>
                </select>
            </div>

            <button type="button" wire:click="clearFilters" class="rsvp-clear">
                Clear
            </button>
        </div>

        <div class="rsvp-table-wrap">
            <table class="rsvp-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Guest Name</th>
                        <th>Delivery</th>
                        <th>Opened</th>
                        <th>Views</th>
                        <th>Last Opened</th>
                        <th>RSVP</th>
                        <th>Guests</th>
                        <th>RSVP Date</th>
                        <th>Comment</th>
                        <th>Resend</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($invitees as $invitee)
                        @php
                            $conversationCollection = $invitee->relationLoaded('conversations')
                                ? $invitee->conversations
                                : collect();

                            $smsLog = $conversationCollection
                                ->where('channel', 'sms')
                                ->where('direction', 'outgoing')
                                ->sortByDesc('created_at')
                                ->first();

                            $whatsappLog = $conversationCollection
                                ->where('channel', 'whatsapp')
                                ->where('direction', 'outgoing')
                                ->sortByDesc('created_at')
                                ->first();

                            if (! $invitee->relationLoaded('conversations')) {
                                $smsLog = $invitee->conversations()
                                    ->where('channel', 'sms')
                                    ->where('direction', 'outgoing')
                                    ->latest()
                                    ->first();

                                $whatsappLog = $invitee->conversations()
                                    ->where('channel', 'whatsapp')
                                    ->where('direction', 'outgoing')
                                    ->latest()
                                    ->first();
                            }

                            $hasLegacySms = $legacySmsSent($invitee);
                            $hasLegacyWhatsapp = $legacyWhatsappSent($invitee);

                            $smsStatus = $smsLog
                                ? $actualProviderStatus($smsLog, 'sms')
                                : ($hasLegacySms
                                    ? $normalizeDeliveryStatus(
                                        $invitee->sms_status
                                            ?? $invitee->invitation_sms_status
                                            ?? 'sent',
                                        'sms',
                                    )
                                    : 'not_sent');

                            $whatsappStatus = $whatsappLog
                                ? $actualProviderStatus($whatsappLog, 'whatsapp')
                                : ($hasLegacyWhatsapp
                                    ? $normalizeDeliveryStatus(
                                        $invitee->last_message_status ?? 'sent',
                                        'whatsapp',
                                    )
                                    : 'not_sent');

                            $hasSms = filled($smsLog) || $hasLegacySms;
                            $hasWhatsapp = filled($whatsappLog) || $hasLegacyWhatsapp;

                            $deliveryChannel = match (true) {
                                $hasSms && $hasWhatsapp => 'multi',
                                $hasWhatsapp => 'whatsapp',
                                $hasSms => 'sms',
                                default => 'not_sent',
                            };

                            $bestChannel = $deliveryPriority($whatsappStatus) >= $deliveryPriority($smsStatus)
                                ? 'whatsapp'
                                : 'sms';

                            if (! $hasWhatsapp && $bestChannel === 'whatsapp') {
                                $bestChannel = 'sms';
                            }

                            if (! $hasSms && $bestChannel === 'sms') {
                                $bestChannel = 'whatsapp';
                            }

                            $deliveryStatus = $deliveryChannel === 'not_sent'
                                ? 'not_sent'
                                : ($bestChannel === 'whatsapp' ? $whatsappStatus : $smsStatus);

                            $bestLog = $bestChannel === 'whatsapp' ? $whatsappLog : $smsLog;

                            $deliveryTime = $bestLog
                                ? $actualProviderTime($bestLog, $deliveryStatus)
                                : ($bestChannel === 'sms'
                                    ? ($invitee->last_sms_sent_at ?? $invitee->sms_sent_at ?? $invitee->invitation_sms_sent_at)
                                    : ($invitee->last_whatsapp_sent_at ?? null));

                            $smsTime = $smsLog
                                ? $actualProviderTime($smsLog, $smsStatus)
                                : ($invitee->last_sms_sent_at ?? $invitee->sms_sent_at ?? $invitee->invitation_sms_sent_at);

                            $whatsappTime = $whatsappLog
                                ? $actualProviderTime($whatsappLog, $whatsappStatus)
                                : ($invitee->last_whatsapp_sent_at ?? null);

                            $hasOpenedInvitation = (bool) (
                                $invitee->has_opened_invitation
                                ?? filled($invitee->first_opened_at)
                            );

                            $lastOpenedHuman = $invitee->last_opened_human
                                ?? ($invitee->last_opened_at
                                    ? $invitee->last_opened_at->diffForHumans()
                                    : '—');

                            $allowedGuestLimit = $inviteeAllowedGuests($invitee);
                            $confirmedGuestCount = $inviteeConfirmedGuests($invitee);

                            $guestCountLabel = in_array(
                                $invitee->rsvp_status,
                                ['attending', 'not_attending', 'declined'],
                                true,
                            )
                                ? $confirmedGuestCount.' of '.$allowedGuestLimit
                                : '— of '.$allowedGuestLimit;

                            $rsvpStatus = $invitee->rsvp_status ?: 'pending';
                        @endphp

                        <tr wire:key="invitee-row-{{ $invitee->id }}">
                            <td>{{ $invitees instanceof \Illuminate\Contracts\Pagination\Paginator ? $invitees->firstItem() + $loop->index : $loop->iteration }}</td>

                            <td>
                                <div class="rsvp-name">{{ $invitee->name }}</div>
                                <div class="rsvp-meta">{{ $invitee->phone }}</div>
                            </td>

                            <td>
                                <div class="rsvp-delivery">
                                    @if ($deliveryChannel === 'multi')
                                        <div class="rsvp-delivery-line">
                                            <span class="rsvp-channel" style="color:#213B73;">
                                                <x-filament::icon icon="heroicon-o-chat-bubble-left" />
                                            </span>
                                            <div>
                                                <span class="rsvp-badge" style="{{ $statusStyles[$smsStatus] ?? $statusStyles['unknown'] }}">
                                                    {{ $formatStatus($smsStatus) }}
                                                </span>
                                                <span class="rsvp-time">{{ $smsTime ? optional($smsTime)->format('d M H:i') : '—' }}</span>
                                            </div>
                                        </div>

                                        <div class="rsvp-delivery-line">
                                            <span class="rsvp-channel" style="color:#16A34A;">
                                                <x-filament::icon icon="heroicon-o-device-phone-mobile" />
                                            </span>
                                            <div>
                                                <span class="rsvp-badge" style="{{ $statusStyles[$whatsappStatus] ?? $statusStyles['unknown'] }}">
                                                    {{ $formatStatus($whatsappStatus) }}
                                                </span>
                                                <span class="rsvp-time">{{ $whatsappTime ? optional($whatsappTime)->format('d M H:i') : '—' }}</span>
                                            </div>
                                        </div>
                                    @else
                                        <div class="rsvp-delivery-line">
                                            <span class="rsvp-channel" style="{{ $deliveryChannel === 'whatsapp' ? 'color:#16A34A;' : 'color:#213B73;' }}">
                                                <x-filament::icon :icon="$deliveryChannel === 'whatsapp' ? 'heroicon-o-device-phone-mobile' : 'heroicon-o-chat-bubble-left'" />
                                            </span>
                                            <div>
                                                <span class="rsvp-badge" style="{{ $statusStyles[$deliveryStatus] ?? $statusStyles['unknown'] }}">
                                                    {{ $formatStatus($deliveryStatus) }}
                                                </span>
                                                <span class="rsvp-time">{{ $deliveryTime ? optional($deliveryTime)->format('d M H:i') : '—' }}</span>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </td>

                            <td>
                                <span class="rsvp-badge" style="{{ $hasOpenedInvitation ? 'background:#DBEAFE;color:#213B73;border-color:#BFDBFE;' : 'background:#F1F5F9;color:#64748B;border-color:#E2E8F0;' }}">
                                    {{ $hasOpenedInvitation ? 'Opened' : 'Not Opened' }}
                                </span>
                            </td>

                            <td>{{ (int) ($invitee->open_count ?? 0) }}</td>

                            <td>
                                <div>{{ $lastOpenedHuman }}</div>
                                @if ($invitee->last_opened_at)
                                    <div class="rsvp-meta">{{ $invitee->last_opened_at?->format('d M H:i') }}</div>
                                @endif
                            </td>

                            <td>
                                <span class="rsvp-badge" style="{{ $rsvpStyles[$rsvpStatus] ?? 'background:#F1F5F9;color:#475569;border-color:#E2E8F0;' }}">
                                    {{ $formatStatus($rsvpStatus) }}
                                </span>
                            </td>

                            <td>
                                <span class="rsvp-badge" style="{{ $rsvpStyles[$rsvpStatus] ?? 'background:#F8FAFC;color:#475569;border-color:#E2E8F0;' }}">
                                    {{ $guestCountLabel }}
                                </span>
                            </td>

                            <td>{{ $invitee->rsvp_confirmed_at?->format('d M Y') ?? '—' }}</td>

                            <td>
                                <div class="rsvp-comment" title="{{ $invitee->last_reply_message ?: 'No comment' }}">
                                    {{ $invitee->last_reply_message ?: 'No comment' }}
                                </div>
                                @if ($invitee->last_reply_at)
                                    <div class="rsvp-meta">{{ $invitee->last_reply_at?->format('d M H:i') }}</div>
                                @endif
                            </td>

                            <td>
                                @if ($canSendMessages)
                                    <div class="rsvp-actions">
                                        <button
                                            type="button"
                                            class="rsvp-action"
                                            style="color:#213B73;"
                                            onclick="return confirm('Resend the SMS invitation to this invitee?')"
                                            wire:click="resendSmsInvitation({{ $invitee->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="resendSmsInvitation({{ $invitee->id }})"
                                            title="Resend SMS"
                                        >
                                            <x-filament::icon icon="heroicon-o-chat-bubble-left" />
                                        </button>

                                        <button
                                            type="button"
                                            class="rsvp-action"
                                            style="color:#15803D;"
                                            onclick="return confirm('Resend the WhatsApp invitation to this invitee?')"
                                            wire:click="resendWhatsAppInvitation({{ $invitee->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="resendWhatsAppInvitation({{ $invitee->id }})"
                                            title="Resend WhatsApp"
                                        >
                                            <x-filament::icon icon="heroicon-o-device-phone-mobile" />
                                        </button>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400">Restricted</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11">
                                <div class="rsvp-empty">
                                    <div class="text-lg font-black text-gray-900">No invitees found</div>
                                    <div class="mt-1 text-sm text-gray-500">No invitees match the current filters.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if ($invitees instanceof \Illuminate\Contracts\Pagination\Paginator)
                <div class="rsvp-pagination">
                    {{ $invitees->links() }}
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
