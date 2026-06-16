<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CardTypesRelationManager extends RelationManager
{
    protected static string $relationship = 'cardTypes';

    protected static ?string $title = 'Card Types';

    protected static ?string $modelLabel = 'Card Type';

    protected static ?string $pluralModelLabel = 'Card Types';

    public function form(Form $form): Form
    {
        return $form->schema($this->getCardTypeFormSchema());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->emptyStateHeading('No card types yet')
            ->emptyStateDescription('Create your own card type for this event, for example Bride Side VIP, Family Friend, Committee Member, Single, Double, Family, VIP, or VVIP.')
            ->emptyStateIcon('heroicon-o-identification')
            ->emptyStateActions([
                Tables\Actions\Action::make('add_card_type_empty')
                    ->label('Add Card Type')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->modalHeading('Add Card Type')
                    ->modalSubmitActionLabel('Save Card Type')
                    ->form($this->getCardTypeFormSchema())
                    ->action(fn (array $data) => $this->createCardType($data)),

                Tables\Actions\Action::make('create_default_card_types_empty')
                    ->label('Create Default Card Types')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Create default card types')
                    ->modalDescription('This will create standard social-event card types for this event. Existing card types with the same name will be skipped.')
                    ->modalSubmitActionLabel('Create Defaults')
                    ->action(fn () => $this->createDefaultCardTypes()),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Card Type')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('allowed_people')
                    ->label('Allowed Guests')
                    ->alignCenter()
                    ->badge()
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('invitees_count')
                    ->label('Invitees')
                    ->counts('invitees')
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($state): string => (int) $state > 0 ? 'success' : 'gray'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->alignCenter(),

                Tables\Columns\ColorColumn::make('color')
                    ->label('Color')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(45)
                    ->placeholder('-')
                    ->toggleable(),

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
                Tables\Actions\Action::make('add_card_type')
                    ->label('Add Card Type')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->button()
                    ->modalHeading('Add Card Type')
                    ->modalSubmitActionLabel('Save Card Type')
                    ->form($this->getCardTypeFormSchema())
                    ->action(fn (array $data) => $this->createCardType($data)),

                Tables\Actions\Action::make('create_default_card_types')
                    ->label('Create Default Card Types')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->button()
                    ->requiresConfirmation()
                    ->modalHeading('Create default card types')
                    ->modalDescription('This will create standard social-event card types for this event. Existing card types with the same name will be skipped.')
                    ->modalSubmitActionLabel('Create Defaults')
                    ->action(fn () => $this->createDefaultCardTypes()),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('edit_card_type')
                        ->label('Edit Card Type')
                        ->icon('heroicon-o-pencil-square')
                        ->color('primary')
                        ->modalHeading(fn (Model $record): string => 'Edit Card Type: ' . $record->name)
                        ->modalSubmitActionLabel('Save Changes')
                        ->fillForm(fn (Model $record): array => [
                            'name' => $record->name,
                            'allowed_people' => $record->allowed_people,
                            'color' => $record->color ?: '#213B73',
                            'is_active' => (bool) $record->is_active,
                            'description' => $record->description,
                        ])
                        ->form($this->getCardTypeFormSchema())
                        ->action(function (Model $record, array $data): void {
                            $data = $this->prepareCardTypeData($data);

                            $this->validateUniqueCardTypeName($data['name'], $record->id);

                            $record->update($data);

                            Notification::make()
                                ->title('Card type updated successfully')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('deactivate_card_type')
                        ->label('Deactivate Card Type')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Deactivate card type')
                        ->modalDescription('Existing invitees will remain linked, but this card type will not be used for new invitees/imports.')
                        ->visible(fn (Model $record): bool => (bool) $record->is_active)
                        ->action(function (Model $record): void {
                            $record->update(['is_active' => false]);

                            Notification::make()
                                ->title('Card type deactivated')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('activate_card_type')
                        ->label('Activate Card Type')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Activate card type')
                        ->visible(fn (Model $record): bool => ! (bool) $record->is_active)
                        ->action(function (Model $record): void {
                            $record->update(['is_active' => true]);

                            Notification::make()
                                ->title('Card type activated')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('delete_card_type')
                        ->label('Delete Card Type')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Delete card type')
                        ->modalDescription('Delete only unused card types. If invitees are using this card type, delete will be blocked.')
                        ->action(function (Model $record): void {
                            try {
                                if ($this->cardTypeHasInvitees($record)) {
                                    Notification::make()
                                        ->title('Card type cannot be deleted')
                                        ->body('This card type is already assigned to invitees. Deactivate it instead of deleting.')
                                        ->danger()
                                        ->persistent()
                                        ->send();

                                    return;
                                }

                                $record->delete();

                                Notification::make()
                                    ->title('Card type deleted successfully')
                                    ->success()
                                    ->send();
                            } catch (Throwable $e) {
                                Notification::make()
                                    ->title('Card type could not be deleted')
                                    ->body('This card type may already be used by invitees. Deactivate it instead.')
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('activate_selected_card_types')
                    ->label('Activate Selected')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function ($records): void {
                        $records->each(fn ($record) => $record->update(['is_active' => true]));

                        Notification::make()
                            ->title('Selected card types activated')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\BulkAction::make('deactivate_selected_card_types')
                    ->label('Deactivate Selected')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Existing invitees remain linked, but inactive card types will not be used for new invitees/imports.')
                    ->deselectRecordsAfterCompletion()
                    ->action(function ($records): void {
                        $records->each(fn ($record) => $record->update(['is_active' => false]));

                        Notification::make()
                            ->title('Selected card types deactivated')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    protected function getCardTypeFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Card Type Details')
                ->description('Create and manage your own card types for this event.')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Card Type Name')
                        ->placeholder('Example: Bride Side VIP, Family Friend, Committee Member')
                        ->required()
                        ->maxLength(100)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, callable $set) => $set('name', trim((string) $state))),

                    Forms\Components\TextInput::make('allowed_people')
                        ->label('Allowed Guests')
                        ->helperText('Total number of people allowed with this card type.')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
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
                        ->maxLength(255)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ];
    }

    protected function createCardType(array $data): void
    {
        $data = $this->prepareCardTypeData($data);

        $this->validateUniqueCardTypeName($data['name']);

        $this->getOwnerRecord()
            ->cardTypes()
            ->create($data);

        Notification::make()
            ->title('Card type added successfully')
            ->success()
            ->send();
    }

    protected function createDefaultCardTypes(): void
    {
        $created = 0;
        $skipped = 0;

        $defaults = [
            [
                'name' => 'Single',
                'allowed_people' => 1,
                'color' => '#213B73',
                'description' => 'One person only.',
            ],
            [
                'name' => 'Double',
                'allowed_people' => 2,
                'color' => '#FD9618',
                'description' => 'Invitee plus one guest.',
            ],
            [
                'name' => 'Family',
                'allowed_people' => 5,
                'color' => '#16A34A',
                'description' => 'Family invitation.',
            ],
            [
                'name' => 'VIP',
                'allowed_people' => 1,
                'color' => '#7C3AED',
                'description' => 'VIP guest invitation.',
            ],
            [
                'name' => 'VVIP',
                'allowed_people' => 2,
                'color' => '#B45309',
                'description' => 'Very important guest invitation.',
            ],
            [
                'name' => 'Committee',
                'allowed_people' => 4,
                'color' => '#0EA5E9',
                'description' => 'Committee or organizing team card.',
            ],
            [
                'name' => 'Custom',
                'allowed_people' => 1,
                'color' => '#111827',
                'description' => 'Custom card type.',
            ],
        ];

        DB::transaction(function () use ($defaults, &$created, &$skipped): void {
            foreach ($defaults as $cardType) {
                $exists = $this->getOwnerRecord()
                    ->cardTypes()
                    ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($cardType['name']))])
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                $this->getOwnerRecord()
                    ->cardTypes()
                    ->create([
                        'name' => $cardType['name'],
                        'allowed_people' => $cardType['allowed_people'],
                        'color' => $cardType['color'],
                        'description' => $cardType['description'],
                        'is_active' => true,
                    ]);

                $created++;
            }
        });

        Notification::make()
            ->title('Default card types prepared')
            ->body("Created: {$created}. Skipped existing: {$skipped}.")
            ->success()
            ->persistent()
            ->send();
    }

    protected function prepareCardTypeData(array $data): array
    {
        $data['name'] = trim((string) ($data['name'] ?? ''));
        $data['allowed_people'] = max(1, (int) ($data['allowed_people'] ?? 1));
        $data['color'] = $data['color'] ?: '#213B73';
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['description'] = $data['description'] ?? null;

        return $data;
    }

    protected function validateUniqueCardTypeName(?string $name, ?int $ignoreId = null): void
    {
        if (blank($name)) {
            return;
        }

        $normalizedName = strtolower(trim($name));

        $exists = $this->getOwnerRecord()
            ->cardTypes()
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalizedName])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => "Card type '{$name}' already exists for this event.",
            ]);
        }
    }

    protected function cardTypeHasInvitees(Model $record): bool
    {
        if (method_exists($record, 'invitees')) {
            return $record->invitees()->exists();
        }

        return $this->getOwnerRecord()
            ->invitees()
            ->where('card_type_id', $record->id)
            ->exists();
    }
}