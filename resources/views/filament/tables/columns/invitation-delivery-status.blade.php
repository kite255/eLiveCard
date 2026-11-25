@php
    $channels = $getState() ?? [];
@endphp
@once
    <style>
        @media (max-width: 640px) {
            .elive-delivery-status { min-width: 0 !important; }
            .elive-delivery-row { grid-template-columns: 18px minmax(0, 1fr) !important; row-gap: 2px; }
            .elive-delivery-time { grid-column: 2; white-space: normal !important; }
        }
    </style>
@endonce
<div class="elive-delivery-status" style="display:flex;flex-direction:column;gap:3px;padding:5px 8px;min-width: max-content;">
    @foreach ($channels as $channel)
        @php
            $status = strtolower(trim($channel['label']));
            [$indicator, $foreground] = match (true) {
                $status === 'delivered' => ['double-check', '#15803D'],
                $status === 'read' => ['double-check', '#2563EB'],
                $status === 'sent' => ['check', '#64748B'],
                $status === 'failed' => ['failed', '#DC2626'],
                str_starts_with($status, 'not sent') => ['not-sent', '#64748B'],
                in_array($status, ['pending', 'queued', 'sending', 'submitted', 'buffered'], true) => ['clock', '#B45309'],
                default => ['unknown', '#64748B'],
            };
        @endphp
        <div class="elive-delivery-row" style="display:grid;grid-template-columns:18px 110px max-content;align-items:center;column-gap:5px;">
            <span style="display:inline-flex;align-items:center;gap:5px;width:18px;flex-shrink:0;justify-content:center;" role="img" aria-label="{{ $channel['name'] }}" title="{{ $channel['name'] }}">
                @if ($channel['name'] === 'SMS')
                    <svg data-channel-icon="sms" aria-hidden="true" viewBox="0 0 24 24" style="display:inline-block;width:16px;height:16px;flex-shrink:0;color:#213B73;" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="5" width="18" height="14" rx="2"/>
                        <path d="m3 7 9 6 9-6"/>
                    </svg>
                @else
                    <svg data-channel-icon="whatsapp" aria-hidden="true" viewBox="0 0 24 24" style="display:inline-block;width:16px;height:16px;flex-shrink:0;color:#16A34A;" fill="currentColor">
                        <path d="M12 2a10 10 0 0 0-8.7 15L2 22l5.1-1.3A10 10 0 1 0 12 2zm0 1.8a8.2 8.2 0 1 1-4.2 15.3l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 0 1 12 3.8z"/>
                        <path d="M8.4 7.2c-.4 0-1 .6-1 1.6 0 2.8 3.4 6.2 6.4 6.6 1.3.2 2.4-.6 2.5-1.5.1-.3-2.2-1.6-2.5-1.5-.3.1-.8 1-1.1.9-1.5-.5-2.6-1.7-3.1-2.8-.1-.3.7-.8.7-1.1 0-.3-.9-2.2-1.2-2.2z"/>
                    </svg>
                @endif
            </span>
            <span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;line-height:1.3;font-weight:600;min-width:0;color:{{ $foreground }};">
                <svg data-status-icon="{{ $indicator }}" aria-hidden="true" viewBox="0 0 24 24" style="display:inline-block;width:14px;height:14px;flex-shrink:0;{{ $indicator === 'clock' ? 'color:#FD9618;' : '' }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    @switch($indicator)
                        @case('double-check')
                            <path d="m2 12 5 5L18 6M10 17 21 6"/>
                            @break
                        @case('check')
                            <path d="m5 12 4 4L19 6"/>
                            @break
                        @case('clock')
                            <circle cx="12" cy="12" r="9"/>
                            <path d="M12 7v5l3 2"/>
                            @break
                        @case('failed')
                            <circle cx="12" cy="12" r="9"/>
                            <path d="m9 9 6 6m0-6-6 6"/>
                            @break
                        @case('not-sent')
                            <circle cx="12" cy="12" r="9"/>
                            <path d="M8 12h8"/>
                            @break
                        @default
                            <circle cx="12" cy="12" r="9"/>
                            <path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 2-3 4m.1 3h.01"/>
                    @endswitch
                </svg>
                {{ $channel['label'] }}
            </span>
            @if ($channel['time'])
                <span class="elive-delivery-time" style="font-size:10px;line-height:1.3;opacity:.75;white-space:nowrap;">{{ $channel['time'] }}</span>
            @endif
        </div>
    @endforeach
</div>
