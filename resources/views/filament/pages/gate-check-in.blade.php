<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit.prevent="searchInvitee">
            {{ $this->form }}

            <div class="mt-4">
                <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">
                    Search Invitee
                </x-filament::button>
            </div>
        </form>

        @if ($results->isNotEmpty())
            <x-filament::section>
                <x-slot name="heading">
                    Search Results
                </x-slot>

                <div class="space-y-3">
                    @foreach ($results as $result)
                        <div
                            class="rounded-xl border p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-4
                            {{ $invitee?->id === $result->id ? 'bg-primary-500/10 border-primary-500' : 'border-gray-700 bg-gray-900' }}"
                        >
                            <div class="grid grid-cols-1 md:grid-cols-5 gap-3 flex-1">
                                <div>
                                    <div class="text-xs text-gray-400">Name</div>
                                    <div class="font-bold">{{ $result->name }}</div>
                                </div>

                                <div>
                                    <div class="text-xs text-gray-400">Phone</div>
                                    <div class="font-bold">{{ $result->phone }}</div>
                                </div>

                                <div>
                                    <div class="text-xs text-gray-400">Card Type</div>
                                    <div class="font-bold">{{ $result->cardType?->name }}</div>
                                </div>

                                <div>
                                    <div class="text-xs text-gray-400">Serial</div>
                                    <div class="font-bold">{{ $result->serial_number }}</div>
                                </div>

                                <div>
                                    <div class="text-xs text-gray-400">Status</div>
                                    <div class="font-bold uppercase">{{ $result->card_status }}</div>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <div class="text-center">
                                    <div class="text-xs text-gray-400">Allowed</div>
                                    <div class="text-lg font-bold">{{ $result->final_allowed_guests }}</div>
                                </div>

                                <div class="text-center">
                                    <div class="text-xs text-gray-400">In</div>
                                    <div class="text-lg font-bold">{{ $result->checked_in_count }}</div>
                                </div>

                                <div class="text-center">
                                    <div class="text-xs text-gray-400">Remain</div>
                                    <div class="text-lg font-bold">{{ $result->remaining_guests }}</div>
                                </div>

                                <x-filament::button
                                    type="button"
                                    wire:click="selectInvitee({{ $result->id }})"
                                    color="{{ $invitee?->id === $result->id ? 'success' : 'gray' }}"
                                    size="sm"
                                >
                                    {{ $invitee?->id === $result->id ? 'Selected' : 'Select' }}
                                </x-filament::button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        @if ($invitee)
            <x-filament::section>
                <x-slot name="heading">
                    Selected Invitee Details
                </x-slot>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <div class="text-sm text-gray-500">Name</div>
                        <div class="font-bold">{{ $invitee->name }}</div>
                    </div>

                    <div>
                        <div class="text-sm text-gray-500">Phone</div>
                        <div class="font-bold">{{ $invitee->phone }}</div>
                    </div>

                    <div>
                        <div class="text-sm text-gray-500">Serial Number</div>
                        <div class="font-bold">{{ $invitee->serial_number }}</div>
                    </div>

                    <div>
                        <div class="text-sm text-gray-500">Event</div>
                        <div class="font-bold">{{ $invitee->event?->title }}</div>
                    </div>

                    <div>
                        <div class="text-sm text-gray-500">Card Type</div>
                        <div class="font-bold">{{ $invitee->cardType?->name }}</div>
                    </div>

                    <div>
                        <div class="text-sm text-gray-500">Card Status</div>
                        <div class="font-bold uppercase">{{ $invitee->card_status }}</div>
                    </div>

                    <div>
                        <div class="text-sm text-gray-500">Allowed Guests</div>
                        <div class="text-2xl font-bold">{{ $invitee->final_allowed_guests }}</div>
                    </div>

                    <div>
                        <div class="text-sm text-gray-500">Checked In</div>
                        <div class="text-2xl font-bold">{{ $invitee->checked_in_count }}</div>
                    </div>

                    <div>
                        <div class="text-sm text-gray-500">Remaining</div>
                        <div class="text-2xl font-bold">{{ $invitee->remaining_guests }}</div>
                    </div>
                </div>

                <div class="mt-6">
                    @if ($invitee->remaining_guests > 0 && $invitee->card_status !== 'blocked')
                        <x-filament::button
                            wire:click="checkIn"
                            color="success"
                            icon="heroicon-o-qr-code"
                            size="lg"
                        >
                            Check In
                        </x-filament::button>
                    @else
                        <x-filament::badge color="danger">
                            No remaining guests or card blocked
                        </x-filament::badge>
                    @endif
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>