<?php

namespace App\Services;

use App\Models\CardTemplate;
use App\Models\CardTemplatePlaceholder;
use App\Models\GeneratedCard;
use App\Models\Invitee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

class CardGenerationService
{
    /**
     * Maximum working width/height for generated cards.
     * This prevents GD memory errors when the uploaded template image is very large.
     */
    protected int $maxWorkingDimension = 2400;

    /**
     * JPEG quality for final generated cards.
     */
    protected int $jpegQuality = 88;

    public function generateForInvitee(Invitee $invitee): GeneratedCard
    {
        $invitee->loadMissing(['event', 'cardType']);

        $event = $invitee->event;

        if (! $event) {
            throw new \Exception('Invitee does not belong to an event.');
        }

        $template = $event->cardTemplates()
            ->when(Schema::hasColumn('card_templates', 'status'), function ($query) {
                $query->whereIn('status', ['active', 'published', 'draft']);
            })
            ->latest('id')
            ->first();

        if (! $template) {
            throw new \Exception('No card template found for this invitee event. Please upload/design a template first.');
        }

        return $this->generate($template, $invitee);
    }

    public function generate(CardTemplate $template, Invitee $invitee): GeneratedCard
    {
        ini_set('memory_limit', '512M');
        set_time_limit(120);

        $template->loadMissing(['event', 'placeholders']);
        $invitee->loadMissing(['cardType', 'event']);

        if ((int) $template->event_id !== (int) $invitee->event_id) {
            throw new \Exception('The selected card template does not belong to the invitee event.');
        }

        $this->markGeneratedCardStatus($template, $invitee, $this->generatedStatus('generating'));

        try {
            $this->ensureInviteeIdentity($invitee);
            $this->ensureInviteeHasQrCode($invitee);

            $templatePath = $this->resolvePublicStoragePath($template->template_image ?? null);

            if (! $templatePath || ! file_exists($templatePath)) {
                throw new \Exception('Card template image not found: ' . ($template->template_image ?? 'empty path'));
            }

            $manager = new ImageManager(new Driver());
            $image = $manager->read($templatePath);

            $this->resizeTemplateIfTooLarge($image);

            $imageWidth = $image->width();
            $imageHeight = $image->height();

            $placeholders = $template->placeholders()
                ->where(function ($query) {
                    $query->where('is_visible', true)
                        ->orWhereNull('is_visible');
                })
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            foreach ($placeholders as $placeholder) {
                if ($this->isQrPlaceholder($placeholder)) {
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
                    'status' => $this->generatedStatus('generated'),
                    'generated_at' => now(),
                ]
            );

            $this->syncInviteeGeneratedCardPath($invitee, $path);
            $this->syncInviteeCardStatus($invitee, 'generated');

            return $generatedCard;
        } catch (Throwable $exception) {
            $this->markGeneratedCardStatus($template, $invitee, $this->generatedStatus('failed'), $exception->getMessage());
            $this->syncInviteeCardStatus($invitee, 'failed');

            Log::error('Failed to generate invitee card.', [
                'event_id' => $template->event_id,
                'template_id' => $template->id,
                'invitee_id' => $invitee->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
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

        if (Schema::hasColumn('invitees', 'serial_number') && blank($invitee->serial_number)) {
            do {
                $serialNumber = 'ELV-' . now()->format('Y') . '-' . strtoupper(Str::random(6));
            } while (Invitee::where('serial_number', $serialNumber)->whereKeyNot($invitee->id)->exists());

            $invitee->serial_number = $serialNumber;
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

        if (Schema::hasColumn('invitees', 'qr_token_hash') && blank($invitee->qr_token_hash)) {
            $token = $invitee->qr_token ?: Str::random(64);

            if (Schema::hasColumn('invitees', 'qr_token') && blank($invitee->qr_token)) {
                $invitee->qr_token = $token;
            }

            $invitee->qr_token_hash = hash('sha256', $token);
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

        throw new \Exception('QR code image not found for invitee: ' . ($invitee->name ?: $invitee->id));
    }

    protected function getPlaceholderValue(
        CardTemplate $template,
        Invitee $invitee,
        CardTemplatePlaceholder $placeholder
    ): ?string {
        $event = $template->event ?? $invitee->event;
        $key = $this->normalizePlaceholderKey((string) ($placeholder->placeholder_key ?? ''));

        return match ($key) {
            'name', 'guest_name', 'invitee_name' => $invitee->name,

            'card_type' => $invitee->cardType?->name ?? $invitee->card_type ?? 'Card',

            'serial_number', 'serial' => $invitee->serial_number,

            'guest_count', 'allowed_guests', 'guests', 'allowed_people' => (string) $this->resolveAllowedGuests($invitee),

            'table_number', 'table' => filled($invitee->table_number) ? (string) $invitee->table_number : null,

            'category' => $invitee->category,

            'event_name', 'event_title' => $event?->title ?? $event?->name,

            'event_date', 'date' => $this->formatDateValue(
                $event?->event_date ?? $event?->date ?? $event?->start_date ?? null,
                'd/m/Y'
            ),

            'event_time', 'time' => $this->formatEventTime($event),

            'event_venue', 'venue' => $event?->venue_name
                ?? $event?->venue
                ?? $event?->venue_address
                ?? null,

            'location', 'location_link', 'google_maps_link' => $event?->google_maps_link ?? null,

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
        $x = $this->percentToPixels($placeholder->x_percent ?? 0, $imageWidth);
        $y = $this->percentToPixels($placeholder->y_percent ?? 0, $imageHeight);

        $boxWidth = max(10, $this->percentToPixels($placeholder->width_percent ?? 20, $imageWidth));
        $boxHeight = max(10, $this->percentToPixels($placeholder->height_percent ?? 5, $imageHeight));

        $fontSize = max(8, (int) ($placeholder->font_size ?: 24));
        $fontColor = $this->normalizeHexColor($placeholder->font_color ?: '#000000');
        $fontWeight = $placeholder->font_weight ?: 'normal';
        $textAlign = in_array($placeholder->text_align, ['left', 'center', 'right'], true)
            ? $placeholder->text_align
            : 'center';

        $fontFile = $this->resolveFontFile(
            fontFamily: $placeholder->font_family ?: $this->defaultFontFamily(),
            fontWeight: $fontWeight
        );

        $lines = $this->wrapTextToBox(
            text: $text,
            boxWidth: $boxWidth,
            fontSize: $fontSize,
            fontFile: $fontFile
        );

        $lineHeight = max(10, (int) round($fontSize * 1.25));
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
        $x = $this->percentToPixels($placeholder->x_percent ?? 0, $imageWidth);
        $y = $this->percentToPixels($placeholder->y_percent ?? 0, $imageHeight);

        $boxWidth = max(40, $this->percentToPixels($placeholder->width_percent ?? 15, $imageWidth));
        $boxHeight = max(40, $this->percentToPixels($placeholder->height_percent ?? 15, $imageHeight));

        $maxBoxSize = min($boxWidth, $boxHeight);
        $requestedQrSize = (int) ($placeholder->qr_size ?: $maxBoxSize);
        $qrSize = max(40, min($requestedQrSize, $maxBoxSize, 800));

        if ($maxBoxSize < 80) {
            $qrSize = $maxBoxSize;
        }

        $qrFullPath = $this->getInviteeQrFullPath($invitee);

        if (! $qrFullPath || ! file_exists($qrFullPath)) {
            throw new \Exception('QR code image not found for invitee: ' . ($invitee->name ?: $invitee->id));
        }

        $qrImage = $manager->read($qrFullPath)->resize($qrSize, $qrSize);

        $placeX = $x + (int) round(($boxWidth - $qrSize) / 2);
        $placeY = $y + (int) round(($boxHeight - $qrSize) / 2);

        // Add a small white background/padding behind the QR so it scans better on busy card designs.
        $padding = max(4, (int) round($qrSize * 0.04));
        $backgroundSize = $qrSize + ($padding * 2);
        $backgroundX = $placeX - $padding;
        $backgroundY = $placeY - $padding;

        if (method_exists($image, 'drawRectangle')) {
            $image->drawRectangle($backgroundX, $backgroundY, function ($rectangle) use ($backgroundSize): void {
                $rectangle->size($backgroundSize, $backgroundSize);
                $rectangle->background('#FFFFFF');
            });
        }

        $image->place($qrImage, 'top-left', $placeX, $placeY);
    }

    protected function getInviteeQrFullPath(Invitee $invitee): ?string
    {
        $path = $invitee->qr_code_path ?? $invitee->qr_code ?? null;

        return $this->resolvePublicStoragePath($path);
    }

    protected function buildGeneratedCardPath(CardTemplate $template, Invitee $invitee): string
    {
        $serialNumber = $invitee->serial_number
            ?: 'ELV-' . now()->format('Y') . '-' . str_pad((string) $invitee->id, 6, '0', STR_PAD_LEFT);

        $safeName = Str::slug($invitee->name ?: 'invitee-' . $invitee->id);
        $safeSerialNumber = preg_replace('/[^A-Za-z0-9\-_]/', '-', $serialNumber);

        return 'events/' . $template->event_id . '/generated-cards/' . $safeName . '-' . $safeSerialNumber . '.jpg';
    }

    protected function syncInviteeGeneratedCardPath(Invitee $invitee, string $path): void
    {
        $updates = [];

        if (Schema::hasColumn('invitees', 'generated_card_path')) {
            $updates['generated_card_path'] = $path;
        }

        if (Schema::hasColumn('invitees', 'card_path')) {
            $updates['card_path'] = $path;
        }

        if (! empty($updates)) {
            $invitee->forceFill($updates)->saveQuietly();
        }
    }

    protected function syncInviteeCardStatus(Invitee $invitee, string $status): void
    {
        if (! Schema::hasColumn('invitees', 'card_status')) {
            return;
        }

        $invitee->forceFill([
            'card_status' => $status,
        ])->saveQuietly();
    }

    protected function markGeneratedCardStatus(CardTemplate $template, Invitee $invitee, string $status, ?string $error = null): void
    {
        $values = [
            'event_id' => $template->event_id,
            'status' => $status,
        ];

        if ($status === $this->generatedStatus('generated') && Schema::hasColumn('generated_cards', 'generated_at')) {
            $values['generated_at'] = now();
        }

        if ($error && Schema::hasColumn('generated_cards', 'error_message')) {
            $values['error_message'] = Str::limit($error, 1000);
        }

        GeneratedCard::updateOrCreate(
            [
                'invitee_id' => $invitee->id,
                'card_template_id' => $template->id,
            ],
            $values
        );
    }

    protected function wrapTextToBox(string $text, int $boxWidth, int $fontSize, ?string $fontFile = null): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if ($text === '') {
            return [''];
        }

        $words = preg_split('/\s+/', $text) ?: [];
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            $candidate = $currentLine === '' ? $word : $currentLine . ' ' . $word;

            if ($this->estimateTextWidth($candidate, $fontSize, $fontFile) <= $boxWidth) {
                $currentLine = $candidate;
                continue;
            }

            if ($currentLine !== '') {
                $lines[] = $currentLine;
                $currentLine = $word;
                continue;
            }

            $lines[] = $word;
            $currentLine = '';
        }

        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }

        return $lines ?: [$text];
    }

    protected function estimateTextWidth(string $text, int $fontSize, ?string $fontFile = null): int
    {
        if ($fontFile && file_exists($fontFile) && function_exists('imagettfbbox')) {
            $box = imagettfbbox($fontSize, 0, $fontFile, $text);

            if (is_array($box)) {
                return abs((int) $box[2] - (int) $box[0]);
            }
        }

        $averageCharWidth = max(5, (int) round($fontSize * 0.55));

        return mb_strlen($text) * $averageCharWidth;
    }

    protected function resolveFontFile(string $fontFamily, string $fontWeight = 'normal'): ?string
    {
        if (! method_exists(CardTemplatePlaceholder::class, 'fontFiles')) {
            return null;
        }

        $fontFiles = CardTemplatePlaceholder::fontFiles();

        if (! array_key_exists($fontFamily, $fontFiles)) {
            $fontFamily = $this->defaultFontFamily();
        }

        $weight = $fontWeight === 'bold' ? 'bold' : 'regular';

        return $fontFiles[$fontFamily][$weight]
            ?? $fontFiles[$fontFamily]['regular']
            ?? $fontFiles[$this->defaultFontFamily()][$weight]
            ?? $fontFiles[$this->defaultFontFamily()]['regular']
            ?? null;
    }

    protected function defaultFontFamily(): string
    {
        if (method_exists(CardTemplatePlaceholder::class, 'defaultFontFamily')) {
            return CardTemplatePlaceholder::defaultFontFamily();
        }

        return 'Poppins';
    }

    protected function percentToPixels(mixed $percent, int $total): int
    {
        return (int) round(((float) ($percent ?? 0) / 100) * $total);
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
        } catch (Throwable) {
            return (string) $value;
        }
    }

    protected function formatEventTime($event): ?string
    {
        if (! $event) {
            return null;
        }

        $start = $event->start_time ?? $event->event_time ?? $event->time ?? null;
        $end = $event->end_time ?? null;

        $startText = $this->formatDateValue($start, 'H:i');
        $endText = $this->formatDateValue($end, 'H:i');

        if ($startText && $endText) {
            return $startText . ' - ' . $endText;
        }

        return $startText ?: $endText;
    }

    protected function resolveAllowedGuests(Invitee $invitee): int
    {
        return (int) (
            $invitee->allowed_guests
            ?? $invitee->guest_count
            ?? $invitee->cardType?->allowed_guests
            ?? $invitee->cardType?->allowed_people
            ?? $invitee->cardType?->guest_count
            ?? 1
        );
    }

    protected function isQrPlaceholder(CardTemplatePlaceholder $placeholder): bool
    {
        if (method_exists($placeholder, 'isQrCode') && $placeholder->isQrCode()) {
            return true;
        }

        return in_array($this->normalizePlaceholderKey((string) ($placeholder->placeholder_key ?? '')), [
            'qr_code',
            'qrcode',
            'qr',
        ], true);
    }

    protected function normalizePlaceholderKey(string $key): string
    {
        return Str::of($key)
            ->trim()
            ->lower()
            ->replace(['{{', '}}', '#'], '')
            ->replace(['-', ' '], '_')
            ->toString();
    }

    protected function normalizeHexColor(string $color): string
    {
        $color = trim($color);

        if (! str_starts_with($color, '#')) {
            $color = '#' . $color;
        }

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? $color : '#000000';
    }

    protected function resolvePublicStoragePath(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            $storagePosition = strpos($path, '/storage/');

            if ($storagePosition !== false) {
                $path = substr($path, $storagePosition + strlen('/storage/'));
            }
        }

        $path = ltrim((string) $path, '/');

        if (Str::startsWith($path, 'storage/')) {
            $path = Str::after($path, 'storage/');
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->path($path);
    }

    protected function generatedStatus(string $status): string
    {
        $constant = GeneratedCard::class . '::STATUS_' . strtoupper($status);

        return defined($constant) ? constant($constant) : $status;
    }
}
