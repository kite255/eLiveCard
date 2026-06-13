<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CardTypesRelationManager extends RelationManager
{
    protected static string $relationship = 'cardTypes';

    protected static ?string $title = 'Card Types';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Card Type Details')
                    ->description('Create only the card types you want to use for this event.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Card Type Name')
                            ->placeholder('Example: Single, Double, Family')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('allowed_people')
                            ->label('Guests')
                            ->helperText('Total number of people allowed with this card type.')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),

                        Forms\Components\ColorPicker::make('color')
                            ->label('Display Color')
                            ->default('#213B73'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Only active card types should be used when adding or importing invitees.')
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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('allowed_people')
                    ->label('Guests')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\ColorColumn::make('color')
                    ->label('Color')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'asc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive')
                    ->placeholder('All')
                    ->native(false),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Card Type'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('No card types yet')
            ->emptyStateDescription('Create the card types you need for this event.')
            ->emptyStateIcon('heroicon-o-identification');
    }
}