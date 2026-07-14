<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Exports\EventCheckInsExport;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Facades\Excel;

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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
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
                    ->action(function () {
                        $event = $this->getOwnerRecord();
                        $eventName = str($event->title ?? $event->name ?? 'event')
                            ->slug('-')
                            ->toString();

                        return Excel::download(
                            new EventCheckInsExport((int) $event->id),
                            $eventName . '-check-ins-report.xlsx'
                        );
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('invitee.name')
                    ->label('Invitee')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->placeholder('Unknown invitee'),

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
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining_guests')
                    ->label('Remaining')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('checkin_method')
                    ->label('Method')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => str($state ?: 'qr')->replace('_', ' ')->title()->toString())
                    ->color('gray')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => str($state ?: 'checked_in')->replace('_', ' ')->title()->toString())
                    ->color(fn (?string $state): string => match ($state) {
                        'success', 'checked_in', 'valid' => 'success',
                        'failed', 'invalid', 'rejected' => 'danger',
                        'warning', 'partial' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('checkedInBy.name')
                    ->label('Gate User')
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('checked_in_at')
                    ->label('Checked In At')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('remarks')
                    ->label('Remarks')
                    ->limit(40)
                    ->tooltip(fn ($record): ?string => $record->remarks)
                    ->placeholder('-')
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
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('View')
                    ->icon('heroicon-o-eye'),
            ])
            ->bulkActions([]);
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