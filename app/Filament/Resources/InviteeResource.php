<?php

namespace App\Filament\Resources;

use App\Exports\InviteesExport;
use App\Exports\AttendanceExport;
use App\Jobs\GenerateInviteeCardJob;
use App\Filament\Resources\InviteeResource\Pages;
use App\Models\Invitee;
use App\Models\GeneratedCard;
use App\Services\ReminderSmsService;
use App\Services\SmsService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class InviteeResource extends Resource
{
    protected static ?string $model = Invitee::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Invitees';

    protected static ?string $modelLabel = 'Invitee';

    protected static ?string $pluralModelLabel = 'Invitees';

    protected static ?string $navigationGroup = 'Event Management';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'event',
                'cardType',
                'latestGeneratedCard',
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Invitee Details')
                    ->description('Add invitee details and assign the correct card type.')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->label('Event')
                            ->relationship('event', 'title')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('card_type_id')
                            ->label('Card Type')
                            ->relationship('cardType', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('The selected card type determines the default number of people allowed.'),

                        Forms\Components\TextInput::make('name')
                            ->label('Full Name')
                            ->required()
                            ->unique(
                                table: 'invitees',
                                column: 'name',
                                ignoreRecord: true,
                                modifyRuleUsing: fn ($rule, $get) => $rule->where('event_id', $get('event_id'))
                            )
                            ->validationMessages([
                                'unique' => 'This invitee name already exists in this event.',
                            ])
                            ->maxLength(255)
                            ->helperText('Invitee name must not repeat in the same event.'),

                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->required()
                            ->maxLength(20)
                            ->placeholder('Example: 255745939140')
                            ->helperText('Phone number is required. The same phone number can be used by more than one invitee.'),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('category')
                            ->label('Category')
                            ->maxLength(255)
                            ->placeholder('Example: Bride Family, Groom Friends, VIP'),

                        Forms\Components\TextInput::make('table_number')
                            ->label('Table Number')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('allowed_guests')
                            ->label('Custom Allowed Guests')
                            ->helperText('Leave empty to use the number of people defined in the selected card type.')
                            ->numeric()
                            ->minValue(1),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('RSVP Information')
                    ->description('RSVP confirmation status from the invitee.')
                    ->schema([
                        Forms\Components\TextInput::make('rsvp_status')
                            ->label('RSVP Status')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('confirmed_guests')
                            ->label('Confirmed Guests')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('rsvp_confirmed_at')
                            ->label('RSVP Confirmed At')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('rsvp_url')
                            ->label('RSVP Confirmation Link')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsed()
                    ->visibleOn('edit'),

                Forms\Components\Section::make('SMS Tracking')
                    ->description('Latest SMS and reminder tracking information.')
                    ->schema([
                        Forms\Components\TextInput::make('sms_status')
                            ->label('Latest SMS Status')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('sms_sent_at')
                            ->label('Latest SMS Sent At')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('sms_message_id')
                            ->label('Latest SMS Message ID')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('invitation_sms_status')
                            ->label('Invitation SMS Status')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('invitation_sms_sent_at')
                            ->label('Invitation SMS Sent At')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('reminder_sms_status')
                            ->label('Reminder SMS Status')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('reminder_sms_sent_at')
                            ->label('Reminder SMS Sent At')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('final_sms_status')
                            ->label('Final SMS Status')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('final_sms_sent_at')
                            ->label('Final SMS Sent At')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Textarea::make('sms_error')
                            ->label('SMS Error')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('last_sms_error')
                            ->label('Last Reminder SMS Error')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsed()
                    ->visibleOn('edit'),

                Forms\Components\Section::make('System Information')
                    ->description('These values are generated automatically by the system.')
                    ->schema([
                        Forms\Components\TextInput::make('serial_number')
                            ->label('Serial Number')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('short_code')
                            ->label('Short Code')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('private_invitation_url')
                            ->label('Private Invitation Link')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('qr_code_path')
                            ->label('QR Code Path')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('card_status')
                            ->label('Card Status')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('checked_in_count')
                            ->label('Checked-in Count')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('final_allowed_guests')
                            ->label('Allowed Guests')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('remaining_guests')
                            ->label('Remaining Guests')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2)
                    ->collapsed()
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Invitee')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('cardType.name')
                    ->label('Card Type')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('final_allowed_guests')
                    ->label('Allowed'),

                Tables\Columns\TextColumn::make('checked_in_count')
                    ->label('In')
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining_guests')
                    ->label('Remain'),

                Tables\Columns\TextColumn::make('serial_number')
                    ->label('Serial')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Serial number copied'),

                Tables\Columns\TextColumn::make('private_invitation_url')
                    ->label('Invitation Link')
                    ->copyable()
                    ->copyMessage('Private invitation link copied')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('rsvp_url')
                    ->label('RSVP Link')
                    ->copyable()
                    ->copyMessage('RSVP link copied')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('qr_code_path')
                    ->label('QR Path')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('rsvp_status')
                    ->label('RSVP')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Invitee::rsvpStatuses()[$state] ?? 'Pending')
                    ->colors([
                        'gray' => Invitee::RSVP_PENDING,
                        'success' => Invitee::RSVP_ATTENDING,
                        'danger' => Invitee::RSVP_NOT_ATTENDING,
                        'warning' => Invitee::RSVP_MAYBE,
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('confirmed_guests')
                    ->label('Confirmed')
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('rsvp_confirmed_at')
                    ->label('RSVP Date')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('sms_status')
                    ->label('SMS')
                    ->badge()
                    ->colors([
                        'gray' => Invitee::SMS_STATUS_NOT_SENT,
                        'warning' => Invitee::SMS_STATUS_PENDING,
                        'success' => Invitee::SMS_STATUS_SENT,
                        'danger' => Invitee::SMS_STATUS_FAILED,
                        'info' => Invitee::SMS_STATUS_DELIVERED,
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('invitation_sms_status')
                    ->label('Invitation SMS')
                    ->badge()
                    ->colors([
                        'gray' => Invitee::SMS_STATUS_PENDING,
                        'success' => Invitee::SMS_STATUS_SENT,
                        'danger' => Invitee::SMS_STATUS_FAILED,
                        'info' => Invitee::SMS_STATUS_DELIVERED,
                    ])
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('invitation_sms_sent_at')
                    ->label('Invitation Sent')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reminder_sms_status')
                    ->label('Reminder SMS')
                    ->badge()
                    ->colors([
                        'gray' => Invitee::SMS_STATUS_PENDING,
                        'success' => Invitee::SMS_STATUS_SENT,
                        'danger' => Invitee::SMS_STATUS_FAILED,
                        'info' => Invitee::SMS_STATUS_DELIVERED,
                    ])
                    ->toggleable(),

                Tables\Columns\TextColumn::make('final_sms_status')
                    ->label('Final SMS')
                    ->badge()
                    ->colors([
                        'gray' => Invitee::SMS_STATUS_PENDING,
                        'success' => Invitee::SMS_STATUS_SENT,
                        'danger' => Invitee::SMS_STATUS_FAILED,
                        'info' => Invitee::SMS_STATUS_DELIVERED,
                    ])
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('sms_sent_at')
                    ->label('SMS Sent')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('reminder_sms_sent_at')
                    ->label('Reminder Sent')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('sms_message_id')
                    ->label('SMS ID')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('last_sms_error')
                    ->label('Reminder Error')
                    ->limit(40)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('sms_error')
                    ->label('SMS Error')
                    ->limit(40)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('card_status')
                    ->label('Card')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        Invitee::CARD_STATUS_PENDING => 'Pending',
                        Invitee::CARD_STATUS_ACTIVE => 'Active',
                        Invitee::CARD_STATUS_CANCELLED => 'Cancelled',
                        Invitee::CARD_STATUS_BLOCKED => 'Blocked',
                        Invitee::CARD_STATUS_USED => 'Used',
                        default => ucfirst((string) $state),
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        Invitee::CARD_STATUS_ACTIVE => 'success',
                        Invitee::CARD_STATUS_PENDING => 'warning',
                        Invitee::CARD_STATUS_CANCELLED => 'danger',
                        Invitee::CARD_STATUS_BLOCKED => 'danger',
                        Invitee::CARD_STATUS_USED => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('latestGeneratedCard.status')
                    ->label('Card Gen')
                    ->badge()
                    ->default('not_generated')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        GeneratedCard::STATUS_PENDING => 'Pending',
                        GeneratedCard::STATUS_GENERATING => 'Generating',
                        GeneratedCard::STATUS_GENERATED => 'Generated',
                        GeneratedCard::STATUS_SENT => 'Sent',
                        GeneratedCard::STATUS_FAILED => 'Failed',
                        default => 'Not Generated',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        GeneratedCard::STATUS_PENDING => 'gray',
                        GeneratedCard::STATUS_GENERATING => 'warning',
                        GeneratedCard::STATUS_GENERATED => 'success',
                        GeneratedCard::STATUS_SENT => 'info',
                        GeneratedCard::STATUS_FAILED => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('latestGeneratedCard.generated_at')
                    ->label('Card Generated')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('latestGeneratedCard.status')
                    ->label('Card Gen')
                    ->badge()
                    ->default('not_generated')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        GeneratedCard::STATUS_PENDING => 'Pending',
                        GeneratedCard::STATUS_GENERATING => 'Generating',
                        GeneratedCard::STATUS_GENERATED => 'Generated',
                        GeneratedCard::STATUS_SENT => 'Sent',
                        GeneratedCard::STATUS_FAILED => 'Failed',
                        default => 'Not Generated',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        GeneratedCard::STATUS_PENDING => 'gray',
                        GeneratedCard::STATUS_GENERATING => 'warning',
                        GeneratedCard::STATUS_GENERATED => 'success',
                        GeneratedCard::STATUS_SENT => 'info',
                        GeneratedCard::STATUS_FAILED => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('latestGeneratedCard.generated_at')
                    ->label('Card Generated')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Event')
                    ->relationship('event', 'title')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('card_type_id')
                    ->label('Card Type')
                    ->relationship('cardType', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('rsvp_status')
                    ->label('RSVP Status')
                    ->options(Invitee::rsvpStatuses()),

                Tables\Filters\SelectFilter::make('sms_status')
                    ->label('SMS Status')
                    ->options([
                        Invitee::SMS_STATUS_NOT_SENT => 'Not Sent',
                        Invitee::SMS_STATUS_PENDING => 'Pending',
                        Invitee::SMS_STATUS_SENT => 'Sent',
                        Invitee::SMS_STATUS_FAILED => 'Failed',
                        Invitee::SMS_STATUS_DELIVERED => 'Delivered',
                    ]),

                Tables\Filters\SelectFilter::make('invitation_sms_status')
                    ->label('Invitation SMS Status')
                    ->options([
                        Invitee::SMS_STATUS_PENDING => 'Pending',
                        Invitee::SMS_STATUS_SENT => 'Sent',
                        Invitee::SMS_STATUS_FAILED => 'Failed',
                        Invitee::SMS_STATUS_DELIVERED => 'Delivered',
                    ]),

                Tables\Filters\SelectFilter::make('reminder_sms_status')
                    ->label('Reminder SMS Status')
                    ->options([
                        Invitee::SMS_STATUS_PENDING => 'Pending',
                        Invitee::SMS_STATUS_SENT => 'Sent',
                        Invitee::SMS_STATUS_FAILED => 'Failed',
                        Invitee::SMS_STATUS_DELIVERED => 'Delivered',
                    ]),

                Tables\Filters\SelectFilter::make('final_sms_status')
                    ->label('Final SMS Status')
                    ->options([
                        Invitee::SMS_STATUS_PENDING => 'Pending',
                        Invitee::SMS_STATUS_SENT => 'Sent',
                        Invitee::SMS_STATUS_FAILED => 'Failed',
                        Invitee::SMS_STATUS_DELIVERED => 'Delivered',
                    ]),

                Tables\Filters\SelectFilter::make('card_status')
                    ->label('Card Status')
                    ->options([
                        Invitee::CARD_STATUS_PENDING => 'Pending',
                        Invitee::CARD_STATUS_ACTIVE => 'Active',
                        Invitee::CARD_STATUS_USED => 'Used',
                        Invitee::CARD_STATUS_BLOCKED => 'Blocked',
                        Invitee::CARD_STATUS_CANCELLED => 'Cancelled',
                    ]),

                Tables\Filters\SelectFilter::make('generated_card_status')
                    ->label('Generated Card Status')
                    ->options([
                        'not_generated' => 'Not Generated',
                        GeneratedCard::STATUS_PENDING => 'Pending',
                        GeneratedCard::STATUS_GENERATING => 'Generating',
                        GeneratedCard::STATUS_GENERATED => 'Generated',
                        GeneratedCard::STATUS_SENT => 'Sent',
                        GeneratedCard::STATUS_FAILED => 'Failed',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        if ($value === 'not_generated') {
                            return $query->whereDoesntHave('latestGeneratedCard');
                        }

                        return $query->whereHas('latestGeneratedCard', fn (Builder $query): Builder => $query->where('status', $value));
                    }),

                Tables\Filters\SelectFilter::make('card_generation_status')
                    ->label('Card Generation')
                    ->options([
                        'not_generated' => 'Not Generated',
                        GeneratedCard::STATUS_PENDING => 'Pending',
                        GeneratedCard::STATUS_GENERATING => 'Generating',
                        GeneratedCard::STATUS_GENERATED => 'Generated',
                        GeneratedCard::STATUS_SENT => 'Sent',
                        GeneratedCard::STATUS_FAILED => 'Failed',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $status = $data['value'] ?? null;

                        if (blank($status)) {
                            return $query;
                        }

                        if ($status === 'not_generated') {
                            return $query->doesntHave('latestGeneratedCard');
                        }

                        return $query->whereHas('latestGeneratedCard', fn (Builder $cardQuery): Builder => $cardQuery->where('status', $status));
                    }),

            ])
            ->headerActions([
                Tables\Actions\Action::make('refresh_status')
                    ->label('Refresh Status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->action(fn (): null => null),

                Tables\Actions\Action::make('export_all_invitees')
                    ->label('Export Invitees Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('event_id')
                            ->label('Event')
                            ->relationship('event', 'title')
                            ->searchable()
                            ->preload()
                            ->helperText('Select an event to export invitees from one event only. Leave empty to export all invitees.'),
                    ])
                    ->action(function (array $data) {
                        $eventId = $data['event_id'] ?? null;

                        $fileName = $eventId
                            ? 'event-' . $eventId . '-invitees-' . now()->format('Y-m-d-His') . '.xlsx'
                            : 'all-invitees-' . now()->format('Y-m-d-His') . '.xlsx';

                        return Excel::download(
                            new InviteesExport($eventId),
                            $fileName
                        );
                    }),

                Tables\Actions\Action::make('export_attendance_report')
                    ->label('Export Attendance Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('event_id')
                            ->label('Event')
                            ->relationship('event', 'title')
                            ->searchable()
                            ->preload()
                            ->helperText('Select an event to export attendance from one event only. Leave empty to export all attendance.'),
                    ])
                    ->action(function (array $data) {
                        $eventId = $data['event_id'] ?? null;

                        $fileName = $eventId
                            ? 'event-' . $eventId . '-attendance-' . now()->format('Y-m-d-His') . '.xlsx'
                            : 'all-attendance-' . now()->format('Y-m-d-His') . '.xlsx';

                        return Excel::download(
                            new AttendanceExport($eventId),
                            $fileName
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('open_invitation_page')
                    ->label('Invitation')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (Invitee $record): string => $record->private_invitation_url, shouldOpenInNewTab: true),

                Tables\Actions\Action::make('open_rsvp_link')
                    ->label('RSVP')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->url(fn (Invitee $record): string => $record->rsvpUrl(), shouldOpenInNewTab: true),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('generate_card')
                        ->label(fn (Invitee $record): string => $record->latestGeneratedCard ? 'Regenerate Card' : 'Generate Card')
                        ->icon('heroicon-o-photo')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading(fn (Invitee $record): string => $record->latestGeneratedCard ? 'Regenerate invitation card' : 'Generate invitation card')
                        ->modalDescription('This will generate the personalized card in the background. Keep the queue worker running.')
                        ->modalSubmitActionLabel('Start Generation')
                        ->action(function (Invitee $record): void {
                            $record->loadMissing('latestGeneratedCard');

                            $record->latestGeneratedCard?->markAsGenerating();

                            GenerateInviteeCardJob::dispatch($record->id);

                            Notification::make()
                                ->title('Card generation started')
                                ->body('The card will be generated in the background.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('view_card')
                        ->label('View Card')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->url(fn (Invitee $record): ?string => $record->generated_card_url)
                        ->openUrlInNewTab()
                        ->visible(fn (Invitee $record): bool => filled($record->generated_card_url)),

                    Tables\Actions\Action::make('retry_card')
                        ->label('Retry Failed Card')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (Invitee $record): bool => $record->latestGeneratedCard?->status === GeneratedCard::STATUS_FAILED)
                        ->action(function (Invitee $record): void {
                            $record->loadMissing('latestGeneratedCard');

                            $record->latestGeneratedCard?->markAsGenerating();

                            GenerateInviteeCardJob::dispatch($record->id);

                            Notification::make()
                                ->title('Card generation restarted')
                                ->body('The failed card has been queued again.')
                                ->success()
                                ->send();
                        }),
                ])
                    ->label('Card')
                    ->icon('heroicon-o-photo')
                    ->color('info')
                    ->button(),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('send_invitation_sms')
                        ->label('Send Invitation SMS')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Send Invitation SMS')
                        ->modalDescription(fn (Invitee $record): string => 'Send invitation SMS with RSVP link to ' . $record->name . '?')
                        ->modalSubmitActionLabel('Send SMS')
                        ->action(function (Invitee $record): void {
                            try {
                                app(SmsService::class)->sendInvitation($record);

                                Notification::make()
                                    ->title('Invitation SMS sent')
                                    ->body('Invitation SMS with RSVP link was sent to ' . $record->name . '.')
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('SMS sending failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Tables\Actions\Action::make('send_rsvp_reminder_sms')
                        ->label('Send RSVP Reminder')
                        ->icon('heroicon-o-bell-alert')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Send RSVP Reminder SMS')
                        ->modalDescription(fn (Invitee $record): string => 'Send RSVP pending reminder SMS to ' . $record->name . '?')
                        ->modalSubmitActionLabel('Send Reminder')
                        ->visible(fn (Invitee $record): bool => $record->rsvp_status === Invitee::RSVP_PENDING)
                        ->action(function (Invitee $record): void {
                            $sent = app(ReminderSmsService::class)->sendRsvpPendingReminder($record);

                            Notification::make()
                                ->title($sent ? 'RSVP reminder sent' : 'RSVP reminder failed')
                                ->body($sent
                                    ? 'RSVP reminder SMS was sent to ' . $record->name . '.'
                                    : 'Could not send RSVP reminder. Check SMS Logs for details.')
                                ->color($sent ? 'success' : 'danger')
                                ->send();
                        }),

                    Tables\Actions\Action::make('send_attending_reminder_sms')
                        ->label('Send Attending Reminder')
                        ->icon('heroicon-o-user-group')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Send Attending Reminder SMS')
                        ->modalDescription(fn (Invitee $record): string => 'Send attending reminder SMS to ' . $record->name . '?')
                        ->modalSubmitActionLabel('Send Reminder')
                        ->visible(fn (Invitee $record): bool => $record->rsvp_status === Invitee::RSVP_ATTENDING)
                        ->action(function (Invitee $record): void {
                            $sent = app(ReminderSmsService::class)->sendAttendingReminder($record);

                            Notification::make()
                                ->title($sent ? 'Attending reminder sent' : 'Attending reminder failed')
                                ->body($sent
                                    ? 'Attending reminder SMS was sent to ' . $record->name . '.'
                                    : 'Could not send attending reminder. Check SMS Logs for details.')
                                ->color($sent ? 'success' : 'danger')
                                ->send();
                        }),

                    Tables\Actions\Action::make('send_event_day_sms')
                        ->label('Send Event Day SMS')
                        ->icon('heroicon-o-calendar-days')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('Send Event Day SMS')
                        ->modalDescription(fn (Invitee $record): string => 'Send final event-day SMS to ' . $record->name . '?')
                        ->modalSubmitActionLabel('Send Final SMS')
                        ->action(function (Invitee $record): void {
                            $sent = app(ReminderSmsService::class)->sendEventDayReminder($record);

                            Notification::make()
                                ->title($sent ? 'Event day SMS sent' : 'Event day SMS failed')
                                ->body($sent
                                    ? 'Event day reminder sent to ' . $record->name . '.'
                                    : 'Could not send event day reminder. Check SMS Logs for details.')
                                ->color($sent ? 'success' : 'danger')
                                ->send();
                        }),
                ])
                    ->label('SMS')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->button(),

                Tables\Actions\Action::make('check_in')
                    ->label('Check In')
                    ->icon('heroicon-o-qr-code')
                    ->button()
                    ->color('success')
                    ->visible(fn (Invitee $record): bool => $record->remaining_guests > 0)
                    ->form([
                        Forms\Components\TextInput::make('guests_to_check_in')
                            ->label('Number of Guests Entering')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(1)
                            ->helperText(fn (Invitee $record): string => 'Remaining guests allowed: ' . $record->remaining_guests),
                    ])
                    ->modalHeading('Check In Invitee')
                    ->modalDescription(fn (Invitee $record): string => 'Invitee: ' . $record->name . ' | Remaining guests: ' . $record->remaining_guests)
                    ->modalSubmitActionLabel('Confirm Check In')
                    ->action(function (Invitee $record, array $data) {
                        $record->refresh();

                        $guestsToCheckIn = (int) $data['guests_to_check_in'];

                        if ($guestsToCheckIn < 1) {
                            Notification::make()
                                ->title('Invalid guest number')
                                ->body('The number of guests must be at least 1.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($record->card_status === Invitee::CARD_STATUS_BLOCKED) {
                            Notification::make()
                                ->title('Card blocked')
                                ->body('This invitation card is blocked and cannot be checked in.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($record->rsvp_status === Invitee::RSVP_NOT_ATTENDING) {
                            Notification::make()
                                ->title('Invitee marked Not Attending')
                                ->body('This invitee selected Not Attending in RSVP. Check-in has been stopped.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($record->remaining_guests <= 0) {
                            Notification::make()
                                ->title('Guest limit reached')
                                ->body('This invitee has no remaining guests allowed.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($guestsToCheckIn > $record->remaining_guests) {
                            Notification::make()
                                ->title('Guest limit exceeded')
                                ->body('Only ' . $record->remaining_guests . ' guest(s) remaining for this card.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $previousCount = $record->checked_in_count;
                        $newCount = $previousCount + $guestsToCheckIn;
                        $remainingGuests = max(0, $record->final_allowed_guests - $newCount);

                        $record->checkIns()->create([
                            'event_id' => $record->event_id,
                            'checked_in_by' => Auth::id(),
                            'checkin_method' => 'manual',
                            'guests_checked_in' => $guestsToCheckIn,
                            'previous_checked_in_count' => $previousCount,
                            'remaining_guests' => $remainingGuests,
                            'status' => 'success',
                            'remarks' => 'Checked in manually from Filament admin panel.',
                            'checked_in_at' => now(),
                        ]);

                        $record->update([
                            'checked_in_count' => $newCount,
                            'checked_in_at' => now(),
                        ]);

                        if (method_exists($record, 'markAsUsedIfFullyCheckedIn')) {
                            $record->refresh();
                            $record->markAsUsedIfFullyCheckedIn();
                        }

                        Notification::make()
                            ->title('Check-in successful')
                            ->body($guestsToCheckIn . ' guest(s) checked in for ' . $record->name . '. Remaining: ' . $remainingGuests)
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('generate_qr')
                    ->label('Generate QR')
                    ->icon('heroicon-o-qr-code')
                    ->button()
                    ->color('info')
                    ->visible(fn (Invitee $record): bool => blank($record->qr_code_path))
                    ->action(function (Invitee $record) {
                        $record->generateQrCode();

                        Notification::make()
                            ->title('QR code generated')
                            ->body('QR code generated for ' . $record->name)
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->label('Edit'),

                Tables\Actions\DeleteAction::make()
                    ->label('Delete'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('generate_cards')
                        ->label('Generate Cards')
                        ->icon('heroicon-o-photo')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Generate selected invitation cards')
                        ->modalDescription('This will queue card generation jobs for all selected invitees. Existing generated cards will be regenerated.')
                        ->modalSubmitActionLabel('Start Generation')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $queued = 0;
                            $skipped = 0;

                            foreach ($records as $record) {
                                $record->loadMissing('latestGeneratedCard');

                                if ($record->latestGeneratedCard?->status === GeneratedCard::STATUS_GENERATING) {
                                    $skipped++;
                                    continue;
                                }

                                $record->latestGeneratedCard?->markAsGenerating();

                                GenerateInviteeCardJob::dispatch($record->id);

                                $queued++;
                            }

                            Notification::make()
                                ->title('Card generation jobs started')
                                ->body($queued . ' card(s) queued. ' . $skipped . ' skipped because they are already generating.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('export_selected_invitees')
                        ->label('Export Selected Invitees')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (Collection $records) {
                            return Excel::download(
                                new InviteesExport(inviteeIds: $records->pluck('id')->toArray()),
                                'selected-invitees-' . now()->format('Y-m-d-His') . '.xlsx'
                            );
                        }),

                    Tables\Actions\BulkAction::make('export_selected_attendance')
                        ->label('Export Selected Attendance')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('info')
                        ->action(function (Collection $records) {
                            return Excel::download(
                                new AttendanceExport(inviteeIds: $records->pluck('id')->toArray()),
                                'selected-attendance-' . now()->format('Y-m-d-His') . '.xlsx'
                            );
                        }),

                    Tables\Actions\BulkAction::make('send_sms_bulk')
                        ->label('Send SMS Invitations')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Send SMS Invitations')
                        ->modalDescription('Send SMS invitations with RSVP links to all selected invitees?')
                        ->modalSubmitActionLabel('Send SMS')
                        ->action(function (Collection $records): void {
                            $sent = 0;
                            $failed = 0;

                            foreach ($records as $record) {
                                try {
                                    app(SmsService::class)->sendInvitation($record);
                                    $sent++;
                                } catch (\Throwable) {
                                    $failed++;
                                }
                            }

                            Notification::make()
                                ->title($failed === 0 ? 'SMS invitations sent' : 'SMS sending completed with errors')
                                ->body($sent . ' sent, ' . $failed . ' failed. Check SMS Logs or SMS Error column for details.')
                                ->color($failed === 0 ? 'success' : 'warning')
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('send_rsvp_reminder_sms_bulk')
                        ->label('Send RSVP Reminder SMS')
                        ->icon('heroicon-o-bell-alert')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Send RSVP Reminder SMS')
                        ->modalDescription('Send RSVP reminder SMS only to selected invitees with RSVP pending?')
                        ->modalSubmitActionLabel('Send Reminders')
                        ->action(function (Collection $records): void {
                            $result = app(ReminderSmsService::class)
                                ->sendBulkRsvpPendingReminders($records);

                            Notification::make()
                                ->title('RSVP reminder SMS completed')
                                ->body("Sent: {$result['sent']} | Failed: {$result['failed']} | Skipped: {$result['skipped']}")
                                ->color($result['failed'] > 0 ? 'warning' : 'success')
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('send_attending_reminder_sms_bulk')
                        ->label('Send One Day Reminder SMS')
                        ->icon('heroicon-o-calendar-days')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Send One Day Reminder SMS')
                        ->modalDescription('Send one-day-before reminder SMS only to selected invitees marked as attending?')
                        ->modalSubmitActionLabel('Send Reminders')
                        ->action(function (Collection $records): void {
                            $result = app(ReminderSmsService::class)
                                ->sendBulkAttendingReminders($records);

                            Notification::make()
                                ->title('One day reminder SMS completed')
                                ->body("Sent: {$result['sent']} | Failed: {$result['failed']} | Skipped: {$result['skipped']}")
                                ->color($result['failed'] > 0 ? 'warning' : 'success')
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('send_event_day_sms_bulk')
                        ->label('Send Event Day SMS')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Send Event Day SMS')
                        ->modalDescription('Send final event day SMS to all selected invitees?')
                        ->modalSubmitActionLabel('Send Final SMS')
                        ->action(function (Collection $records): void {
                            $result = app(ReminderSmsService::class)
                                ->sendBulkEventDayReminders($records);

                            Notification::make()
                                ->title('Event day SMS completed')
                                ->body("Sent: {$result['sent']} | Failed: {$result['failed']} | Skipped: {$result['skipped']}")
                                ->color($result['failed'] > 0 ? 'warning' : 'success')
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function queueInviteeCardGeneration(Invitee $invitee, bool $force = false): string
    {
        $invitee->loadMissing([
            'event.cardTemplates',
            'latestGeneratedCard',
        ]);

        $generatedCard = $invitee->latestGeneratedCard;

        if ($generatedCard?->status === GeneratedCard::STATUS_GENERATING && ! $force) {
            return 'skipped';
        }

        if ($generatedCard) {
            $generatedCard->markAsGenerating();
        } else {
            $template = $invitee->event?->cardTemplates()
                ->latest()
                ->first();

            GeneratedCard::create([
                'event_id' => $invitee->event_id,
                'invitee_id' => $invitee->id,
                'card_template_id' => $template?->id,
                'file_path' => null,
                'status' => GeneratedCard::STATUS_GENERATING,
                'generated_at' => null,
                'sent_at' => null,
            ]);
        }

        GenerateInviteeCardJob::dispatch($invitee->id);

        return 'queued';
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvitees::route('/'),
            'create' => Pages\CreateInvitee::route('/create'),
            'edit' => Pages\EditInvitee::route('/{record}/edit'),
        ];
    }
}
