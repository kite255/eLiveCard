<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MessageTemplateResource\Pages;
use App\Models\Event;
use App\Models\MessageTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MessageTemplateResource extends Resource
{
    protected static ?string $model = MessageTemplate::class;

    /**
     * Message templates are mainly managed from the Event workspace,
     * so this resource stays hidden from the sidebar.
     */
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Message Templates';

    protected static ?string $modelLabel = 'Message Template';

    protected static ?string $pluralModelLabel = 'Message Templates';

    protected static ?int $navigationSort = 1;

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
        return auth()->user()?->canSendMessages() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->canSendMessages() ?? false;
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
        return auth()->user()?->canSendMessages() ?? false;
    }

    protected static function canAccessRecord(?MessageTemplate $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
            return false;
        }

        if (! $user->canSendMessages()) {
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

        if (! $user || ! $user->canSendMessages()) {
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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Template Details')
                    ->description('Create reusable SMS or WhatsApp messages for an event.')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->label('Event')
                            ->options(fn (): array => static::visibleEventOptions())
                            ->searchable()
                            ->preload()
                            ->default(fn (): ?int => static::defaultEventId())
                            ->disabled(fn (): bool => auth()->user()?->isEventAdmin() ?? false)
                            ->dehydrated()
                            ->required()
                            ->helperText('Super Admin can choose any event. Event Admin can only use their own events.'),

                        Forms\Components\TextInput::make('name')
                            ->label('Template Name')
                            ->placeholder('Example: Wedding Invitation')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('channel')
                            ->label('Message Channel')
                            ->options([
                                'sms' => 'SMS',
                                'whatsapp' => 'WhatsApp',
                            ])
                            ->default('sms')
                            ->required()
                            ->native(false)
                            ->live(),

                        Forms\Components\Select::make('type')
                            ->label('Message Type')
                            ->options([
                                'invitation' => 'Invitation',
                                'rsvp_pending_reminder' => 'RSVP Pending Reminder',
                                'attending_reminder' => 'One Day Before Reminder',
                                'event_day_reminder' => 'Event Day Reminder',
                                'welcome_checkin' => 'Welcome After Check-in',
                                'thank_you' => 'Thank You',
                                'custom' => 'Custom',
                            ])
                            ->default('invitation')
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'draft' => 'Draft',
                            ])
                            ->default('active')
                            ->required()
                            ->native(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Message Content')
                    ->description('Use hashtag placeholders to keep messages consistent with eLive Card.')
                    ->schema([
                        Forms\Components\Textarea::make('content')
                            ->label('Message')
                            ->placeholder('Habari #NAME#, umealikwa kwenye #EVENT_NAME#. Fungua kadi yako hapa: #INVITATION_LINK#')
                            ->required()
                            ->rows(8)
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('available_placeholders')
                            ->label('Available placeholders')
                            ->content('#NAME#, #PHONE#, #EVENT_NAME#, #EVENT_DATE#, #EVENT_TIME#, #EVENT_VENUE#, #VENUE_ADDRESS#, #LOCATION_LINK#, #DRESS_CODE#, #CARD_TYPE#, #ALLOWED_GUESTS#, #GUEST_COUNT#, #TABLE_NUMBER#, #CATEGORY#, #SERIAL_NUMBER#, #INVITATION_LINK#, #PRIVATE_INVITATION_URL#, #RSVP_LINK#')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('WhatsApp Configuration')
                    ->description('These settings are only required when the message channel is WhatsApp.')
                    ->visible(fn (Forms\Get $get): bool => $get('channel') === 'whatsapp')
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp_template_name')
                            ->label('Approved WhatsApp Template Name')
                            ->placeholder('event_invitation_en')
                            ->helperText('Enter the exact template name approved in Meta WhatsApp Manager.')
                            ->maxLength(255),

                        Forms\Components\Select::make('whatsapp_language_code')
                            ->label('WhatsApp Language')
                            ->options([
                                'en' => 'English',
                                'sw' => 'Swahili',
                            ])
                            ->default('en')
                            ->native(false),

                        Forms\Components\KeyValue::make('whatsapp_buttons')
                            ->label('WhatsApp Buttons')
                            ->keyLabel('Button Text')
                            ->valueLabel('Action / Payload / URL')
                            ->helperText('Admin notes only. Actual WhatsApp button payloads are sent from the WhatsApp API service.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),
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
                    ->label('Template')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('channel')
                    ->label('Channel')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'whatsapp' => 'WhatsApp',
                        'sms' => 'SMS',
                        default => ucfirst((string) $state),
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'whatsapp' => 'success',
                        'sms' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => str($state ?? 'custom')->replace('_', ' ')->title()->toString())
                    ->sortable(),

                Tables\Columns\TextColumn::make('content')
                    ->label('Message')
                    ->limit(70)
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('whatsapp_template_name')
                    ->label('WhatsApp Template')
                    ->placeholder('Not configured')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('whatsapp_language_code')
                    ->label('WA Lang')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'sw' => 'Swahili',
                        'en' => 'English',
                        null, '' => '-',
                        default => strtoupper((string) $state),
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'sw' => 'success',
                        'en' => 'info',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ucfirst($state ?? 'inactive'))
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'draft' => 'warning',
                        'inactive' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

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

                Tables\Filters\SelectFilter::make('channel')
                    ->label('Channel')
                    ->options([
                        'sms' => 'SMS',
                        'whatsapp' => 'WhatsApp',
                    ])
                    ->native(false),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Message Type')
                    ->options([
                        'invitation' => 'Invitation',
                        'rsvp_pending_reminder' => 'RSVP Pending Reminder',
                        'attending_reminder' => 'One Day Before Reminder',
                        'event_day_reminder' => 'Event Day Reminder',
                        'welcome_checkin' => 'Welcome After Check-in',
                        'thank_you' => 'Thank You',
                        'custom' => 'Custom',
                    ])
                    ->native(false),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'draft' => 'Draft',
                    ])
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (MessageTemplate $record): bool => static::canAccessRecord($record)),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (MessageTemplate $record): bool => static::canAccessRecord($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->canSendMessages() ?? false),
                ]),
            ])
            ->emptyStateHeading('No message templates yet')
            ->emptyStateDescription('Open an event workspace and create an SMS or WhatsApp template.')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMessageTemplates::route('/'),
            'create' => Pages\CreateMessageTemplate::route('/create'),
            'edit' => Pages\EditMessageTemplate::route('/{record}/edit'),
        ];
    }
}
