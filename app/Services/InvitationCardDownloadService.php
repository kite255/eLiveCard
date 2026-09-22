<?php

namespace App\Services;

use App\Models\GeneratedCard;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class InvitationCardDownloadService
{
    /**
     * @param  iterable<GeneratedCard>  $cards
     * @return array{path: string, name: string, added: int, skipped: int}
     */
    public function createArchive(iterable $cards, string $eventName): array
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'elive-invitation-cards-');

        if ($temporaryPath === false) {
            throw new RuntimeException('A temporary download file could not be created.');
        }

        $archive = new ZipArchive();
        $opened = $archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            @unlink($temporaryPath);

            throw new RuntimeException('The invitation card archive could not be created.');
        }

        $added = 0;
        $skipped = 0;

        foreach ($cards as $card) {
            $card->loadMissing('invitee');

            if (blank($card->file_path) || ! Storage::disk('public')->exists($card->file_path)) {
                $skipped++;
                continue;
            }

            if ($archive->addFile(
                Storage::disk('public')->path($card->file_path),
                $card->download_name,
            )) {
                $added++;
            } else {
                $skipped++;
            }
        }

        $archive->close();

        if ($added === 0) {
            @unlink($temporaryPath);

            throw new RuntimeException('None of the selected invitation card files could be found.');
        }

        $eventSlug = Str::slug($eventName) ?: 'event';

        return [
            'path' => $temporaryPath,
            'name' => "{$eventSlug}-invitation-cards-".now()->format('Ymd-His').'.zip',
            'added' => $added,
            'skipped' => $skipped,
        ];
    }
}
