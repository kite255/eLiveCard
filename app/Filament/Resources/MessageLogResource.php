<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MessageLogResource\Pages;
use App\Models\Event;
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

    public static function canViewAny(): bool
    {
        return auth()->user()?->canViewReports() ?? false;
    }

    public static function canView($record): bool
    {
        return static::canAccessRecord($record);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return (auth()->user()?->isSuperAdmin() ?? false)
            && static::canAccessRecord($record);
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return static::scopeQueryToUser(parent::getEloquentQuery());
    }

    protected static function scopeQueryToUser(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isEventAdmin()) {
            return $query->whereHas('event', function (Builder $eventQuery) use ($user): void {
                $eventQuery->where('user_id', $user->id);
            });
        }

        return $query->whereRaw('1 = 0');
    }

    protected static function canAccessRecord(?MessageLog $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isEventAdmin()) {
            $record->loadMissing('event');

            return (int) ($record->event?->user_id) === (int) $user->id;
        }

        return false;
    }

    protected static function visibleEventOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        return Event::query()
            ->when(
                $user->isEventAdmin(),
                fn (Builder $query): Builder => $query->where('user_id', $user->id)
            )
            ->when(
                $user->isCheckInOfficer(),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0')
            )
            ->orderBy('title')
            ->pluck('title', 'id')
            ->toArray();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query
                    ->with(['event', 'invitee'])
                    ->latest('created_at')
            )
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
                    ->options(fn (): array => static::visibleEventOptions())
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
                        'invitation_card' => 'Invitation Card',
                        'rsvp_reminder' => 'RSVP Reminder',
                        'rsvp_pending_reminder' => 'RSVP Pending Reminder',
                        'one_day_reminder' => 'One Day Before',
                        'attending_reminder' => 'Attending Reminder',
                        'event_day_reminder' => 'Event Day',
                        'welcome' => 'Welcome',
                        'welcome_sms' => 'Welcome SMS',
                        'welcome_checkin' => 'Welcome Check-in',
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
                        'logged' => 'Logged',
                        'failed' => 'Failed',
                        'rejected' => 'Rejected',
                        'undelivered' => 'Undelivered',
                        'error' => 'Error',
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
                                        $query->whereDate('created_at', '>=', $date)
                                )
                                ->when(
                                    $data['until'] ?? null,
                                    fn (Builder $query, $date): Builder =>
                                        $query->whereDate('created_at', '<=', $date)
                                );
                        }
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Message Log Details')
                    ->visible(fn (MessageLog $record): bool => static::canAccessRecord($record))
                    ->modalContent(
                        fn ($record) =>
                            view('components.message-log-content', [
                                'record' => $record,
                            ])
                    ),

                Tables\Actions\DeleteAction::make()
                    ->visible(
                        fn (MessageLog $record): bool =>
                            (auth()->user()?->isSuperAdmin() ?? false)
                            && static::canAccessRecord($record)
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