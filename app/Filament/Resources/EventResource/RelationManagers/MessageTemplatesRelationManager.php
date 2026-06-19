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

    /**
     * Keep these extra types here even if your MessageTemplate model only has the MVP constants.
     * They are useful for eLive Card automation: check-in welcome and after-event thank you messages.
     */
    private const TYPE_WELCOME_CHECKIN = 'welcome_checkin';
    private const TYPE_THANK_YOU = 'thank_you';

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
        '{name}',
        '{phone}',
        '{event_name}',
        '{event_date}',
        '{event_time}',
        '{event_venue}',
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
        '{invitation_link}',
        '{private_link}',
        '{private_invitation_url}',
        '{rsvp_link}',
        '{rsvp_url}',
        '{card_link}',
        '{{name}}',
        '{{phone}}',
        '{{event_name}}',
        '{{event_date}}',
        '{{event_time}}',
        '{{event_venue}}',
        '{{venue}}',
        '{{venue_address}}',
        '{{location_link}}',
        '{{dress_code}}',
        '{{card_type}}',
        '{{allowed_guests}}',
        '{{guest_count}}',
        '{{table_number}}',
        '{{category}}',
        '{{serial_number}}',
        '{{invitation_link}}',
        '{{private_link}}',
        '{{private_invitation_url}}',
        '{{rsvp_link}}',
        '{{rsvp_url}}',
        '{{card_link}}',
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

        '{name}' => 'Joel Mwasiposya',
        '{phone}' => '255768461644',
        '{event_name}' => 'Joel Wedding Ceremony',
        '{event_date}' => '25/06/2026',
        '{event_time}' => '18:00',
        '{event_venue}' => 'Victoria Place',
        '{venue}' => 'Victoria Place',
        '{venue_address}' => 'Dar es Salaam',
        '{location_link}' => 'https://maps.google.com/example',
        '{dress_code}' => 'Smart Casual',
        '{card_type}' => 'VIP',
        '{allowed_guests}' => '2',
        '{guest_count}' => '2',
        '{table_number}' => 'Table 5',
        '{category}' => 'Family',
        '{serial_number}' => 'ELV-2026-ABC123',
        '{invitation_link}' => 'https://staging-digital.elive.co.tz/i/ABC123',
        '{private_link}' => 'https://staging-digital.elive.co.tz/i/ABC123',
        '{private_invitation_url}' => 'https://staging-digital.elive.co.tz/i/ABC123',
        '{rsvp_link}' => 'https://staging-digital.elive.co.tz/i/ABC123',
        '{rsvp_url}' => 'https://staging-digital.elive.co.tz/i/ABC123',
        '{card_link}' => 'https://staging-digital.elive.co.tz/storage/events/1/generated-cards/sample.jpg',

        '{{name}}' => 'Joel Mwasiposya',
        '{{phone}}' => '255768461644',
        '{{event_name}}' => 'Joel Wedding Ceremony',
        '{{event_date}}' => '25/06/2026',
        '{{event_time}}' => '18:00',
        '{{event_venue}}' => 'Victoria Place',
        '{{venue}}' => 'Victoria Place',
        '{{venue_address}}' => 'Dar es Salaam',
        '{{location_link}}' => 'https://maps.google.com/example',
        '{{dress_code}}' => 'Smart Casual',
        '{{card_type}}' => 'VIP',
        '{{allowed_guests}}' => '2',
        '{{guest_count}}' => '2',
        '{{table_number}}' => 'Table 5',
        '{{category}}' => 'Family',
        '{{serial_number}}' => 'ELV-2026-ABC123',
        '{{invitation_link}}' => 'https://staging-digital.elive.co.tz/i/ABC123',
        '{{private_link}}' => 'https://staging-digital.elive.co.tz/i/ABC123',
        '{{private_invitation_url}}' => 'https://staging-digital.elive.co.tz/i/ABC123',
        '{{rsvp_link}}' => 'https://staging-digital.elive.co.tz/i/ABC123',
        '{{rsvp_url}}' => 'https://staging-digital.elive.co.tz/i/ABC123',
        '{{card_link}}' => 'https://staging-digital.elive.co.tz/storage/events/1/generated-cards/sample.jpg',
    ];

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Template Details')
                    ->description('Create reusable SMS and WhatsApp templates for this event.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Template Name')
                            ->placeholder('Example: SMS Invitation')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Forms\Components\Select::make('channel')
                            ->label('Channel')
                            ->options(self::channelOptions())
                            ->default(MessageTemplate::CHANNEL_SMS)
                            ->required()
                            ->native(false)
                            ->live(),

                        Forms\Components\Select::make('type')
                            ->label('Template Type')
                            ->options(self::typeOptions())
                            ->default(MessageTemplate::TYPE_INVITATION)
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(self::statusOptions())
                            ->default(MessageTemplate::STATUS_ACTIVE)
                            ->required()
                            ->native(false),

                        Forms\Components\Placeholder::make('template_rule')
                            ->label('Usage Rule')
                            ->content(fn (Forms\Get $get): HtmlString => new HtmlString(
                                $this->usageRuleHtml(
                                    (string) ($get('channel') ?? MessageTemplate::CHANNEL_SMS),
                                    (string) ($get('type') ?? MessageTemplate::TYPE_INVITATION),
                                )
                            ))
                            ->columnSpan(2),
                    ])
                    ->columns(4),

                Forms\Components\Section::make('Message Content')
                    ->description('Use placeholders below. Your services already support #NAME#, {name}, and {{name}} formats.')
                    ->schema([
                        Forms\Components\Textarea::make('content')
                            ->label('Message')
                            ->rows(9)
                            ->required()
                            ->live(debounce: 500)
                            ->columnSpanFull()
                            ->placeholder(
                                'Habari #NAME#, umealikwa kwenye #EVENT_NAME#.' . PHP_EOL .
                                'Tarehe: #EVENT_DATE#' . PHP_EOL .
                                'Muda: #EVENT_TIME#' . PHP_EOL .
                                'Ukumbi: #EVENT_VENUE#' . PHP_EOL .
                                'Kadi yako: #INVITATION_LINK#'
                            ),

                        Forms\Components\Placeholder::make('message_preview')
                            ->label('Preview')
                            ->content(fn (Forms\Get $get): HtmlString => new HtmlString(
                                $this->messagePreviewHtml((string) ($get('content') ?? ''))
                            ))
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('available_placeholders')
                            ->label('Available Placeholders')
                            ->content(fn (): HtmlString => new HtmlString($this->placeholdersHtml()))
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('WhatsApp Provider Settings')
                    ->description('Only required for WhatsApp Cloud API or provider-approved templates.')
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp_template_name')
                            ->label('Provider Template Name')
                            ->placeholder('Example: elive_event_invitation_rsvp')
                            ->required(fn (Forms\Get $get): bool => $get('channel') === MessageTemplate::CHANNEL_WHATSAPP)
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\KeyValue::make('whatsapp_buttons')
                            ->label('WhatsApp Buttons')
                            ->keyLabel('Button Text')
                            ->valueLabel('Action / Payload / URL Placeholder')
                            ->addActionLabel('Add Button')
                            ->reorderable()
                            ->helperText('Example: View Invitation = #INVITATION_LINK#, RSVP Yes = rsvp_attending, RSVP No = rsvp_not_attending.')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Forms\Get $get): bool => $get('channel') === MessageTemplate::CHANNEL_WHATSAPP),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('updated_at', 'desc')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right')
            ->emptyStateHeading('No message templates yet')
            ->emptyStateDescription('Create default templates first, then edit the wording for this event.')
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
                    ->sortable(),

                Tables\Columns\TextColumn::make('content')
                    ->label('Preview')
                    ->formatStateUsing(fn (?string $state): string => Str::limit(
                        str_replace(["\r\n", "\n", "\r"], ' ', (string) $state),
                        90
                    ))
                    ->tooltip(fn (MessageTemplate $record): ?string => $record->content)
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('channel')
                    ->label('Channel')
                    ->options(self::channelOptions()),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Type')
                    ->options(self::typeOptions()),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(self::statusOptions()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('New Template')
                    ->icon('heroicon-o-plus')
                    ->button()
                    ->color('primary')
                    ->modalHeading('Create Message Template')
                    ->modalWidth('5xl')
                    ->mutateFormDataUsing(fn (array $data): array => $this->mutateTemplateData($data)),

                Tables\Actions\Action::make('create_default_templates')
                    ->label('Create Defaults')
                    ->icon('heroicon-o-sparkles')
                    ->button()
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Create Default Message Templates')
                    ->modalDescription('This will create missing SMS and WhatsApp templates for this event. Existing templates will not be overwritten.')
                    ->action(function (): void {
                        $created = $this->createDefaultTemplates();

                        Notification::make()
                            ->title($created > 0 ? 'Default templates created' : 'Templates already exist')
                            ->body($created > 0 ? "{$created} missing templates were created." : 'No changes were made because all default templates already exist.')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (MessageTemplate $record): string => 'Preview: ' . $record->name)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (MessageTemplate $record): HtmlString => new HtmlString(
                        $this->recordPreviewHtml($record)
                    )),

                Tables\Actions\Action::make('make_active')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (MessageTemplate $record): bool => $record->status !== MessageTemplate::STATUS_ACTIVE)
                    ->requiresConfirmation()
                    ->action(function (MessageTemplate $record): void {
                        $record->update(['status' => MessageTemplate::STATUS_ACTIVE]);

                        Notification::make()
                            ->title('Template activated')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('make_inactive')
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

                Tables\Actions\EditAction::make()
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('Edit Message Template')
                    ->modalWidth('5xl')
                    ->mutateFormDataUsing(fn (array $data): array => $this->mutateTemplateData($data)),

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
                            $records->each->update(['status' => MessageTemplate::STATUS_ACTIVE]);

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
                MessageTemplate::TYPE_ATTENDING_REMINDER => 'Attending Reminder',
                MessageTemplate::TYPE_EVENT_DAY_REMINDER => 'Event Day Reminder',
                MessageTemplate::TYPE_CUSTOM => 'Custom Message',
            ];

        return array_merge($types, [
            self::TYPE_WELCOME_CHECKIN => 'Welcome After Check-in',
            self::TYPE_THANK_YOU => 'Thank You',
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

    private function createDefaultTemplates(): int
    {
        /** @var Model $event */
        $event = $this->getOwnerRecord();

        $defaults = [
            [
                'channel' => MessageTemplate::CHANNEL_SMS,
                'type' => MessageTemplate::TYPE_INVITATION,
                'name' => 'SMS Invitation',
                'content' => "Habari #NAME#, umealikwa kwenye #EVENT_NAME#.\nTarehe: #EVENT_DATE#\nMuda: #EVENT_TIME#\nUkumbi: #EVENT_VENUE#\nFungua kadi yako hapa: #INVITATION_LINK#",
                'whatsapp_template_name' => null,
                'whatsapp_buttons' => null,
            ],
            [
                'channel' => MessageTemplate::CHANNEL_SMS,
                'type' => MessageTemplate::TYPE_RSVP_PENDING_REMINDER,
                'name' => 'SMS RSVP Pending Reminder',
                'content' => "Habari #NAME#, tunakukumbusha kuthibitisha ushiriki wako kwenye #EVENT_NAME#.\nTafadhali fungua link hii: #RSVP_LINK#",
                'whatsapp_template_name' => null,
                'whatsapp_buttons' => null,
            ],
            [
                'channel' => MessageTemplate::CHANNEL_SMS,
                'type' => MessageTemplate::TYPE_ATTENDING_REMINDER,
                'name' => 'SMS Attending Reminder',
                'content' => "Habari #NAME#, tunakukumbusha kuhusu #EVENT_NAME# tarehe #EVENT_DATE# saa #EVENT_TIME#.\nUkumbi: #EVENT_VENUE#\nKadi yako: #INVITATION_LINK#",
                'whatsapp_template_name' => null,
                'whatsapp_buttons' => null,
            ],
            [
                'channel' => MessageTemplate::CHANNEL_SMS,
                'type' => MessageTemplate::TYPE_EVENT_DAY_REMINDER,
                'name' => 'SMS Event Day Reminder',
                'content' => "Habari #NAME#, leo ni siku ya #EVENT_NAME#.\nTafadhali njoo na kadi yako au serial number: #SERIAL_NUMBER#\nLocation: #LOCATION_LINK#",
                'whatsapp_template_name' => null,
                'whatsapp_buttons' => null,
            ],
            [
                'channel' => MessageTemplate::CHANNEL_SMS,
                'type' => self::TYPE_WELCOME_CHECKIN,
                'name' => 'Welcome SMS After Check-in',
                'content' => 'Karibu #NAME# kwenye #EVENT_NAME#. Tunafurahi kuwa nawe. Furahia tukio.',
                'whatsapp_template_name' => null,
                'whatsapp_buttons' => null,
            ],
            [
                'channel' => MessageTemplate::CHANNEL_SMS,
                'type' => self::TYPE_THANK_YOU,
                'name' => 'SMS Thank You Message',
                'content' => 'Habari #NAME#, asante kwa kuhudhuria #EVENT_NAME#. Tunashukuru sana kwa muda wako na ushiriki wako.',
                'whatsapp_template_name' => null,
                'whatsapp_buttons' => null,
            ],
            [
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'type' => MessageTemplate::TYPE_INVITATION,
                'name' => 'WhatsApp Invitation',
                'content' => "Habari #NAME#,\n\nUnakaribishwa kwenye #EVENT_NAME#.\n\nTarehe: #EVENT_DATE#\nMuda: #EVENT_TIME#\nUkumbi: #EVENT_VENUE#\n\nTafadhali thibitisha kama utahudhuria kwa kutumia button.",
                'whatsapp_template_name' => config('services.whatsapp.templates.invitation', config('services.whatsapp.invitation_template', 'elive_event_invitation_rsvp')),
                'whatsapp_buttons' => [
                    'Asante, Nitafika' => 'rsvp_attending',
                    'Sitafika, Nina udhuru' => 'rsvp_not_attending',
                    'View Invitation' => '#INVITATION_LINK#',
                    'View Location' => '#LOCATION_LINK#',
                ],
            ],
            [
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'type' => MessageTemplate::TYPE_RSVP_PENDING_REMINDER,
                'name' => 'WhatsApp RSVP Pending Reminder',
                'content' => "Habari #NAME#,\n\nTunakukumbusha kuthibitisha ushiriki wako kwenye #EVENT_NAME#.\n\nTafadhali tumia button kuthibitisha.",
                'whatsapp_template_name' => 'elive_event_rsvp_reminder',
                'whatsapp_buttons' => [
                    'Asante, Nitafika' => 'rsvp_attending',
                    'Sitafika, Nina udhuru' => 'rsvp_not_attending',
                    'View Invitation' => '#INVITATION_LINK#',
                ],
            ],
            [
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'type' => MessageTemplate::TYPE_ATTENDING_REMINDER,
                'name' => 'WhatsApp Attending Reminder',
                'content' => "Habari #NAME#,\n\nTunakukumbusha kuhusu #EVENT_NAME#.\n\nTarehe: #EVENT_DATE#\nMuda: #EVENT_TIME#\nUkumbi: #EVENT_VENUE#\n\nFungua kadi yako kwa maelezo zaidi.",
                'whatsapp_template_name' => 'elive_event_attending_reminder',
                'whatsapp_buttons' => [
                    'View Invitation' => '#INVITATION_LINK#',
                    'View Location' => '#LOCATION_LINK#',
                ],
            ],
            [
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'type' => MessageTemplate::TYPE_EVENT_DAY_REMINDER,
                'name' => 'WhatsApp Event Day Reminder',
                'content' => "Habari #NAME#,\n\nLeo ni siku ya #EVENT_NAME#.\n\nTafadhali njoo na kadi yako kwa ajili ya check-in.\n\nUkumbi: #EVENT_VENUE#",
                'whatsapp_template_name' => 'elive_event_day_reminder',
                'whatsapp_buttons' => [
                    'View Invitation' => '#INVITATION_LINK#',
                    'View Location' => '#LOCATION_LINK#',
                ],
            ],
            [
                'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                'type' => self::TYPE_THANK_YOU,
                'name' => 'WhatsApp Thank You Message',
                'content' => "Habari #NAME#,\n\nAsante kwa kuhudhuria #EVENT_NAME#.\n\nTunashukuru sana kwa muda wako, upendo wako, na ushiriki wako.",
                'whatsapp_template_name' => 'elive_event_thank_you',
                'whatsapp_buttons' => null,
            ],
        ];

        $created = 0;

        foreach ($defaults as $template) {
            $record = MessageTemplate::firstOrCreate(
                [
                    'event_id' => $event->getKey(),
                    'channel' => $template['channel'],
                    'type' => $template['type'],
                ],
                array_merge($template, [
                    'event_id' => $event->getKey(),
                    'status' => MessageTemplate::STATUS_ACTIVE,
                ])
            );

            if ($record->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    private function messagePreviewHtml(string $content): string
    {
        $preview = blank($content)
            ? 'Start typing your message to see a preview here.'
            : $this->previewMessage($content);

        return '<div style="white-space: pre-line; background:#F8FAFC; border:1px solid #e5e7eb; border-radius:12px; padding:14px; color:#111827; line-height:1.7;">'
            . e($preview)
            . '</div>';
    }

    private function placeholdersHtml(): string
    {
        return '<div style="display:flex; flex-wrap:wrap; gap:6px; line-height:1.8;">'
            . collect(self::PLACEHOLDERS)
                ->unique()
                ->map(fn (string $placeholder): string => '<code style="background:#F8FAFC; border:1px solid #e5e7eb; padding:3px 7px; border-radius:7px;">' . e($placeholder) . '</code>')
                ->implode('')
            . '</div>';
    }

    private function previewMessage(string $content): string
    {
        return str_replace(
            array_keys(self::SAMPLE_VALUES),
            array_values(self::SAMPLE_VALUES),
            $content
        );
    }

    private function usageRuleHtml(string $channel, string $type): string
    {
        $channelLabel = self::channelOptions()[$channel] ?? ucfirst($channel);
        $typeLabel = self::typeOptions()[$type] ?? ucwords(str_replace('_', ' ', $type));

        return '<div style="background:#F8FAFC; border-left:4px solid #213B73; border-radius:10px; padding:12px; color:#111827;">'
            . '<strong>' . e($channelLabel . ' / ' . $typeLabel) . '</strong><br>'
            . 'The sending actions will use the latest active template matching this event, channel, and type.'
            . '</div>';
    }

    private function templateDescription(MessageTemplate $record): string
    {
        if ($record->channel === MessageTemplate::CHANNEL_WHATSAPP && filled($record->whatsapp_template_name)) {
            return 'Provider: ' . $record->whatsapp_template_name;
        }

        return match ($record->type) {
            MessageTemplate::TYPE_INVITATION => 'Used when sending invitation cards',
            MessageTemplate::TYPE_RSVP_PENDING_REMINDER => 'Used for invitees who have not confirmed RSVP',
            MessageTemplate::TYPE_ATTENDING_REMINDER => 'Used for invitees who confirmed attendance',
            MessageTemplate::TYPE_EVENT_DAY_REMINDER => 'Used on event day before check-in',
            self::TYPE_WELCOME_CHECKIN => 'Used after successful gate check-in',
            self::TYPE_THANK_YOU => 'Used after the event',
            default => 'Reusable event message',
        };
    }

    private function recordPreviewHtml(MessageTemplate $record): string
    {
        $providerTemplate = filled($record->whatsapp_template_name)
            ? '<div><strong>Provider Template:</strong> ' . e($record->whatsapp_template_name) . '</div>'
            : '';

        $buttons = collect($record->whatsapp_buttons ?? [])
            ->map(fn ($value, $key): string => '<span style="display:inline-block; background:#F8FAFC; border:1px solid #e5e7eb; border-radius:999px; padding:4px 10px; margin:4px;">' . e($key) . ' → ' . e($value) . '</span>')
            ->implode('');

        $buttonsHtml = filled($buttons)
            ? '<div style="margin-top:12px;"><strong>Buttons:</strong><br>' . $buttons . '</div>'
            : '';

        return '<div style="font-family: system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif; color:#111827;">'
            . '<div style="display:grid; gap:6px; margin-bottom:14px;">'
            . '<div><strong>Channel:</strong> ' . e(self::channelOptions()[$record->channel] ?? $record->channel) . '</div>'
            . '<div><strong>Type:</strong> ' . e(self::typeOptions()[$record->type] ?? $record->type) . '</div>'
            . '<div><strong>Status:</strong> ' . e(self::statusOptions()[$record->status] ?? $record->status) . '</div>'
            . $providerTemplate
            . '</div>'
            . '<div style="white-space: pre-line; background:#F8FAFC; border:1px solid #e5e7eb; border-radius:12px; padding:14px; line-height:1.7;">'
            . e($this->previewMessage((string) $record->content))
            . '</div>'
            . $buttonsHtml
            . '</div>';
    }
}
