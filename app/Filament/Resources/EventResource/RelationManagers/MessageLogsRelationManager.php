<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\MessageLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MessageLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'messageLogs';

    protected static ?string $title = 'Message Logs';

    protected static ?string $modelLabel = 'Message Log';

    protected static ?string $pluralModelLabel = 'Message Logs';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Recipient')
                    ->schema([
                        Forms\Components\TextInput::make('invitee.name')
                            ->label('Invitee')
                            ->disabled(),

                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->disabled(),

                        Forms\Components\TextInput::make('channel')
                            ->label('Channel')
                            ->disabled(),

                        Forms\Components\TextInput::make('type')
                            ->label('Message Type')
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Delivery Information')
                    ->schema([
                        Forms\Components\TextInput::make('status')
                            ->label('Status')
                            ->disabled(),

                        Forms\Components\TextInput::make('provider')
                            ->label('Provider')
                            ->disabled(),

                        Forms\Components\TextInput::make('provider_message_id')
                            ->label('Provider Message ID')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('sent_at')
                            ->label('Sent At')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('delivered_at')
                            ->label('Delivered At')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('read_at')
                            ->label('Read At')
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Message Content')
                    ->schema([
                        Forms\Components\Textarea::make('message')
                            ->label('Message')
                            ->rows(7)
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('error_message')
                            ->label('Error Message')
                            ->rows(4)
                            ->disabled()
                            ->visible(
                                fn (?MessageLog $record): bool =>
                                    filled($record?->error_message)
                            )
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('phone')
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query
                    ->with(['invitee'])
                    ->latest('created_at')
            )
            ->emptyStateHeading('No messages sent yet')
            ->emptyStateDescription(
                'SMS and WhatsApp delivery records for this event will appear here.'
            )
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right')
            ->columns([
                Tables\Columns\TextColumn::make('invitee.name')
                    ->label('Invitee')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Unknown invitee')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Phone number copied')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('channel')
                    ->label('Channel')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'sms' => 'SMS',
                            'whatsapp' => 'WhatsApp',
                            default => strtoupper((string) $state),
                        }
                    )
                    ->icon(
                        fn (?string $state): string => match ($state) {
                            'sms' => 'heroicon-o-device-phone-mobile',
                            'whatsapp' => 'heroicon-o-chat-bubble-left-right',
                            default => 'heroicon-o-envelope',
                        }
                    )
                    ->color(
                        fn (?string $state): string => match ($state) {
                            'sms' => 'info',
                            'whatsapp' => 'success',
                            default => 'gray',
                        }
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'invitation',
                            'invitation_card' => 'Invitation',

                            'rsvp_pending_reminder',
                            'rsvp_reminder' => 'RSVP Reminder',

                            'attending_reminder' => 'Attending Reminder',

                            'event_day_reminder',
                            'event_reminder' => 'Event Reminder',

                            'final_reminder' => 'Final Reminder',

                            'welcome_checkin',
                            'welcome_sms',
                            'welcome' => 'Welcome Check-in',

                            'thank_you' => 'Thank You',

                            'custom' => 'Custom',

                            default => str($state ?: '-')
                                ->replace('_', ' ')
                                ->title()
                                ->toString(),
                        }
                    )
                    ->color(
                        fn (?string $state): string => match ($state) {
                            'invitation',
                            'invitation_card' => 'primary',

                            'welcome_checkin',
                            'welcome_sms',
                            'welcome' => 'success',

                            'rsvp_pending_reminder',
                            'rsvp_reminder',
                            'attending_reminder',
                            'event_day_reminder',
                            'event_reminder',
                            'final_reminder' => 'warning',

                            'thank_you' => 'info',

                            default => 'gray',
                        }
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'not_sent' => 'Not Sent',
                            'queued' => 'Queued',
                            'pending' => 'Pending',
                            'accepted' => 'Accepted',
                            'sent' => 'Sent',
                            'delivered' => 'Delivered',
                            'read' => 'Read',
                            'logged' => 'Logged',
                            'failed' => 'Failed',
                            'rejected' => 'Rejected',
                            default => str($state ?: 'unknown')
                                ->replace('_', ' ')
                                ->title()
                                ->toString(),
                        }
                    )
                    ->color(
                        fn (?string $state): string => match ($state) {
                            'delivered',
                            'read' => 'success',

                            'sent',
                            'accepted',
                            'logged' => 'info',

                            'queued',
                            'pending',
                            'not_sent' => 'warning',

                            'failed',
                            'rejected' => 'danger',

                            default => 'gray',
                        }
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('provider')
                    ->label('Provider')
                    ->placeholder('-')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('provider_message_id')
                    ->label('Provider ID')
                    ->copyable()
                    ->copyMessage('Provider message ID copied')
                    ->placeholder('-')
                    ->limit(24)
                    ->tooltip(
                        fn (MessageLog $record): ?string =>
                            $record->provider_message_id
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('message')
                    ->label('Message')
                    ->limit(65)
                    ->wrap()
                    ->tooltip(
                        fn (MessageLog $record): ?string =>
                            $record->message
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(45)
                    ->wrap()
                    ->color('danger')
                    ->placeholder('-')
                    ->tooltip(
                        fn (MessageLog $record): ?string =>
                            $record->error_message
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent At')
                    ->dateTime('d M Y H:i:s')
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('delivered_at')
                    ->label('Delivered At')
                    ->dateTime('d M Y H:i:s')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('read_at')
                    ->label('Read At')
                    ->dateTime('d M Y H:i:s')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('channel')
                    ->label('Channel')
                    ->options([
                        'sms' => 'SMS',
                        'whatsapp' => 'WhatsApp',
                    ]),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Message Type')
                    ->options([
                        'invitation' => 'Invitation',
                        'invitation_card' => 'Invitation Card',
                        'rsvp_pending_reminder' => 'RSVP Pending Reminder',
                        'rsvp_reminder' => 'RSVP Reminder',
                        'attending_reminder' => 'Attending Reminder',
                        'event_day_reminder' => 'Event Day Reminder',
                        'event_reminder' => 'Event Reminder',
                        'final_reminder' => 'Final Reminder',
                        'welcome_checkin' => 'Welcome After Check-in',
                        'thank_you' => 'Thank You',
                        'custom' => 'Custom',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Delivery Status')
                    ->options([
                        'not_sent' => 'Not Sent',
                        'queued' => 'Queued',
                        'pending' => 'Pending',
                        'accepted' => 'Accepted',
                        'logged' => 'Logged',
                        'sent' => 'Sent',
                        'delivered' => 'Delivered',
                        'read' => 'Read',
                        'failed' => 'Failed',
                        'rejected' => 'Rejected',
                    ]),

                Tables\Filters\SelectFilter::make('provider')
                    ->label('Provider')
                    ->options(fn (): array => MessageLog::query()
                        ->whereNotNull('provider')
                        ->where('provider', '!=', '')
                        ->distinct()
                        ->orderBy('provider')
                        ->pluck('provider', 'provider')
                        ->all()),

                Tables\Filters\Filter::make('failed_only')
                    ->label('Failed Messages')
                    ->query(
                        fn (Builder $query): Builder =>
                            $query->whereIn('status', ['failed', 'rejected'])
                    ),

                Tables\Filters\Filter::make('delivered_only')
                    ->label('Delivered Messages')
                    ->query(
                        fn (Builder $query): Builder =>
                            $query->whereIn('status', ['delivered', 'read'])
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('View')
                    ->icon('heroicon-o-eye'),

                Tables\Actions\Action::make('show_message')
                    ->label('Message')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->modalHeading(
                        fn (MessageLog $record): string =>
                            'Message to ' . ($record->invitee?->name ?: $record->phone)
                    )
                    ->modalContent(
                        fn (MessageLog $record) => view(
                            'filament.components.message-log-content',
                            ['record' => $record]
                        )
                    )
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->poll('15s');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    protected function canCreate(): bool
    {
        return false;
    }

    protected function canEdit($record): bool
    {
        return false;
    }

    protected function canDelete($record): bool
    {
        return false;
    }
}