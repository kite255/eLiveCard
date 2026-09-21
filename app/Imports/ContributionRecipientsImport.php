<?php

namespace App\Imports;

use App\Models\ContributionCampaign;
use App\Models\ContributionRecipient;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ContributionRecipientsImport implements ToCollection, WithHeadingRow
{
    public array $errors = [];

    public int $importedCount = 0;

    protected array $phonesInFile = [];

    public function __construct(protected int $campaignId) {}

    public function collection(Collection $rows): void
    {
        $campaign = ContributionCampaign::query()->findOrFail($this->campaignId);

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $name = trim((string) ($row['name'] ?? ''));
            $phone = trim((string) ($row['phone'] ?? ''));

            if ($name === '') {
                $this->errors[] = "Row {$rowNumber}: Name is required.";
            }

            if ($phone === '') {
                $this->errors[] = "Row {$rowNumber}: Phone number is required.";
            }

            if ($name === '' || $phone === '') {
                continue;
            }

            $normalizedPhone = self::normalizePhone($phone);

            if (! $normalizedPhone) {
                $this->errors[] = "Row {$rowNumber}: Phone number '{$phone}' is invalid. Use a valid Tanzania phone number.";
                continue;
            }

            if (in_array($normalizedPhone, $this->phonesInFile, true)) {
                $this->errors[] = "Row {$rowNumber}: Phone number '{$phone}' is duplicated in this Excel file.";
                continue;
            }

            $this->phonesInFile[] = $normalizedPhone;

            if ($campaign->recipients()->where('phone', $normalizedPhone)->exists()) {
                $this->errors[] = "Row {$rowNumber}: Phone number '{$phone}' already exists in this campaign.";
                continue;
            }

            $campaign->recipients()->create([
                'event_id' => $campaign->event_id,
                'name' => preg_replace('/\s+/u', ' ', $name),
                'phone' => $normalizedPhone,
                'generation_status' => ContributionRecipient::STATUS_PENDING,
                'send_status' => ContributionRecipient::STATUS_PENDING,
                'whatsapp_status' => ContributionRecipient::STATUS_NOT_SENT,
            ]);

            $this->importedCount++;
        }

        if ($this->errors !== []) {
            throw ValidationException::withMessages([
                'import_file' => $this->errors,
            ]);
        }
    }

    public static function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '0')) {
            $digits = '255'.substr($digits, 1);
        } elseif (strlen($digits) === 9) {
            $digits = '255'.$digits;
        }

        return preg_match('/^255[67]\d{8}$/', $digits) ? $digits : null;
    }
}
