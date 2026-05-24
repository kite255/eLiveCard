<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventResource\Pages;
use App\Models\Event;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

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
                            ->options([
                                'wedding' => 'Wedding',
                                'send_off' => 'Send-off',
                                'kitchen_party' => 'Kitchen Party',
                                'engagement' => 'Engagement',
                                'birthday' => 'Birthday',
                                'graduation' => 'Graduation',
                                'anniversary' => 'Anniversary',
                                'baby_shower' => 'Baby Shower',
                                'religious_celebration' => 'Religious Celebration',
                                'family_event' => 'Private Family Event',
                                'other' => 'Other',
                            ])
                            ->searchable(),

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
                    ->description('Add venue, map link, dress code, program, and organizer contact details.')
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

                Forms\Components\Section::make('Reminder SMS Settings')
                    ->description('Choose whether this event should send reminder SMS automatically or manually.')
                    ->schema([
                        Forms\Components\Toggle::make('auto_sms_reminders_enabled')
                            ->label('Enable Automatic Reminder SMS')
                            ->helperText('If disabled, reminders can still be sent manually from Communication or Invitees.')
                            ->default(false)
                            ->live(),

                        Forms\Components\Toggle::make('auto_rsvp_pending_reminder_enabled')
                            ->label('Auto-send RSVP Pending Reminder')
                            ->helperText('Sends to invitees who have not confirmed attendance.')
                            ->default(true)
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('auto_sms_reminders_enabled')),

                        Forms\Components\Toggle::make('auto_one_day_reminder_enabled')
                            ->label('Auto-send One Day Before Reminder')
                            ->helperText('Sends to invitees marked as attending one day before the event.')
                            ->default(true)
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('auto_sms_reminders_enabled')),

                        Forms\Components\Toggle::make('auto_event_day_reminder_enabled')
                            ->label('Auto-send Event Day Reminder')
                            ->helperText('Sends the final reminder on the event day.')
                            ->default(true)
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('auto_sms_reminders_enabled')),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('SMS Summary')
                    ->description('Quick SMS communication performance for this event.')
                    ->schema([
                        Forms\Components\TextInput::make('sms_sent_count')
                            ->label('Total SMS Sent')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),

                        Forms\Components\TextInput::make('sms_failed_count')
                            ->label('Failed SMS')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),

                        Forms\Components\TextInput::make('invitation_sms_sent_count')
                            ->label('Invitation SMS Sent')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),

                        Forms\Components\TextInput::make('reminder_sms_sent_count')
                            ->label('Reminder SMS Sent')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),

                        Forms\Components\TextInput::make('final_sms_sent_count')
                            ->label('Event Day SMS Sent')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                    ])
                    ->columns(3)
                    ->collapsed()
                    ->visibleOn('edit'),
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

                Tables\Columns\TextColumn::make('event_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? ucwords(str_replace('_', ' ', $state))
                        : 'Not set')
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('event_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Start')
                    ->time('H:i')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('end_time')
                    ->label('End')
                    ->time('H:i')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('venue_name')
                    ->label('Venue')
                    ->searchable()
                    ->placeholder('Venue not set'),

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

                Tables\Columns\IconColumn::make('auto_sms_reminders_enabled')
                    ->label('Auto SMS')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('auto_rsvp_pending_reminder_enabled')
                    ->label('Auto RSVP')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('auto_one_day_reminder_enabled')
                    ->label('Auto 1 Day')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('auto_event_day_reminder_enabled')
                    ->label('Auto Event Day')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('sms_sent_count')
                    ->label('SMS Sent')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sms_failed_count')
                    ->label('SMS Failed')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('dress_code')
                    ->label('Dress Code')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('contact_person_name')
                    ->label('Contact Person')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('contact_person_phone')
                    ->label('Contact Phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(Event::statuses()),

                Tables\Filters\SelectFilter::make('event_type')
                    ->label('Event Type')
                    ->options([
                        'wedding' => 'Wedding',
                        'send_off' => 'Send-off',
                        'kitchen_party' => 'Kitchen Party',
                        'engagement' => 'Engagement',
                        'birthday' => 'Birthday',
                        'graduation' => 'Graduation',
                        'anniversary' => 'Anniversary',
                        'baby_shower' => 'Baby Shower',
                        'religious_celebration' => 'Religious Celebration',
                        'family_event' => 'Private Family Event',
                        'other' => 'Other',
                    ]),

                Tables\Filters\TernaryFilter::make('auto_sms_reminders_enabled')
                    ->label('Automatic SMS Reminders'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edit'),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
        ];
    }
}