<?php

namespace App\Services;

use App\Models\MessageTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MetaWhatsAppTemplateCatalog
{
    /**
     * Only import Meta invitation templates compatible with the existing sender:
     * image header, five text body parameters and dynamic URL at button index 2.
     * Unsupported templates must not be silently sent with the wrong payload.
     */
    public function sync(): array
    {
        $accountId = trim((string) config('services.whatsapp.business_account_id'));
        $token = trim((string) config('services.whatsapp.access_token'));

        if ($accountId === '' || $token === '') {
            throw new RuntimeException('Configure WhatsApp Business Account ID and access token before syncing Meta templates.');
        }

        $base = rtrim((string) config('services.whatsapp.base_url', 'https://graph.facebook.com'), '/');
        $version = trim((string) config('services.whatsapp.api_version', 'v25.0'), '/');
        $url = $base.'/'.$version.'/'.$accountId.'/message_templates';
        $templates = [];
        $next = $url;
        $pages = 0;

        while ($next && ++$pages <= 10) {
            $response = Http::withToken($token)->acceptJson()->timeout(15)
                ->get($next, $pages === 1 ? [
                    'fields' => 'name,language,status,components',
                    'limit' => 100,
                ] : []);

            if (! $response->successful()) {
                throw new RuntimeException('Meta template retrieval failed (HTTP '.$response->status().'). Check WhatsApp Business permissions.');
            }

            $result = $response->json();
            foreach (($result['data'] ?? []) as $item) {
                if (($item['status'] ?? null) !== 'APPROVED' || ! $this->compatibleInvitation($item)) {
                    continue;
                }

                $name = (string) ($item['name'] ?? '');
                $language = (string) ($item['language'] ?? '');
                if ($name === '' || $language === '') {
                    continue;
                }

                $template = MessageTemplate::query()->firstOrNew([
                    'event_id' => null,
                    'channel' => MessageTemplate::CHANNEL_WHATSAPP,
                    'type' => MessageTemplate::TYPE_INVITATION,
                    'whatsapp_template_name' => $name,
                    'whatsapp_language_code' => $language,
                ]);
                $template->fill([
                    'name' => $name.' ('.strtoupper($language).')',
                    'content' => (string) collect($item['components'] ?? [])
                        ->firstWhere('type', 'BODY')['text'] ?? '',
                    'status' => MessageTemplate::STATUS_ACTIVE,
                ]);
                $template->save();
                $templates[$template->id] = $template->name.' — Meta approved';
            }

            $next = data_get($result, 'paging.next');
            if ($next && ! str_starts_with($next, $base.'/')) {
                throw new RuntimeException('Meta returned an unexpected pagination endpoint.');
            }
        }

        return $templates;
    }

    private function compatibleInvitation(array $template): bool
    {
        $components = collect($template['components'] ?? []);
        $header = $components->firstWhere('type', 'HEADER');
        $body = $components->firstWhere('type', 'BODY');
        $buttons = $components->firstWhere('type', 'BUTTONS')['buttons'] ?? [];

        return ($header['format'] ?? '') === 'IMAGE'
            && preg_match_all('/\{\{\d+\}\}/', (string) ($body['text'] ?? '')) === 5
            && isset($buttons[2])
            && ($buttons[2]['type'] ?? '') === 'URL'
            && str_contains((string) ($buttons[2]['url'] ?? ''), '{{1}}');
    }
}
