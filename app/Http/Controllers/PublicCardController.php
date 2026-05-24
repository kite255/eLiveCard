<?php

namespace App\Http\Controllers;

use App\Models\GeneratedCard;
use App\Models\Invitee;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicCardController extends Controller
{
    public function show(string $serialNumber)
    {
        $invitee = Invitee::query()
            ->with(['event', 'cardType'])
            ->where('serial_number', $serialNumber)
            ->firstOrFail();

        $generatedCard = GeneratedCard::query()
            ->where('invitee_id', $invitee->id)
            ->latest()
            ->first();

        abort_if(! $generatedCard, 404, 'Invitation card has not been generated yet.');
        abort_if(! $generatedCard->card_path, 404, 'Invitation card path is missing.');

        abort_if(
            ! Storage::disk('public')->exists($generatedCard->card_path),
            404,
            'Invitation card file was not found.'
        );

        return view('public.card-show', [
            'invitee' => $invitee,
            'generatedCard' => $generatedCard,
            'cardUrl' => Storage::disk('public')->url($generatedCard->card_path),
        ]);
    }

    public function download(string $serialNumber): StreamedResponse
    {
        $invitee = Invitee::query()
            ->where('serial_number', $serialNumber)
            ->firstOrFail();

        $generatedCard = GeneratedCard::query()
            ->where('invitee_id', $invitee->id)
            ->latest()
            ->first();

        abort_if(! $generatedCard, 404, 'Invitation card has not been generated yet.');
        abort_if(! $generatedCard->card_path, 404, 'Invitation card path is missing.');

        abort_if(
            ! Storage::disk('public')->exists($generatedCard->card_path),
            404,
            'Invitation card file was not found.'
        );

        return Storage::disk('public')->download(
            $generatedCard->card_path,
            $invitee->serial_number . '-invitation-card.png'
        );
    }
}