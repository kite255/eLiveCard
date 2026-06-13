<?php

namespace App\Imports;

use App\Models\CardType;
use App\Models\Invitee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
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
            |--------------------------------------------------------------------------
            | Excel Columns
            |--------------------------------------------------------------------------
            | Required: name, phone, card_type
            | Optional: email, category, table_number, allowed_guests
            */

            $name = $this->cleanText($row['name'] ?? null);
            $phone = $this->cleanText($row['phone'] ?? null);
            $cardTypeName = $this->cleanText($row['card_type'] ?? null);

            $email = $this->cleanText($row['email'] ?? null);
            $category = $this->cleanText($row['category'] ?? null);
            $tableNumber = $this->cleanText($row['table_number'] ?? null);

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
            |--------------------------------------------------------------------------
            | Normalize Phone Number
            |--------------------------------------------------------------------------
            | Accepts:
            | 0711111111
            | 711111111
            | +255711111111
            | 255711111111
            | 0711 111 111
            | 0711-111-111
            |
            | Saves as:
            | 255711111111
            */

            $normalizedPhone = $this->normalizeTanzaniaPhone($phone);

            if (! $normalizedPhone) {
                $this->errors[] = "Row {$rowNumber}: Phone number '{$phone}' is invalid. Use a valid Tanzania phone number.";
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Duplicate Rule
            |--------------------------------------------------------------------------
            | Same event + same invitee name = duplicate.
            | Same phone number is allowed for different invitees.
            */

            $normalizedName = $this->normalizeName($name);

            if (in_array($normalizedName, $this->namesInFile, true)) {
                $this->errors[] = "Row {$rowNumber}: Invitee name '{$name}' is duplicated in this Excel file.";
                continue;
            }

            $this->namesInFile[] = $normalizedName;

            $nameExists = Invitee::query()
                ->where('event_id', $this->eventId)
                ->whereRaw("LOWER(TRIM(name)) = ?", [$normalizedName])
                ->exists();

            if ($nameExists) {
                $this->errors[] = "Row {$rowNumber}: Invitee name '{$name}' already exists in this event.";
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Card Type Validation
            |--------------------------------------------------------------------------
            | Only card types created for this specific event are accepted.
            */

            $cardType = CardType::query()
                ->where('event_id', $this->eventId)
                ->where('is_active', true)
                ->whereRaw("LOWER(TRIM(name)) = ?", [$this->normalizeName($cardTypeName)])
                ->first();

            if (! $cardType) {
                $this->errors[] = "Row {$rowNumber}: Card type '{$cardTypeName}' does not exist or is inactive for this event.";
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Allowed Guests
            |--------------------------------------------------------------------------
            | The card type controls guest limit.
            | This prevents Excel mistakes from changing the allowed guests.
            */

            $allowedGuests = max(1, (int) ($cardType->allowed_people ?? 1));

            /*
            |--------------------------------------------------------------------------
            | Create Invitee
            |--------------------------------------------------------------------------
            */

            $invitee = new Invitee();

            $invitee->event_id = $this->eventId;
            $invitee->card_type_id = $cardType->id;
            $invitee->name = $name;
            $invitee->phone = $normalizedPhone;

            if (Schema::hasColumn('invitees', 'email')) {
                $invitee->email = $email ?: null;
            }

            if (Schema::hasColumn('invitees', 'category')) {
                $invitee->category = $category ?: null;
            }

            if (Schema::hasColumn('invitees', 'table_number')) {
                $invitee->table_number = $tableNumber ?: null;
            }

            if (Schema::hasColumn('invitees', 'allowed_guests')) {
                $invitee->allowed_guests = $allowedGuests;
            }

            if (Schema::hasColumn('invitees', 'card_status')) {
                $invitee->card_status = defined(Invitee::class . '::CARD_STATUS_ACTIVE')
                    ? Invitee::CARD_STATUS_ACTIVE
                    : 'active';
            }

            if (Schema::hasColumn('invitees', 'rsvp_status')) {
                $invitee->rsvp_status = defined(Invitee::class . '::RSVP_STATUS_PENDING')
                    ? Invitee::RSVP_STATUS_PENDING
                    : 'pending';
            }

            if (Schema::hasColumn('invitees', 'check_in_status')) {
                $invitee->check_in_status = 'not_checked_in';
            }

            if (Schema::hasColumn('invitees', 'sms_status')) {
                $invitee->sms_status = 'not_sent';
            }

            if (Schema::hasColumn('invitees', 'whatsapp_status')) {
                $invitee->whatsapp_status = 'not_sent';
            }

            if (Schema::hasColumn('invitees', 'serial_number')) {
                $invitee->serial_number = $this->generateUniqueSerialNumber();
            }

            if (Schema::hasColumn('invitees', 'qr_token')) {
                $invitee->qr_token = Str::random(64);
            }

            if (Schema::hasColumn('invitees', 'short_code')) {
                $invitee->short_code = $this->generateUniqueShortCode();
            }

            $invitee->save();

            $this->importedCount++;
        }

        /*
        |--------------------------------------------------------------------------
        | Error Handling
        |--------------------------------------------------------------------------
        | Valid rows are imported.
        | Invalid/duplicate rows are reported clearly.
        */

        if (! empty($this->errors)) {
            throw ValidationException::withMessages([
                'import_file' => $this->errors,
            ]);
        }
    }

    private function cleanText(mixed $value): string
    {
        return trim((string) $value);
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/\s+/', ' ', $name);

        return Str::lower($name);
    }

    private function normalizeTanzaniaPhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $phone = preg_replace('/\D+/', '', $phone);

        if (! $phone) {
            return null;
        }

        // Example: 00255711111111 -> 255711111111
        if (str_starts_with($phone, '00255')) {
            $phone = '255' . substr($phone, 5);
        }

        // Example: 0711111111 -> 255711111111
        if (str_starts_with($phone, '0') && strlen($phone) === 10) {
            $phone = '255' . substr($phone, 1);
        }

        // Example: 711111111 -> 255711111111
        if (strlen($phone) === 9 && preg_match('/^[67]/', $phone)) {
            $phone = '255' . $phone;
        }

        // Final format: 255 + 9 digits, starting with 6 or 7
        if (! preg_match('/^255[67]\d{8}$/', $phone)) {
            return null;
        }

        return $phone;
    }

    private function generateUniqueSerialNumber(): string
    {
        do {
            $serial = 'ELC-' . now()->format('ymd') . '-' . strtoupper(Str::random(6));
        } while (
            Invitee::query()
                ->where('event_id', $this->eventId)
                ->where('serial_number', $serial)
                ->exists()
        );

        return $serial;
    }

    private function generateUniqueShortCode(): string
    {
        do {
            $shortCode = strtoupper(Str::random(6));
        } while (
            Invitee::query()
                ->where('short_code', $shortCode)
                ->exists()
        );

        return $shortCode;
    }
}