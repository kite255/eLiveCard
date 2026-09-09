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

    /**
     * Controlled QR quiet zone around the actual QR modules.
     *
     * 10% on each side keeps the QR reliably scannable while avoiding the large
     * inherited white margin that may already exist in stored invitee QR images.
     */
    protected float $qrQuietZoneRatio = 0.10;

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
            | Generate on the original source image
            |--------------------------------------------------------------------------
            | The designer uses the same aspect ratio as this source image and stores
            | placeholder geometry as percentages. Therefore X/Y/width/height can be
            | applied directly to the original high-resolution image without resizing.
            */
            $imageWidth = $image->width();
            $imageHeight = $image->height();

            /*
            |--------------------------------------------------------------------------
            | Keep persisted source dimensions accurate
            |--------------------------------------------------------------------------
            | This also upgrades older templates the first time they are generated.
            */
            if (
                Schema::hasColumn('card_templates', 'source_width')
                && Schema::hasColumn('card_templates', 'source_height')
                && (
                    (int) $template->source_width !== (int) $imageWidth
                    || (int) $template->source_height !== (int) $imageHeight
                )
            ) {
                $updates = [
                    'source_width' => $imageWidth,
                    'source_height' => $imageHeight,
                ];

                if (Schema::hasColumn('card_templates', 'width')) {
                    $updates['width'] = CardTemplate::DESIGNER_REFERENCE_WIDTH;
                }

                if (Schema::hasColumn('card_templates', 'height')) {
                    $updates['height'] = CardTemplate::calculateDesignerHeight(
                        sourceWidth: $imageWidth,
                        sourceHeight: $imageHeight,
                        designerWidth: CardTemplate::DESIGNER_REFERENCE_WIDTH,
                    );
                }

                $template->forceFill($updates)->saveQuietly();
                $template->refresh();
            }

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
        | designer canvas, so it must be scaled from the stored template width to
        | the actual uploaded image width. The designer preview itself scales from
        | this width, so width is the correct reference axis for CSS-like font size.
        |
        | Example for the current event:
        | 1080 designer width -> 3500 real image width
        | scale = 3500 / 1080 ~= 3.241
        */
        $fontScale = $this->resolveFontScale(
            template: $template,
            imageWidth: $imageWidth,
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

        /*
        |--------------------------------------------------------------------------
        | Match browser designer line-height
        |--------------------------------------------------------------------------
        | placeholderStyle() uses lineHeight: 1.1, so generation must use the same
        | multiplier to keep vertical placement as close as possible.
        */
        $lineHeight = max(
            10,
            (int) round($fontSize * 1.10)
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
        | The saved placeholder rectangle defines the visible QR size and position.
        | No hidden padding is added here. This keeps generation consistent with
        | the designer once the designer QR preview also uses zero inner padding.
        */
        $visibleQrSize = max(
            1,
            min(
                $boxWidth,
                $boxHeight
            )
        );

        /*
        |--------------------------------------------------------------------------
        | QR raster quality
        |--------------------------------------------------------------------------
        | qr_size only controls how much detail is used to build the QR bitmap.
        | It does NOT change the visible designer geometry.
        */
        $requestedOutputSize = max(
            CardTemplatePlaceholder::MIN_QR_SIZE,
            (int) ($placeholder->qr_size ?: CardTemplatePlaceholder::DEFAULT_QR_SIZE)
        );

        $qrRasterSize = min(
            CardTemplatePlaceholder::MAX_QR_SIZE,
            max(
                $requestedOutputSize,
                min(
                    $visibleQrSize,
                    CardTemplatePlaceholder::MAX_QR_SIZE
                )
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
            size: $qrRasterSize,
            foregroundHex: $qrColor,
            backgroundHex: $qrBackgroundColor,
        );

        $qrImage = $manager->read($qrBinary);

        /*
        |--------------------------------------------------------------------------
        | Resize to the exact visible designer square
        |--------------------------------------------------------------------------
        | The generated QR must fill the saved square exactly. There is no extra
        | margin, padding, or safe-area adjustment here.
        */
        if (
            $qrImage->width() !== $visibleQrSize
            || $qrImage->height() !== $visibleQrSize
        ) {
            $qrImage->resize(
                $visibleQrSize,
                $visibleQrSize
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Center the exact QR square inside the saved rectangle
        |--------------------------------------------------------------------------
        */
        $placeX = $x + (int) round(($boxWidth - $visibleQrSize) / 2);
        $placeY = $y + (int) round(($boxHeight - $visibleQrSize) / 2);

        /*
        | Do not draw an extra background rectangle here. The generated QR bitmap
        | already contains the requested background color. Drawing an additional
        | rectangle can make the QR appear to have a larger visual frame than the
        | designer preview.
        */
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
    /**
     * Build a sharp, recolored QR PNG from the existing secure QR image.
     *
     * The source QR may already contain a large white border. We detect the real
     * dark-module bounds first, remove that inherited whitespace, then add one
     * controlled quiet zone. This keeps the generated QR visually consistent with
     * the designer while preserving a safe scanning margin.
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

        $size = max(
            1,
            min(
                $size,
                CardTemplatePlaceholder::MAX_QR_SIZE
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Detect the real QR-module bounds
        |--------------------------------------------------------------------------
        | Stored QR files may contain their own large white border. We crop only
        | the surrounding whitespace, never the actual dark modules.
        */
        [$cropLeft, $cropTop, $cropRight, $cropBottom] =
            $this->detectQrForegroundBounds(
                source: $source,
                sourceWidth: $sourceWidth,
                sourceHeight: $sourceHeight,
            );

        $cropWidth = max(1, $cropRight - $cropLeft + 1);
        $cropHeight = max(1, $cropBottom - $cropTop + 1);

        /*
        |--------------------------------------------------------------------------
        | Controlled quiet zone
        |--------------------------------------------------------------------------
        | The designer placeholder is the complete visible QR square. We reserve
        | a predictable quiet zone inside that square instead of inheriting an
        | unknown amount of whitespace from the source QR file.
        */
        $quietZone = max(
            2,
            (int) round($size * $this->qrQuietZoneRatio)
        );

        $innerSize = max(
            1,
            $size - ($quietZone * 2)
        );

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
        | Only the detected QR-module area is mapped into the inner square.
        | Nearest-neighbour sampling keeps module edges crisp.
        */
        for ($targetY = 0; $targetY < $innerSize; $targetY++) {
            $sourceY = min(
                $cropBottom,
                $cropTop + (int) floor(($targetY / $innerSize) * $cropHeight)
            );

            for ($targetX = 0; $targetX < $innerSize; $targetX++) {
                $sourceX = min(
                    $cropRight,
                    $cropLeft + (int) floor(($targetX / $innerSize) * $cropWidth)
                );

                if (! $this->isQrForegroundPixel($source, $sourceX, $sourceY)) {
                    continue;
                }

                imagesetpixel(
                    $target,
                    $quietZone + $targetX,
                    $quietZone + $targetY,
                    $foreground
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
     * Find the bounding rectangle containing all dark QR modules.
     *
     * @return array{0:int,1:int,2:int,3:int}
     */
    protected function detectQrForegroundBounds(
        $source,
        int $sourceWidth,
        int $sourceHeight,
    ): array {
        $left = $sourceWidth;
        $top = $sourceHeight;
        $right = -1;
        $bottom = -1;

        for ($y = 0; $y < $sourceHeight; $y++) {
            for ($x = 0; $x < $sourceWidth; $x++) {
                if (! $this->isQrForegroundPixel($source, $x, $y)) {
                    continue;
                }

                $left = min($left, $x);
                $top = min($top, $y);
                $right = max($right, $x);
                $bottom = max($bottom, $y);
            }
        }

        /*
        | If foreground detection fails for an unexpected source format, preserve
        | the whole source image rather than generating a broken QR.
        */
        if ($right < $left || $bottom < $top) {
            return [
                0,
                0,
                max(0, $sourceWidth - 1),
                max(0, $sourceHeight - 1),
            ];
        }

        return [
            $left,
            $top,
            $right,
            $bottom,
        ];
    }

    /**
     * Determine whether one source pixel belongs to a dark QR module.
     */
    protected function isQrForegroundPixel(
        $source,
        int $x,
        int $y,
    ): bool {
        $rgba = imagecolorat($source, $x, $y);

        $alpha = ($rgba & 0x7F000000) >> 24;
        $red = ($rgba >> 16) & 0xFF;
        $green = ($rgba >> 8) & 0xFF;
        $blue = $rgba & 0xFF;

        $luminance = (0.2126 * $red)
            + (0.7152 * $green)
            + (0.0722 * $blue);

        return $alpha < 120 && $luminance < 160;
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

            $lineHeight = max(10, (int) round($fontSize * 1.10));
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
        int $imageWidth,
    ): float {
        /*
        |--------------------------------------------------------------------------
        | Use the same designer reference width as the browser
        |--------------------------------------------------------------------------
        | CardTemplate::designer_width is the normalized browser canvas width
        | (currently 1080). Font size is stored in designer pixels, so only the
        | width scale is required. Placeholder geometry itself remains percentage
        | based and does not use this scale.
        */
        $designerWidth = max(
            1,
            (int) (
                $template->designer_width
                ?? $template->width
                ?? CardTemplate::DESIGNER_REFERENCE_WIDTH
            )
        );

        return max(
            0.1,
            $imageWidth / $designerWidth
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

        return 'Montserrat';
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

        return in_array(
            $this->normalizePlaceholderKey(
                (string) ($placeholder->placeholder_key ?? '')
            ),
            [
                'qr_code',
                'qrcode',
                'qr',
                'guest_qr_code',
                'invitee_qr_code',
            ],
            true
        );
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

        if (Str::startsWith($path, 'public/')) {
            $path = Str::after($path, 'public/');
        }

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
