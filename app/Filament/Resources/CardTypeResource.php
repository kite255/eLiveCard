<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CardTypeResource\Pages;
use App\Models\CardType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CardTypeResource extends Resource
{
    protected static ?string $model = CardType::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Event Setup';

    protected static ?string $navigationLabel = 'Card Types';

    protected static ?string $modelLabel = 'Card Type';

    protected static ?string $pluralModelLabel = 'Card Types';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageEvents() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->canManageEvents() ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->canManageEvents() ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->isSuperAdmin()
            || auth()->user()?->isEventOwner();
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isSuperAdmin()
            || auth()->user()?->isEventOwner();
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
                            ->relationship('event', 'title')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('name')
                            ->label('Card Type Name')
                            ->placeholder('Example: Single, Double, Family, Special Guest')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('allowed_people')
                            ->label('Guests')
                            ->helperText('Total number of people allowed with this card type.')
                            ->required()
                            ->numeric()
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
                            ->placeholder('Optional notes about this card type.')
                            ->rows(3)
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
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Card Type')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('allowed_people')
                    ->label('Guests')
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
                    ->relationship('event', 'title')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive')
                    ->placeholder('All')
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin()
                        || auth()->user()?->isEventOwner()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->isSuperAdmin()
                            || auth()->user()?->isEventOwner()),
                ]),
            ])
            ->emptyStateHeading('No card types yet')
            ->emptyStateDescription('Create the card types you need for your event.')
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