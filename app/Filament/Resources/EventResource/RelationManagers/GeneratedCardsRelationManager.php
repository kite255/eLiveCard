<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Jobs\GenerateInviteeCardJob;
use App\Models\GeneratedCard;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class GeneratedCardsRelationManager extends RelationManager
{
    protected static string $relationship = 'generatedCards';

    protected static ?string $title = 'Generated Cards';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Generated Cards')
            ->description('View, download, regenerate, and manage personalized cards for this event.')
            ->modifyQueryUsing(fn ($query) => $query
                ->with([
                    'invitee',
                    'cardTemplate',
                ])
                ->latest()
            )
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

                Tables\Columns\TextColumn::make('cardTemplate.name')
                    ->label('Template')
                    ->limit(30)
                    ->toggleable(),

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
                        ->visible(fn (GeneratedCard $record): bool => filled($record->file_path) && $record->fileExists()),

                    Tables\Actions\Action::make('download_card')
                        ->label('Download')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->visible(fn (GeneratedCard $record): bool => filled($record->file_path) && $record->fileExists())
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
                        ->action(function (GeneratedCard $record): void {
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
                        ->visible(fn (GeneratedCard $record): bool => ! $record->isSent())
                        ->action(function (GeneratedCard $record): void {
                            $record->markAsSent();

                            Notification::make()
                                ->title('Card marked as sent')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteAction::make()
                        ->label('Delete'),
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
                        ->action(function (Collection $records): void {
                            $count = 0;

                            foreach ($records as $record) {
                                if (! $record->invitee_id) {
                                    continue;
                                }

                                $record->markAsGenerating();

                                GenerateInviteeCardJob::dispatch($record->invitee_id);

                                $count++;
                            }

                            Notification::make()
                                ->title('Card regeneration jobs started')
                                ->body($count . ' card(s) queued for regeneration.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('mark_selected_as_sent')
                        ->label('Mark Selected as Sent')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                $record->markAsSent();
                            }

                            Notification::make()
                                ->title('Selected cards marked as sent')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-identification')
            ->emptyStateHeading('No generated cards yet')
            ->emptyStateDescription('Generate cards from the Invitees tab or from an active card template first.');
    }
}