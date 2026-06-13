<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\MessageTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MessageTemplatesRelationManager extends RelationManager
{
    protected static string $relationship = 'messageTemplates';

    protected static ?string $title = 'Message Templates';

    protected static ?string $modelLabel = 'Message Template';

    protected static ?string $pluralModelLabel = 'Message Templates';

    private const PLACEHOLDERS = [
        '{name}',
        '{phone}',
        '{event_name}',
        '{event_date}',
        '{event_time}',
        '{venue}',
        '{venue_address}',
        '{location_link}',
        '{dress_code}',
        '{card_type}',
        '{allowed_guests}',
        '{guest_count}',
        '{table_number}',
        '{category}',
        '{serial_number}',
        '{private_link}',
        '{private_invitation_url}',
        '{rsvp_url}',
    ];

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Template Details')
                    ->description('Create an SMS or WhatsApp template for this event.')
                    ->schema([
                        Forms\Components\Select::make('channel')
                            ->label('Channel')
                            ->options([
                                'sms' => 'SMS',
                                'whatsapp' => 'WhatsApp',
                            ])
                            ->default('sms')
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('type')
                            ->label('Message Type')
                            ->options([
                                'invitation' => 'Invitation',
                                'rsvp_pending_reminder' => 'RSVP Pending Reminder',
                                'attending_reminder' => 'Attending Reminder',
                                'event_day_reminder' => 'Event Day Reminder',
                                'welcome_checkin' => 'Welcome After Check-in',
                                'thank_you' => 'Thank You',
                                'custom' => 'Custom Message',
                            ])
                            ->default('invitation')
                            ->required(),

                        Forms\Components\TextInput::make('name')
                            ->label('Template Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Example: WhatsApp Invitation'),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                            ])
                            ->default('active')
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Message')
                    ->description('Write any message and insert placeholders where needed.')
                    ->schema([
                        Forms\Components\Textarea::make('content')
                            ->label('Message Content')
                            ->rows(8)
                            ->required()
                            ->columnSpanFull()
                            ->placeholder(
                                'Hello {name}, you are invited to {event_name}.' . PHP_EOL .
                                'Date: {event_date}' . PHP_EOL .
                                'Time: {event_time}' . PHP_EOL .
                                'Venue: {venue}'
                            ),

                        Forms\Components\Placeholder::make('available_placeholders')
                            ->label('Available Placeholders')
                            ->content(implode(', ', self::PLACEHOLDERS))
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('WhatsApp / Message Market')
                    ->description('The provider template name must match the approved template in Message Market or Meta.')
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp_template_name')
                            ->label('Provider Template Name')
                            ->placeholder('Example: elive_event_invitation')
                            ->required(fn (Forms\Get $get): bool => $get('channel') === 'whatsapp')
                            ->maxLength(255),

                        Forms\Components\KeyValue::make('whatsapp_buttons')
                            ->label('WhatsApp Buttons')
                            ->keyLabel('Button Text')
                            ->valueLabel('Action or URL Placeholder')
                            ->addActionLabel('Add Button')
                            ->reorderable()
                            ->helperText(
                                'Recommended: View Invitation = {private_invitation_url}; ' .
                                'Open Location = {location_link}; Attending = attend; Not Attending = not_attend.'
                            )
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->visible(fn (Forms\Get $get): bool => $get('channel') === 'whatsapp'),

                Forms\Components\Section::make('Preview Guide')
                    ->schema([
                        Forms\Components\Placeholder::make('sample_preview')
                            ->label('Example')
                            ->content(
                                "Hello Guest 1, you are invited to Nancy SendOff.\n\n" .
                                "Date: 17 May 2026\n" .
                                "Time: 18:00\n" .
                                "Venue: Victoria Place\n\n" .
                                "[View Invitation]   [Open Location]"
                            ),
                    ])
                    ->visible(fn (Forms\Get $get): bool => $get('channel') === 'whatsapp')
                    ->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->emptyStateHeading('No message templates yet')
            ->emptyStateDescription('Create SMS and WhatsApp templates for this event.')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Template')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('channel')
                    ->label('Channel')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'sms' => 'SMS',
                        'whatsapp' => 'WhatsApp',
                        default => ucfirst((string) $state),
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'sms' => 'warning',
                        'whatsapp' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'invitation' => 'Invitation',
                        'rsvp_pending_reminder' => 'RSVP Pending',
                        'attending_reminder' => 'Attending Reminder',
                        'event_day_reminder' => 'Event Day',
                        'welcome_checkin' => 'Welcome Check-in',
                        'thank_you' => 'Thank You',
                        'custom' => 'Custom',
                        default => ucfirst(str_replace('_', ' ', (string) $state)),
                    })
                    ->color('gray'),

                Tables\Columns\TextColumn::make('content')
                    ->label('Message')
                    ->limit(70)
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('whatsapp_template_name')
                    ->label('Provider Template')
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ucfirst($state ?: 'active'))
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Template')
                    ->icon('heroicon-o-plus')
                    ->mutateFormDataUsing(function (array $data): array {
                        if (($data['channel'] ?? null) !== 'whatsapp') {
                            $data['whatsapp_template_name'] = null;
                            $data['whatsapp_buttons'] = null;
                        }

                        return $data;
                    }),

                Tables\Actions\Action::make('create_default_templates')
                    ->label('Create Defaults')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('This creates practical SMS and WhatsApp templates for the current event.')
                    ->action(function (): void {
                        $event = $this->getOwnerRecord();

                        $defaults = [
                            [
                                'channel' => 'sms',
                                'type' => 'invitation',
                                'name' => 'SMS Invitation',
                                'content' => 'Hello {name}, you are invited to {event_name} on {event_date} at {event_time}, venue {venue}. View your invitation: {private_invitation_url}',
                            ],
                            [
                                'channel' => 'sms',
                                'type' => 'welcome_checkin',
                                'name' => 'Welcome SMS After Check-in',
                                'content' => 'Welcome {name} to {event_name}. We are happy to have you with us. Enjoy the event.',
                            ],
                            [
                                'channel' => 'whatsapp',
                                'type' => 'invitation',
                                'name' => 'WhatsApp Invitation',
                                'content' => "Hello {name}, you are invited to {event_name}.\n\nDate: {event_date}\nTime: {event_time}\nVenue: {venue}\n\nTap a button below for more details.",
                                'whatsapp_template_name' => config(
                                    'services.whatsapp.invitation_template',
                                    'elive_event_invitation'
                                ),
                                'whatsapp_buttons' => [
                                    'View Invitation' => '{private_invitation_url}',
                                    'Open Location' => '{location_link}',
                                ],
                            ],
                        ];

                        foreach ($defaults as $template) {
                            MessageTemplate::updateOrCreate(
                                [
                                    'event_id' => $event->id,
                                    'channel' => $template['channel'],
                                    'type' => $template['type'],
                                    'name' => $template['name'],
                                ],
                                array_merge($template, [
                                    'event_id' => $event->id,
                                    'status' => 'active',
                                ])
                            );
                        }

                        Notification::make()
                            ->title('Default templates created')
                            ->body('SMS invitation, welcome SMS, and WhatsApp invitation templates are ready.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edit')
                    ->mutateFormDataUsing(function (array $data): array {
                        if (($data['channel'] ?? null) !== 'whatsapp') {
                            $data['whatsapp_template_name'] = null;
                            $data['whatsapp_buttons'] = null;
                        }

                        return $data;
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Delete'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
