<?php

namespace App\Services;

use App\Models\CardTemplate;
use App\Models\CardTemplatePlaceholder;
use App\Models\GeneratedCard;
use App\Models\Invitee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class CardGenerationService
{
    /**
     * Maximum working width/height for generated cards.
     * This prevents GD memory errors when the uploaded template is very large.
     */
    protected int $maxWorkingDimension = 2400;

    /**
     * JPEG quality for final generated cards.
     * 88 is good quality for WhatsApp/SMS sharing and keeps file size reasonable.
     */
    protected int $jpegQuality = 88;

    public function generateForInvitee(Invitee $invitee): GeneratedCard
    {
        $invitee->loadMissing(['event', 'event.cardTemplates', 'cardType']);

        $template = $invitee->event?->cardTemplates()
            ->latest()
            ->first();

        if (! $template) {
            throw new \Exception('No card template found for this invitee event.');
        }

        return $this->generate($template, $invitee);
    }

    public function generate(CardTemplate $template, Invitee $invitee): GeneratedCard
    {
        ini_set('memory_limit', '512M');
        set_time_limit(120);

        $template->loadMissing(['event', 'placeholders']);
        $invitee->loadMissing(['cardType', 'event']);

        $this->ensureInviteeIdentity($invitee);
        $this->ensureInviteeHasQrCode($invitee);

        $templatePath = Storage::disk('public')->path($template->template_image);

        if (! file_exists($templatePath)) {
            throw new \Exception('Card template image not found: ' . $templatePath);
        }

        $manager = new ImageManager(new Driver());
        $image = $manager->read($templatePath);

        $this->resizeTemplateIfTooLarge($image);

        $imageWidth = $image->width();
        $imageHeight = $image->height();

        $placeholders = $template->placeholders()
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($placeholders as $placeholder) {
            if ($placeholder->isQrCode()) {
                $this->addQrCode(
                    image: $image,
                    invitee: $invitee,
                    placeholder: $placeholder,
                    manager: $manager,
                    imageWidth: $imageWidth,
                    imageHeight: $imageHeight
                );

                continue;
            }

            $value = $this->getPlaceholderValue($template, $invitee, $placeholder);

            if (filled($value)) {
                $this->addText(
                    image: $image,
                    text: $value,
                    placeholder: $placeholder,
                    imageWidth: $imageWidth,
                    imageHeight: $imageHeight
                );
            }
        }

        $path = $this->buildGeneratedCardPath($template, $invitee);

        Storage::disk('public')->put($path, (string) $image->toJpeg($this->jpegQuality));

        $generatedCard = GeneratedCard::updateOrCreate(
            [
                'invitee_id' => $invitee->id,
                'card_template_id' => $template->id,
            ],
            [
                'event_id' => $template->event_id,
                'file_path' => $path,
                'status' => GeneratedCard::STATUS_GENERATED,
                'generated_at' => now(),
            ]
        );

        $this->syncInviteeGeneratedCardPath($invitee, $path);

        return $generatedCard;
    }

    protected function resizeTemplateIfTooLarge($image): void
    {
        $width = $image->width();
        $height = $image->height();

        $largestSide = max($width, $height);

        if ($largestSide <= $this->maxWorkingDimension) {
            return;
        }

        $ratio = $this->maxWorkingDimension / $largestSide;

        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $image->resize($newWidth, $newHeight);
    }

    protected function ensureInviteeIdentity(Invitee $invitee): void
    {
        $needsSave = false;

        if (blank($invitee->serial_number)) {
            $invitee->serial_number = 'ELV-' . now()->format('Y') . '-' . strtoupper(Str::random(6));
            $needsSave = true;
        }

        if (Schema::hasColumn('invitees', 'short_code') && blank($invitee->short_code)) {
            do {
                $shortCode = strtoupper(Str::random(6));
            } while (Invitee::where('short_code', $shortCode)->whereKeyNot($invitee->id)->exists());

            $invitee->short_code = $shortCode;
            $needsSave = true;
        }

        if (Schema::hasColumn('invitees', 'qr_token') && blank($invitee->qr_token)) {
            $invitee->qr_token = Str::random(64);
            $needsSave = true;
        }

        if (
            Schema::hasColumn('invitees', 'qr_token_hash')
            && blank($invitee->qr_token_hash)
            && filled($invitee->qr_token)
        ) {
            $invitee->qr_token_hash = hash('sha256', $invitee->qr_token);
            $needsSave = true;
        }

        if (Schema::hasColumn('invitees', 'rsvp_token') && blank($invitee->rsvp_token)) {
            $invitee->rsvp_token = Str::random(48);
            $needsSave = true;
        }

        if ($needsSave) {
            $invitee->saveQuietly();
            $invitee->refresh();
        }
    }

    protected function ensureInviteeHasQrCode(Invitee $invitee): void
    {
        $path = $invitee->qr_code_path ?? $invitee->qr_code ?? null;

        if (filled($path) && Storage::disk('public')->exists($path)) {
            return;
        }

        if (method_exists($invitee, 'generateQrCode')) {
            $invitee->generateQrCode();
            $invitee->refresh();

            $path = $invitee->qr_code_path ?? $invitee->qr_code ?? null;

            if (filled($path) && Storage::disk('public')->exists($path)) {
                return;
            }
        }

        throw new \Exception('QR code image not found for invitee: ' . $invitee->name);
    }

    protected function getPlaceholderValue(
        CardTemplate $template,
        Invitee $invitee,
        CardTemplatePlaceholder $placeholder
    ): ?string {
        $event = $template->event ?? $invitee->event;

        return match ($placeholder->placeholder_key) {
            CardTemplatePlaceholder::PLACEHOLDER_NAME => $invitee->name,

            CardTemplatePlaceholder::PLACEHOLDER_CARD_TYPE => $invitee->cardType?->name ?? 'Card',

            CardTemplatePlaceholder::PLACEHOLDER_SERIAL_NUMBER => $invitee->serial_number,

            CardTemplatePlaceholder::PLACEHOLDER_GUEST_COUNT,
            CardTemplatePlaceholder::PLACEHOLDER_ALLOWED_GUESTS => (string) (
                $invitee->allowed_guests
                ?? $invitee->guest_count
                ?? $invitee->cardType?->allowed_guests
                ?? $invitee->cardType?->allowed_people
                ?? $invitee->cardType?->guest_count
                ?? 1
            ),

            CardTemplatePlaceholder::PLACEHOLDER_TABLE_NUMBER => filled($invitee->table_number)
                ? (string) $invitee->table_number
                : null,

            CardTemplatePlaceholder::PLACEHOLDER_CATEGORY => $invitee->category,

            CardTemplatePlaceholder::PLACEHOLDER_EVENT_NAME => $event?->title ?? $event?->name,

            CardTemplatePlaceholder::PLACEHOLDER_EVENT_DATE => $this->formatDateValue(
                $event?->event_date ?? $event?->date ?? null,
                'd/m/Y'
            ),

            CardTemplatePlaceholder::PLACEHOLDER_EVENT_TIME => $this->formatDateValue(
                $event?->event_time ?? $event?->time ?? null,
                'H:i'
            ),

            CardTemplatePlaceholder::PLACEHOLDER_EVENT_VENUE => $event?->venue,

            default => null,
        };
    }

    protected function addText(
        $image,
        string $text,
        CardTemplatePlaceholder $placeholder,
        int $imageWidth,
        int $imageHeight
    ): void {
        $x = $this->percentToPixels($placeholder->x_percent, $imageWidth);
        $y = $this->percentToPixels($placeholder->y_percent, $imageHeight);

        $boxWidth = max(10, $this->percentToPixels($placeholder->width_percent, $imageWidth));
        $boxHeight = max(10, $this->percentToPixels($placeholder->height_percent, $imageHeight));

        $fontSize = max(8, (int) ($placeholder->font_size ?: 24));
        $fontColor = $placeholder->font_color ?: '#000000';
        $fontWeight = $placeholder->font_weight ?: 'normal';
        $textAlign = $placeholder->text_align ?: 'center';

        $fontFile = $this->resolveFontFile(
            fontFamily: $placeholder->font_family ?: CardTemplatePlaceholder::defaultFontFamily(),
            fontWeight: $fontWeight
        );

        $lines = $this->wrapTextToBox(
            text: $text,
            boxWidth: $boxWidth,
            fontSize: $fontSize
        );

        $lineHeight = (int) round($fontSize * 1.25);
        $textBlockHeight = max($lineHeight, count($lines) * $lineHeight);

        $startY = $y + max(0, (int) round(($boxHeight - $textBlockHeight) / 2));

        foreach ($lines as $index => $line) {
            $lineY = $startY + ($index * $lineHeight);

            $drawX = match ($textAlign) {
                'left' => $x,
                'right' => $x + $boxWidth,
                default => $x + (int) round($boxWidth / 2),
            };

            $image->text(
                $line,
                (int) $drawX,
                (int) $lineY,
                function ($font) use ($fontFile, $fontSize, $fontColor, $textAlign): void {
                    if ($fontFile && file_exists($fontFile)) {
                        $font->filename($fontFile);
                    }

                    $font->size($fontSize);
                    $font->color($fontColor);
                    $font->align($textAlign);
                    $font->valign('top');
                }
            );
        }
    }

    protected function addQrCode(
        $image,
        Invitee $invitee,
        CardTemplatePlaceholder $placeholder,
        ImageManager $manager,
        int $imageWidth,
        int $imageHeight
    ): void {
        $x = $this->percentToPixels($placeholder->x_percent, $imageWidth);
        $y = $this->percentToPixels($placeholder->y_percent, $imageHeight);

        $boxWidth = max(40, $this->percentToPixels($placeholder->width_percent, $imageWidth));
        $boxHeight = max(40, $this->percentToPixels($placeholder->height_percent, $imageHeight));

        $maxBoxSize = min($boxWidth, $boxHeight);

        $requestedQrSize = (int) ($placeholder->qr_size ?: $maxBoxSize);

        $qrSize = min($requestedQrSize, $maxBoxSize);
        $qrSize = max(40, min($qrSize, 800));

        if ($maxBoxSize < 80) {
            $qrSize = $maxBoxSize;
        }

        $qrFullPath = $this->getInviteeQrFullPath($invitee);

        if (! $qrFullPath || ! file_exists($qrFullPath)) {
            throw new \Exception('QR code image not found for invitee: ' . $invitee->name);
        }

        $qrImage = $manager->read($qrFullPath)->resize($qrSize, $qrSize);

        $placeX = $x + (int) round(($boxWidth - $qrSize) / 2);
        $placeY = $y + (int) round(($boxHeight - $qrSize) / 2);

        $image->place($qrImage, 'top-left', $placeX, $placeY);
    }

    protected function getInviteeQrFullPath(Invitee $invitee): ?string
    {
        $path = $invitee->qr_code_path ?? $invitee->qr_code ?? null;

        if (blank($path)) {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->path($path);
    }

    protected function buildGeneratedCardPath(CardTemplate $template, Invitee $invitee): string
    {
        $serialNumber = $invitee->serial_number
            ?: 'ELC-' . str_pad((string) $invitee->id, 6, '0', STR_PAD_LEFT);

        $safeName = Str::slug($invitee->name ?: 'invitee');
        $safeSerialNumber = preg_replace('/[^A-Za-z0-9\-_]/', '-', $serialNumber);

        return 'events/' . $template->event_id . '/generated-cards/' . $safeName . '-' . $safeSerialNumber . '.jpg';
    }

    protected function syncInviteeGeneratedCardPath(Invitee $invitee, string $path): void
    {
        if (! Schema::hasColumn('invitees', 'generated_card_path')) {
            return;
        }

        $invitee->forceFill([
            'generated_card_path' => $path,
        ])->saveQuietly();
    }

    protected function wrapTextToBox(string $text, int $boxWidth, int $fontSize): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if ($text === '') {
            return [''];
        }

        $averageCharWidth = max(5, (int) round($fontSize * 0.55));
        $maxCharsPerLine = max(1, (int) floor($boxWidth / $averageCharWidth));

        if (mb_strlen($text) <= $maxCharsPerLine) {
            return [$text];
        }

        $words = preg_split('/\s+/', $text) ?: [];
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            $candidate = $currentLine === '' ? $word : $currentLine . ' ' . $word;

            if (mb_strlen($candidate) <= $maxCharsPerLine) {
                $currentLine = $candidate;

                continue;
            }

            if ($currentLine !== '') {
                $lines[] = $currentLine;
            }

            $currentLine = $word;
        }

        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }

        return $lines;
    }

    protected function resolveFontFile(string $fontFamily, string $fontWeight = 'normal'): ?string
    {
        $fontFiles = CardTemplatePlaceholder::fontFiles();

        if (! array_key_exists($fontFamily, $fontFiles)) {
            $fontFamily = CardTemplatePlaceholder::defaultFontFamily();
        }

        $weight = $fontWeight === 'bold' ? 'bold' : 'regular';

        return $fontFiles[$fontFamily][$weight]
            ?? $fontFiles[CardTemplatePlaceholder::defaultFontFamily()][$weight]
            ?? null;
    }

    protected function percentToPixels(mixed $percent, int $total): int
    {
        return (int) round(((float) $percent / 100) * $total);
    }

    protected function formatDateValue($value, string $format): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            if ($value instanceof \DateTimeInterface) {
                return $value->format($format);
            }

            return Carbon::parse($value)->format($format);
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
