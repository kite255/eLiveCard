<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Jobs\GenerateInviteeCardJob;
use App\Models\GeneratedCard;
use App\Services\AuditLogService;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class GeneratedCardsRelationManager extends RelationManager
{
    protected static string $relationship = 'generatedCards';

    protected static ?string $title = 'Generated Cards';

    protected static ?string $modelLabel = 'Generated Card';

    protected static ?string $pluralModelLabel = 'Generated Cards';

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

        return false;
    }

    protected function canViewGeneratedCards(): bool
    {
        return static::canAccessOwnerRecord($this->getOwnerRecord());
    }

    protected function canManageGeneratedCards(): bool
    {
        return $this->canViewGeneratedCards()
            && (auth()->user()?->canGenerateCards() ?? false);
    }

    protected function canDeleteGeneratedCards(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function isReadOnly(): bool
    {
        return ! $this->canManageGeneratedCards();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Generated Cards')
            ->description('View, download, regenerate, and manage personalized cards for this event.')
            ->modifyQueryUsing(fn ($query) => $query
                ->where('event_id', $this->getOwnerRecord()->getKey())
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
                        ->url(function (GeneratedCard $record): ?string {
                            AuditLogService::record(
                                action: 'generated_card.viewed',
                                subject: $record,
                                eventId: $record->event_id,
                                description: 'Generated invitation card was viewed.',
                                metadata: [
                                    'invitee_id' => $record->invitee_id,
                                    'card_template_id' => $record->card_template_id,
                                    'file_path' => $record->file_path,
                                ],
                            );

                            return $record->file_url;
                        })
                        ->openUrlInNewTab()
                        ->visible(fn (GeneratedCard $record): bool => filled($record->file_path) && $record->fileExists()),

                    Tables\Actions\Action::make('download_card')
                        ->label('Download')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->visible(fn (GeneratedCard $record): bool => filled($record->file_path) && $record->fileExists())
                        ->action(function (GeneratedCard $record) {
                            AuditLogService::record(
                                action: 'generated_card.downloaded',
                                subject: $record,
                                eventId: $record->event_id,
                                description: 'Generated invitation card was downloaded.',
                                metadata: [
                                    'invitee_id' => $record->invitee_id,
                                    'card_template_id' => $record->card_template_id,
                                    'file_path' => $record->file_path,
                                    'download_name' => $record->download_name,
                                ],
                            );

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
                        ->visible(fn (): bool => $this->canManageGeneratedCards())
                        ->action(function (GeneratedCard $record): void {
                            if (! $record->invitee_id) {
                                Notification::make()
                                    ->title('Invitee not found')
                                    ->body('This generated card is not linked to an invitee.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $oldValues = $record->only([
                                'status',
                                'generated_at',
                                'sent_at',
                            ]);

                            $record->markAsGenerating();
                            $record->refresh();

                            GenerateInviteeCardJob::dispatch($record->invitee_id);

                            AuditLogService::updated(
                                subject: $record,
                                eventId: $record->event_id,
                                description: 'Generated card was queued for regeneration.',
                                oldValues: $oldValues,
                                newValues: $record->only([
                                    'status',
                                    'generated_at',
                                    'sent_at',
                                ]),
                                metadata: [
                                    'invitee_id' => $record->invitee_id,
                                    'card_template_id' => $record->card_template_id,
                                ],
                            );

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
                        ->visible(fn (GeneratedCard $record): bool => $this->canManageGeneratedCards() && ! $record->isSent())
                        ->action(function (GeneratedCard $record): void {
                            $oldValues = $record->only([
                                'status',
                                'sent_at',
                            ]);

                            $record->markAsSent();
                            $record->refresh();

                            AuditLogService::updated(
                                subject: $record,
                                eventId: $record->event_id,
                                description: 'Generated card was marked as sent.',
                                oldValues: $oldValues,
                                newValues: $record->only([
                                    'status',
                                    'sent_at',
                                ]),
                                metadata: [
                                    'invitee_id' => $record->invitee_id,
                                    'card_template_id' => $record->card_template_id,
                                ],
                            );

                            Notification::make()
                                ->title('Card marked as sent')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteAction::make()
                        ->label('Delete')
                        ->visible(fn (): bool => $this->canDeleteGeneratedCards())
                        ->before(function (GeneratedCard $record): void {
                            AuditLogService::deleted(
                                subject: $record,
                                eventId: $record->event_id,
                                description: 'Generated invitation card record was deleted.',
                                metadata: [
                                    'invitee_id' => $record->invitee_id,
                                    'card_template_id' => $record->card_template_id,
                                    'status' => $record->status,
                                    'file_path' => $record->file_path,
                                    'generated_at' => $record->generated_at,
                                    'sent_at' => $record->sent_at,
                                ],
                            );
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
                    Tables\Actions\BulkAction::make('regenerate_selected')
                        ->label('Regenerate Selected')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (): bool => $this->canManageGeneratedCards())
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $count = 0;

                            foreach ($records as $record) {
                                if (! $record instanceof GeneratedCard || ! $record->invitee_id) {
                                    continue;
                                }

                                $oldValues = $record->only([
                                    'status',
                                    'generated_at',
                                    'sent_at',
                                ]);

                                $record->markAsGenerating();
                                $record->refresh();

                                GenerateInviteeCardJob::dispatch($record->invitee_id);

                                AuditLogService::updated(
                                    subject: $record,
                                    eventId: $record->event_id,
                                    description: 'Generated card was queued for regeneration in bulk.',
                                    oldValues: $oldValues,
                                    newValues: $record->only([
                                        'status',
                                        'generated_at',
                                        'sent_at',
                                    ]),
                                    metadata: [
                                        'invitee_id' => $record->invitee_id,
                                        'card_template_id' => $record->card_template_id,
                                        'bulk_action' => true,
                                    ],
                                );

                                $count++;
                            }

                            AuditLogService::system(
                                action: 'generated_cards_bulk_regeneration',
                                description: 'Selected generated cards were queued for regeneration.',
                                eventId: $this->getOwnerRecord()->getKey(),
                                metadata: [
                                    'queued_count' => $count,
                                ],
                            );

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
                        ->visible(fn (): bool => $this->canManageGeneratedCards())
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            $marked = 0;

                            foreach ($records as $record) {
                                if (! $record instanceof GeneratedCard) {
                                    continue;
                                }

                                $oldValues = $record->only([
                                    'status',
                                    'sent_at',
                                ]);

                                $record->markAsSent();
                                $record->refresh();

                                AuditLogService::updated(
                                    subject: $record,
                                    eventId: $record->event_id,
                                    description: 'Generated card was marked as sent in bulk.',
                                    oldValues: $oldValues,
                                    newValues: $record->only([
                                        'status',
                                        'sent_at',
                                    ]),
                                    metadata: [
                                        'invitee_id' => $record->invitee_id,
                                        'card_template_id' => $record->card_template_id,
                                        'bulk_action' => true,
                                    ],
                                );

                                $marked++;
                            }

                            AuditLogService::system(
                                action: 'generated_cards_bulk_marked_sent',
                                description: 'Selected generated cards were marked as sent.',
                                eventId: $this->getOwnerRecord()->getKey(),
                                metadata: [
                                    'marked_count' => $marked,
                                ],
                            );

                            Notification::make()
                                ->title('Selected cards marked as sent')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => $this->canDeleteGeneratedCards())
                        ->before(function (Collection $records): void {
                            foreach ($records as $record) {
                                if (! $record instanceof GeneratedCard) {
                                    continue;
                                }

                                AuditLogService::deleted(
                                    subject: $record,
                                    eventId: $record->event_id,
                                    description: 'Generated invitation card record was deleted in bulk.',
                                    metadata: [
                                        'invitee_id' => $record->invitee_id,
                                        'card_template_id' => $record->card_template_id,
                                        'status' => $record->status,
                                        'file_path' => $record->file_path,
                                        'bulk_action' => true,
                                    ],
                                );
                            }
                        }),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-identification')
            ->emptyStateHeading('No generated cards yet')
            ->emptyStateDescription('Generate cards from the Invitees tab or from an active card template first.');
    }
}
