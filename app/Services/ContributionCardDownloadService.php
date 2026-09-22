<?php

namespace App\Services;

use App\Models\ContributionRecipient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ContributionCardDownloadService
{
    /**
     * @param  iterable<ContributionRecipient>  $recipients
     * @return array{path: string, name: string, added: int, skipped: int}
     */
    public function createArchive(iterable $recipients, string $campaignName): array
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'elive-contribution-cards-');

        if ($temporaryPath === false) {
            throw new RuntimeException('A temporary download file could not be created.');
        }

        $archive = new ZipArchive();
        $opened = $archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            @unlink($temporaryPath);

            throw new RuntimeException('The contribution card archive could not be created.');
        }

        $added = 0;
        $skipped = 0;

        foreach ($recipients as $recipient) {
            $storedPath = $recipient->generated_card_path;

            if (! filled($storedPath) || ! Storage::disk('public')->exists($storedPath)) {
                $skipped++;
                continue;
            }

            if ($archive->addFile(
                Storage::disk('public')->path($storedPath),
                $this->cardFileName($recipient),
            )) {
                $added++;
            } else {
                $skipped++;
            }
        }

        $archive->close();

        if ($added === 0) {
            @unlink($temporaryPath);

            throw new RuntimeException('None of the selected generated card files could be found.');
        }

        $campaignSlug = Str::slug($campaignName) ?: 'contribution';

        return [
            'path' => $temporaryPath,
            'name' => "{$campaignSlug}-cards-".now()->format('Ymd-His').'.zip',
            'added' => $added,
            'skipped' => $skipped,
        ];
    }

    public function cardFileName(ContributionRecipient $recipient): string
    {
        $extension = strtolower(pathinfo((string) $recipient->generated_card_path, PATHINFO_EXTENSION));

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = 'jpg';
        }

        $name = Str::slug($recipient->name) ?: 'member';

        return "{$recipient->id}-{$name}.{$extension}";
    }
}
