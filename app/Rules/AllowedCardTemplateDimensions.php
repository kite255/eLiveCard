<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AllowedCardTemplateDimensions implements ValidationRule
{
    /**
     * Minimum supported template dimensions.
     */
    public const MIN_WIDTH = 595;
    public const MIN_HEIGHT = 595;

    /**
     * Maximum supported width/height.
     *
     * This allows high-resolution card artwork such as 3500 × 4749 while still
     * protecting the application from excessively large uploads.
     */
    public const MAX_WIDTH = 6000;
    public const MAX_HEIGHT = 6000;

    /**
     * Maximum total pixel count.
     *
     * 25 MP keeps large card designs practical for GD/Intervention Image while
     * protecting memory usage during generation.
     */
    public const MAX_PIXELS = 25_000_000;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $path = $this->resolvePath($value);

        if (! $path || ! is_file($path)) {
            $fail('The uploaded card template could not be read.');

            return;
        }

        $size = @getimagesize($path);

        if (! is_array($size) || ! isset($size[0], $size[1])) {
            $fail('The uploaded file is not a valid image.');

            return;
        }

        $width = (int) $size[0];
        $height = (int) $size[1];
        $pixels = $width * $height;

        if (
            $width < self::MIN_WIDTH
            || $height < self::MIN_HEIGHT
            || $width > self::MAX_WIDTH
            || $height > self::MAX_HEIGHT
            || $pixels > self::MAX_PIXELS
        ) {
            $megapixels = round($pixels / 1_000_000, 2);

            $fail(
                'The card template must be at least '
                . self::MIN_WIDTH
                . ' × '
                . self::MIN_HEIGHT
                . ' px, no larger than '
                . self::MAX_WIDTH
                . ' × '
                . self::MAX_HEIGHT
                . ' px, and must not exceed '
                . round(self::MAX_PIXELS / 1_000_000)
                . ' megapixels. '
                . "Uploaded image is {$width} × {$height}px ({$megapixels} MP)."
            );

            return;
        }
    }

    protected function resolvePath(mixed $value): ?string
    {
        if (
            $value instanceof TemporaryUploadedFile
            || $value instanceof UploadedFile
        ) {
            return $value->getRealPath() ?: null;
        }

        if (is_array($value)) {
            $value = collect($value)
                ->filter()
                ->first();

            if (
                $value instanceof TemporaryUploadedFile
                || $value instanceof UploadedFile
            ) {
                return $value->getRealPath() ?: null;
            }
        }

        if (! is_string($value) || blank($value)) {
            return null;
        }

        $path = trim($value);

        if (
            Str::startsWith(
                $path,
                ['http://', 'https://', 'data:image/']
            )
        ) {
            return null;
        }

        $path = ltrim($path, '/');

        foreach (['public/', 'storage/'] as $prefix) {
            if (Str::startsWith($path, $prefix)) {
                $path = Str::after($path, $prefix);
            }
        }

        return Storage::disk('public')->exists($path)
            ? Storage::disk('public')->path($path)
            : null;
    }
}
