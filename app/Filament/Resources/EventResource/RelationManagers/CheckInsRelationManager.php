<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Exports\EventCheckInsExport;
use App\Models\CheckIn;
use App\Services\AuditLogService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CheckInsRelationManager extends RelationManager
{
    protected static string $relationship = 'checkIns';

    protected static ?string $title = 'Check-ins';

    protected static ?string $modelLabel = 'Check-in';

    protected static ?string $pluralModelLabel = 'Check-ins';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return static::canAccessOwnerRecord($ownerRecord);
    }

    protected static function canAccessOwnerRecord(Model $ownerRecord): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isEventAdmin()) {
            return (int) ($ownerRecord->user_id ?? 0) === (int) $user->id;
        }

        if ($user->isCheckInOfficer()) {
            return $user->canScanGuests();
        }

        return false;
    }

    protected function canExportCheckIns(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isEventAdmin()) {
            return (int) ($this->getOwnerRecord()->user_id ?? 0) === (int) $user->id
                && ($user->canViewReports() ?? false);
        }

        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Invitee Details')
                    ->schema([
                        Forms\Components\TextInput::make('invitee.name')
                            ->label('Invitee')
                            ->disabled(),

                        Forms\Components\TextInput::make('invitee.phone')
                            ->label('Phone')
                            ->disabled(),

                        Forms\Components\TextInput::make('invitee.serial_number')
                            ->label('Serial Number')
                            ->disabled(),

                        Forms\Components\TextInput::make('checkedInBy.name')
                            ->label('Gate User')
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Check-in Details')
                    ->schema([
                        Forms\Components\TextInput::make('guests_checked_in')
                            ->label('Guests Checked In')
                            ->disabled(),

                        Forms\Components\TextInput::make('remaining_guests')
                            ->label('Remaining Guests')
                            ->disabled(),

                        Forms\Components\TextInput::make('checkin_method')
                            ->label('Check-in Method')
                            ->disabled(),

                        Forms\Components\TextInput::make('status')
                            ->label('Status')
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('checked_in_at')
                            ->label('Checked In At')
                            ->disabled(),

                        Forms\Components\Textarea::make('remarks')
                            ->label('Remarks')
                            ->rows(4)
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Guest Check-in Records')
            ->description('Review QR, serial, phone, name-search, and manual gate check-ins for this event.')
            ->recordTitleAttribute('id')
            ->searchPlaceholder('Search invitee, phone, serial number, gate user, method, or remarks')
            ->searchDebounce('500ms')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query
                    ->where('event_id', $this->getOwnerRecord()->getKey())
                    ->with([
                        'invitee',
                        'checkedInBy',
                    ])
            )
            ->defaultSort('checked_in_at', 'desc')
            ->emptyStateIcon('heroicon-o-qr-code')
            ->emptyStateHeading('No check-ins yet')
            ->emptyStateDescription('Guest check-ins for this event will appear here after QR scanning or manual check-in.')
            ->headerActions([
                Tables\Actions\Action::make('export_check_ins')
                    ->label('Export Check-ins')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (): bool => $this->canExportCheckIns())
                    ->action(fn (): BinaryFileResponse => $this->downloadCheckIns()),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('invitee.name')
                    ->label('Invitee')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->placeholder('Unknown invitee')
                    ->description(
                        fn (CheckIn $record): string =>
                            $record->invitee?->phone ?: 'No phone'
                    ),

                Tables\Columns\TextColumn::make('invitee.phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Phone copied')
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('invitee.serial_number')
                    ->label('Serial')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Serial number copied')
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('guests_checked_in')
                    ->label('Guests')
                    ->alignCenter()
                    ->badge()
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining_guests')
                    ->label('Remaining')
                    ->alignCenter()
                    ->badge()
                    ->color(
                        fn ($state): string =>
                            (int) $state > 0 ? 'warning' : 'gray'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('checkin_method')
                    ->label('Method')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            str($state ?: 'qr')
                                ->replace('_', ' ')
                                ->title()
                                ->toString()
                    )
                    ->color(
                        fn (?string $state): string => match ($state) {
                            'qr', 'gate_scanner' => 'primary',
                            'serial' => 'info',
                            'phone', 'name' => 'warning',
                            'manual' => 'gray',
                            default => 'gray',
                        }
                    )
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            str($state ?: 'checked_in')
                                ->replace('_', ' ')
                                ->title()
                                ->toString()
                    )
                    ->color(fn (?string $state): string => match ($state) {
                        'success', 'checked_in', 'valid' => 'success',
                        'failed', 'invalid', 'rejected' => 'danger',
                        'warning', 'partial' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('checkedInBy.name')
                    ->label('Gate User')
                    ->searchable()
                    ->sortable()
                    ->placeholder('System')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('checked_in_at')
                    ->label('Checked In At')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('remarks')
                    ->label('Remarks')
                    ->limit(40)
                    ->tooltip(
                        fn (CheckIn $record): ?string =>
                            $record->remarks
                    )
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Recorded At')
                    ->dateTime('d M Y H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'checked_in' => 'Checked In',
                        'success' => 'Success',
                        'valid' => 'Valid',
                        'partial' => 'Partial',
                        'failed' => 'Failed',
                        'invalid' => 'Invalid',
                        'rejected' => 'Rejected',
                    ]),

                Tables\Filters\SelectFilter::make('checkin_method')
                    ->label('Method')
                    ->options([
                        'qr' => 'QR',
                        'manual' => 'Manual',
                        'serial' => 'Serial Number',
                        'phone' => 'Phone',
                        'name' => 'Name Search',
                        'gate_scanner' => 'Gate Scanner',
                    ]),

                Tables\Filters\SelectFilter::make('checked_in_by')
                    ->label('Gate User')
                    ->relationship(
                        name: 'checkedInBy',
                        titleAttribute: 'name'
                    )
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('successful')
                    ->label('Successful')
                    ->query(
                        fn (Builder $query): Builder =>
                            $query->whereIn('status', [
                                'success',
                                'checked_in',
                                'valid',
                                'partial',
                            ])
                    ),

                Tables\Filters\Filter::make('failed')
                    ->label('Failed')
                    ->query(
                        fn (Builder $query): Builder =>
                            $query->whereIn('status', [
                                'failed',
                                'invalid',
                                'rejected',
                            ])
                    ),

                Tables\Filters\Filter::make('today')
                    ->label('Today')
                    ->query(
                        fn (Builder $query): Builder =>
                            $query->whereDate('checked_in_at', today())
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
                                    $query->whereDate('checked_in_at', '>=', $date)
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, $date): Builder =>
                                    $query->whereDate('checked_in_at', '<=', $date)
                            );
                    }),
            ])
            ->filtersFormColumns(3)
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->after(function (CheckIn $record): void {
                        AuditLogService::record(
                            action: 'check_in.viewed',
                            subject: $record,
                            eventId: $record->event_id,
                            description: 'Check-in record was viewed.',
                            metadata: [
                                'invitee_id' => $record->invitee_id,
                                'checked_in_by' => $record->checked_in_by,
                                'checkin_method' => $record->checkin_method,
                                'status' => $record->status,
                                'guests_checked_in' => $record->guests_checked_in,
                                'remaining_guests' => $record->remaining_guests,
                                'checked_in_at' => $record->checked_in_at,
                            ],
                        );
                    }),
            ])
            ->bulkActions([])
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

    private function downloadCheckIns(): BinaryFileResponse
    {
        if (! $this->canExportCheckIns()) {
            abort(403);
        }

        $event = $this->getOwnerRecord();

        $eventName = str(
            $event->title
            ?? $event->name
            ?? 'event'
        )
            ->slug('-')
            ->toString();

        $filename = $eventName
            .'-check-ins-report-'
            .now()->format('Ymd-His')
            .'.xlsx';

        $totalRecords = CheckIn::query()
            ->where('event_id', $event->getKey())
            ->count();

        $successfulRecords = CheckIn::query()
            ->where('event_id', $event->getKey())
            ->whereIn('status', [
                'success',
                'checked_in',
                'valid',
                'partial',
            ])
            ->count();

        $failedRecords = CheckIn::query()
            ->where('event_id', $event->getKey())
            ->whereIn('status', [
                'failed',
                'invalid',
                'rejected',
            ])
            ->count();

        $totalGuests = CheckIn::query()
            ->where('event_id', $event->getKey())
            ->sum('guests_checked_in');

        AuditLogService::exported(
            subject: $event,
            eventId: $event->getKey(),
            description: 'Event check-in report was exported.',
            metadata: [
                'export_type' => 'event_check_ins',
                'filename' => $filename,
                'row_count' => $totalRecords,
                'successful_records' => $successfulRecords,
                'failed_records' => $failedRecords,
                'total_guests_checked_in' => $totalGuests,
            ],
        );

        return Excel::download(
            new EventCheckInsExport(
                (int) $event->getKey()
            ),
            $filename
        );
    }
}
