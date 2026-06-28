<?php

namespace App\Http\Controllers;

use App\Models\GeneratedCard;
use App\Models\Invitee;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicCardController extends Controller
{
    public function show(string $serialNumber)
    {
        $invitee = Invitee::query()
            ->with(['event', 'cardType'])
            ->where('serial_number', $serialNumber)
            ->firstOrFail();

        $cardPath = $this->resolveCardPath($invitee);

        abort_if(! $cardPath, 404, 'Invitation card file was not found.');

        return view('public.card-show', [
            'invitee' => $invitee,
            'generatedCard' => $this->latestGeneratedCard($invitee),
            'cardUrl' => Storage::disk('public')->url($cardPath),
            'cardPath' => $cardPath,
        ]);
    }

    public function download(string $serialNumber): StreamedResponse
    {
        $invitee = Invitee::query()
            ->where('serial_number', $serialNumber)
            ->firstOrFail();

        $cardPath = $this->resolveCardPath($invitee);

        abort_if(! $cardPath, 404, 'Invitation card file was not found.');

        $extension = pathinfo($cardPath, PATHINFO_EXTENSION) ?: 'jpg';

        $filename = Str::slug($invitee->name ?: 'invitee')
            . '-'
            . $invitee->serial_number
            . '-invitation-card.'
            . $extension;

        return Storage::disk('public')->download($cardPath, $filename);
    }

    protected function latestGeneratedCard(Invitee $invitee): ?GeneratedCard
    {
        return GeneratedCard::query()
            ->where('invitee_id', $invitee->id)
            ->latest()
            ->first();
    }

    protected function resolveCardPath(Invitee $invitee): ?string
    {
        $generatedCard = $this->latestGeneratedCard($invitee);

        if ($generatedCard) {
            foreach (['card_path', 'file_path'] as $column) {
                if (
                    isset($generatedCard->{$column})
                    && filled($generatedCard->{$column})
                    && Storage::disk('public')->exists($generatedCard->{$column})
                ) {
                    return $generatedCard->{$column};
                }
            }
        }

        foreach (['generated_card_path', 'card_path', 'file_path'] as $column) {
            if (
                isset($invitee->{$column})
                && filled($invitee->{$column})
                && Storage::disk('public')->exists($invitee->{$column})
            ) {
                return $invitee->{$column};
            }
        }

        $fallbackPath = 'events/'
            . $invitee->event_id
            . '/generated-cards/'
            . Str::slug($invitee->name)
            . '-'
            . $invitee->serial_number
            . '.jpg';

        if (Storage::disk('public')->exists($fallbackPath)) {
            return $fallbackPath;
        }

        return null;
    }
}