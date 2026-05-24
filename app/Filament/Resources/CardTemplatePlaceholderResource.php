<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CardTemplatePlaceholderResource\Pages;
use App\Models\CardTemplatePlaceholder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CardTemplatePlaceholderResource extends Resource
{
    protected static ?string $model = CardTemplatePlaceholder::class;

    protected static ?string $navigationIcon = 'heroicon-o-cursor-arrow-rays';

    protected static ?string $navigationGroup = 'Card Management';

    protected static ?string $navigationLabel = 'Card Placeholders';

    protected static ?string $modelLabel = 'Card Placeholder';

    protected static ?string $pluralModelLabel = 'Card Placeholders';

    protected static ?int $navigationSort = 4;

    /**
     * Hide Card Placeholders from the sidebar.
     *
     * Placeholders should be managed from:
     * Card Templates -> Design
     *
     * This prevents placeholders from being edited globally
     * and mixing between different events/templates.
     */
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Placeholder Details')
                    ->description('Set position, size, text style, and QR style for each placeholder on the selected card template.')
                    ->schema([
                        Forms\Components\Select::make('card_template_id')
                            ->label('Card Template')
                            ->relationship('cardTemplate', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Choose the card template that owns this placeholder.'),

                        Forms\Components\Select::make('placeholder_key')
                            ->label('Placeholder')
                            ->options(CardTemplatePlaceholder::allowedPlaceholderKeys())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Choose what will be printed on the card, for example name, card type, QR code, serial number, guest count, event name, date, time, or venue.'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Disable this if you want to keep the placeholder but stop printing it on generated cards.'),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Position and Size')
                    ->description('Use X and Y to move the placeholder. Use width and height especially for QR code and future image areas.')
                    ->schema([
                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\TextInput::make('x_position')
                                    ->label('X Position')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->required()
                                    ->helperText('Move right'),

                                Forms\Components\TextInput::make('y_position')
                                    ->label('Y Position')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->required()
                                    ->helperText('Move down'),

                                Forms\Components\TextInput::make('width')
                                    ->label('Width')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(160)
                                    ->placeholder('Example: 160')
                                    ->helperText('QR recommended: 150–180'),

                                Forms\Components\TextInput::make('height')
                                    ->label('Height')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(160)
                                    ->placeholder('Example: 160')
                                    ->helperText('Keep QR square'),
                            ]),
                    ]),

                Forms\Components\Section::make('Text Style')
                    ->description('Used for text placeholders like invitee name, card type, serial number, event name, date, time, venue, guest count, and table number.')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('font_size')
                                    ->label('Font Size')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(24),

                                Forms\Components\ColorPicker::make('font_color')
                                    ->label('Font Color')
                                    ->default('#000000'),

                                Forms\Components\TextInput::make('font_family')
                                    ->label('Font Family')
                                    ->placeholder('Example: Arial')
                                    ->helperText('Optional. Leave empty to use default font.'),
                            ]),
                    ]),

                Forms\Components\Section::make('QR Code Style')
                    ->description('Used only when the placeholder is QR Code. For best scanning, use black QR on white background.')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\ColorPicker::make('qr_color')
                                    ->label('QR Color')
                                    ->default('#000000')
                                    ->helperText('Recommended: black'),

                                Forms\Components\ColorPicker::make('qr_background_color')
                                    ->label('QR Background Color')
                                    ->default('#FFFFFF')
                                    ->helperText('Recommended: white'),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('cardTemplate.event.title')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('cardTemplate.name')
                    ->label('Template')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('placeholder_key')
                    ->label('Placeholder')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (?string $state): string => CardTemplatePlaceholder::allowedPlaceholderKeys()[$state] ?? $state ?? '-'),

                Tables\Columns\TextColumn::make('x_position')
                    ->label('X')
                    ->sortable(),

                Tables\Columns\TextColumn::make('y_position')
                    ->label('Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('width')
                    ->label('W')
                    ->sortable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('height')
                    ->label('H')
                    ->sortable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('font_size')
                    ->label('Font')
                    ->sortable()
                    ->placeholder('-'),

                Tables\Columns\ColorColumn::make('font_color')
                    ->label('Text Color'),

                Tables\Columns\ColorColumn::make('qr_color')
                    ->label('QR Color')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\ColorColumn::make('qr_background_color')
                    ->label('QR Background')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('card_template_id')
                    ->label('Card Template')
                    ->relationship('cardTemplate', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('placeholder_key')
                    ->label('Placeholder')
                    ->options(CardTemplatePlaceholder::allowedPlaceholderKeys())
                    ->searchable(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCardTemplatePlaceholders::route('/'),
            'create' => Pages\CreateCardTemplatePlaceholder::route('/create'),
            'edit' => Pages\EditCardTemplatePlaceholder::route('/{record}/edit'),
        ];
    }
}