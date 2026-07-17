<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\SmsLog;
use App\Services\AuditLogService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SmsLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'smsLogs';

    protected static ?string $title = 'SMS Logs';

    protected static ?string $modelLabel = 'SMS Log';

    protected static ?string $pluralModelLabel = 'SMS Logs';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        if (
            method_exists($user, 'isEventAdmin')
            && $user->isEventAdmin()
            && (int) ($ownerRecord->user_id ?? 0) === (int) $user->id
        ) {
            return true;
        }

        if (method_exists($user, 'canAccessEvent')) {
            return (bool) $user->canAccessEvent($ownerRecord);
        }

        return $user->can('view', $ownerRecord);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('SMS Details')
                    ->schema([
                        Forms\Components\TextInput::make('invitee.name')
                            ->label('Invitee')
                            ->disabled(),

                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->disabled(),

                        Forms\Components\TextInput::make('sms_type')
                            ->label('SMS Type')
                            ->disabled(),

                        Forms\Components\TextInput::make('status')
                            ->label('Status')
                            ->disabled(),

                        Forms\Components\TextInput::make('provider_status')
                            ->label('Provider Status')
                            ->disabled(),

                        Forms\Components\TextInput::make('send_source')
                            ->label('Send Source')
                            ->disabled(),

                        Forms\Components\TextInput::make('provider_message_id')
                            ->label('Provider Message ID')
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('message')
                            ->label('Message')
                            ->rows(6)
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('provider_response')
                            ->label('Provider Response')
                            ->rows(5)
                            ->disabled()
                            ->visible(
                                fn (?SmsLog $record): bool =>
                                    filled($record?->provider_response)
                            )
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('error_message')
                            ->label('Error Message')
                            ->rows(4)
                            ->disabled()
                            ->visible(
                                fn (?SmsLog $record): bool =>
                                    filled($record?->error_message)
                            )
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('SMS Delivery Logs')
            ->description('Review invitation, reminder, welcome, and other SMS delivery records for this event.')
            ->recordTitleAttribute('phone')
            ->searchPlaceholder('Search invitee, phone, provider ID, message, status, or error')
            ->searchDebounce('500ms')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query
                    ->with('invitee')
                    ->latest('created_at')
            )
            ->emptyStateHeading('No SMS logs yet')
            ->emptyStateDescription(
                'Invitation, reminder, welcome and other SMS records for this event will appear here.'
            )
            ->emptyStateIcon('heroicon-o-device-phone-mobile')
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

                Tables\Columns\TextColumn::make('sms_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'invitation',
                            'invitation_sms',
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
                            'welcome_checkin',
                            'welcome_sms',
                            'welcome' => 'success',

                            'invitation',
                            'invitation_sms',
                            'invitation_card' => 'primary',

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
                            'sent',
                            'delivered' => 'success',

                            'accepted' => 'info',

                            'queued',
                            'pending',
                            'not_sent' => 'warning',

                            'failed',
                            'rejected' => 'danger',

                            default => 'gray',
                        }
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('provider_status')
                    ->label('Provider Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => str($state ?: '-')
                            ->replace('_', ' ')
                            ->title()
                            ->toString()
                    )
                    ->color(
                        fn (?string $state): string => match (strtolower((string) $state)) {
                            'delivered', 'success', 'operator submitted' => 'success',
                            'accepted', 'submitted' => 'info',
                            'queued', 'pending', 'processing' => 'warning',
                            'failed', 'rejected', 'undelivered' => 'danger',
                            default => 'gray',
                        }
                    )
                    ->placeholder('-')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('message')
                    ->label('Message')
                    ->limit(70)
                    ->wrap()
                    ->tooltip(
                        fn (SmsLog $record): ?string => $record->message
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('provider_message_id')
                    ->label('Provider ID')
                    ->copyable()
                    ->copyMessage('Provider message ID copied')
                    ->limit(24)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(45)
                    ->wrap()
                    ->color('danger')
                    ->placeholder('-')
                    ->tooltip(
                        fn (SmsLog $record): ?string =>
                            $record->error_message
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('send_source')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => str($state ?: '-')
                            ->replace('_', ' ')
                            ->title()
                            ->toString()
                    )
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('batch_id')
                    ->label('Batch ID')
                    ->copyable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent At')
                    ->dateTime('d M Y H:i:s')
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('sms_type')
                    ->label('SMS Type')
                    ->options([
                        'invitation' => 'Invitation',
                        'invitation_sms' => 'Invitation SMS',
                        'rsvp_pending_reminder' => 'RSVP Pending Reminder',
                        'rsvp_reminder' => 'RSVP Reminder',
                        'attending_reminder' => 'Attending Reminder',
                        'event_day_reminder' => 'Event Day Reminder',
                        'event_reminder' => 'Event Reminder',
                        'final_reminder' => 'Final Reminder',
                        'welcome_checkin' => 'Welcome After Check-in',
                        'welcome_sms' => 'Welcome SMS',
                        'thank_you' => 'Thank You',
                        'custom' => 'Custom',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'not_sent' => 'Not Sent',
                        'queued' => 'Queued',
                        'pending' => 'Pending',
                        'accepted' => 'Accepted',
                        'sent' => 'Sent',
                        'delivered' => 'Delivered',
                        'failed' => 'Failed',
                        'rejected' => 'Rejected',
                    ]),

                Tables\Filters\Filter::make('successful')
                    ->label('Successful')
                    ->query(
                        fn (Builder $query): Builder =>
                            $query->whereIn('status', [
                                'accepted',
                                'sent',
                                'delivered',
                            ])
                    ),

                Tables\Filters\Filter::make('failed')
                    ->label('Failed')
                    ->query(
                        fn (Builder $query): Builder =>
                            $query->whereIn('status', [
                                'failed',
                                'rejected',
                            ])
                    ),

                Tables\Filters\Filter::make('today')
                    ->label('Today')
                    ->query(
                        fn (Builder $query): Builder =>
                            $query->whereDate('created_at', today())
                    ),

                Tables\Filters\Filter::make('date_range')
                    ->label('Date Range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
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
                    }),
            ])
            ->filtersFormColumns(3)
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (SmsLog $record): string => 'SMS Log: '.($record->phone ?: 'Unknown'))
                    ->after(function (SmsLog $record): void {
                        AuditLogService::record(
                            action: 'sms_log.viewed',
                            subject: $record,
                            eventId: $record->event_id,
                            description: 'SMS delivery log was viewed.',
                            metadata: [
                                'invitee_id' => $record->invitee_id,
                                'sms_type' => $record->sms_type,
                                'status' => $record->status,
                                'provider_status' => $record->provider_status,
                                'provider_message_id' => $record->provider_message_id,
                            ],
                        );
                    }),
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