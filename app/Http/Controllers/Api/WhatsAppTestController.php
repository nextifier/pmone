<?php

namespace App\Http\Controllers\Api;

use App\Helpers\PhoneCountryHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendTestWhatsAppRequest;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;

/**
 * Admin tool to send a one-off test WhatsApp template message, used to verify the
 * Meta Cloud API setup (token, phone number ID, approved templates) without
 * waiting for a real reservation. Bypasses the WHATSAPP_ENABLED reservation gate
 * by calling the service directly.
 */
class WhatsAppTestController extends Controller
{
    public function send(SendTestWhatsAppRequest $request, WhatsAppService $whatsapp): JsonResponse
    {
        if (! config('services.whatsapp.token') || ! config('services.whatsapp.phone_number_id')) {
            return response()->json([
                'message' => 'WhatsApp is not configured. Set WHATSAPP_ACCESS_TOKEN and WHATSAPP_PHONE_NUMBER_ID first.',
            ], 422);
        }

        $data = $request->validated();
        $to = PhoneCountryHelper::toWhatsAppNumber((string) $data['to']);

        if ($to === '') {
            return response()->json(['message' => 'The phone number is not valid.'], 422);
        }

        try {
            $result = $whatsapp->sendTemplate(
                $to,
                $data['template'],
                $data['params'] ?? [],
                $data['lang'] ?? 'id',
            );
        } catch (RequestException $e) {
            $body = $e->response->json();

            return response()->json([
                'message' => 'The WhatsApp API rejected the request. Check the template name, language, and parameter count.',
                'error' => $body['error'] ?? $body,
            ], 422);
        }

        return response()->json([
            'message' => "Test message sent to {$to}.",
            'to' => $to,
            'result' => $result,
        ]);
    }
}
