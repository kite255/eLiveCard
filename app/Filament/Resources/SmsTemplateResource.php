<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SmsTemplateResource\Pages;
use App\Models\Invitee;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SmsTemplateResource extends Resource
{
    protected static ?string $model = SmsTemplate::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Communication';

    protected static ?string $navigationLabel = 'SMS Templates';

    protected static ?string $modelLabel = 'SMS Template';

    protected static ?string $pluralModelLabel = 'SMS Templates';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Template Details')
                    ->description('Create reusable SMS templates for invitations and reminders.')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->label('Event')
                            ->relationship('event', 'title')
                            ->searchable()
                            ->preload()
                            ->helperText('Leave empty to make this template global for all events.'),

                        Forms\Components\TextInput::make('name')
                            ->label('Template Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Example: RSVP Pending Reminder'),

                        Forms\Components\Select::make('sms_type')
                            ->label('SMS Type')
                            ->options(SmsTemplate::smsTypes())
                            ->required(),

                        Forms\Components\Textarea::make('message')
                            ->label('SMS Message')
                            ->required()
                            ->rows(7)
                            ->live()
                            ->columnSpanFull()
                            ->helperText('Use placeholders such as {name}, {event_name}, {event_date}, {venue}, {serial_number}, {guest_count}, {private_url}, {google_maps_link}.'),

                        Forms\Components\Toggle::make('is_default')
                            ->label('Default Template')
                            ->helperText('Use this as the default template for this SMS type.'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Available Placeholders')
                    ->description('Copy and paste these placeholders into your SMS message.')
                    ->schema([
                        Forms\Components\Placeholder::make('main_placeholders')
                            ->label('Main Invitee Placeholders')
                            ->content(
                                "{name} - Invitee full name\n" .
                                "{phone} - Invitee phone number\n" .
                                "{serial_number} - Invitation serial number\n" .
                                "{short_code} - Private short code\n" .
                                "{private_url} - Private invitee page link\n" .
                                "{rsvp_link} - Private RSVP link\n" .
                                "{qr_code_url} - QR code image URL"
                            ),

                        Forms\Components\Placeholder::make('event_placeholders')
                            ->label('Event Placeholders')
                            ->content(
                                "{event_name} - Event title/name\n" .
                                "{event_type} - Event type\n" .
                                "{event_date} - Event date\n" .
                                "{event_time} - Event start time\n" .
                                "{event_end_time} - Event end time\n" .
                                "{venue} - Venue name or address\n" .
                                "{venue_name} - Venue name\n" .
                                "{venue_address} - Venue address\n" .
                                "{google_maps_link} - Google Maps link\n" .
                                "{dress_code} - Dress code\n" .
                                "{program} - Event program"
                            ),

                        Forms\Components\Placeholder::make('card_placeholders')
                            ->label('Card and Seating Placeholders')
                            ->content(
                                "{card_type} - Invitee card type\n" .
                                "{guest_count} - Allowed guest count\n" .
                                "{allowed_guests} - Allowed guest count\n" .
                                "{category} - Invitee category\n" .
                                "{table_number} - Table number"
                            ),

                        Forms\Components\Placeholder::make('contact_placeholders')
                            ->label('Contact Placeholders')
                            ->content(
                                "{contact_person_name} - Organizer/contact person name\n" .
                                "{contact_person_phone} - Organizer/contact person phone"
                            ),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Message Preview')
                    ->description('Preview this SMS template using a real invitee.')
                    ->schema([
                        Forms\Components\Select::make('preview_invitee_id')
                            ->label('Preview Invitee')
                            ->options(fn () => Invitee::query()
                                ->with('event')
                                ->latest()
                                ->limit(100)
                                ->get()
                                ->mapWithKeys(fn (Invitee $invitee) => [
                                    $invitee->id => $invitee->name . ' - ' . ($invitee->event?->title ?? 'No Event'),
                                ]))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->dehydrated(false),

                        Forms\Components\Placeholder::make('preview_message')
                            ->label('Preview Message')
                            ->content(function (Forms\Get $get): string {
                                $message = (string) ($get('message') ?? '');
                                $inviteeId = $get('preview_invitee_id');

                                if (blank($message)) {
                                    return 'Write an SMS message first.';
                                }

                                if (blank($inviteeId)) {
                                    return 'Select an invitee to preview the message.';
                                }

                                $invitee = Invitee::query()
                                    ->with(['event', 'cardType'])
                                    ->find($inviteeId);

                                if (! $invitee) {
                                    return 'Preview invitee not found.';
                                }

                                return self::replacePreviewPlaceholders($message, $invitee);
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Template')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event')
                    ->placeholder('Global')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sms_type')
                    ->label('SMS Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        SmsLog::TYPE_INVITATION => 'Invitation',
                        SmsLog::TYPE_RSVP_PENDING_REMINDER => 'RSVP Reminder',
                        SmsLog::TYPE_ATTENDING_REMINDER => 'One Day Before',
                        SmsLog::TYPE_EVENT_DAY_REMINDER => 'Event Day',
                        default => $state ? ucfirst(str_replace('_', ' ', $state)) : 'Unknown',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        SmsLog::TYPE_INVITATION => 'primary',
                        SmsLog::TYPE_RSVP_PENDING_REMINDER => 'warning',
                        SmsLog::TYPE_ATTENDING_REMINDER => 'info',
                        SmsLog::TYPE_EVENT_DAY_REMINDER => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('message')
                    ->label('Message')
                    ->limit(70)
                    ->tooltip(fn (SmsTemplate $record): string => $record->message)
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('sms_type')
                    ->label('SMS Type')
                    ->options(SmsTemplate::smsTypes()),

                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Event')
                    ->relationship('event', 'title')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\TernaryFilter::make('is_default')
                    ->label('Default'),
            ])
            ->actions([
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

    protected static function replacePreviewPlaceholders(string $message, Invitee $invitee): string
    {
        $invitee->loadMissing(['event', 'cardType']);

        $event = $invitee->event;

        $eventName = $event?->title ?? 'the event';
        $eventType = $event?->event_type ?? '';

        $eventDate = $event?->event_date
            ? $event->event_date->format('d M Y')
            : '';

        $eventTime = $event?->start_time
            ? $event->start_time->format('H:i')
            : '';

        $eventEndTime = $event?->end_time
            ? $event->end_time->format('H:i')
            : '';

        $venueName = $event?->venue_name ?? '';
        $venueAddress = $event?->venue_address ?? '';
        $venue = $venueName ?: $venueAddress ?: 'the venue';

        $googleMapsLink = $event?->google_maps_link ?? '';
        $dressCode = $event?->dress_code ?? '';
        $program = $event?->program ?? '';

        $contactPersonName = $event?->contact_person_name ?? '';
        $contactPersonPhone = $event?->contact_person_phone ?? '';

        $cardType = $invitee->cardType?->name ?? '';

        $guestCount = $invitee->final_allowed_guests
            ?? $invitee->allowed_guests
            ?? 1;

        $allowedGuests = $guestCount;

        $category = $invitee->category ?? '';
        $tableNumber = $invitee->table_number ?? '';
        $serialNumber = $invitee->serial_number ?? '';
        $shortCode = $invitee->short_code ?? '';

        $privateUrl = $invitee->private_invitation_url ?? '';
        $rsvpLink = $privateUrl;
        $qrCodeUrl = $invitee->qr_code_url ?? '';

        $message = str_replace(
            [
                '{name}',
                '{phone}',

                '{event_name}',
                '{event_type}',
                '{event_date}',
                '{event_time}',
                '{event_end_time}',

                '{venue}',
                '{venue_name}',
                '{venue_address}',
                '{google_maps_link}',

                '{dress_code}',
                '{program}',

                '{contact_person_name}',
                '{contact_person_phone}',

                '{card_type}',
                '{guest_count}',
                '{allowed_guests}',

                '{category}',
                '{table_number}',

                '{serial_number}',
                '{short_code}',

                '{private_url}',
                '{rsvp_link}',
                '{qr_code_url}',
            ],
            [
                $invitee->name,
                $invitee->phone,

                $eventName,
                $eventType,
                $eventDate,
                $eventTime,
                $eventEndTime,

                $venue,
                $venueName,
                $venueAddress,
                $googleMapsLink,

                $dressCode,
                $program,

                $contactPersonName,
                $contactPersonPhone,

                $cardType,
                $guestCount,
                $allowedGuests,

                $category,
                $tableNumber,

                $serialNumber,
                $shortCode,

                $privateUrl,
                $rsvpLink,
                $qrCodeUrl,
            ],
            $message
        );

        return trim(preg_replace('/\s+/', ' ', $message));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSmsTemplates::route('/'),
            'create' => Pages\CreateSmsTemplate::route('/create'),
            'edit' => Pages\EditSmsTemplate::route('/{record}/edit'),
        ];
    }
}
