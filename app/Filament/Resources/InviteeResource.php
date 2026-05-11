<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InviteeResource\Pages;
use App\Models\Invitee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class InviteeResource extends Resource
{
    protected static ?string $model = Invitee::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Invitees';

    protected static ?string $modelLabel = 'Invitee';

    protected static ?string $pluralModelLabel = 'Invitees';

    protected static ?string $navigationGroup = 'Event Management';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Invitee Details')
                    ->description('Add invitee details and assign the correct card type.')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->label('Event')
                            ->relationship('event', 'title')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('card_type_id')
                            ->label('Card Type')
                            ->relationship('cardType', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('The selected card type determines the default number of people allowed.'),

                        Forms\Components\TextInput::make('name')
                            ->label('Full Name')
                            ->required()
                            ->unique(
                                table: 'invitees',
                                column: 'name',
                                ignoreRecord: true,
                                modifyRuleUsing: fn ($rule, $get) => $rule->where('event_id', $get('event_id'))
                            )
                            ->validationMessages([
                                'unique' => 'This invitee name already exists in this event.',
                            ])
                            ->maxLength(255)
                            ->helperText('Invitee name must not repeat in the same event.'),

                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->required()
                            ->maxLength(20)
                            ->placeholder('Example: 255745939140')
                            ->helperText('Phone number is required. The same phone number can be used by more than one invitee.'),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('category')
                            ->label('Category')
                            ->maxLength(255)
                            ->placeholder('Example: Bride Family, Groom Friends, VIP'),

                        Forms\Components\TextInput::make('table_number')
                            ->label('Table Number')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('allowed_guests')
                            ->label('Custom Allowed Guests')
                            ->helperText('Leave empty to use the number of people defined in the selected card type.')
                            ->numeric()
                            ->minValue(1),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('System Information')
                    ->description('These values are generated automatically by the system.')
                    ->schema([
                        Forms\Components\TextInput::make('serial_number')
                            ->label('Serial Number')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('qr_code_path')
                            ->label('QR Code Path')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('card_status')
                            ->label('Card Status')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('rsvp_status')
                            ->label('RSVP Status')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('checked_in_count')
                            ->label('Checked-in Count')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('final_allowed_guests')
                            ->label('Allowed Guests')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('remaining_guests')
                            ->label('Remaining Guests')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2)
                    ->collapsed()
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Invitee')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('cardType.name')
                    ->label('Card Type')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('final_allowed_guests')
                    ->label('Allowed'),

                Tables\Columns\TextColumn::make('checked_in_count')
                    ->label('In')
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining_guests')
                    ->label('Remain'),

                Tables\Columns\TextColumn::make('serial_number')
                    ->label('Serial')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Serial number copied'),

                Tables\Columns\TextColumn::make('qr_code_path')
                    ->label('QR Path')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('rsvp_status')
                    ->label('RSVP')
                    ->badge()
                    ->colors([
                        'gray' => 'pending',
                        'success' => 'attending',
                        'danger' => 'not_attending',
                        'warning' => 'maybe',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('card_status')
                    ->label('Card')
                    ->badge()
                    ->colors([
                        'gray' => 'pending',
                        'success' => 'generated',
                        'info' => 'sent',
                        'danger' => 'blocked',
                    ])
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Event')
                    ->relationship('event', 'title')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('card_type_id')
                    ->label('Card Type')
                    ->relationship('cardType', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('rsvp_status')
                    ->label('RSVP Status')
                    ->options([
                        'pending' => 'Pending',
                        'attending' => 'Attending',
                        'not_attending' => 'Not Attending',
                        'maybe' => 'Maybe',
                    ]),

                Tables\Filters\SelectFilter::make('card_status')
                    ->label('Card Status')
                    ->options([
                        'pending' => 'Pending',
                        'generated' => 'Generated',
                        'sent' => 'Sent',
                        'blocked' => 'Blocked',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('check_in')
                    ->label('Check In')
                    ->icon('heroicon-o-qr-code')
                    ->button()
                    ->color('success')
                    ->visible(fn (Invitee $record): bool => $record->remaining_guests > 0)
                    ->form([
                        Forms\Components\TextInput::make('guests_to_check_in')
                            ->label('Number of Guests Entering')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(1)
                            ->helperText(fn (Invitee $record): string => 'Remaining guests allowed: ' . $record->remaining_guests),
                    ])
                    ->modalHeading('Check In Invitee')
                    ->modalDescription(fn (Invitee $record): string => 'Invitee: ' . $record->name . ' | Remaining guests: ' . $record->remaining_guests)
                    ->modalSubmitActionLabel('Confirm Check In')
                    ->action(function (Invitee $record, array $data) {
                        $record->refresh();

                        $guestsToCheckIn = (int) $data['guests_to_check_in'];

                        if ($guestsToCheckIn < 1) {
                            Notification::make()
                                ->title('Invalid guest number')
                                ->body('The number of guests must be at least 1.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($record->card_status === 'blocked') {
                            Notification::make()
                                ->title('Card blocked')
                                ->body('This invitation card is blocked and cannot be checked in.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($record->remaining_guests <= 0) {
                            Notification::make()
                                ->title('Guest limit reached')
                                ->body('This invitee has no remaining guests allowed.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($guestsToCheckIn > $record->remaining_guests) {
                            Notification::make()
                                ->title('Guest limit exceeded')
                                ->body('Only ' . $record->remaining_guests . ' guest(s) remaining for this card.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $previousCount = $record->checked_in_count;
                        $newCount = $previousCount + $guestsToCheckIn;
                        $remainingGuests = max(0, $record->final_allowed_guests - $newCount);

                        $record->checkIns()->create([
                            'event_id' => $record->event_id,
                            'checked_in_by' => Auth::id(),
                            'checkin_method' => 'manual',
                            'guests_checked_in' => $guestsToCheckIn,
                            'previous_checked_in_count' => $previousCount,
                            'remaining_guests' => $remainingGuests,
                            'status' => 'success',
                            'remarks' => 'Checked in manually from Filament admin panel.',
                            'checked_in_at' => now(),
                        ]);

                        $record->update([
                            'checked_in_count' => $newCount,
                            'checked_in_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Check-in successful')
                            ->body($guestsToCheckIn . ' guest(s) checked in for ' . $record->name . '. Remaining: ' . $remainingGuests)
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('generate_qr')
                    ->label('Generate QR')
                    ->icon('heroicon-o-qr-code')
                    ->button()
                    ->color('info')
                    ->visible(fn (Invitee $record): bool => blank($record->qr_code_path))
                    ->action(function (Invitee $record) {
                        $record->generateQrCode();

                        Notification::make()
                            ->title('QR code generated')
                            ->body('QR code generated for ' . $record->name)
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->label('Edit'),

                Tables\Actions\DeleteAction::make()
                    ->label('Delete'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvitees::route('/'),
            'create' => Pages\CreateInvitee::route('/create'),
            'edit' => Pages\EditInvitee::route('/{record}/edit'),
        ];
    }
}