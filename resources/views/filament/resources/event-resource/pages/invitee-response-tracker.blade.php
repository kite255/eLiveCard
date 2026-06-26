<x-filament-panels::page>
    @php
        $stats = $this->stats;
        $invitees = $this->invitees;

        $statusBadge = function (?string $status) {
            return match ($status) {
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
                default => 'background:#F1F5F9;color:#475569;border-color:#E2E8F0;',
            };
        };

        $channelBadge = function (?string $channel) {
            return match ($channel) {
                'whatsapp' => 'background:#DCFCE7;color:#15803D;border-color:#BBF7D0;',
                'sms' => 'background:#DBEAFE;color:#1D4ED8;border-color:#BFDBFE;',
                'multi' => 'background:#FEF3C7;color:#B45309;border-color:#FDE68A;',
                default => 'background:#F1F5F9;color:#475569;border-color:#E2E8F0;',
            };
        };

        $rsvpBadge = function (?string $status) {
            return match ($status) {
                'attending' => 'background:#DCFCE7;color:#15803D;border-color:#BBF7D0;',
                'not_attending', 'declined' => 'background:#FEE2E2;color:#B91C1C;border-color:#FECACA;',
                'maybe' => 'background:#FEF3C7;color:#B45309;border-color:#FDE68A;',
                'pending' => 'background:#FFF7ED;color:#C2410C;border-color:#FED7AA;',
                default => 'background:#F1F5F9;color:#475569;border-color:#E2E8F0;',
            };
        };

        $formatStatus = function (?string $value) {
            return $value ? str($value)->replace('_', ' ')->title() : 'Not Sent';
        };

        $formatChannel = function (?string $value) {
            return match ($value) {
                'whatsapp' => 'WhatsApp',
                'sms' => 'SMS',
                'multi' => 'Multi',
                default => 'Not Sent',
            };
        };

        $normalizeDeliveryStatus = function (?string $status, ?string $channel = null) {
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

            if ($channel === 'sms' && ! in_array($status, [
                'not_sent', 'queued', 'sending', 'sent', 'delivered',
                'failed', 'undelivered', 'expired', 'rejected', 'unknown',
            ], true)) {
                return 'unknown';
            }

            if ($channel === 'whatsapp' && ! in_array($status, [
                'not_sent', 'queued', 'sending', 'sent', 'delivered',
                'read', 'replied', 'failed', 'unknown',
            ], true)) {
                return 'unknown';
            }

            return $status;
        };

        $deliveryPriority = function (?string $status) {
            return match ($status) {
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
        };

        $actualProviderStatus = function ($log, ?string $channel = null) use ($normalizeDeliveryStatus) {
            if (! $log) {
                return 'not_sent';
            }

            return $normalizeDeliveryStatus(
                $log->provider_status
                    ?? $log->delivery_status
                    ?? $log->status
                    ?? 'unknown',
                $channel
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

        $providerReference = function ($log) {
            if (! $log) {
                return null;
            }

            return $log->provider_message_id
                ?? $log->message_id
                ?? $log->provider_reference
                ?? null;
        };

        $legacySmsSent = function ($invitee) {
            return filled($invitee->sms_sent_at)
                || filled($invitee->sms_message_id)
                || in_array($invitee->sms_status, ['sent', 'delivered', 'queued', 'failed'], true)
                || in_array($invitee->invitation_sms_status, ['sent', 'delivered', 'queued', 'failed'], true);
        };

        $legacyWhatsappSent = function ($invitee) {
            return filled($invitee->last_whatsapp_sent_at)
                || filled($invitee->whatsapp_message_id)
                || $invitee->last_message_channel === 'whatsapp';
        };

        $responseRate = ($stats['total'] ?? 0) > 0
            ? round((($stats['attending'] ?? 0) + ($stats['replied'] ?? 0)) / max(($stats['total'] ?? 1), 1) * 100)
            : 0;
    @endphp

    <style>
        .elive-page {
            --elive-blue: #213B73;
            --elive-orange: #FD9618;
            --elive-dark: #111827;
            --elive-bg: #F8FAFC;
            --elive-border: #E5E7EB;
        }

        .elive-shell {
            overflow: hidden;
            border-radius: 18px;
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
            margin-top: 0;
        }

        .elive-table-header {
            background: linear-gradient(135deg, #213B73 0%, #172C5C 100%);
            color: #FFFFFF;
            padding: 12px 16px;
        }

        .elive-hero-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .elive-hero-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 10px;
            background: rgba(255, 255, 255, .12);
            color: #FFFFFF;
            flex: 0 0 32px;
        }

        .elive-hero-kicker {
            display: none;
        }

        .elive-header-stat {
            display: flex;
            align-items: center;
            gap: 7px;
            color: #FFFFFF;
            min-width: 58px;
        }

        .elive-header-stat-number {
            font-size: 16px;
            font-weight: 900;
            line-height: 1;
        }

        .elive-header-stat-label {
            margin-top: 2px;
            font-size: 9px;
            font-weight: 800;
            color: rgba(255, 255, 255, .72);
            line-height: 1;
        }

        .elive-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            padding: 14px 16px 12px;
            background: #FFFFFF;
        }

        @media (max-width: 1024px) {
            .elive-summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .elive-summary-grid {
                grid-template-columns: 1fr;
            }
        }

        .elive-summary-card {
            border: 1px solid #E5E7EB;
            border-radius: 14px;
            padding: 11px 12px;
            background: #FFFFFF;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: none;
        }

        .elive-summary-icon {
            width: 36px;
            height: 36px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 36px;
        }

        .elive-summary-icon svg {
            width: 17px;
            height: 17px;
            display: block;
        }

        .elive-summary-value {
            font-size: 22px;
            font-weight: 900;
            line-height: 1;
            color: #111827;
        }

        .elive-summary-label {
            margin-top: 3px;
            font-size: 11px;
            font-weight: 800;
            color: #475569;
        }

        .elive-filters {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 10px;
            align-items: end;
            padding: 0 16px 14px;
            background: #FFFFFF;
        }

        @media (max-width: 900px) {
            .elive-filters {
                grid-template-columns: 1fr;
            }
        }

        .elive-label {
            display: block;
            margin-bottom: 6px;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            color: #64748B;
        }

        .elive-input,
        .elive-select,
        .elive-textarea {
            width: 100%;
            border: 1px solid #CBD5E1;
            border-radius: 12px;
            background: #FFFFFF;
            color: #111827;
            font-size: 13px;
            outline: none;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .elive-input:focus,
        .elive-select:focus,
        .elive-textarea:focus {
            border-color: #213B73;
            box-shadow: 0 0 0 3px rgba(33, 59, 115, .08);
        }

        .elive-input,
        .elive-select {
            height: 42px;
            padding: 0 14px;
        }

        .elive-textarea {
            min-height: 120px;
            padding: 12px 14px;
            resize: vertical;
        }

        .elive-input:focus,
        .elive-select:focus,
        .elive-textarea:focus {
            border-color: var(--elive-blue);
            box-shadow: 0 0 0 3px rgba(33, 59, 115, 0.12);
        }

        .elive-select {
            appearance: auto;
            background-image: none !important;
        }

        .elive-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            table-layout: fixed;
        }

        .elive-table th,
        .elive-table td {
            vertical-align: middle;
        }

        .elive-table th:nth-child(1),
        .elive-table td:nth-child(1) {
            width: 52px;
            text-align: center;
        }

        .elive-table th:nth-child(2),
        .elive-table td:nth-child(2) {
            width: 23%;
            text-align: left;
        }

        .elive-table th:nth-child(3),
        .elive-table td:nth-child(3) {
            width: 18%;
            text-align: left;
        }

        .elive-table th:nth-child(4),
        .elive-table td:nth-child(4) {
            width: 13%;
            text-align: left;
        }

        .elive-table th:nth-child(5),
        .elive-table td:nth-child(5) {
            width: 13%;
            text-align: left;
            white-space: nowrap;
        }

        .elive-table th:nth-child(6),
        .elive-table td:nth-child(6) {
            width: 22%;
            text-align: left;
        }

        .elive-table th:nth-child(7),
        .elive-table td:nth-child(7) {
            width: 96px;
            text-align: right;
        }

        .elive-table td:nth-child(3),
        .elive-table td:nth-child(4),
        .elive-table td:nth-child(5),
        .elive-table td:nth-child(7) {
            white-space: nowrap;
        }

        .elive-cell-center {
            display: flex;
            align-items: center;
            min-height: 38px;
        }

        .elive-cell-center.end {
            justify-content: flex-end;
        }

        .elive-row-stack {
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 42px;
        }


        .elive-table-wrap {
            margin: 0 20px 20px;
            overflow-x: auto;
            border: 1px solid #E5E7EB;
            border-radius: 18px;
            background: #FFFFFF;
        }

        .elive-table {
            width: 100%;
            min-width: 980px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .elive-table thead th {
            background: #F8FAFC;
            color: #111827;
            font-size: 12px;
            font-weight: 900;
            text-align: left;
            padding: 14px 16px;
            border-bottom: 1px solid #E5E7EB;
            white-space: nowrap;
        }

        .elive-table tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid #E5E7EB;
            color: #111827;
            font-size: 13px;
            vertical-align: middle;
            white-space: nowrap;
        }

        .elive-table tbody tr:last-child td {
            border-bottom: none;
        }

        .elive-table tbody tr:hover td {
            background: #F8FAFC;
        }

        .elive-guest-name {
            font-size: 14px;
            font-weight: 850;
            color: #0F172A;
        }

        .elive-guest-meta {
            margin-top: 3px;
            font-size: 12px;
            font-weight: 600;
            color: #64748B;
        }

        .elive-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid transparent;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 850;
            white-space: nowrap;
            line-height: 1;
        }

        .elive-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: none;
            border-radius: 12px;
            padding: 9px 13px;
            font-size: 12px;
            font-weight: 850;
            cursor: pointer;
            text-decoration: none;
            transition: all .15s ease;
            white-space: nowrap;
        }

        .elive-btn:hover {
            transform: translateY(-1px);
        }

        .elive-btn-primary { background: #213B73; color: #FFFFFF; }
        .elive-btn-orange { background: #FD9618; color: #FFFFFF; }
        .elive-btn-gray { background: #F1F5F9; color: #334155; }

        .elive-icon-btn {
            width: 42px;
            height: 42px;
            padding: 0;
            border-radius: 14px;
        }

        .elive-icon-btn svg,
        .elive-channel-icon svg {
            width: 18px;
            height: 18px;
            display: block;
        }

        .elive-channel-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 9px;
            border: 1px solid #E5E7EB;
            background: #FFFFFF;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            flex: 0 0 28px;
        }

        .elive-channel-icon svg {
            width: 14px;
            height: 14px;
            display: block;
        }

        .elive-delivery-stack {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            gap: 5px;
            min-height: 42px;
        }

        .elive-delivery-line {
            display: grid;
            grid-template-columns: 28px minmax(0, 1fr);
            align-items: center;
            column-gap: 7px;
            min-width: 0;
            padding: 0;
            line-height: 1.15;
        }

        .elive-delivery-line + .elive-delivery-line {
            margin-top: 0;
        }

        .elive-delivery-text {
            display: inline-grid;
            grid-template-columns: auto auto;
            align-items: center;
            column-gap: 6px;
            min-width: 0;
        }

        .elive-delivery-status {
            background: transparent !important;
            border: none !important;
            border-radius: 0 !important;
            padding: 0 !important;
            min-width: auto !important;
            font-size: 11px;
            font-weight: 850;
            line-height: 1.15;
        }

        .elive-delivery-time {
            font-size: 10.5px;
            font-weight: 700;
            color: #64748B;
            line-height: 1.15;
            padding-left: 0;
            white-space: nowrap;
        }


        .elive-page {
            margin-top: -6px;
        }

        .elive-page-wrap-fix {
            height: 0;
            overflow: hidden;
        }


        .elive-resend-actions {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
            white-space: nowrap;
            min-height: 42px;
        }

        .elive-resend-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 10px;
            border: 1px solid #E5E7EB;
            background: #FFFFFF;
            color: #213B73;
            transition: background .15s ease, border-color .15s ease, transform .15s ease;
        }

        .elive-resend-btn:hover {
            background: #F8FAFC;
            border-color: #CBD5E1;
            transform: translateY(-1px);
        }

        .elive-resend-btn[disabled] {
            opacity: .55;
            cursor: not-allowed;
            transform: none;
        }

        .elive-resend-btn.whatsapp {
            color: #15803D;
        }

        .elive-resend-btn.sms {
            color: #213B73;
        }

        .elive-resend-btn svg {
            width: 15px;
            height: 15px;
            display: block;
        }

        .elive-sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }


        .elive-row-stack,
        .elive-row-stack {
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 36px;
        }

        .elive-status-cell {
            display: flex;
            align-items: center;
            min-height: 42px;
        }

        .elive-date-cell {
            display: flex;
            align-items: center;
            min-height: 42px;
            color: #475569;
            font-weight: 750;
        }

        .elive-comment-preview {
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #475569;
            font-size: 12px;
            font-weight: 650;
        }

        .elive-modal-section {
            border: 1px solid #E5E7EB;
            border-radius: 16px;
            background: #FFFFFF;
            padding: 14px;
        }

        .elive-modal-title {
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            color: #64748B;
            margin-bottom: 10px;
        }

        .elive-help-box {
            background: #FEF3C7;
            color: #92400E;
            border-radius: 14px;
            padding: 11px;
            font-size: 13px;
        }


        @media (max-width: 768px) {
            .elive-table {
                min-width: 980px;
            }

            .elive-table-wrap {
                overflow-x: auto;
            }
        }

        .elive-empty {
            padding: 48px 20px;
            text-align: center;
        }
    </style>

    <div class="elive-page space-y-3">
        <div class="elive-shell">
            {{-- Header --}}
            <div class="elive-table-header">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div class="elive-hero-title">
                        <div class="elive-hero-icon">
                            <svg viewBox="0 0 24 24" class="h-5 w-5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 6 9 17l-5-5" />
                            </svg>
                        </div>

                        <div>
                            <div class="elive-hero-kicker">Invitee Responses</div>
                            <div class="text-sm font-black leading-tight">
                                RSVP Tracker
                            </div>

                            <div class="mt-0.5 text-[11px] font-semibold text-white/75">
                                {{ $record->name ?? $record->title ?? 'Event' }}
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="elive-header-stat">
                            <div class="elive-header-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 6h16v12H4z" />
                                    <path d="m4 7 8 6 8-6" />
                                </svg>
                            </div>
                            <div>
                                <div class="elive-header-stat-number">
                                    {{ ($stats['sms_sent'] ?? 0) + ($stats['whatsapp_sent'] ?? 0) }}
                                </div>
                                <div class="elive-header-stat-label">Sent</div>
                            </div>
                        </div>

                        <div class="elive-header-stat">
                            <div class="elive-header-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M7 17 17 7" />
                                    <path d="M9 7h8v8" />
                                </svg>
                            </div>
                            <div>
                                <div class="elive-header-stat-number">
                                    {{ $responseRate }}%
                                </div>
                                <div class="elive-header-stat-label">Response Rate</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Summary --}}
            <div class="elive-summary-grid">
                <div class="elive-summary-card">
                    <div class="elive-summary-icon" style="background:#DCFCE7;color:#15803D;">
                        <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 6 9 17l-5-5" />
                        </svg>
                    </div>
                    <div>
                        <div class="elive-summary-value" style="color:#15803D;">
                            {{ $stats['attending'] ?? 0 }}
                        </div>
                        <div class="elive-summary-label">Attending</div>
                    </div>
                </div>

                <div class="elive-summary-card">
                    <div class="elive-summary-icon" style="background:#FEE2E2;color:#B91C1C;">
                        <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18" />
                            <path d="m6 6 12 12" />
                        </svg>
                    </div>
                    <div>
                        <div class="elive-summary-value" style="color:#B91C1C;">
                            {{ $stats['failed'] ?? 0 }}
                        </div>
                        <div class="elive-summary-label">Failed / Declined</div>
                    </div>
                </div>

                <div class="elive-summary-card">
                    <div class="elive-summary-icon" style="background:#FFF7ED;color:#FD9618;">
                        <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="13" r="8" />
                            <path d="M12 9v4l3 2" />
                            <path d="M9 2h6" />
                        </svg>
                    </div>
                    <div>
                        <div class="elive-summary-value" style="color:#FD9618;">
                            {{ $stats['pending'] ?? 0 }}
                        </div>
                        <div class="elive-summary-label">Pending</div>
                    </div>
                </div>

                <div class="elive-summary-card">
                    <div class="elive-summary-icon" style="background:#DBEAFE;color:#213B73;">
                        <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M7 17 17 7" />
                            <path d="M9 7h8v8" />
                        </svg>
                    </div>
                    <div>
                        <div class="elive-summary-value" style="color:#213B73;">
                            {{ $responseRate }}%
                        </div>
                        <div class="elive-summary-label">Response Rate</div>
                    </div>
                </div>
            </div>

            {{-- Filters --}}
            <div class="elive-filters">
                <div>
                    <label class="elive-label">Search Invitee</label>
                    <input
                        type="text"
                        wire:model.live.debounce.500ms="search"
                        class="elive-input"
                        placeholder="Search by name, phone, or serial number..."
                    >
                </div>

                <div>
                    <label class="elive-label">Status</label>
                    <select wire:model.live="statusFilter" class="elive-select">
                        <option value="">All Statuses</option>
                        <option value="not_sent">Not Sent</option>
                        <option value="queued">Queued</option>
                        <option value="sending">Sending</option>
                        <option value="sent">Sent</option>
                        <option value="delivered">Delivered</option>
                        <option value="read">Read / WhatsApp</option>
                        <option value="replied">Replied / WhatsApp</option>
                        <option value="failed">Failed</option>
                        <option value="undelivered">Undelivered / SMS</option>
                        <option value="expired">Expired / SMS</option>
                        <option value="rejected">Rejected / SMS</option>
                        <option value="unknown">Unknown</option>
                        <option value="rsvp_pending">RSVP Pending</option>
                        <option value="attending">Attending</option>
                        <option value="not_attending">Not Attending</option>
                        <option value="maybe">Maybe</option>
                    </select>
                </div>

                <div>
                    <label class="elive-label">Channel</label>
                    <select wire:model.live="channelFilter" class="elive-select">
                        <option value="">All Channels</option>
                        <option value="sms">SMS</option>
                        <option value="whatsapp">WhatsApp</option>
                    </select>
                </div>

                <div>
                    <button
                        type="button"
                        wire:click="clearFilters"
                        class="elive-btn elive-btn-gray h-[42px] w-full md:w-auto"
                    >
                        Clear
                    </button>
                </div>
            </div>

            {{-- Table --}}
            <div class="elive-table-wrap">
                <table class="elive-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Guest Name</th>
                            <th>Delivery</th>
                            <th>RSVP Status</th>
                            <th>RSVP Date</th>
                            <th>Latest Comment</th>
                            <th class="text-right">Resend</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($invitees as $invitee)
                            @php
                                /*
                                    Channel/status rule:
                                    1. Use real conversation/provider logs when available.
                                    2. If the old SMS/WhatsApp columns show a previous send, use them as fallback.
                                    3. Show "Not Sent" instead of "None" when there is no channel.
                                */

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

                                $hasLegacySms = $legacySmsSent($invitee);
                                $hasLegacyWhatsapp = $legacyWhatsappSent($invitee);

                                $smsStatus = $smsLog
                                    ? $actualProviderStatus($smsLog, 'sms')
                                    : ($hasLegacySms
                                        ? $normalizeDeliveryStatus($invitee->sms_status ?? $invitee->invitation_sms_status ?? 'sent', 'sms')
                                        : 'not_sent');

                                $whatsappStatus = $whatsappLog
                                    ? $actualProviderStatus($whatsappLog, 'whatsapp')
                                    : ($hasLegacyWhatsapp
                                        ? $normalizeDeliveryStatus($invitee->last_message_status ?? 'sent', 'whatsapp')
                                        : 'not_sent');

                                $hasSms = filled($smsLog) || $hasLegacySms;
                                $hasWhatsapp = filled($whatsappLog) || $hasLegacyWhatsapp;

                                $deliveryChannel = match (true) {
                                    $hasSms && $hasWhatsapp => 'multi',
                                    $hasWhatsapp => 'whatsapp',
                                    $hasSms => 'sms',
                                    default => 'not_sent',
                                };

                                if ($deliveryChannel === 'not_sent') {
                                    $bestChannel = 'not_sent';
                                    $deliveryStatus = 'not_sent';
                                    $deliveryTime = null;
                                    $providerMessageId = null;
                                } else {
                                    $bestChannel = ($deliveryPriority($whatsappStatus) >= $deliveryPriority($smsStatus))
                                        ? 'whatsapp'
                                        : 'sms';

                                    if (! $hasWhatsapp && $bestChannel === 'whatsapp') {
                                        $bestChannel = 'sms';
                                    }

                                    if (! $hasSms && $bestChannel === 'sms') {
                                        $bestChannel = 'whatsapp';
                                    }

                                    $deliveryStatus = $bestChannel === 'whatsapp' ? $whatsappStatus : $smsStatus;
                                    $bestLog = $bestChannel === 'whatsapp' ? $whatsappLog : $smsLog;

                                    $deliveryTime = $bestLog
                                        ? $actualProviderTime($bestLog, $deliveryStatus)
                                        : ($bestChannel === 'sms'
                                            ? ($invitee->last_sms_sent_at ?? $invitee->sms_sent_at ?? $invitee->invitation_sms_sent_at)
                                            : ($invitee->last_whatsapp_sent_at ?? null));

                                    $providerMessageId = $bestLog
                                        ? $providerReference($bestLog)
                                        : ($bestChannel === 'sms'
                                            ? ($invitee->sms_message_id ?? null)
                                            : ($invitee->whatsapp_message_id ?? null));
                                }

                                $smsTime = $smsLog
                                    ? $actualProviderTime($smsLog, $smsStatus)
                                    : ($invitee->last_sms_sent_at ?? $invitee->sms_sent_at ?? $invitee->invitation_sms_sent_at);

                                $whatsappTime = $whatsappLog
                                    ? $actualProviderTime($whatsappLog, $whatsappStatus)
                                    : ($invitee->last_whatsapp_sent_at ?? null);

                                $smsProviderId = $smsLog ? $providerReference($smsLog) : ($invitee->sms_message_id ?? null);
                                $whatsappProviderId = $whatsappLog ? $providerReference($whatsappLog) : ($invitee->whatsapp_message_id ?? null);
                            @endphp

                            <tr wire:key="invitee-row-{{ $invitee->id }}">
                                <td>{{ $loop->iteration }}</td>

                                <td>
                                    <div class="elive-row-stack">
                                        <div class="elive-guest-name">
                                            {{ $invitee->name }}
                                        </div>

                                        <div class="elive-guest-meta">
                                            {{ $invitee->phone }}
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    @if ($deliveryChannel === 'multi')
                                        <div class="elive-delivery-stack">
                                            @if ($hasSms)
                                                <div class="elive-delivery-line">
                                                    <span
                                                        class="elive-channel-icon"
                                                        style="color:#213B73;"
                                                        title="SMS"
                                                        aria-label="SMS"
                                                    >
                                                        <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z" />
                                                            <path d="M8 9h8M8 13h5" />
                                                        </svg>
                                                        <span class="elive-sr-only">SMS</span>
                                                    </span>

                                                    <div class="elive-delivery-text">
                                                        <span class="elive-badge elive-delivery-status" style="{{ $statusBadge($smsStatus) }}">
                                                            {{ $formatStatus($smsStatus) }}
                                                        </span>
                                                        <span class="elive-delivery-time">
                                                            {{ $smsTime ? optional($smsTime)->format('d M H:i') : '—' }}
                                                        </span>
                                                    </div>
                                                </div>
                                            @endif

                                            @if ($hasWhatsapp)
                                                <div class="elive-delivery-line">
                                                    <span
                                                        class="elive-channel-icon"
                                                        style="color:#16A34A;"
                                                        title="WhatsApp"
                                                        aria-label="WhatsApp"
                                                    >
                                                        <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor">
                                                            <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.33 4.95L2 22l5.28-1.39a9.86 9.86 0 0 0 4.76 1.22h.01c5.46 0 9.91-4.45 9.91-9.91S17.5 2 12.04 2Zm0 18.15h-.01a8.2 8.2 0 0 1-4.17-1.14l-.3-.18-3.13.82.84-3.05-.2-.31a8.17 8.17 0 0 1-1.25-4.37c0-4.54 3.69-8.23 8.23-8.23 2.2 0 4.26.86 5.82 2.41a8.17 8.17 0 0 1 2.41 5.82c-.01 4.54-3.7 8.23-8.24 8.23Zm4.51-6.16c-.25-.12-1.46-.72-1.69-.8-.23-.08-.39-.12-.56.12-.16.25-.64.8-.78.96-.14.16-.29.18-.54.06-.25-.12-1.04-.38-1.98-1.22-.73-.65-1.23-1.46-1.37-1.7-.14-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.14.16-.25.25-.41.08-.16.04-.31-.02-.43-.06-.12-.56-1.35-.77-1.85-.2-.49-.41-.42-.56-.43h-.48c-.16 0-.43.06-.66.31-.23.25-.86.84-.86 2.05s.88 2.38 1 2.54c.12.16 1.73 2.65 4.2 3.72.59.25 1.04.4 1.4.52.59.19 1.12.16 1.54.1.47-.07 1.46-.6 1.67-1.17.21-.58.21-1.07.14-1.17-.06-.1-.22-.16-.47-.28Z"/>
                                                        </svg>
                                                        <span class="elive-sr-only">WhatsApp</span>
                                                    </span>

                                                    <div class="elive-delivery-text">
                                                        <span class="elive-badge elive-delivery-status" style="{{ $statusBadge($whatsappStatus) }}">
                                                            {{ $formatStatus($whatsappStatus) }}
                                                        </span>
                                                        <span class="elive-delivery-time">
                                                            {{ $whatsappTime ? optional($whatsappTime)->format('d M H:i') : '—' }}
                                                        </span>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <div class="elive-delivery-line">
                                            <span
                                                class="elive-channel-icon"
                                                style="{{ $deliveryChannel === 'whatsapp' ? 'color:#16A34A;' : ($deliveryChannel === 'sms' ? 'color:#213B73;' : 'color:#64748B;') }}"
                                                title="{{ $formatChannel($deliveryChannel) }}"
                                                aria-label="{{ $formatChannel($deliveryChannel) }}"
                                            >
                                                @if ($deliveryChannel === 'whatsapp')
                                                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor">
                                                        <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.33 4.95L2 22l5.28-1.39a9.86 9.86 0 0 0 4.76 1.22h.01c5.46 0 9.91-4.45 9.91-9.91S17.5 2 12.04 2Zm0 18.15h-.01a8.2 8.2 0 0 1-4.17-1.14l-.3-.18-3.13.82.84-3.05-.2-.31a8.17 8.17 0 0 1-1.25-4.37c0-4.54 3.69-8.23 8.23-8.23 2.2 0 4.26.86 5.82 2.41a8.17 8.17 0 0 1 2.41 5.82c-.01 4.54-3.7 8.23-8.24 8.23Zm4.51-6.16c-.25-.12-1.46-.72-1.69-.8-.23-.08-.39-.12-.56.12-.16.25-.64.8-.78.96-.14.16-.29.18-.54.06-.25-.12-1.04-.38-1.98-1.22-.73-.65-1.23-1.46-1.37-1.7-.14-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.14.16-.25.25-.41.08-.16.04-.31-.02-.43-.06-.12-.56-1.35-.77-1.85-.2-.49-.41-.42-.56-.43h-.48c-.16 0-.43.06-.66.31-.23.25-.86.84-.86 2.05s.88 2.38 1 2.54c.12.16 1.73 2.65 4.2 3.72.59.25 1.04.4 1.4.52.59.19 1.12.16 1.54.1.47-.07 1.46-.6 1.67-1.17.21-.58.21-1.07.14-1.17-.06-.1-.22-.16-.47-.28Z"/>
                                                    </svg>
                                                @elseif ($deliveryChannel === 'sms')
                                                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z" />
                                                        <path d="M8 9h8M8 13h5" />
                                                    </svg>
                                                @else
                                                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <circle cx="12" cy="12" r="9" />
                                                        <path d="M8 12h8" />
                                                    </svg>
                                                @endif
                                                <span class="elive-sr-only">{{ $formatChannel($deliveryChannel) }}</span>
                                            </span>

                                            <div class="elive-delivery-text">
                                                <span class="elive-badge elive-delivery-status" style="{{ $statusBadge($deliveryStatus) }}">
                                                    {{ $formatStatus($deliveryStatus) }}
                                                </span>
                                                <span class="elive-delivery-time">
                                                    {{ $deliveryTime ? optional($deliveryTime)->format('d M H:i') : '—' }}
                                                </span>
                                            </div>
                                        </div>
                                    @endif
                                </td>

                                <td>
                                    <div class="elive-status-cell">
                                        <span class="elive-badge" style="{{ $rsvpBadge($invitee->rsvp_status) }}">
                                            {{ $formatStatus($invitee->rsvp_status ?? 'pending') }}
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <div class="elive-date-cell">
                                        {{ $invitee->rsvp_confirmed_at?->format('d M Y') ?? '—' }}
                                    </div>
                                </td>

                                <td>
                                    <div class="elive-row-stack">
                                        <div class="elive-comment-preview">
                                            @if ($invitee->last_reply_message)
                                                “{{ $invitee->last_reply_message }}”
                                            @else
                                                No comment
                                            @endif
                                        </div>

                                        @if ($invitee->last_reply_at)
                                            <div class="elive-guest-meta">
                                                {{ $invitee->last_reply_at?->format('d M H:i') }}
                                            </div>
                                        @endif
                                    </div>
                                </td>

                                <td class="text-right">
                                    <div class="elive-cell-center end">
                                        <div class="elive-resend-actions">
                                        <button
                                            type="button"
                                            wire:click="resendSmsInvitation({{ $invitee->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="resendSmsInvitation({{ $invitee->id }})"
                                            class="elive-resend-btn sms"
                                            title="Resend SMS invitation / RSVP reminder"
                                            aria-label="Resend SMS invitation / RSVP reminder"
                                        >
                                            <span wire:loading.remove wire:target="resendSmsInvitation({{ $invitee->id }})">
                                                <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z" />
                                                    <path d="M8 9h8M8 13h5" />
                                                </svg>
                                            </span>
                                            <span wire:loading wire:target="resendSmsInvitation({{ $invitee->id }})" class="text-[10px] font-black">...</span>
                                            <span class="elive-sr-only">Resend SMS</span>
                                        </button>

                                        <button
                                            type="button"
                                            wire:click="resendWhatsAppInvitation({{ $invitee->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="resendWhatsAppInvitation({{ $invitee->id }})"
                                            class="elive-resend-btn whatsapp"
                                            title="Resend WhatsApp invitation / RSVP reminder"
                                            aria-label="Resend WhatsApp invitation / RSVP reminder"
                                        >
                                            <span wire:loading.remove wire:target="resendWhatsAppInvitation({{ $invitee->id }})">
                                                <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor">
                                                <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.33 4.95L2 22l5.28-1.39a9.86 9.86 0 0 0 4.76 1.22h.01c5.46 0 9.91-4.45 9.91-9.91S17.5 2 12.04 2Zm0 18.15h-.01a8.2 8.2 0 0 1-4.17-1.14l-.3-.18-3.13.82.84-3.05-.2-.31a8.17 8.17 0 0 1-1.25-4.37c0-4.54 3.69-8.23 8.23-8.23 2.2 0 4.26.86 5.82 2.41a8.17 8.17 0 0 1 2.41 5.82c-.01 4.54-3.7 8.23-8.24 8.23Zm4.51-6.16c-.25-.12-1.46-.72-1.69-.8-.23-.08-.39-.12-.56.12-.16.25-.64.8-.78.96-.14.16-.29.18-.54.06-.25-.12-1.04-.38-1.98-1.22-.73-.65-1.23-1.46-1.37-1.7-.14-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.14.16-.25.25-.41.08-.16.04-.31-.02-.43-.06-.12-.56-1.35-.77-1.85-.2-.49-.41-.42-.56-.43h-.48c-.16 0-.43.06-.66.31-.23.25-.86.84-.86 2.05s.88 2.38 1 2.54c.12.16 1.73 2.65 4.2 3.72.59.25 1.04.4 1.4.52.59.19 1.12.16 1.54.1.47-.07 1.46-.6 1.67-1.17.21-.58.21-1.07.14-1.17-.06-.1-.22-.16-.47-.28Z"/>
                                                </svg>
                                            </span>
                                            <span wire:loading wire:target="resendWhatsAppInvitation({{ $invitee->id }})" class="text-[10px] font-black">...</span>
                                            <span class="elive-sr-only">Resend WhatsApp</span>
                                        </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="elive-empty">
                                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
                                            <svg class="h-7 w-7" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z" />
                                            </svg>
                                        </div>

                                        <div class="mt-3 text-lg font-black text-[#111827]">
                                            No invitees found
                                        </div>

                                        <div class="mt-1 text-sm text-slate-500">
                                            No invitees match the current filters.
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
