<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends WhatsApp template messages through the Meta WhatsApp Cloud API (Graph
 * API). Credentials are global (a single WABA), read from config/services.php.
 */
class WhatsAppService
{
    /**
     * Send a pre-approved template message.
     *
     * The template (name, language, parameter count) MUST already be approved in
     * Meta Business Manager - everything here has to match it exactly.
     *
     * @param  string  $to  Recipient in WhatsApp digits-only format (e.g. 628123456789)
     * @param  string  $template  Template name as approved in Meta Business Manager
     * @param  list<string|int|float|null>  $params  Positional body params mapped to {{1}}, {{2}}, ...
     * @param  string  $lang  Template language code (must match the approved template)
     * @return array<string, mixed> The Graph API JSON response
     */
    public function sendTemplate(string $to, string $template, array $params = [], string $lang = 'id'): array
    {
        $version = config('services.whatsapp.api_version', 'v21.0');
        $phoneNumberId = config('services.whatsapp.phone_number_id');

        $body = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $lang],
            ],
        ];

        if ($params !== []) {
            $body['template']['components'] = [[
                'type' => 'body',
                'parameters' => array_map(
                    fn ($value): array => ['type' => 'text', 'text' => (string) $value],
                    array_values($params),
                ),
            ]];
        }

        $response = Http::withToken((string) config('services.whatsapp.token'))
            ->asJson()
            ->timeout(15)
            ->connectTimeout(5)
            ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", $body);

        if ($response->failed()) {
            Log::error('WhatsApp sendTemplate failed', [
                'to' => $to,
                'template' => $template,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            $response->throw();
        }

        return (array) $response->json();
    }
}
