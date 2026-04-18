<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Traits\HandlesTmpMediaUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventBrandingController extends Controller
{
    use HandlesTmpMediaUpload;

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
            'tmp_logo' => ['nullable', 'string', 'starts_with:tmp-'],
            'delete_logo' => ['nullable', 'boolean'],
        ]);

        $branding = $data['branding'] ?? null;

        if ($branding !== null) {
            if ($request->boolean('delete_logo')) {
                $event->clearMediaCollection('branding_logo');
                $branding['logo_url'] = null;
            }

            if ($tmp = $request->input('tmp_logo')) {
                $this->moveTempToMediaCollection($event, $tmp, 'branding_logo');
                $branding['logo_url'] = $event->getFirstMediaUrl('branding_logo');
            }
        } else {
            $event->clearMediaCollection('branding_logo');
        }

        $event->update(['branding' => $branding]);

        return response()->json([
            'event_id' => $event->id,
            'branding' => $event->branding,
            'message' => 'Branding updated',
        ]);
    }
}
