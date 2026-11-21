<?php

namespace App\Imports;

use App\Models\ContributionCampaign;
use App\Models\ContributionRecipient;
use App\Support\WhatsAppPhone;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ContributionRecipientsImport implements ToCollection, WithHeadingRow
{
    public array $errors = [];

    public int $importedCount = 0;

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

            $normalizedPhone = WhatsAppPhone::normalize($phone);

            if (! $normalizedPhone) {
                $this->errors[] = "Row {$rowNumber}: Phone number '{$phone}' is invalid. Enter a Tanzanian mobile number or a WhatsApp number with its country code.";
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
}
