<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CardTypeResource\Pages;
use App\Models\CardType;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CardTypeResource extends Resource
{
    protected static ?string $model = CardType::class;

    /**
     * Card types are mainly managed inside each Event workspace,
     * so they should not appear as a separate sidebar item.
     */
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Event Setup';

    protected static ?string $navigationLabel = 'Card Types';

    protected static ?string $modelLabel = 'Card Type';

    protected static ?string $pluralModelLabel = 'Card Types';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with('event');

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

        return $query->whereRaw('1 = 0');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageCardTypes() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->canManageCardTypes() ?? false;
    }

    public static function canView($record): bool
    {
        return static::canAccessRecord($record);
    }

    public static function canEdit($record): bool
    {
        return static::canAccessRecord($record);
    }

    public static function canDelete($record): bool
    {
        return static::canAccessRecord($record);
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->canManageCardTypes() ?? false;
    }

    protected static function canAccessRecord(?CardType $record): bool
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

            return (int) ($record->event?->user_id) === (int) $user->id;
        }

        return false;
    }

    protected static function visibleEventOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        return \App\Models\Event::query()
            ->when(
                $user->isEventAdmin(),
                fn (Builder $query): Builder => $query->where('user_id', $user->id)
            )
            ->when(
                $user->isCheckInOfficer(),
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
                Forms\Components\Section::make('Card Type Details')
                    ->description('Create only the card types you want to use for this specific event.')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->label('Event')
                            ->options(fn (): array => static::visibleEventOptions())
                            ->searchable()
                            ->preload()
                            ->default(function (): ?int {
                                $user = auth()->user();

                                if (! $user?->isEventAdmin()) {
                                    return null;
                                }

                                return \App\Models\Event::query()
                                    ->where('user_id', $user->id)
                                    ->orderBy('id')
                                    ->value('id');
                            })
                            ->disabled(fn (): bool => auth()->user()?->isEventAdmin() ?? false)
                            ->dehydrated()
                            ->required()
                            ->helperText('Super Admin can choose any event. Event Admin can only use their own event.'),

                        Forms\Components\TextInput::make('name')
                            ->label('Card Type Name')
                            ->placeholder('Example: Single, Double, Family, VIP, VVIP, Committee')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('allowed_people')
                            ->label('Allowed Guests')
                            ->helperText('Total number of people allowed to enter using this card type.')
                            ->required()
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->default(1),

                        Forms\Components\ColorPicker::make('color')
                            ->label('Display Color')
                            ->default('#213B73'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Only active card types can be used when adding or importing invitees.')
                            ->default(true)
                            ->required(),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->placeholder('Optional notes or instructions about this card type.')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
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

                Tables\Columns\TextColumn::make('name')
                    ->label('Card Type')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('allowed_people')
                    ->label('Allowed Guests')
                    ->alignCenter()
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\ColorColumn::make('color')
                    ->label('Color'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Event')
                    ->options(fn (): array => static::visibleEventOptions())
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive')
                    ->placeholder('All card types')
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (CardType $record): bool => static::canAccessRecord($record)),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (CardType $record): bool => static::canAccessRecord($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->canManageCardTypes() ?? false),
                ]),
            ])
            ->emptyStateHeading('No card types yet')
            ->emptyStateDescription('Open an event workspace and create the card types required for that event.')
            ->emptyStateIcon('heroicon-o-identification');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCardTypes::route('/'),
            'create' => Pages\CreateCardType::route('/create'),
            'edit' => Pages\EditCardType::route('/{record}/edit'),
        ];
    }
}