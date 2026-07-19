<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventResource\Pages;
use App\Filament\Resources\EventResource\RelationManagers;
use App\Models\Event;
use App\Models\User;
use App\Support\EliveMessagePlaceholders;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

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

    private const RELATION_ASSIGNED_USERS = 0;
    private const RELATION_CARD_TYPES = 1;
    private const RELATION_INVITEES = 2;
    private const RELATION_INVITEE_UPLOADS = 3;
    private const RELATION_CARD_TEMPLATES = 4;
    private const RELATION_GENERATED_CARDS = 5;
    private const RELATION_MESSAGE_TEMPLATES = 6;
    private const RELATION_MESSAGE_LOGS = 7;
    private const RELATION_SMS_LOGS = 8;
    private const RELATION_CHECK_INS = 9;
    private const RELATION_AUDIT_LOGS = 10;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canManageEvents() ?? false;
    }

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
        return auth()->user()?->canManageEvent($record) ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->canAccessEvent($record) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->visibleTo(auth()->user());
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Event Details')
                    ->description('Create and manage the main social event information.')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Event Owner')
                            ->options(fn (): array => User::query()
                                ->whereIn('role', [
                                    User::ROLE_SUPER_ADMIN,
                                    User::ROLE_EVENT_ADMIN,
                                ])
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray())
                            ->searchable()
                            ->preload()
                            ->default(fn (): ?int => auth()->id())
                            ->disabled(fn (): bool => ! (auth()->user()?->isSuperAdmin() ?? false))
                            ->dehydrated()
                            ->required()
                            ->helperText('Super Admin can assign the event owner. Event Admin owns their created events automatically.'),

                        Forms\Components\TextInput::make('title')
                            ->label('Event Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Example: Nancy SendOff'),

                        Forms\Components\Select::make('event_type')
                            ->label('Event Type')
                            ->options(Event::eventTypes())
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\DatePicker::make('event_date')
                            ->label('Event Date')
                            ->required()
                            ->native(false),

                        Forms\Components\TimePicker::make('start_time')
                            ->label('Start Time')
                            ->required()
                            ->seconds(false),

                        Forms\Components\TimePicker::make('end_time')
                            ->label('End Time')
                            ->seconds(false),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(Event::statuses())
                            ->default(Event::STATUS_DRAFT)
                            ->live()
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Public Event Visibility')
                    ->description('Control whether this event is listed on the public Events page.')
                    ->icon('heroicon-o-globe-alt')
                    ->schema([
                        Forms\Components\Toggle::make('is_public')
                            ->label('Show on Public Events Page')
                            ->helperText(function (Forms\Get $get): string {
                                if ($get('status') === Event::STATUS_DRAFT) {
                                    return 'Draft events cannot be published. Change the status before enabling public visibility.';
                                }

                                if ($get('status') === Event::STATUS_CANCELLED) {
                                    return 'Cancelled events cannot be published.';
                                }

                                return 'Enable this only when the event may be visible to everyone.';
                            })
                            ->default(false)
                            ->inline(false)
                            ->live()
                            ->disabled(fn (Forms\Get $get): bool => in_array(
                                $get('status'),
                                [
                                    Event::STATUS_DRAFT,
                                    Event::STATUS_CANCELLED,
                                ],
                                true
                            ))
                            ->dehydrated()
                            ->afterStateHydrated(function (Forms\Components\Toggle $component, $state, Forms\Get $get): void {
                                if (in_array(
                                    $get('status'),
                                    [
                                        Event::STATUS_DRAFT,
                                        Event::STATUS_CANCELLED,
                                    ],
                                    true
                                )) {
                                    $component->state(false);
                                }
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible(),

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

                        Forms\Components\TextInput::make('google_maps_link')
                            ->label('Google Maps Link')
                            ->url()
                            ->maxLength(2048)
                            ->columnSpanFull()
                            ->placeholder('https://maps.app.goo.gl/...')
                            ->helperText('Paste the Google Maps location link for the event venue.'),

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

                Forms\Components\Section::make('Invitee Digital Page')
                    ->description('Customize the public private invitee page opened from SMS or WhatsApp.')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->schema([
                        Forms\Components\FileUpload::make('cover_image')
                            ->label('Cover / Wedding Photo')
                            ->image()
                            ->disk('public')
                            ->directory('events/cover-images')
                            ->visibility('public')
                            ->imageEditor()
                            ->maxSize(4096)
                            ->columnSpanFull()
                            ->helperText('Use a wedding, engagement, send-off, graduation, birthday, or event cover photo.'),

                        Forms\Components\Textarea::make('welcome_message')
                            ->label('Welcome Message')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull()
                            ->placeholder('With joy in our hearts, we warmly invite you to celebrate with us.'),

                        Forms\Components\Textarea::make('love_story')
                            ->label('Love Story / Event Story')
                            ->rows(6)
                            ->columnSpanFull()
                            ->placeholder("Our journey began with friendship, grew with love, and now we are excited to celebrate this special day with you."),

                        Forms\Components\TextInput::make('organizer_phone')
                            ->label('Organizer Phone')
                            ->tel()
                            ->maxLength(30)
                            ->placeholder('255745939140')
                            ->helperText('Used for Call Organizer and WhatsApp Organizer buttons on the invitee page.'),

                        Forms\Components\Toggle::make('show_cover_image')
                            ->label('Show Cover Photo')
                            ->default(true)
                            ->inline(false),

                        Forms\Components\Toggle::make('show_love_story')
                            ->label('Show Love Story')
                            ->default(false)
                            ->inline(false),

                        Forms\Components\Toggle::make('show_program')
                            ->label('Show Program')
                            ->default(true)
                            ->inline(false),

                        Forms\Components\Toggle::make('show_countdown')
                            ->label('Show Countdown')
                            ->default(true)
                            ->inline(false),

                        Forms\Components\Toggle::make('show_wishes')
                            ->label('Show Wishes Form')
                            ->default(true)
                            ->inline(false),

                        Forms\Components\Toggle::make('show_photo_upload')
                            ->label('Show Photo Upload Form')
                            ->helperText('Allows invitees to upload event photos for admin approval before public display.')
                            ->default(true)
                            ->inline(false),

                        Forms\Components\Toggle::make('show_organizer_contact')
                            ->label('Show Organizer Contact')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(2)
                    ->collapsible(),

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
                            ->default('Karibu #NAME# kwenye #EVENT_NAME#. Tunafurahi kuwa nawe. Furahia tukio hili maalum.')
                            ->placeholder('Karibu #NAME# kwenye #EVENT_NAME#. Tunafurahi kuwa nawe.')
                            ->helperText(EliveMessagePlaceholders::helperText())
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

                                Infolists\Components\TextEntry::make('user.name')
                                    ->label('Event Owner')
                                    ->badge()
                                    ->color('primary')
                                    ->icon('heroicon-o-user')
                                    ->placeholder('Not assigned'),

                                Infolists\Components\TextEntry::make('event_type_display')
                                    ->label('Event Type')
                                    ->badge()
                                    ->color('primary')
                                    ->icon('heroicon-o-tag')
                                    ->placeholder('Not set'),

                                Infolists\Components\TextEntry::make('display_status')
                                    ->label('Status')
                                    ->state(fn (Event $record): string => $record->display_status)
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string =>
                                        str($state ?: Event::STATUS_DRAFT)->headline()->toString()
                                    )
                                    ->color(fn (?string $state): string => match ($state) {
                                        'active' => 'success',
                                        'upcoming' => 'warning',
                                        'completed' => 'info',
                                        'cancelled' => 'danger',
                                        'draft' => 'gray',
                                        default => 'gray',
                                    })
                                    ->icon(fn (?string $state): string => match ($state) {
                                        'active' => 'heroicon-o-bolt',
                                        'upcoming' => 'heroicon-o-clock',
                                        'completed' => 'heroicon-o-check-circle',
                                        'cancelled' => 'heroicon-o-x-circle',
                                        'draft' => 'heroicon-o-pencil-square',
                                        default => 'heroicon-o-signal',
                                    }),

                                Infolists\Components\TextEntry::make('is_public')
                                    ->label('Public Visibility')
                                    ->formatStateUsing(fn ($state): string => $state ? 'Public' : 'Private')
                                    ->badge()
                                    ->color(fn ($state): string => $state ? 'success' : 'gray')
                                    ->icon(fn ($state): string => $state
                                        ? 'heroicon-o-eye'
                                        : 'heroicon-o-eye-slash'
                                    ),

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

                Infolists\Components\Section::make('Invitee Digital Page')
                    ->description('Public page content shown to invitees through /i/{short_code}.')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->schema([
                        Infolists\Components\Grid::make([
                            'default' => 1,
                            'md' => 3,
                        ])
                            ->schema([
                                Infolists\Components\ImageEntry::make('cover_image')
                                    ->label('Cover Photo')
                                    ->disk('public')
                                    ->height(140)
                                    ->visible(fn ($record): bool => filled($record->cover_image)),

                                Infolists\Components\TextEntry::make('welcome_message')
                                    ->label('Welcome Message')
                                    ->placeholder('Not set')
                                    ->columnSpan([
                                        'default' => 1,
                                        'md' => 2,
                                    ]),

                                Infolists\Components\TextEntry::make('love_story')
                                    ->label('Love Story / Event Story')
                                    ->placeholder('Not set')
                                    ->limit(180)
                                    ->columnSpanFull(),

                                Infolists\Components\TextEntry::make('program')
                                    ->label('Program')
                                    ->placeholder('Not set')
                                    ->limit(180)
                                    ->columnSpanFull(),

                                Infolists\Components\TextEntry::make('organizer_phone')
                                    ->label('Organizer Phone')
                                    ->placeholder('Not set')
                                    ->icon('heroicon-o-phone'),

                                Infolists\Components\TextEntry::make('show_cover_image')
                                    ->label('Cover Photo')
                                    ->formatStateUsing(fn ($state): string => $state ? 'Visible' : 'Hidden')
                                    ->badge()
                                    ->color(fn ($state): string => $state ? 'success' : 'gray'),

                                Infolists\Components\TextEntry::make('show_love_story')
                                    ->label('Love Story')
                                    ->formatStateUsing(fn ($state): string => $state ? 'Visible' : 'Hidden')
                                    ->badge()
                                    ->color(fn ($state): string => $state ? 'success' : 'gray'),

                                Infolists\Components\TextEntry::make('show_program')
                                    ->label('Program')
                                    ->formatStateUsing(fn ($state): string => $state ? 'Visible' : 'Hidden')
                                    ->badge()
                                    ->color(fn ($state): string => $state ? 'success' : 'gray'),

                                Infolists\Components\TextEntry::make('show_countdown')
                                    ->label('Countdown')
                                    ->formatStateUsing(fn ($state): string => $state ? 'Visible' : 'Hidden')
                                    ->badge()
                                    ->color(fn ($state): string => $state ? 'success' : 'gray'),

                                Infolists\Components\TextEntry::make('show_wishes')
                                    ->label('Wishes')
                                    ->formatStateUsing(fn ($state): string => $state ? 'Visible' : 'Hidden')
                                    ->badge()
                                    ->color(fn ($state): string => $state ? 'success' : 'gray'),

                                Infolists\Components\TextEntry::make('show_photo_upload')
                                    ->label('Photo Upload')
                                    ->formatStateUsing(fn ($state): string => $state ? 'Visible' : 'Hidden')
                                    ->badge()
                                    ->color(fn ($state): string => $state ? 'success' : 'gray'),

                                Infolists\Components\TextEntry::make('show_organizer_contact')
                                    ->label('Organizer Contact')
                                    ->formatStateUsing(fn ($state): string => $state ? 'Visible' : 'Hidden')
                                    ->badge()
                                    ->color(fn ($state): string => $state ? 'success' : 'gray'),
                            ]),
                    ])
                    ->collapsed(),

                Infolists\Components\Section::make('Message Center')
                    ->description('SMS, WhatsApp, templates, reminders, and delivery status for this event.')
                    ->icon('heroicon-o-envelope')
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
                                    ->label('Automatic SMS Reminders')
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

                                Infolists\Components\TextEntry::make('whatsapp_status')
                                    ->label('WhatsApp Mode')
                                    ->state(fn (): string =>
                                        config('services.whatsapp.token') || config('services.whatsapp.access_token')
                                            ? 'Cloud API'
                                            : 'Log Mode'
                                    )
                                    ->badge()
                                    ->color(fn (): string =>
                                        config('services.whatsapp.token') || config('services.whatsapp.access_token')
                                            ? 'success'
                                            : 'gray'
                                    )
                                    ->icon('heroicon-o-device-phone-mobile'),

                                Infolists\Components\TextEntry::make('whatsapp_provider')
                                    ->label('Provider')
                                    ->state(fn (): string => config('services.whatsapp.provider') ?: 'Meta WhatsApp Cloud API')
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
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query
                    ->with('user')
                    ->withCount([
                        'invitees',
                        'generatedCards',
                        'checkIns',
                        'invitees as rsvp_attending_count' => fn (Builder $query): Builder =>
                            $query->where('rsvp_status', 'attending'),
                        'invitees as rsvp_pending_count' => fn (Builder $query): Builder =>
                            $query->where(function (Builder $query): void {
                                $query
                                    ->whereNull('rsvp_status')
                                    ->orWhere('rsvp_status', 'pending');
                            }),
                        'generatedCards as generated_cards_ready_count' => fn (Builder $query): Builder =>
                            $query->whereIn('status', ['generated', 'sent']),
                        'inviteeUploads as pending_invitee_uploads_count' => fn (Builder $query): Builder =>
                            $query->where('status', 'pending'),
                        'auditLogs',
                        'messageLogs as sms_sent_count' => fn (Builder $query): Builder =>
                            $query
                                ->where('channel', 'sms')
                                ->whereIn('status', ['sent', 'delivered']),
                        'messageLogs as whatsapp_sent_count' => fn (Builder $query): Builder =>
                            $query
                                ->where('channel', 'whatsapp')
                                ->whereIn('status', ['sent', 'delivered', 'read']),
                    ])
                    ->withSum('invitees as total_allowed_guests', 'allowed_guests')
                    ->withSum('invitees as total_checked_in_guests', 'checked_in_count')
            )
            ->defaultSort('event_date', 'desc')
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('No events created yet')
            ->emptyStateDescription('Create your first social event and begin managing invitees, cards, RSVP, messaging, and check-in.')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Event')
                    ->description(fn (Event $record): string =>
                        collect([
                            $record->venue_name,
                            $record->start_time
                                ? Carbon::parse($record->start_time)->format('h:i A')
                                : null,
                        ])->filter()->implode(' • ')
                    )
                    ->icon('heroicon-o-calendar-days')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Owner')
                    ->icon('heroicon-o-user-circle')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),

                Tables\Columns\TextColumn::make('event_type')
                    ->label('Type')
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            Event::eventTypes()[$state] ?? str($state ?? 'Not set')->headline()->toString()
                    )
                    ->badge()
                    ->color('gray')
                    ->icon('heroicon-o-tag')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('event_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->description(fn (Event $record): ?string =>
                        $record->event_date
                            ? Carbon::parse($record->event_date)->diffForHumans()
                            : null
                    )
                    ->icon('heroicon-o-calendar')
                    ->sortable(),

                Tables\Columns\TextColumn::make('invitees_count')
                    ->label('Invitees')
                    ->numeric()
                    ->alignCenter()
                    ->icon('heroicon-o-users')
                    ->sortable(),

                Tables\Columns\TextColumn::make('rsvp_progress')
                    ->label('RSVP')
                    ->state(function (Event $record): string {
                        $total = max((int) $record->invitees_count, 0);
                        $attending = max((int) $record->rsvp_attending_count, 0);

                        if ($total === 0) {
                            return '0 / 0';
                        }

                        $percentage = (int) round(($attending / $total) * 100);

                        return "{$attending} / {$total} ({$percentage}%)";
                    })
                    ->description(fn (Event $record): string =>
                        ((int) $record->rsvp_pending_count) . ' pending'
                    )
                    ->badge()
                    ->color(function (Event $record): string {
                        $total = (int) $record->invitees_count;
                        $attending = (int) $record->rsvp_attending_count;

                        if ($total === 0) {
                            return 'gray';
                        }

                        $percentage = ($attending / $total) * 100;

                        return match (true) {
                            $percentage >= 75 => 'success',
                            $percentage >= 40 => 'warning',
                            default => 'gray',
                        };
                    })
                    ->icon('heroicon-o-hand-thumb-up')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('card_progress')
                    ->label('Cards')
                    ->state(function (Event $record): string {
                        $total = max((int) $record->invitees_count, 0);
                        $generated = max((int) $record->generated_cards_ready_count, 0);

                        if ($total === 0) {
                            return '0 / 0';
                        }

                        $percentage = (int) round(($generated / $total) * 100);

                        return "{$generated} / {$total} ({$percentage}%)";
                    })
                    ->badge()
                    ->color(function (Event $record): string {
                        $total = (int) $record->invitees_count;
                        $generated = (int) $record->generated_cards_ready_count;

                        if ($total === 0) {
                            return 'gray';
                        }

                        $percentage = ($generated / $total) * 100;

                        return match (true) {
                            $percentage >= 100 => 'success',
                            $percentage >= 50 => 'warning',
                            default => 'gray',
                        };
                    })
                    ->icon('heroicon-o-identification')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('check_in_progress')
                    ->label('Check-in')
                    ->state(function (Event $record): string {
                        $expected = max((int) ($record->total_allowed_guests ?? 0), 0);
                        $admitted = max((int) ($record->total_checked_in_guests ?? 0), 0);

                        if ($expected === 0) {
                            return '0 / 0';
                        }

                        $percentage = min(
                            100,
                            (int) round(($admitted / $expected) * 100)
                        );

                        return "{$admitted} / {$expected} ({$percentage}%)";
                    })
                    ->description(fn (Event $record): string =>
                        number_format((int) $record->check_ins_count).' transactions'
                    )
                    ->badge()
                    ->color(function (Event $record): string {
                        $expected = max((int) ($record->total_allowed_guests ?? 0), 0);
                        $admitted = max((int) ($record->total_checked_in_guests ?? 0), 0);

                        if ($expected === 0 || $admitted === 0) {
                            return 'gray';
                        }

                        return $admitted >= $expected ? 'success' : 'warning';
                    })
                    ->icon('heroicon-o-qr-code')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('message_summary')
                    ->label('Messages')
                    ->state(fn (Event $record): string =>
                        ((int) $record->sms_sent_count) . ' SMS • ' .
                        ((int) $record->whatsapp_sent_count) . ' WhatsApp'
                    )
                    ->icon('heroicon-o-paper-airplane')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('auto_sms_reminders_enabled')
                    ->label('Reminders')
                    ->boolean()
                    ->trueIcon('heroicon-o-bell-alert')
                    ->falseIcon('heroicon-o-bell-slash')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('pending_invitee_uploads_count')
                    ->label('Pending Reviews')
                    ->numeric()
                    ->badge()
                    ->color(fn ($state): string => (int) $state > 0 ? 'warning' : 'gray')
                    ->icon('heroicon-o-photo')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('audit_logs_count')
                    ->label('Activity')
                    ->numeric()
                    ->badge()
                    ->color(fn ($state): string => (int) $state > 0 ? 'info' : 'gray')
                    ->icon('heroicon-o-shield-check')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-eye-slash')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('display_status')
                    ->label('Status')
                    ->state(fn (Event $record): string => $record->display_status)
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string =>
                        str($state ?: Event::STATUS_DRAFT)->headline()->toString()
                    )
                    ->color(fn (?string $state): string => self::statusColor($state))
                    ->icon(fn (?string $state): string => self::statusIcon($state)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Event Status')
                    ->options(Event::statuses())
                    ->multiple()
                    ->preload(),

                Tables\Filters\SelectFilter::make('event_type')
                    ->label('Event Type')
                    ->options(Event::eventTypes())
                    ->multiple()
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('upcoming')
                    ->label('Upcoming Events')
                    ->query(
                        fn (Builder $query): Builder =>
                            $query->whereDate('event_date', '>=', now()->toDateString())
                    )
                    ->toggle(),

                Tables\Filters\Filter::make('past')
                    ->label('Past Events')
                    ->query(
                        fn (Builder $query): Builder =>
                            $query->whereDate('event_date', '<', now()->toDateString())
                    )
                    ->toggle(),

                Tables\Filters\Filter::make('event_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date')
                            ->native(false),

                        Forms\Components\DatePicker::make('until')
                            ->label('To Date')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, $date): Builder =>
                                    $query->whereDate('event_date', '>=', $date)
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, $date): Builder =>
                                    $query->whereDate('event_date', '<=', $date)
                            );
                    }),

                Tables\Filters\TernaryFilter::make('is_public')
                    ->label('Public Visibility')
                    ->trueLabel('Public Events')
                    ->falseLabel('Private Events')
                    ->placeholder('All Events'),

                Tables\Filters\TernaryFilter::make('auto_sms_reminders_enabled')
                    ->label('Automatic SMS Reminders'),

                Tables\Filters\TernaryFilter::make('welcome_sms_enabled')
                    ->label('Welcome SMS'),
            ])
            ->filtersFormColumns(2)
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Open Workspace')
                    ->icon('heroicon-o-folder-open')
                    ->color('primary'),

                Tables\Actions\Action::make('manage_invitees')
                    ->label('Manage Invitees')
                    ->icon('heroicon-o-users')
                    ->color('gray')
                    ->url(fn (Event $record): string => static::getUrl('view', [
                        'record' => $record,
                        'activeRelationManager' => self::RELATION_INVITEES,
                    ])),

                Tables\Actions\Action::make('wishes_photos')
                    ->label('Wishes & Photos')
                    ->icon('heroicon-o-photo')
                    ->color('info')
                    ->badge(fn (Event $record): ?string =>
                        (int) $record->pending_invitee_uploads_count > 0
                            ? (string) $record->pending_invitee_uploads_count
                            : null
                    )
                    ->url(fn (Event $record): string => static::getUrl('view', [
                        'record' => $record,
                        'activeRelationManager' => self::RELATION_INVITEE_UPLOADS,
                    ])),

                Tables\Actions\Action::make('activity_log')
                    ->label('Activity Log')
                    ->icon('heroicon-o-shield-check')
                    ->color('gray')
                    ->badge(fn (Event $record): ?string =>
                        (int) $record->audit_logs_count > 0
                            ? (string) $record->audit_logs_count
                            : null
                    )
                    ->visible(fn (Event $record): bool =>
                        auth()->user()?->canAccessEvent($record) ?? false
                    )
                    ->url(fn (Event $record): string => static::getUrl('view', [
                        'record' => $record,
                        'activeRelationManager' => self::RELATION_AUDIT_LOGS,
                    ])),

                Tables\Actions\Action::make('message_center')
                    ->label('Message Center')
                    ->icon('heroicon-o-envelope')
                    ->color('warning')
                    ->visible(fn (Event $record): bool =>
                        (auth()->user()?->canSendMessages() ?? false)
                        && (auth()->user()?->canManageEvent($record) ?? false)
                    )
                    ->url(fn (Event $record): string => static::getUrl('send-message', [
                        'record' => $record,
                    ])),

                Tables\Actions\Action::make('invitee_responses')
                    ->label('RSVP Responses')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(fn (Event $record): string => static::getUrl('invitee-responses', [
                        'record' => $record,
                    ])),

                Tables\Actions\Action::make('check_in_dashboard')
                    ->label('Check-in Dashboard')
                    ->icon('heroicon-o-chart-bar-square')
                    ->color('success')
                    ->visible(fn (Event $record): bool =>
                        auth()->user()?->canAccessEvent($record) ?? false
                    )
                    ->url(fn (Event $record): string => static::getUrl(
                        'check-in-dashboard',
                        ['record' => $record]
                    )),

                Tables\Actions\Action::make('gate_check_in')
                    ->label('Gate Check-in')
                    ->icon('heroicon-o-qr-code')
                    ->color('warning')
                    ->visible(fn (Event $record): bool =>
                        method_exists($record, 'canBeCheckedInBy')
                            ? $record->canBeCheckedInBy(auth()->user())
                            : (auth()->user()?->canAccessEvent($record) ?? false)
                    )
                    ->url(fn (Event $record): string => route('gate.check-in.entry', [
                        'event' => $record->getKey(),
                    ]))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('reports')
                    ->label('Reports')
                    ->icon('heroicon-o-chart-bar-square')
                    ->color('gray')
                    ->visible(fn (Event $record): bool =>
                        auth()->user()?->canAccessEvent($record) ?? false
                    )
                    ->url(fn (Event $record): string => url(
                        '/admin/reports?event_id='.$record->getKey()
                    ))
                    ->openUrlInNewTab(),

                Tables\Actions\EditAction::make()
                    ->label('Edit Event')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (Event $record): bool =>
                        auth()->user()?->canManageEvent($record) ?? false
                    ),

                Tables\Actions\DeleteAction::make()
                    ->label('Delete Event')
                    ->icon('heroicon-o-trash')
                    ->visible(fn (): bool =>
                        auth()->user()?->isSuperAdmin() ?? false
                    ),
            ])
            ->actionsPosition(Tables\Enums\ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool =>
                            auth()->user()?->isSuperAdmin() ?? false
                        ),
                ]),
            ]);
    }

    private static function statusLabel(?string $state): string
    {
        return Event::statuses()[$state]
            ?? str($state ?? Event::STATUS_DRAFT)->headline()->toString();
    }

    private static function statusColor(?string $state): string
    {
        return match ($state) {
            'active' => 'success',
            'upcoming' => 'warning',
            'completed' => 'info',
            'cancelled' => 'danger',
            'draft' => 'gray',
            default => 'gray',
        };
    }

    private static function statusIcon(?string $state): string
    {
        return match ($state) {
            'active' => 'heroicon-o-bolt',
            'upcoming' => 'heroicon-o-clock',
            'completed' => 'heroicon-o-check-circle',
            'cancelled' => 'heroicon-o-x-circle',
            'draft' => 'heroicon-o-pencil-square',
            default => 'heroicon-o-signal',
        };
    }

    public static function getRelations(): array
    {
        // Keep this order synchronized with ViewEvent relation index constants.
        return [
            RelationManagers\AssignedUsersRelationManager::class,     // 0
            RelationManagers\CardTypesRelationManager::class,         // 1
            RelationManagers\InviteesRelationManager::class,          // 2
            RelationManagers\InviteeUploadsRelationManager::class,    // 3
            RelationManagers\CardTemplatesRelationManager::class,     // 4
            RelationManagers\GeneratedCardsRelationManager::class,    // 5
            RelationManagers\MessageTemplatesRelationManager::class,  // 6
            RelationManagers\MessageLogsRelationManager::class,       // 7
            RelationManagers\SmsLogsRelationManager::class,           // 8
            RelationManagers\CheckInsRelationManager::class,          // 9
            RelationManagers\AuditLogsRelationManager::class,         // 10
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'view' => Pages\ViewEvent::route('/{record}'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
            'send-message' => Pages\SendEventMessage::route('/{record}/send-message'),
            'invitee-responses' => Pages\InviteeResponseTracker::route('/{record}/invitee-responses'),
            'check-in-dashboard' => Pages\CheckInDashboard::route('/{record}/check-in-dashboard'),
        ];
    }
}