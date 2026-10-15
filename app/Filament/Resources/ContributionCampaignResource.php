<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContributionCampaignResource\Pages;
use App\Filament\Resources\ContributionCampaignResource\RelationManagers\RecipientsRelationManager;
use App\Models\CardTemplatePlaceholder;
use App\Models\ContributionCampaign;
use App\Models\Event;
use App\Rules\AllowedCardTemplateDimensions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContributionCampaignResource extends Resource
{
    protected static ?string $model = ContributionCampaign::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Communication';
    protected static ?string $navigationLabel = 'Contribution Cards';
    protected static ?string $modelLabel = 'Contribution Campaign';
    protected static ?string $pluralModelLabel = 'Contribution Cards';
    protected static ?int $navigationSort = 5;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('event')->withCount('recipients');
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $campaigns) use ($user): void {
            $campaigns->where('created_by', $user->id)
                ->orWhereHas('event', function (Builder $events) use ($user): void {
                    $events->where('user_id', $user->id)
                        ->orWhereHas('assignedUsers', function (Builder $assigned) use ($user): void {
                            $assigned->where('users.id', $user->id)
                                ->where('event_user.role', 'event_admin')
                                ->where('event_user.is_active', true);
                        });
                });
        });
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canSendMessages() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->canSendMessages() ?? false;
    }

    public static function canView($record): bool
    {
        return static::canManage($record);
    }

    public static function canEdit($record): bool
    {
        return static::canManage($record);
    }

    public static function canDelete($record): bool
    {
        return static::canManage($record);
    }

    private static function canManage(?ContributionCampaign $campaign): bool
    {
        $user = auth()->user();
        return $user && $campaign
            && ($user->canSendMessages() ?? false)
            && ($user->isSuperAdmin() || $campaign->created_by === $user->id || $campaign->event?->canBeManagedBy($user));
    }

    private static function eventOptions(): array
    {
        $user = auth()->user();

        return Event::query()
            ->when(! $user?->isSuperAdmin(), function (Builder $query) use ($user): void {
                $query->where(function (Builder $events) use ($user): void {
                    $events->where('user_id', $user?->id)
                        ->orWhereHas('assignedUsers', function (Builder $assigned) use ($user): void {
                            $assigned->where('users.id', $user?->id)
                                ->where('event_user.role', 'event_admin')
                                ->where('event_user.is_active', true);
                        });
                });
            })
            ->orderBy('title')
            ->pluck('title', 'id')
            ->toArray();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Contribution Campaign')
                ->description('Set up a wedding, church, family or other contribution campaign.')
                ->schema([
                    Forms\Components\Select::make('campaign_type')
                        ->label('Campaign Type')
                        ->options(ContributionCampaign::types())
                        ->default('wedding')->required()->native(false),
                    Forms\Components\Select::make('event_id')
                        ->label('Associated Event (Optional)')
                        ->helperText('Leave blank for a standalone contribution campaign.')
                        ->options(fn (): array => static::eventOptions())
                        ->searchable()->preload(),
                    Forms\Components\TextInput::make('name')
                        ->label('Campaign Name')
                        ->placeholder('Example: Wedding Committee Contributions')
                        ->required()->maxLength(255),
                    Forms\Components\DatePicker::make('deadline')->label('Contribution Deadline')->native(false),
                    Forms\Components\Textarea::make('description')->label('Purpose / Description')->rows(2)->maxLength(2000)->columnSpanFull(),
                    Forms\Components\Select::make('status')
                        ->options(ContributionCampaign::statuses())
                        ->default(ContributionCampaign::STATUS_DRAFT)
                        ->required()->native(false),
                    Forms\Components\FileUpload::make('template_image')
                        ->label('Contribution Card Template')
                        ->image()->disk('public')->visibility('public')
                        ->directory(fn (Forms\Get $get): string => $get('event_id') ? 'events/'.$get('event_id').'/contribution-templates' : 'contribution-campaigns/templates')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->rules([new AllowedCardTemplateDimensions()])
                        ->maxSize(5120)->required()
                        ->imagePreviewHeight('360')->columnSpanFull()
                        ->helperText('JPG, PNG or WEBP, maximum 5 MB. Preview the upload and position the member name in the visual designer after saving.'),
                ])->columns(2),

            Forms\Components\Section::make('Member Name Placement')
                ->description('Fine-tune these settings here, or use the visual designer after creating the campaign.')
                ->schema([
                    Forms\Components\TextInput::make('name_x_percent')->label('Left')->numeric()->minValue(0)->maxValue(99)->default(10)->suffix('%')->required(),
                    Forms\Components\TextInput::make('name_y_percent')->label('Top')->numeric()->minValue(0)->maxValue(99)->default(70)->suffix('%')->required(),
                    Forms\Components\TextInput::make('name_width_percent')->label('Text Width')->numeric()->minValue(1)->maxValue(100)->default(80)->suffix('%')->required(),
                    Forms\Components\TextInput::make('name_font_size')->label('Font Size')->numeric()->minValue(8)->maxValue(300)->default(42)->suffix('px')->required(),
                    Forms\Components\ColorPicker::make('name_font_color')->label('Font Color')->default('#111827')->required(),
                    Forms\Components\Select::make('name_font_family')->options(CardTemplatePlaceholder::fontFamilyOptions())->default(CardTemplatePlaceholder::FONT_MONTSERRAT)->required()->native(false),
                    Forms\Components\Select::make('name_font_weight')->options(CardTemplatePlaceholder::fontWeightOptions())->default('bold')->required()->native(false),
                    Forms\Components\Select::make('name_text_align')->options(CardTemplatePlaceholder::textAlignOptions())->default('center')->required()->native(false),
                ])->columns(4)->collapsible(),

            Forms\Components\Section::make('WhatsApp Delivery')
                ->description('Use an approved Meta WhatsApp image-header template. Sending is configured after members are imported.')
                ->schema([
                    Forms\Components\TextInput::make('whatsapp_template_name')
                        ->default('contribution_card_sw')->required()->maxLength(255)
                        ->helperText('Default: contribution_card_sw'),
                    Forms\Components\Select::make('whatsapp_language_code')
                        ->options(['sw' => 'Swahili', 'en' => 'English'])
                        ->default('sw')->required()->native(false),
                ])->columns(2)->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('template_image')->label('Template')->disk('public')->height(70),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('event.title')->label('Event')->placeholder('Standalone')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('recipients_count')->label('Members')->badge(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'active' => 'success', 'paused' => 'warning', 'completed' => 'info', 'draft' => 'gray', default => 'gray',
                }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Event')
                    ->options(fn (): array => static::eventOptions())
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->options(ContributionCampaign::statuses()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [RecipientsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContributionCampaigns::route('/'),
            'create' => Pages\CreateContributionCampaign::route('/create'),
            'edit' => Pages\EditContributionCampaign::route('/{record}/edit'),
            'designer' => Pages\ContributionCampaignDesigner::route('/{record}/designer'),
        ];
    }
}
