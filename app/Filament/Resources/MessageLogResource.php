<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MessageLogResource\Pages;
use App\Models\MessageLog;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MessageLogResource extends Resource
{
    protected static ?string $model = MessageLog::class;

    /**
     * Message logs are viewed inside the Event workspace.
     * They should not appear as a separate sidebar item.
     */
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Message Logs';

    protected static ?string $modelLabel = 'Message Log';

    protected static ?string $pluralModelLabel = 'Message Logs';

    protected static ?string $recordTitleAttribute = 'recipient';

    protected static ?int $navigationSort = 2;

    /**
     * Allow authorized users to view message logs.
     */
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return ($user?->isSuperAdmin() ?? false)
            || ($user?->isEventOwner() ?? false)
            || ($user?->isEventManager() ?? false)
            || ($user?->isMessageSender() ?? false)
            || ($user?->isReportViewer() ?? false);
    }

    /**
     * Message logs should only be created automatically
     * when SMS or WhatsApp messages are sent.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Message logs should remain immutable for auditing.
     */
    public static function canEdit($record): bool
    {
        return false;
    }

    /**
     * Only Super Admin can delete a message log.
     */
    public static function canDelete($record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    /**
     * Logs are read-only, so no editable form is required.
     */
    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $user = auth()->user();

                if (! $user) {
                    return $query->whereRaw('1 = 0');
                }

                if ($user->isSuperAdmin()) {
                    return $query;
                }

                /*
                 * Apply event ownership filtering when the users table
                 * and Event model use owner_id or user_id.
                 *
                 * Adjust this relationship condition if your Event model
                 * uses a different ownership field.
                 */
                if (
                    $user->isEventOwner()
                    || $user->isEventManager()
                    || $user->isMessageSender()
                    || $user->isReportViewer()
                ) {
                    return $query->whereHas(
                        'event',
                        fn (Builder $eventQuery): Builder =>
                            $eventQuery->where('user_id', $user->id)
                    );
                }

                return $query->whereRaw('1 = 0');
            })
            ->columns([
                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->placeholder('No event')
                    ->wrap(),

                Tables\Columns\TextColumn::make('invitee.name')
                    ->label('Invitee')
                    ->searchable()
                    ->sortable()
                    ->placeholder('No invitee')
                    ->wrap(),

                Tables\Columns\TextColumn::make('recipient')
                    ->label('Recipient')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Recipient copied')
                    ->placeholder('Not available'),

                Tables\Columns\TextColumn::make('channel')
                    ->label('Channel')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            match (strtolower((string) $state)) {
                                'whatsapp' => 'WhatsApp',
                                'sms' => 'SMS',
                                default => ucfirst((string) $state),
                            }
                    )
                    ->color(
                        fn (?string $state): string =>
                            match (strtolower((string) $state)) {
                                'whatsapp' => 'success',
                                'sms' => 'primary',
                                default => 'gray',
                            }
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Message Type')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            str($state ?: 'custom')
                                ->replace('_', ' ')
                                ->title()
                                ->toString()
                    )
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('message')
                    ->label('Message')
                    ->limit(80)
                    ->wrap()
                    ->tooltip(
                        fn ($record): ?string =>
                            filled($record->message)
                                ? $record->message
                                : null
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            str($state ?: 'unknown')
                                ->replace('_', ' ')
                                ->title()
                                ->toString()
                    )
                    ->color(
                        fn (?string $state): string =>
                            match (strtolower((string) $state)) {
                                'sent',
                                'accepted',
                                'delivered',
                                'read',
                                'success' => 'success',

                                'pending',
                                'queued',
                                'processing' => 'warning',

                                'failed',
                                'rejected',
                                'undelivered',
                                'error' => 'danger',

                                default => 'gray',
                            }
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('provider_message_id')
                    ->label('Provider ID')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Provider ID copied')
                    ->limit(30)
                    ->placeholder('Not available')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(60)
                    ->wrap()
                    ->color('danger')
                    ->placeholder('No error')
                    ->tooltip(
                        fn ($record): ?string =>
                            filled($record->error_message)
                                ? $record->error_message
                                : null
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent At')
                    ->dateTime('d M Y, H:i:s')
                    ->sortable()
                    ->placeholder('Not sent'),

                Tables\Columns\TextColumn::make('delivered_at')
                    ->label('Delivered At')
                    ->dateTime('d M Y, H:i:s')
                    ->sortable()
                    ->placeholder('Not delivered')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('read_at')
                    ->label('Read At')
                    ->dateTime('d M Y, H:i:s')
                    ->sortable()
                    ->placeholder('Not read')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y, H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('d M Y, H:i:s')
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
                        'welcome' => 'Welcome',
                        'thank_you' => 'Thank You',
                        'custom' => 'Custom',
                    ])
                    ->native(false),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'queued' => 'Queued',
                        'processing' => 'Processing',
                        'accepted' => 'Accepted',
                        'sent' => 'Sent',
                        'delivered' => 'Delivered',
                        'read' => 'Read',
                        'failed' => 'Failed',
                        'rejected' => 'Rejected',
                        'undelivered' => 'Undelivered',
                    ])
                    ->native(false),

                Tables\Filters\Filter::make('failed_messages')
                    ->label('Failed only')
                    ->query(
                        fn (Builder $query): Builder =>
                            $query->whereIn('status', [
                                'failed',
                                'rejected',
                                'undelivered',
                                'error',
                            ])
                    ),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label('From date'),

                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label('Until date'),
                    ])
                    ->query(
                        function (Builder $query, array $data): Builder {
                            return $query
                                ->when(
                                    $data['from'] ?? null,
                                    fn (Builder $query, $date): Builder =>
                                        $query->whereDate(
                                            'created_at',
                                            '>=',
                                            $date
                                        )
                                )
                                ->when(
                                    $data['until'] ?? null,
                                    fn (Builder $query, $date): Builder =>
                                        $query->whereDate(
                                            'created_at',
                                            '<=',
                                            $date
                                        )
                                );
                        }
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Message Log Details')
                    ->modalContent(
                        fn ($record) =>
                            view(
                                'components.message-log-content',
                                ['record' => $record]
                            )
                    ),

                Tables\Actions\DeleteAction::make()
                    ->visible(
                        fn (): bool =>
                            auth()->user()?->isSuperAdmin() ?? false
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(
                            fn (): bool =>
                                auth()->user()?->isSuperAdmin() ?? false
                        ),
                ]),
            ])
            ->emptyStateHeading('No message logs yet')
            ->emptyStateDescription(
                'SMS and WhatsApp activity will appear here after messages are sent.'
            )
            ->emptyStateIcon('heroicon-o-document-text')
            ->poll('30s');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMessageLogs::route('/'),
        ];
    }
}