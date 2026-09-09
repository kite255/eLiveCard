<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CardTemplateResource\Pages;
use App\Models\CardTemplate;
use App\Models\Event;
use App\Models\Invitee;
use App\Services\CardGenerationService;
use App\Rules\AllowedCardTemplateDimensions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class CardTemplateResource extends Resource
{
    protected static ?string $model = CardTemplate::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Card Management';

    protected static ?string $navigationLabel = 'Card Templates';

    protected static ?string $modelLabel = 'Card Template';

    protected static ?string $pluralModelLabel = 'Card Templates';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['event']);

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
        return auth()->user()?->canManageCardDesigns() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->canManageCardDesigns() ?? false;
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
        return auth()->user()?->canManageCardDesigns() ?? false;
    }

    protected static function canAccessRecord(?CardTemplate $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
            return false;
        }

        if (! ($user->canManageCardDesigns() ?? false)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isEventAdmin()) {
            $record->loadMissing('event');

            return (int) ($record->event?->user_id ?? 0) === (int) $user->id;
        }

        return false;
    }

    protected static function visibleEventOptions(): array
    {
        $user = auth()->user();

        if (! $user || ! ($user->canManageCardDesigns() ?? false)) {
            return [];
        }

        return Event::query()
            ->when(
                $user->isEventAdmin(),
                fn (Builder $query): Builder => $query->where('user_id', $user->id)
            )
            ->orderBy('title')
            ->pluck('title', 'id')
            ->toArray();
    }

    protected static function defaultEventId(): ?int
    {
        $user = auth()->user();

        if (! $user?->isEventAdmin()) {
            return null;
        }

        return Event::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->value('id');
    }

    /**
     * Ensure old templates also receive their real source dimensions.
     *
     * New templates are handled automatically by CardTemplate::saving().
     */
    protected static function ensureAutomaticDimensions(CardTemplate $record): bool
    {
        if (! $record->hasTemplateImage()) {
            return false;
        }

        if (
            (int) $record->source_width > 0
            && (int) $record->source_height > 0
        ) {
            return true;
        }

        [$sourceWidth, $sourceHeight] = $record->detectSourceDimensions();

        if (! $sourceWidth || ! $sourceHeight) {
            return false;
        }

        if (! CardTemplate::hasAllowedDimensions($sourceWidth, $sourceHeight)) {
            return false;
        }

        $record->source_width = $sourceWidth;
        $record->source_height = $sourceHeight;
        $record->width = $sourceWidth;
        $record->height = $sourceHeight;

        $record->save();

        return true;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Template Details')
                    ->description('Upload a blank invitation card design and connect it to one social event.')
                    ->icon('heroicon-o-photo')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->label('Event')
                            ->options(fn (): array => static::visibleEventOptions())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->default(fn (): ?int => static::defaultEventId())
                            ->disabled(fn (): bool => auth()->user()?->isEventAdmin() ?? false)
                            ->dehydrated()
                            ->required()
                            ->native(false)
                            ->helperText('Super Admin can choose any event. Event Admin can only use own event.'),

                        Forms\Components\TextInput::make('name')
                            ->label('Template Name')
                            ->placeholder('Example: Wedding VIP Card')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(CardTemplate::statuses())
                            ->default(CardTemplate::STATUS_DRAFT)
                            ->required()
                            ->native(false)
                            ->helperText('Keep as Draft until placeholders are placed.'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Template Image')
                    ->description('Upload a high-quality card image. The system preserves its exact dimensions and aspect ratio.')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->schema([
                        Forms\Components\FileUpload::make('template_image')
                            ->label('Card Template Image')
                            ->image()
                            ->disk('public')
                            ->directory(
                                fn ($get): string => 'card-templates/event-' . ($get('event_id') ?: 'unassigned')
                            )
                            ->visibility('public')
                            ->imagePreviewHeight('360')
                            ->acceptedFileTypes([
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->rules([
                                new AllowedCardTemplateDimensions(),
                            ])
                            ->maxSize(5120)
                            ->downloadable()
                            ->openable()
                            ->required()
                            ->columnSpanFull()
                            ->helperText(
                                'Minimum 595 × 595 px, maximum 6000 × 6000 px, up to 25 MP. PNG, JPG, or WEBP. Original dimensions and aspect ratio are preserved.'
                            ),

                        Forms\Components\Placeholder::make('automatic_dimensions')
                            ->label('Automatic Size')
                            ->content(function (?CardTemplate $record): string {
                                if (! $record || ! $record->hasTemplateImage()) {
                                    return 'The real source size will be detected automatically after the template is saved.';
                                }

                                $sourceWidth = $record->source_image_width;
                                $sourceHeight = $record->source_image_height;
                                $designerWidth = $record->designer_width;
                                $designerHeight = $record->designer_height;

                                return "Source: {$sourceWidth} × {$sourceHeight}px · Designer: {$designerWidth} × {$designerHeight}px";
                            })
                            ->helperText(
                                'The designer, preview, and generated card use the exact uploaded dimensions so placeholder positions stay consistent.'
                            )
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->heading('Card Templates')
            ->description(
                'Upload templates, design placeholders, activate templates, and generate personalized invitation cards.'
            )
            ->columns([
                Tables\Columns\ImageColumn::make('template_image')
                    ->label('Template')
                    ->disk('public')
                    ->height(76)
                    ->width(56)
                    ->extraImgAttributes([
                        'class' => 'rounded-xl object-contain bg-white ring-1 ring-gray-200 dark:ring-gray-700',
                    ]),

                Tables\Columns\TextColumn::make('name')
                    ->label('Template Name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->description(
                        fn (CardTemplate $record): string => $record->event?->title ?? 'No event assigned'
                    ),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string => CardTemplate::statuses()[$state] ?? ucfirst($state)
                    )
                    ->color(fn (string $state): string => match ($state) {
                        CardTemplate::STATUS_ACTIVE => 'success',
                        CardTemplate::STATUS_DRAFT => 'warning',
                        CardTemplate::STATUS_ARCHIVED => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('dimensions')
                    ->label('Size')
                    ->state(function (CardTemplate $record): string {
                        return "{$record->source_image_width} × {$record->source_image_height}px";
                    })
                    ->description(function (CardTemplate $record): string {
                        return "Designer {$record->designer_width} × {$record->designer_height}px";
                    })
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('placeholders_count')
                    ->label('Placeholders')
                    ->counts('placeholders')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'warning')
                    ->sortable(),

                Tables\Columns\TextColumn::make('generated_cards_count')
                    ->label('Cards')
                    ->counts('generatedCards')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('M d, Y h:i A')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Event')
                    ->options(fn (): array => static::visibleEventOptions())
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(CardTemplate::statuses()),

                Tables\Filters\Filter::make('has_placeholders')
                    ->label('Has Placeholders')
                    ->query(fn (Builder $query): Builder => $query->has('placeholders')),
            ])
            ->actions([
                Action::make('designer')
                    ->label('Open Designer')
                    ->icon('heroicon-o-cursor-arrow-rays')
                    ->color('info')
                    ->button()
                    ->visible(fn (CardTemplate $record): bool => static::canAccessRecord($record))
                    ->action(function (CardTemplate $record) {
                        /*
                        |--------------------------------------------------------------------------
                        | Upgrade legacy templates before opening the designer.
                        |--------------------------------------------------------------------------
                        */
                        static::ensureAutomaticDimensions($record);

                        return redirect()->to(
                            static::getUrl('designer', ['record' => $record])
                        );
                    }),

                Action::make('refresh_dimensions')
                    ->label('Refresh Size')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (CardTemplate $record): bool => static::canAccessRecord($record))
                    ->action(function (CardTemplate $record): void {
                        [$sourceWidth, $sourceHeight] = $record->detectSourceDimensions();

                        if (! $sourceWidth || ! $sourceHeight) {
                            Notification::make()
                                ->title('Unable to detect image size')
                                ->body(
                                    'The template image could not be read from public storage. Confirm the file exists and storage:link is configured.'
                                )
                                ->danger()
                                ->send();

                            return;
                        }

                        if (! CardTemplate::hasAllowedDimensions($sourceWidth, $sourceHeight)) {
                            Notification::make()
                                ->title('Unsupported template size')
                                ->body('Template must be at least 595 × 595 px, no larger than 6000 × 6000 px, and must not exceed 25 MP.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->source_width = $sourceWidth;
                        $record->source_height = $sourceHeight;
                        $record->width = $sourceWidth;
                        $record->height = $sourceHeight;
                        $record->save();

                        Notification::make()
                            ->title('Template size refreshed')
                            ->body("Template confirmed at {$sourceWidth} × {$sourceHeight}px.")
                            ->success()
                            ->send();
                    }),

                Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Activate Template')
                    ->modalDescription(
                        'Activate this template only after placing and saving placeholders.'
                    )
                    ->visible(
                        fn (CardTemplate $record): bool => static::canAccessRecord($record) && ! $record->isActive()
                    )
                    ->action(function (CardTemplate $record): void {
                        if (! $record->event_id) {
                            Notification::make()
                                ->title('Template has no event')
                                ->body('Please assign this template to an event before activating it.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if (! $record->template_image) {
                            Notification::make()
                                ->title('Template image missing')
                                ->body('Please upload a card template image first.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if (! static::ensureAutomaticDimensions($record)) {
                            Notification::make()
                                ->title('Image dimensions unavailable')
                                ->body(
                                    'The template dimensions are unavailable or outside the supported range. Use at least 595 × 595 px, no larger than 6000 × 6000 px, and up to 25 MP.'
                                )
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($record->visiblePlaceholders()->count() === 0) {
                            Notification::make()
                                ->title('No visible placeholders found')
                                ->body(
                                    'Open the designer and save placeholders before activating this template.'
                                )
                                ->warning()
                                ->send();

                            return;
                        }

                        CardTemplate::query()
                            ->where('event_id', $record->event_id)
                            ->where('id', '!=', $record->id)
                            ->where('status', CardTemplate::STATUS_ACTIVE)
                            ->update(['status' => CardTemplate::STATUS_DRAFT]);

                        $record->update([
                            'status' => CardTemplate::STATUS_ACTIVE,
                        ]);

                        Notification::make()
                            ->title('Template activated')
                            ->body('This template is now ready for card generation.')
                            ->success()
                            ->send();
                    }),

                Action::make('generate_cards')
                    ->label('Generate Cards')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Generate Personalized Cards')
                    ->modalDescription(
                        'This will generate cards for all invitees under this template event.'
                    )
                    ->visible(
                        fn (CardTemplate $record): bool => static::canAccessRecord($record) && $record->isActive()
                    )
                    ->action(function (CardTemplate $record): void {
                        if (! static::canAccessRecord($record)) {
                            Notification::make()
                                ->title('Access denied')
                                ->body('You are not allowed to generate cards for this event.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if (! $record->event_id) {
                            Notification::make()
                                ->title('Template has no event')
                                ->body(
                                    'Please assign this template to an event before generating cards.'
                                )
                                ->danger()
                                ->send();

                            return;
                        }

                        if (! $record->template_image) {
                            Notification::make()
                                ->title('Template image missing')
                                ->body('Please upload a template image before generating cards.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if (! $record->isActive()) {
                            Notification::make()
                                ->title('Template is not active')
                                ->body('Only active templates can generate cards.')
                                ->warning()
                                ->send();

                            return;
                        }

                        if (! static::ensureAutomaticDimensions($record)) {
                            Notification::make()
                                ->title('Image dimensions unavailable')
                                ->body(
                                    'The template dimensions are unavailable or outside the supported range. Use at least 595 × 595 px, no larger than 6000 × 6000 px, and up to 25 MP.'
                                )
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($record->visiblePlaceholders()->count() === 0) {
                            Notification::make()
                                ->title('No visible placeholders found')
                                ->body(
                                    'Please open the designer and save placeholder positions first.'
                                )
                                ->warning()
                                ->send();

                            return;
                        }

                        $invitees = Invitee::query()
                            ->where('event_id', $record->event_id)
                            ->with('cardType')
                            ->get();

                        if ($invitees->isEmpty()) {
                            Notification::make()
                                ->title('No invitees found')
                                ->body('This event has no invitees to generate cards for.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $service = app(CardGenerationService::class);

                        $generatedCount = 0;
                        $failedCount = 0;

                        foreach ($invitees as $invitee) {
                            try {
                                $service->generate($record, $invitee);
                                $generatedCount++;
                            } catch (Throwable $exception) {
                                report($exception);
                                $failedCount++;
                            }
                        }

                        if ($failedCount > 0) {
                            Notification::make()
                                ->title('Cards generated with some errors')
                                ->body(
                                    "Generated: {$generatedCount}. Failed: {$failedCount}. Check Laravel logs."
                                )
                                ->warning()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Cards generated successfully')
                            ->body(
                                "{$generatedCount} personalized cards generated successfully."
                            )
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->label('Edit')
                    ->visible(fn (CardTemplate $record): bool => static::canAccessRecord($record)),

                Tables\Actions\DeleteAction::make()
                    ->label('Delete')
                    ->visible(fn (CardTemplate $record): bool => static::canAccessRecord($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(
                            fn (): bool => auth()->user()?->canManageCardDesigns() ?? false
                        ),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-photo')
            ->emptyStateHeading('No card templates yet')
            ->emptyStateDescription(
                'Upload a card template first, then open the designer to place invitee placeholders.'
            )
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Create Template')
                    ->icon('heroicon-o-plus')
                    ->visible(
                        fn (): bool => auth()->user()?->canManageCardDesigns() ?? false
                    ),
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
            'index' => Pages\ListCardTemplates::route('/'),
            'create' => Pages\CreateCardTemplate::route('/create'),
            'edit' => Pages\EditCardTemplate::route('/{record}/edit'),
            'designer' => Pages\CardTemplateDesigner::route('/{record}/designer'),
        ];
    }
}
