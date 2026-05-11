<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CheckInResource\Pages;
use App\Models\CheckIn;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CheckInResource extends Resource
{
    protected static ?string $model = CheckIn::class;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationLabel = 'Check-ins';

    protected static ?string $modelLabel = 'Check-in';

    protected static ?string $pluralModelLabel = 'Check-ins';

    protected static ?string $navigationGroup = 'Event Management';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Check-in Details')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->label('Event')
                            ->relationship('event', 'title')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(),

                        Forms\Components\Select::make('invitee_id')
                            ->label('Invitee')
                            ->relationship('invitee', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(),

                        Forms\Components\TextInput::make('checkin_method')
                            ->label('Check-in Method')
                            ->disabled(),

                        Forms\Components\TextInput::make('guests_checked_in')
                            ->label('Guests Checked In')
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('previous_checked_in_count')
                            ->label('Previous Checked-in Count')
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('remaining_guests')
                            ->label('Remaining Guests')
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('status')
                            ->label('Status')
                            ->disabled(),

                        Forms\Components\Textarea::make('remarks')
                            ->label('Remarks')
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\DateTimePicker::make('checked_in_at')
                            ->label('Checked In At')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('invitee.name')
                    ->label('Invitee')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('invitee.phone')
                    ->label('Phone')
                    ->searchable(),

                Tables\Columns\TextColumn::make('invitee.serial_number')
                    ->label('Serial Number')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('checkedInBy.name')
                    ->label('Checked In By')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('checkin_method')
                    ->label('Method')
                    ->badge(),

                Tables\Columns\TextColumn::make('guests_checked_in')
                    ->label('Guests'),

                Tables\Columns\TextColumn::make('previous_checked_in_count')
                    ->label('Previous'),

                Tables\Columns\TextColumn::make('remaining_guests')
                    ->label('Remaining'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'success' => 'success',
                        'danger' => 'rejected',
                        'warning' => 'duplicate',
                    ]),

                Tables\Columns\TextColumn::make('checked_in_at')
                    ->label('Checked In At')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Event')
                    ->relationship('event', 'title')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'success' => 'Success',
                        'rejected' => 'Rejected',
                        'duplicate' => 'Duplicate',
                    ]),

                Tables\Filters\SelectFilter::make('checkin_method')
                    ->label('Method')
                    ->options([
                        'manual' => 'Manual',
                        'qr_scan' => 'QR Scan',
                        'serial_number' => 'Serial Number',
                        'phone_search' => 'Phone Search',
                        'name_search' => 'Name Search',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('checked_in_at', 'desc');
    }

    public static function canCreate(): bool
    {
        return false;
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
            'index' => Pages\ListCheckIns::route('/'),
            'view' => Pages\ViewCheckIn::route('/{record}'),
        ];
    }
}