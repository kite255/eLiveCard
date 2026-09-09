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
     * JPEG quality for final generated cards.
     *
     * Quality 95 provides sharp text, logos, and QR edges without the excessive
     * file size normally produced by quality 100.
     */
    protected int $jpegQuality = 95;

    public function generateForInvitee(Invitee $invitee): GeneratedCard
    {
        $invitee->loadMissing(['event', 'cardType']);

        $event = $invitee->event;

        if (! $event) {
            throw new \Exception('Invitee does not belong to an event.');
        }

        $templateQuery = $event->cardTemplates();

        $template = Schema::hasColumn('card_templates', 'status')
            ? (clone $templateQuery)
                ->whereIn('status', ['active', 'published'])
                ->latest('id')
                ->first()
            : null;

        $template ??= (clone $templateQuery)
            ->when(
                Schema::hasColumn('card_templates', 'status'),
                fn ($query) => $query->where('status', 'draft')
            )
            ->latest('id')
            ->first();

        $template ??= $templateQuery
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

            /*
            |--------------------------------------------------------------------------
            | Preserve the designer canvas exactly
            |--------------------------------------------------------------------------
            | Placeholder positions, box sizes, font sizes, and QR sizes are saved
            | against the uploaded template. Resizing the template during generation
            | changes that coordinate system and causes generated cards to drift from
            | what the designer showed.
            |
            | Therefore generation always uses the original uploaded image dimensions.
            */
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
                        imageHeight: $imageHeight,
                        template: $template
                    );
                }
            }

            $path = $this->buildGeneratedCardPath($template, $invitee);

            $encodedCard = $image->toJpeg(
                quality: $this->jpegQuality,
                progressive: true
            );

            Storage::disk('public')->makeDirectory(
                dirname($path)
            );

            Storage::disk('public')->put(
                $path,
                (string) $encodedCard
            );

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
        int $imageHeight,
        CardTemplate $template
    ): void {
        $text = trim($text);

        if ($text === '' || preg_match('/^[\p{P}\p{S}\s]+$/u', $text)) {
            return;
        }

        [$x, $y, $boxWidth, $boxHeight] = $this->resolveDesignerPlaceholderBox(
            placeholder: $placeholder,
            imageWidth: $imageWidth,
            imageHeight: $imageHeight,
            defaultWidthPercent: 20,
            defaultHeightPercent: 5,
            minimumWidth: 10,
            minimumHeight: 10,
        );

        /*
        |--------------------------------------------------------------------------
        | Scale designer font size to the real source-image canvas
        |--------------------------------------------------------------------------
        | Placeholder geometry is stored as percentages and therefore already
        | scales correctly on the real image. Font size is stored in pixels on the
        | designer canvas, so it must be scaled from the stored template height to
        | the actual uploaded image height.
        |
        | Example for the current event:
        | 1920 designer height -> 4749 real image height
        | scale = 4749 / 1920 ~= 2.473
        */
        $fontScale = $this->resolveFontScale(
            template: $template,
            imageHeight: $imageHeight,
        );

        $savedFontSize = max(
            8,
            (int) ($placeholder->font_size ?: CardTemplatePlaceholder::DEFAULT_FONT_SIZE)
        );

        $fontSize = max(
            8,
            (int) round($savedFontSize * $fontScale)
        );

        $fontColor = $this->normalizeHexColor(
            $placeholder->font_color ?: CardTemplatePlaceholder::DEFAULT_FONT_COLOR
        );

        $fontWeight = $placeholder->font_weight ?: 'normal';

        $textAlign = in_array(
            $placeholder->text_align,
            ['left', 'center', 'right'],
            true
        ) ? $placeholder->text_align : 'center';

        $fontFile = $this->resolveFontFile(
            fontFamily: $placeholder->font_family ?: $this->defaultFontFamily(),
            fontWeight: $fontWeight
        );

        $placeholderKey = $this->normalizePlaceholderKey(
            (string) ($placeholder->placeholder_key ?? '')
        );

        /*
        |--------------------------------------------------------------------------
        | Match designer behaviour for single-line fields
        |--------------------------------------------------------------------------
        | Name, card type, serial, table, category and guest-count placeholders
        | remain on one line. Only shrink if the actual value is wider than the
        | exact designer box.
        */
        if ($this->isSingleLineTextPlaceholder($placeholderKey)) {
            $fontSize = $this->fitSingleLineFontSize(
                text: $text,
                boxWidth: $boxWidth,
                initialFontSize: $fontSize,
                fontFile: $fontFile,
            );

            $lines = [$text];
        } else {
            [$fontSize, $lines] = $this->fitTextToBox(
                text: $text,
                boxWidth: $boxWidth,
                boxHeight: $boxHeight,
                initialFontSize: $fontSize,
                fontFile: $fontFile,
            );
        }

        $lineHeight = max(
            10,
            (int) round($fontSize * 1.22)
        );

        $textBlockHeight = max(
            $lineHeight,
            count($lines) * $lineHeight
        );

        /*
        |--------------------------------------------------------------------------
        | Same vertical alignment as designer
        |--------------------------------------------------------------------------
        | The preview centers the text inside the draggable placeholder rectangle.
        */
        $startY = $y + max(
            0,
            (int) round(($boxHeight - $textBlockHeight) / 2)
        );

        foreach ($lines as $index => $line) {
            $lineY = $startY + ($index * $lineHeight);

            if ($lineY + $lineHeight > $y + $boxHeight) {
                break;
            }

            $drawX = match ($textAlign) {
                'left' => $x,
                'right' => $x + $boxWidth,
                default => $x + (int) round($boxWidth / 2),
            };

            $image->text(
                $line,
                (int) $drawX,
                (int) $lineY,
                function ($font) use (
                    $fontFile,
                    $fontSize,
                    $fontColor,
                    $textAlign
                ): void {
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
        [$x, $y, $boxWidth, $boxHeight] = $this->resolveDesignerPlaceholderBox(
            placeholder: $placeholder,
            imageWidth: $imageWidth,
            imageHeight: $imageHeight,
            defaultWidthPercent: CardTemplatePlaceholder::DEFAULT_QR_WIDTH_PERCENT,
            defaultHeightPercent: CardTemplatePlaceholder::DEFAULT_QR_HEIGHT_PERCENT,
            minimumWidth: 40,
            minimumHeight: 40,
        );

        /*
        |--------------------------------------------------------------------------
        | Designer QR box is the source of truth
        |--------------------------------------------------------------------------
        | The visible QR occupies the largest square that fits inside the exact
        | saved placeholder box. qr_size is only a quality/source preference; it
        | must not independently change the visible designer geometry.
        */
        $qrSize = max(
            1,
            min(
                $boxWidth,
                $boxHeight,
                CardTemplatePlaceholder::MAX_QR_SIZE
            )
        );

        $qrFullPath = $this->getInviteeQrFullPath($invitee);

        if (! $qrFullPath || ! file_exists($qrFullPath)) {
            throw new \Exception(
                'QR code image not found for invitee: '
                .($invitee->name ?: $invitee->id)
            );
        }

        $qrColor = $this->normalizeHexColor(
            $placeholder->qr_color
                ?: CardTemplatePlaceholder::DEFAULT_QR_COLOR
        );

        $qrBackgroundColor = $this->normalizeHexColor(
            $placeholder->qr_background_color
                ?: CardTemplatePlaceholder::DEFAULT_QR_BACKGROUND_COLOR
        );

        if (! $this->hasSafeQrContrast(
            foregroundHex: $qrColor,
            backgroundHex: $qrBackgroundColor,
        )) {
            Log::warning('Unsafe QR contrast detected. Falling back to secure defaults.', [
                'template_id' => $placeholder->card_template_id,
                'placeholder_id' => $placeholder->id,
                'invitee_id' => $invitee->id,
                'qr_color' => $qrColor,
                'qr_background_color' => $qrBackgroundColor,
            ]);

            $qrColor = CardTemplatePlaceholder::DEFAULT_QR_COLOR;
            $qrBackgroundColor = CardTemplatePlaceholder::DEFAULT_QR_BACKGROUND_COLOR;
        }

        $qrBinary = $this->buildColoredQrPng(
            sourcePath: $qrFullPath,
            size: $qrSize,
            foregroundHex: $qrColor,
            backgroundHex: $qrBackgroundColor,
        );

        $qrImage = $manager->read($qrBinary);

        /*
        |--------------------------------------------------------------------------
        | Center exact square inside designer rectangle
        |--------------------------------------------------------------------------
        */
        $placeX = $x + (int) round(($boxWidth - $qrSize) / 2);
        $placeY = $y + (int) round(($boxHeight - $qrSize) / 2);

        if (method_exists($image, 'drawRectangle')) {
            $image->drawRectangle(
                $placeX,
                $placeY,
                function ($rectangle) use (
                    $qrSize,
                    $qrBackgroundColor
                ): void {
                    $rectangle->size($qrSize, $qrSize);
                    $rectangle->background($qrBackgroundColor);
                }
            );
        }

        $image->place(
            $qrImage,
            'top-left',
            $placeX,
            $placeY
        );
    }

    /**
     * Build a sharp, recolored QR PNG from the existing secure QR image.
     */
    protected function buildColoredQrPng(
        string $sourcePath,
        int $size,
        string $foregroundHex,
        string $backgroundHex,
    ): string {
        if (! function_exists('imagecreatefromstring')) {
            throw new \RuntimeException(
                'The GD extension is required for colored QR generation.'
            );
        }

        $sourceContents = @file_get_contents($sourcePath);

        if ($sourceContents === false) {
            throw new \RuntimeException(
                'Unable to read the invitee QR image.'
            );
        }

        $source = @imagecreatefromstring($sourceContents);

        if ($source === false) {
            throw new \RuntimeException(
                'The invitee QR image could not be decoded.'
            );
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        $size = max(1, min(
            $size,
            CardTemplatePlaceholder::MAX_QR_SIZE
        ));

        $target = imagecreatetruecolor($size, $size);

        if ($target === false) {
            imagedestroy($source);

            throw new \RuntimeException(
                'Unable to create the colored QR image.'
            );
        }

        [$foregroundRed, $foregroundGreen, $foregroundBlue] =
            $this->hexToRgb($foregroundHex);

        [$backgroundRed, $backgroundGreen, $backgroundBlue] =
            $this->hexToRgb($backgroundHex);

        $foreground = imagecolorallocate(
            $target,
            $foregroundRed,
            $foregroundGreen,
            $foregroundBlue
        );

        $background = imagecolorallocate(
            $target,
            $backgroundRed,
            $backgroundGreen,
            $backgroundBlue
        );

        imagefill($target, 0, 0, $background);

        /*
        |--------------------------------------------------------------------------
        | Nearest-neighbour QR mapping
        |--------------------------------------------------------------------------
        | A luminance threshold identifies dark modules. Direct nearest-neighbour
        | mapping preserves hard QR edges better than normal image resampling.
        */
        for ($targetY = 0; $targetY < $size; $targetY++) {
            $sourceY = min(
                $sourceHeight - 1,
                (int) floor(($targetY / $size) * $sourceHeight)
            );

            for ($targetX = 0; $targetX < $size; $targetX++) {
                $sourceX = min(
                    $sourceWidth - 1,
                    (int) floor(($targetX / $size) * $sourceWidth)
                );

                $rgba = imagecolorat($source, $sourceX, $sourceY);

                $alpha = ($rgba & 0x7F000000) >> 24;
                $red = ($rgba >> 16) & 0xFF;
                $green = ($rgba >> 8) & 0xFF;
                $blue = $rgba & 0xFF;

                $luminance = (0.2126 * $red)
                    + (0.7152 * $green)
                    + (0.0722 * $blue);

                $isForeground = $alpha < 120 && $luminance < 160;

                imagesetpixel(
                    $target,
                    $targetX,
                    $targetY,
                    $isForeground ? $foreground : $background
                );
            }
        }

        ob_start();
        imagepng($target, null, 9);
        $png = ob_get_clean();

        imagedestroy($source);
        imagedestroy($target);

        if (! is_string($png) || $png === '') {
            throw new \RuntimeException(
                'Unable to encode the colored QR image.'
            );
        }

        return $png;
    }

    /**
     * Convert a six-character hexadecimal color to RGB components.
     *
     * @return array{0:int,1:int,2:int}
     */
    protected function hexToRgb(string $hex): array
    {
        $hex = ltrim($this->normalizeHexColor($hex), '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    protected function hasSafeQrContrast(
        string $foregroundHex,
        string $backgroundHex,
    ): bool {
        return $this->calculateContrastRatio(
            $foregroundHex,
            $backgroundHex
        ) >= CardTemplatePlaceholder::MIN_SAFE_QR_CONTRAST;
    }

    protected function calculateContrastRatio(
        string $foregroundHex,
        string $backgroundHex,
    ): float {
        $foregroundLuminance = $this->relativeLuminance($foregroundHex);
        $backgroundLuminance = $this->relativeLuminance($backgroundHex);

        $lighter = max($foregroundLuminance, $backgroundLuminance);
        $darker = min($foregroundLuminance, $backgroundLuminance);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    protected function relativeLuminance(string $hex): float
    {
        [$red, $green, $blue] = $this->hexToRgb($hex);

        $components = array_map(
            static function (int $value): float {
                $component = $value / 255;

                return $component <= 0.03928
                    ? $component / 12.92
                    : (($component + 0.055) / 1.055) ** 2.4;
            },
            [$red, $green, $blue],
        );

        return (0.2126 * $components[0])
            + (0.7152 * $components[1])
            + (0.0722 * $components[2]);
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
        /*
        |--------------------------------------------------------------------------
        | Invitee card-status protection
        |--------------------------------------------------------------------------
        | Generation state belongs to generated_cards.status.
        |
        | invitees.card_status must remain a lifecycle/access value such as:
        | active, pending, blocked, cancelled, or used.
        |
        | Therefore this method intentionally does not write "generated" or
        | "failed" into invitees.card_status.
        */
        return;
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

        /*
         * Some existing installations do not have generated_cards.error_message.
         * Only write it when the column exists so generation never fails because
         * of schema differences.
         */
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

    /**
     * Convert the exact designer percentages into image pixels.
     *
     * @return array{0:int,1:int,2:int,3:int}
     */
    protected function resolveDesignerPlaceholderBox(
        CardTemplatePlaceholder $placeholder,
        int $imageWidth,
        int $imageHeight,
        float $defaultWidthPercent,
        float $defaultHeightPercent,
        int $minimumWidth,
        int $minimumHeight,
    ): array {
        /*
        |--------------------------------------------------------------------------
        | Exact designer coordinates
        |--------------------------------------------------------------------------
        | x_percent, y_percent, width_percent, and height_percent are the source of
        | truth. Do not force a synthetic safe margin because doing so changes the
        | position selected in the template designer.
        |
        | We only clip the box when it would extend beyond the real image boundary.
        */

        $x = $this->percentToPixels(
            $placeholder->x_percent ?? 0,
            $imageWidth
        );

        $y = $this->percentToPixels(
            $placeholder->y_percent ?? 0,
            $imageHeight
        );

        $boxWidth = max(
            $minimumWidth,
            $this->percentToPixels(
                $placeholder->width_percent ?? $defaultWidthPercent,
                $imageWidth
            )
        );

        $boxHeight = max(
            $minimumHeight,
            $this->percentToPixels(
                $placeholder->height_percent ?? $defaultHeightPercent,
                $imageHeight
            )
        );

        $x = max(0, min($x, max(0, $imageWidth - 1)));
        $y = max(0, min($y, max(0, $imageHeight - 1)));

        $boxWidth = min(
            $boxWidth,
            max(1, $imageWidth - $x)
        );

        $boxHeight = min(
            $boxHeight,
            max(1, $imageHeight - $y)
        );

        return [
            (int) $x,
            (int) $y,
            (int) $boxWidth,
            (int) $boxHeight,
        ];
    }

    /**
     * Reduce the font size until the wrapped text fits inside its box.
     *
     * @return array{0:int,1:array<int,string>}
     */
    protected function fitTextToBox(
        string $text,
        int $boxWidth,
        int $boxHeight,
        int $initialFontSize,
        ?string $fontFile = null,
    ): array {
        $fontSize = max(8, $initialFontSize);

        do {
            $lines = $this->wrapTextToBox(
                text: $text,
                boxWidth: $boxWidth,
                fontSize: $fontSize,
                fontFile: $fontFile,
            );

            $lineHeight = max(10, (int) round($fontSize * 1.22));
            $requiredHeight = count($lines) * $lineHeight;

            if ($requiredHeight <= $boxHeight) {
                return [$fontSize, $lines];
            }

            $fontSize--;
        } while ($fontSize > 8);

        $lines = $this->wrapTextToBox(
            text: $text,
            boxWidth: $boxWidth,
            fontSize: 8,
            fontFile: $fontFile,
        );

        $maxLines = max(1, (int) floor($boxHeight / 10));

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);

            $lastIndex = count($lines) - 1;
            $lines[$lastIndex] = Str::limit($lines[$lastIndex], 40, '…');
        }

        return [8, $lines];
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

    protected function resolveFontFile(
        string $fontFamily,
        string $fontWeight = 'normal'
    ): ?string {
        if (! method_exists(CardTemplatePlaceholder::class, 'fontFiles')) {
            return null;
        }

        $fontFiles = CardTemplatePlaceholder::fontFiles();
        $weight = $fontWeight === 'bold' ? 'bold' : 'regular';

        $candidates = [
            $fontFiles[$fontFamily][$weight] ?? null,
            $fontFiles[$fontFamily]['regular'] ?? null,
        ];

        $defaultFamily = $this->defaultFontFamily();

        $candidates[] = $fontFiles[$defaultFamily][$weight] ?? null;
        $candidates[] = $fontFiles[$defaultFamily]['regular'] ?? null;

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && file_exists($candidate)) {
                return $candidate;
            }
        }

        Log::warning('No usable card-generation font file was found.', [
            'requested_font_family' => $fontFamily,
            'requested_font_weight' => $fontWeight,
        ]);

        return null;
    }

    protected function resolveFontScale(
        CardTemplate $template,
        int $imageHeight,
    ): float {
        $designerHeight = (float) ($template->height ?? 0);

        if ($designerHeight <= 0) {
            return 1.0;
        }

        return max(
            0.1,
            $imageHeight / $designerHeight
        );
    }

    protected function isSingleLineTextPlaceholder(string $placeholderKey): bool
    {
        return in_array($placeholderKey, [
            'name',
            'guest_name',
            'invitee_name',
            'card_type',
            'serial_number',
            'serial',
            'guest_count',
            'allowed_guests',
            'guests',
            'allowed_people',
            'table_number',
            'table',
            'category',
        ], true);
    }

    protected function fitSingleLineFontSize(
        string $text,
        int $boxWidth,
        int $initialFontSize,
        ?string $fontFile = null,
    ): int {
        $fontSize = max(8, $initialFontSize);
        $availableWidth = max(1, $boxWidth);

        while (
            $fontSize > 8
            && $this->estimateTextWidth(
                $text,
                $fontSize,
                $fontFile
            ) > $availableWidth
        ) {
            $fontSize--;
        }

        return $fontSize;
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
        if (method_exists(CardTemplatePlaceholder::class, 'normalizeHexColor')) {
            return CardTemplatePlaceholder::normalizeHexColor(
                $color,
                CardTemplatePlaceholder::DEFAULT_QR_COLOR
            );
        }

        $color = trim($color);

        if (! str_starts_with($color, '#')) {
            $color = '#' . $color;
        }

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $color)
            ? strtoupper($color)
            : CardTemplatePlaceholder::DEFAULT_QR_COLOR;
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
