<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MessageTemplateResource\Pages;
use App\Models\MessageTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MessageTemplateResource extends Resource
{
    protected static ?string $model = MessageTemplate::class;

    /**
     * Message templates are managed from the Event workspace.
     */
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Message Templates';

    protected static ?string $modelLabel = 'Message Template';

    protected static ?string $pluralModelLabel = 'Message Templates';

    protected static ?int $navigationSort = 1;

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
        return auth()->user()?->canManageEvents() ?? false;
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();

        return ($user?->isSuperAdmin() ?? false)
            || ($user?->isEventOwner() ?? false);
    }

    public static function canDeleteAny(): bool
    {
        $user = auth()->user();

        return ($user?->isSuperAdmin() ?? false)
            || ($user?->isEventOwner() ?? false);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Template Details')
                    ->description(
                        'Create reusable SMS or WhatsApp messages for an event.'
                    )
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->label('Event')
                            ->relationship('event', 'title')
                            ->searchable()
                            ->preload()
                            ->required(),

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
                                'rsvp_reminder' => 'RSVP Reminder',
                                'one_day_reminder' => 'One Day Before',
                                'event_day_reminder' => 'Event Day',
                                'welcome' => 'Welcome Message',
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
                    ->description(
                        'Use placeholders such as {invitee_name}, {event_name}, {event_date}, {event_time}, {venue}, {serial_number}, and {invitation_link}.'
                    )
                    ->schema([
                        Forms\Components\Textarea::make('content')
                            ->label('Message')
                            ->placeholder(
                                'Hello {invitee_name}, you are invited to {event_name} on {event_date} at {event_time}. Venue: {venue}.'
                            )
                            ->required()
                            ->rows(8)
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('available_placeholders')
                            ->label('Available placeholders')
                            ->content(
                                '{invitee_name}, {event_name}, {event_date}, {event_time}, {venue}, {serial_number}, {guest_count}, {table_number}, {invitation_link}, {rsvp_link}, {location_link}'
                            )
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('WhatsApp Configuration')
                    ->description(
                        'These settings are only required when the message channel is WhatsApp.'
                    )
                    ->visible(
                        fn (Forms\Get $get): bool =>
                            $get('channel') === 'whatsapp'
                    )
                    ->schema([
                        Forms\Components\TextInput::make('whatsapp_template_name')
                            ->label('Approved WhatsApp Template Name')
                            ->placeholder('event_invitation_sw')
                            ->helperText(
                                'Enter the exact template name approved in Meta WhatsApp Manager.'
                            )
                            ->maxLength(255),

                        Forms\Components\Textarea::make('whatsapp_buttons')
                            ->label('WhatsApp Buttons')
                            ->placeholder(
                                'Example: View Invitation, Confirm Attendance, View Location'
                            )
                            ->helperText(
                                'Enter a short description of the buttons configured in the approved WhatsApp template.'
                            )
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
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
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            match ($state) {
                                'whatsapp' => 'WhatsApp',
                                'sms' => 'SMS',
                                default => ucfirst((string) $state),
                            }
                    )
                    ->color(
                        fn (?string $state): string =>
                            match ($state) {
                                'whatsapp' => 'success',
                                'sms' => 'primary',
                                default => 'gray',
                            }
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            str($state ?? 'custom')
                                ->replace('_', ' ')
                                ->title()
                                ->toString()
                    )
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

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            ucfirst($state ?? 'inactive')
                    )
                    ->color(
                        fn (?string $state): string =>
                            match ($state) {
                                'active' => 'success',
                                'draft' => 'warning',
                                'inactive' => 'gray',
                                default => 'gray',
                            }
                    )
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
                    ->relationship('event', 'title')
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
                        'rsvp_reminder' => 'RSVP Reminder',
                        'one_day_reminder' => 'One Day Before',
                        'event_day_reminder' => 'Event Day',
                        'welcome' => 'Welcome Message',
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
                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make()
                    ->visible(function (): bool {
                        $user = auth()->user();

                        return ($user?->isSuperAdmin() ?? false)
                            || ($user?->isEventOwner() ?? false);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(function (): bool {
                            $user = auth()->user();

                            return ($user?->isSuperAdmin() ?? false)
                                || ($user?->isEventOwner() ?? false);
                        }),
                ]),
            ])
            ->emptyStateHeading('No message templates yet')
            ->emptyStateDescription(
                'Open an event workspace and create an SMS or WhatsApp template.'
            )
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