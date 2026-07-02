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

    private const TYPE_WELCOME_CHECKIN = 'welcome_checkin';
    private const TYPE_THANK_YOU = 'thank_you';

    private const WHATSAPP_LANGUAGE_CODE = 'en';

    private const PLACEHOLDERS = [
        '#NAME#',
        '#PHONE#',
        '#EVENT_NAME#',
        '#EVENT_DATE#',
        '#EVENT_TIME#',
        '#EVENT_VENUE#',
        '#VENUE_ADDRESS#',
        '#LOCATION_LINK#',
        '#DRESS_CODE#',
        '#CARD_TYPE#',
        '#ALLOWED_GUESTS#',
        '#GUEST_COUNT#',
        '#TABLE_NUMBER#',
        '#CATEGORY#',
        '#SERIAL_NUMBER#',
        '#INVITATION_LINK#',
        '#PRIVATE_INVITATION_URL#',
        '#RSVP_LINK#',
        '#CARD_LINK#',
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
                Forms\Components\Section::make('1. Select Template')
                    ->description('Choose the message template first. The customization options will appear after selection.')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Template')
                            ->options(self::typeOptions())
                            ->placeholder('Select a template')
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (?string $state, Forms\Get $get, callable $set): void {
                                if (blank($state)) {
                                    $set('name', null);
                                    $set('content', null);
                                    $set('whatsapp_template_name', null);
                                    $set('whatsapp_buttons', null);

                                    return;
                                }

                                $channel = $get('channel') ?: MessageTemplate::CHANNEL_SMS;
                                $starter = self::starterFor($state, $channel);

                                if (! $starter) {
                                    return;
                                }

                                $set('name', $starter['name']);
                                $set('content', $starter['content']);
                                $set('whatsapp_template_name', $starter['whatsapp_template_name'] ?? null);
                                $set('whatsapp_buttons', $starter['whatsapp_buttons'] ?? null);
                            })
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('template_summary')
                            ->label('')
                            ->content(fn (Forms\Get $get): HtmlString => new HtmlString(
                                blank($get('type'))
                                    ? $this->emptyTemplateBox()
                                    : $this->selectedTemplateBox((string) $get('type'))
                            ))
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('2. Template Options')
                    ->description('Control where this template is used and whether it is active.')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->visible(fn (Forms\Get $get): bool => filled($get('type')))
                    ->schema([
                        Forms\Components\Select::make('channel')
                            ->label('Channel')
                            ->options(self::channelOptions())
                            ->default(MessageTemplate::CHANNEL_SMS)
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (?string $state, Forms\Get $get, callable $set): void {
                                $type = $get('type');

                                if (blank($type) || blank($state)) {
                                    return;
                                }

                                $starter = self::starterFor($type, $state);

                                if ($starter) {
                                    $set('name', $starter['name']);
                                    $set('content', $starter['content']);
                                    $set('whatsapp_template_name', $starter['whatsapp_template_name'] ?? null);
                                    $set('whatsapp_buttons', $starter['whatsapp_buttons'] ?? null);
                                }

                                if ($state !== MessageTemplate::CHANNEL_WHATSAPP) {
                                    $set('whatsapp_template_name', null);
                                    $set('whatsapp_buttons', null);
                                }
                            }),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(self::statusOptions())
                            ->default(MessageTemplate::STATUS_ACTIVE)
                            ->required()
                            ->native(false)
                            ->helperText('Only active templates are used when sending messages.'),

                        Forms\Components\TextInput::make('name')
                            ->label('Template Name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('usage_rule')
                            ->label('How it will be used')
                            ->content(fn (Forms\Get $get): HtmlString => new HtmlString(
                                $this->usageRuleBox(
                                    (string) ($get('channel') ?: MessageTemplate::CHANNEL_SMS),
                                    (string) ($get('type') ?: MessageTemplate::TYPE_INVITATION),
                                )
                            ))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('3. Customize Message')
                    ->description('Edit the wording. The preview will update automatically.')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (Forms\Get $get): bool => filled($get('type')))
                    ->schema([
                        Forms\Components\Select::make('starter_template')
                            ->label('Load Sample Wording')
                            ->dehydrated(false)
                            ->native(false)
                            ->options(fn (Forms\Get $get): array => self::starterOptionsFor(
                                $get('type'),
                                $get('channel') ?: MessageTemplate::CHANNEL_SMS,
                            ))
                            ->placeholder('Optional: choose sample wording')
                            ->helperText('Selecting a sample will replace the current message content.')
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
                                $set('whatsapp_buttons', $starter['whatsapp_buttons'] ?? null);
                            })
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('content')
                            ->label('Message Content')
                            ->rows(12)
                            ->required()
                            ->live(debounce: 500)
                            ->placeholder("Habari #NAME#, umealikwa kwenye #EVENT_NAME#.\nTarehe: #EVENT_DATE#\nMuda: #EVENT_TIME#\nUkumbi: #EVENT_VENUE#\nKadi yako: #INVITATION_LINK#")
                            ->helperText('Use placeholders like #NAME#, #EVENT_NAME#, #EVENT_DATE#, #EVENT_TIME#, #EVENT_VENUE#, #INVITATION_LINK#, #RSVP_LINK#, #LOCATION_LINK#.')
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('live_preview')
                            ->label('Live Preview')
                            ->content(fn (Forms\Get $get): HtmlString => new HtmlString(
                                $this->previewBox((string) ($get('content') ?? ''))
                            ))
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('4. WhatsApp Options')
                    ->description('These options are shown only for WhatsApp templates.')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->visible(fn (Forms\Get $get): bool => filled($get('type')) && $get('channel') === MessageTemplate::CHANNEL_WHATSAPP)
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp_template_name')
                            ->label('Provider Template Name')
                            ->placeholder('Example: elive_event_invitation_rsvp')
                            ->required(fn (Forms\Get $get): bool => $get('channel') === MessageTemplate::CHANNEL_WHATSAPP)
                            ->maxLength(255)
                            ->helperText('Use the exact approved Meta template name. For the current templates, use language code: en.')
                            ->columnSpanFull(),

                        Forms\Components\KeyValue::make('whatsapp_buttons')
                            ->label('WhatsApp Buttons')
                            ->keyLabel('Button Text')
                            ->valueLabel('Action / URL / Payload')
                            ->addActionLabel('Add Button')
                            ->reorderable()
                            ->helperText('Examples: View Invitation = #INVITATION_LINK#, View Location = #LOCATION_LINK#, RSVP Yes = rsvp_attending.')
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('whatsapp_warning')
                            ->label('Note')
                            ->content(new HtmlString(
                                '<div style="background:#F8FAFC;border-left:4px solid #FD9618;border-radius:12px;padding:12px;color:#111827;">WhatsApp sending requires approved Meta templates. Current Meta templates use language code <strong>en</strong>. The provider template name must match exactly, for example <strong>elive_event_invitation_rsvp</strong>.</div>'
                            ))
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Available Placeholders')
                    ->description('Copy these placeholders into the message content.')
                    ->icon('heroicon-o-code-bracket')
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn (Forms\Get $get): bool => filled($get('type')))
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
                    ->label('Sync WhatsApp Providers')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->button()
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Sync WhatsApp provider template names')
                    ->modalDescription('This updates this event\'s WhatsApp templates to the approved Meta provider names: elive_event_invitation_rsvp, elive_event_rsvp_reminder, elive_event_attending_reminder, and elive_event_day_reminder. Use WHATSAPP_TEMPLATE_LANGUAGE=en in .env.')
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
            'whatsapp_invitation' => [
                'label' => 'WhatsApp Invitation with RSVP Buttons',
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'type' => MessageTemplate::TYPE_INVITATION,
                'name' => 'WhatsApp Invitation',
                'content' => "Habari #NAME#,\n\nUnakaribishwa kwenye #EVENT_NAME#.\nKadi yako ni #CARD_TYPE#.\nUkumbi ni #EVENT_VENUE#.\nMuda: #EVENT_TIME#\n\nPia tusaidie kujua ushiriki wako kwa kubonyeza mojawapo ya vitufe vilivyo hapa chini.",
                'whatsapp_template_name' => 'elive_event_invitation_rsvp',
                'whatsapp_buttons' => [
                    'Asante, Nitafika' => 'rsvp_attending',
                    'Sitafika, Nina udhuru' => 'rsvp_not_attending',
                    'View Invitation' => '#INVITATION_LINK#',
                    'View Location' => '#LOCATION_LINK#',
                ],
            ],
            'whatsapp_rsvp_pending' => [
                'label' => 'WhatsApp RSVP Pending Reminder',
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'type' => MessageTemplate::TYPE_RSVP_PENDING_REMINDER,
                'name' => 'WhatsApp RSVP Pending Reminder',
                'content' => "Habari #NAME#,\n\nTunakukumbusha kuthibitisha ushiriki wako kwenye #EVENT_NAME#.\nUkumbi: #EVENT_VENUE#\nMuda: #EVENT_TIME#\n\nTafadhali tumia button kuthibitisha.",
                'whatsapp_template_name' => 'elive_event_rsvp_reminder',
                'whatsapp_buttons' => [
                    'Asante, Nitafika' => 'rsvp_attending',
                    'Sitafika, Nina udhuru' => 'rsvp_not_attending',
                    'View Invitation' => '#INVITATION_LINK#',
                ],
            ],
            'whatsapp_attending' => [
                'label' => 'WhatsApp One Day Before Reminder',
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'type' => MessageTemplate::TYPE_ATTENDING_REMINDER,
                'name' => 'WhatsApp One Day Before Reminder',
                'content' => "Habari #NAME#,\n\nTunakukumbusha kuhusu #EVENT_NAME#.\nKadi yako ni #CARD_TYPE#.\nUkumbi: #EVENT_VENUE#\nMuda: #EVENT_TIME#\n\nFungua kadi yako kwa maelezo zaidi.",
                'whatsapp_template_name' => 'elive_event_attending_reminder',
                'whatsapp_buttons' => [
                    'View Invitation' => '#INVITATION_LINK#',
                    'View Location' => '#LOCATION_LINK#',
                ],
            ],
            'whatsapp_event_day' => [
                'label' => 'WhatsApp Event Day Reminder',
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'type' => MessageTemplate::TYPE_EVENT_DAY_REMINDER,
                'name' => 'WhatsApp Event Day Reminder',
                'content' => "Habari #NAME#,\n\nLeo ni siku ya #EVENT_NAME#.\nKadi yako ni #CARD_TYPE#.\nUkumbi: #EVENT_VENUE#\nMuda: #EVENT_TIME#\n\nTafadhali njoo na kadi yako kwa ajili ya check-in.",
                'whatsapp_template_name' => 'elive_event_day_reminder',
                'whatsapp_buttons' => [
                    'View Invitation' => '#INVITATION_LINK#',
                    'View Location' => '#LOCATION_LINK#',
                ],
            ],
            'whatsapp_welcome_checkin' => [
                'label' => 'WhatsApp Welcome After Check-in',
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'type' => self::TYPE_WELCOME_CHECKIN,
                'name' => 'WhatsApp Welcome After Check-in',
                'content' => "Karibu #NAME# kwenye #EVENT_NAME#.\n\nTunafurahi kuwa nawe. Furahia tukio.",
                'whatsapp_template_name' => 'elive_event_welcome_checkin',
                'whatsapp_buttons' => null,
            ],
            'whatsapp_thank_you' => [
                'label' => 'WhatsApp Thank You Message',
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'type' => self::TYPE_THANK_YOU,
                'name' => 'WhatsApp Thank You Message',
                'content' => "Habari #NAME#,\n\nAsante kwa kuhudhuria #EVENT_NAME#.\n\nTunashukuru sana kwa muda wako, upendo wako, na ushiriki wako.",
                'whatsapp_template_name' => 'elive_event_thank_you',
                'whatsapp_buttons' => null,
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
            ->body("Updated/created: {$updated}. Make sure WHATSAPP_TEMPLATE_LANGUAGE=en is set in .env, then run php artisan optimize:clear.")
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
            $data['whatsapp_buttons'] = null;
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
        return '<div style="display:flex;flex-wrap:wrap;gap:6px;line-height:1.8;">'
            . collect(self::PLACEHOLDERS)
                ->unique()
                ->map(fn (string $placeholder): string => '<code style="background:#F8FAFC;border:1px solid #E5E7EB;padding:3px 7px;border-radius:7px;">' . e($placeholder) . '</code>')
                ->implode('')
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
            return 'Provider: ' . $record->whatsapp_template_name;
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
            . '</div>'
            . '<div style="white-space:pre-line;background:#F8FAFC;border:1px solid #E5E7EB;border-radius:12px;padding:14px;line-height:1.7;">'
            . e($this->previewMessage((string) $record->content))
            . '</div>'
            . $buttonsHtml
            . '</div>';
    }
}
