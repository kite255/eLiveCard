<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\CardTemplate;
use App\Models\CardTemplatePlaceholder;
use App\Services\AuditLogService;
use App\Support\EliveNotification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CardTemplatesRelationManager extends RelationManager
{
    /**
     * Default vertical position for starter QR placeholders.
     */
    protected const DEFAULT_QR_Y_PERCENT = 58.0;
    protected static string $relationship = 'cardTemplates';

    protected static ?string $title = 'Card Templates';

    protected static ?string $modelLabel = 'Card Template';

    protected static ?string $pluralModelLabel = 'Card Templates';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return static::canAccessOwnerRecord($ownerRecord);
    }

    protected static function canAccessOwnerRecord(Model $ownerRecord): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isEventAdmin()) {
            return (int) ($ownerRecord->user_id ?? 0) === (int) $user->id;
        }

        return false;
    }

    protected function canManageCardTemplates(): bool
    {
        return static::canAccessOwnerRecord($this->getOwnerRecord())
            && (auth()->user()?->canManageCardDesigns() ?? false);
    }

    public function isReadOnly(): bool
    {
        return ! $this->canManageCardTemplates();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Template Details')
                    ->description('Upload and configure the invitation card background image for this event.')
                    ->icon('heroicon-o-photo')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Template Name')
                            ->placeholder('Example: Main Invitation Card')
                            ->required()
                            ->maxLength(255)
                            ->autofocus()
                            ->columnSpanFull(),

                        Forms\Components\FileUpload::make('template_image')
                            ->label('Card Template Image')
                            ->image()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->disk('public')
                            ->visibility('public')
                            ->storeFiles(true)
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
                            ->maxSize(1536)
                            ->maxFiles(1)
                            ->imagePreviewHeight('320')
                            ->panelLayout('compact')
                            ->loadingIndicatorPosition('center')
                            ->uploadButtonPosition('center')
                            ->uploadProgressIndicatorPosition('center')
                            ->removeUploadedFileButtonPosition('right')
                            ->helperText('Upload a high-resolution JPG, PNG, or WEBP image. Recommended: 1080 × 1920 pixels. Maximum size: 1.5 MB.')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('width')
                            ->label('Template Width')
                            ->numeric()
                            ->minValue(1)
                            ->default(1080)
                            ->required()
                            ->readOnly()
                            ->suffix('px')
                            ->helperText('Detected automatically from the uploaded image.'),

                        Forms\Components\TextInput::make('height')
                            ->label('Template Height')
                            ->numeric()
                            ->minValue(1)
                            ->default(1920)
                            ->required()
                            ->readOnly()
                            ->suffix('px')
                            ->helperText('Detected automatically from the uploaded image.'),

                        Forms\Components\Select::make('status')
                            ->label('Template Status')
                            ->options(CardTemplate::statuses())
                            ->default(CardTemplate::STATUS_DRAFT)
                            ->required()
                            ->native(false)
                            ->helperText('Only the active template is used for personalized card generation.')
                            ->columnSpanFull(),
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
                    ->visibility('public')
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
                    ->modalDescription('Upload a high-quality invitation background and configure how it will be used.')
                    ->modalWidth('4xl')
                    ->modalSubmitActionLabel('Upload Template')
                    ->visible(fn (): bool => $this->canManageCardTemplates())
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
                    ->after(function (CardTemplate $record): void {
                        AuditLogService::created(
                            subject: $record,
                            eventId: $record->event_id,
                            description: 'Card template was uploaded.',
                            metadata: [
                                'name' => $record->name,
                                'status' => $record->status,
                                'width' => $record->width,
                                'height' => $record->height,
                                'template_image' => $record->template_image,
                                'source' => 'filament_admin',
                            ],
                        );

                        EliveNotification::success(
                            title: 'Template uploaded successfully',
                            body: "Image size: {$record->width} × {$record->height} pixels. The template is ready for placeholder design.",
                            context: $record,
                            persistent: true,
                            actionLabel: 'Design Placeholders',
                            actionUrl: route('card-templates.designer', $record),
                            openActionInNewTab: true,
                        );
                    })
                    ->successNotification(null),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('View')
                        ->icon('heroicon-o-eye'),

                    Tables\Actions\EditAction::make()
                        ->label('Edit Template')
                        ->icon('heroicon-o-pencil-square')
                        ->modalHeading('Edit Card Template')
                        ->modalDescription('Update the template name, image, dimensions, or status.')
                        ->modalWidth('4xl')
                        ->modalSubmitActionLabel('Save Changes')
                        ->visible(fn (): bool => $this->canManageCardTemplates())
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
                        ->using(function (CardTemplate $record, array $data): CardTemplate {
                            $oldValues = $record->only([
                                'name',
                                'template_image',
                                'width',
                                'height',
                                'status',
                            ]);

                            $record->update($data);
                            $record->refresh();

                            AuditLogService::updated(
                                subject: $record,
                                eventId: $record->event_id,
                                description: 'Card template details were updated.',
                                oldValues: $oldValues,
                                newValues: $record->only([
                                    'name',
                                    'template_image',
                                    'width',
                                    'height',
                                    'status',
                                ]),
                                metadata: [
                                    'source' => 'filament_admin',
                                ],
                            );

                            return $record;
                        })
                        ->after(function (CardTemplate $record): void {
                            EliveNotification::success(
                                title: 'Template updated successfully',
                                body: "The latest template details have been saved. Image size: {$record->width} × {$record->height} pixels.",
                                context: $record,
                                actionLabel: 'Design Placeholders',
                                actionUrl: route('card-templates.designer', $record),
                                openActionInNewTab: true,
                            );
                        })
                        ->successNotification(null),

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
                        ->url(function (CardTemplate $record): string {
                            AuditLogService::record(
                                action: 'card_template.designer_opened',
                                subject: $record,
                                eventId: $record->event_id,
                                description: 'Card template placeholder designer was opened.',
                                metadata: [
                                    'template_name' => $record->name,
                                    'placeholders_count' => $record->placeholders()->count(),
                                ],
                            );

                            return route('card-templates.designer', $record);
                        })
                        ->openUrlInNewTab()
                        ->visible(fn (): bool => $this->canManageCardTemplates()),

                    Tables\Actions\Action::make('set_active')
                        ->label('Set Active')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Set Template as Active')
                        ->modalDescription('This will make this template active for card generation. Other templates for this event will be changed to draft.')
                        ->visible(fn (CardTemplate $record): bool => $this->canManageCardTemplates() && $record->status !== CardTemplate::STATUS_ACTIVE)
                        ->action(function (CardTemplate $record): void {
                            $oldValues = $record->only(['status']);

                            $deactivatedTemplateIds = CardTemplate::where('event_id', $record->event_id)
                                ->where('id', '!=', $record->id)
                                ->where('status', CardTemplate::STATUS_ACTIVE)
                                ->pluck('id')
                                ->all();

                            CardTemplate::whereIn('id', $deactivatedTemplateIds)
                                ->update([
                                    'status' => CardTemplate::STATUS_DRAFT,
                                ]);

                            $record->update([
                                'status' => CardTemplate::STATUS_ACTIVE,
                            ]);

                            AuditLogService::updated(
                                subject: $record,
                                eventId: $record->event_id,
                                description: 'Card template was set as active.',
                                oldValues: $oldValues,
                                newValues: $record->only(['status']),
                                metadata: [
                                    'deactivated_template_ids' => $deactivatedTemplateIds,
                                ],
                            );

                            EliveNotification::success(
                                title: 'Template set as active',
                                body: 'This template will now be used for personalized card generation.',
                                context: $record,
                            );
                        }),

                    Tables\Actions\Action::make('add_starter_placeholders')
                        ->label('Add Starter Placeholders')
                        ->icon('heroicon-o-sparkles')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Add Starter Placeholders')
                        ->modalDescription(
                            'This will add the common invitation placeholders. '
                            .'The QR code starts with the universal 200 px, 18% × 18%, dark-on-white configuration.'
                        )
                        ->visible(fn (): bool => $this->canManageCardTemplates())
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
                                    'placeholder_key' =>
                                        CardTemplatePlaceholder::PLACEHOLDER_QR_CODE,

                                    'label' => 'QR Code',

                                    'x_percent' => $this->defaultQrXPercent(),
                                    'y_percent' => $this->defaultQrYPercent(),

                                    'width_percent' =>
                                        CardTemplatePlaceholder::DEFAULT_QR_WIDTH_PERCENT,

                                    'height_percent' =>
                                        CardTemplatePlaceholder::DEFAULT_QR_HEIGHT_PERCENT,

                                    'font_size' =>
                                        CardTemplatePlaceholder::DEFAULT_FONT_SIZE,

                                    'font_color' =>
                                        CardTemplatePlaceholder::DEFAULT_FONT_COLOR,

                                    'font_weight' => 'normal',

                                    'font_family' =>
                                        CardTemplatePlaceholder::defaultFontFamily(),

                                    'text_align' => 'center',

                                    'qr_size' =>
                                        CardTemplatePlaceholder::DEFAULT_QR_SIZE,

                                    'qr_color' =>
                                        CardTemplatePlaceholder::DEFAULT_QR_COLOR,

                                    'qr_background_color' =>
                                        CardTemplatePlaceholder::DEFAULT_QR_BACKGROUND_COLOR,

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

                            $createdOrUpdated = [];

                            foreach ($starterPlaceholders as $placeholder) {
                                $savedPlaceholder = CardTemplatePlaceholder::updateOrCreate(
                                    [
                                        'card_template_id' => $record->id,
                                        'placeholder_key' => $placeholder['placeholder_key'],
                                    ],
                                    array_merge($placeholder, [
                                        'card_template_id' => $record->id,
                                    ])
                                );

                                $createdOrUpdated[] = [
                                    'id' => $savedPlaceholder->id,
                                    'key' => $savedPlaceholder->placeholder_key,
                                    'label' => $savedPlaceholder->label,
                                ];
                            }

                            AuditLogService::record(
                                action: 'card_template.starter_placeholders_added',
                                subject: $record,
                                eventId: $record->event_id,
                                description: 'Starter placeholders were added or updated.',
                                metadata: [
                                    'count' => count($createdOrUpdated),
                                    'placeholders' => $createdOrUpdated,
                                    'qr_defaults' => [
                                        'x_percent' => $this->defaultQrXPercent(),
                                        'y_percent' => $this->defaultQrYPercent(),
                                        'width_percent' =>
                                            CardTemplatePlaceholder::DEFAULT_QR_WIDTH_PERCENT,
                                        'height_percent' =>
                                            CardTemplatePlaceholder::DEFAULT_QR_HEIGHT_PERCENT,
                                        'qr_size' =>
                                            CardTemplatePlaceholder::DEFAULT_QR_SIZE,
                                        'qr_color' =>
                                            CardTemplatePlaceholder::DEFAULT_QR_COLOR,
                                        'qr_background_color' =>
                                            CardTemplatePlaceholder::DEFAULT_QR_BACKGROUND_COLOR,
                                    ],
                                ],
                            );

                            EliveNotification::success(
                                title: 'Starter placeholders added',
                                body: count($createdOrUpdated)
                                    .' placeholders are ready. The QR code uses the universal '
                                    .CardTemplatePlaceholder::DEFAULT_QR_SIZE
                                    .' px default and can now be adjusted in the designer.',
                                context: $record,
                                persistent: true,
                                actionLabel: 'Design Placeholders',
                                actionUrl: route('card-templates.designer', $record),
                                openActionInNewTab: true,
                            );
                        }),

                    Tables\Actions\Action::make('delete_placeholders')
                        ->label('Delete Placeholders')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Delete All Placeholders')
                        ->modalDescription('This will delete all placeholders for this template. The template image will not be deleted.')
                        ->visible(fn (CardTemplate $record): bool => $this->canManageCardTemplates() && $record->placeholders()->exists())
                        ->action(function (CardTemplate $record): void {
                            $placeholders = $record->placeholders()
                                ->get([
                                    'id',
                                    'placeholder_key',
                                    'label',
                                    'x_percent',
                                    'y_percent',
                                    'width_percent',
                                    'height_percent',
                                ])
                                ->toArray();

                            $record->placeholders()->delete();

                            AuditLogService::record(
                                action: 'card_template.placeholders_deleted',
                                subject: $record,
                                eventId: $record->event_id,
                                description: 'All placeholders were deleted from the card template.',
                                metadata: [
                                    'deleted_count' => count($placeholders),
                                    'placeholders' => $placeholders,
                                ],
                            );

                            EliveNotification::warning(
                                title: 'Placeholders deleted',
                                body: count($placeholders).' placeholder(s) were removed. The template image was not deleted.',
                                context: $record,
                            );
                        }),

                    Tables\Actions\Action::make('archive_template')
                        ->label('Archive Template')
                        ->icon('heroicon-o-archive-box')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->visible(fn (CardTemplate $record): bool => $this->canManageCardTemplates() && $record->status !== CardTemplate::STATUS_ARCHIVED)
                        ->action(function (CardTemplate $record): void {
                            $oldValues = $record->only(['status']);

                            $record->update([
                                'status' => CardTemplate::STATUS_ARCHIVED,
                            ]);

                            AuditLogService::updated(
                                subject: $record,
                                eventId: $record->event_id,
                                description: 'Card template was archived.',
                                oldValues: $oldValues,
                                newValues: $record->only(['status']),
                            );

                            EliveNotification::info(
                                title: 'Template archived',
                                body: 'The template has been removed from active use but its records remain available.',
                                context: $record,
                            );
                        }),

                    Tables\Actions\Action::make('delete_template')
                        ->label('Delete Template')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Delete Card Template')
                        ->modalDescription('Use this only for test templates. If cards were already generated, archive the template instead.')
                        ->visible(fn (): bool => $this->canManageCardTemplates())
                        ->action(function (CardTemplate $record): void {
                            if ($record->generatedCards()->exists()) {
                                EliveNotification::danger(
                                    title: 'Template cannot be deleted',
                                    body: 'This template already has generated cards. Archive it instead to preserve event records.',
                                    context: $record,
                                );

                                return;
                            }

                            $metadata = [
                                'name' => $record->name,
                                'status' => $record->status,
                                'width' => $record->width,
                                'height' => $record->height,
                                'template_image' => $record->template_image,
                                'placeholders_count' => $record->placeholders()->count(),
                            ];

                            AuditLogService::deleted(
                                subject: $record,
                                eventId: $record->event_id,
                                description: 'Card template was deleted.',
                                metadata: $metadata,
                            );

                            $templateImagePath = $this->normalizeTemplateImagePath(
                                $record->template_image
                            );

                            if (
                                filled($templateImagePath)
                                && Storage::disk('public')->exists($templateImagePath)
                            ) {
                                Storage::disk('public')->delete($templateImagePath);
                            }

                            $record->placeholders()->delete();
                            $record->delete();

                            EliveNotification::success(
                                title: 'Template deleted successfully',
                                body: 'The template image and placeholder records were removed.',
                                context: $record->name,
                            );
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
                        ->visible(fn (): bool => $this->canManageCardTemplates())
                        ->action(function ($records): void {
                            $records->each(function (CardTemplate $record): void {
                                $oldValues = $record->only(['status']);

                                $record->update([
                                    'status' => CardTemplate::STATUS_ARCHIVED,
                                ]);

                                AuditLogService::updated(
                                    subject: $record,
                                    eventId: $record->event_id,
                                    description: 'Card template was archived in bulk.',
                                    oldValues: $oldValues,
                                    newValues: $record->only(['status']),
                                    metadata: [
                                        'bulk_action' => true,
                                    ],
                                );
                            });

                            EliveNotification::info(
                                title: 'Selected templates archived',
                                body: $records->count().' template(s) were archived successfully.',
                                context: $this->getOwnerRecord(),
                            );
                        }),
                ]),
            ]);
    }

    protected function defaultQrXPercent(): float
    {
        return round(
            (100 - CardTemplatePlaceholder::DEFAULT_QR_WIDTH_PERCENT) / 2,
            4
        );
    }

    protected function defaultQrYPercent(): float
    {
        $maximumY = 100
            - CardTemplatePlaceholder::DEFAULT_QR_HEIGHT_PERCENT;

        return round(
            min(self::DEFAULT_QR_Y_PERCENT, $maximumY),
            4
        );
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