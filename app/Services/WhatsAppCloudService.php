<?php

namespace App\Services;

use App\Models\Invitee;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class WhatsAppCloudService
{
    public function sendInvitationTemplate(
        Invitee $invitee,
        array $bodyParameters = [],
        array $urlButtonParameters = [],
        array $quickReplyButtonPayloads = [],
        ?array $header = null,
    ): array {
        $invitee->loadMissing(['event', 'cardType']);

        return $this->sendTemplate(
            phone: (string) $invitee->phone,
            templateName: (string) config(
                'services.whatsapp.templates.invitation',
                'event_invitation_sw'
            ),
            languageCode: (string) config(
                'services.whatsapp.template_language',
                'sw'
            ),
            bodyParameters: $bodyParameters,
            urlButtonParameters: $urlButtonParameters,
            quickReplyButtonPayloads: $quickReplyButtonPayloads,
            header: $header,
        );
    }

    public function sendTemplate(
        string $phone,
        string $templateName,
        string $languageCode,
        array $bodyParameters = [],
        array $urlButtonParameters = [],
        array $quickReplyButtonPayloads = [],
        ?array $header = null,
    ): array {
        $this->ensureEnabled();

        $normalizedPhone = $this->normalizePhone($phone);

        if ($normalizedPhone === null) {
            throw new RuntimeException('Invalid WhatsApp phone number.');
        }

        if (blank($templateName)) {
            throw new RuntimeException('WhatsApp template name is missing.');
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $normalizedPhone,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode,
                ],
            ],
        ];

        $components = $this->buildTemplateComponents(
            bodyParameters: $bodyParameters,
            urlButtonParameters: $urlButtonParameters,
            quickReplyButtonPayloads: $quickReplyButtonPayloads,
            header: $header,
        );

        if ($components !== []) {
            $payload['template']['components'] = $components;
        }

        return $this->sendPayload($payload);
    }

    public function sendText(
        string $phone,
        string $message,
        bool $previewUrl = false,
    ): array {
        $this->ensureEnabled();

        $normalizedPhone = $this->normalizePhone($phone);

        if ($normalizedPhone === null) {
            throw new RuntimeException('Invalid WhatsApp phone number.');
        }

        if (blank($message)) {
            throw new RuntimeException('WhatsApp message cannot be empty.');
        }

        return $this->sendPayload([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $normalizedPhone,
            'type' => 'text',
            'text' => [
                'preview_url' => $previewUrl,
                'body' => $message,
            ],
        ]);
    }

    protected function buildTemplateComponents(
        array $bodyParameters,
        array $urlButtonParameters,
        array $quickReplyButtonPayloads,
        ?array $header,
    ): array {
        $components = [];

        if ($header !== null) {
            $components[] = [
                'type' => 'header',
                'parameters' => [
                    $this->normalizeHeaderParameter($header),
                ],
            ];
        }

        if ($bodyParameters !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_values(array_map(
                    fn (mixed $value): array => [
                        'type' => 'text',
                        'text' => (string) $value,
                    ],
                    $bodyParameters
                )),
            ];
        }

        foreach ($urlButtonParameters as $index => $value) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => (string) $index,
                'parameters' => [
                    [
                        'type' => 'text',
                        'text' => (string) $value,
                    ],
                ],
            ];
        }

        foreach ($quickReplyButtonPayloads as $index => $payload) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'quick_reply',
                'index' => (string) $index,
                'parameters' => [
                    [
                        'type' => 'payload',
                        'payload' => (string) $payload,
                    ],
                ],
            ];
        }

        return $components;
    }

    protected function normalizeHeaderParameter(array $header): array
    {
        $type = strtolower((string) ($header['type'] ?? ''));

        return match ($type) {
            'image', 'video' => [
                'type' => $type,
                $type => [
                    'link' => $this->requiredHeaderValue($header, 'link'),
                ],
            ],

            'document' => [
                'type' => 'document',
                'document' => array_filter([
                    'link' => $this->requiredHeaderValue($header, 'link'),
                    'filename' => $header['filename'] ?? null,
                ]),
            ],

            'text' => [
                'type' => 'text',
                'text' => $this->requiredHeaderValue($header, 'text'),
            ],

            default => throw new RuntimeException(
                'Unsupported WhatsApp template header type.'
            ),
        };
    }

    protected function requiredHeaderValue(array $header, string $key): string
    {
        $value = trim((string) ($header[$key] ?? ''));

        if ($value === '') {
            throw new RuntimeException(
                "WhatsApp template header {$key} is required."
            );
        }

        return $value;
    }

    protected function sendPayload(array $payload): array
    {
        $driver = (string) config('services.whatsapp.driver', 'log');

        if ($driver === 'log') {
            Log::info('WhatsApp Cloud API log-driver payload.', [
                'payload' => $payload,
            ]);

            return [
                'successful' => true,
                'driver' => 'log',
                'status' => 200,
                'message_id' => 'log-' . now()->format('YmdHis'),
                'response' => [
                    'logged' => true,
                ],
                'payload' => $payload,
            ];
        }

        if ($driver !== 'cloud_api') {
            throw new RuntimeException(
                "Unsupported WhatsApp driver [{$driver}]."
            );
        }

        $phoneNumberId = trim((string) config(
            'services.whatsapp.phone_number_id'
        ));

        if ($phoneNumberId === '') {
            throw new RuntimeException(
                'WHATSAPP_PHONE_NUMBER_ID is missing.'
            );
        }

        $url = sprintf(
            '%s/%s/%s/messages',
            rtrim((string) config(
                'services.whatsapp.base_url',
                'https://graph.facebook.com'
            ), '/'),
            trim((string) config(
                'services.whatsapp.api_version',
                'v23.0'
            ), '/'),
            $phoneNumberId
        );

        try {
            $response = $this->httpClient()->post($url, $payload);

            $responseData = $response->json() ?? [];

            if ($response->failed()) {
                Log::error('WhatsApp Cloud API request failed.', [
                    'status' => $response->status(),
                    'response' => $responseData,
                ]);

                throw new RuntimeException(
                    $this->extractErrorMessage(
                        response: $responseData,
                        fallback: "WhatsApp API returned HTTP {$response->status()}."
                    )
                );
            }

            $messageId = data_get(
                $responseData,
                'messages.0.id'
            );

            Log::info('WhatsApp Cloud API message accepted.', [
                'message_id' => $messageId,
                'recipient' => $payload['to'] ?? null,
                'template' => data_get($payload, 'template.name'),
            ]);

            return [
                'successful' => true,
                'driver' => 'cloud_api',
                'status' => $response->status(),
                'message_id' => $messageId,
                'response' => $responseData,
                'payload' => $payload,
            ];
        } catch (ConnectionException $exception) {
            Log::error('WhatsApp Cloud API connection failed.', [
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException(
                'Could not connect to WhatsApp Cloud API.',
                previous: $exception
            );
        } catch (RequestException $exception) {
            Log::error('WhatsApp Cloud API HTTP request failed.', [
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException(
                'WhatsApp Cloud API request failed.',
                previous: $exception
            );
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            Log::error('Unexpected WhatsApp Cloud API error.', [
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException(
                'Unexpected WhatsApp Cloud API error.',
                previous: $exception
            );
        }
    }

    protected function httpClient(): PendingRequest
    {
        $token = trim((string) config(
            'services.whatsapp.access_token'
        ));

        if ($token === '') {
            throw new RuntimeException(
                'WHATSAPP_ACCESS_TOKEN is missing.'
            );
        }

        return Http::acceptJson()
            ->asJson()
            ->withToken($token)
            ->connectTimeout((int) config(
                'services.whatsapp.connect_timeout',
                10
            ))
            ->timeout((int) config(
                'services.whatsapp.timeout',
                30
            ))
            ->retry(
                times: 2,
                sleepMilliseconds: 500,
                throw: false
            );
    }

    protected function ensureEnabled(): void
    {
        if (! (bool) config('services.whatsapp.enabled', false)) {
            throw new RuntimeException(
                'WhatsApp sending is disabled.'
            );
        }
    }

    public function normalizePhone(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $phone = preg_replace('/\D+/', '', $phone);

        if (blank($phone)) {
            return null;
        }

        if (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        }

        if (str_starts_with($phone, '0') && strlen($phone) === 10) {
            $phone = '255' . substr($phone, 1);
        }

        if (
            strlen($phone) === 9
            && preg_match('/^[67]/', $phone)
        ) {
            $phone = '255' . $phone;
        }

        if (! preg_match('/^[1-9]\d{7,14}$/', $phone)) {
            return null;
        }

        return $phone;
    }

    protected function extractErrorMessage(
        array $response,
        string $fallback,
    ): string {
        $message = data_get($response, 'error.message');

        $errorCode = data_get($response, 'error.code');
        $errorSubcode = data_get($response, 'error.error_subcode');

        if (! $message) {
            return $fallback;
        }

        $details = array_filter([
            $errorCode ? "code {$errorCode}" : null,
            $errorSubcode ? "subcode {$errorSubcode}" : null,
        ]);

        if ($details === []) {
            return (string) $message;
        }

        return sprintf(
            '%s (%s)',
            $message,
            implode(', ', $details)
        );
    }
}
