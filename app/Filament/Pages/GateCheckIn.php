<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Models\Invitee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class GateCheckIn extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass-circle';

    protected static string $view = 'filament.pages.gate-check-in';

    protected static ?string $navigationLabel = 'Gate Check-in';

    protected static ?string $title = 'Gate Check-in';

    protected static ?string $navigationGroup = 'Event Management';

    protected static ?int $navigationSort = 2;

    public ?array $data = [];

    public ?Invitee $invitee = null;

    public Collection $results;

    public function mount(): void
    {
        $this->results = collect();

        $this->form->fill([
            'guests_to_check_in' => 1,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Gate Search')
                    ->description('Select event first, then search invitee by serial number, phone number, or name.')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->label('Event')
                            ->options(Event::query()->orderBy('event_date', 'desc')->pluck('title', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->helperText('Search results will be limited to this event.'),

                        Forms\Components\TextInput::make('search')
                            ->label('Serial Number / Phone / Name')
                            ->placeholder('Example: ELV-2026-UVS28X, 255768461644, or John')
                            ->required(),

                        Forms\Components\TextInput::make('guests_to_check_in')
                            ->label('Guests Entering')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(1),
                    ])
                    ->columns(3),
            ])
            ->statePath('data');
    }

    public function searchInvitee(): void
    {
        $eventId = $this->data['event_id'] ?? null;
        $search = trim($this->data['search'] ?? '');

        $this->invitee = null;
        $this->results = collect();

        if (! $eventId) {
            Notification::make()
                ->title('Event is required')
                ->body('Please select an event first.')
                ->danger()
                ->send();

            return;
        }

        if ($search === '') {
            Notification::make()
                ->title('Search field is required')
                ->body('Enter serial number, phone number, or name.')
                ->danger()
                ->send();

            return;
        }

        $this->results = Invitee::query()
            ->with(['event', 'cardType'])
            ->where('event_id', $eventId)
            ->where(function ($query) use ($search) {
                $query->where('serial_number', $search)
                    ->orWhere('phone', $search)
                    ->orWhere('name', 'ILIKE', '%' . $search . '%');
            })
            ->orderBy('name')
            ->limit(30)
            ->get();

        if ($this->results->isEmpty()) {
            Notification::make()
                ->title('Invitee not found')
                ->body('No invitee found in the selected event.')
                ->danger()
                ->send();

            return;
        }

        if ($this->results->count() === 1) {
            $this->invitee = $this->results->first();

            Notification::make()
                ->title('Invitee found')
                ->body($this->invitee->name . ' found successfully.')
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Multiple invitees found')
            ->body($this->results->count() . ' matching invitees found in this event. Select the correct invitee.')
            ->info()
            ->send();
    }

    public function selectInvitee(int $inviteeId): void
    {
        $eventId = $this->data['event_id'] ?? null;

        $this->invitee = Invitee::query()
            ->with(['event', 'cardType'])
            ->where('event_id', $eventId)
            ->find($inviteeId);

        if (! $this->invitee) {
            Notification::make()
                ->title('Invitee not found')
                ->body('The selected invitee does not belong to this event.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Invitee selected')
            ->body($this->invitee->name . ' selected.')
            ->success()
            ->send();
    }

    public function checkIn(): void
    {
        if (! $this->invitee) {
            Notification::make()
                ->title('No invitee selected')
                ->body('Search and select an invitee first.')
                ->danger()
                ->send();

            return;
        }

        $this->invitee->refresh();

        $guestsToCheckIn = (int) ($this->data['guests_to_check_in'] ?? 1);

        if ($guestsToCheckIn < 1) {
            Notification::make()
                ->title('Invalid guest number')
                ->body('Guests entering must be at least 1.')
                ->danger()
                ->send();

            return;
        }

        if ($this->invitee->card_status === 'blocked') {
            Notification::make()
                ->title('Card blocked')
                ->body('This card is blocked and cannot be checked in.')
                ->danger()
                ->send();

            return;
        }

        if ($this->invitee->remaining_guests <= 0) {
            Notification::make()
                ->title('Guest limit reached')
                ->body('No remaining guests are allowed for this card.')
                ->danger()
                ->send();

            return;
        }

        if ($guestsToCheckIn > $this->invitee->remaining_guests) {
            Notification::make()
                ->title('Guest limit exceeded')
                ->body('Only ' . $this->invitee->remaining_guests . ' guest(s) remaining.')
                ->danger()
                ->send();

            return;
        }

        $previousCount = $this->invitee->checked_in_count;
        $newCount = $previousCount + $guestsToCheckIn;
        $remainingGuests = max(0, $this->invitee->final_allowed_guests - $newCount);

        $this->invitee->checkIns()->create([
            'event_id' => $this->invitee->event_id,
            'checked_in_by' => Auth::id(),
            'checkin_method' => 'gate_search',
            'guests_checked_in' => $guestsToCheckIn,
            'previous_checked_in_count' => $previousCount,
            'remaining_guests' => $remainingGuests,
            'status' => 'success',
            'remarks' => 'Checked in from gate search page.',
            'checked_in_at' => now(),
        ]);

        $this->invitee->update([
            'checked_in_count' => $newCount,
            'checked_in_at' => now(),
        ]);

        $this->invitee->refresh();

        $this->results = Invitee::query()
            ->with(['event', 'cardType'])
            ->where('id', $this->invitee->id)
            ->get();

        Notification::make()
            ->title('Check-in successful')
            ->body($guestsToCheckIn . ' guest(s) checked in. Remaining: ' . $remainingGuests)
            ->success()
            ->send();
    }
}