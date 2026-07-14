<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CheckInResource\Pages;
use App\Models\CheckIn;
use App\Models\Event;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CheckInResource extends Resource
{
    protected static ?string $model = CheckIn::class;

    /**
     * Check-ins are managed from the Event workspace and Gate Check-In page.
     * Keep this resource hidden from the sidebar, but still protect it if opened directly.
     */
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationLabel = 'Check-ins';

    protected static ?string $modelLabel = 'Check-in';

    protected static ?string $pluralModelLabel = 'Check-ins';

    protected static ?string $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['event', 'invitee', 'checkedInBy']);

        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isEventAdmin()) {
            return $query->whereHas('event', function (Builder $eventQuery) use ($user): void {
                $eventQuery->where('user_id', $user->id);
            });
        }

        /*
         * MVP rule:
         * Check-in Officer can view check-in records because this resource is part of
         * the gate/check-in workflow. Later you can restrict this to assigned events.
         */
        if ($user->isCheckInOfficer() && ($user->canScanGuests() ?? false)) {
            return $query;
        }

        return $query->whereRaw('1 = 0');
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) (
            $user?->isSuperAdmin()
            || $user?->isEventAdmin()
            || $user?->isCheckInOfficer()
            || $user?->canViewReports()
            || $user?->canScanGuests()
        );
    }

    public static function canView($record): bool
    {
        return static::canAccessRecord($record);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected static function canAccessRecord(?CheckIn $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isEventAdmin()) {
            $record->loadMissing('event');

            return (int) ($record->event?->user_id ?? 0) === (int) $user->id;
        }

        if ($user->isCheckInOfficer()) {
            return (bool) ($user->canScanGuests() ?? false);
        }

        return false;
    }

    protected static function visibleEventOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        return Event::query()
            ->when(
                $user->isEventAdmin(),
                fn (Builder $query): Builder => $query->where('user_id', $user->id)
            )
            ->when(
                ! $user->isSuperAdmin() && ! $user->isEventAdmin() && ! $user->isCheckInOfficer(),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0')
            )
            ->orderBy('title')
            ->pluck('title', 'id')
            ->toArray();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Check-in Details')
                    ->description('Check-in records are read-only and are created from the gate check-in process.')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->label('Event')
                            ->options(fn (): array => static::visibleEventOptions())
                            ->searchable()
                            ->preload()
                            ->disabled(),

                        Forms\Components\Select::make('invitee_id')
                            ->label('Invitee')
                            ->relationship('invitee', 'name')
                            ->searchable()
                            ->preload()
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
                    ->sortable()
                    ->placeholder('No event'),

                Tables\Columns\TextColumn::make('invitee.name')
                    ->label('Invitee')
                    ->searchable()
                    ->sortable()
                    ->placeholder('No invitee'),

                Tables\Columns\TextColumn::make('invitee.phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable()
                    ->placeholder('No phone'),

                Tables\Columns\TextColumn::make('invitee.serial_number')
                    ->label('Serial Number')
                    ->searchable()
                    ->copyable()
                    ->placeholder('No serial'),

                Tables\Columns\TextColumn::make('checkedInBy.name')
                    ->label('Checked In By')
                    ->searchable()
                    ->sortable()
                    ->placeholder('System'),

                Tables\Columns\TextColumn::make('checkin_method')
                    ->label('Method')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            str($state ?: 'manual')
                                ->replace('_', ' ')
                                ->title()
                                ->toString()
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('guests_checked_in')
                    ->label('Guests')
                    ->alignCenter()
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('previous_checked_in_count')
                    ->label('Previous')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('remaining_guests')
                    ->label('Remaining')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            str($state ?: 'unknown')
                                ->replace('_', ' ')
                                ->title()
                                ->toString()
                    )
                    ->color(
                        fn (?string $state): string =>
                            match ($state) {
                                'success',
                                'checked_in',
                                'valid' => 'success',

                                'partial',
                                'duplicate',
                                'warning' => 'warning',

                                'failed',
                                'invalid',
                                'rejected' => 'danger',

                                default => 'gray',
                            }
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('checked_in_at')
                    ->label('Checked In At')
                    ->dateTime('d M Y, H:i:s')
                    ->sortable()
                    ->placeholder('Not recorded'),

                Tables\Columns\TextColumn::make('remarks')
                    ->label('Remarks')
                    ->limit(40)
                    ->tooltip(fn ($record): ?string => $record->remarks)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y, H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Event')
                    ->options(fn (): array => static::visibleEventOptions())
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'success' => 'Success',
                        'checked_in' => 'Checked In',
                        'valid' => 'Valid',
                        'partial' => 'Partial',
                        'duplicate' => 'Duplicate',
                        'rejected' => 'Rejected',
                        'failed' => 'Failed',
                        'invalid' => 'Invalid',
                    ])
                    ->native(false),

                Tables\Filters\SelectFilter::make('checkin_method')
                    ->label('Method')
                    ->options([
                        'manual' => 'Manual',
                        'qr' => 'QR',
                        'qr_scan' => 'QR Scan',
                        'serial' => 'Serial Number',
                        'serial_number' => 'Serial Number',
                        'phone' => 'Phone',
                        'phone_search' => 'Phone Search',
                        'name' => 'Name Search',
                        'name_search' => 'Name Search',
                        'gate_scanner' => 'Gate Scanner',
                    ])
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->visible(fn (CheckIn $record): bool => static::canAccessRecord($record)),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
                ]),
            ])
            ->defaultSort('checked_in_at', 'desc')
            ->emptyStateHeading('No check-ins yet')
            ->emptyStateDescription('Guest check-ins will appear here after scanning or manual verification.')
            ->emptyStateIcon('heroicon-o-qr-code')
            ->poll('20s');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCheckIns::route('/'),
            'view' => Pages\ViewCheckIn::route('/{record}'),
        ];
    }
}
