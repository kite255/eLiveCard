<?php

namespace Tests\Unit;

use App\Models\GeneratedCard;
use App\Models\Invitee;
use App\Services\InvitationCardDownloadService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class InvitationCardDownloadServiceTest extends TestCase
{
    public function test_it_creates_an_archive_and_skips_missing_invitation_cards(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('cards/lucas.jpg', 'invitation-card');

        $invitee = new Invitee([
            'name' => 'Kitenken Lucas',
            'serial_number' => 'ELV-2026-ABC123',
        ]);

        $existing = new GeneratedCard(['file_path' => 'cards/lucas.jpg']);
        $existing->setRelation('invitee', $invitee);

        $missing = new GeneratedCard(['file_path' => 'cards/missing.jpg']);
        $missing->setRelation('invitee', new Invitee(['name' => 'Missing Member']));

        $result = app(InvitationCardDownloadService::class)->createArchive(
            collect([$existing, $missing]),
            'Wedding Event',
        );

        $this->assertSame(1, $result['added']);
        $this->assertSame(1, $result['skipped']);
        $this->assertStringStartsWith('wedding-event-invitation-cards-', $result['name']);

        $archive = new ZipArchive();
        $this->assertTrue($archive->open($result['path']) === true);
        $this->assertSame('invitation-card', $archive->getFromName($existing->download_name));
        $archive->close();

        @unlink($result['path']);
    }
}
