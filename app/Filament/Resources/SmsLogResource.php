<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SmsLogResource\Pages;
use App\Models\SmsLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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
                            ->label('System Status')
                            ->helperText('This is the actual provider status saved in the log.')
                            ->disabled(),

                        Forms\Components\TextInput::make('provider')
                            ->label('Provider')
                            ->disabled(),

                        Forms\Components\TextInput::make('provider_status')
                            ->label('Provider Status')
                            ->disabled(),

                        Forms\Components\TextInput::make('provider_message_id')
                            ->label('Shoot ID / Provider Message ID')
                            ->helperText('This ID is used to fetch the delivery report from the SMS provider.')
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
                            ->label('Provider Response / Delivery Report')
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
                        default => $state ? Str::of($state)->replace('_', ' ')->title()->toString() : 'Unknown',
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
                    ->formatStateUsing(fn (?string $state): string => SmsLog::sources()[$state] ?? Str::of((string) $state)->replace('_', ' ')->title()->toString())
                    ->color(fn (?string $state): string => match ($state) {
                        SmsLog::SOURCE_AUTOMATIC => 'success',
                        SmsLog::SOURCE_BULK_MANUAL => 'warning',
                        SmsLog::SOURCE_REMINDER_PAGE => 'info',
                        SmsLog::SOURCE_INVITEE_ACTION => 'primary',
                        SmsLog::SOURCE_MANUAL => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Provider Status')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (?string $state): string => self::formatStatus($state))
                    ->color(fn (?string $state): string => self::statusColor($state))
                    ->description(fn (SmsLog $record): ?string => $record->provider_status
                        ? 'Provider: ' . self::formatStatus($record->provider_status)
                        : null),

                Tables\Columns\TextColumn::make('provider_message_id')
                    ->label('Shoot ID')
                    ->searchable()
                    ->copyable()
                    ->placeholder('No shoot ID')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('delivered_at')
                    ->label('Delivered At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('failed_at')
                    ->label('Failed At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('sentBy.name')
                    ->label('Sent By')
                    ->placeholder('System')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('batch_id')
                    ->label('Batch ID')
                    ->copyable()
                    ->limit(8)
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    ->label('Provider Status')
                    ->options(self::statusOptions()),
            ])
            ->actions([
                Tables\Actions\Action::make('refreshDelivery')
                    ->label('Get Delivery Report')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (SmsLog $record): bool => filled($record->provider_message_id))
                    ->requiresConfirmation()
                    ->modalHeading('Get actual SMS delivery report')
                    ->modalDescription('This will call the SMS provider delivery report API using the Shoot ID / Provider Message ID saved on this log.')
                    ->action(fn (SmsLog $record) => self::refreshDeliveryReport($record)),

                Tables\Actions\ViewAction::make()
                    ->label('View'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('refreshDeliveryReports')
                        ->label('Get Delivery Reports')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Refresh selected delivery reports')
                        ->modalDescription('This will call the SMS provider delivery report API for all selected logs that have Shoot ID / Provider Message ID.')
                        ->action(function ($records): void {
                            $checked = 0;

                            foreach ($records as $record) {
                                if (! filled($record->provider_message_id)) {
                                    continue;
                                }

                                self::refreshDeliveryReport($record, notify: false);
                                $checked++;
                            }

                            Notification::make()
                                ->title('Delivery reports refreshed')
                                ->body("Checked {$checked} selected SMS log(s).")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function refreshDeliveryReport(SmsLog $record, bool $notify = true): void
    {
        if (! filled($record->provider_message_id)) {
            if ($notify) {
                Notification::make()
                    ->title('No Shoot ID found')
                    ->body('This SMS log does not have provider_message_id / shootId.')
                    ->warning()
                    ->send();
            }

            return;
        }

        $baseUrl = rtrim((string) config('services.elive_sms.base_url'), '/');
        $apiKey = config('services.elive_sms.api_key');
        $apiSecret = config('services.elive_sms.api_secret');

        if (! $baseUrl || ! $apiKey || ! $apiSecret) {
            if ($notify) {
                Notification::make()
                    ->title('SMS provider config missing')
                    ->body('Set ELIVE_SMS_BASE_URL, ELIVE_SMS_API_KEY, and ELIVE_SMS_API_SECRET in .env.')
                    ->danger()
                    ->send();
            }

            return;
        }

        $url = $baseUrl . '/delivery/' . $record->provider_message_id;

        $response = Http::withHeaders([
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'Accept' => 'application/json',
        ])->timeout(30)->get($url);

        $payload = $response->json();

        if (! $response->successful()) {
            $record->update([
                'provider_status' => 'report_failed',
                'provider_response' => $payload,
                'error_message' => data_get($payload, 'message', 'Failed to fetch SMS delivery report.'),
            ]);

            if ($notify) {
                Notification::make()
                    ->title('Delivery report failed')
                    ->body($record->error_message)
                    ->danger()
                    ->send();
            }

            return;
        }

        $reports = collect(data_get($payload, 'data', []));

        $report = $reports->firstWhere('mobile', $record->phone)
            ?? $reports->firstWhere('phone', $record->phone)
            ?? $reports->first();

        if (! $report) {
            $record->update([
                'provider_status' => 'unknown',
                'provider_response' => $payload,
                'error_message' => data_get($payload, 'message', 'Delivery report returned no data.'),
            ]);

            if ($notify) {
                Notification::make()
                    ->title('No delivery data found')
                    ->body('The provider returned a successful response, but no report data was found for this SMS.')
                    ->warning()
                    ->send();
            }

            return;
        }

        $providerStatus = (string) data_get($report, 'status', 'unknown');
        $status = self::normalizeProviderStatus($providerStatus);
        $sentAt = self::parseProviderDate(data_get($report, 'sentAt'));

        $updates = [
            'status' => $status,
            'provider_status' => $providerStatus,
            'provider_response' => $payload,
            'error_message' => data_get($report, 'explanation'),
        ];

        if ($sentAt && blank($record->sent_at)) {
            $updates['sent_at'] = $sentAt;
        }

        if ($status === 'delivered') {
            $updates['delivered_at'] = now();
            $updates['failed_at'] = null;
        }

        if (in_array($status, ['failed', 'undelivered', 'expired', 'rejected'], true)) {
            $updates['failed_at'] = now();
            $updates['delivered_at'] = null;
            $updates['error_message'] = data_get($report, 'explanation') ?: 'SMS was not delivered.';
        }

        $record->update($updates);

        if ($notify) {
            Notification::make()
                ->title('Delivery report updated')
                ->body('Provider status: ' . self::formatStatus($providerStatus))
                ->success()
                ->send();
        }
    }

    protected static function normalizeProviderStatus(?string $status): string
    {
        $status = Str::of((string) $status)
            ->lower()
            ->replace(' ', '_')
            ->replace('-', '_')
            ->toString();

        return match ($status) {
            'delivered', 'delivery_success', 'success' => 'delivered',
            'sent', 'sender_id', 'accepted', 'submitted' => 'sent',
            'queued', 'pending' => 'pending',
            'sending', 'processing' => 'sending',
            'failed', 'failure', 'error' => 'failed',
            'undelivered', 'not_delivered' => 'undelivered',
            'expired' => 'expired',
            'rejected', 'blocked' => 'rejected',
            default => 'unknown',
        };
    }

    protected static function parseProviderDate(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function statusOptions(): array
    {
        return [
            'pending' => 'Pending',
            'queued' => 'Queued',
            'sending' => 'Sending',
            'sent' => 'Sent',
            'delivered' => 'Delivered',
            'failed' => 'Failed',
            'undelivered' => 'Undelivered',
            'expired' => 'Expired',
            'rejected' => 'Rejected',
            'unknown' => 'Unknown',
        ];
    }

    protected static function formatStatus(?string $state): string
    {
        return $state
            ? Str::of($state)->replace('_', ' ')->replace('-', ' ')->title()->toString()
            : 'Unknown';
    }

    protected static function statusColor(?string $state): string
    {
        $state = self::normalizeProviderStatus($state);

        return match ($state) {
            'delivered' => 'success',
            'sent' => 'info',
            'queued', 'pending', 'sending' => 'warning',
            'failed', 'undelivered', 'expired', 'rejected' => 'danger',
            default => 'gray',
        };
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
