<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventResource\Pages;
use App\Filament\Resources\EventResource\RelationManagers;
use App\Models\Event;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Event Management';

    protected static ?string $navigationLabel = 'Events';

    protected static ?string $modelLabel = 'Event';

    protected static ?string $pluralModelLabel = 'Events';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Event Details')
                    ->description('Create and manage the main social event information.')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Event Owner')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('title')
                            ->label('Event Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Example: Nancy SendOff'),

                        Forms\Components\Select::make('event_type')
                            ->label('Event Type')
                            ->options(Event::eventTypes())
                            ->searchable()
                            ->preload(),

                        Forms\Components\DatePicker::make('event_date')
                            ->label('Event Date')
                            ->native(false),

                        Forms\Components\TimePicker::make('start_time')
                            ->label('Start Time')
                            ->seconds(false),

                        Forms\Components\TimePicker::make('end_time')
                            ->label('End Time')
                            ->seconds(false),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(Event::statuses())
                            ->default(Event::STATUS_DRAFT)
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Venue and Contact Details')
                    ->description('Venue, map, dress code, program, and organizer contacts.')
                    ->schema([
                        Forms\Components\TextInput::make('venue_name')
                            ->label('Venue Name')
                            ->maxLength(255)
                            ->placeholder('Example: Victoria Place'),

                        Forms\Components\TextInput::make('dress_code')
                            ->label('Dress Code')
                            ->maxLength(255)
                            ->placeholder('Example: Maroon'),

                        Forms\Components\Textarea::make('venue_address')
                            ->label('Venue Address')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('google_maps_link')
                            ->label('Google Maps Link')
                            ->rows(2)
                            ->columnSpanFull()
                            ->placeholder('https://maps.app.goo.gl/...'),

                        Forms\Components\Textarea::make('program')
                            ->label('Program')
                            ->rows(4)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('contact_person_name')
                            ->label('Contact Person Name')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('contact_person_phone')
                            ->label('Contact Person Phone')
                            ->tel()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Welcome SMS Settings')
                    ->description('Send an automatic welcome SMS after a successful gate check-in.')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->schema([
                        Forms\Components\Toggle::make('welcome_sms_enabled')
                            ->label('Enable Welcome SMS After Check-in')
                            ->helperText('When enabled, one welcome SMS is queued after a successful check-in. SMS failure will not reverse the check-in.')
                            ->default(false)
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('welcome_sms_message')
                            ->label('Welcome SMS Message')
                            ->default('Welcome {name} to {event_name}. We are happy to have you with us. Enjoy the event.')
                            ->placeholder('Welcome {name} to {event_name}. We are happy to have you with us.')
                            ->helperText('Placeholders: {name}, {phone}, {event_name}, {event_date}, {event_time}, {venue}, {venue_address}, {location_link}, {dress_code}, {card_type}, {allowed_guests}, {table_number}, {category}, {serial_number}, {private_invitation_url}, {rsvp_url}.')
                            ->rows(5)
                            ->maxLength(480)
                            ->required(fn (Forms\Get $get): bool => (bool) $get('welcome_sms_enabled'))
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('welcome_sms_enabled'))
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make('Reminder SMS Settings')
                    ->description('Enable reminders and choose the sending time for this event.')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        Forms\Components\Toggle::make('auto_sms_reminders_enabled')
                            ->label('Enable Automatic Reminder SMS')
                            ->helperText('Laravel checks every minute and sends reminders when the selected event time is reached.')
                            ->default(false)
                            ->live()
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('auto_rsvp_pending_reminder_enabled')
                            ->label('Auto-send RSVP Pending Reminder')
                            ->default(true)
                            ->live()
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('auto_sms_reminders_enabled')),

                        Forms\Components\TimePicker::make('rsvp_pending_reminder_time')
                            ->label('RSVP Pending Reminder Time')
                            ->default('09:00')
                            ->seconds(false)
                            ->native(false)
                            ->required(fn (Forms\Get $get): bool =>
                                (bool) $get('auto_sms_reminders_enabled')
                                && (bool) $get('auto_rsvp_pending_reminder_enabled')
                            )
                            ->visible(fn (Forms\Get $get): bool =>
                                (bool) $get('auto_sms_reminders_enabled')
                                && (bool) $get('auto_rsvp_pending_reminder_enabled')
                            ),

                        Forms\Components\Toggle::make('auto_one_day_reminder_enabled')
                            ->label('Auto-send One Day Before Reminder')
                            ->default(true)
                            ->live()
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('auto_sms_reminders_enabled')),

                        Forms\Components\TimePicker::make('one_day_reminder_time')
                            ->label('One Day Before Reminder Time')
                            ->default('10:00')
                            ->seconds(false)
                            ->native(false)
                            ->required(fn (Forms\Get $get): bool =>
                                (bool) $get('auto_sms_reminders_enabled')
                                && (bool) $get('auto_one_day_reminder_enabled')
                            )
                            ->visible(fn (Forms\Get $get): bool =>
                                (bool) $get('auto_sms_reminders_enabled')
                                && (bool) $get('auto_one_day_reminder_enabled')
                            ),

                        Forms\Components\Toggle::make('auto_event_day_reminder_enabled')
                            ->label('Auto-send Event Day Reminder')
                            ->default(true)
                            ->live()
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('auto_sms_reminders_enabled')),

                        Forms\Components\TimePicker::make('event_day_reminder_time')
                            ->label('Event Day Reminder Time')
                            ->default('06:00')
                            ->seconds(false)
                            ->native(false)
                            ->required(fn (Forms\Get $get): bool =>
                                (bool) $get('auto_sms_reminders_enabled')
                                && (bool) $get('auto_event_day_reminder_enabled')
                            )
                            ->visible(fn (Forms\Get $get): bool =>
                                (bool) $get('auto_sms_reminders_enabled')
                                && (bool) $get('auto_event_day_reminder_enabled')
                            ),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Event Profile')
                    ->description('Main event identity and schedule.')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        Infolists\Components\Grid::make([
                            'default' => 1,
                            'md' => 3,
                        ])
                            ->schema([
                                Infolists\Components\TextEntry::make('title')
                                    ->label('Event Name')
                                    ->weight('bold')
                                    ->size('lg')
                                    ->icon('heroicon-o-sparkles'),

                                Infolists\Components\TextEntry::make('event_type_display')
                                    ->label('Event Type')
                                    ->badge()
                                    ->color('primary')
                                    ->icon('heroicon-o-tag')
                                    ->placeholder('Not set'),

                                Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string => Event::statuses()[$state] ?? ucfirst((string) $state))
                                    ->color(fn (?string $state): string => match ($state) {
                                        Event::STATUS_ACTIVE => 'success',
                                        Event::STATUS_COMPLETED => 'info',
                                        Event::STATUS_CANCELLED => 'danger',
                                        Event::STATUS_DRAFT => 'gray',
                                        default => 'gray',
                                    })
                                    ->icon('heroicon-o-signal'),

                                Infolists\Components\TextEntry::make('event_date_display')
                                    ->label('Event Date')
                                    ->icon('heroicon-o-calendar'),

                                Infolists\Components\TextEntry::make('time_display')
                                    ->label('Event Time')
                                    ->icon('heroicon-o-clock'),

                                Infolists\Components\TextEntry::make('dress_code')
                                    ->label('Dress Code')
                                    ->placeholder('Not set')
                                    ->icon('heroicon-o-swatch'),
                            ]),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('Venue & Organizer')
                    ->description('Location, map, and contact details.')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        Infolists\Components\Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])
                            ->schema([
                                Infolists\Components\TextEntry::make('full_venue_display')
                                    ->label('Venue')
                                    ->icon('heroicon-o-building-office-2')
                                    ->columnSpanFull(),

                                Infolists\Components\TextEntry::make('contact_display')
                                    ->label('Organizer Contact')
                                    ->icon('heroicon-o-phone'),

                                Infolists\Components\TextEntry::make('google_maps_link')
                                    ->label('Google Maps')
                                    ->url(fn ($record) => $record->google_maps_link)
                                    ->openUrlInNewTab()
                                    ->placeholder('Not set')
                                    ->icon('heroicon-o-map'),
                            ]),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('Communication Center')
                    ->description('SMS and WhatsApp configuration/status for this event.')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->schema([
                        Infolists\Components\Grid::make([
                            'default' => 1,
                            'md' => 4,
                        ])
                            ->schema([
                                Infolists\Components\TextEntry::make('sms_sent_count')
                                    ->label('SMS Sent')
                                    ->badge()
                                    ->color('success')
                                    ->icon('heroicon-o-envelope'),

                                Infolists\Components\TextEntry::make('sms_failed_count')
                                    ->label('SMS Failed')
                                    ->badge()
                                    ->color(fn ($state): string => (int) $state > 0 ? 'danger' : 'gray')
                                    ->icon('heroicon-o-exclamation-triangle'),

                                Infolists\Components\TextEntry::make('welcome_sms_enabled')
                                    ->label('Welcome SMS')
                                    ->formatStateUsing(fn ($state): string => $state ? 'Enabled' : 'Disabled')
                                    ->badge()
                                    ->color(fn ($state): string => $state ? 'success' : 'gray')
                                    ->icon('heroicon-o-chat-bubble-left-ellipsis'),

                                Infolists\Components\TextEntry::make('welcome_sms_sent_count')
                                    ->label('Welcome SMS Sent')
                                    ->badge()
                                    ->color('success')
                                    ->icon('heroicon-o-check-circle'),

                                Infolists\Components\TextEntry::make('welcome_sms_failed_count')
                                    ->label('Welcome SMS Failed')
                                    ->badge()
                                    ->color(fn ($state): string => (int) $state > 0 ? 'danger' : 'gray')
                                    ->icon('heroicon-o-x-circle'),

                                Infolists\Components\TextEntry::make('auto_sms_reminders_enabled')
                                    ->label('Automatic Reminders')
                                    ->formatStateUsing(fn ($state): string => $state ? 'Enabled' : 'Disabled')
                                    ->badge()
                                    ->color(fn ($state): string => $state ? 'success' : 'gray')
                                    ->icon('heroicon-o-bell'),

                                Infolists\Components\TextEntry::make('rsvp_pending_reminder_time_display')
                                    ->label('RSVP Reminder Time')
                                    ->icon('heroicon-o-clock')
                                    ->visible(fn ($record): bool =>
                                        (bool) $record->auto_sms_reminders_enabled
                                        && (bool) $record->auto_rsvp_pending_reminder_enabled
                                    ),

                                Infolists\Components\TextEntry::make('one_day_reminder_time_display')
                                    ->label('One Day Reminder Time')
                                    ->icon('heroicon-o-clock')
                                    ->visible(fn ($record): bool =>
                                        (bool) $record->auto_sms_reminders_enabled
                                        && (bool) $record->auto_one_day_reminder_enabled
                                    ),

                                Infolists\Components\TextEntry::make('event_day_reminder_time_display')
                                    ->label('Event Day Reminder Time')
                                    ->icon('heroicon-o-clock')
                                    ->visible(fn ($record): bool =>
                                        (bool) $record->auto_sms_reminders_enabled
                                        && (bool) $record->auto_event_day_reminder_enabled
                                    ),

                                Infolists\Components\TextEntry::make('whatsapp_sent_count')
                                    ->label('WhatsApp Sent')
                                    ->badge()
                                    ->color('success')
                                    ->icon('heroicon-o-paper-airplane'),

                                Infolists\Components\TextEntry::make('whatsapp_failed_count')
                                    ->label('WhatsApp Failed')
                                    ->badge()
                                    ->color(fn ($state): string => (int) $state > 0 ? 'danger' : 'gray')
                                    ->icon('heroicon-o-exclamation-triangle'),

                                Infolists\Components\TextEntry::make('whatsapp_status')
                                    ->label('WhatsApp Mode')
                                    ->state(fn () => config('services.whatsapp.access_token') ? 'Cloud API' : 'Log Mode')
                                    ->badge()
                                    ->color(fn () => config('services.whatsapp.access_token') ? 'success' : 'gray')
                                    ->icon('heroicon-o-device-phone-mobile'),

                                Infolists\Components\TextEntry::make('whatsapp_provider')
                                    ->label('Provider')
                                    ->state(fn () => config('services.whatsapp.provider') ?: 'Not set')
                                    ->badge()
                                    ->color('primary')
                                    ->icon('heroicon-o-cloud'),

                                Infolists\Components\TextEntry::make('whatsapp_template_status')
                                    ->label('Templates')
                                    ->state('Invitation + RSVP')
                                    ->badge()
                                    ->color('success')
                                    ->icon('heroicon-o-rectangle-stack'),
                            ]),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('event_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Event')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Owner')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('event_type_display')
                    ->label('Type')
                    ->badge()
                    ->color('gray')
                    ->searchable(query: fn ($query, string $search) => $query->where('event_type', 'like', "%{$search}%")),

                Tables\Columns\TextColumn::make('event_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Start')
                    ->time('H:i')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('venue_display')
                    ->label('Venue')
                    ->searchable(query: function ($query, string $search) {
                        return $query
                            ->where('venue_name', 'like', "%{$search}%")
                            ->orWhere('venue_address', 'like', "%{$search}%");
                    }),

                Tables\Columns\TextColumn::make('invitees_count')
                    ->label('Invitees')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('generated_cards_count')
                    ->label('Cards')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('rsvp_attending_count')
                    ->label('Attending')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('check_ins_count')
                    ->label('Checked In')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sms_sent_count')
                    ->label('SMS')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('welcome_sms_sent_count')
                    ->label('Welcome SMS')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('welcome_sms_enabled')
                    ->label('Welcome Enabled')
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('whatsapp_sent_count')
                    ->label('WhatsApp')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Event::statuses()[$state] ?? ucfirst((string) $state))
                    ->color(fn (?string $state): string => match ($state) {
                        Event::STATUS_ACTIVE => 'success',
                        Event::STATUS_COMPLETED => 'info',
                        Event::STATUS_CANCELLED => 'danger',
                        Event::STATUS_DRAFT => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(Event::statuses()),

                Tables\Filters\SelectFilter::make('event_type')
                    ->label('Event Type')
                    ->options(Event::eventTypes()),

                Tables\Filters\TernaryFilter::make('auto_sms_reminders_enabled')
                    ->label('Automatic SMS Reminders'),

                Tables\Filters\TernaryFilter::make('welcome_sms_enabled')
                    ->label('Welcome SMS'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Open Workspace')
                    ->icon('heroicon-o-folder-open'),

                Tables\Actions\EditAction::make()
                    ->label('Edit'),

                Tables\Actions\DeleteAction::make()
                    ->label('Delete'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\InviteesRelationManager::class,
            RelationManagers\CardTypesRelationManager::class,
            RelationManagers\CardTemplatesRelationManager::class,
            RelationManagers\GeneratedCardsRelationManager::class,

            RelationManagers\MessageTemplatesRelationManager::class,
            RelationManagers\MessageLogsRelationManager::class,

            RelationManagers\SmsLogsRelationManager::class,
            RelationManagers\CheckInsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'view' => Pages\ViewEvent::route('/{record}'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
        ];
    }
}
