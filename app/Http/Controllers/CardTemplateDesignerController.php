<?php

namespace App\Http\Controllers;

use App\Models\CardTemplate;
use App\Models\CardTemplatePlaceholder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CardTemplateDesignerController extends Controller
{
    public function show(CardTemplate $cardTemplate): View
    {
        $this->authorizeAccess();

        $cardTemplate->load([
            'event',
            'placeholders' => fn ($query) => $query
                ->orderBy('sort_order')
                ->orderBy('id'),
        ]);

        return view('card-templates.designer', [
            'cardTemplate' => $cardTemplate,
            'event' => $cardTemplate->event,
            'placeholders' => $cardTemplate->placeholders,
            'availablePlaceholders' => CardTemplatePlaceholder::availablePlaceholders(),
            'fontFamilies' => CardTemplatePlaceholder::fontFamilyOptions(),
            'fontWeights' => CardTemplatePlaceholder::fontWeightOptions(),
            'textAlignments' => CardTemplatePlaceholder::textAlignOptions(),
        ]);
    }

    public function save(Request $request, CardTemplate $cardTemplate): JsonResponse
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'placeholders' => ['required', 'array'],

            'placeholders.*.id' => ['nullable', 'integer', 'exists:card_template_placeholders,id'],
            'placeholders.*.placeholder_key' => ['required', 'string', 'max:100'],
            'placeholders.*.label' => ['nullable', 'string', 'max:255'],

            'placeholders.*.x_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'placeholders.*.y_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'placeholders.*.width_percent' => ['required', 'numeric', 'min:1', 'max:100'],
            'placeholders.*.height_percent' => ['required', 'numeric', 'min:1', 'max:100'],

            'placeholders.*.font_size' => ['nullable', 'integer', 'min:6', 'max:200'],
            'placeholders.*.font_color' => ['nullable', 'string', 'max:20'],
            'placeholders.*.font_weight' => ['nullable', 'string', 'max:50'],
            'placeholders.*.font_family' => ['nullable', 'string', 'max:100'],
            'placeholders.*.text_align' => ['nullable', 'string', 'max:20'],

            'placeholders.*.qr_size' => ['nullable', 'integer', 'min:20', 'max:1000'],
            'placeholders.*.qr_color' => ['nullable', 'string', 'max:20'],
            'placeholders.*.qr_background_color' => ['nullable', 'string', 'max:20'],

            'placeholders.*.is_visible' => ['nullable', 'boolean'],
            'placeholders.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $savedIds = [];

        foreach ($validated['placeholders'] as $index => $placeholderData) {
            $placeholderKey = $placeholderData['placeholder_key'];

            if (! array_key_exists($placeholderKey, CardTemplatePlaceholder::availablePlaceholders())) {
                continue;
            }

            $isQrCode = $placeholderKey === CardTemplatePlaceholder::PLACEHOLDER_QR_CODE;

            $data = [
                'card_template_id' => $cardTemplate->id,
                'placeholder_key' => $placeholderKey,
                'label' => $placeholderData['label']
                    ?? CardTemplatePlaceholder::availablePlaceholders()[$placeholderKey],
                'x_percent' => round((float) $placeholderData['x_percent'], 4),
                'y_percent' => round((float) $placeholderData['y_percent'], 4),
                'width_percent' => round((float) $placeholderData['width_percent'], 4),
                'height_percent' => round((float) $placeholderData['height_percent'], 4),
                'font_size' => (int) ($placeholderData['font_size'] ?? 32),
                'font_color' => $placeholderData['font_color'] ?? '#111827',
                'font_weight' => $placeholderData['font_weight'] ?? 'normal',
                'font_family' => $placeholderData['font_family'] ?? CardTemplatePlaceholder::defaultFontFamily(),
                'text_align' => $placeholderData['text_align'] ?? 'center',
                'qr_size' => (int) ($placeholderData['qr_size'] ?? 160),
                'qr_color' => $placeholderData['qr_color'] ?? '#111827',
                'qr_background_color' => $placeholderData['qr_background_color'] ?? '#FFFFFF',
                'is_visible' => (bool) ($placeholderData['is_visible'] ?? true),
                'sort_order' => (int) ($placeholderData['sort_order'] ?? ($index + 1)),
            ];

            if ($isQrCode) {
                $data['font_size'] = $data['font_size'] ?: 24;
            }

            $placeholder = CardTemplatePlaceholder::updateOrCreate(
                [
                    'card_template_id' => $cardTemplate->id,
                    'placeholder_key' => $placeholderKey,
                ],
                $data
            );

            $savedIds[] = $placeholder->id;
        }

        CardTemplatePlaceholder::where('card_template_id', $cardTemplate->id)
            ->whereNotIn('id', $savedIds)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Placeholders saved successfully.',
            'count' => count($savedIds),
        ]);
    }

    public function createPlaceholder(Request $request, CardTemplate $cardTemplate): JsonResponse
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'placeholder_key' => ['required', 'string', 'max:100'],
        ]);

        $placeholderKey = $validated['placeholder_key'];

        if (! array_key_exists($placeholderKey, CardTemplatePlaceholder::availablePlaceholders())) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid placeholder selected.',
            ], 422);
        }

        $isQrCode = $placeholderKey === CardTemplatePlaceholder::PLACEHOLDER_QR_CODE;

        $placeholder = CardTemplatePlaceholder::updateOrCreate(
            [
                'card_template_id' => $cardTemplate->id,
                'placeholder_key' => $placeholderKey,
            ],
            [
                'card_template_id' => $cardTemplate->id,
                'placeholder_key' => $placeholderKey,
                'label' => CardTemplatePlaceholder::availablePlaceholders()[$placeholderKey],
                'x_percent' => 50,
                'y_percent' => $isQrCode ? 72 : 45,
                'width_percent' => $isQrCode ? 22 : 65,
                'height_percent' => $isQrCode ? 22 : 7,
                'font_size' => $isQrCode ? 24 : 32,
                'font_color' => '#111827',
                'font_weight' => $placeholderKey === CardTemplatePlaceholder::PLACEHOLDER_NAME ? 'bold' : 'normal',
                'font_family' => CardTemplatePlaceholder::defaultFontFamily(),
                'text_align' => 'center',
                'qr_size' => 180,
                'qr_color' => '#111827',
                'qr_background_color' => '#FFFFFF',
                'is_visible' => true,
                'sort_order' => CardTemplatePlaceholder::where('card_template_id', $cardTemplate->id)->count() + 1,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Placeholder added successfully.',
            'placeholder' => $placeholder,
        ]);
    }

    public function deletePlaceholder(CardTemplate $cardTemplate, CardTemplatePlaceholder $placeholder): JsonResponse
    {
        $this->authorizeAccess();

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

    protected function authorizeAccess(): void
    {
        abort_unless(Auth::check(), 403);

        $user = Auth::user();

        abort_unless(
            $user?->canManageEvents()
            || $user?->isSuperAdmin()
            || $user?->isEventOwner()
            || $user?->isEventManager()
            || $user?->isCardDesigner(),
            403
        );
    }
}