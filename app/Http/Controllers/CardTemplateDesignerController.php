<?php

namespace App\Http\Controllers;

use App\Models\CardTemplate;
use App\Models\CardTemplatePlaceholder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CardTemplateDesignerController extends Controller
{
    /**
     * Keep newly created placeholders away from the visible card edges.
     */
    protected const SAFE_MARGIN_PERCENT = 3.0;

    public function show(CardTemplate $cardTemplate): View
    {
        $this->authorizeAccess($cardTemplate);

        $cardTemplate->load([
            'event',
            'placeholders' => fn ($query) => $query
                ->orderBy('sort_order')
                ->orderBy('id'),
        ]);

        return view('filament.pages.card-template-designer', [
            'cardTemplate' => $cardTemplate,
            'event' => $cardTemplate->event,
            'placeholders' => $cardTemplate->placeholders,
            'availablePlaceholders' => CardTemplatePlaceholder::availablePlaceholders(),
            'fontFamilies' => CardTemplatePlaceholder::fontFamilyOptions(),
            'fontWeights' => CardTemplatePlaceholder::fontWeightOptions(),
            'textAlignments' => CardTemplatePlaceholder::textAlignOptions(),

            /*
            |--------------------------------------------------------------------------
            | QR designer configuration
            |--------------------------------------------------------------------------
            | These values keep the Blade designer synchronized with the model
            | and final card-generation service.
            */
            'qrDefaults' => [
                'size' => CardTemplatePlaceholder::DEFAULT_QR_SIZE,
                'minimumSize' => CardTemplatePlaceholder::MIN_QR_SIZE,
                'maximumSize' => CardTemplatePlaceholder::MAX_QR_SIZE,
                'widthPercent' => CardTemplatePlaceholder::DEFAULT_QR_WIDTH_PERCENT,
                'heightPercent' => $this->defaultQrHeightPercent($cardTemplate),
                'xPercent' => $this->defaultQrXPercent(),
                'yPercent' => $this->defaultQrYPercent($cardTemplate),
                'color' => CardTemplatePlaceholder::DEFAULT_QR_COLOR,
                'backgroundColor' => CardTemplatePlaceholder::DEFAULT_QR_BACKGROUND_COLOR,
                'minimumContrast' => CardTemplatePlaceholder::MIN_SAFE_QR_CONTRAST,
            ],
        ]);
    }

    public function save(Request $request, CardTemplate $cardTemplate): JsonResponse
    {
        $this->authorizeAccess($cardTemplate);

        $availablePlaceholderKeys = array_keys(
            CardTemplatePlaceholder::availablePlaceholders()
        );

        $validated = $request->validate([
            'placeholders' => ['required', 'array'],

            'placeholders.*.id' => [
                'nullable',
                'integer',
                Rule::exists('card_template_placeholders', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'card_template_id',
                            $cardTemplate->id
                        )
                    ),
            ],

            'placeholders.*.placeholder_key' => [
                'required',
                'string',
                'max:100',
                Rule::in($availablePlaceholderKeys),
            ],

            'placeholders.*.label' => [
                'nullable',
                'string',
                'max:255',
            ],

            'placeholders.*.x_percent' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'placeholders.*.y_percent' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'placeholders.*.width_percent' => [
                'required',
                'numeric',
                'min:1',
                'max:100',
            ],

            'placeholders.*.height_percent' => [
                'required',
                'numeric',
                'min:1',
                'max:100',
            ],

            'placeholders.*.font_size' => [
                'nullable',
                'integer',
                'min:8',
                'max:300',
            ],

            'placeholders.*.font_color' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^#?[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/',
            ],

            'placeholders.*.font_weight' => [
                'nullable',
                'string',
                Rule::in(array_keys(
                    CardTemplatePlaceholder::fontWeightOptions()
                )),
            ],

            'placeholders.*.font_family' => [
                'nullable',
                'string',
                Rule::in(array_keys(
                    CardTemplatePlaceholder::fontFamilyOptions()
                )),
            ],

            'placeholders.*.text_align' => [
                'nullable',
                'string',
                Rule::in(array_keys(
                    CardTemplatePlaceholder::textAlignOptions()
                )),
            ],

            'placeholders.*.qr_size' => [
                'nullable',
                'integer',
                'min:'.CardTemplatePlaceholder::MIN_QR_SIZE,
                'max:'.CardTemplatePlaceholder::MAX_QR_SIZE,
            ],

            'placeholders.*.qr_color' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^#?[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/',
            ],

            'placeholders.*.qr_background_color' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^#?[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/',
            ],

            'placeholders.*.is_visible' => [
                'nullable',
                'boolean',
            ],

            'placeholders.*.sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ]);

        $savedIds = DB::transaction(function () use (
            $validated,
            $cardTemplate
        ): array {
            $savedIds = [];

            foreach ($validated['placeholders'] as $index => $placeholderData) {
                $placeholderKey = $placeholderData['placeholder_key'];
                $isQrCode = $placeholderKey ===
                    CardTemplatePlaceholder::PLACEHOLDER_QR_CODE;

                $dimensions = $this->normalizePlaceholderDimensions(
                    placeholderData: $placeholderData,
                    isQrCode: $isQrCode,
                    cardTemplate: $cardTemplate,
                );

                $qrColor = CardTemplatePlaceholder::normalizeHexColor(
                    $placeholderData['qr_color']
                        ?? CardTemplatePlaceholder::DEFAULT_QR_COLOR,
                    CardTemplatePlaceholder::DEFAULT_QR_COLOR,
                );

                $qrBackgroundColor = CardTemplatePlaceholder::normalizeHexColor(
                    $placeholderData['qr_background_color']
                        ?? CardTemplatePlaceholder::DEFAULT_QR_BACKGROUND_COLOR,
                    CardTemplatePlaceholder::DEFAULT_QR_BACKGROUND_COLOR,
                );

                if (
                    $isQrCode
                    && ! $this->hasSafeQrContrast(
                        $qrColor,
                        $qrBackgroundColor
                    )
                ) {
                    $qrColor = CardTemplatePlaceholder::DEFAULT_QR_COLOR;
                    $qrBackgroundColor =
                        CardTemplatePlaceholder::DEFAULT_QR_BACKGROUND_COLOR;
                }

                $data = [
                    'card_template_id' => $cardTemplate->id,
                    'placeholder_key' => $placeholderKey,

                    'label' => filled($placeholderData['label'] ?? null)
                        ? trim((string) $placeholderData['label'])
                        : CardTemplatePlaceholder::availablePlaceholders()[$placeholderKey],

                    'x_percent' => $dimensions['x_percent'],
                    'y_percent' => $dimensions['y_percent'],
                    'width_percent' => $dimensions['width_percent'],
                    'height_percent' => $dimensions['height_percent'],

                    'font_size' => (int) (
                        $placeholderData['font_size']
                        ?? CardTemplatePlaceholder::DEFAULT_FONT_SIZE
                    ),

                    'font_color' => CardTemplatePlaceholder::normalizeHexColor(
                        $placeholderData['font_color']
                            ?? CardTemplatePlaceholder::DEFAULT_FONT_COLOR,
                        CardTemplatePlaceholder::DEFAULT_FONT_COLOR,
                    ),

                    'font_weight' => $placeholderData['font_weight']
                        ?? 'normal',

                    'font_family' => $placeholderData['font_family']
                        ?? CardTemplatePlaceholder::defaultFontFamily(),

                    'text_align' => $placeholderData['text_align']
                        ?? 'center',

                    'qr_size' => max(
                        CardTemplatePlaceholder::MIN_QR_SIZE,
                        min(
                            CardTemplatePlaceholder::MAX_QR_SIZE,
                            (int) (
                                $placeholderData['qr_size']
                                ?? CardTemplatePlaceholder::DEFAULT_QR_SIZE
                            )
                        )
                    ),

                    'qr_color' => $qrColor,
                    'qr_background_color' => $qrBackgroundColor,

                    'is_visible' => (bool) (
                        $placeholderData['is_visible'] ?? true
                    ),

                    'sort_order' => (int) (
                        $placeholderData['sort_order'] ?? ($index + 1)
                    ),
                ];

                $placeholder = CardTemplatePlaceholder::updateOrCreate(
                    [
                        'card_template_id' => $cardTemplate->id,
                        'placeholder_key' => $placeholderKey,
                    ],
                    $data
                );

                $savedIds[] = $placeholder->id;
            }

            $deleteQuery = CardTemplatePlaceholder::query()
                ->where('card_template_id', $cardTemplate->id);

            if (empty($savedIds)) {
                $deleteQuery->delete();
            } else {
                $deleteQuery
                    ->whereNotIn('id', $savedIds)
                    ->delete();
            }

            return $savedIds;
        });

        $savedPlaceholders = CardTemplatePlaceholder::query()
            ->where('card_template_id', $cardTemplate->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Placeholders saved successfully.',
            'count' => $savedPlaceholders->count(),
            'placeholders' => $savedPlaceholders,
        ]);
    }

    public function createPlaceholder(
        Request $request,
        CardTemplate $cardTemplate
    ): JsonResponse {
        $this->authorizeAccess($cardTemplate);

        $validated = $request->validate([
            'placeholder_key' => [
                'required',
                'string',
                'max:100',
                Rule::in(array_keys(
                    CardTemplatePlaceholder::availablePlaceholders()
                )),
            ],
        ]);

        $placeholderKey = $validated['placeholder_key'];
        $isQrCode = $placeholderKey ===
            CardTemplatePlaceholder::PLACEHOLDER_QR_CODE;

        $placeholder = CardTemplatePlaceholder::updateOrCreate(
            [
                'card_template_id' => $cardTemplate->id,
                'placeholder_key' => $placeholderKey,
            ],
            [
                'card_template_id' => $cardTemplate->id,
                'placeholder_key' => $placeholderKey,

                'label' =>
                    CardTemplatePlaceholder::availablePlaceholders()[$placeholderKey],

                /*
                |--------------------------------------------------------------------------
                | New placeholder defaults
                |--------------------------------------------------------------------------
                | The QR starts centered at 16% width, with height calculated from the template aspect ratio. It can then be
                | moved and resized inside the designer.
                */
                'x_percent' => $isQrCode
                    ? $this->defaultQrXPercent()
                    : 17.5,

                'y_percent' => $isQrCode
                    ? $this->defaultQrYPercent($cardTemplate)
                    : 45,

                'width_percent' => $isQrCode
                    ? CardTemplatePlaceholder::DEFAULT_QR_WIDTH_PERCENT
                    : 65,

                'height_percent' => $isQrCode
                    ? $this->defaultQrHeightPercent($cardTemplate)
                    : 7,

                'font_size' => CardTemplatePlaceholder::DEFAULT_FONT_SIZE,

                'font_color' =>
                    CardTemplatePlaceholder::DEFAULT_FONT_COLOR,

                'font_weight' => $placeholderKey ===
                    CardTemplatePlaceholder::PLACEHOLDER_NAME
                        ? 'bold'
                        : 'normal',

                'font_family' =>
                    CardTemplatePlaceholder::defaultFontFamily(),

                'text_align' => 'center',

                'qr_size' =>
                    CardTemplatePlaceholder::DEFAULT_QR_SIZE,

                'qr_color' =>
                    CardTemplatePlaceholder::DEFAULT_QR_COLOR,

                'qr_background_color' =>
                    CardTemplatePlaceholder::DEFAULT_QR_BACKGROUND_COLOR,

                'is_visible' => true,

                'sort_order' => CardTemplatePlaceholder::query()
                    ->where('card_template_id', $cardTemplate->id)
                    ->count() + 1,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Placeholder added successfully.',
            'placeholder' => $placeholder->fresh(),
        ]);
    }

    public function deletePlaceholder(
        CardTemplate $cardTemplate,
        CardTemplatePlaceholder $placeholder
    ): JsonResponse {
        $this->authorizeAccess($cardTemplate);

        if ((int) $placeholder->card_template_id !== (int) $cardTemplate->id) {
            return response()->json([
                'success' => false,
                'message' => 'This placeholder does not belong to this template.',
            ], 403);
        }

        $placeholder->delete();

        return response()->json([
            'success' => true,
            'message' => 'Placeholder deleted successfully.',
        ]);
    }

    /**
     * Keep a placeholder box fully inside the card canvas.
     *
     * @return array{
     *     x_percent:float,
     *     y_percent:float,
     *     width_percent:float,
     *     height_percent:float
     * }
     */
    protected function normalizePlaceholderDimensions(
        array $placeholderData,
        bool $isQrCode,
        CardTemplate $cardTemplate
    ): array {
        $width = round(
            (float) (
                $placeholderData['width_percent']
                ?? (
                    $isQrCode
                        ? CardTemplatePlaceholder::DEFAULT_QR_WIDTH_PERCENT
                        : 60
                )
            ),
            4
        );

        $height = round(
            (float) (
                $placeholderData['height_percent']
                ?? (
                    $isQrCode
                        ? $this->defaultQrHeightPercent($cardTemplate)
                        : 8
                )
            ),
            4
        );

        $width = max(1, min(100, $width));
        $height = max(1, min(100, $height));

        if ($isQrCode) {
            $height = $this->qrHeightPercentForWidth(
                widthPercent: $width,
                cardTemplate: $cardTemplate,
            );
        }

        $x = round(
            (float) (
                $placeholderData['x_percent']
                ?? ($isQrCode ? $this->defaultQrXPercent() : 0)
            ),
            4
        );

        $y = round(
            (float) (
                $placeholderData['y_percent']
                ?? ($isQrCode ? $this->defaultQrYPercent($cardTemplate) : 0)
            ),
            4
        );

        $x = max(0, min(100 - $width, $x));
        $y = max(0, min(100 - $height, $y));

        return [
            'x_percent' => $x,
            'y_percent' => $y,
            'width_percent' => $width,
            'height_percent' => $height,
        ];
    }

    protected function defaultQrHeightPercent(CardTemplate $cardTemplate): float
    {
        return $this->qrHeightPercentForWidth(
            widthPercent: CardTemplatePlaceholder::DEFAULT_QR_WIDTH_PERCENT,
            cardTemplate: $cardTemplate,
        );
    }

    protected function qrHeightPercentForWidth(
        float $widthPercent,
        CardTemplate $cardTemplate
    ): float {
        $templateWidth = max(1, (float) $cardTemplate->width);
        $templateHeight = max(1, (float) $cardTemplate->height);

        return round(
            ($widthPercent * $templateWidth) / $templateHeight,
            4
        );
    }

    protected function defaultQrXPercent(): float
    {
        return round(
            (100 - CardTemplatePlaceholder::DEFAULT_QR_WIDTH_PERCENT) / 2,
            4
        );
    }

    protected function defaultQrYPercent(?CardTemplate $cardTemplate = null): float
    {
        $qrHeightPercent = $cardTemplate
            ? $this->defaultQrHeightPercent($cardTemplate)
            : CardTemplatePlaceholder::DEFAULT_QR_HEIGHT_PERCENT;

        $maximumY = 100
            - $qrHeightPercent
            - self::SAFE_MARGIN_PERCENT;

        return min(58.0, max(self::SAFE_MARGIN_PERCENT, $maximumY));
    }

    protected function hasSafeQrContrast(
        string $foreground,
        string $background
    ): bool {
        return $this->contrastRatio(
            $foreground,
            $background
        ) >= CardTemplatePlaceholder::MIN_SAFE_QR_CONTRAST;
    }

    protected function contrastRatio(
        string $foreground,
        string $background
    ): float {
        $foregroundLuminance = $this->relativeLuminance($foreground);
        $backgroundLuminance = $this->relativeLuminance($background);

        $lighter = max($foregroundLuminance, $backgroundLuminance);
        $darker = min($foregroundLuminance, $backgroundLuminance);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    protected function relativeLuminance(string $hex): float
    {
        $hex = ltrim(
            CardTemplatePlaceholder::normalizeHexColor($hex),
            '#'
        );

        $components = [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];

        $components = array_map(
            static fn (float $component): float =>
                $component <= 0.03928
                    ? $component / 12.92
                    : (($component + 0.055) / 1.055) ** 2.4,
            $components,
        );

        return (0.2126 * $components[0])
            + (0.7152 * $components[1])
            + (0.0722 * $components[2]);
    }

    protected function authorizeAccess(CardTemplate $cardTemplate): void
    {
        abort_unless(Auth::check(), 403);

        $user = Auth::user();

        abort_unless(
            $user?->canManageCardDesigns() ?? false,
            403
        );

        if ($user->isSuperAdmin()) {
            return;
        }

        if ($user->isEventAdmin()) {
            $cardTemplate->loadMissing('event');

            abort_unless(
                (int) ($cardTemplate->event?->user_id ?? 0)
                    === (int) $user->id,
                403
            );

            return;
        }

        abort(403);
    }
}
