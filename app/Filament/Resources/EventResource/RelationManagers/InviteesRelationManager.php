<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Exports\EventInviteesExport;
use App\Exports\EventRsvpExport;
use App\Jobs\GenerateInviteeCardJob;
use App\Models\CardType;
use App\Models\GeneratedCard;
use App\Models\Invitee;
use App\Models\MessageTemplate;
use App\Services\AuditLogService;
use App\Services\MessageTemplateRenderer;
use App\Services\SmsService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

class InviteesRelationManager extends RelationManager
{
    protected static string $relationship = 'invitees';

    protected static ?string $title = 'Invitees';

    protected static ?string $modelLabel = 'Invitee';

    protected static ?string $pluralModelLabel = 'Invitees';

    protected static ?string $recordTitleAttribute = 'name';

    private const RSVP_OPTIONS = [
        Invitee::RSVP_PENDING => 'Pending',
        Invitee::RSVP_ATTENDING => 'Attending',
        Invitee::RSVP_NOT_ATTENDING => 'Not Attending',
        Invitee::RSVP_MAYBE => 'Maybe',
    ];

    private const CARD_STATUS_OPTIONS = [
        Invitee::CARD_STATUS_PENDING => 'Pending',
        Invitee::CARD_STATUS_ACTIVE => 'Active',
        Invitee::CARD_STATUS_CANCELLED => 'Cancelled',
        Invitee::CARD_STATUS_BLOCKED => 'Blocked',
        Invitee::CARD_STATUS_USED => 'Used',
    ];

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return static::userCanAccessOwnerRecord(auth()->user(), $ownerRecord)
            && static::userCanManageInvitees(auth()->user());
    }

    protected static function userCanAccessOwnerRecord($user, Model $ownerRecord): bool
    {
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

    protected static function userCanManageInvitees($user): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'canManageInvitees')) {
            return $user->canManageInvitees();
        }

        return (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin())
            || (method_exists($user, 'isEventAdmin') && $user->isEventAdmin());
    }

    protected function canManageInviteesForOwner(): bool
    {
        return static::userCanAccessOwnerRecord(auth()->user(), $this->getOwnerRecord())
            && static::userCanManageInvitees(auth()->user());
    }

    protected function canGenerateCardsForOwner(): bool
    {
        $user = auth()->user();

        if (! static::userCanAccessOwnerRecord($user, $this->getOwnerRecord())) {
            return false;
        }

        if (method_exists($user, 'canGenerateCards')) {
            return $user->canGenerateCards();
        }

        return static::userCanManageInvitees($user);
    }

    protected function canSendMessagesForOwner(): bool
    {
        $user = auth()->user();

        if (! static::userCanAccessOwnerRecord($user, $this->getOwnerRecord())) {
            return false;
        }

        return method_exists($user, 'canSendMessages')
            ? $user->canSendMessages()
            : static::userCanManageInvitees($user);
    }

    protected function canViewReportsForOwner(): bool
    {
        $user = auth()->user();

        if (! static::userCanAccessOwnerRecord($user, $this->getOwnerRecord())) {
            return false;
        }

        return method_exists($user, 'canViewReports')
            ? $user->canViewReports()
            : static::userCanManageInvitees($user);
    }

    protected function canDeleteInviteesForOwner(): bool
    {
        $user = auth()->user();

        return $user
            && method_exists($user, 'isSuperAdmin')
            && $user->isSuperAdmin();
    }

    protected function brandedNotification(): Notification
    {
        return Notification::make()
            ->icon('heroicon-o-check-badge')
            ->iconColor('primary')
            ->color('primary');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Invitee Information')
                    ->description('Add and manage invitees for this event.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Full Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Example: Guest Name')
                            ->dehydrateStateUsing(
                                fn (?string $state): string =>
                                    trim(preg_replace('/\s+/', ' ', (string) $state) ?? '')
                            )
                            ->helperText('The invitee name must be unique within this event. Phone numbers may be shared.'),

                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->required()
                            ->maxLength(30)
                            ->placeholder('Example: 0711111111')
                            ->helperText('Accepted formats: 0711111111, 711111111, +255711111111, or 255711111111. The same phone number may be used by multiple invitees.'),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('Optional'),

                        Forms\Components\Select::make('card_type_id')
                            ->label('Card Type')
                            ->options(fn () => CardType::where('event_id', $this->getOwnerRecord()->id)
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $cardType = CardType::find($state);

                                if ($cardType) {
                                    $set('allowed_guests', $cardType->allowed_people ?? 1);
                                }
                            }),

                        Forms\Components\TextInput::make('allowed_guests')
                            ->label('Allowed Guests')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required()
                            ->helperText('This is normally filled from the selected card type.'),

                        Forms\Components\TextInput::make('category')
                            ->label('Category')
                            ->maxLength(100)
                            ->placeholder('Example: Family, Friend, Committee'),

                        Forms\Components\TextInput::make('table_number')
                            ->label('Table Number')
                            ->maxLength(50)
                            ->placeholder('Example: A1'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('RSVP and Card Status')
                    ->description('Control invitation status and RSVP result.')
                    ->schema([
                        Forms\Components\Select::make('rsvp_status')
                            ->label('RSVP Status')
                            ->options(self::RSVP_OPTIONS)
                            ->default(Invitee::RSVP_PENDING)
                            ->required(),

                        Forms\Components\TextInput::make('confirmed_guests')
                            ->label('Confirmed Guests')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),

                        Forms\Components\Select::make('card_status')
                            ->label('Card Status')
                            ->options(self::CARD_STATUS_OPTIONS)
                            ->default(Invitee::CARD_STATUS_ACTIVE)
                            ->required(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('System Generated Details')
                    ->description('These details are generated automatically by the system.')
                    ->schema([
                        Forms\Components\TextInput::make('serial_number')
                            ->label('Serial Number')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('short_code')
                            ->label('Short Code')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('qr_code_path')
                            ->label('QR Code Path')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('checked_in_count')
                            ->label('Checked-in Guests')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\DateTimePicker::make('checked_in_at')
                            ->label('Checked In At')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(3)
                    ->collapsed()
                    ->visibleOn('edit'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->searchPlaceholder('Search name, phone, serial, short code, email, table, category, status, or card type')
            ->searchDebounce('500ms')
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('No invitees yet')
            ->emptyStateDescription('Add invitees manually or import them from Excel for this event.')
            ->emptyStateIcon('heroicon-o-users')
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->with([
                    'cardType',
                    'latestGeneratedCard',
                ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Invitee')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $this->applyInviteeTableSearch($query, $search);
                    })
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Invitee $record): ?string => collect([
                        $record->phone,
                        $record->email,
                    ])->filter()->implode(' • ') ?: null)
                    ->wrap(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->copyable()
                    ->copyMessage('Phone number copied')
                    ->copyMessageDuration(1500)
                    ->toggleable(isToggledHiddenByDefault: true),


                Tables\Columns\TextColumn::make('cardType.name')
                    ->label('Card Type')
                    ->badge()
                    ->color('primary')
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('guest_summary')
                    ->label('Guests')
                    ->state(function (Invitee $record): string {
                        $allowed = max(1, (int) $record->allowed_guests);
                        $confirmed = min(
                            $allowed,
                            max(0, (int) $record->confirmed_guests),
                        );

                        return "{$confirmed} / {$allowed}";
                    })
                    ->description(function (Invitee $record): string {
                        $allowed = max(1, (int) $record->allowed_guests);
                        $confirmed = min(
                            $allowed,
                            max(0, (int) $record->confirmed_guests),
                        );
                        $remaining = max(0, $allowed - $confirmed);

                        return "{$remaining} remaining";
                    })
                    ->badge()
                    ->color(function (Invitee $record): string {
                        $allowed = max(1, (int) $record->allowed_guests);
                        $confirmed = min(
                            $allowed,
                            max(0, (int) $record->confirmed_guests),
                        );

                        return ($allowed - $confirmed) === 0
                            ? 'success'
                            : 'gray';
                    })
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('table_number')
                    ->label('Table')
                    ->placeholder('-')
                    ->alignCenter()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('serial_number')
                    ->label('Serial Number')
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Serial number copied')
                    ->copyMessageDuration(1500)
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('qr_ready')
                    ->label('QR')
                    ->state(fn (Invitee $record): bool => filled(
                        $record->qr_code_path ?: $record->qr_code
                    ))
                    ->boolean()
                    ->trueIcon('heroicon-o-qr-code')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('rsvp_status')
                    ->label('RSVP')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        Invitee::RSVP_PENDING => 'Pending',
                        Invitee::RSVP_ATTENDING => 'Attending',
                        Invitee::RSVP_NOT_ATTENDING => 'Not Attending',
                        Invitee::RSVP_MAYBE => 'Maybe',
                        default => ucfirst((string) $state),
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        Invitee::RSVP_PENDING => 'warning',
                        Invitee::RSVP_ATTENDING => 'success',
                        Invitee::RSVP_NOT_ATTENDING => 'danger',
                        Invitee::RSVP_MAYBE => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('card_status')
                    ->label('Card Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        Invitee::CARD_STATUS_PENDING => 'Pending',
                        Invitee::CARD_STATUS_ACTIVE => 'Active',
                        Invitee::CARD_STATUS_CANCELLED => 'Cancelled',
                        Invitee::CARD_STATUS_BLOCKED => 'Blocked',
                        Invitee::CARD_STATUS_USED => 'Used',
                        default => 'Invalid Status',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        Invitee::CARD_STATUS_ACTIVE => 'success',
                        Invitee::CARD_STATUS_PENDING => 'warning',
                        Invitee::CARD_STATUS_CANCELLED,
                        Invitee::CARD_STATUS_BLOCKED => 'danger',
                        Invitee::CARD_STATUS_USED => 'info',
                        default => 'danger',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('latestGeneratedCard.status')
                    ->label('Card Generation')
                    ->badge()
                    ->default('Not Generated')
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

                Tables\Columns\TextColumn::make('checked_in_count')
                    ->label('Checked In')
                    ->alignCenter()
                    ->badge()
                    ->description(fn (Invitee $record): ?string =>
                        $record->checked_in_at
                            ? Carbon::parse($record->checked_in_at)->format('d M, H:i')
                            : null
                    )
                    ->color(fn ($state): string => (int) $state > 0 ? 'success' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('message_status_summary')
                    ->label('Message Status')
                    ->state(function (Invitee $record): string {
                        $sms = match ($record->sms_status) {
                            Invitee::SMS_STATUS_SENT => 'Sent',
                            Invitee::SMS_STATUS_DELIVERED => 'Delivered',
                            Invitee::SMS_STATUS_FAILED => 'Failed',
                            Invitee::SMS_STATUS_PENDING => 'Pending',
                            default => 'Not Sent',
                        };

                        return 'SMS: '.$sms;
                    })
                    ->description(function (Invitee $record): string {
                        $whatsapp = match ($record->whatsapp_status) {
                            'sending' => 'Sending',
                            'sent' => 'Sent',
                            'delivered' => 'Delivered',
                            'read' => 'Read',
                            'failed' => 'Failed',
                            default => 'Not Sent',
                        };

                        return 'WhatsApp: '.$whatsapp;
                    })
                    ->badge()
                    ->color(fn (Invitee $record): string => match ($record->sms_status) {
                        Invitee::SMS_STATUS_SENT,
                        Invitee::SMS_STATUS_DELIVERED => 'success',
                        Invitee::SMS_STATUS_FAILED => 'danger',
                        Invitee::SMS_STATUS_PENDING => 'warning',
                        default => 'gray',
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('sms_status')
                    ->label('SMS Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string =>
                        ucfirst(str_replace('_', ' ', $state ?: 'not_sent'))
                    )
                    ->color(fn (?string $state): string => match ($state) {
                        Invitee::SMS_STATUS_SENT,
                        Invitee::SMS_STATUS_DELIVERED => 'success',
                        Invitee::SMS_STATUS_FAILED => 'danger',
                        Invitee::SMS_STATUS_PENDING => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('whatsapp_status')
                    ->label('WhatsApp Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'not_sent', null, '' => 'Not Sent',
                        'sending' => 'Sending',
                        'sent' => 'Sent',
                        'delivered' => 'Delivered',
                        'read' => 'Read',
                        'failed' => 'Failed',
                        default => ucfirst(str_replace('_', ' ', (string) $state)),
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'sent', 'delivered', 'read' => 'success',
                        'sending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('last_message_channel')
                    ->label('Last Channel')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'sms' => 'SMS',
                        'whatsapp' => 'WhatsApp',
                        null, '' => '-',
                        default => ucfirst((string) $state),
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'sms' => 'info',
                        'whatsapp' => 'success',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('whatsapp_sent_at')
                    ->label('WhatsApp Sent At')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent At')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('card_type_id')
                    ->label('Card Type')
                    ->options(fn () => CardType::where('event_id', $this->getOwnerRecord()->id)
                        ->orderBy('name')
                        ->pluck('name', 'id')),

                Tables\Filters\SelectFilter::make('rsvp_status')
                    ->label('RSVP Status')
                    ->options(self::RSVP_OPTIONS),

                Tables\Filters\SelectFilter::make('card_status')
                    ->label('Card Status')
                    ->options(self::CARD_STATUS_OPTIONS),

                Tables\Filters\SelectFilter::make('category')
                    ->label('Category')
                    ->options(fn (): array => Invitee::query()
                        ->where('event_id', $this->getOwnerRecord()->getKey())
                        ->whereNotNull('category')
                        ->where('category', '!=', '')
                        ->distinct()
                        ->orderBy('category')
                        ->pluck('category', 'category')
                        ->toArray())
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('table_number')
                    ->label('Table Number')
                    ->options(fn (): array => Invitee::query()
                        ->where('event_id', $this->getOwnerRecord()->getKey())
                        ->whereNotNull('table_number')
                        ->where('table_number', '!=', '')
                        ->distinct()
                        ->orderBy('table_number')
                        ->pluck('table_number', 'table_number')
                        ->toArray())
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('has_phone')
                    ->label('Valid Phone')
                    ->queries(
                        true: fn (Builder $query): Builder => $query
                            ->whereNotNull('phone')
                            ->where('phone', '!=', ''),
                        false: fn (Builder $query): Builder => $query
                            ->where(function (Builder $query): void {
                                $query->whereNull('phone')->orWhere('phone', '');
                            }),
                    ),

                Tables\Filters\SelectFilter::make('generated_card_status')
                    ->label('Generated Card')
                    ->options([
                        'not_generated' => 'Not Generated',
                        GeneratedCard::STATUS_PENDING => 'Pending',
                        GeneratedCard::STATUS_GENERATING => 'Generating',
                        GeneratedCard::STATUS_GENERATED => 'Generated',
                        GeneratedCard::STATUS_SENT => 'Sent',
                        GeneratedCard::STATUS_FAILED => 'Failed',
                    ])
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        if ($value === 'not_generated') {
                            return $query->whereDoesntHave('latestGeneratedCard');
                        }

                        return $query->whereHas('latestGeneratedCard', fn ($query) => $query->where('status', $value));
                    }),

                Tables\Filters\SelectFilter::make('whatsapp_status')
                    ->label('WhatsApp Status')
                    ->options([
                        'not_sent' => 'Not Sent',
                        'sending' => 'Sending',
                        'sent' => 'Sent',
                        'delivered' => 'Delivered',
                        'read' => 'Read',
                        'failed' => 'Failed',
                    ]),

                Tables\Filters\Filter::make('checked_in')
                    ->label('Checked In')
                    ->query(fn ($query) => $query->where('checked_in_count', '>', 0)),

                Tables\Filters\Filter::make('not_checked_in')
                    ->label('Not Checked In')
                    ->query(fn ($query) => $query->where(function ($query) {
                        $query->whereNull('checked_in_count')
                            ->orWhere('checked_in_count', 0);
                    })),
            ])
            ->filtersFormColumns(3)
            ->headerActions([
                Tables\Actions\Action::make('add_manual_invitee')
                    ->label('Add Invitee')
                    ->visible(fn (): bool => $this->canManageInviteesForOwner())
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->modalHeading('Add Invitee Manually')
                    ->modalDescription('Use this form to add one invitee without uploading Excel. Serial number, QR token, short code, and RSVP token will be generated automatically.')
                    ->modalSubmitActionLabel('Save Invitee')
                    ->form([
                        Forms\Components\Section::make('Manual Invitee Details')
                            ->description('Enter the invitee details that will appear on the generated invitation card.')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Full Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Example: Guest Name')
                                    ->dehydrateStateUsing(
                                        fn (?string $state): string =>
                                            trim(preg_replace('/\s+/', ' ', (string) $state) ?? '')
                                    )
                                    ->helperText('The name must be unique within this event. A phone number may belong to multiple invitees.'),

                                Forms\Components\TextInput::make('phone')
                                    ->label('Phone Number')
                                    ->tel()
                                    ->required()
                                    ->maxLength(30)
                                    ->placeholder('Example: 0711111111')
                                    ->helperText('Accepted formats: 0711111111, 711111111, +255711111111, or 255711111111. The same phone number may be used by multiple invitees.'),

                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255)
                                    ->placeholder('Optional'),

                                Forms\Components\Select::make('card_type_id')
                                    ->label('Card Type')
                                    ->options(fn () => CardType::where('event_id', $this->getOwnerRecord()->id)
                                        ->where('is_active', true)
                                        ->orderBy('name')
                                        ->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->required()
                                    ->afterStateUpdated(function ($state, callable $set): void {
                                        $cardType = CardType::find($state);

                                        if ($cardType) {
                                            $set('allowed_guests', $cardType->allowed_people ?? 1);
                                        }
                                    }),

                                Forms\Components\TextInput::make('allowed_guests')
                                    ->label('Allowed Guests')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required()
                                    ->helperText('This is filled automatically from the selected card type, but you can adjust it if needed.'),

                                Forms\Components\TextInput::make('category')
                                    ->label('Category')
                                    ->maxLength(100)
                                    ->placeholder('Example: Family, Friends, Committee, VIP'),

                                Forms\Components\TextInput::make('table_number')
                                    ->label('Table Number')
                                    ->maxLength(50)
                                    ->placeholder('Example: A1, VIP 2, Family Table'),
                            ])
                            ->columns(2),

                        Forms\Components\Section::make('Default Status')
                            ->description('These defaults are suitable for a new manual invitee.')
                            ->schema([
                                Forms\Components\Select::make('rsvp_status')
                                    ->label('RSVP Status')
                                    ->options(self::RSVP_OPTIONS)
                                    ->default(Invitee::RSVP_PENDING)
                                    ->required(),

                                Forms\Components\Select::make('card_status')
                                    ->label('Card Status')
                                    ->options(self::CARD_STATUS_OPTIONS)
                                    ->default(Invitee::CARD_STATUS_ACTIVE)
                                    ->required(),

                                Forms\Components\TextInput::make('confirmed_guests')
                                    ->label('Confirmed Guests')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->required(),
                            ])
                            ->columns(3),
                    ])
                    ->action(function (array $data): void {
                        $this->validateNoDuplicateInviteeName($data['name'] ?? null);

                        $preparedData = $this->prepareInviteeData($data);

                        $invitee = Invitee::create($preparedData);

                        AuditLogService::created(
                            subject: $invitee,
                            eventId: $invitee->event_id,
                            description: 'Invitee was added manually.',
                            metadata: [
                                'source' => 'manual',
                                'card_type_id' => $invitee->card_type_id,
                                'allowed_guests' => $invitee->allowed_guests,
                            ],
                        );

                        $this->queueAutomaticCardGeneration($invitee);

                        $this->brandedNotification()
                            ->title('eLive Card • Invitee added')
                            ->body('Serial number, QR code, private link, and card generation have been started automatically.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('generate_missing_cards')
                    ->label('Generate Cards')
                    ->visible(fn (): bool => $this->canGenerateCardsForOwner())
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Generate missing invitation cards')
                    ->modalDescription('This will queue card generation only for invitees whose card is missing, failed, or not yet generated.')
                    ->modalSubmitActionLabel('Generate Cards')
                    ->action(function (): void {
                        $event = $this->getOwnerRecord();

                        $invitees = Invitee::query()
                            ->where('event_id', $event->id)
                            ->with(['latestGeneratedCard'])
                            ->get();

                        $queued = 0;
                        $skippedGenerated = 0;
                        $skippedGenerating = 0;
                        $failed = 0;

                        foreach ($invitees as $invitee) {
                            try {
                                if (! $invitee instanceof Invitee) {
                                    continue;
                                }

                                if ($this->isInviteeCardGenerating($invitee)) {
                                    $skippedGenerating++;

                                    continue;
                                }

                                if ($this->inviteeHasUsableGeneratedCard($invitee)) {
                                    $skippedGenerated++;

                                    continue;
                                }

                                $this->prepareGeneratedCardForQueue($invitee);
                                GenerateInviteeCardJob::dispatch($invitee->id);

                                $queued++;
                            } catch (Throwable $e) {
                                $failed++;
                            }
                        }

                        AuditLogService::system(
                            action: 'cards_generate_missing',
                            description: 'Missing invitation card generation was processed.',
                            eventId: $event->id,
                            metadata: [
                                'queued' => $queued,
                                'already_generated' => $skippedGenerated,
                                'already_generating' => $skippedGenerating,
                                'failed' => $failed,
                            ],
                        );

                        $this->brandedNotification()
                            ->title('eLive Card • ' . ($queued > 0 ? 'Card generation started' : 'No missing cards'))
                            ->body("Queued: {$queued}. Already generated/sent: {$skippedGenerated}. Already generating: {$skippedGenerating}. Failed: {$failed}.")
                            ->color($failed > 0 ? 'warning' : ($queued > 0 ? 'success' : 'info'))
                            ->persistent()
                            ->send();
                    }),

                Tables\Actions\Action::make('import_excel')
                    ->label('Import Excel')
                    ->visible(fn (): bool => $this->canManageInviteesForOwner())
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('gray')
                    ->modalHeading('Import Invitees from Excel')
                    ->modalDescription('Upload an Excel file with columns: name, phone, card_type, category, table_number.')
                    ->modalSubmitActionLabel('Import Invitees')
                    ->form([
                        Forms\Components\FileUpload::make('excel_file')
                            ->label('Excel File')
                            ->required()
                            ->storeFiles(false)
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'text/csv',
                                'application/csv',
                                'application/octet-stream',
                            ])
                            ->maxSize(10240)
                            ->helperText('Required columns: name, phone, card_type. Optional: email, category, table_number.'),
                    ])
                    ->action(function (array $data): void {
                        $this->importInviteesFromExcel($data['excel_file']);
                    }),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('send_message')
                        ->label('Send Message')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->modalHeading('Send SMS Message')
                        ->modalDescription('Choose the SMS message option and recipients.')
                        ->modalSubmitActionLabel('Send SMS')
                        ->form([
                            Forms\Components\Select::make('template_type')
                                ->label('Message Option')
                                ->options([
                                    'invitation' => 'Invitation SMS',
                                    'card_link' => 'Card Link SMS',
                                    'custom' => 'Custom SMS',
                                ])
                                ->default('invitation')
                                ->required(),

                            Forms\Components\Select::make('recipient_scope')
                                ->label('Send To')
                                ->options([
                                    'not_sent' => 'Invitees Not Yet Sent',
                                    'all_active' => 'All Active Invitees',
                                    'pending_rsvp' => 'Pending RSVP Invitees',
                                    'attending' => 'Attending Invitees',
                                    'failed_sms' => 'Failed SMS Invitees',
                                ])
                                ->default('not_sent')
                                ->required(),

                            Forms\Components\Placeholder::make('note')
                                ->label('Template Source')
                                ->content('The system will use the active SMS template from the Message Templates tab.'),
                        ])
                        ->requiresConfirmation()
                        ->action(function (array $data): void {
                            $templateType = match ($data['template_type']) {
                                'card_link' => 'invitation',
                                default => $data['template_type'],
                            };

                            $this->sendEventMessagesFromToolbar(
                                channel: 'sms',
                                templateType: $templateType,
                                recipientScope: $data['recipient_scope'],
                                actionTitle: 'SMS messages processed',
                            );
                        }),

                    Tables\Actions\Action::make('send_whatsapp')
                        ->label('Send WhatsApp')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->color('primary')
                        ->modalHeading('Send WhatsApp Message')
                        ->modalDescription('Choose the exact approved Meta WhatsApp template and recipients.')
                        ->modalSubmitActionLabel('Send WhatsApp')
                        ->form([
                            Forms\Components\Select::make('message_template_id')
                                ->label('WhatsApp Template')
                                ->options(fn (): array => $this->whatsappMessageTemplateOptions())
                                ->searchable()
                                ->preload()
                                ->required()
                                ->helperText('Choose the exact Meta template to send, for example event_invitation_en, event_invitation_sw, or event_ticket_en.'),

                            Forms\Components\Select::make('recipient_scope')
                                ->label('Send To')
                                ->options([
                                    'not_sent' => 'Invitees Not Yet Sent',
                                    'all_active' => 'All Active Invitees',
                                    'pending_rsvp' => 'Pending RSVP Invitees',
                                    'attending' => 'Attending Invitees',
                                ])
                                ->default('not_sent')
                                ->required(),

                            Forms\Components\Placeholder::make('note')
                                ->label('Note')
                                ->content('This sends using the selected WhatsApp Cloud API template and saves the response in Message Logs.'),
                        ])
                        ->requiresConfirmation()
                        ->action(function (array $data): void {
                            $selectedTemplate = $this->messageTemplateById((int) $data['message_template_id'], 'whatsapp');

                            if (! $selectedTemplate) {
                                $this->brandedNotification()
                                    ->title('eLive Card • WhatsApp template not found')
                                    ->body('Please choose a valid WhatsApp template.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $this->sendEventMessagesFromToolbar(
                                channel: 'whatsapp',
                                templateType: $selectedTemplate->type,
                                recipientScope: $data['recipient_scope'],
                                actionTitle: 'WhatsApp messages processed',
                                messageTemplateId: $selectedTemplate->id,
                            );
                        }),

                    Tables\Actions\Action::make('send_reminder')
                        ->label('Reminder')
                        ->icon('heroicon-o-bell')
                        ->color('warning')
                        ->modalHeading('Send Reminder')
                        ->modalDescription('Choose reminder type, channel, and recipients.')
                        ->modalSubmitActionLabel('Send Reminder')
                        ->form([
                            Forms\Components\Select::make('channel')
                                ->label('Channel')
                                ->options([
                                    'sms' => 'SMS',
                                    'whatsapp' => 'WhatsApp',
                                ])
                                ->default('sms')
                                ->required(),

                            Forms\Components\Select::make('template_type')
                                ->label('Reminder Option')
                                ->options([
                                    'rsvp_pending_reminder' => 'RSVP Pending Reminder',
                                    'attending_reminder' => 'One Day Before Reminder',
                                    'event_day_reminder' => 'Event Day Reminder',
                                ])
                                ->default('rsvp_pending_reminder')
                                ->required(),

                            Forms\Components\Select::make('recipient_scope')
                                ->label('Send To')
                                ->options([
                                    'pending_rsvp' => 'Pending RSVP Invitees',
                                    'attending' => 'Attending Invitees',
                                    'all_active' => 'All Active Invitees',
                                ])
                                ->default('pending_rsvp')
                                ->required(),
                        ])
                        ->requiresConfirmation()
                        ->action(function (array $data): void {
                            $this->sendEventMessagesFromToolbar(
                                channel: $data['channel'],
                                templateType: $data['template_type'],
                                recipientScope: $data['recipient_scope'],
                                actionTitle: 'Reminder messages processed',
                            );
                        }),

                    Tables\Actions\Action::make('thank_you_message')
                        ->label('Thank You Message')
                        ->icon('heroicon-o-heart')
                        ->color('gray')
                        ->modalHeading('Send Thank You Message')
                        ->modalDescription('Choose thank-you message channel and recipients.')
                        ->modalSubmitActionLabel('Send Thank You')
                        ->form([
                            Forms\Components\Select::make('channel')
                                ->label('Channel')
                                ->options([
                                    'sms' => 'SMS',
                                    'whatsapp' => 'WhatsApp',
                                ])
                                ->default('sms')
                                ->required(),

                            Forms\Components\Select::make('recipient_scope')
                                ->label('Send To')
                                ->options([
                                    'attending' => 'Thank You to Attending Guests',
                                    'checked_in' => 'Thank You to Checked-in Guests',
                                    'all_active' => 'Thank You to All Active Invitees',
                                ])
                                ->default('attending')
                                ->required(),

                            Forms\Components\Placeholder::make('note')
                                ->label('Template Source')
                                ->content('The system will use the active thank_you template for the selected channel.'),
                        ])
                        ->requiresConfirmation()
                        ->action(function (array $data): void {
                            $this->sendEventMessagesFromToolbar(
                                channel: $data['channel'],
                                templateType: 'thank_you',
                                recipientScope: $data['recipient_scope'],
                                actionTitle: 'Thank you messages processed',
                            );
                        }),
                ])
                    ->label('Communications')
                    ->visible(fn (): bool => $this->canSendMessagesForOwner())
                    ->icon('heroicon-o-envelope')
                    ->button()
                    ->color('success'),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('export_invitees')
                    ->label('Export Invitees')
                    ->visible(fn (): bool => $this->canViewReportsForOwner())
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->action(function () {
                        $event = $this->getOwnerRecord();

                        $eventName = Str::slug((string) ($event->title ?? $event->name ?? 'event-' . $event->id));

                        AuditLogService::exported(
                            subject: $event,
                            eventId: $event->id,
                            description: 'Invitees report was exported.',
                            metadata: [
                                'report' => 'invitees',
                                'format' => 'xlsx',
                            ],
                        );

                        return Excel::download(
                            new EventInviteesExport((int) $event->id),
                            $eventName . '-invitees-report.xlsx'
                        );
                    }),

                    Tables\Actions\Action::make('export_rsvp')
                    ->label('Export RSVP')
                    ->visible(fn (): bool => $this->canViewReportsForOwner())
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('warning')
                    ->action(function () {
                        $event = $this->getOwnerRecord();

                        $eventName = Str::slug((string) ($event->title ?? $event->name ?? 'event-' . $event->id));

                        AuditLogService::exported(
                            subject: $event,
                            eventId: $event->id,
                            description: 'RSVP report was exported.',
                            metadata: [
                                'report' => 'rsvp',
                                'format' => 'xlsx',
                            ],
                        );

                        return Excel::download(
                            new EventRsvpExport((int) $event->id),
                            $eventName . '-rsvp-report.xlsx'
                        );
                    }),

                    Tables\Actions\Action::make('refresh_status')
                        ->label('Refresh Status')
                        ->icon('heroicon-o-arrow-path')
                        ->action(function (): void {
                            $this->brandedNotification()
                                ->title('eLive Card • Status refreshed')
                                ->body('The invitee and card generation statuses have been refreshed.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('download_sample_excel')
                        ->label('Download Sample Excel')
                        ->visible(fn (): bool => $this->canManageInviteesForOwner())
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(fn () => route('invitees.sample-excel'))
                        ->openUrlInNewTab(),
                ])
                    ->label('More')
                    ->visible(fn (): bool => $this->canManageInviteesForOwner() || $this->canViewReportsForOwner())
                    ->icon('heroicon-o-ellipsis-horizontal')
                    ->button()
                    ->color('gray'),
            ])
            ->actionsPosition(Tables\Enums\ActionsPosition::AfterColumns)
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('View')
                        ->icon('heroicon-o-eye')
                        ->modalHeading(fn ($record): string => 'View Invitee: ' . $record->name),

                    Tables\Actions\Action::make('edit_invitee')
                        ->label('Edit Invitee')
                        ->visible(fn (): bool => $this->canManageInviteesForOwner())
                        ->icon('heroicon-o-pencil-square')
                        ->color('primary')
                        ->modalHeading(fn ($record): string => 'Edit Invitee: ' . $record->name)
                        ->modalSubmitActionLabel('Save Changes')
                        ->fillForm(fn (Invitee $record): array => [
                            'name' => $record->name,
                            'phone' => $record->phone,
                            'email' => $record->email,
                            'card_type_id' => $record->card_type_id,
                            'allowed_guests' => $record->allowed_guests,
                            'category' => $record->category,
                            'table_number' => $record->table_number,
                            'rsvp_status' => $record->rsvp_status,
                            'confirmed_guests' => $record->confirmed_guests,
                            'card_status' => $record->card_status,
                        ])
                        ->form([
                            Forms\Components\Section::make('Invitee Information')
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->label('Full Name')
                                        ->required()
                                        ->maxLength(255)
                                        ->dehydrateStateUsing(
                                            fn (?string $state): string =>
                                                trim(preg_replace('/\s+/', ' ', (string) $state) ?? '')
                                        )
                                        ->helperText('The name must remain unique within this event. Phone numbers may repeat.'),

                                    Forms\Components\TextInput::make('phone')
                                        ->label('Phone Number')
                                        ->tel()
                                        ->required()
                                        ->maxLength(30),

                                    Forms\Components\TextInput::make('email')
                                        ->label('Email')
                                        ->email()
                                        ->maxLength(255),

                                    Forms\Components\Select::make('card_type_id')
                                        ->label('Card Type')
                                        ->options(fn () => CardType::where('event_id', $this->getOwnerRecord()->id)
                                            ->where('is_active', true)
                                            ->orderBy('name')
                                            ->pluck('name', 'id'))
                                        ->searchable()
                                        ->preload()
                                        ->live()
                                        ->required()
                                        ->afterStateUpdated(function ($state, callable $set) {
                                            $cardType = CardType::find($state);

                                            if ($cardType) {
                                                $set('allowed_guests', $cardType->allowed_people ?? 1);
                                            }
                                        }),

                                    Forms\Components\TextInput::make('allowed_guests')
                                        ->label('Allowed Guests')
                                        ->numeric()
                                        ->minValue(1)
                                        ->required(),

                                    Forms\Components\TextInput::make('category')
                                        ->label('Category')
                                        ->maxLength(100),

                                    Forms\Components\TextInput::make('table_number')
                                        ->label('Table Number')
                                        ->maxLength(50),
                                ])
                                ->columns(2),

                            Forms\Components\Section::make('RSVP and Card Status')
                                ->schema([
                                    Forms\Components\Select::make('rsvp_status')
                                        ->label('RSVP Status')
                                        ->options([
                                            Invitee::RSVP_PENDING => 'Pending',
                                            Invitee::RSVP_ATTENDING => 'Attending',
                                            Invitee::RSVP_NOT_ATTENDING => 'Not Attending',
                                            Invitee::RSVP_MAYBE => 'Maybe',
                                        ])
                                        ->required(),

                                    Forms\Components\TextInput::make('confirmed_guests')
                                        ->label('Confirmed Guests')
                                        ->numeric()
                                        ->minValue(0)
                                        ->required(),

                                    Forms\Components\Select::make('card_status')
                                        ->label('Card Status')
                                        ->options([
                                            Invitee::CARD_STATUS_PENDING => 'Pending',
                                            Invitee::CARD_STATUS_ACTIVE => 'Active',
                                            Invitee::CARD_STATUS_CANCELLED => 'Cancelled',
                                            Invitee::CARD_STATUS_BLOCKED => 'Blocked',
                                            Invitee::CARD_STATUS_USED => 'Used',
                                        ])
                                        ->required(),
                                ])
                                ->columns(3),
                        ])
                        ->action(function (Invitee $record, array $data): void {
                            $this->validateNoDuplicateInviteeName($data['name'] ?? null, $record->id);

                            $data['phone'] = $this->normalizePhone($data['phone'] ?? null);

                            if (! $data['phone']) {
                                throw ValidationException::withMessages([
                                    'phone' => 'Invalid Tanzania phone number.',
                                ]);
                            }

                            if (! empty($data['card_type_id'])) {
                                $cardType = CardType::find($data['card_type_id']);
                                $data['allowed_guests'] = $cardType?->allowed_people ?? 1;
                            }

                            $data['allowed_guests'] = max(1, (int) ($data['allowed_guests'] ?? 1));
                            $data['confirmed_guests'] = max(0, (int) ($data['confirmed_guests'] ?? 0));

                            if ($data['confirmed_guests'] > $data['allowed_guests']) {
                                $data['confirmed_guests'] = $data['allowed_guests'];
                            }

                            $cardSensitiveFields = [
                                'name',
                                'phone',
                                'card_type_id',
                                'allowed_guests',
                                'category',
                                'table_number',
                            ];

                            $originalData = $record->only($cardSensitiveFields);

                            $auditOldValues = $record->only([
                                'name',
                                'phone',
                                'email',
                                'card_type_id',
                                'allowed_guests',
                                'category',
                                'table_number',
                                'rsvp_status',
                                'confirmed_guests',
                                'card_status',
                            ]);

                            $record->update($data);
                            $record->refresh();

                            AuditLogService::updated(
                                subject: $record,
                                eventId: $record->event_id,
                                description: 'Invitee details were updated.',
                                oldValues: $auditOldValues,
                                newValues: $record->only([
                                    'name',
                                    'phone',
                                    'email',
                                    'card_type_id',
                                    'allowed_guests',
                                    'category',
                                    'table_number',
                                    'rsvp_status',
                                    'confirmed_guests',
                                    'card_status',
                                ]),
                            );

                            $shouldRegenerateCard = collect($cardSensitiveFields)
                                ->contains(fn (string $field): bool => ($originalData[$field] ?? null) != ($record->{$field} ?? null));

                            if ($shouldRegenerateCard) {
                                $this->queueAutomaticCardGeneration($record, true);
                            }

                            $this->brandedNotification()
                                ->title('eLive Card • Invitee updated successfully')
                                ->body($shouldRegenerateCard
                                    ? 'Card-related details changed, so card regeneration has started automatically.'
                                    : 'Invitee details updated. Card regeneration was not needed.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('generate_card')
                        ->label('Generate / Regenerate Card')
                        ->visible(fn (): bool => $this->canGenerateCardsForOwner())
                        ->icon('heroicon-o-photo')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Generate or regenerate invitation card')
                        ->modalDescription('This will generate the personalized card in the background.')
                        ->action(function (Invitee $record): void {
                            if ($record->latestGeneratedCard?->status === GeneratedCard::STATUS_GENERATING) {
                                $this->brandedNotification()
                                    ->title('eLive Card • Card is already generating')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $this->queueAutomaticCardGeneration($record, true);

                            AuditLogService::record(
                                action: 'invitee.card_generation_queued',
                                subject: $record,
                                eventId: $record->event_id,
                                description: 'Invitation card generation or regeneration was queued.',
                                metadata: [
                                    'force_regenerate' => true,
                                ],
                            );

                            $this->brandedNotification()
                                ->title('eLive Card • Card generation started')
                                ->body('Click Refresh Status after a few seconds to update the Card Gen column from Generating to Generated.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('view_generated_card')
                        ->label('View Generated Card')
                        ->icon('heroicon-o-photo')
                        ->color('info')
                        ->url(fn (Invitee $record): ?string => $record->generated_card_url)
                        ->openUrlInNewTab()
                        ->visible(fn (Invitee $record): bool => filled($record->generated_card_url)),

                    Tables\Actions\Action::make('retry_failed_card')
                        ->label('Retry Failed Card')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (Invitee $record): bool => $this->canGenerateCardsForOwner() && $record->latestGeneratedCard?->status === GeneratedCard::STATUS_FAILED)
                        ->action(function (Invitee $record): void {
                            $this->queueAutomaticCardGeneration($record, true);

                            AuditLogService::record(
                                action: 'invitee.card_generation_retried',
                                subject: $record,
                                eventId: $record->event_id,
                                description: 'Failed invitation card generation was retried.',
                            );

                            $this->brandedNotification()
                                ->title('eLive Card • Card generation restarted')
                                ->body('Click Refresh Status after a few seconds to update the Card Gen column.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('open_invitee_page')
                        ->label('Open Invitee Page')
                        ->icon('heroicon-o-eye')
                        ->url(fn ($record) => route('invitee.page', $record->short_code))
                        ->openUrlInNewTab()
                        ->visible(fn ($record): bool => filled($record->short_code)),

                    Tables\Actions\Action::make('show_private_link')
                        ->label('Show Private Link')
                        ->icon('heroicon-o-link')
                        ->action(function ($record) {
                            $this->brandedNotification()
                                ->title('eLive Card • Private invitee link')
                                ->body(route('invitee.page', $record->short_code))
                                ->success()
                                ->persistent()
                                ->send();
                        })
                        ->visible(fn ($record): bool => filled($record->short_code)),

                    Tables\Actions\Action::make('send_card_link')
                        ->label('Send Message')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->modalHeading(fn (Invitee $record): string => 'Send message to ' . $record->name)
                        ->modalSubmitActionLabel('Send / Record')
                        ->form([
                            Forms\Components\Select::make('channel')
                                ->label('Channel')
                                ->options([
                                    'sms' => 'SMS',
                                    'whatsapp' => 'WhatsApp',
                                ])
                                ->default('sms')
                                ->live()
                                ->required(),

                            Forms\Components\Select::make('template_type')
                                ->label('Message Type')
                                ->options([
                                    'invitation' => 'Invitation',
                                    'rsvp_pending_reminder' => 'RSVP Pending Reminder',
                                    'attending_reminder' => 'Attending Reminder',
                                    'event_day_reminder' => 'Event Day Reminder',
                                    'welcome_checkin' => 'Welcome After Check-in',
                                    'thank_you' => 'Thank You',
                                    'custom' => 'Custom',
                                ])
                                ->default('invitation')
                                ->required(),

                            Forms\Components\Select::make('message_template_id')
                                ->label('Exact WhatsApp Template')
                                ->options(fn (): array => $this->whatsappMessageTemplateOptions())
                                ->searchable()
                                ->preload()
                                ->helperText('For WhatsApp, choose the exact approved Meta template. For SMS, leave this empty.'),

                            Forms\Components\Placeholder::make('template_note')
                                ->label('Template Source')
                                ->content('For WhatsApp, the selected exact template is used. For SMS, the active template for the selected type is used.'),
                        ])
                        ->action(function (Invitee $record, array $data): void {
                            $result = $this->sendInviteeMessageUsingTemplate(
                                invitee: $record,
                                channel: $data['channel'],
                                templateType: $data['template_type'],
                                messageTemplateId: ($data['channel'] ?? null) === 'whatsapp' && filled($data['message_template_id'] ?? null)
                                    ? (int) $data['message_template_id']
                                    : null,
                            );

                            AuditLogService::record(
                                action: 'invitee.message_'.$result['status'],
                                subject: $record,
                                eventId: $record->event_id,
                                description: ucfirst((string) $data['channel']).' message action was processed for the invitee.',
                                metadata: [
                                    'channel' => $data['channel'],
                                    'template_type' => $data['template_type'],
                                    'status' => $result['status'] ?? null,
                                    'message_template_id' => $data['message_template_id'] ?? null,
                                ],
                            );

                            $notification = $this->brandedNotification()
                                ->title('eLive Card • ' . $result['title'])
                                ->body($result['body'])
                                ->persistent();

                            match ($result['type']) {
                                'success' => $notification->success(),
                                'danger' => $notification->danger(),
                                'warning' => $notification->warning(),
                                default => $notification->info(),
                            };

                            $notification->send();
                        })
                        ->visible(fn (Invitee $record): bool => $this->canSendMessagesForOwner() && filled($record->short_code)),

                    Tables\Actions\Action::make('mark_attending')
                        ->label('Mark Attending')
                        ->visible(fn (): bool => $this->canManageInviteesForOwner())
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Invitee $record): void {
                            $oldValues = $record->only([
                                'rsvp_status',
                                'rsvp_confirmed_at',
                                'confirmed_guests',
                            ]);

                            $record->update([
                                'rsvp_status' => Invitee::RSVP_ATTENDING,
                                'rsvp_confirmed_at' => now(),
                                'confirmed_guests' => max(1, (int) ($record->confirmed_guests ?: 1)),
                            ]);

                            AuditLogService::updated(
                                subject: $record,
                                eventId: $record->event_id,
                                description: 'Invitee RSVP was marked as attending.',
                                oldValues: $oldValues,
                                newValues: $record->only([
                                    'rsvp_status',
                                    'rsvp_confirmed_at',
                                    'confirmed_guests',
                                ]),
                            );

                            $this->brandedNotification()
                                ->title('eLive Card • Invitee marked as attending')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('mark_not_attending')
                        ->label('Mark Not Attending')
                        ->visible(fn (): bool => $this->canManageInviteesForOwner())
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Invitee $record): void {
                            $oldValues = $record->only([
                                'rsvp_status',
                                'rsvp_confirmed_at',
                                'confirmed_guests',
                            ]);

                            $record->update([
                                'rsvp_status' => Invitee::RSVP_NOT_ATTENDING,
                                'rsvp_confirmed_at' => now(),
                                'confirmed_guests' => 0,
                            ]);

                            AuditLogService::updated(
                                subject: $record,
                                eventId: $record->event_id,
                                description: 'Invitee RSVP was marked as not attending.',
                                oldValues: $oldValues,
                                newValues: $record->only([
                                    'rsvp_status',
                                    'rsvp_confirmed_at',
                                    'confirmed_guests',
                                ]),
                            );

                            $this->brandedNotification()
                                ->title('eLive Card • Invitee marked as not attending')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('reset_rsvp')
                        ->label('Reset RSVP')
                        ->visible(fn (): bool => $this->canManageInviteesForOwner())
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Invitee $record): void {
                            $oldValues = $record->only([
                                'rsvp_status',
                                'rsvp_confirmed_at',
                                'confirmed_guests',
                            ]);

                            $record->update([
                                'rsvp_status' => Invitee::RSVP_PENDING,
                                'rsvp_confirmed_at' => null,
                                'confirmed_guests' => 0,
                            ]);

                            AuditLogService::updated(
                                subject: $record,
                                eventId: $record->event_id,
                                description: 'Invitee RSVP was reset to pending.',
                                oldValues: $oldValues,
                                newValues: $record->only([
                                    'rsvp_status',
                                    'rsvp_confirmed_at',
                                    'confirmed_guests',
                                ]),
                            );

                            $this->brandedNotification()
                                ->title('eLive Card • RSVP reset to pending')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('cancel_invitee')
                        ->label('Cancel Invitee')
                        ->visible(fn (): bool => $this->canManageInviteesForOwner())
                        ->icon('heroicon-o-no-symbol')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Cancel Invitee')
                        ->modalDescription('This keeps the invitee record but blocks the card from being used.')
                        ->action(function (Invitee $record): void {
                            $oldValues = $record->only(['card_status']);

                            $record->update([
                                'card_status' => Invitee::CARD_STATUS_CANCELLED,
                            ]);

                            AuditLogService::updated(
                                subject: $record,
                                eventId: $record->event_id,
                                description: 'Invitee card was cancelled.',
                                oldValues: $oldValues,
                                newValues: $record->only(['card_status']),
                            );

                            $this->brandedNotification()
                                ->title('eLive Card • Invitee cancelled successfully')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('delete_invitee')
                        ->label('Delete Invitee')
                        ->visible(fn (): bool => $this->canDeleteInviteesForOwner())
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Delete Invitee')
                        ->modalDescription('Use this only for test invitees. For real invitees, use Cancel Invitee instead.')
                        ->action(function (Invitee $record): void {
                            try {
                                AuditLogService::deleted(
                                    subject: $record,
                                    eventId: $record->event_id,
                                    description: 'Invitee was deleted.',
                                    metadata: [
                                        'serial_number' => $record->serial_number,
                                        'phone' => $record->phone,
                                    ],
                                );

                                $record->delete();

                                $this->brandedNotification()
                                    ->title('eLive Card • Invitee deleted successfully')
                                    ->success()
                                    ->send();
                            } catch (Throwable $e) {
                                $this->brandedNotification()
                                    ->title('eLive Card • Invitee could not be deleted')
                                    ->body('This invitee may already have related records such as SMS logs, generated cards, RSVP, or check-in records. Use Cancel Invitee instead.')
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),
                ])
                    ->label('Manage invitee')
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->color('primary')
                    ->iconButton()
                    ->tooltip('Manage invitee'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('regenerate_missing_qr_codes')
                        ->label('Regenerate Missing QR Codes')
                        ->visible(fn (): bool => $this->canManageInviteesForOwner())
                        ->icon('heroicon-o-qr-code')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Regenerate missing QR codes')
                        ->modalDescription('This will regenerate QR code images only for selected invitees whose QR image file is missing from storage. Existing QR images will be skipped.')
                        ->modalSubmitActionLabel('Regenerate QR Codes')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $regenerated = 0;
                            $skipped = 0;
                            $failed = 0;

                            foreach ($records as $record) {
                                try {
                                    if (! $record instanceof Invitee) {
                                        $skipped++;
                                        continue;
                                    }

                                    $qrPath = $record->qr_code_path ?: $record->qr_code;

                                    if (filled($qrPath) && Storage::disk('public')->exists($qrPath)) {
                                        $skipped++;
                                        continue;
                                    }

                                    $this->ensureInviteeQrCode($record);

                                    $record->refresh();

                                    $newQrPath = $record->qr_code_path ?: $record->qr_code;

                                    if (filled($newQrPath) && Storage::disk('public')->exists($newQrPath)) {
                                        $regenerated++;
                                    } else {
                                        $failed++;
                                    }
                                } catch (Throwable $e) {
                                    $failed++;
                                }
                            }

                            AuditLogService::system(
                                action: 'invitee_qr_regeneration',
                                description: 'Selected invitee QR code regeneration was processed.',
                                eventId: $this->getOwnerRecord()->id,
                                metadata: [
                                    'regenerated' => $regenerated,
                                    'skipped' => $skipped,
                                    'failed' => $failed,
                                ],
                            );

                            $this->brandedNotification()
                                ->title('eLive Card • QR regeneration completed')
                                ->body("Regenerated: {$regenerated}. Skipped: {$skipped}. Failed: {$failed}.")
                                ->color($failed > 0 ? 'warning' : 'success')
                                ->persistent()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('health_check_selected_invitees')
                        ->label('Health Check Selected Invitees')
                        ->visible(fn (): bool => $this->canManageInviteesForOwner())
                        ->icon('heroicon-o-shield-check')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Run invitee health check')
                        ->modalDescription('This will check selected invitees for missing QR images, missing generated cards, missing private links, invalid phone numbers, and inactive card status.')
                        ->modalSubmitActionLabel('Run Health Check')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $total = $records->count();
                            $healthy = 0;
                            $withIssues = 0;

                            $qrMissing = 0;
                            $cardMissing = 0;
                            $linkMissing = 0;
                            $invalidPhone = 0;
                            $inactiveCards = 0;

                            $pendingRsvp = 0;
                            $attendingRsvp = 0;
                            $notAttendingRsvp = 0;

                            $issueLines = [];

                            foreach ($records as $record) {
                                if (! $record instanceof Invitee) {
                                    continue;
                                }

                                $record->loadMissing(['latestGeneratedCard', 'cardType', 'event']);

                                $issues = [];

                                if (! $this->inviteeQrExists($record)) {
                                    $qrMissing++;
                                    $issues[] = 'QR missing';
                                }

                                if (! $this->generatedCardFileExists($record)) {
                                    $cardMissing++;
                                    $issues[] = 'card missing';
                                }

                                if (blank($record->short_code)) {
                                    $linkMissing++;
                                    $issues[] = 'private link missing';
                                }

                                if (blank($this->normalizePhone($record->phone))) {
                                    $invalidPhone++;
                                    $issues[] = 'invalid phone';
                                }

                                if ($record->card_status !== Invitee::CARD_STATUS_ACTIVE) {
                                    $inactiveCards++;
                                    $issues[] = 'card not active';
                                }

                                match ($record->rsvp_status) {
                                    Invitee::RSVP_ATTENDING => $attendingRsvp++,
                                    Invitee::RSVP_NOT_ATTENDING => $notAttendingRsvp++,
                                    default => $pendingRsvp++,
                                };

                                if (empty($issues)) {
                                    $healthy++;
                                    continue;
                                }

                                $withIssues++;

                                if (count($issueLines) < 8) {
                                    $issueLines[] = ($record->name ?: 'Invitee #' . $record->id) . ': ' . implode(', ', $issues);
                                }
                            }

                            $body = "Checked: {$total}. Healthy: {$healthy}. With issues: {$withIssues}.\n"
                                . "QR missing: {$qrMissing}. Card missing: {$cardMissing}. Private link missing: {$linkMissing}. Invalid phone: {$invalidPhone}. Inactive cards: {$inactiveCards}.\n"
                                . "RSVP — Pending/Maybe: {$pendingRsvp}. Attending: {$attendingRsvp}. Not attending: {$notAttendingRsvp}.";

                            if (! empty($issueLines)) {
                                $body .= "\n\nFirst issues:\n" . implode("\n", $issueLines);

                                if ($withIssues > count($issueLines)) {
                                    $remaining = $withIssues - count($issueLines);
                                    $body .= "\n...and {$remaining} more.";
                                }
                            }

                            $this->brandedNotification()
                                ->title('eLive Card • ' . ($withIssues > 0 ? 'Invitee health check completed with issues' : 'Invitee health check passed'))
                                ->body($body)
                                ->color($withIssues > 0 ? 'warning' : 'success')
                                ->persistent()
                                ->send();
                        }),


                    Tables\Actions\BulkAction::make('generate_missing_selected_cards')
                        ->label('Generate Missing Selected Cards')
                        ->visible(fn (): bool => $this->canGenerateCardsForOwner())
                        ->icon('heroicon-o-sparkles')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Generate missing cards for selected invitees')
                        ->modalDescription('This will queue only selected invitees whose cards are missing or failed. Already generated, sent, or currently generating cards will be skipped.')
                        ->modalSubmitActionLabel('Generate Missing Selected')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $queued = 0;
                            $skippedGenerated = 0;
                            $skippedGenerating = 0;
                            $failed = 0;

                            foreach ($records as $record) {
                                try {
                                    if (! $record instanceof Invitee) {
                                        continue;
                                    }

                                    if ($this->isInviteeCardGenerating($record)) {
                                        $skippedGenerating++;
                                        continue;
                                    }

                                    if ($this->inviteeHasUsableGeneratedCard($record)) {
                                        $skippedGenerated++;
                                        continue;
                                    }

                                    $this->prepareGeneratedCardForQueue($record);
                                    GenerateInviteeCardJob::dispatch($record->id);
                                    $queued++;
                                } catch (Throwable $e) {
                                    $failed++;
                                }
                            }

                            AuditLogService::system(
                                action: 'selected_cards_generate_missing',
                                description: 'Missing cards for selected invitees were processed.',
                                eventId: $this->getOwnerRecord()->id,
                                metadata: [
                                    'queued' => $queued,
                                    'already_generated' => $skippedGenerated,
                                    'already_generating' => $skippedGenerating,
                                    'failed' => $failed,
                                ],
                            );

                            $this->brandedNotification()
                                ->title('eLive Card • ' . ($queued > 0 ? 'Missing selected cards queued' : 'No missing selected cards'))
                                ->body("Queued: {$queued}. Already generated/sent: {$skippedGenerated}. Already generating: {$skippedGenerating}. Failed: {$failed}.")
                                ->color($failed > 0 ? 'warning' : ($queued > 0 ? 'success' : 'info'))
                                ->persistent()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('regenerate_selected_cards')
                        ->label('Regenerate Selected Cards')
                        ->visible(fn (): bool => $this->canGenerateCardsForOwner())
                        ->icon('heroicon-o-photo')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Regenerate selected invitation cards')
                        ->modalDescription('This will replace/rebuild cards for the selected invitees. Use this only after editing names, card type, table number, or template positions.')
                        ->modalSubmitActionLabel('Regenerate Selected Cards')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $queued = 0;
                            $skippedGenerating = 0;
                            $failed = 0;

                            foreach ($records as $record) {
                                try {
                                    if (! $record instanceof Invitee) {
                                        continue;
                                    }

                                    if ($this->isInviteeCardGenerating($record)) {
                                        $skippedGenerating++;
                                        continue;
                                    }

                                    $this->prepareGeneratedCardForQueue($record);
                                    GenerateInviteeCardJob::dispatch($record->id);
                                    $queued++;
                                } catch (Throwable $e) {
                                    $failed++;
                                }
                            }

                            AuditLogService::system(
                                action: 'selected_cards_regenerated',
                                description: 'Selected invitation cards were queued for regeneration.',
                                eventId: $this->getOwnerRecord()->id,
                                metadata: [
                                    'queued' => $queued,
                                    'already_generating' => $skippedGenerating,
                                    'failed' => $failed,
                                ],
                            );

                            $this->brandedNotification()
                                ->title('eLive Card • Selected card regeneration started')
                                ->body("Queued: {$queued}. Already generating: {$skippedGenerating}. Failed: {$failed}.")
                                ->color($failed > 0 ? 'warning' : 'success')
                                ->persistent()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('mark_generated_cards_as_sent')
                        ->label('Mark Generated Cards as Sent')
                        ->visible(fn (): bool => $this->canGenerateCardsForOwner())
                        ->icon('heroicon-o-paper-airplane')
                        ->color('info')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $marked = 0;
                            $skipped = 0;

                            foreach ($records as $record) {
                                if (! $record->latestGeneratedCard) {
                                    $skipped++;
                                    continue;
                                }

                                $record->latestGeneratedCard->markAsSent();
                                $marked++;
                            }

                            AuditLogService::system(
                                action: 'generated_cards_marked_sent',
                                description: 'Generated cards were marked as sent.',
                                eventId: $this->getOwnerRecord()->id,
                                metadata: [
                                    'marked' => $marked,
                                    'skipped' => $skipped,
                                ],
                            );

                            $this->brandedNotification()
                                ->title('eLive Card • Cards marked as sent')
                                ->body($marked . ' card(s) marked as sent. ' . $skipped . ' skipped because they have no generated card.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('mark_selected_attending')
                        ->label('Mark Selected Attending')
                        ->visible(fn (): bool => $this->canManageInviteesForOwner())
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each(function (Invitee $record): void {
                                $oldValues = $record->only([
                                    'rsvp_status',
                                    'rsvp_confirmed_at',
                                    'confirmed_guests',
                                ]);

                                $record->update([
                                    'rsvp_status' => Invitee::RSVP_ATTENDING,
                                    'rsvp_confirmed_at' => now(),
                                    'confirmed_guests' => max(1, (int) ($record->confirmed_guests ?: 1)),
                                ]);

                                AuditLogService::updated(
                                    subject: $record,
                                    eventId: $record->event_id,
                                    description: 'Invitee RSVP was marked as attending in bulk.',
                                    oldValues: $oldValues,
                                    newValues: $record->only([
                                        'rsvp_status',
                                        'rsvp_confirmed_at',
                                        'confirmed_guests',
                                    ]),
                                    metadata: ['bulk_action' => true],
                                );
                            });

                            $this->brandedNotification()
                                ->title('eLive Card • Selected invitees marked as attending')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('reset_selected_rsvp')
                        ->label('Reset Selected RSVP')
                        ->visible(fn (): bool => $this->canManageInviteesForOwner())
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each(function (Invitee $record): void {
                                $oldValues = $record->only([
                                    'rsvp_status',
                                    'rsvp_confirmed_at',
                                    'confirmed_guests',
                                ]);

                                $record->update([
                                    'rsvp_status' => Invitee::RSVP_PENDING,
                                    'rsvp_confirmed_at' => null,
                                    'confirmed_guests' => 0,
                                ]);

                                AuditLogService::updated(
                                    subject: $record,
                                    eventId: $record->event_id,
                                    description: 'Invitee RSVP was reset in bulk.',
                                    oldValues: $oldValues,
                                    newValues: $record->only([
                                        'rsvp_status',
                                        'rsvp_confirmed_at',
                                        'confirmed_guests',
                                    ]),
                                    metadata: ['bulk_action' => true],
                                );
                            });

                            $this->brandedNotification()
                                ->title('eLive Card • Selected RSVP records reset')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('cancel_selected_invitees')
                        ->label('Cancel Selected Invitees')
                        ->visible(fn (): bool => $this->canManageInviteesForOwner())
                        ->icon('heroicon-o-no-symbol')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each(function (Invitee $record): void {
                                $oldValues = $record->only(['card_status']);

                                $record->update([
                                    'card_status' => Invitee::CARD_STATUS_CANCELLED,
                                ]);

                                AuditLogService::updated(
                                    subject: $record,
                                    eventId: $record->event_id,
                                    description: 'Invitee was cancelled in bulk.',
                                    oldValues: $oldValues,
                                    newValues: $record->only(['card_status']),
                                    metadata: ['bulk_action' => true],
                                );
                            });

                            $this->brandedNotification()
                                ->title('eLive Card • Selected invitees cancelled successfully')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('delete_selected_invitees')
                        ->label('Delete Selected Invitees')
                        ->visible(fn (): bool => $this->canDeleteInviteesForOwner())
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $deleted = 0;
                            $failed = 0;

                            foreach ($records as $record) {
                                try {
                                    AuditLogService::deleted(
                                        subject: $record,
                                        eventId: $record->event_id,
                                        description: 'Invitee was deleted in bulk.',
                                        metadata: [
                                            'bulk_action' => true,
                                            'serial_number' => $record->serial_number,
                                            'phone' => $record->phone,
                                        ],
                                    );

                                    $record->delete();
                                    $deleted++;
                                } catch (Throwable $e) {
                                    $failed++;
                                }
                            }

                            $this->brandedNotification()
                                ->title('eLive Card • Bulk delete completed')
                                ->body("Deleted: {$deleted}. Failed: {$failed}. If some failed, use Cancel Selected Invitees.")
                                ->success()
                                ->persistent()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('send_selected_message')
                        ->label('Send Message to Selected')
                        ->visible(fn (): bool => $this->canSendMessagesForOwner())
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Send message to selected invitees')
                        ->modalDescription('Choose the channel and, for WhatsApp, the exact Meta template.')
                        ->form([
                            Forms\Components\Select::make('channel')
                                ->label('Channel')
                                ->options([
                                    'sms' => 'SMS',
                                    'whatsapp' => 'WhatsApp',
                                ])
                                ->default('sms')
                                ->required(),

                            Forms\Components\Select::make('template_type')
                                ->label('Message Type')
                                ->options([
                                    'invitation' => 'Invitation',
                                    'rsvp_pending_reminder' => 'RSVP Pending Reminder',
                                    'attending_reminder' => 'Attending Reminder',
                                    'event_day_reminder' => 'Event Day Reminder',
                                    'welcome_checkin' => 'Welcome After Check-in',
                                    'thank_you' => 'Thank You',
                                    'custom' => 'Custom',
                                ])
                                ->default('invitation')
                                ->required(),

                            Forms\Components\Select::make('message_template_id')
                                ->label('Exact WhatsApp Template')
                                ->options(fn (): array => $this->whatsappMessageTemplateOptions())
                                ->searchable()
                                ->preload()
                                ->helperText('For WhatsApp bulk sending, choose the exact Meta template. For SMS, leave this empty.'),
                        ])
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, array $data): void {
                            $sent = 0;
                            $skipped = 0;
                            $failed = 0;

                            foreach ($records as $record) {
                                if (! $record instanceof Invitee) {
                                    $skipped++;
                                    continue;
                                }

                                $result = $this->sendInviteeMessageUsingTemplate(
                                    invitee: $record,
                                    channel: $data['channel'],
                                    templateType: $data['template_type'],
                                    notifySkipped: false,
                                    messageTemplateId: ($data['channel'] ?? null) === 'whatsapp' && filled($data['message_template_id'] ?? null)
                                        ? (int) $data['message_template_id']
                                        : null,
                                );

                                if (in_array($result['status'], ['sent', 'logged'], true)) {
                                    $sent++;
                                } elseif ($result['status'] === 'skipped') {
                                    $skipped++;
                                } else {
                                    $failed++;
                                }
                            }

                            AuditLogService::system(
                                action: 'selected_messages_processed',
                                description: 'Messages to selected invitees were processed.',
                                eventId: $this->getOwnerRecord()->id,
                                metadata: [
                                    'channel' => $data['channel'],
                                    'template_type' => $data['template_type'],
                                    'sent_or_recorded' => $sent,
                                    'skipped' => $skipped,
                                    'failed' => $failed,
                                ],
                            );

                            $this->brandedNotification()
                                ->title('eLive Card • Selected messages processed')
                                ->body("Sent/recorded: {$sent}. Skipped: {$skipped}. Failed: {$failed}.")
                                ->color($failed > 0 ? 'warning' : 'success')
                                ->persistent()
                                ->send();
                        }),
                ]),
            ]);
    }



    protected function applyInviteeTableSearch(Builder $query, string $search): Builder
    {
        $search = trim(preg_replace('/\s+/', ' ', $search) ?? '');

        if ($search === '') {
            return $query;
        }

        $lowerSearch = mb_strtolower($search);
        $likeSearch = '%'.$this->escapeLikeValue($search).'%';

        $phoneDigits = preg_replace('/\D+/', '', $search) ?? '';
        $phoneCandidates = $this->phoneSearchCandidates($phoneDigits);

        $serialCompact = strtoupper(
            preg_replace('/[^A-Za-z0-9]+/', '', $search) ?? ''
        );
        $serialCandidates = $this->serialSearchCandidates($serialCompact);

        $statusCandidates = $this->statusSearchCandidates($lowerSearch);

        return $query->where(function (Builder $query) use (
            $likeSearch,
            $phoneCandidates,
            $serialCandidates,
            $statusCandidates,
        ): void {
            $query
                ->whereRaw('name ILIKE ?', [$likeSearch])
                ->orWhereRaw("COALESCE(email, '') ILIKE ?", [$likeSearch])
                ->orWhereRaw("COALESCE(category, '') ILIKE ?", [$likeSearch])
                ->orWhereRaw("COALESCE(table_number, '') ILIKE ?", [$likeSearch])
                ->orWhereRaw("COALESCE(short_code, '') ILIKE ?", [$likeSearch])
                ->orWhereHas('cardType', function (Builder $cardTypeQuery) use ($likeSearch): void {
                    $cardTypeQuery->whereRaw('name ILIKE ?', [$likeSearch]);
                });

            foreach ($phoneCandidates as $phoneCandidate) {
                $query->orWhere('phone', 'like', '%'.$phoneCandidate.'%');
            }

            foreach ($serialCandidates as $serialCandidate) {
                $query->orWhere('serial_number', 'like', '%'.$serialCandidate.'%');
            }

            foreach ($statusCandidates as $column => $values) {
                if (! empty($values)) {
                    $query->orWhereIn($column, $values);
                }
            }
        });
    }

    protected function escapeLikeValue(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value,
        );
    }

    protected function phoneSearchCandidates(string $digits): array
    {
        if ($digits === '') {
            return [];
        }

        $candidates = [$digits];

        if (str_starts_with($digits, '00255')) {
            $candidates[] = '255'.substr($digits, 5);
        }

        if (str_starts_with($digits, '0') && strlen($digits) >= 10) {
            $local = substr($digits, 1);
            $candidates[] = $local;
            $candidates[] = '255'.$local;
        }

        if (strlen($digits) === 9 && preg_match('/^[67]/', $digits)) {
            $candidates[] = '255'.$digits;
            $candidates[] = '0'.$digits;
        }

        if (str_starts_with($digits, '255') && strlen($digits) >= 12) {
            $local = substr($digits, 3);
            $candidates[] = $local;
            $candidates[] = '0'.$local;
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    protected function serialSearchCandidates(string $serialCompact): array
    {
        if ($serialCompact === '') {
            return [];
        }

        $candidates = [$serialCompact];

        if (preg_match('/^\d{6}$/', $serialCompact)) {
            $candidates[] = 'ELV-'.$serialCompact;
            $candidates[] = 'ELV'.$serialCompact;
        }

        if (preg_match('/^ELV(\d{6})$/', $serialCompact, $matches)) {
            $candidates[] = $matches[1];
            $candidates[] = 'ELV-'.$matches[1];
        }

        return array_values(array_unique($candidates));
    }

    protected function statusSearchCandidates(string $search): array
    {
        $search = str_replace(['-', '_'], ' ', $search);

        $matches = [
            'rsvp_status' => [],
            'card_status' => [],
            'sms_status' => [],
            'whatsapp_status' => [],
        ];

        $map = [
            'pending' => [
                'rsvp_status' => [Invitee::RSVP_PENDING],
                'card_status' => [Invitee::CARD_STATUS_PENDING],
                'sms_status' => [Invitee::SMS_STATUS_PENDING],
                'whatsapp_status' => ['sending'],
            ],
            'attending' => [
                'rsvp_status' => [Invitee::RSVP_ATTENDING],
            ],
            'not attending' => [
                'rsvp_status' => [Invitee::RSVP_NOT_ATTENDING],
            ],
            'maybe' => [
                'rsvp_status' => [Invitee::RSVP_MAYBE],
            ],
            'active' => [
                'card_status' => [Invitee::CARD_STATUS_ACTIVE],
            ],
            'cancelled' => [
                'card_status' => [Invitee::CARD_STATUS_CANCELLED],
            ],
            'canceled' => [
                'card_status' => [Invitee::CARD_STATUS_CANCELLED],
            ],
            'blocked' => [
                'card_status' => [Invitee::CARD_STATUS_BLOCKED],
            ],
            'used' => [
                'card_status' => [Invitee::CARD_STATUS_USED],
            ],
            'not sent' => [
                'sms_status' => [Invitee::SMS_STATUS_NOT_SENT],
                'whatsapp_status' => ['not_sent'],
            ],
            'sent' => [
                'sms_status' => [Invitee::SMS_STATUS_SENT],
                'whatsapp_status' => ['sent'],
            ],
            'delivered' => [
                'sms_status' => [Invitee::SMS_STATUS_DELIVERED],
                'whatsapp_status' => ['delivered'],
            ],
            'read' => [
                'whatsapp_status' => ['read'],
            ],
            'failed' => [
                'sms_status' => [Invitee::SMS_STATUS_FAILED],
                'whatsapp_status' => ['failed'],
            ],
            'sending' => [
                'whatsapp_status' => ['sending'],
            ],
        ];

        foreach ($map as $keyword => $groups) {
            if (! str_contains($search, $keyword)) {
                continue;
            }

            foreach ($groups as $column => $values) {
                $matches[$column] = array_merge($matches[$column], $values);
            }
        }

        foreach ($matches as $column => $values) {
            $matches[$column] = array_values(array_unique($values));
        }

        return $matches;
    }


    protected function sendEventMessagesFromToolbar(
        string $channel,
        string $templateType,
        string $recipientScope,
        string $actionTitle,
        ?int $messageTemplateId = null,
    ): void {
        $event = $this->getOwnerRecord();

        $query = Invitee::query()
            ->where('event_id', $event->id)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->whereNotNull('short_code')
            ->where('short_code', '!=', '');

        match ($recipientScope) {
            'not_sent' => $query->where(function ($query) use ($channel): void {
                if ($channel === 'whatsapp') {
                    $query
                        ->whereNull('whatsapp_status')
                        ->orWhere('whatsapp_status', '')
                        ->orWhere('whatsapp_status', 'not_sent')
                        ->orWhereNull('whatsapp_sent_at');

                    return;
                }

                $query
                    ->whereNull('message_status')
                    ->orWhere('message_status', '')
                    ->orWhere('message_status', 'not_sent')
                    ->orWhere('sms_status', Invitee::SMS_STATUS_NOT_SENT);
            }),

            'all_active' => $query->where('card_status', Invitee::CARD_STATUS_ACTIVE),

            'pending_rsvp' => $query->where(function ($query): void {
                $query
                    ->whereNull('rsvp_status')
                    ->orWhere('rsvp_status', '')
                    ->orWhere('rsvp_status', Invitee::RSVP_PENDING)
                    ->orWhere('rsvp_status', Invitee::RSVP_MAYBE);
            }),

            'attending' => $query->where('rsvp_status', Invitee::RSVP_ATTENDING),

            'checked_in' => $query->where('checked_in_count', '>', 0),

            'failed_sms' => $query->where('sms_status', Invitee::SMS_STATUS_FAILED),

            default => null,
        };

        $invitees = $query->get();

        if ($invitees->isEmpty()) {
            $this->brandedNotification()
                ->title('eLive Card • No invitees found')
                ->body('No invitees matched the selected option.')
                ->warning()
                ->send();

            return;
        }

        $template = $this->resolveMessageTemplate($channel, $templateType, $messageTemplateId);

        if (! $template) {
            $this->brandedNotification()
                ->title('eLive Card • Message template not found')
                ->body("No active {$channel} template found for type: {$templateType}. Please create or activate it in the Message Templates tab.")
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;
        $failedExamples = [];

        foreach ($invitees as $invitee) {
            if (! $invitee instanceof Invitee) {
                $skipped++;

                continue;
            }

            $result = $this->sendInviteeMessageUsingTemplate(
                invitee: $invitee,
                channel: $channel,
                templateType: $template->type ?: $templateType,
                notifySkipped: false,
                messageTemplateId: $template->id,
            );

            if (in_array($result['status'] ?? null, ['sent', 'logged'], true)) {
                $sent++;

                continue;
            }

            if (($result['status'] ?? null) === 'skipped') {
                $skipped++;

                continue;
            }

            $failed++;

            if (count($failedExamples) < 3) {
                $failedExamples[] = ($invitee->name ?: 'Invitee #' . $invitee->id) . ': ' . ($result['body'] ?? 'Failed');
            }
        }

        $body = "Sent/recorded: {$sent}. Skipped: {$skipped}. Failed: {$failed}.";

        if (! empty($failedExamples)) {
            $body .= "\n\nFirst errors:\n" . implode("\n", $failedExamples);
        }

        AuditLogService::system(
            action: 'event_messages_processed',
            description: $actionTitle,
            eventId: $event->id,
            metadata: [
                'channel' => $channel,
                'template_type' => $templateType,
                'recipient_scope' => $recipientScope,
                'message_template_id' => $messageTemplateId,
                'sent_or_recorded' => $sent,
                'skipped' => $skipped,
                'failed' => $failed,
            ],
        );

        $this->brandedNotification()
            ->title('eLive Card • ' . $actionTitle)
            ->body($body)
            ->color($failed > 0 ? 'warning' : 'success')
            ->persistent()
            ->send();
    }

    protected function activeMessageTemplate(string $channel, string $type): ?MessageTemplate
    {
        return MessageTemplate::query()
            ->where('event_id', $this->getOwnerRecord()->id)
            ->where('channel', $channel)
            ->where('type', $type)
            ->where('status', 'active')
            ->latest('id')
            ->first();
    }

    protected function messageTemplateById(int $messageTemplateId, ?string $channel = null): ?MessageTemplate
    {
        return MessageTemplate::query()
            ->where('event_id', $this->getOwnerRecord()->id)
            ->when($channel, fn ($query) => $query->where('channel', $channel))
            ->where('id', $messageTemplateId)
            ->first();
    }

    protected function resolveMessageTemplate(string $channel, string $type, ?int $messageTemplateId = null): ?MessageTemplate
    {
        if ($messageTemplateId) {
            return $this->messageTemplateById($messageTemplateId, $channel);
        }

        return $this->activeMessageTemplate($channel, $type);
    }

    protected function whatsappMessageTemplateOptions(): array
    {
        return MessageTemplate::query()
            ->where('event_id', $this->getOwnerRecord()->id)
            ->where('channel', 'whatsapp')
            ->whereNotNull('whatsapp_template_name')
            ->where('whatsapp_template_name', '!=', '')
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (MessageTemplate $template): array {
                $status = $template->status ?: 'unknown';
                $providerName = $template->whatsapp_template_name ?: 'missing_provider_name';

                return [
                    $template->id => "{$template->name} — {$providerName} [{$status}]",
                ];
            })
            ->toArray();
    }

    protected function renderMessageTemplate(MessageTemplate $template, Invitee $invitee): string
    {
        $message = app(MessageTemplateRenderer::class)
            ->render($template->content, $invitee);

        return $this->replaceMessagePlaceholders($message, $invitee);
    }

    protected function sendInviteeMessageUsingTemplate(
        Invitee $invitee,
        string $channel,
        string $templateType,
        bool $notifySkipped = true,
        ?int $messageTemplateId = null,
    ): array {
        $invitee->loadMissing(['event', 'cardType', 'latestGeneratedCard']);

        if (! in_array($channel, ['sms', 'whatsapp'], true)) {
            return [
                'status' => 'failed',
                'type' => 'danger',
                'title' => 'Invalid channel',
                'body' => 'Please choose SMS or WhatsApp.',
            ];
        }

        $template = $this->resolveMessageTemplate($channel, $templateType, $messageTemplateId);

        if (! $template) {
            return [
                'status' => 'failed',
                'type' => 'danger',
                'title' => 'Template not found',
                'body' => "No active {$channel} {$templateType} template found for this event. Create it from the Message Templates tab.",
            ];
        }

        $message = $this->renderMessageTemplate($template, $invitee);
        $effectiveTemplateType = $template->type ?: $templateType;

        if ($channel === 'sms') {
            return $this->sendSmsUsingTemplate($invitee, $effectiveTemplateType, $message);
        }

        return $this->recordWhatsappUsingTemplate(
            invitee: $invitee,
            template: $template,
            message: $message,
            templateType: $effectiveTemplateType,
        );
    }

    protected function sendSmsUsingTemplate(Invitee $invitee, string $templateType, string $message): array
    {
        try {
            $response = $this->sendCardLinkBySms($invitee, $message);

            if (($response['status'] ?? null) !== 'failed') {
                $response['title'] = match ($templateType) {
                    'invitation' => $response['title'] ?? 'SMS invitation sent',
                    'thank_you' => 'SMS thank you message sent',
                    'welcome_checkin' => 'Welcome SMS sent',
                    default => 'SMS message sent',
                };
            }

            return $response;
        } catch (Throwable $e) {
            return [
                'status' => 'failed',
                'type' => 'danger',
                'title' => 'SMS failed',
                'body' => $e->getMessage(),
            ];
        }
    }

    protected function recordWhatsappUsingTemplate(
        Invitee $invitee,
        MessageTemplate $template,
        string $message,
        string $templateType = 'invitation',
    ): array {
        $invitee->loadMissing(['latestGeneratedCard', 'cardType', 'event']);

        if ($invitee->card_status !== Invitee::CARD_STATUS_ACTIVE) {
            return [
                'status' => 'skipped',
                'type' => 'warning',
                'title' => 'Invitee not active',
                'body' => 'This invitee card is not active, so the WhatsApp message was not sent.',
            ];
        }

        if (blank($invitee->short_code)) {
            return [
                'status' => 'failed',
                'type' => 'danger',
                'title' => 'Missing private link',
                'body' => 'This invitee has no short code/private invitation link.',
            ];
        }

        if (blank($this->normalizePhone($invitee->phone))) {
            return [
                'status' => 'failed',
                'type' => 'danger',
                'title' => 'Invalid phone number',
                'body' => 'This invitee has an invalid Tanzania phone number.',
            ];
        }

        return $this->sendWhatsappCloudMessage(
            invitee: $invitee,
            message: $message,
            template: $template,
            templateType: $templateType,
        );
    }


    /**
     * Queue automatic card generation for a single invitee.
     *
     * This is used after manual add, Excel import, and card-sensitive edits.
     * It keeps the UI actions safe by avoiding duplicate generation jobs where possible.
     */
    protected function queueAutomaticCardGeneration(Invitee $invitee, bool $forceRegenerate = false): void
    {
        try {
            $invitee->refresh();

            if (! $forceRegenerate && $this->inviteeHasUsableGeneratedCard($invitee)) {
                return;
            }

            if ($this->isInviteeCardGenerating($invitee)) {
                return;
            }

            $this->ensureInviteeQrCode($invitee);

            $invitee->refresh();

            $this->prepareGeneratedCardForQueue($invitee);

            GenerateInviteeCardJob::dispatch($invitee->id);
        } catch (Throwable $e) {
            report($e);

            $this->brandedNotification()
                ->title('eLive Card • Invitee saved, but card generation was not started')
                ->body($e->getMessage())
                ->warning()
                ->persistent()
                ->send();
        }
    }


    protected function inviteeQrExists(Invitee $invitee): bool
    {
        $qrPath = $this->normalizePublicStoragePath($invitee->qr_code_path ?: $invitee->qr_code);

        return filled($qrPath) && Storage::disk('public')->exists($qrPath);
    }

    protected function generatedCardFileExists(Invitee $invitee): bool
    {
        $invitee->loadMissing('latestGeneratedCard');

        $path = $this->generatedCardStoragePath($invitee);

        return filled($path) && Storage::disk('public')->exists($path);
    }

    protected function generatedCardStoragePath(Invitee $invitee): ?string
    {
        $invitee->loadMissing('latestGeneratedCard');

        $card = $invitee->latestGeneratedCard;

        if (! $card) {
            return null;
        }

        $possiblePaths = [
            $card->file_path ?? null,
            $card->card_path ?? null,
            $card->path ?? null,
            $card->generated_card_path ?? null,
        ];

        foreach ($possiblePaths as $path) {
            $normalizedPath = $this->normalizePublicStoragePath($path);

            if (filled($normalizedPath)) {
                return $normalizedPath;
            }
        }

        return null;
    }

    protected function normalizePublicStoragePath(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $path = trim($path);

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $urlPath = parse_url($path, PHP_URL_PATH);
            $path = is_string($urlPath) ? $urlPath : $path;
        }

        $path = ltrim($path, '/');

        foreach (['storage/', 'public/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
            }
        }

        return filled($path) ? $path : null;
    }

    protected function buildInvitationMessage(Invitee $invitee): string
    {
        return $this->replaceMessagePlaceholders(
            "Habari #NAME#,\n\nKaribu kwenye #EVENT_NAME#. Fungua kadi yako ya mwaliko hapa: #INVITATION_LINK#\n\nSerial No: #SERIAL_NUMBER#",
            $invitee
        );
    }

    protected function replaceMessagePlaceholders(string $message, Invitee $invitee): string
    {
        $invitee->loadMissing(['cardType', 'event']);

        $event = $invitee->event ?? $this->getOwnerRecord();

        return str_replace(
            [
                '#NAME#',
                '#EVENT_NAME#',
                '#EVENT_DATE#',
                '#EVENT_TIME#',
                '#EVENT_VENUE#',
                '#INVITATION_LINK#',
                '#RSVP_LINK#',
                '#SERIAL_NUMBER#',
                '#CARD_TYPE#',
                '#GUEST_COUNT#',
                '#TABLE_NUMBER#',
            ],
            [
                (string) ($invitee->name ?? 'Mgeni'),
                (string) ($event?->name ?? $event?->title ?? 'our event'),
                $this->formatWhatsappEventDate($event),
                $this->formatWhatsappEventTime($event),
                (string) ($event?->venue_name ?? $event?->venue ?? '-'),
                $this->privateInvitationUrl($invitee),
                filled($invitee->rsvp_token) ? route('invitee.rsvp', $invitee->rsvp_token) : '-',
                (string) ($invitee->serial_number ?? '-'),
                (string) ($invitee->cardType?->name ?? '-'),
                (string) ($invitee->allowed_guests ?? 1),
                (string) ($invitee->table_number ?? '-'),
            ],
            $message
        );
    }

    protected function recordCardMessageAsSent(
        Invitee $invitee,
        string $channel,
        string $message,
        bool $notifySkipped = true,
    ): array {
        $invitee->loadMissing(['latestGeneratedCard', 'cardType', 'event']);

        if ($invitee->card_status !== Invitee::CARD_STATUS_ACTIVE) {
            return [
                'status' => 'skipped',
                'type' => 'warning',
                'title' => 'Invitee not active',
                'body' => 'This invitee card is not active, so the invitation was not sent.',
            ];
        }

        if (blank($invitee->short_code)) {
            return [
                'status' => 'failed',
                'type' => 'danger',
                'title' => 'Missing private link',
                'body' => 'This invitee has no short code/private invitation link.',
            ];
        }

        if (! in_array($channel, ['sms', 'whatsapp'], true)) {
            return [
                'status' => 'failed',
                'type' => 'danger',
                'title' => 'Invalid channel',
                'body' => 'Please choose SMS or WhatsApp.',
            ];
        }

        if ($channel === 'sms') {
            return $this->sendCardLinkBySms($invitee, $message);
        }

        return $this->sendWhatsappCloudMessage(
            invitee: $invitee,
            message: $message,
            template: null,
            templateType: 'invitation',
        );
    }

    protected function sendWhatsappCloudMessage(
        Invitee $invitee,
        string $message,
        ?MessageTemplate $template = null,
        string $templateType = 'invitation',
    ): array {
        $accessToken = config('services.whatsapp.access_token') ?: env('WHATSAPP_ACCESS_TOKEN');
        $phoneNumberId = config('services.whatsapp.phone_number_id') ?: env('WHATSAPP_PHONE_NUMBER_ID');

        if (blank($accessToken) || blank($phoneNumberId)) {
            return [
                'status' => 'failed',
                'type' => 'danger',
                'title' => 'WhatsApp not configured',
                'body' => 'Set WHATSAPP_ACCESS_TOKEN and WHATSAPP_PHONE_NUMBER_ID in your production environment.',
            ];
        }

        $phone = $this->normalizePhone($invitee->phone);

        if (blank($phone)) {
            return [
                'status' => 'failed',
                'type' => 'danger',
                'title' => 'Invalid phone number',
                'body' => 'This invitee phone number is invalid.',
            ];
        }

        $providerTemplateName = trim((string) ($template?->whatsapp_template_name ?? ''));

        /*
         * For real WhatsApp invitations, approved Meta templates are recommended.
         * If this message came from a saved WhatsApp template and no provider
         * template name is configured, stop instead of silently sending text.
         */
        if (blank($providerTemplateName) && $template instanceof MessageTemplate) {
            return [
                'status' => 'failed',
                'type' => 'danger',
                'title' => 'WhatsApp template missing',
                'body' => 'This WhatsApp message template has no approved provider template name. Add the exact Meta template name in the Message Templates tab.',
            ];
        }

        $messageType = $this->messageLogTypeFromTemplateType($templateType);

        $logId = $this->createMessageLog(
            invitee: $invitee,
            channel: 'whatsapp',
            message: $message,
            status: 'sending',
            errorMessage: null,
            messageType: $messageType,
        );

        $this->safeUpdateInvitee($invitee, [
            'message_status' => 'sending',
            'whatsapp_status' => 'sending',
            'last_message_channel' => 'whatsapp',
            'last_message_body' => $message,
        ]);

        try {
            $payload = $this->buildWhatsappCloudPayload(
                invitee: $invitee,
                phone: $phone,
                message: $message,
                template: $template,
                templateType: $templateType,
            );

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->asJson()
                ->timeout(30)
                ->post("https://graph.facebook.com/v23.0/{$phoneNumberId}/messages", $payload);

            $responseData = $response->json();

            if (! is_array($responseData)) {
                $responseData = [
                    'body' => $response->body(),
                ];
            }

            $providerMessageId = data_get($responseData, 'messages.0.id');

            if (! $response->successful() || blank($providerMessageId)) {
                $errorMessage = data_get($responseData, 'error.message')
                    ?: $response->body()
                    ?: 'WhatsApp API request failed.';

                $this->updateMessageLogAfterWhatsappSend(
                    logId: $logId,
                    status: 'failed',
                    providerMessageId: null,
                    responseData: [
                        'request_payload' => $payload,
                        'response' => $responseData,
                        'http_status' => $response->status(),
                    ],
                    errorMessage: $errorMessage,
                );

                $this->safeUpdateInvitee($invitee, [
                    'message_status' => 'failed',
                    'whatsapp_status' => 'failed',
                    'whatsapp_failed_at' => now(),
                    'last_message_channel' => 'whatsapp',
                    'last_message_body' => $message,
                ]);

                return [
                    'status' => 'failed',
                    'type' => 'danger',
                    'title' => 'WhatsApp failed',
                    'body' => $errorMessage,
                ];
            }

            $now = now();

            DB::transaction(function () use ($invitee, $message, $now, $logId, $providerMessageId, $responseData, $payload, $response): void {
                $this->safeUpdateInvitee($invitee, [
                    'message_status' => 'sent',
                    'sent_at' => $now,

                    'whatsapp_status' => 'sent',
                    'whatsapp_message_id' => $providerMessageId,
                    'whatsapp_sent_at' => $now,
                    'whatsapp_failed_at' => null,

                    'last_message_channel' => 'whatsapp',
                    'last_message_body' => $message,
                ]);

                $this->markLatestGeneratedCardAsSent($invitee, $now);

                $this->updateMessageLogAfterWhatsappSend(
                    logId: $logId,
                    status: 'sent',
                    providerMessageId: $providerMessageId,
                    responseData: [
                        'request_payload' => $payload,
                        'response' => $responseData,
                        'http_status' => $response->status(),
                    ],
                    errorMessage: null,
                );
            });

            return [
                'status' => 'sent',
                'type' => 'success',
                'title' => 'WhatsApp sent successfully',
                'body' => 'WhatsApp message sent to ' . $phone . '. Message ID: ' . $providerMessageId,
            ];
        } catch (Throwable $e) {
            $this->updateMessageLogAfterWhatsappSend(
                logId: $logId,
                status: 'failed',
                providerMessageId: null,
                responseData: [
                    'exception' => $e->getMessage(),
                ],
                errorMessage: $e->getMessage(),
            );

            $this->safeUpdateInvitee($invitee, [
                'message_status' => 'failed',
                'whatsapp_status' => 'failed',
                'whatsapp_failed_at' => now(),
                'last_message_channel' => 'whatsapp',
                'last_message_body' => $message,
            ]);

            return [
                'status' => 'failed',
                'type' => 'danger',
                'title' => 'WhatsApp failed',
                'body' => $e->getMessage(),
            ];
        }
    }

    protected function buildWhatsappCloudPayload(
        Invitee $invitee,
        string $phone,
        string $message,
        ?MessageTemplate $template = null,
        string $templateType = 'invitation',
    ): array {
        $providerTemplateName = trim((string) ($template?->whatsapp_template_name ?? ''));

        if ($providerTemplateName !== '') {
            $components = [];

            /*
             * The Meta template `event_invitation` has an image header.
             * WhatsApp Cloud API requires the header image parameter to be sent
             * together with the body parameters.
             */
            if ($providerTemplateName === 'event_invitation') {
                $headerImageUrl = $this->whatsappHeaderImageUrl($invitee);

                if (filled($headerImageUrl)) {
                    $components[] = [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'image',
                                'image' => [
                                    'link' => $headerImageUrl,
                                ],
                            ],
                        ],
                    ];
                }
            }

            $components[] = [
                'type' => 'body',
                'parameters' => $this->buildWhatsappTemplateBodyParameters($invitee),
            ];

            foreach ($this->buildWhatsappTemplateButtonComponents($invitee, $templateType) as $buttonComponent) {
                $components[] = $buttonComponent;
            }

            return [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $phone,
                'type' => 'template',
                'template' => [
                    'name' => $providerTemplateName,
                    'language' => [
                        'code' => $this->whatsappTemplateLanguageCode($template),
                    ],
                    'components' => $components,
                ],
            ];
        }

        /*
         * Fallback for manual WhatsApp text messages only.
         * Invitation/reminder templates should normally have whatsapp_template_name.
         */
        return [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'text',
            'text' => [
                'preview_url' => true,
                'body' => $message,
            ],
        ];
    }

    protected function buildWhatsappTemplateButtonComponents(Invitee $invitee, string $templateType): array
    {
        /*
         * These payloads must match WhatsAppWebhookController/RsvpService.
         *
         * Button index 0 = Attending
         * Button index 1 = Not Attending
         * Button index 2 = LOCATION URL button
         *
         * Meta template button order must be:
         * 1. Quick Reply
         * 2. Quick Reply
         * 3. Visit Website dynamic URL: https://digital.elive.co.tz/l/{{1}}
         */
        if (! in_array($templateType, [
            'invitation',
            'rsvp_pending_reminder',
        ], true)) {
            return [];
        }

        return [
            [
                'type' => 'button',
                'sub_type' => 'quick_reply',
                'index' => '0',
                'parameters' => [
                    [
                        'type' => 'payload',
                        'payload' => 'rsvp_attending',
                    ],
                ],
            ],
            [
                'type' => 'button',
                'sub_type' => 'quick_reply',
                'index' => '1',
                'parameters' => [
                    [
                        'type' => 'payload',
                        'payload' => 'rsvp_not_attending',
                    ],
                ],
            ],
            [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '2',
                'parameters' => [
                    [
                        'type' => 'text',
                        'text' => (string) $invitee->short_code,
                    ],
                ],
            ],
        ];
    }

    protected function whatsappTemplateLanguageCode(?MessageTemplate $template): string
    {
        if (
            $template instanceof MessageTemplate
            && Schema::hasColumn($template->getTable(), 'whatsapp_language_code')
            && filled($template->whatsapp_language_code ?? null)
        ) {
            return (string) $template->whatsapp_language_code;
        }

        return (string) config('services.whatsapp.template_language', env('WHATSAPP_TEMPLATE_LANGUAGE', 'en'));
    }

    protected function whatsappHeaderImageUrl(Invitee $invitee): ?string
    {
        $invitee->loadMissing(['latestGeneratedCard', 'event']);

        $cardUrl = $invitee->generated_card_url ?? null;

        if (filled($cardUrl)) {
            return $cardUrl;
        }

        $cardPath = $this->generatedCardStoragePath($invitee);

        if (filled($cardPath) && Storage::disk('public')->exists($cardPath)) {
            return Storage::disk('public')->url($cardPath);
        }

        $qrPath = $this->normalizePublicStoragePath($invitee->qr_code_path ?: $invitee->qr_code);

        if (filled($qrPath) && Storage::disk('public')->exists($qrPath)) {
            return Storage::disk('public')->url($qrPath);
        }

        /*
         * Fallback image for the Meta template header.
         * Put a public image here so Meta can fetch it:
         * public/images/whatsapp-template-header.jpg
         */
        return asset('images/whatsapp-template-header.jpg');
    }

    protected function buildWhatsappTemplateBodyParameters(Invitee $invitee): array
    {
        $invitee->loadMissing(['event', 'cardType']);

        $event = $invitee->event ?? $this->getOwnerRecord();

        /*
         * Meta template: event_invitation • English
         * Body variable order:
         * {{1}} Invitee name
         * {{2}} Event name
         * {{3}} Card type
         * {{4}} Venue
         * {{5}} Time
         *
         * Keep this list at exactly 5 parameters unless the Meta template is changed.
         */
        return [
            [
                'type' => 'text',
                'text' => (string) ($invitee->name ?? 'Mgeni'),
            ],
            [
                'type' => 'text',
                'text' => (string) ($event?->name ?? $event?->title ?? 'Tukio'),
            ],
            [
                'type' => 'text',
                'text' => (string) ($invitee->cardType?->name ?? '-'),
            ],
            [
                'type' => 'text',
                'text' => (string) ($event?->venue_name ?? $event?->venue ?? '-'),
            ],
            [
                'type' => 'text',
                'text' => $this->formatWhatsappEventTime($event),
            ],
        ];
    }

    protected function formatWhatsappEventDate($event): string
    {
        $date = $event?->event_date
            ?? $event?->date
            ?? $event?->starts_at
            ?? null;

        if (blank($date)) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($date)->format('d M Y');
        } catch (Throwable) {
            return (string) $date;
        }
    }

    protected function formatWhatsappEventTime($event): string
    {
        $time = $event?->start_time
            ?? $event?->time
            ?? $event?->starts_at
            ?? null;

        if (blank($time)) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($time)->format('h:i A');
        } catch (Throwable) {
            return (string) $time;
        }
    }

    protected function messageLogTypeFromTemplateType(string $templateType): string
    {
        return match ($templateType) {
            'rsvp_pending_reminder' => 'rsvp_pending_reminder',
            'attending_reminder' => 'attending_reminder',
            'event_day_reminder' => 'event_day_reminder',
            'welcome_checkin' => 'welcome_checkin',
            'thank_you' => 'thank_you',
            'custom' => 'custom',
            default => 'invitation_card',
        };
    }


    protected function updateMessageLogAfterWhatsappSend(
        ?int $logId,
        string $status,
        ?string $providerMessageId,
        array|string|null $responseData,
        ?string $errorMessage = null,
    ): void {
        if (! $logId || ! Schema::hasTable('message_logs')) {
            return;
        }

        $now = now();

        $data = [
            'status' => $status,
            'provider_message_id' => $providerMessageId,
            'provider_response' => is_array($responseData) ? json_encode($responseData) : $responseData,
            'response' => is_array($responseData) ? json_encode($responseData) : $responseData,
            'error_message' => $errorMessage,
            'error' => $errorMessage,
            'sent_at' => $status === 'sent' ? $now : null,
            'updated_at' => $now,
        ];

        $updateable = collect($data)
            ->filter(fn ($value, $key) => Schema::hasColumn('message_logs', $key))
            ->toArray();

        if (! empty($updateable)) {
            DB::table('message_logs')
                ->where('id', $logId)
                ->update($updateable);
        }
    }

    protected function sendCardLinkBySms(Invitee $invitee, string $message): array
    {
        try {
            $response = app(SmsService::class)->sendCardLink($invitee, $message);

            $now = now();

            $this->safeUpdateInvitee($invitee->fresh() ?? $invitee, [
                'message_status' => 'sent',
                'sms_status' => Invitee::SMS_STATUS_SENT,
                'invitation_sms_status' => 'sent',
                'sent_at' => $now,
                'sms_sent_at' => $now,
                'last_message_channel' => 'sms',
                'last_message_body' => $message,
            ]);

            $this->markLatestGeneratedCardAsSent($invitee->fresh(['latestGeneratedCard']) ?? $invitee, $now);

            $driver = $response['driver'] ?? config('services.sms.driver', env('SMS_DRIVER', 'log'));
            $messageId = $response['message_id'] ?? $response['id'] ?? null;

            return [
                'status' => 'sent',
                'type' => 'success',
                'title' => $driver === 'log' ? 'SMS invitation logged' : 'SMS invitation sent',
                'body' => ($driver === 'log'
                    ? 'SMS logged only because SMS_DRIVER=log. '
                    : 'SMS sent successfully. ')
                    . ($messageId ? "Message ID: {$messageId}. " : '')
                    . "Link: {$this->privateInvitationUrl($invitee)}",
            ];
        } catch (Throwable $e) {
            $now = now();

            $this->safeUpdateInvitee($invitee, [
                'message_status' => 'failed',
                'sms_status' => Invitee::SMS_STATUS_FAILED,
                'invitation_sms_status' => 'failed',
                'sms_error' => $e->getMessage(),
                'last_message_channel' => 'sms',
                'last_message_body' => $message,
            ]);

            // SmsService already records a failed message log. This fallback is only for safety.
            if (! Schema::hasTable('message_logs')) {
                return [
                    'status' => 'failed',
                    'type' => 'danger',
                    'title' => 'SMS failed',
                    'body' => $e->getMessage(),
                ];
            }

            return [
                'status' => 'failed',
                'type' => 'danger',
                'title' => 'SMS failed',
                'body' => $e->getMessage(),
            ];
        }
    }

    protected function markLatestGeneratedCardAsSent(Invitee $invitee, $sentAt = null): void
    {
        $sentAt ??= now();
        $invitee->loadMissing('latestGeneratedCard');

        if ($invitee->latestGeneratedCard && method_exists($invitee->latestGeneratedCard, 'markAsSent')) {
            $invitee->latestGeneratedCard->markAsSent();
            return;
        }

        if ($invitee->latestGeneratedCard) {
            $invitee->latestGeneratedCard->forceFill([
                'status' => GeneratedCard::STATUS_SENT,
                'sent_at' => $sentAt,
            ])->saveQuietly();
        }
    }

    protected function createMessageLog(
        Invitee $invitee,
        string $channel,
        string $message,
        string $status,
        ?string $errorMessage = null,
        string $messageType = 'invitation_card',
    ): ?int {
        if (! Schema::hasTable('message_logs')) {
            return null;
        }

        $now = now();

        /*
         * Your existing message_logs table has some required columns such as `type`.
         * Keep this insert defensive so it works with older/newer table structures.
         */
        $data = [
            'event_id' => $invitee->event_id,
            'invitee_id' => $invitee->id,
            'channel' => $channel,
            'type' => $messageType,
            'recipient' => $this->normalizePhone($invitee->phone) ?: $invitee->phone,
            'phone' => $this->normalizePhone($invitee->phone) ?: $invitee->phone,
            'message' => $message,
            'body' => $message,
            'status' => $status,
            'provider_message_id' => null,
            'provider' => $channel === 'whatsapp' ? 'WhatsApp Cloud API' : null,
            'error_message' => $errorMessage,
            'error' => $errorMessage,
            'sent_by' => Auth::id(),
            'user_id' => Auth::id(),
            'sent_at' => $status === 'sent' ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $insertable = collect($data)
            ->filter(fn ($value, $key) => Schema::hasColumn('message_logs', $key))
            ->toArray();

        if (Schema::hasColumn('message_logs', 'type') && blank($insertable['type'] ?? null)) {
            $insertable['type'] = $messageType;
        }

        if (Schema::hasColumn('message_logs', 'channel') && blank($insertable['channel'] ?? null)) {
            $insertable['channel'] = $channel;
        }

        if (Schema::hasColumn('message_logs', 'status') && blank($insertable['status'] ?? null)) {
            $insertable['status'] = $status;
        }

        return DB::table('message_logs')->insertGetId($insertable);
    }

    protected function safeUpdateInvitee(Invitee $invitee, array $data): void
    {
        $filtered = collect($data)
            ->filter(fn ($value, $key) => Schema::hasColumn($invitee->getTable(), $key))
            ->toArray();

        if (! empty($filtered)) {
            $invitee->forceFill($filtered)->saveQuietly();
            $invitee->refresh();
        }
    }

    protected function privateInvitationUrl(Invitee $invitee): string
    {
        if (filled($invitee->short_code)) {
            return route('invitee.page', $invitee->short_code);
        }

        return '-';
    }

    protected function importInviteesFromExcel(mixed $file): void
    {
        if (! $file instanceof TemporaryUploadedFile) {
            $this->brandedNotification()
                ->title('eLive Card • Invalid Excel upload')
                ->body('Please upload the Excel file again.')
                ->danger()
                ->send();

            return;
        }

        $fullPath = $file->getRealPath();

        if (! $fullPath || ! file_exists($fullPath)) {
            $this->brandedNotification()
                ->title('eLive Card • Excel file not found')
                ->body('The temporary uploaded file could not be found. Please upload again.')
                ->danger()
                ->send();

            return;
        }

        $spreadsheet = IOFactory::load($fullPath);
        $rows = collect($spreadsheet->getActiveSheet()->toArray(null, true, true, true));

        if ($rows->count() < 2) {
            $this->brandedNotification()
                ->title('eLive Card • Empty Excel file')
                ->body('The Excel file must contain headings and at least one invitee row.')
                ->danger()
                ->send();

            return;
        }

        $headingRow = collect($rows->first());

        $headings = $headingRow
            ->mapWithKeys(function ($heading, $columnLetter) {
                $cleanHeading = Str::of((string) $heading)
                    ->trim()
                    ->lower()
                    ->replace(' ', '_')
                    ->replace('-', '_')
                    ->toString();

                return [$columnLetter => $cleanHeading];
            })
            ->filter()
            ->toArray();

        $requiredColumns = ['name', 'phone', 'card_type'];

        foreach ($requiredColumns as $column) {
            if (! in_array($column, $headings, true)) {
                $this->brandedNotification()
                    ->title('eLive Card • Missing required column')
                    ->body("Your Excel file is missing the required column: {$column}")
                    ->danger()
                    ->send();

                return;
            }
        }

        $eventId = $this->getOwnerRecord()->id;

        $created = 0;
        $skipped = 0;
        $errors = [];
        $namesInFile = [];

        foreach ($rows->skip(1) as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;

            $data = [];

            foreach ($headings as $columnLetter => $heading) {
                $data[$heading] = isset($row[$columnLetter])
                    ? trim((string) $row[$columnLetter])
                    : null;
            }

            $name = trim((string) ($data['name'] ?? ''));
            $rawPhone = trim((string) ($data['phone'] ?? ''));
            $phone = $this->normalizePhone($rawPhone);
            $cardTypeName = trim((string) ($data['card_type'] ?? ''));

            if (blank($name) && blank($rawPhone) && blank($cardTypeName)) {
                continue;
            }

            if (blank($name)) {
                $skipped++;
                $errors[] = "Row {$rowNumber}: name is required.";
                continue;
            }

            if (blank($rawPhone)) {
                $skipped++;
                $errors[] = "Row {$rowNumber}: phone is required.";
                continue;
            }

            if (blank($phone)) {
                $skipped++;
                $errors[] = "Row {$rowNumber}: phone '{$rawPhone}' is invalid.";
                continue;
            }

            if (blank($cardTypeName)) {
                $skipped++;
                $errors[] = "Row {$rowNumber}: card_type is required.";
                continue;
            }

            $normalizedName = $this->normalizeName($name);

            if (in_array($normalizedName, $namesInFile, true)) {
                $skipped++;
                $errors[] = "Row {$rowNumber}: invitee name '{$name}' is duplicated in this Excel file.";
                continue;
            }

            $namesInFile[] = $normalizedName;

            $existingInvitee = Invitee::query()
                ->where('event_id', $eventId)
                ->whereRaw('LOWER(TRIM(name)) = ?', [$normalizedName])
                ->first();

            if ($existingInvitee) {
                $skipped++;
                $errors[] = "Row {$rowNumber}: invitee name '{$name}' already exists for this event.";
                continue;
            }

            $cardType = CardType::where('event_id', $eventId)
                ->where('is_active', true)
                ->whereRaw('LOWER(TRIM(name)) = ?', [$this->normalizeName($cardTypeName)])
                ->first();

            if (! $cardType) {
                $skipped++;
                $errors[] = "Row {$rowNumber}: card type '{$cardTypeName}' was not found or is inactive for this event.";
                continue;
            }

            $allowedGuests = max(1, (int) ($cardType->allowed_people ?? 1));

            $invitee = Invitee::create($this->prepareInviteeData([
                'name' => $name,
                'phone' => $phone,
                'email' => $data['email'] ?? null,
                'card_type_id' => $cardType->id,
                'allowed_guests' => $allowedGuests,
                'category' => $data['category'] ?? null,
                'table_number' => $data['table_number'] ?? null,
            ]));

            AuditLogService::created(
                subject: $invitee,
                eventId: $invitee->event_id,
                description: 'Invitee was imported from Excel.',
                metadata: [
                    'source' => 'excel',
                    'row_number' => $rowNumber,
                    'card_type_id' => $invitee->card_type_id,
                ],
            );

            $this->queueAutomaticCardGeneration($invitee);

            $created++;
        }

        $message = "Imported: {$created}. Skipped: {$skipped}.";

        if (! empty($errors)) {
            $message .= "\n\n" . collect($errors)->take(5)->implode("\n");

            if (count($errors) > 5) {
                $message .= "\n...and more.";
            }
        }

        AuditLogService::system(
            action: 'invitees_excel_imported',
            description: 'Invitee Excel import was completed.',
            eventId: $eventId,
            metadata: [
                'created' => $created,
                'skipped' => $skipped,
                'error_count' => count($errors),
            ],
        );

        $this->brandedNotification()
            ->title('eLive Card • Excel import completed')
            ->body($message)
            ->success()
            ->persistent()
            ->send();
    }

    protected function ensureInviteeQrCode(Invitee $invitee): void
    {
        if (method_exists($invitee, 'generateQrCode')) {
            $invitee->generateQrCode();
            $invitee->refresh();

            return;
        }

        if (blank($invitee->short_code)) {
            $invitee->forceFill([
                'short_code' => $this->generateUniqueShortCode(),
            ])->saveQuietly();

            $invitee->refresh();
        }

        if (blank($invitee->serial_number)) {
            $invitee->forceFill([
                'serial_number' => $this->generateUniqueSerialNumber(),
            ])->saveQuietly();

            $invitee->refresh();
        }

        $qrPath = 'events/' . $invitee->event_id . '/qr-codes/' . $invitee->serial_number . '.png';

        if (Storage::disk('public')->exists($qrPath)) {
            $invitee->forceFill([
                'qr_code_path' => $qrPath,
                'qr_code' => $qrPath,
            ])->saveQuietly();

            return;
        }

        Storage::disk('public')->makeDirectory('events/' . $invitee->event_id . '/qr-codes');

        $qrContent = route('invitee.page', $invitee->short_code);

        $qrImage = QrCode::format('png')
            ->size(500)
            ->margin(2)
            ->generate($qrContent);

        Storage::disk('public')->put($qrPath, $qrImage);

        $invitee->forceFill([
            'qr_code_path' => $qrPath,
            'qr_code' => $qrPath,
        ])->saveQuietly();
    }

    protected function isInviteeCardGenerating(Invitee $invitee): bool
    {
        $invitee->loadMissing('latestGeneratedCard');

        return $invitee->latestGeneratedCard?->status === GeneratedCard::STATUS_GENERATING;
    }

    protected function inviteeHasUsableGeneratedCard(Invitee $invitee): bool
    {
        $cards = GeneratedCard::query()
            ->where('event_id', $invitee->event_id)
            ->where('invitee_id', $invitee->id)
            ->whereIn('status', [GeneratedCard::STATUS_GENERATED, GeneratedCard::STATUS_SENT])
            ->latest('updated_at')
            ->get();

        foreach ($cards as $card) {
            $possiblePaths = [
                $card->file_path ?? null,
                $card->card_path ?? null,
                $card->path ?? null,
                $card->generated_card_path ?? null,
            ];

            foreach ($possiblePaths as $path) {
                $normalizedPath = $this->normalizePublicStoragePath($path);

                if (filled($normalizedPath) && Storage::disk('public')->exists($normalizedPath)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function prepareGeneratedCardForQueue(Invitee $invitee): GeneratedCard
    {
        $templateId = $this->getOwnerRecord()
            ->cardTemplates()
            ->latest()
            ->value('id');

        if (! $templateId) {
            throw new \Exception('No card template found for this event. Please upload a card template first.');
        }

        /*
        |--------------------------------------------------------------------------
        | Important
        |--------------------------------------------------------------------------
        | Do not use GeneratedCard::create() here.
        |
        | The generated_cards table has a unique rule for:
        | invitee_id + card_template_id
        |
        | If a generated card already exists, create() causes a duplicate key error.
        | updateOrCreate() safely reuses the existing record and marks it as generating.
        */
        return GeneratedCard::updateOrCreate(
            [
                'invitee_id' => $invitee->id,
                'card_template_id' => $templateId,
            ],
            [
                'event_id' => $this->getOwnerRecord()->id,
                'status' => GeneratedCard::STATUS_GENERATING,
                'generated_at' => null,
                'sent_at' => null,
            ]
        );
    }

    protected function prepareInviteeData(array $data): array
    {
        $data['event_id'] = $this->getOwnerRecord()->id;

        $data['name'] = trim(
            preg_replace('/\s+/', ' ', (string) ($data['name'] ?? '')) ?? ''
        );

        if ($data['name'] === '') {
            throw ValidationException::withMessages([
                'name' => 'Invitee name is required.',
            ]);
        }

        $data['phone'] = $this->normalizePhone($data['phone'] ?? null);

        if (! $data['phone']) {
            throw ValidationException::withMessages([
                'phone' => 'Invalid Tanzania phone number.',
            ]);
        }

        if (! empty($data['card_type_id'])) {
            $cardType = CardType::find($data['card_type_id']);

            if ($cardType) {
                $data['allowed_guests'] = $cardType->allowed_people ?? 1;
            }
        }

        $data['serial_number'] = filled($data['serial_number'] ?? null)
            ? $this->normalizeSerialNumber($data['serial_number'])
            : $this->generateUniqueSerialNumber();
        $data['short_code'] = $data['short_code'] ?? $this->generateUniqueShortCode();

        if (empty($data['qr_token'])) {
            $data['qr_token'] = Str::random(64);
        }

        if (empty($data['qr_token_hash'])) {
            $data['qr_token_hash'] = hash('sha256', $data['qr_token']);
        }

        $data['rsvp_status'] = $data['rsvp_status'] ?? Invitee::RSVP_PENDING;
        $data['card_status'] = $data['card_status'] ?? Invitee::CARD_STATUS_ACTIVE;
        $data['message_status'] = $data['message_status'] ?? 'not_sent';
        $data['sms_status'] = $data['sms_status'] ?? Invitee::SMS_STATUS_NOT_SENT;
        $data['invitation_sms_status'] = $data['invitation_sms_status'] ?? 'pending';
        $data['reminder_sms_status'] = $data['reminder_sms_status'] ?? 'pending';
        $data['final_sms_status'] = $data['final_sms_status'] ?? 'pending';
        $data['rsvp_token'] = $data['rsvp_token'] ?? Str::random(48);
        $data['confirmed_guests'] = max(0, (int) ($data['confirmed_guests'] ?? 0));
        $data['checked_in_count'] = max(0, (int) ($data['checked_in_count'] ?? 0));
        $data['allowed_guests'] = max(1, (int) ($data['allowed_guests'] ?? 1));

        if ($data['confirmed_guests'] > $data['allowed_guests']) {
            $data['confirmed_guests'] = $data['allowed_guests'];
        }

        return $data;
    }

    protected function validateNoDuplicateInviteeName(?string $name, ?int $ignoreInviteeId = null): void
    {
        if (blank($name)) {
            return;
        }

        $normalizedName = $this->normalizeName($name);

        $exists = Invitee::where('event_id', $this->getOwnerRecord()->id)
            ->when($ignoreInviteeId, fn ($query) => $query->where('id', '!=', $ignoreInviteeId))
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalizedName])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => "Invitee name '{$name}' already exists for this event.",
            ]);
        }
    }

    protected function normalizeName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/\s+/', ' ', $name) ?? '';

        return mb_strtolower($name);
    }

    protected function generateUniqueSerialNumber(): string
    {
        /*
         * Simple random serial number for manual gate check-in.
         *
         * Format:
         * ELV-483927
         *
         * The number is unique within the current event, so the same number can
         * still be used in another event without affecting this event's gate search.
         */
        do {
            $serialNumber = 'ELV-' . random_int(100000, 999999);
        } while (
            Invitee::where('event_id', $this->getOwnerRecord()->id)
                ->where('serial_number', $serialNumber)
                ->exists()
        );

        return $serialNumber;
    }

    protected function normalizeSerialNumber(?string $serialNumber): ?string
    {
        if (blank($serialNumber)) {
            return null;
        }

        $serialNumber = strtoupper(trim($serialNumber));
        $serialNumber = preg_replace('/\s+/', '', $serialNumber);

        if (preg_match('/^\d{6}$/', $serialNumber)) {
            return 'ELV-' . $serialNumber;
        }

        if (preg_match('/^ELV-\d{6}$/', $serialNumber)) {
            return $serialNumber;
        }

        return $serialNumber;
    }

    protected function generateUniqueShortCode(): string
    {
        do {
            $shortCode = strtoupper(Str::random(6));
        } while (Invitee::where('short_code', $shortCode)->exists());

        return $shortCode;
    }

    protected function normalizePhone(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $phone = preg_replace('/\D+/', '', $phone);

        if (blank($phone)) {
            return null;
        }

        if (str_starts_with($phone, '00255')) {
            $phone = '255' . substr($phone, 5);
        }

        if (str_starts_with($phone, '0') && strlen($phone) === 10) {
            $phone = '255' . substr($phone, 1);
        }

        if (strlen($phone) === 9 && preg_match('/^[67]/', $phone)) {
            $phone = '255' . $phone;
        }

        if (! preg_match('/^255[67]\d{8}$/', $phone)) {
            return null;
        }

        return $phone;
    }
}