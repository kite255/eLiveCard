<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Jobs\GenerateInviteeCardJob;
use App\Models\CardType;
use App\Models\GeneratedCard;
use App\Models\Invitee;
use App\Services\SmsService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

class InviteesRelationManager extends RelationManager
{
    protected static string $relationship = 'invitees';

    protected static ?string $title = 'Invitees';

    protected static ?string $modelLabel = 'Invitee';

    protected static ?string $pluralModelLabel = 'Invitees';

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
                            ->placeholder('Example: Guest Name'),

                        Forms\Components\TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->required()
                            ->maxLength(30)
                            ->placeholder('Example: 0711111111')
                            ->helperText('Accepted formats: 0711111111, 711111111, +255711111111, or 255711111111.'),

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
                            ->options([
                                Invitee::RSVP_PENDING => 'Pending',
                                Invitee::RSVP_ATTENDING => 'Attending',
                                Invitee::RSVP_NOT_ATTENDING => 'Not Attending',
                                Invitee::RSVP_MAYBE => 'Maybe',
                            ])
                            ->default(Invitee::RSVP_PENDING)
                            ->required(),

                        Forms\Components\TextInput::make('confirmed_guests')
                            ->label('Confirmed Guests')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),

                        Forms\Components\Select::make('card_status')
                            ->label('Card Status')
                            ->options([
                                Invitee::CARD_STATUS_PENDING => 'Pending',
                                Invitee::CARD_STATUS_ACTIVE => 'Active',
                                Invitee::CARD_STATUS_CANCELLED => 'Cancelled',
                                Invitee::CARD_STATUS_BLOCKED => 'Blocked',
                                Invitee::CARD_STATUS_USED => 'Used',
                            ])
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
            ->emptyStateHeading('No invitees yet')
            ->emptyStateDescription('Add invitees manually or import them from Excel for this event.')
            ->emptyStateIcon('heroicon-o-users')
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with([
                'cardType',
                'latestGeneratedCard',
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Invitee')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record): ?string => $record->phone),

                Tables\Columns\TextColumn::make('cardType.name')
                    ->label('Card Type')
                    ->badge()
                    ->color('primary')
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('allowed_guests')
                    ->label('Allowed')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('confirmed_guests')
                    ->label('Confirmed')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining_guests')
                    ->label('Remaining')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('table_number')
                    ->label('Table')
                    ->placeholder('-')
                    ->alignCenter()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('serial_number')
                    ->label('Serial')
                    ->searchable()
                    ->copyable()
                    ->placeholder('-')
                    ->toggleable(),

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
                    ->color(fn ($state): string => (int) $state > 0 ? 'success' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sms_status')
                    ->label('SMS')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ucfirst(str_replace('_', ' ', $state ?: 'not_sent')))
                    ->color(fn (?string $state): string => match ($state) {
                        Invitee::SMS_STATUS_SENT,
                        Invitee::SMS_STATUS_DELIVERED => 'success',
                        Invitee::SMS_STATUS_FAILED => 'danger',
                        Invitee::SMS_STATUS_PENDING => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(),

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
                    ->options([
                        Invitee::RSVP_PENDING => 'Pending',
                        Invitee::RSVP_ATTENDING => 'Attending',
                        Invitee::RSVP_NOT_ATTENDING => 'Not Attending',
                        Invitee::RSVP_MAYBE => 'Maybe',
                    ]),

                Tables\Filters\SelectFilter::make('card_status')
                    ->label('Card Status')
                    ->options([
                        Invitee::CARD_STATUS_PENDING => 'Pending',
                        Invitee::CARD_STATUS_ACTIVE => 'Active',
                        Invitee::CARD_STATUS_CANCELLED => 'Cancelled',
                        Invitee::CARD_STATUS_BLOCKED => 'Blocked',
                        Invitee::CARD_STATUS_USED => 'Used',
                    ]),

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
            ->headerActions([
                Tables\Actions\Action::make('add_manual_invitee')
                    ->label('Add Invitee Manually')
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
                                    ->placeholder('Example: Guest Name'),

                                Forms\Components\TextInput::make('phone')
                                    ->label('Phone Number')
                                    ->tel()
                                    ->required()
                                    ->maxLength(30)
                                    ->placeholder('Example: 0711111111')
                                    ->helperText('Accepted formats: 0711111111, 711111111, +255711111111, or 255711111111.'),

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
                                    ->options([
                                        Invitee::RSVP_PENDING => 'Pending',
                                        Invitee::RSVP_ATTENDING => 'Attending',
                                        Invitee::RSVP_NOT_ATTENDING => 'Not Attending',
                                        Invitee::RSVP_MAYBE => 'Maybe',
                                    ])
                                    ->default(Invitee::RSVP_PENDING)
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

                        $this->ensureInviteeQrCode($invitee);

                        Notification::make()
                            ->title('Invitee added manually')
                            ->body('Serial number, short code, QR code, and RSVP token have been prepared automatically.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('refresh_status')
                    ->label('Refresh Status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->action(function (): void {
                        Notification::make()
                            ->title('Status refreshed')
                            ->body('The invitee and card generation statuses have been refreshed.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('download_sample_excel')
                    ->label('Download Sample Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn () => route('invitees.sample-excel'))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('import_excel')
                    ->label('Import Excel')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('gray')
                    ->modalHeading('Import Invitees from Excel')
                    ->modalDescription('Upload an Excel file with columns: name, phone, card_type, category, table_number. Guest limit will be taken from the selected card type.')
                    ->modalSubmitActionLabel('Import Invitees')
                    ->form([
                        Forms\Components\FileUpload::make('excel_file')
                            ->label('Excel File')
                            ->required()
                            ->disk('public')
                            ->directory('invitee-imports')
                            ->preserveFilenames()
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'text/csv',
                            ])
                            ->helperText('Required columns: name, phone, card_type. Optional: email, category, table_number. Same phone can be used by different invitees.'),
                    ])
                    ->action(function (array $data): void {
                        $this->importInviteesFromExcel($data['excel_file']);
                    }),

                Tables\Actions\Action::make('send_message')
                    ->label('Send Message')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->url(fn () => url('/admin/events/' . $this->getOwnerRecord()->id . '/send-message')),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make()
                        ->label('View')
                        ->icon('heroicon-o-eye')
                        ->modalHeading(fn ($record): string => 'View Invitee: ' . $record->name),

                    Tables\Actions\Action::make('edit_invitee')
                        ->label('Edit Invitee')
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
                                        ->maxLength(255),

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

                            $record->update($data);

                            Notification::make()
                                ->title('Invitee updated successfully')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('generate_card')
                        ->label(fn (Invitee $record): string => $record->latestGeneratedCard ? 'Regenerate Card' : 'Generate Card')
                        ->icon('heroicon-o-photo')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading(fn (Invitee $record): string => $record->latestGeneratedCard ? 'Regenerate invitation card' : 'Generate invitation card')
                        ->modalDescription('This will generate the personalized card in the background.')
                        ->action(function (Invitee $record): void {
                            if ($record->latestGeneratedCard?->status === GeneratedCard::STATUS_GENERATING) {
                                Notification::make()
                                    ->title('Card is already generating')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $this->prepareGeneratedCardForQueue($record);

                            GenerateInviteeCardJob::dispatch($record->id);

                            Notification::make()
                                ->title('Card generation started')
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
                        ->visible(fn (Invitee $record): bool => $record->latestGeneratedCard?->status === GeneratedCard::STATUS_FAILED)
                        ->action(function (Invitee $record): void {
                            $this->prepareGeneratedCardForQueue($record);

                            GenerateInviteeCardJob::dispatch($record->id);

                            Notification::make()
                                ->title('Card generation restarted')
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
                            Notification::make()
                                ->title('Private invitee link')
                                ->body(route('invitee.page', $record->short_code))
                                ->success()
                                ->persistent()
                                ->send();
                        })
                        ->visible(fn ($record): bool => filled($record->short_code)),

                    Tables\Actions\Action::make('send_card_link')
                        ->label('Send Card Link')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->modalHeading(fn (Invitee $record): string => 'Send invitation to ' . $record->name)
                        ->modalSubmitActionLabel('Send / Record')
                        ->form(fn (Invitee $record): array => [
                            Forms\Components\Select::make('channel')
                                ->label('Channel')
                                ->options([
                                    'sms' => 'SMS',
                                    'whatsapp' => 'WhatsApp',
                                ])
                                ->default('sms')
                                ->required(),

                            Forms\Components\Textarea::make('message')
                                ->label('Message')
                                ->rows(6)
                                ->required()
                                ->default(fn () => $this->buildInvitationMessage($record))
                                ->helperText('SMS will call SmsService. WhatsApp is still recorded only until WhatsApp API is connected.'),
                        ])
                        ->action(function (Invitee $record, array $data): void {
                            $result = $this->recordCardMessageAsSent($record, $data['channel'], $data['message']);

                            $notification = Notification::make()
                                ->title($result['title'])
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
                        ->visible(fn (Invitee $record): bool => filled($record->short_code)),

                    Tables\Actions\Action::make('mark_attending')
                        ->label('Mark Attending')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $record->update([
                                'rsvp_status' => Invitee::RSVP_ATTENDING,
                                'rsvp_confirmed_at' => now(),
                                'confirmed_guests' => max(1, (int) ($record->confirmed_guests ?: 1)),
                            ]);

                            Notification::make()
                                ->title('Invitee marked as attending')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('mark_not_attending')
                        ->label('Mark Not Attending')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $record->update([
                                'rsvp_status' => Invitee::RSVP_NOT_ATTENDING,
                                'rsvp_confirmed_at' => now(),
                                'confirmed_guests' => 0,
                            ]);

                            Notification::make()
                                ->title('Invitee marked as not attending')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('reset_rsvp')
                        ->label('Reset RSVP')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $record->update([
                                'rsvp_status' => Invitee::RSVP_PENDING,
                                'rsvp_confirmed_at' => null,
                                'confirmed_guests' => 0,
                            ]);

                            Notification::make()
                                ->title('RSVP reset to pending')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('cancel_invitee')
                        ->label('Cancel Invitee')
                        ->icon('heroicon-o-no-symbol')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Cancel Invitee')
                        ->modalDescription('This keeps the invitee record but blocks the card from being used.')
                        ->action(function (Invitee $record): void {
                            $record->update([
                                'card_status' => Invitee::CARD_STATUS_CANCELLED,
                            ]);

                            Notification::make()
                                ->title('Invitee cancelled successfully')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('delete_invitee')
                        ->label('Delete Invitee')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Delete Invitee')
                        ->modalDescription('Use this only for test invitees. For real invitees, use Cancel Invitee instead.')
                        ->action(function (Invitee $record): void {
                            try {
                                $record->delete();

                                Notification::make()
                                    ->title('Invitee deleted successfully')
                                    ->success()
                                    ->send();
                            } catch (Throwable $e) {
                                Notification::make()
                                    ->title('Invitee could not be deleted')
                                    ->body('This invitee may already have related records such as SMS logs, generated cards, RSVP, or check-in records. Use Cancel Invitee instead.')
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('generate_cards')
                        ->label('Generate Cards')
                        ->icon('heroicon-o-photo')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Generate selected invitation cards')
                        ->modalDescription('This will queue card generation jobs for all selected invitees. Keep the queue worker running.')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $queued = 0;
                            $skipped = 0;

                            foreach ($records as $record) {
                                if ($record->latestGeneratedCard?->status === GeneratedCard::STATUS_GENERATING) {
                                    $skipped++;
                                    continue;
                                }

                                $this->prepareGeneratedCardForQueue($record);

                                GenerateInviteeCardJob::dispatch($record->id);

                                $queued++;
                            }

                            Notification::make()
                                ->title('Card generation jobs started')
                                ->body($queued . ' card(s) queued. ' . $skipped . ' skipped because they are already generating.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('mark_generated_cards_as_sent')
                        ->label('Mark Generated Cards as Sent')
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

                            Notification::make()
                                ->title('Cards marked as sent')
                                ->body($marked . ' card(s) marked as sent. ' . $skipped . ' skipped because they have no generated card.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('mark_selected_attending')
                        ->label('Mark Selected Attending')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->update([
                                    'rsvp_status' => Invitee::RSVP_ATTENDING,
                                    'rsvp_confirmed_at' => now(),
                                    'confirmed_guests' => max(1, (int) ($record->confirmed_guests ?: 1)),
                                ]);
                            });

                            Notification::make()
                                ->title('Selected invitees marked as attending')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('reset_selected_rsvp')
                        ->label('Reset Selected RSVP')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->update([
                                    'rsvp_status' => Invitee::RSVP_PENDING,
                                    'rsvp_confirmed_at' => null,
                                    'confirmed_guests' => 0,
                                ]);
                            });

                            Notification::make()
                                ->title('Selected RSVP records reset')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('cancel_selected_invitees')
                        ->label('Cancel Selected Invitees')
                        ->icon('heroicon-o-no-symbol')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->update([
                                    'card_status' => Invitee::CARD_STATUS_CANCELLED,
                                ]);
                            });

                            Notification::make()
                                ->title('Selected invitees cancelled successfully')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('delete_selected_invitees')
                        ->label('Delete Selected Invitees')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $deleted = 0;
                            $failed = 0;

                            foreach ($records as $record) {
                                try {
                                    $record->delete();
                                    $deleted++;
                                } catch (Throwable $e) {
                                    $failed++;
                                }
                            }

                            Notification::make()
                                ->title('Bulk delete completed')
                                ->body("Deleted: {$deleted}. Failed: {$failed}. If some failed, use Cancel Selected Invitees.")
                                ->success()
                                ->persistent()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('send_selected_message')
                        ->label('Send Card Link to Selected')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Send card link to selected invitees')
                        ->modalDescription('SMS will call SmsService for each selected invitee. WhatsApp is still recorded only until WhatsApp API is connected.')
                        ->form([
                            Forms\Components\Select::make('channel')
                                ->label('Channel')
                                ->options([
                                    'sms' => 'SMS',
                                    'whatsapp' => 'WhatsApp',
                                ])
                                ->default('sms')
                                ->required(),

                            Forms\Components\Textarea::make('message_template')
                                ->label('Message Template')
                                ->rows(7)
                                ->required()
                                ->default("Habari #NAME#,\n\nKaribu kwenye #EVENT_NAME#. Fungua kadi yako ya mwaliko hapa: #INVITATION_LINK#\n\nSerial No: #SERIAL_NUMBER#")
                                ->helperText('Available placeholders: #NAME#, #EVENT_NAME#, #INVITATION_LINK#, #SERIAL_NUMBER#, #CARD_TYPE#, #GUEST_COUNT#, #TABLE_NUMBER#.'),
                        ])
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, array $data): void {
                            $sent = 0;
                            $skipped = 0;
                            $failed = 0;

                            foreach ($records as $record) {
                                $message = $this->replaceMessagePlaceholders($data['message_template'], $record);

                                $result = $this->recordCardMessageAsSent($record, $data['channel'], $message, false);

                                if ($result['status'] === 'sent') {
                                    $sent++;
                                } elseif ($result['status'] === 'skipped') {
                                    $skipped++;
                                } else {
                                    $failed++;
                                }
                            }

                            Notification::make()
                                ->title('Selected card links processed')
                                ->body("Sent/recorded: {$sent}. Skipped: {$skipped}. Failed: {$failed}.")
                                ->success()
                                ->persistent()
                                ->send();
                        }),
                ]),
            ]);
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
                '#INVITATION_LINK#',
                '#SERIAL_NUMBER#',
                '#CARD_TYPE#',
                '#GUEST_COUNT#',
                '#TABLE_NUMBER#',
            ],
            [
                $invitee->name,
                $event?->name ?? $event?->title ?? 'our event',
                $this->privateInvitationUrl($invitee),
                $invitee->serial_number ?? '-',
                $invitee->cardType?->name ?? '-',
                (string) ($invitee->allowed_guests ?? 1),
                $invitee->table_number ?? '-',
            ],
            $message
        );
    }

    protected function recordCardMessageAsSent(Invitee $invitee, string $channel, string $message, bool $notifySkipped = true): array
    {
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

        /*
         * WhatsApp MVP behavior:
         * WhatsApp is recorded only until WhatsApp Cloud API/provider is connected.
         */
        DB::transaction(function () use ($invitee, $channel, $message): void {
            $now = now();

            $this->safeUpdateInvitee($invitee, [
                'message_status' => 'sent',
                'sent_at' => $now,
                'last_message_channel' => $channel,
                'last_message_body' => $message,
            ]);

            $this->markLatestGeneratedCardAsSent($invitee, $now);

            $this->createMessageLog($invitee, $channel, $message, 'sent');
        });

        return [
            'status' => 'sent',
            'type' => 'success',
            'title' => 'WhatsApp invitation recorded',
            'body' => "WhatsApp message recorded. Connect WhatsApp API later for real sending. Link: {$this->privateInvitationUrl($invitee)}",
        ];
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

    protected function createMessageLog(Invitee $invitee, string $channel, string $message, string $status, ?string $errorMessage = null): void
    {
        if (! Schema::hasTable('message_logs')) {
            return;
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
            'type' => 'invitation_card',
            'recipient' => $invitee->phone,
            'phone' => $invitee->phone,
            'message' => $message,
            'body' => $message,
            'status' => $status,
            'provider_message_id' => null,
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
            $insertable['type'] = 'invitation_card';
        }

        if (Schema::hasColumn('message_logs', 'channel') && blank($insertable['channel'] ?? null)) {
            $insertable['channel'] = $channel;
        }

        if (Schema::hasColumn('message_logs', 'status') && blank($insertable['status'] ?? null)) {
            $insertable['status'] = $status;
        }

        DB::table('message_logs')->insert($insertable);
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

    protected function importInviteesFromExcel(string $filePath): void
    {
        if (! Storage::disk('public')->exists($filePath)) {
            Notification::make()
                ->title('Excel file not found')
                ->body('Please upload the Excel file again.')
                ->danger()
                ->send();

            return;
        }

        $fullPath = Storage::disk('public')->path($filePath);

        $spreadsheet = IOFactory::load($fullPath);
        $rows = collect($spreadsheet->getActiveSheet()->toArray(null, true, true, true));

        if ($rows->count() < 2) {
            Notification::make()
                ->title('Empty Excel file')
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
                Notification::make()
                    ->title('Missing required column')
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

            $alreadyExists = Invitee::where('event_id', $eventId)
                ->whereRaw('LOWER(TRIM(name)) = ?', [$normalizedName])
                ->exists();

            if ($alreadyExists) {
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

            $this->ensureInviteeQrCode($invitee);

            $created++;
        }

        $message = "Imported: {$created}. Skipped: {$skipped}.";

        if (! empty($errors)) {
            $message .= "\n\n" . collect($errors)->take(5)->implode("\n");

            if (count($errors) > 5) {
                $message .= "\n...and more.";
            }
        }

        Notification::make()
            ->title('Excel import completed')
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

        $data['serial_number'] = $data['serial_number'] ?? $this->generateUniqueSerialNumber();
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
        $name = preg_replace('/\s+/', ' ', $name);

        return strtolower($name);
    }

    protected function generateUniqueSerialNumber(): string
    {
        do {
            $serialNumber = 'ELV-' . now()->format('Y') . '-' . strtoupper(Str::random(6));
        } while (
            Invitee::where('event_id', $this->getOwnerRecord()->id)
                ->where('serial_number', $serialNumber)
                ->exists()
        );

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