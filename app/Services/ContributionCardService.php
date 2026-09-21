<?php

namespace App\Services;

use App\Models\CardTemplatePlaceholder;
use App\Models\ContributionRecipient;
use App\Rules\AllowedCardTemplateDimensions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use RuntimeException;

class ContributionCardService
{
    public function generate(ContributionRecipient $recipient): ContributionRecipient
    {
        $recipient->loadMissing('campaign');
        $campaign = $recipient->campaign;

        if (! $campaign || blank($campaign->template_image)) {
            throw new RuntimeException('The contribution campaign has no card template.');
        }

        $templatePath = Storage::disk('public')->path($campaign->template_image);
        if (! is_file($templatePath)) {
            throw new RuntimeException('The uploaded contribution card template could not be found.');
        }

        $image = (new ImageManager(new Driver()))->read($templatePath);
        $pixelCount = $image->width() * $image->height();

        if ($image->width() < AllowedCardTemplateDimensions::MIN_WIDTH
            || $image->height() < AllowedCardTemplateDimensions::MIN_HEIGHT
            || $image->width() > AllowedCardTemplateDimensions::MAX_WIDTH
            || $image->height() > AllowedCardTemplateDimensions::MAX_HEIGHT
            || $pixelCount > AllowedCardTemplateDimensions::MAX_PIXELS) {
            throw new RuntimeException('The contribution card template dimensions are not supported.');
        }

        $fontFamily = (string) $campaign->name_font_family;
        $fontWeight = $campaign->name_font_weight === 'bold' ? 'bold' : 'regular';
        $fontFile = CardTemplatePlaceholder::fontFiles()[$fontFamily][$fontWeight]
            ?? CardTemplatePlaceholder::fontFiles()[CardTemplatePlaceholder::defaultFontFamily()][$fontWeight];
        $boxX = (int) round($image->width() * ((float) $campaign->name_x_percent / 100));
        $boxWidth = max(1, (int) round($image->width() * ((float) $campaign->name_width_percent / 100)));
        $y = (int) round($image->height() * ((float) $campaign->name_y_percent / 100));
        $size = max(8, (int) $campaign->name_font_size);

        while ($size > 8 && $this->textWidth($recipient->name, $size, $fontFile) > $boxWidth) {
            $size--;
        }

        $align = in_array($campaign->name_text_align, ['left', 'center', 'right'], true)
            ? $campaign->name_text_align
            : 'center';
        $x = match ($align) {
            'left' => $boxX,
            'right' => $boxX + $boxWidth,
            default => $boxX + (int) round($boxWidth / 2),
        };

        $image->text($recipient->name, $x, $y, function ($font) use ($fontFile, $size, $campaign, $align): void {
            if (is_file($fontFile)) {
                $font->filename($fontFile);
            }
            $font->size($size);
            $font->color((string) $campaign->name_font_color);
            $font->align($align);
            $font->valign('top');
        });

        $safeName = Str::slug($recipient->name) ?: 'committee-member';
        $previousPath = filled($recipient->generated_card_path)
            ? (string) $recipient->generated_card_path
            : null;
        $version = now()->format('YmdHisv');
        $path = "events/{$campaign->event_id}/contribution-cards/{$campaign->id}/{$recipient->id}-{$safeName}-{$version}.jpg";

        Storage::disk('public')->makeDirectory(dirname($path));
        Storage::disk('public')->put($path, (string) $image->toJpeg(quality: 100, progressive: false));

        $recipient->forceFill([
            'generated_card_path' => $path,
            'generation_status' => ContributionRecipient::STATUS_GENERATED,
            'send_status' => ContributionRecipient::STATUS_PENDING,
            'generated_at' => now(),
            'last_error' => null,
        ])->saveQuietly();

        if ($previousPath && $previousPath !== $path) {
            Storage::disk('public')->delete($previousPath);
        }

        return $recipient->refresh();
    }

    private function textWidth(string $text, int $size, string $fontFile): int
    {
        if (is_file($fontFile) && function_exists('imagettfbbox')) {
            $box = imagettfbbox($size, 0, $fontFile, $text);
            if (is_array($box)) {
                return max($box[0], $box[2], $box[4], $box[6])
                    - min($box[0], $box[2], $box[4], $box[6]);
            }
        }

        return (int) round(mb_strlen($text) * $size * .55);
    }
}
