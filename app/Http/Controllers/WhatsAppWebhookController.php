<?php

namespace App\Http\Controllers;

use App\Models\Invitee;
use App\Services\RsvpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response|JsonResponse
    {
        $mode = $request->query('hub_mode');
        $verifyToken = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $configuredToken = (string) config(
            'services.whatsapp.webhook_verify_token'
        );

        if (
            $mode === 'subscribe'
            && $configuredToken !== ''
            && hash_equals($configuredToken, (string) $verifyToken)
        ) {
            Log::info('WhatsApp webhook verified successfully.');

            return response(
                (string) $challenge,
                SymfonyResponse::HTTP_OK,
                ['Content-Type' => 'text/plain']
            );
        }

        Log::warning('WhatsApp webhook verification failed.', [
            'mode' => $mode,
            'token_present' => filled($verifyToken),
            'challenge_present' => filled($challenge),
        ]);

        return response()->json([
            'message' => 'WhatsApp webhook verification failed.',
        ], SymfonyResponse::HTTP_FORBIDDEN);
    }

    public function handle(Request $request, RsvpService $rsvpService): JsonResponse
    {
        if (! $this->hasValidSignature($request)) {
            Log::warning('WhatsApp webhook rejected because of invalid signature.');

            return response()->json([
                'message' => 'Invalid webhook signature.',
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        $payload = $request->json()->all();

        Log::info('WhatsApp webhook received.', [
            'object' => $payload['object'] ?? null,
            'entry_count' => count($payload['entry'] ?? []),
        ]);

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                foreach ($value['messages'] ?? [] as $message) {
                    $this->handleIncomingMessage($message, $rsvpService);
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    $this->handleMessageStatus($status);
                }
            }
        }

        return response()->json([
            'received' => true,
        ], SymfonyResponse::HTTP_OK);
    }

    protected function handleIncomingMessage(array $message, RsvpService $rsvpService): void
    {
        $fromPhone = $this->normalizePhone((string) ($message['from'] ?? ''));

        $buttonPayload = data_get($message, 'interactive.button_reply.id');
        $buttonTitle = data_get($message, 'interactive.button_reply.title');

        // Some WhatsApp replies may come as normal button payloads.
        if (! $buttonPayload) {
            $buttonPayload = data_get($message, 'button.payload');
            $buttonTitle = data_get($message, 'button.text');
        }

        if (! $fromPhone) {
            Log::warning('WhatsApp incoming message ignored because phone number is missing.', [
                'message' => $message,
            ]);

            return;
        }

        if (! $buttonPayload) {
            Log::info('WhatsApp incoming message received without RSVP button payload.', [
                'from' => $fromPhone,
                'type' => $message['type'] ?? null,
            ]);

            return;
        }

        $invitee = $this->findInviteeByPhone($fromPhone);

        if (! $invitee) {
            Log::warning('WhatsApp RSVP reply ignored because invitee was not found.', [
                'from' => $fromPhone,
                'button_payload' => $buttonPayload,
                'button_title' => $buttonTitle,
            ]);

            return;
        }

        $beforeStatus = $invitee->rsvp_status;

        $updatedInvitee = $rsvpService->updateFromWhatsappButton(
            invitee: $invitee,
            buttonPayload: (string) $buttonPayload,
            buttonTitle: $buttonTitle ? (string) $buttonTitle : null,
        );

        Log::info('WhatsApp RSVP reply processed.', [
            'invitee_id' => $updatedInvitee->id,
            'event_id' => $updatedInvitee->event_id,
            'phone' => $fromPhone,
            'button_payload' => $buttonPayload,
            'button_title' => $buttonTitle,
            'before_status' => $beforeStatus,
            'after_status' => $updatedInvitee->rsvp_status,
        ]);
    }

    protected function handleMessageStatus(array $status): void
    {
        Log::info('WhatsApp message status received.', [
            'message_id' => $status['id'] ?? null,
            'recipient_id' => $status['recipient_id'] ?? null,
            'status' => $status['status'] ?? null,
            'timestamp' => $status['timestamp'] ?? null,
        ]);
    }

    protected function findInviteeByPhone(string $phone): ?Invitee
    {
        $normalizedPhone = $this->normalizePhone($phone);

        if (! $normalizedPhone) {
            return null;
        }

        return Invitee::query()
            ->where('phone', $normalizedPhone)
            ->orWhere('phone', '+' . $normalizedPhone)
            ->latest('id')
            ->first();
    }

    protected function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?: '';
    }

    protected function hasValidSignature(Request $request): bool
    {
        $verificationEnabled = (bool) config(
            'services.whatsapp.verify_webhook_signature',
            false
        );

        if (! $verificationEnabled) {
            return true;
        }

        $appSecret = (string) config('services.whatsapp.app_secret');

        if ($appSecret === '') {
            Log::error(
                'WhatsApp signature verification is enabled but WHATSAPP_APP_SECRET is missing.'
            );

            return false;
        }

        $signatureHeader = (string) $request->header(
            'X-Hub-Signature-256',
            ''
        );

        if (! str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $receivedSignature = substr($signatureHeader, 7);

        $expectedSignature = hash_hmac(
            'sha256',
            $request->getContent(),
            $appSecret
        );

        return hash_equals($expectedSignature, $receivedSignature);
    }
}