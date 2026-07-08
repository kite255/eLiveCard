<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\MessageTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class MessageTemplatesRelationManager extends RelationManager
{
    protected static string $relationship = 'messageTemplates';

    protected static ?string $title = 'Message Templates';

    protected static ?string $modelLabel = 'Message Template';

    protected static ?string $pluralModelLabel = 'Message Templates';

    public function isReadOnly(): bool
    {
        return false;
    }

    private const TYPE_WELCOME_CHECKIN = 'welcome_checkin';
    private const TYPE_THANK_YOU = 'thank_you';

    private const WHATSAPP_LANGUAGE_CODE = 'en';

    private const PLACEHOLDERS = [
        '#NAME#',
        '#EVENT_NAME#',
        '#EVENT_DATE#',
        '#EVENT_TIME#',
        '#EVENT_VENUE#',
        '#INVITATION_LINK#',
        '#RSVP_LINK#',
        '#SERIAL_NUMBER#',
        '#CARD_TYPE#',
        '#LOCATION_LINK#',
        '#GUEST_COUNT#',
        '#TABLE_NUMBER#',
    ];

    private const PLACEHOLDER_DESCRIPTIONS = [
        '#NAME#' => 'Invitee name',
        '#EVENT_NAME#' => 'Event name',
        '#EVENT_DATE#' => 'Event date',
        '#EVENT_TIME#' => 'Event time',
        '#EVENT_VENUE#' => 'Venue name',
        '#INVITATION_LINK#' => 'Private invitee page / card link',
        '#RSVP_LINK#' => 'RSVP confirmation link',
        '#SERIAL_NUMBER#' => 'Invitee serial number',
        '#CARD_TYPE#' => 'Card type such as Single, Family, VIP',
        '#LOCATION_LINK#' => 'Google Maps / venue link',
        '#GUEST_COUNT#' => 'Allowed guest count',
        '#TABLE_NUMBER#' => 'Assigned table number',
    ];

    private const SAMPLE_VALUES = [
        '#NAME#' => 'Joel Mwasiposya',
        '#PHONE#' => '255768461644',
        '#EVENT_NAME#' => 'Joel Wedding Ceremony',
        '#EVENT_DATE#' => '25/06/2026',
        '#EVENT_TIME#' => '18:00',
        '#EVENT_VENUE#' => 'Victoria Place',
        '#VENUE_ADDRESS#' => 'Dar es Salaam',
        '#LOCATION_LINK#' => 'https://maps.google.com/example',
        '#DRESS_CODE#' => 'Smart Casual',
        '#CARD_TYPE#' => 'VIP',
        '#ALLOWED_GUESTS#' => '2',
        '#GUEST_COUNT#' => '2',
        '#TABLE_NUMBER#' => 'Table 5',
        '#CATEGORY#' => 'Family',
        '#SERIAL_NUMBER#' => 'ELV-2026-ABC123',
        '#INVITATION_LINK#' => 'https://staging-digital.elive.co.tz/i/ABC123',
        '#PRIVATE_INVITATION_URL#' => 'https://staging-digital.elive.co.tz/i/ABC123',
        '#RSVP_LINK#' => 'https://staging-digital.elive.co.tz/i/ABC123',
        '#CARD_LINK#' => 'https://staging-digital.elive.co.tz/storage/events/1/generated-cards/sample.jpg',
    ];

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Template Details')
                    ->description('Choose the channel, template type, status, and name. Then edit the message below.')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->schema([
                        Forms\Components\Select::make('channel')
                            ->label('Channel')
                            ->options(self::channelOptions())
                            ->default(MessageTemplate::CHANNEL_SMS)
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (?string $state, callable $set): void {
                                if ($state !== MessageTemplate::CHANNEL_WHATSAPP) {
                                    $set('whatsapp_template_name', null);
                                    $set('whatsapp_language_code', null);
                                    $set('whatsapp_buttons', null);
                                }
                            })
                            ->helperText('Choose SMS for text messages or WhatsApp for approved Meta templates.'),

                        Forms\Components\Select::make('type')
                            ->label('Template Type')
                            ->options(self::typeOptions())
                            ->placeholder('Select template type')
                            ->required()
                            ->native(false)
                            ->live()
                            ->helperText('This controls which action uses this template, for example Invitation or Thank You.'),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(self::statusOptions())
                            ->default(MessageTemplate::STATUS_ACTIVE)
                            ->required()
                            ->native(false)
                            ->helperText('Only active templates are used automatically.'),

                        Forms\Components\TextInput::make('name')
                            ->label('Template Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Example: SMS Invitation'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Message Content')
                    ->description('Edit the SMS or WhatsApp message text. The preview updates automatically.')
                    ->icon('heroicon-o-pencil-square')
                    ->schema([
                        Forms\Components\Select::make('starter_template')
                            ->label('Optional: Load Sample Wording')
                            ->dehydrated(false)
                            ->native(false)
                            ->options(fn (Forms\Get $get): array => self::starterOptionsFor(
                                $get('type'),
                                $get('channel') ?: MessageTemplate::CHANNEL_SMS,
                            ))
                            ->placeholder('Do not load sample')
                            ->helperText('Use this only when creating a new template or when you want to replace the current wording.')
                            ->live()
                            ->afterStateUpdated(function (?string $state, callable $set): void {
                                if (! $state) {
                                    return;
                                }

                                $starter = self::starterTemplates()[$state] ?? null;

                                if (! $starter) {
                                    return;
                                }

                                $set('type', $starter['type']);
                                $set('channel', $starter['channel']);
                                $set('name', $starter['name']);
                                $set('content', $starter['content']);
                                $set('whatsapp_template_name', $starter['whatsapp_template_name'] ?? null);
                                $set('whatsapp_language_code', $starter['whatsapp_language_code'] ?? self::WHATSAPP_LANGUAGE_CODE);
                                $set('whatsapp_buttons', $starter['whatsapp_buttons'] ?? null);
                            })
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('content')
                            ->label('Message Text')
                            ->rows(10)
                            ->required()
                            ->live(debounce: 500)
                            ->placeholder("Habari #NAME#, umealikwa kwenye #EVENT_NAME#. Fungua kadi yako hapa: #INVITATION_LINK#")
                            ->helperText('Recommended placeholders: #NAME#, #EVENT_NAME#, #EVENT_DATE#, #EVENT_TIME#, #EVENT_VENUE#, #INVITATION_LINK#, #RSVP_LINK#, #SERIAL_NUMBER#.')
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('live_preview')
                            ->label('Preview')
                            ->content(fn (Forms\Get $get): HtmlString => new HtmlString(
                                $this->previewBox((string) ($get('content') ?? ''))
                            ))
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('WhatsApp Settings')
                    ->description('Only needed for WhatsApp templates approved in Meta.')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->visible(fn (Forms\Get $get): bool => $get('channel') === MessageTemplate::CHANNEL_WHATSAPP)
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp_template_name')
                            ->label('Meta Template Name')
                            ->placeholder('Example: event_invitation_en')
                            ->required(fn (Forms\Get $get): bool => $get('channel') === MessageTemplate::CHANNEL_WHATSAPP)
                            ->maxLength(255)
                            ->helperText('Use only the exact approved Meta template name: event_invitation_en, event_ticket_en, or event_invitation_sw.'),

                        Forms\Components\Select::make('whatsapp_language_code')
                            ->label('WhatsApp Language')
                            ->options([
                                'en' => 'English',
                                'sw' => 'Swahili',
                            ])
                            ->default(self::WHATSAPP_LANGUAGE_CODE)
                            ->required(fn (Forms\Get $get): bool => $get('channel') === MessageTemplate::CHANNEL_WHATSAPP)
                            ->native(false)
                            ->helperText('Must match the language selected in Meta. Use English if Meta created the template under English.'),

                        Forms\Components\KeyValue::make('whatsapp_buttons')
                            ->label('Button Notes')
                            ->keyLabel('Button Text')
                            ->valueLabel('Action / URL / Payload')
                            ->addActionLabel('Add Button')
                            ->reorderable()
                            ->helperText('These are notes for admin. The actual RSVP and LOCATION buttons are sent from the WhatsApp Cloud API payload.')
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('whatsapp_warning')
                            ->label('Allowed Meta Templates')
                            ->content(new HtmlString(
                                '<div style="background:#F8FAFC;border-left:4px solid #FD9618;border-radius:12px;padding:12px;color:#111827;line-height:1.6;">Use only: <strong>event_invitation_en</strong>, <strong>event_ticket_en</strong>, or <strong>event_invitation_sw</strong>.</div>'
                            ))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Recommended Placeholders & SMS Examples')
                    ->description('Use only the recommended placeholders below to keep SMS and WhatsApp messages clean.')
                    ->icon('heroicon-o-code-bracket')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\Placeholder::make('placeholders')
                            ->label('')
                            ->content(fn (): HtmlString => new HtmlString($this->placeholdersBox()))
                            ->columnSpanFull(),
                    ]),
            ]);
    }


    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('updated_at', 'desc')
            ->striped()
            ->recordAction('edit')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right')
            ->emptyStateHeading('No message templates yet')
            ->emptyStateDescription('Create default templates first, then customize them for this event.')
            ->emptyStateActions([
                Tables\Actions\Action::make('create_default_templates_empty')
                    ->label('Create Default Templates')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->button()
                    ->requiresConfirmation()
                    ->modalHeading('Create Default Message Templates')
                    ->modalDescription('This will create missing SMS and WhatsApp templates. Existing templates will not be overwritten.')
                    ->action(fn () => $this->createDefaultsAndNotify()),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Template')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (MessageTemplate $record): string => $this->templateDescription($record)),

                Tables\Columns\TextColumn::make('channel')
                    ->label('Channel')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::channelOptions()[$state] ?? ucfirst((string) $state))
                    ->color(fn (?string $state): string => match ($state) {
                        MessageTemplate::CHANNEL_SMS => 'warning',
                        MessageTemplate::CHANNEL_WHATSAPP => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::typeOptions()[$state] ?? ucwords(str_replace('_', ' ', (string) $state)))
                    ->color(fn (?string $state): string => match ($state) {
                        MessageTemplate::TYPE_INVITATION => 'primary',
                        MessageTemplate::TYPE_RSVP_PENDING_REMINDER => 'info',
                        MessageTemplate::TYPE_ATTENDING_REMINDER => 'success',
                        MessageTemplate::TYPE_EVENT_DAY_REMINDER => 'warning',
                        self::TYPE_WELCOME_CHECKIN => 'gray',
                        self::TYPE_THANK_YOU => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('whatsapp_template_name')
                    ->label('Provider Template')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

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
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::statusOptions()[$state] ?? ucfirst((string) $state))
                    ->color(fn (?string $state): string => match ($state) {
                        MessageTemplate::STATUS_ACTIVE => 'success',
                        MessageTemplate::STATUS_INACTIVE => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('content')
                    ->label('Preview')
                    ->formatStateUsing(fn (?string $state): string => Str::limit(
                        str_replace(["\r\n", "\n", "\r"], ' ', (string) $state),
                        100,
                    ))
                    ->tooltip(fn (MessageTemplate $record): ?string => $record->content)
                    ->wrap()
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('channel')
                    ->label('Channel')
                    ->options(self::channelOptions()),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Template Type')
                    ->options(self::typeOptions()),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(self::statusOptions()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Create Template')
                    ->icon('heroicon-o-plus')
                    ->button()
                    ->color('primary')
                    ->modalHeading('Create Message Template')
                    ->modalDescription('Select the template first, then customize the available options.')
                    ->modalWidth('6xl')
                    ->mutateFormDataUsing(fn (array $data): array => $this->mutateTemplateData($data))
                    ->after(fn (MessageTemplate $record): null => $this->afterTemplateSaved($record)),

                Tables\Actions\Action::make('create_default_templates')
                    ->label('Create Defaults')
                    ->icon('heroicon-o-sparkles')
                    ->button()
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Create Default Message Templates')
                    ->modalDescription('This will create missing SMS and WhatsApp templates. Existing templates will not be overwritten.')
                    ->action(fn () => $this->createDefaultsAndNotify()),

                Tables\Actions\Action::make('sync_whatsapp_provider_templates')
                    ->label('Sync WhatsApp Templates')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->button()
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Sync WhatsApp provider template names')
                    ->modalDescription('This creates/updates only the current approved Meta templates: event_invitation_en, event_ticket_en, and event_invitation_sw.')
                    ->action(fn () => $this->syncWhatsAppProviderTemplatesAndNotify()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->button()
                    ->modalHeading(fn (MessageTemplate $record): string => 'Edit Template: ' . $record->name)
                    ->modalDescription('Customize the message wording, placeholders, status, WhatsApp template name, and buttons.')
                    ->modalWidth('6xl')
                    ->mutateFormDataUsing(fn (array $data): array => $this->mutateTemplateData($data))
                    ->after(fn (MessageTemplate $record): null => $this->afterTemplateSaved($record)),

                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (MessageTemplate $record): string => 'Preview: ' . $record->name)
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (MessageTemplate $record): HtmlString => new HtmlString($this->recordPreviewBox($record))),

                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Duplicate this template?')
                    ->modalDescription('This will create a new inactive copy that you can customize safely.')
                    ->action(function (MessageTemplate $record): void {
                        $copy = $record->replicate();
                        $copy->name = 'Copy of ' . $record->name;
                        $copy->status = MessageTemplate::STATUS_INACTIVE;
                        $copy->created_at = now();
                        $copy->updated_at = now();
                        $copy->save();

                        Notification::make()
                            ->title('Template duplicated')
                            ->body('An inactive copy was created. Open it and customize it.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (MessageTemplate $record): bool => $record->status !== MessageTemplate::STATUS_ACTIVE)
                    ->requiresConfirmation()
                    ->modalHeading('Activate template')
                    ->modalDescription('Only one active template is kept for the same event, channel, and type.')
                    ->action(function (MessageTemplate $record): void {
                        $record->update(['status' => MessageTemplate::STATUS_ACTIVE]);
                        $this->deactivateOtherActiveTemplates($record);

                        Notification::make()
                            ->title('Template activated')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (MessageTemplate $record): bool => $record->status === MessageTemplate::STATUS_ACTIVE)
                    ->requiresConfirmation()
                    ->action(function (MessageTemplate $record): void {
                        $record->update(['status' => MessageTemplate::STATUS_INACTIVE]);

                        Notification::make()
                            ->title('Template deactivated')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->label('Delete')
                    ->icon('heroicon-o-trash'),
            ])
            ->actionsColumnLabel('Actions')
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate_selected')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $records->each(function (MessageTemplate $record): void {
                                $record->update(['status' => MessageTemplate::STATUS_ACTIVE]);
                                $this->deactivateOtherActiveTemplates($record);
                            });

                            Notification::make()
                                ->title('Selected templates activated')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('deactivate_selected')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $records->each->update(['status' => MessageTemplate::STATUS_INACTIVE]);

                            Notification::make()
                                ->title('Selected templates deactivated')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function starterTemplates(): array
    {
        return [
            'sms_invitation' => [
                'label' => 'SMS Invitation',
                'channel' => MessageTemplate::CHANNEL_SMS,
                'type' => MessageTemplate::TYPE_INVITATION,
                'name' => 'SMS Invitation',
                'content' => "Habari #NAME#, umealikwa kwenye #EVENT_NAME#.\nTarehe: #EVENT_DATE#\nMuda: #EVENT_TIME#\nUkumbi: #EVENT_VENUE#\nFungua kadi yako hapa: #INVITATION_LINK#",
            ],
            'sms_rsvp_pending' => [
                'label' => 'SMS RSVP Pending Reminder',
                'channel' => MessageTemplate::CHANNEL_SMS,
                'type' => MessageTemplate::TYPE_RSVP_PENDING_REMINDER,
                'name' => 'SMS RSVP Pending Reminder',
                'content' => "Habari #NAME#, tunakukumbusha kuthibitisha ushiriki wako kwenye #EVENT_NAME#.\nTafadhali fungua link hii: #RSVP_LINK#",
            ],
            'sms_attending' => [
                'label' => 'SMS One Day Before Reminder',
                'channel' => MessageTemplate::CHANNEL_SMS,
                'type' => MessageTemplate::TYPE_ATTENDING_REMINDER,
                'name' => 'SMS One Day Before Reminder',
                'content' => "Habari #NAME#, tunakukumbusha kuhusu #EVENT_NAME# tarehe #EVENT_DATE# saa #EVENT_TIME#.\nUkumbi: #EVENT_VENUE#\nKadi yako: #INVITATION_LINK#",
            ],
            'sms_event_day' => [
                'label' => 'SMS Event Day Reminder',
                'channel' => MessageTemplate::CHANNEL_SMS,
                'type' => MessageTemplate::TYPE_EVENT_DAY_REMINDER,
                'name' => 'SMS Event Day Reminder',
                'content' => "Habari #NAME#, leo ni siku ya #EVENT_NAME#.\nTafadhali njoo na kadi yako au serial number: #SERIAL_NUMBER#\nLocation: #LOCATION_LINK#",
            ],
            'sms_welcome_checkin' => [
                'label' => 'SMS Welcome After Check-in',
                'channel' => MessageTemplate::CHANNEL_SMS,
                'type' => self::TYPE_WELCOME_CHECKIN,
                'name' => 'SMS Welcome After Check-in',
                'content' => "Karibu #NAME# kwenye #EVENT_NAME#.\nTunafurahi kuwa nawe. Furahia tukio.",
            ],
            'sms_thank_you' => [
                'label' => 'SMS Thank You Message',
                'channel' => MessageTemplate::CHANNEL_SMS,
                'type' => self::TYPE_THANK_YOU,
                'name' => 'SMS Thank You Message',
                'content' => "Habari #NAME#, asante kwa kuhudhuria #EVENT_NAME#.\nTunashukuru sana kwa muda wako, upendo wako, na ushiriki wako.",
            ],
            'whatsapp_invitation_en' => [
                'label' => 'WhatsApp Invitation English',
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'type' => MessageTemplate::TYPE_INVITATION,
                'name' => 'WhatsApp Invitation English',
                'content' => "Hello #NAME#,

You are invited to #EVENT_NAME#.

Your card type is #CARD_TYPE#.
Venue: #EVENT_VENUE#
Time: #EVENT_TIME#

Please confirm your attendance by tapping one of the buttons below.

For the venue map, tap LOCATION.",
                'whatsapp_template_name' => 'event_invitation_en',
                'whatsapp_language_code' => 'en',
                'whatsapp_buttons' => [
                    'Attending' => 'rsvp_attending',
                    'Not Attending' => 'rsvp_not_attending',
                    'LOCATION' => '#LOCATION_LINK#',
                ],
            ],
            'whatsapp_event_ticket_en' => [
                'label' => 'WhatsApp Event Ticket English',
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'type' => MessageTemplate::TYPE_INVITATION,
                'name' => 'WhatsApp Event Ticket English',
                'content' => "Hello #NAME#,

Your ticket for #EVENT_NAME# is ready.

Ticket type: #CARD_TYPE#
Venue: #EVENT_VENUE#
Time: #EVENT_TIME#

Please come with your ticket on the event day. This ticket will be verified at the gate using the QR code.

For the venue map, tap LOCATION.",
                'whatsapp_template_name' => 'event_ticket_en',
                'whatsapp_language_code' => 'en',
                'whatsapp_buttons' => [
                    'Attending' => 'rsvp_attending',
                    'Not Attending' => 'rsvp_not_attending',
                    'LOCATION' => '#LOCATION_LINK#',
                ],
            ],
            'whatsapp_invitation_sw' => [
                'label' => 'WhatsApp Invitation Swahili',
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'type' => MessageTemplate::TYPE_INVITATION,
                'name' => 'WhatsApp Invitation Swahili',
                'content' => "Habari #NAME#,

Unakaribishwa kwenye #EVENT_NAME#.

Aina ya kadi yako ni #CARD_TYPE#.
Ukumbi: #EVENT_VENUE#
Muda: #EVENT_TIME#

Tafadhali thibitisha kama utahudhuria kwa kubonyeza mojawapo ya vitufe hapa chini.

Kwa ramani ya ukumbi, bonyeza LOCATION.",
                'whatsapp_template_name' => 'event_invitation_sw',
                'whatsapp_language_code' => 'en',
                'whatsapp_buttons' => [
                    'Attending' => 'rsvp_attending',
                    'Not Attending' => 'rsvp_not_attending',
                    'LOCATION' => '#LOCATION_LINK#',
                ],
            ],
        ];
    }

    private static function starterOptionsFor(?string $type, ?string $channel): array
    {
        return collect(self::starterTemplates())
            ->filter(fn (array $template): bool => blank($type) || $template['type'] === $type)
            ->filter(fn (array $template): bool => blank($channel) || $template['channel'] === $channel)
            ->mapWithKeys(fn (array $template, string $key): array => [$key => $template['label']])
            ->toArray();
    }

    private static function starterFor(?string $type, ?string $channel): ?array
    {
        if (blank($type)) {
            return null;
        }

        $channel ??= MessageTemplate::CHANNEL_SMS;

        $exact = collect(self::starterTemplates())
            ->first(fn (array $template): bool => $template['type'] === $type && $template['channel'] === $channel);

        if ($exact) {
            return $exact;
        }

        return collect(self::starterTemplates())
            ->first(fn (array $template): bool => $template['type'] === $type);
    }

    private function createDefaultsAndNotify(): void
    {
        $created = $this->createDefaultTemplates();

        Notification::make()
            ->title($created > 0 ? 'Default templates created' : 'Templates already exist')
            ->body($created > 0 ? "{$created} missing templates were created." : 'No changes were made because all default templates already exist.')
            ->success()
            ->send();
    }

    private function createDefaultTemplates(): int
    {
        /** @var Model $event */
        $event = $this->getOwnerRecord();
        $created = 0;

        foreach (self::starterTemplates() as $template) {
            $record = MessageTemplate::firstOrCreate(
                [
                    'event_id' => $event->getKey(),
                    'channel' => $template['channel'],
                    'type' => $template['type'],
                ],
                [
                    'event_id' => $event->getKey(),
                    'channel' => $template['channel'],
                    'type' => $template['type'],
                    'name' => $template['name'],
                    'content' => $template['content'],
                    'whatsapp_template_name' => $template['whatsapp_template_name'] ?? null,
                    'whatsapp_language_code' => $template['whatsapp_language_code'] ?? (
                        ($template['channel'] ?? null) === MessageTemplate::CHANNEL_WHATSAPP
                            ? self::WHATSAPP_LANGUAGE_CODE
                            : null
                    ),
                    'whatsapp_buttons' => $template['whatsapp_buttons'] ?? null,
                    'status' => MessageTemplate::STATUS_ACTIVE,
                ],
            );

            if ($record->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    private function syncWhatsAppProviderTemplatesAndNotify(): void
    {
        $updated = $this->syncWhatsAppProviderTemplates();

        Notification::make()
            ->title('WhatsApp provider templates synced')
            ->body("Updated/created: {$updated}. Each template language is stored on the template. Run php artisan optimize:clear after syncing.")
            ->success()
            ->persistent()
            ->send();
    }

    private function syncWhatsAppProviderTemplates(): int
    {
        /** @var Model $event */
        $event = $this->getOwnerRecord();
        $updated = 0;

        $whatsappTemplates = collect(self::starterTemplates())
            ->filter(fn (array $template): bool => $template['channel'] === MessageTemplate::CHANNEL_WHATSAPP);

        foreach ($whatsappTemplates as $template) {
            $record = MessageTemplate::updateOrCreate(
                [
                    'event_id' => $event->getKey(),
                    'channel' => $template['channel'],
                    'type' => $template['type'],
                ],
                [
                    'name' => $template['name'],
                    'content' => $template['content'],
                    'whatsapp_template_name' => $template['whatsapp_template_name'] ?? null,
                    'whatsapp_language_code' => $template['whatsapp_language_code'] ?? (
                        ($template['channel'] ?? null) === MessageTemplate::CHANNEL_WHATSAPP
                            ? self::WHATSAPP_LANGUAGE_CODE
                            : null
                    ),
                    'whatsapp_buttons' => $template['whatsapp_buttons'] ?? null,
                    'status' => MessageTemplate::STATUS_ACTIVE,
                ],
            );

            $this->deactivateOtherActiveTemplates($record);

            $updated++;
        }

        return $updated;
    }

    private function afterTemplateSaved(MessageTemplate $record): null
    {
        if ($record->status === MessageTemplate::STATUS_ACTIVE) {
            $this->deactivateOtherActiveTemplates($record);
        }

        Notification::make()
            ->title('Template saved')
            ->body('Your template is ready to use in the Communications actions.')
            ->success()
            ->send();

        return null;
    }

    private function deactivateOtherActiveTemplates(MessageTemplate $record): void
    {
        MessageTemplate::query()
            ->where('event_id', $record->event_id)
            ->where('channel', $record->channel)
            ->where('type', $record->type)
            ->where('id', '!=', $record->id)
            ->where('status', MessageTemplate::STATUS_ACTIVE)
            ->update([
                'status' => MessageTemplate::STATUS_INACTIVE,
                'updated_at' => now(),
            ]);
    }

    private function mutateTemplateData(array $data): array
    {
        if (($data['channel'] ?? null) !== MessageTemplate::CHANNEL_WHATSAPP) {
            $data['whatsapp_template_name'] = null;
            $data['whatsapp_language_code'] = null;
            $data['whatsapp_buttons'] = null;
        }

        if (($data['channel'] ?? null) === MessageTemplate::CHANNEL_WHATSAPP && blank($data['whatsapp_language_code'] ?? null)) {
            $data['whatsapp_language_code'] = self::WHATSAPP_LANGUAGE_CODE;
        }

        if (! array_key_exists('status', $data) || blank($data['status'])) {
            $data['status'] = MessageTemplate::STATUS_ACTIVE;
        }

        return $data;
    }

    private static function channelOptions(): array
    {
        return method_exists(MessageTemplate::class, 'channels')
            ? MessageTemplate::channels()
            : [
                MessageTemplate::CHANNEL_SMS => 'SMS',
                MessageTemplate::CHANNEL_WHATSAPP => 'WhatsApp',
            ];
    }

    private static function typeOptions(): array
    {
        $types = method_exists(MessageTemplate::class, 'types')
            ? MessageTemplate::types()
            : [
                MessageTemplate::TYPE_INVITATION => 'Invitation',
                MessageTemplate::TYPE_RSVP_PENDING_REMINDER => 'RSVP Pending Reminder',
                MessageTemplate::TYPE_ATTENDING_REMINDER => 'One Day Before Reminder',
                MessageTemplate::TYPE_EVENT_DAY_REMINDER => 'Event Day Reminder',
                MessageTemplate::TYPE_CUSTOM => 'Custom Message',
            ];

        return array_merge($types, [
            self::TYPE_WELCOME_CHECKIN => 'Welcome After Check-in',
            self::TYPE_THANK_YOU => 'Thank You Message',
        ]);
    }

    private static function statusOptions(): array
    {
        return method_exists(MessageTemplate::class, 'statuses')
            ? MessageTemplate::statuses()
            : [
                MessageTemplate::STATUS_ACTIVE => 'Active',
                MessageTemplate::STATUS_INACTIVE => 'Inactive',
            ];
    }

    private function emptyTemplateBox(): string
    {
        return '<div style="background:#F8FAFC;border:1px dashed #CBD5E1;border-radius:14px;padding:16px;color:#64748B;">Select a template above to see the options.</div>';
    }

    private function selectedTemplateBox(string $type): string
    {
        $label = self::typeOptions()[$type] ?? ucwords(str_replace('_', ' ', $type));

        $description = match ($type) {
            MessageTemplate::TYPE_INVITATION => 'Use this for sending invitation cards or private invitation links.',
            MessageTemplate::TYPE_RSVP_PENDING_REMINDER => 'Use this to remind invitees who have not confirmed RSVP.',
            MessageTemplate::TYPE_ATTENDING_REMINDER => 'Use this to remind invitees who already confirmed attendance.',
            MessageTemplate::TYPE_EVENT_DAY_REMINDER => 'Use this on event day before guests arrive.',
            self::TYPE_WELCOME_CHECKIN => 'Use this after successful gate check-in.',
            self::TYPE_THANK_YOU => 'Use this after the event to thank guests.',
            default => 'Use this for custom event communication.',
        };

        return '<div style="background:#FFFFFF;border:1px solid #E5E7EB;border-left:5px solid #213B73;border-radius:14px;padding:16px;color:#111827;">'
            . '<div style="font-weight:700;font-size:15px;margin-bottom:4px;">' . e($label) . '</div>'
            . '<div style="color:#64748B;">' . e($description) . '</div>'
            . '</div>';
    }

    private function usageRuleBox(string $channel, string $type): string
    {
        $channelLabel = self::channelOptions()[$channel] ?? ucfirst($channel);
        $typeLabel = self::typeOptions()[$type] ?? ucwords(str_replace('_', ' ', $type));

        return '<div style="background:#F8FAFC;border-left:4px solid #213B73;border-radius:10px;padding:12px;color:#111827;">'
            . '<strong>' . e($channelLabel . ' / ' . $typeLabel) . '</strong><br>'
            . 'When sending, the system should use the active template matching this event, channel, and template type.'
            . '</div>';
    }

    private function previewBox(string $content): string
    {
        $preview = blank($content)
            ? 'Start typing your message to see a preview here.'
            : $this->previewMessage($content);

        return '<div style="white-space:pre-line;background:#F8FAFC;border:1px solid #E5E7EB;border-radius:12px;padding:14px;color:#111827;line-height:1.7;">'
            . e($preview)
            . '</div>';
    }

    private function placeholdersBox(): string
    {
        $placeholderRows = collect(self::PLACEHOLDERS)
            ->unique()
            ->map(function (string $placeholder): string {
                $description = self::PLACEHOLDER_DESCRIPTIONS[$placeholder] ?? 'Message value';

                return '<tr>'
                    . '<td style="padding:8px 10px;border-bottom:1px solid #E5E7EB;white-space:nowrap;"><code style="background:#F8FAFC;border:1px solid #E5E7EB;padding:3px 7px;border-radius:7px;">' . e($placeholder) . '</code></td>'
                    . '<td style="padding:8px 10px;border-bottom:1px solid #E5E7EB;color:#475569;">' . e($description) . '</td>'
                    . '</tr>';
            })
            ->implode('');

        $smsExamples = [
            'SMS Invitation' => "Habari #NAME#, umealikwa kwenye #EVENT_NAME#.\nTarehe: #EVENT_DATE#\nMuda: #EVENT_TIME#\nUkumbi: #EVENT_VENUE#\nFungua kadi yako hapa: #INVITATION_LINK#",
            'SMS RSVP Reminder' => "Habari #NAME#, tunakukumbusha kuthibitisha ushiriki wako kwenye #EVENT_NAME#.\nTafadhali fungua link hii: #RSVP_LINK#",
            'SMS Event Day Reminder' => "Habari #NAME#, leo ni siku ya #EVENT_NAME#.\nUkumbi: #EVENT_VENUE#\nMuda: #EVENT_TIME#\nNjoo na kadi yako au serial number: #SERIAL_NUMBER#.",
            'SMS Thank You' => "Habari #NAME#, asante kwa kuhudhuria #EVENT_NAME#.\nTunashukuru sana kwa muda wako, upendo wako, na ushiriki wako.",
        ];

        $exampleHtml = collect($smsExamples)
            ->map(fn (string $example, string $title): string => '<div style="background:#FFFFFF;border:1px solid #E5E7EB;border-radius:12px;padding:12px;margin-top:10px;">'
                . '<div style="font-weight:700;color:#213B73;margin-bottom:6px;">' . e($title) . '</div>'
                . '<pre style="white-space:pre-wrap;margin:0;color:#111827;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:12px;line-height:1.6;">' . e($example) . '</pre>'
                . '</div>')
            ->implode('');

        return '<div style="display:grid;gap:14px;color:#111827;">'
            . '<div style="background:#F8FAFC;border-left:4px solid #213B73;border-radius:12px;padding:12px;line-height:1.6;">'
            . '<strong>Recommended:</strong> For SMS, mainly use <code>#NAME#</code>, <code>#EVENT_NAME#</code>, <code>#EVENT_DATE#</code>, <code>#EVENT_TIME#</code>, <code>#EVENT_VENUE#</code>, and <code>#INVITATION_LINK#</code>. Keep SMS short to reduce SMS cost.'
            . '</div>'
            . '<table style="width:100%;border-collapse:collapse;background:#FFFFFF;border:1px solid #E5E7EB;border-radius:12px;overflow:hidden;">'
            . '<thead><tr style="background:#F8FAFC;"><th style="text-align:left;padding:8px 10px;color:#111827;">Placeholder</th><th style="text-align:left;padding:8px 10px;color:#111827;">Meaning</th></tr></thead>'
            . '<tbody>' . $placeholderRows . '</tbody>'
            . '</table>'
            . '<div>'
            . '<div style="font-weight:700;color:#111827;margin-bottom:4px;">Ready SMS examples</div>'
            . '<div style="color:#64748B;font-size:13px;">Copy one example into Message Text, then edit it for your event.</div>'
            . $exampleHtml
            . '</div>'
            . '</div>';
    }

    private function previewMessage(string $content): string
    {
        return str_replace(
            array_keys(self::SAMPLE_VALUES),
            array_values(self::SAMPLE_VALUES),
            $content,
        );
    }

    private function templateDescription(MessageTemplate $record): string
    {
        if ($record->channel === MessageTemplate::CHANNEL_WHATSAPP && filled($record->whatsapp_template_name)) {
            $language = filled($record->whatsapp_language_code ?? null)
                ? ' • Lang: ' . strtoupper((string) $record->whatsapp_language_code)
                : '';

            return 'Provider: ' . $record->whatsapp_template_name . $language;
        }

        return match ($record->type) {
            MessageTemplate::TYPE_INVITATION => 'Invitation card or private link',
            MessageTemplate::TYPE_RSVP_PENDING_REMINDER => 'Guests who have not confirmed RSVP',
            MessageTemplate::TYPE_ATTENDING_REMINDER => 'Guests who confirmed attendance',
            MessageTemplate::TYPE_EVENT_DAY_REMINDER => 'Event day reminder',
            self::TYPE_WELCOME_CHECKIN => 'After successful check-in',
            self::TYPE_THANK_YOU => 'After-event thank you message',
            default => 'Custom event message',
        };
    }

    private function recordPreviewBox(MessageTemplate $record): string
    {
        $providerTemplate = filled($record->whatsapp_template_name)
            ? '<div><strong>Provider Template:</strong> ' . e($record->whatsapp_template_name) . '</div>'
            : '';

        $providerLanguage = filled($record->whatsapp_language_code ?? null)
            ? '<div><strong>WhatsApp Language:</strong> ' . e(strtoupper((string) $record->whatsapp_language_code)) . '</div>'
            : '';

        $buttons = collect($record->whatsapp_buttons ?? [])
            ->map(fn ($value, $key): string => '<span style="display:inline-block;background:#F8FAFC;border:1px solid #E5E7EB;border-radius:999px;padding:4px 10px;margin:4px;">' . e($key) . ' → ' . e($value) . '</span>')
            ->implode('');

        $buttonsHtml = filled($buttons)
            ? '<div style="margin-top:12px;"><strong>Buttons:</strong><br>' . $buttons . '</div>'
            : '';

        return '<div style="font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;color:#111827;">'
            . '<div style="display:grid;gap:6px;margin-bottom:14px;">'
            . '<div><strong>Channel:</strong> ' . e(self::channelOptions()[$record->channel] ?? $record->channel) . '</div>'
            . '<div><strong>Type:</strong> ' . e(self::typeOptions()[$record->type] ?? $record->type) . '</div>'
            . '<div><strong>Status:</strong> ' . e(self::statusOptions()[$record->status] ?? $record->status) . '</div>'
            . $providerTemplate
            . $providerLanguage
            . '</div>'
            . '<div style="white-space:pre-line;background:#F8FAFC;border:1px solid #E5E7EB;border-radius:12px;padding:14px;line-height:1.7;">'
            . e($this->previewMessage((string) $record->content))
            . '</div>'
            . $buttonsHtml
            . '</div>';
    }
}
