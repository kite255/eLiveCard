<div class="elive-card elive-kpi">
    <div class="elive-kpi-inner">
        <div class="elive-icon green"><x-heroicon-o-chat-bubble-left-right /></div>
        <div>
            <div class="elive-kpi-label">SMS Balance</div>
            <div class="elive-kpi-value" @style(['font-size:16px' => ! is_numeric(str_replace(',', '', $balance))])>{{ $balance }}</div>
            <div class="elive-kpi-note">System-wide SMS credits</div>
            <button type="button" wire:click="$refresh" wire:loading.attr="disabled" class="elive-link" style="margin-top:6px">Refresh</button>
        </div>
    </div>
</div>
