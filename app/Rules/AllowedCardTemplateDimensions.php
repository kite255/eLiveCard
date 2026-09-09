<?php

namespace App\Rules;

use App\Models\CardTemplate;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AllowedCardTemplateDimensions implements ValidationRule
{
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

        if (! CardTemplate::hasAllowedDimensions($width, $height)) {
            $fail('The card template must be either 1080 × 1350 px or 595 × 842 px.');
        }
    }

    protected function resolvePath(mixed $value): ?string
    {
        if ($value instanceof TemporaryUploadedFile || $value instanceof UploadedFile) {
            return $value->getRealPath() ?: null;
        }

        if (is_array($value)) {
            $value = collect($value)->filter()->first();

            if ($value instanceof TemporaryUploadedFile || $value instanceof UploadedFile) {
                return $value->getRealPath() ?: null;
            }
        }

        if (! is_string($value) || blank($value)) {
            return null;
        }

        $path = trim($value);

        if (Str::startsWith($path, ['http://', 'https://', 'data:image/'])) {
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
