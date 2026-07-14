<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GeneratedCardResource\Pages;
use App\Jobs\GenerateInviteeCardJob;
use App\Models\GeneratedCard;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class GeneratedCardResource extends Resource
{
    protected static ?string $model = GeneratedCard::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Card Management';

    protected static ?string $navigationLabel = 'Generated Cards';

    protected static ?string $modelLabel = 'Generated Card';

    protected static ?string $pluralModelLabel = 'Generated Cards';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'event',
                'invitee',
                'cardTemplate',
            ])
            ->latest();

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

    public static function canViewAny(): bool
    {
        return auth()->user()?->canViewReports() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->canGenerateCards() ?? false;
    }

    public static function canView($record): bool
    {
        return static::canAccessRecord($record);
    }

    public static function canEdit($record): bool
    {
        return static::canManageGeneratedCard($record);
    }

    public static function canDelete($record): bool
    {
        return static::canDeleteGeneratedCard($record);
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected static function canAccessRecord(?GeneratedCard $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
            return false;
        }

        if (! ($user->canViewReports() ?? false)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isEventAdmin()) {
            $record->loadMissing('event');

            return (int) ($record->event?->user_id ?? 0) === (int) $user->id;
        }

        return false;
    }

    protected static function canManageGeneratedCard(?GeneratedCard $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $record) {
            return false;
        }

        if (! ($user->canGenerateCards() ?? false)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isEventAdmin()) {
            $record->loadMissing('event');

            return (int) ($record->event?->user_id ?? 0) === (int) $user->id;
        }

        return false;
    }

    protected static function canDeleteGeneratedCard(?GeneratedCard $record): bool
    {
        return (auth()->user()?->isSuperAdmin() ?? false)
            && static::canAccessRecord($record);
    }

    protected static function visibleEventOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        return \App\Models\Event::query()
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

    public static function table(Table $table): Table
    {
        return $table
            ->heading('Generated Cards')
            ->description('View, download, regenerate, and manage personalized cards generated for invitees.')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->columns([
                Tables\Columns\TextColumn::make('invitee.name')
                    ->label('Invitee')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (GeneratedCard $record): string => $record->invitee?->phone ?? 'No phone'),

                Tables\Columns\TextColumn::make('invitee.serial_number')
                    ->label('Serial')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('cardTemplate.name')
                    ->label('Template')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => GeneratedCard::statuses()[$state] ?? ucfirst($state ?? 'Pending'))
                    ->color(fn (?string $state): string => match ($state) {
                        GeneratedCard::STATUS_PENDING => 'gray',
                        GeneratedCard::STATUS_GENERATING => 'warning',
                        GeneratedCard::STATUS_GENERATED => 'success',
                        GeneratedCard::STATUS_SENT => 'info',
                        GeneratedCard::STATUS_FAILED => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('file_path')
                    ->label('Card File')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? 'Available' : 'Missing')
                    ->badge()
                    ->color(fn (?string $state): string => filled($state) ? 'success' : 'danger')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('file_exists')
                    ->label('Exists')
                    ->boolean()
                    ->getStateUsing(fn (GeneratedCard $record): bool => $record->fileExists())
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('generated_at')
                    ->label('Generated At')
                    ->dateTime('M d, Y h:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent At')
                    ->dateTime('M d, Y h:i A')
                    ->placeholder('Not sent')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('M d, Y h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Event')
                    ->options(fn (): array => static::visibleEventOptions())
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(GeneratedCard::statuses()),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('view_card')
                        ->label('View Card')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->url(fn (GeneratedCard $record): ?string => $record->file_url)
                        ->openUrlInNewTab()
                        ->visible(fn (GeneratedCard $record): bool => static::canAccessRecord($record) && filled($record->file_path) && $record->fileExists()),

                    Tables\Actions\Action::make('download_card')
                        ->label('Download')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->visible(fn (GeneratedCard $record): bool => static::canAccessRecord($record) && filled($record->file_path) && $record->fileExists())
                        ->action(function (GeneratedCard $record) {
                            return response()->download(
                                Storage::disk('public')->path($record->file_path),
                                $record->download_name
                            );
                        }),

                    Tables\Actions\Action::make('regenerate_card')
                        ->label('Regenerate')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Regenerate invitation card')
                        ->modalDescription('This will regenerate the card using the current template and invitee details.')
                        ->visible(fn (GeneratedCard $record): bool => static::canManageGeneratedCard($record))
                        ->action(function (GeneratedCard $record): void {
                            if (! static::canManageGeneratedCard($record)) {
                                Notification::make()
                                    ->title('Access denied')
                                    ->body('You are not allowed to regenerate this card.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            if (! $record->invitee_id) {
                                Notification::make()
                                    ->title('Invitee not found')
                                    ->body('This generated card is not linked to an invitee.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $record->markAsGenerating();

                            GenerateInviteeCardJob::dispatch($record->invitee_id);

                            Notification::make()
                                ->title('Card regeneration started')
                                ->body('The card will be regenerated in the background.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('mark_as_sent')
                        ->label('Mark as Sent')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (GeneratedCard $record): bool => static::canManageGeneratedCard($record) && ! $record->isSent())
                        ->action(function (GeneratedCard $record): void {
                            if (! static::canManageGeneratedCard($record)) {
                                Notification::make()
                                    ->title('Access denied')
                                    ->body('You are not allowed to update this generated card.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $record->markAsSent();

                            Notification::make()
                                ->title('Card marked as sent')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteAction::make()
                        ->label('Delete')
                        ->visible(fn (GeneratedCard $record): bool => static::canDeleteGeneratedCard($record)),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('regenerate_selected')
                        ->label('Regenerate Selected')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn (): bool => auth()->user()?->canGenerateCards() ?? false)
                        ->action(function (Collection $records): void {
                            $count = 0;
                            $skipped = 0;

                            foreach ($records as $record) {
                                if (! $record instanceof GeneratedCard || ! static::canManageGeneratedCard($record)) {
                                    $skipped++;
                                    continue;
                                }

                                if (! $record->invitee_id) {
                                    $skipped++;
                                    continue;
                                }

                                $record->markAsGenerating();

                                GenerateInviteeCardJob::dispatch($record->invitee_id);

                                $count++;
                            }

                            Notification::make()
                                ->title('Card regeneration jobs processed')
                                ->body("Queued: {$count}. Skipped: {$skipped}.")
                                ->color($count > 0 ? 'success' : 'warning')
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('mark_selected_as_sent')
                        ->label('Mark Selected as Sent')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn (): bool => auth()->user()?->canGenerateCards() ?? false)
                        ->action(function (Collection $records): void {
                            $marked = 0;
                            $skipped = 0;

                            foreach ($records as $record) {
                                if (! $record instanceof GeneratedCard || ! static::canManageGeneratedCard($record)) {
                                    $skipped++;
                                    continue;
                                }

                                $record->markAsSent();
                                $marked++;
                            }

                            Notification::make()
                                ->title('Selected cards processed')
                                ->body("Marked as sent: {$marked}. Skipped: {$skipped}.")
                                ->color($marked > 0 ? 'success' : 'warning')
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-identification')
            ->emptyStateHeading('No generated cards yet')
            ->emptyStateDescription('Generate cards from the Invitees tab or from an active card template first.');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGeneratedCards::route('/'),
        ];
    }
}
