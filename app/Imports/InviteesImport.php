<?php

namespace App\Imports;

use App\Models\CardType;
use App\Models\Invitee;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class InviteesImport implements ToCollection, WithHeadingRow
{
    protected int $eventId;

    public array $errors = [];

    public int $importedCount = 0;

    protected array $namesInFile = [];

    public function __construct(int $eventId)
    {
        $this->eventId = $eventId;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // Row 1 is heading row.

            $name = trim((string) ($row['name'] ?? ''));
            $phone = trim((string) ($row['phone'] ?? ''));
            $cardTypeName = trim((string) ($row['card_type'] ?? ''));

            if ($name === '') {
                $this->errors[] = "Row {$rowNumber}: Name is required.";
                continue;
            }

            if ($phone === '') {
                $this->errors[] = "Row {$rowNumber}: Phone number is required.";
                continue;
            }

            if ($cardTypeName === '') {
                $this->errors[] = "Row {$rowNumber}: Card type is required.";
                continue;
            }

            /*
             * Normalize name for duplicate checking.
             * This makes "Vee", "vee", and " Vee " treated as the same name.
             */
            $normalizedName = Str::lower($name);

            /*
             * Rule 1: Name must not repeat inside the uploaded Excel file.
             */
            if (in_array($normalizedName, $this->namesInFile, true)) {
                $this->errors[] = "Row {$rowNumber}: Invitee name '{$name}' is duplicated in this Excel file.";
                continue;
            }

            $this->namesInFile[] = $normalizedName;

            /*
             * Rule 2: Name must not already exist in the selected event.
             */
            $nameExists = Invitee::query()
                ->where('event_id', $this->eventId)
                ->whereRaw('LOWER(name) = ?', [$normalizedName])
                ->exists();

            if ($nameExists) {
                $this->errors[] = "Row {$rowNumber}: Invitee name '{$name}' already exists in this event.";
                continue;
            }

            /*
             * Rule 3: Card type must exist in the selected event.
             */
            $cardType = CardType::query()
                ->where('event_id', $this->eventId)
                ->whereRaw('LOWER(name) = ?', [Str::lower($cardTypeName)])
                ->first();

            if (! $cardType) {
                $this->errors[] = "Row {$rowNumber}: Card type '{$cardTypeName}' does not exist for this event.";
                continue;
            }

            /*
             * Create invitee.
             * Phone number can repeat.
             * Serial number, QR token, QR hash, and QR code are generated automatically by Invitee model.
             */
            Invitee::create([
                'event_id' => $this->eventId,
                'card_type_id' => $cardType->id,
                'name' => $name,
                'phone' => $phone,
            ]);

            $this->importedCount++;
        }

        if (! empty($this->errors)) {
            throw ValidationException::withMessages([
                'import_file' => $this->errors,
            ]);
        }
    }
}