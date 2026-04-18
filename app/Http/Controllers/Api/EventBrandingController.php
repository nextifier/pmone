<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventBrandingController extends Controller
{
    public function show(Event $event): JsonResponse
    {
        return response()->json([
            'event_id' => $event->id,
            'branding' => $event->branding,
        ]);
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        if (! auth()->user()?->can('events.update_branding')) {
            abort(403);
        }

        $data = $request->validate([
            'branding' => ['nullable', 'array'],
            'branding.logo_url' => ['nullable', 'string', 'max:1000'],
            'branding.company_name' => ['nullable', 'string', 'max:255'],
            'branding.address' => ['nullable', 'string', 'max:500'],
            'branding.city' => ['nullable', 'string', 'max:100'],
            'branding.country' => ['nullable', 'string', 'max:100'],
            'branding.phone' => ['nullable', 'string', 'max:50'],
            'branding.email' => ['nullable', 'email', 'max:255'],
            'branding.website' => ['nullable', 'url', 'max:500'],
            'branding.tax_id' => ['nullable', 'string', 'max:100'],
            'branding.bank_accounts' => ['nullable', 'array'],
            'branding.bank_accounts.*.bank_name' => ['required_with:branding.bank_accounts', 'string', 'max:100'],
            'branding.bank_accounts.*.account_number' => ['required_with:branding.bank_accounts', 'string', 'max:50'],
            'branding.bank_accounts.*.account_name' => ['required_with:branding.bank_accounts', 'string', 'max:255'],
            'branding.footer_note' => ['nullable', 'string', 'max:1000'],
            'branding.primary_color' => ['nullable', 'string', 'max:20'],
        ]);

        $event->update(['branding' => $data['branding'] ?? null]);

        return response()->json([
            'event_id' => $event->id,
            'branding' => $event->branding,
            'message' => 'Branding updated',
        ]);
    }
}
