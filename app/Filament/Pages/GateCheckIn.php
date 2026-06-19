<?php

namespace App\Filament\Pages;

use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Invitee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GateCheckIn extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static string $view = 'filament.pages.gate-check-in';

    protected static ?string $navigationLabel = 'Gate Check-In';

    protected static ?string $title = 'Gate Check-In';

    protected static ?string $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    public ?Invitee $invitee = null;

    public Collection $results;

    public array $stats = [
        'total_invitees' => 0,
        'checked_invitees' => 0,
        'allowed_guests' => 0,
        'checked_guests' => 0,
    ];

    public function mount(): void
    {
        $this->results = collect();

        $defaultEventId = Event::query()
            ->when(
                Schema::hasColumn('events', 'event_date'),
                fn (Builder $query) => $query->orderByDesc('event_date'),
                fn (Builder $query) => $query->latest()
            )
            ->value('id');

        $this->form->fill([
            'event_id' => $defaultEventId,
            'search' => null,
            'guests_to_check_in' => 1,
        ]);

        $this->loadStats();
    }

    public function form(Form $form): Form
    {
        $eventNameColumn = Schema::hasColumn('events', 'title') ? 'title' : 'name';

        return $form
            ->schema([
                Forms\Components\Section::make('Gate Search')
                    ->description('Select event, then scan QR code or search by serial number, phone number, or name.')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->label('Event')
                            ->options(fn (): array => Event::query()
                                ->when(
                                    Schema::hasColumn('events', 'event_date'),
                                    fn (Builder $query) => $query->orderByDesc('event_date'),
                                    fn (Builder $query) => $query->latest()
                                )
                                ->pluck($eventNameColumn, 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function () {
                                $this->resetResultOnly();
                                $this->loadStats();
                            })
                            ->helperText('All gate validation will be limited to the selected event.'),

                        Forms\Components\TextInput::make('search')
                            ->label('QR / Serial / Phone / Name')
                            ->placeholder('Scan QR, enter ELV-2026-XXXX, phone, or invitee name')
                            ->required()
                            ->live(onBlur: true),

                        Forms\Components\TextInput::make('guests_to_check_in')
                            ->label('Guests Entering')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(1),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 3,
                    ]),
            ])
            ->statePath('data');
    }

    public function searchInvitee(): void
    {
        $eventId = $this->data['event_id'] ?? null;
        $search = trim((string) ($this->data['search'] ?? ''));

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
                ->body('Scan QR code or enter serial number, phone number, or name.')
                ->danger()
                ->send();

            return;
        }

        $searchTerms = $this->extractSearchTerms($search);

        $this->results = Invitee::query()
            ->with(['event', 'cardType'])
            ->where('event_id', $eventId)
            ->where(function (Builder $query) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $query->orWhere('serial_number', $term);

                    if (Schema::hasColumn('invitees', 'phone')) {
                        $query->orWhere('phone', $term);
                    }

                    if (Schema::hasColumn('invitees', 'name')) {
                        $query->orWhere('name', 'like', '%' . $term . '%');
                    }

                    if (Schema::hasColumn('invitees', 'qr_token')) {
                        $query->orWhere('qr_token', $term);
                    }

                    if (Schema::hasColumn('invitees', 'qr_token_hash')) {
                        $query->orWhere('qr_token_hash', hash('sha256', $term));
                    }
                }
            })
            ->orderBy('name')
            ->limit(30)
            ->get();

        if ($this->results->isEmpty()) {
            Notification::make()
                ->title('Invitee not found')
                ->body('No invitee was found in the selected event.')
                ->danger()
                ->send();

            return;
        }

        if ($this->results->count() === 1) {
            $this->selectInvitee((int) $this->results->first()->id, false);

            Notification::make()
                ->title('Invitee found')
                ->body($this->invitee->name . ' is ready for check-in.')
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Multiple invitees found')
            ->body($this->results->count() . ' matching invitees found. Select the correct invitee.')
            ->info()
            ->send();
    }

    public function selectInvitee(int $inviteeId, bool $notify = true): void
    {
        $eventId = $this->data['event_id'] ?? null;

        $this->invitee = Invitee::query()
            ->with(['event', 'cardType', 'checkIns'])
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

        $this->data['guests_to_check_in'] = $this->suggestGuestsToCheckIn();

        if ($notify) {
            Notification::make()
                ->title('Invitee selected')
                ->body($this->invitee->name . ' selected.')
                ->success()
                ->send();
        }
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

        $guestsToCheckIn = (int) ($this->data['guests_to_check_in'] ?? 1);

        if ($guestsToCheckIn < 1) {
            Notification::make()
                ->title('Invalid guest number')
                ->body('Guests entering must be at least 1.')
                ->danger()
                ->send();

            return;
        }

        $successfulInviteeId = null;

        DB::transaction(function () use ($guestsToCheckIn, &$successfulInviteeId) {
            $invitee = Invitee::query()
                ->with('event')
                ->whereKey($this->invitee->id)
                ->lockForUpdate()
                ->firstOrFail();

            $allowedGuests = (int) ($invitee->final_allowed_guests ?? $invitee->allowed_guests ?? 1);
            $previousCount = (int) ($invitee->checked_in_count ?? 0);
            $remainingGuests = max($allowedGuests - $previousCount, 0);

            if (($invitee->card_status ?? 'active') === 'blocked') {
                Notification::make()
                    ->title('Card blocked')
                    ->body('This card is blocked and cannot be checked in.')
                    ->danger()
                    ->send();

                return;
            }

            $allowedCardStatuses = ['active', 'generated', 'sent'];

            if (! in_array(($invitee->card_status ?? 'active'), $allowedCardStatuses, true)) {
                Notification::make()
                    ->title('Card not valid')
                    ->body('Only active, generated, or sent cards are allowed for check-in.')
                    ->danger()
                    ->send();

                return;
            }

            if ($remainingGuests <= 0) {
                Notification::make()
                    ->title('Guest limit reached')
                    ->body('No remaining guests are allowed for this card.')
                    ->danger()
                    ->send();

                return;
            }

            if ($guestsToCheckIn > $remainingGuests) {
                Notification::make()
                    ->title('Guest limit exceeded')
                    ->body('Only ' . $remainingGuests . ' guest(s) remaining.')
                    ->danger()
                    ->send();

                return;
            }

            $newCount = $previousCount + $guestsToCheckIn;
            $newRemainingGuests = max($allowedGuests - $newCount, 0);
            $checkedInAt = now();

            $checkInData = [
                'event_id' => $invitee->event_id,
                'invitee_id' => $invitee->id,
            ];

            $optionalCheckInFields = [
                'checked_in_by' => Auth::id(),
                'checkin_method' => $this->detectCheckInMethod(),
                'guests_checked_in' => $guestsToCheckIn,
                'previous_checked_in_count' => $previousCount,
                'remaining_guests' => $newRemainingGuests,
                'status' => defined(CheckIn::class . '::STATUS_SUCCESS')
                    ? CheckIn::STATUS_SUCCESS
                    : 'success',
                'remarks' => 'Checked in from gate check-in page.',
                'checked_in_at' => $checkedInAt,
            ];

            foreach ($optionalCheckInFields as $column => $value) {
                if (Schema::hasColumn('check_ins', $column)) {
                    $checkInData[$column] = $value;
                }
            }

            CheckIn::query()->create($checkInData);

            $updateData = [];

            if (Schema::hasColumn('invitees', 'checked_in_count')) {
                $updateData['checked_in_count'] = $newCount;
            }

            if (Schema::hasColumn('invitees', 'checked_in_at')) {
                $updateData['checked_in_at'] = $checkedInAt;
            }

            if (Schema::hasColumn('invitees', 'check_in_status')) {
                $updateData['check_in_status'] = $newRemainingGuests <= 0
                    ? 'checked_in'
                    : 'partial';
            }

            if ($updateData !== []) {
                $invitee->update($updateData);
            }
            $successfulInviteeId = (int) $invitee->id;

            $this->invitee = $invitee->fresh(['event', 'cardType', 'checkIns']);
            $this->results = Invitee::query()
                ->with(['event', 'cardType'])
                ->whereKey($invitee->id)
                ->get();

            $this->data['guests_to_check_in'] = $this->suggestGuestsToCheckIn();
            $this->loadStats();

            Notification::make()
                ->title('Check-in successful')
                ->body($guestsToCheckIn . ' guest(s) checked in. Remaining: ' . $newRemainingGuests)
                ->success()
                ->send();
        });

        if ($successfulInviteeId !== null) {
            $this->dispatchWelcomeSms($successfulInviteeId);
        }
    }

    protected function dispatchWelcomeSms(int $inviteeId): void
    {
        try {
            $invitee = Invitee::query()->with('event')->find($inviteeId);

            if (! $invitee || ! $invitee->event) {
                return;
            }

            if (! $invitee->event->hasWelcomeSmsEnabled()) {
                return;
            }

            if (blank($invitee->phone)) {
                Log::warning('Welcome SMS skipped because invitee has no phone number.', [
                    'event_id' => $invitee->event_id,
                    'invitee_id' => $invitee->id,
                ]);

                return;
            }

            if (! class_exists(\App\Jobs\SendWelcomeSmsJob::class)) {
                Log::warning('SendWelcomeSmsJob does not exist yet.', [
                    'event_id' => $invitee->event_id,
                    'invitee_id' => $invitee->id,
                ]);

                return;
            }

            \App\Jobs\SendWelcomeSmsJob::dispatch($invitee->id);
        } catch (Throwable $exception) {
            Log::error('Welcome SMS dispatch failed after successful check-in.', [
                'invitee_id' => $inviteeId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function resetScanner(): void
    {
        $eventId = $this->data['event_id'] ?? null;

        $this->invitee = null;
        $this->results = collect();

        $this->form->fill([
            'event_id' => $eventId,
            'search' => null,
            'guests_to_check_in' => 1,
        ]);

        $this->loadStats();
    }

    protected function resetResultOnly(): void
    {
        $this->invitee = null;
        $this->results = collect();

        $this->data['search'] = null;
        $this->data['guests_to_check_in'] = 1;
    }

    protected function suggestGuestsToCheckIn(): int
    {
        if (! $this->invitee) {
            return 1;
        }

        $allowedGuests = (int) ($this->invitee->final_allowed_guests ?? $this->invitee->allowed_guests ?? 1);
        $checkedInCount = (int) ($this->invitee->checked_in_count ?? 0);
        $remainingGuests = max($allowedGuests - $checkedInCount, 0);

        return $remainingGuests > 0 ? 1 : 0;
    }

    protected function extractSearchTerms(string $value): array
    {
        $value = trim($value);

        $terms = [$value];

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $parts = parse_url($value);

            if (! empty($parts['path'])) {
                $segments = array_values(array_filter(explode('/', $parts['path'])));
                $lastSegment = end($segments);

                if ($lastSegment) {
                    $terms[] = $lastSegment;
                }
            }

            if (! empty($parts['query'])) {
                parse_str($parts['query'], $query);

                foreach (['token', 'qr', 'code', 'serial', 'serial_number'] as $key) {
                    if (! empty($query[$key])) {
                        $terms[] = (string) $query[$key];
                    }
                }
            }
        }

        return collect($terms)
            ->map(fn ($term) => trim((string) $term))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function detectCheckInMethod(): string
    {
        $search = trim((string) ($this->data['search'] ?? ''));

        if ($search === '') {
            return CheckIn::METHOD_GATE_SCANNER;
        }

        if (filter_var($search, FILTER_VALIDATE_URL)) {
            return CheckIn::METHOD_QR;
        }

        if ($this->invitee && $search === (string) $this->invitee->serial_number) {
            return CheckIn::METHOD_SERIAL;
        }

        if ($this->invitee && $search === (string) $this->invitee->phone) {
            return CheckIn::METHOD_PHONE;
        }

        if ($this->invitee && str_contains(strtolower((string) $this->invitee->name), strtolower($search))) {
            return CheckIn::METHOD_NAME;
        }

        return CheckIn::METHOD_GATE_SCANNER;
    }

    public function loadStats(): void
    {
        $eventId = $this->data['event_id'] ?? null;

        if (! $eventId) {
            $this->stats = [
                'total_invitees' => 0,
                'checked_invitees' => 0,
                'allowed_guests' => 0,
                'checked_guests' => 0,
            ];

            return;
        }

        $invitees = Invitee::query()
            ->where('event_id', $eventId);

        $checkedInColumnExists = Schema::hasColumn('invitees', 'checked_in_count');
        $allowedColumn = Schema::hasColumn('invitees', 'final_allowed_guests')
            ? 'final_allowed_guests'
            : (Schema::hasColumn('invitees', 'allowed_guests') ? 'allowed_guests' : null);

        $this->stats = [
            'total_invitees' => (clone $invitees)->count(),
            'checked_invitees' => $checkedInColumnExists
                ? (clone $invitees)->where('checked_in_count', '>', 0)->count()
                : 0,
            'allowed_guests' => $allowedColumn
                ? (int) (clone $invitees)->sum($allowedColumn)
                : (clone $invitees)->count(),
            'checked_guests' => $checkedInColumnExists
                ? (int) (clone $invitees)->sum('checked_in_count')
                : 0,
        ];
    }

    public function getRecentCheckInsProperty()
    {
        $eventId = $this->data['event_id'] ?? null;

        if (! $eventId) {
            return collect();
        }

        $query = CheckIn::query()
            ->with(['invitee', 'checkedInBy'])
            ->where('event_id', $eventId);

        if (Schema::hasColumn('check_ins', 'checked_in_at')) {
            $query->latest('checked_in_at');
        } else {
            $query->latest();
        }

        return $query->limit(8)->get();
    }
}
