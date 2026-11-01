@php
    $isThankYou = $mode === 'thank_you';
    $configured = $isThankYou
        ? $event->thank_you_mode === 'automatic'
        : (bool) $event->auto_sms_reminders_enabled;
    $time = $isThankYou
        ? ($event->auto_thank_you_time?->format('H:i') ?? '09:00')
        : null;
    $scope = $isThankYou
        ? ($event->auto_thank_you_scope === 'attending' ? 'Confirmed attending guests' : 'Checked-in guests')
        : 'Depends on each enabled reminder type';
    $attempts = $isThankYou
        ? \Illuminate\Support\Facades\DB::table('automatic_thank_you_sends')
            ->where('event_id', $event->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
        : collect();
    $hasTemplate = $isThankYou
        ? \App\Models\MessageTemplate::query()
            ->where('channel', 'sms')
            ->where('type', 'thank_you')
            ->where('status', 'active')
            ->where(fn ($query) => $query->where('event_id', $event->id)->orWhereNull('event_id'))
            ->exists()
        : true;
    $ready = $configured && $hasTemplate;
@endphp

<div class="space-y-4 text-sm">
    <div>
        <strong>Status:</strong>
        {{ $ready ? 'Automatic scheduling enabled' : 'Configuration required' }}
    </div>
    <div><strong>Delivery channel:</strong> SMS</div>
    @if ($isThankYou)
        <div><strong>Recipients:</strong> {{ $scope }}</div>
        <div><strong>Send time:</strong> {{ $time }} (Africa/Dar_es_Salaam), day after event</div>
        <div><strong>Event status:</strong> {{ ucfirst($event->status ?? 'unknown') }}</div>
        <div><strong>Recorded sends:</strong> {{ (int) ($attempts['sent'] ?? 0) }} sent, {{ (int) ($attempts['failed'] ?? 0) }} failed, {{ (int) ($attempts['attempted'] ?? 0) }} attempted</div>
        @unless ($hasTemplate)
            <p class="text-danger-600">No active SMS thank_you template was found.</p>
        @endunless
    @else
        <div><strong>RSVP pending reminder:</strong> {{ $event->auto_rsvp_pending_reminder_enabled ? ($event->rsvp_pending_reminder_time?->format('H:i') ?? 'Time not set') : 'Disabled' }}</div>
        <div><strong>One day before:</strong> {{ $event->auto_one_day_reminder_enabled ? ($event->one_day_reminder_time?->format('H:i') ?? 'Time not set') : 'Disabled' }}</div>
        <div><strong>Event day:</strong> {{ $event->auto_event_day_reminder_enabled ? ($event->event_day_reminder_time?->format('H:i') ?? 'Time not set') : 'Disabled' }}</div>
    @endif
    <p class="text-gray-500">Review or change these options under Edit Event → Message Settings. Processing requires the Laravel scheduler. This panel does not send messages.</p>
</div>
