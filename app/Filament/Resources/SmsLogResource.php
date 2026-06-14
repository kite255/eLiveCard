<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SmsLogResource\Pages;
use App\Models\SmsLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SmsLogResource extends Resource
{
    protected static ?string $model = SmsLog::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Communication';

    protected static ?string $navigationLabel = 'SMS Logs';

    protected static ?string $modelLabel = 'SMS Log';

    protected static ?string $pluralModelLabel = 'SMS Logs';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('SMS Details')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->relationship('event', 'title')
                            ->label('Event')
                            ->disabled(),

                        Forms\Components\Select::make('invitee_id')
                            ->relationship('invitee', 'name')
                            ->label('Invitee')
                            ->disabled(),

                        Forms\Components\TextInput::make('phone')
                            ->label('Phone')
                            ->disabled(),

                        Forms\Components\TextInput::make('sms_type')
                            ->label('SMS Type')
                            ->disabled(),

                        Forms\Components\TextInput::make('send_source')
                            ->label('Source')
                            ->disabled(),

                        Forms\Components\Select::make('sent_by_user_id')
                            ->relationship('sentBy', 'name')
                            ->label('Sent By')
                            ->disabled(),

                        Forms\Components\TextInput::make('batch_id')
                            ->label('Batch ID')
                            ->disabled(),

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

                        Forms\Components\DateTimePicker::make('failed_at')
                            ->label('Failed At')
                            ->disabled(),

                        Forms\Components\Textarea::make('message')
                            ->label('Message')
                            ->rows(4)
                            ->columnSpanFull()
                            ->disabled(),

                        Forms\Components\Textarea::make('error_message')
                            ->label('Error Message')
                            ->rows(4)
                            ->columnSpanFull()
                            ->disabled(),

                        Forms\Components\KeyValue::make('provider_response')
                            ->label('Provider Response')
                            ->columnSpanFull()
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->placeholder('No event'),

                Tables\Columns\TextColumn::make('invitee.name')
                    ->label('Invitee')
                    ->searchable()
                    ->sortable()
                    ->placeholder('No invitee'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('sms_type')
                    ->label('SMS Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        SmsLog::TYPE_INVITATION => 'Invitation',
                        SmsLog::TYPE_RSVP_PENDING_REMINDER => 'RSVP Reminder',
                        SmsLog::TYPE_ATTENDING_REMINDER => 'One Day Before',
                        SmsLog::TYPE_EVENT_DAY_REMINDER => 'Event Day',
                        default => $state ? ucfirst(str_replace('_', ' ', $state)) : 'Unknown',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        SmsLog::TYPE_INVITATION => 'primary',
                        SmsLog::TYPE_RSVP_PENDING_REMINDER => 'warning',
                        SmsLog::TYPE_ATTENDING_REMINDER => 'info',
                        SmsLog::TYPE_EVENT_DAY_REMINDER => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('send_source')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => SmsLog::sources()[$state] ?? ucfirst((string) $state))
                    ->color(fn (?string $state): string => match ($state) {
                        SmsLog::SOURCE_AUTOMATIC => 'success',
                        SmsLog::SOURCE_BULK_MANUAL => 'warning',
                        SmsLog::SOURCE_REMINDER_PAGE => 'info',
                        SmsLog::SOURCE_INVITEE_ACTION => 'primary',
                        SmsLog::SOURCE_MANUAL => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('sentBy.name')
                    ->label('Sent By')
                    ->placeholder('System')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('batch_id')
                    ->label('Batch ID')
                    ->copyable()
                    ->limit(8)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable()
                    ->color(fn (?string $state): string => match ($state) {
                        SmsLog::STATUS_SENT => 'success',
                        SmsLog::STATUS_DELIVERED => 'success',
                        SmsLog::STATUS_FAILED => 'danger',
                        SmsLog::STATUS_PENDING => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('provider_message_id')
                    ->label('Message ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(35)
                    ->tooltip(fn (SmsLog $record): ?string => $record->error_message)
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('message')
                    ->label('Message')
                    ->limit(60)
                    ->tooltip(fn (SmsLog $record): ?string => $record->message)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Event')
                    ->relationship('event', 'title')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('sms_type')
                    ->label('SMS Type')
                    ->options([
                        SmsLog::TYPE_INVITATION => 'Invitation SMS',
                        SmsLog::TYPE_RSVP_PENDING_REMINDER => 'RSVP Pending Reminder',
                        SmsLog::TYPE_ATTENDING_REMINDER => 'One Day Before Reminder',
                        SmsLog::TYPE_EVENT_DAY_REMINDER => 'Event Day Reminder',
                    ]),

                Tables\Filters\SelectFilter::make('send_source')
                    ->label('Source')
                    ->options(SmsLog::sources()),

                Tables\Filters\SelectFilter::make('sent_by_user_id')
                    ->label('Sent By')
                    ->relationship('sentBy', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        SmsLog::STATUS_PENDING => 'Pending',
                        SmsLog::STATUS_SENT => 'Sent',
                        SmsLog::STATUS_DELIVERED => 'Delivered',
                        SmsLog::STATUS_FAILED => 'Failed',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('View'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSmsLogs::route('/'),
            'view' => Pages\ViewSmsLog::route('/{record}'),
        ];
    }
}
