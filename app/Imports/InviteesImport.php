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

            /*
             * Required fields:
             * name, phone, card_type
             */
            $name = trim((string) ($row['name'] ?? ''));
            $phone = trim((string) ($row['phone'] ?? ''));
            $cardTypeName = trim((string) ($row['card_type'] ?? ''));

            /*
             * Optional fields:
             * email, category, table_number, allowed_guests
             */
            $email = trim((string) ($row['email'] ?? ''));
            $category = trim((string) ($row['category'] ?? ''));
            $tableNumber = trim((string) ($row['table_number'] ?? ''));
            $allowedGuestsFromExcel = trim((string) ($row['allowed_guests'] ?? ''));

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

            $normalizedName = Str::lower($name);

            if (in_array($normalizedName, $this->namesInFile, true)) {
                $this->errors[] = "Row {$rowNumber}: Invitee name '{$name}' is duplicated in this Excel file.";
                continue;
            }

            $this->namesInFile[] = $normalizedName;

            $nameExists = Invitee::query()
                ->where('event_id', $this->eventId)
                ->whereRaw('LOWER(name) = ?', [$normalizedName])
                ->exists();

            if ($nameExists) {
                $this->errors[] = "Row {$rowNumber}: Invitee name '{$name}' already exists in this event.";
                continue;
            }

            /*
             * Card type lookup is case-insensitive.
             * Example: Single, SINGLE, single all match.
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
             * allowed_guests is optional.
             * If not provided in Excel, use the card type allowed_people.
             */
            $allowedGuests = is_numeric($allowedGuestsFromExcel)
                ? (int) $allowedGuestsFromExcel
                : (int) $cardType->allowed_people;

            Invitee::create([
                'event_id' => $this->eventId,
                'card_type_id' => $cardType->id,
                'name' => $name,
                'phone' => $phone,
                'email' => $email ?: null,
                'category' => $category ?: null,
                'table_number' => $tableNumber ?: null,
                'allowed_guests' => max(1, $allowedGuests),
                'card_status' => Invitee::CARD_STATUS_ACTIVE,
                'rsvp_status' => Invitee::RSVP_STATUS_PENDING,
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