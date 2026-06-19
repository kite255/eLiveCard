<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class GenerateSampleQr extends Command
{
    protected $signature = 'elive:sample-qr';

    protected $description = 'Generate sample QR code for card template designer';

    public function handle(): int
    {
        Storage::disk('public')->makeDirectory('system');

        $path = storage_path('app/public/system/sample-qr.png');

        QrCode::format('png')
            ->size(300)
            ->margin(1)
            ->generate('https://elivecard.test/sample-invitation', $path);

        $this->info('Sample QR generated successfully: storage/app/public/system/sample-qr.png');

        return self::SUCCESS;
    }
}