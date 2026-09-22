<?php

namespace Tests\Unit;

use App\Models\ContributionRecipient;
use App\Services\ContributionCardDownloadService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ContributionCardDownloadServiceTest extends TestCase
{
    public function test_it_creates_a_zip_for_existing_generated_cards_and_skips_missing_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('cards/lucas.jpg', 'card-one');

        $existing = new ContributionRecipient([
            'name' => 'Kitenken Lucas',
            'generated_card_path' => 'cards/lucas.jpg',
        ]);
        $existing->id = 6;

        $missing = new ContributionRecipient([
            'name' => 'Missing Member',
            'generated_card_path' => 'cards/missing.jpg',
        ]);
        $missing->id = 7;

        $result = app(ContributionCardDownloadService::class)->createArchive(
            collect([$existing, $missing]),
            'Main Contributions',
        );

        $this->assertSame(1, $result['added']);
        $this->assertSame(1, $result['skipped']);
        $this->assertStringStartsWith('main-contributions-cards-', $result['name']);

        $archive = new ZipArchive();
        $this->assertTrue($archive->open($result['path']) === true);
        $this->assertSame('6-kitenken-lucas.jpg', $archive->getNameIndex(0));
        $this->assertSame('card-one', $archive->getFromName('6-kitenken-lucas.jpg'));
        $archive->close();

        @unlink($result['path']);
    }
}
