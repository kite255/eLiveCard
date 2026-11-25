@php
    $channels = $getState() ?? [];
    $colors = [
        'success' => ['#166534', '#dcfce7'],
        'info' => ['#1d4ed8', '#dbeafe'],
        'warning' => ['#92400e', '#fef3c7'],
        'danger' => ['#b91c1c', '#fee2e2'],
        'gray' => ['#475569', '#f1f5f9'],
    ];
@endphp
<div style="display:flex;flex-direction:column;gap:5px;padding:8px 12px;">
    @foreach ($channels as $channel)
        @php([$foreground, $background] = $colors[$channel['color']] ?? $colors['gray'])
        <div style="display:flex;align-items:center;flex-wrap:wrap;gap:5px;">
            <span style="display:inline-flex;align-items:center;gap:5px;width:20px;flex-shrink:0;justify-content:center;" role="img" aria-label="{{ $channel['name'] }}" title="{{ $channel['name'] }}">
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
            <span style="display:inline-block;padding:2px 6px;border-radius:5px;font-size:11px;font-weight:600;color:{{ $foreground }};background:{{ $background }};">{{ $channel['label'] }}</span>
            @if ($channel['time'])
                <span style="font-size:11px;opacity:.75;">{{ $channel['time'] }}</span>
            @endif
        </div>
    @endforeach
</div>
