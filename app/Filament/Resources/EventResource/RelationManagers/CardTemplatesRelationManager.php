<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\CardTemplate;
use App\Models\CardTemplatePlaceholder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CardTemplatesRelationManager extends RelationManager
{
    protected static string $relationship = 'cardTemplates';

    protected static ?string $title = 'Card Templates';

    protected static ?string $modelLabel = 'Card Template';

    protected static ?string $pluralModelLabel = 'Card Templates';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Template Details')
                    ->description('Upload the invitation card background image for this event.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Template Name')
                            ->placeholder('Example: Main Invitation Card')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\FileUpload::make('template_image')
                            ->label('Card Template Image')
                            ->image()
                            ->required()
                            ->disk('public')
                            ->directory(fn (): string => 'events/' . $this->getOwnerRecord()->id . '/card-templates')
                            ->visibility('public')
                            ->storeFiles(true)
                            ->moveFiles()
                            ->saveUploadedFileUsing(function ($file): string {
                                $directory = 'events/' . $this->getOwnerRecord()->id . '/card-templates';

                                Storage::disk('public')->makeDirectory($directory);

                                $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                                $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
                                $filename = Str::slug($name) . '-' . now()->format('YmdHis') . '.' . $extension;

                                return $file->storeAs($directory, $filename, 'public');
                            })
                            ->acceptedFileTypes([
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->maxSize(25600) // 25MB
                            ->maxFiles(1)
                            ->imagePreviewHeight('350')
                            ->loadingIndicatorPosition('left')
                            ->panelAspectRatio('9:16')
                            ->panelLayout('integrated')
                            ->removeUploadedFileButtonPosition('right')
                            ->uploadButtonPosition('left')
                            ->uploadProgressIndicatorPosition('left')
                            ->getUploadedFileNameForStorageUsing(function ($file): string {
                                $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                                $extension = strtolower($file->getClientOriginalExtension());

                                return Str::slug($name) . '-' . now()->format('YmdHis') . '.' . $extension;
                            })
                            ->helperText('Use PNG, JPG, or WEBP. Recommended: high quality portrait invitation card, 1080 × 1920. Maximum upload size: 25MB.'),

                        Forms\Components\TextInput::make('width')
                            ->label('Template Width')
                            ->numeric()
                            ->minValue(1)
                            ->default(1080)
                            ->required(),

                        Forms\Components\TextInput::make('height')
                            ->label('Template Height')
                            ->numeric()
                            ->minValue(1)
                            ->default(1920)
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(CardTemplate::statuses())
                            ->default(CardTemplate::STATUS_DRAFT)
                            ->required()
                            ->helperText('Use Active for the template that should be used to generate cards.'),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->emptyStateHeading('No card templates yet')
            ->emptyStateDescription('Upload a card template image before generating personalized invitation cards.')
            ->emptyStateIcon('heroicon-o-photo')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('template_image')
                    ->label('Preview')
                    ->disk('public')
                    ->height(95)
                    ->width(70)
                    ->square(false)
                    ->extraImgAttributes([
                        'style' => 'object-fit: contain; background: #F8FAFC; border-radius: 8px; border: 1px solid #e5e7eb;',
                    ]),

                Tables\Columns\TextColumn::make('name')
                    ->label('Template Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('width')
                    ->label('Width')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('height')
                    ->label('Height')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => CardTemplate::statuses()[$state] ?? ucfirst((string) $state))
                    ->color(fn (?string $state): string => match ($state) {
                        CardTemplate::STATUS_ACTIVE => 'success',
                        CardTemplate::STATUS_DRAFT => 'warning',
                        CardTemplate::STATUS_ARCHIVED => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('placeholders_count')
                    ->label('Placeholders')
                    ->counts('placeholders')
                    ->alignCenter()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('generated_cards_count')
                    ->label('Generated')
                    ->counts('generatedCards')
                    ->alignCenter()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(CardTemplate::statuses()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Upload Template')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->modalHeading('Upload Card Template')
                    ->modalSubmitActionLabel('Save Template')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['event_id'] = $this->getOwnerRecord()->id;

                        $this->setImageDimensions($data);

                        if (($data['status'] ?? null) === CardTemplate::STATUS_ACTIVE) {
                            CardTemplate::where('event_id', $this->getOwnerRecord()->id)
                                ->where('status', CardTemplate::STATUS_ACTIVE)
                                ->update(['status' => CardTemplate::STATUS_DRAFT]);
                        }

                        return $data;
                    })
                    ->successNotificationTitle('Card template uploaded successfully'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('View')
                        ->icon('heroicon-o-eye'),

                    Tables\Actions\EditAction::make()
                        ->label('Edit Template')
                        ->icon('heroicon-o-pencil-square')
                        ->mutateFormDataUsing(function (array $data, CardTemplate $record): array {
                            $this->setImageDimensions($data);

                            if (($data['status'] ?? null) === CardTemplate::STATUS_ACTIVE) {
                                CardTemplate::where('event_id', $record->event_id)
                                    ->where('id', '!=', $record->id)
                                    ->where('status', CardTemplate::STATUS_ACTIVE)
                                    ->update(['status' => CardTemplate::STATUS_DRAFT]);
                            }

                            return $data;
                        })
                        ->successNotificationTitle('Card template updated successfully'),

                    Tables\Actions\Action::make('open_template_image')
                        ->label('Open Image')
                        ->icon('heroicon-o-photo')
                        ->url(fn (CardTemplate $record): ?string => $record->template_image_url)
                        ->openUrlInNewTab()
                        ->visible(fn (CardTemplate $record): bool => filled($record->template_image)),

                    Tables\Actions\Action::make('design_placeholders')
                        ->label('Design Placeholders')
                        ->icon('heroicon-o-cursor-arrow-rays')
                        ->color('primary')
                        ->url(fn (CardTemplate $record): string => route('card-templates.designer', $record))
                        ->openUrlInNewTab(),

                    Tables\Actions\Action::make('set_active')
                        ->label('Set Active')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Set Template as Active')
                        ->modalDescription('This will make this template active for card generation. Other templates for this event will be changed to draft.')
                        ->visible(fn (CardTemplate $record): bool => $record->status !== CardTemplate::STATUS_ACTIVE)
                        ->action(function (CardTemplate $record): void {
                            CardTemplate::where('event_id', $record->event_id)
                                ->where('id', '!=', $record->id)
                                ->where('status', CardTemplate::STATUS_ACTIVE)
                                ->update([
                                    'status' => CardTemplate::STATUS_DRAFT,
                                ]);

                            $record->update([
                                'status' => CardTemplate::STATUS_ACTIVE,
                            ]);

                            Notification::make()
                                ->title('Template set as active')
                                ->body('This template will now be used for card generation.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('add_starter_placeholders')
                        ->label('Add Starter Placeholders')
                        ->icon('heroicon-o-sparkles')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Add Starter Placeholders')
                        ->modalDescription('This will add common placeholders: Invitee Name, Card Type, Allowed Guests, QR Code, Serial Number, and Table Number.')
                        ->action(function (CardTemplate $record): void {
                            $starterPlaceholders = [
                                [
                                    'placeholder_key' => CardTemplatePlaceholder::PLACEHOLDER_NAME,
                                    'label' => 'Invitee Name',
                                    'x_percent' => 50,
                                    'y_percent' => 42,
                                    'width_percent' => 75,
                                    'height_percent' => 8,
                                    'font_size' => 48,
                                    'font_color' => '#111827',
                                    'font_weight' => 'bold',
                                    'font_family' => CardTemplatePlaceholder::FONT_MONTSERRAT,
                                    'text_align' => 'center',
                                    'is_visible' => true,
                                    'sort_order' => 1,
                                ],
                                [
                                    'placeholder_key' => CardTemplatePlaceholder::PLACEHOLDER_CARD_TYPE,
                                    'label' => 'Card Type',
                                    'x_percent' => 50,
                                    'y_percent' => 49,
                                    'width_percent' => 50,
                                    'height_percent' => 5,
                                    'font_size' => 28,
                                    'font_color' => '#213B73',
                                    'font_weight' => 'bold',
                                    'font_family' => CardTemplatePlaceholder::FONT_MONTSERRAT,
                                    'text_align' => 'center',
                                    'is_visible' => true,
                                    'sort_order' => 2,
                                ],
                                [
                                    'placeholder_key' => CardTemplatePlaceholder::PLACEHOLDER_ALLOWED_GUESTS,
                                    'label' => 'Allowed Guests',
                                    'x_percent' => 50,
                                    'y_percent' => 55,
                                    'width_percent' => 45,
                                    'height_percent' => 5,
                                    'font_size' => 24,
                                    'font_color' => '#111827',
                                    'font_weight' => 'normal',
                                    'font_family' => CardTemplatePlaceholder::FONT_MONTSERRAT,
                                    'text_align' => 'center',
                                    'is_visible' => true,
                                    'sort_order' => 3,
                                ],
                                [
                                    'placeholder_key' => CardTemplatePlaceholder::PLACEHOLDER_QR_CODE,
                                    'label' => 'QR Code',
                                    'x_percent' => 50,
                                    'y_percent' => 73,
                                    'width_percent' => 22,
                                    'height_percent' => 22,
                                    'font_size' => 24,
                                    'font_color' => '#111827',
                                    'font_weight' => 'normal',
                                    'font_family' => CardTemplatePlaceholder::FONT_MONTSERRAT,
                                    'text_align' => 'center',
                                    'qr_size' => 220,
                                    'qr_color' => '#111827',
                                    'qr_background_color' => '#FFFFFF',
                                    'is_visible' => true,
                                    'sort_order' => 4,
                                ],
                                [
                                    'placeholder_key' => CardTemplatePlaceholder::PLACEHOLDER_SERIAL_NUMBER,
                                    'label' => 'Serial Number',
                                    'x_percent' => 50,
                                    'y_percent' => 89,
                                    'width_percent' => 60,
                                    'height_percent' => 5,
                                    'font_size' => 22,
                                    'font_color' => '#111827',
                                    'font_weight' => 'normal',
                                    'font_family' => CardTemplatePlaceholder::FONT_MONTSERRAT,
                                    'text_align' => 'center',
                                    'is_visible' => true,
                                    'sort_order' => 5,
                                ],
                                [
                                    'placeholder_key' => CardTemplatePlaceholder::PLACEHOLDER_TABLE_NUMBER,
                                    'label' => 'Table Number',
                                    'x_percent' => 50,
                                    'y_percent' => 94,
                                    'width_percent' => 50,
                                    'height_percent' => 5,
                                    'font_size' => 22,
                                    'font_color' => '#111827',
                                    'font_weight' => 'bold',
                                    'font_family' => CardTemplatePlaceholder::FONT_MONTSERRAT,
                                    'text_align' => 'center',
                                    'is_visible' => true,
                                    'sort_order' => 6,
                                ],
                            ];

                            foreach ($starterPlaceholders as $placeholder) {
                                CardTemplatePlaceholder::updateOrCreate(
                                    [
                                        'card_template_id' => $record->id,
                                        'placeholder_key' => $placeholder['placeholder_key'],
                                    ],
                                    array_merge($placeholder, [
                                        'card_template_id' => $record->id,
                                    ])
                                );
                            }

                            Notification::make()
                                ->title('Starter placeholders added')
                                ->body('Now open Design Placeholders and drag them to the correct positions.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('delete_placeholders')
                        ->label('Delete Placeholders')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Delete All Placeholders')
                        ->modalDescription('This will delete all placeholders for this template. The template image will not be deleted.')
                        ->visible(fn (CardTemplate $record): bool => $record->placeholders()->exists())
                        ->action(function (CardTemplate $record): void {
                            $record->placeholders()->delete();

                            Notification::make()
                                ->title('Placeholders deleted successfully')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('archive_template')
                        ->label('Archive Template')
                        ->icon('heroicon-o-archive-box')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->visible(fn (CardTemplate $record): bool => $record->status !== CardTemplate::STATUS_ARCHIVED)
                        ->action(function (CardTemplate $record): void {
                            $record->update([
                                'status' => CardTemplate::STATUS_ARCHIVED,
                            ]);

                            Notification::make()
                                ->title('Template archived successfully')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('delete_template')
                        ->label('Delete Template')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Delete Card Template')
                        ->modalDescription('Use this only for test templates. If cards were already generated, archive the template instead.')
                        ->action(function (CardTemplate $record): void {
                            if ($record->generatedCards()->exists()) {
                                Notification::make()
                                    ->title('Template cannot be deleted')
                                    ->body('This template already has generated cards. Archive it instead to keep records safe.')
                                    ->danger()
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            if (filled($record->template_image) && Storage::disk('public')->exists($record->template_image)) {
                                Storage::disk('public')->delete($record->template_image);
                            }

                            $record->placeholders()->delete();
                            $record->delete();

                            Notification::make()
                                ->title('Template deleted successfully')
                                ->success()
                                ->send();
                        }),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('archive_selected')
                        ->label('Archive Selected')
                        ->icon('heroicon-o-archive-box')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $records->each(function (CardTemplate $record): void {
                                $record->update([
                                    'status' => CardTemplate::STATUS_ARCHIVED,
                                ]);
                            });

                            Notification::make()
                                ->title('Selected templates archived successfully')
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    protected function setImageDimensions(array &$data): void
    {
        if (blank($data['template_image'] ?? null)) {
            return;
        }

        $imageValue = is_array($data['template_image'])
            ? collect($data['template_image'])->filter()->first()
            : $data['template_image'];

        $path = $this->normalizeTemplateImagePath($imageValue);

        if (blank($path) || ! Storage::disk('public')->exists($path)) {
            return;
        }

        $fullPath = Storage::disk('public')->path($path);
        $imageSize = @getimagesize($fullPath);

        if (! is_array($imageSize)) {
            return;
        }

        $data['width'] = (int) ($imageSize[0] ?? ($data['width'] ?? 1080));
        $data['height'] = (int) ($imageSize[1] ?? ($data['height'] ?? 1920));
        $data['template_image'] = $path;
    }

    protected function normalizeTemplateImagePath(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $path = trim($path);

        if (Str::startsWith($path, ['http://', 'https://'])) {
            $urlPath = parse_url($path, PHP_URL_PATH);
            $path = is_string($urlPath) ? $urlPath : $path;
        }

        $path = ltrim($path, '/');

        foreach (['storage/', 'public/'] as $prefix) {
            if (Str::startsWith($path, $prefix)) {
                $path = Str::after($path, $prefix);
            }
        }

        return filled($path) ? $path : null;
    }

}