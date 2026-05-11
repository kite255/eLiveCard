<?php

namespace App\Filament\Resources\InviteeResource\Pages;

use App\Filament\Resources\InviteeResource;
use App\Imports\InviteesImport;
use App\Models\Event;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class ListInvitees extends ListRecords
{
    protected static string $resource = InviteeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Invitee'),

            Actions\Action::make('import_invitees')
                ->label('Import Invitees')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->form([
                    Forms\Components\Select::make('event_id')
                        ->label('Event')
                        ->options(Event::query()->pluck('title', 'id'))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('Select the event where these invitees will be imported.'),

                    Forms\Components\FileUpload::make('import_file')
                        ->label('Excel File')
                        ->disk('local')
                        ->directory('imports/invitees')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                        ])
                        ->required()
                        ->helperText('Excel headings must be exactly: name, phone, card_type'),
                ])
                ->modalHeading('Import Invitees from Excel')
                ->modalDescription('Upload an Excel file with only these columns: name, phone, card_type.')
                ->modalSubmitActionLabel('Import Invitees')
                ->action(function (array $data) {
                    $filePath = Storage::disk('local')->path($data['import_file']);

                    if (! file_exists($filePath)) {
                        Notification::make()
                            ->title('Import failed')
                            ->body('Uploaded file was not found. Please upload the Excel file again.')
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    $import = new InviteesImport((int) $data['event_id']);

                    try {
                        Excel::import($import, $filePath);

                        Notification::make()
                            ->title('Invitees imported successfully')
                            ->body($import->importedCount . ' invitee(s) imported successfully.')
                            ->success()
                            ->send();
                    } catch (ValidationException $exception) {
                        $errors = collect($exception->errors())
                            ->flatten()
                            ->take(15)
                            ->implode("\n");

                        Notification::make()
                            ->title('Import failed')
                            ->body($errors ?: 'Please check your Excel file and try again.')
                            ->danger()
                            ->persistent()
                            ->send();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Import failed')
                            ->body($exception->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}