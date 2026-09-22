<?php

namespace App\Filament\Resources\ContributionCampaignResource\RelationManagers;

use App\Jobs\GenerateContributionCardJob;
use App\Jobs\SendContributionCardJob;
use App\Exports\ContributionRecipientsSampleExport;
use App\Imports\ContributionRecipientsImport;
use App\Models\ContributionRecipient;
use App\Services\ContributionCardDownloadService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';
    protected static ?string $title = 'Committee Members';
    protected static ?string $modelLabel = 'Committee Member';
    protected static ?string $pluralModelLabel = 'Committee Members';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();
        return $user
            && ($user->canSendMessages() ?? false)
            && $ownerRecord->event?->canBeManagedBy($user);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Full Name')->required()->maxLength(255),
            Forms\Components\TextInput::make('phone')
                ->label('Phone Number')->tel()->required()->maxLength(32)
                ->helperText('Tanzania formats such as 0712345678 or 255712345678 are accepted.')
                ->rule(function (): \Closure {
                    return function (string $attribute, mixed $value, \Closure $fail): void {
                        if (! ContributionRecipientsImport::normalizePhone((string) $value)) {
                            $fail('Enter a valid Tanzania phone number.');
                        }
                    };
                })
                ->dehydrateStateUsing(function (?string $state): string {
                    return ContributionRecipientsImport::normalizePhone((string) $state)
                        ?? (string) $state;
                }),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No committee members yet')
            ->emptyStateDescription('Import an Excel file containing name and phone columns, or add a member manually.')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('phone')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('generation_status')
                    ->label('Card')->badge()
                    ->color(fn (string $state): string => $this->statusColor($state)),
                Tables\Columns\TextColumn::make('whatsapp_status')
                    ->label('Delivery')
                    ->formatStateUsing(fn (?string $state): string => ucfirst(str_replace('_', ' ', $state ?: 'not sent')))
                    ->badge()
                    ->color(fn (?string $state): string => $this->statusColor((string) $state)),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent At')->dateTime('d M Y, H:i')->placeholder('—'),
                Tables\Columns\TextColumn::make('delivered_at')
                    ->label('Delivered At')->dateTime('d M Y, H:i')->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('read_at')
                    ->label('Read At')->dateTime('d M Y, H:i')->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_reply_message')
                    ->label('Receiver Comment')
                    ->limit(55)
                    ->wrap()
                    ->placeholder('—')
                    ->tooltip(fn (ContributionRecipient $record): ?string => $record->last_reply_message),
                Tables\Columns\TextColumn::make('last_reply_at')
                    ->label('Reply At')->dateTime('d M Y, H:i')->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_error')
                    ->label('Last Error')->limit(45)
                    ->tooltip(fn (ContributionRecipient $record): ?string => $record->last_error)
                    ->toggleable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Member')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['event_id'] = $this->getOwnerRecord()->event_id;

                        return $data;
                    }),
                Tables\Actions\Action::make('download_sample')
                    ->label('Download Excel Sample')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => Excel::download(
                        new ContributionRecipientsSampleExport(),
                        'committee-contribution-members.xlsx'
                    )),
                Tables\Actions\Action::make('import_excel')
                    ->label('Import Excel')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->label('Excel or CSV File')->required()->storeFiles(false)
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'text/csv', 'application/csv', 'text/plain',
                            ])->maxSize(10240)
                            ->helperText('Required headings: name and phone. Other columns are ignored.'),
                    ])
                    ->action(function (array $data): void {
                        $file = $data['file'] ?? null;

                        if (! $file instanceof TemporaryUploadedFile) {
                            Notification::make()->title('Please upload the file again.')->danger()->send();
                            return;
                        }

                        $import = new ContributionRecipientsImport($this->getOwnerRecord()->id);

                        try {
                            Excel::import($import, $file->getRealPath());
                        } catch (ValidationException) {
                            $errorLines = array_slice($import->errors, 0, 10);
                            array_unshift($errorLines, "Imported: {$import->importedCount} committee member(s).");

                            if (count($import->errors) > 10) {
                                $errorLines[] = 'Additional errors were omitted from this notification.';
                            }

                            Notification::make()
                                ->title('Excel import completed with errors')
                                ->body(implode("\n", $errorLines))
                                ->warning()
                                ->persistent()
                                ->send();

                            return;
                        }

                        Notification::make()->title('Committee Excel import completed')
                            ->body("Imported: {$import->importedCount} committee member(s).")
                            ->success()->persistent()->send();
                    }),
                Tables\Actions\Action::make('generate_all')
                    ->label('Generate All Cards')->icon('heroicon-o-photo')->color('warning')
                    ->requiresConfirmation()
                    ->action(function (): void {
                        $count = 0;
                        $this->getOwnerRecord()->recipients()->eachById(function (ContributionRecipient $recipient) use (&$count): void {
                            GenerateContributionCardJob::dispatch($recipient->id);
                            $count++;
                        });
                        Notification::make()->title("{$count} contribution card(s) queued")->success()->send();
                    }),
                Tables\Actions\Action::make('download_all')
                    ->label('Download All Cards')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn (): bool => $this->getOwnerRecord()->recipients()
                        ->whereNotNull('generated_card_path')
                        ->exists())
                    ->action(function (): ?BinaryFileResponse {
                        return $this->downloadCardArchive(
                            $this->getOwnerRecord()->recipients()
                                ->whereNotNull('generated_card_path')
                                ->get(),
                        );
                    }),
                Tables\Actions\Action::make('send_all')
                    ->label('Send Generated Cards')->icon('heroicon-o-paper-airplane')->color('success')
                    ->requiresConfirmation()
                    ->action(function (): void {
                        $count = 0;
                        $this->getOwnerRecord()->recipients()
                            ->where('generation_status', ContributionRecipient::STATUS_GENERATED)
                            ->where('send_status', '!=', ContributionRecipient::STATUS_SENT)
                            ->eachById(function (ContributionRecipient $recipient) use (&$count): void {
                                SendContributionCardJob::dispatch($recipient->id);
                                $count++;
                            });
                        Notification::make()->title("{$count} WhatsApp contribution card(s) queued")->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('generate')
                    ->icon('heroicon-o-photo')->color('warning')
                    ->action(function (ContributionRecipient $record): void {
                        GenerateContributionCardJob::dispatch($record->id);
                        Notification::make()->title('Card generation queued')->success()->send();
                    }),
                Tables\Actions\Action::make('preview')
                    ->icon('heroicon-o-eye')->url(fn (ContributionRecipient $record): ?string => $record->generated_card_url)
                    ->openUrlInNewTab()->visible(fn (ContributionRecipient $record): bool => filled($record->generated_card_path)),
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn (ContributionRecipient $record): bool => filled($record->generated_card_path))
                    ->action(function (ContributionRecipient $record) {
                        if (! Storage::disk('public')->exists($record->generated_card_path)) {
                            Notification::make()
                                ->title('Generated card file not found')
                                ->body('Generate this card again, then retry the download.')
                                ->danger()
                                ->send();

                            return null;
                        }

                        return Storage::disk('public')->download(
                            $record->generated_card_path,
                            app(ContributionCardDownloadService::class)->cardFileName($record),
                        );
                    }),
                Tables\Actions\Action::make('send')
                    ->icon('heroicon-o-paper-airplane')->color('success')->requiresConfirmation()
                    ->visible(fn (ContributionRecipient $record): bool => $record->generation_status === ContributionRecipient::STATUS_GENERATED)
                    ->action(function (ContributionRecipient $record): void {
                        SendContributionCardJob::dispatch($record->id);
                        Notification::make()->title('WhatsApp delivery queued')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('generate_selected')
                        ->label('Generate Selected')->icon('heroicon-o-photo')
                        ->action(function (Collection $records): void {
                            $records->each(fn (ContributionRecipient $record) => GenerateContributionCardJob::dispatch($record->id));
                        })->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('send_selected')
                        ->label('Send Selected')->icon('heroicon-o-paper-airplane')->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->filter(fn (ContributionRecipient $record): bool => filled($record->generated_card_path))
                                ->each(fn (ContributionRecipient $record) => SendContributionCardJob::dispatch($record->id));
                        })->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('download_selected')
                        ->label('Download Selected Cards')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('info')
                        ->action(fn (Collection $records): ?BinaryFileResponse => $this->downloadCardArchive($records))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            ContributionRecipient::STATUS_GENERATED,
            ContributionRecipient::STATUS_SENT,
            'submitted',
            'delivered',
            'read',
            'replied' => 'success',
            ContributionRecipient::STATUS_PROCESSING => 'warning',
            ContributionRecipient::STATUS_FAILED => 'danger',
            default => 'gray',
        };
    }

    private function downloadCardArchive(Collection $recipients): ?BinaryFileResponse
    {
        try {
            $archive = app(ContributionCardDownloadService::class)->createArchive(
                $recipients,
                $this->getOwnerRecord()->name,
            );
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Cards could not be downloaded')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return null;
        }

        if ($archive['skipped'] > 0) {
            Notification::make()
                ->title("{$archive['added']} card(s) included")
                ->body("{$archive['skipped']} card file(s) were missing and skipped.")
                ->warning()
                ->send();
        }

        return response()
            ->download($archive['path'], $archive['name'], ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }
}
