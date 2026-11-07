<?php

namespace App\Filament\Resources\InviteeResource\Pages;

use App\Filament\Resources\InviteeResource;
use App\Imports\InviteesImport;
use App\Models\Event;
use Filament\Actions;
use Filament\Forms;
use App\Support\ActionToast;
use Illuminate\Support\Facades\Log;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
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
                        ->helperText('Required headings: name, phone, card_type. Optional: allowed_guests, email, category, table_number.'),
                ])
                ->modalHeading('Import Invitees from Excel')
                ->modalDescription('Valid rows are imported even if other rows contain errors.')
                ->modalSubmitActionLabel('Import Invitees')
                ->action(function (array $data) {
                    $filePath = Storage::disk('local')->path($data['import_file']);

                    if (! file_exists($filePath)) {
                        ActionToast::failure('Import failed', 'Uploaded file was not found. Please upload the Excel file again.');

                        return;
                    }

                    $import = new InviteesImport((int) $data['event_id']);

                    try {
                        Excel::import($import, $filePath);

                        if ($import->errors !== []) {
                            $errors = collect($import->errors)
                                ->take(15)
                                ->implode("\n");

                            ActionToast::warning(
                                'Import completed with errors',
                                $import->importedCount . " invitee(s) imported.\n" . $errors
                            );

                            return;
                        }

                        $import->importedCount > 0
                            ? ActionToast::success('Invitees imported successfully', $import->importedCount . ' invitee(s) imported.')
                            : ActionToast::warning('No invitees imported', 'The file did not contain any valid invitees.');
                    } catch (\Throwable $exception) {
                        Log::error('Invitee import failed', ['exception' => $exception]);
                        ActionToast::failure('Import failed', 'The import could not be completed. Please check the file and contact support if the issue persists.');
                    }
                }),
        ];
    }
}
