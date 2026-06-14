<?php

namespace App\Http\Controllers;

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

    public function handle(Request $request): JsonResponse
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
            'payload' => $payload,
        ]);

        return response()->json([
            'received' => true,
        ], SymfonyResponse::HTTP_OK);
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
