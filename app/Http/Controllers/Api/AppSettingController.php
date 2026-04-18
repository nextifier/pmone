<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Traits\HandlesTmpMediaUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppSettingController extends Controller
{
    use HandlesTmpMediaUpload;

    public function show(string $key): JsonResponse
    {
        return response()->json([
            'key' => $key,
            'value' => AppSetting::get($key),
        ]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        if (! auth()->user()?->can('app_settings.update')) {
            abort(403);
        }

        $request->validate([
            'value' => ['required', 'array'],
            'description' => ['nullable', 'string', 'max:5000'],
            'tmp_logo' => ['nullable', 'string', 'starts_with:tmp-'],
            'delete_logo' => ['nullable', 'boolean'],
        ]);

        $value = $request->input('value');

        if ($key === 'branding') {
            $setting = AppSetting::firstOrCreate(
                ['key' => 'branding'],
                ['value' => $value, 'description' => $request->input('description')]
            );

            if ($request->boolean('delete_logo')) {
                $setting->clearMediaCollection('branding_logo');
                $value['logo_url'] = null;
            }

            if ($tmp = $request->input('tmp_logo')) {
                $this->moveTempToMediaCollection($setting, $tmp, 'branding_logo');
                $value['logo_url'] = $setting->getFirstMediaUrl('branding_logo');
            }
        }

        $setting = AppSetting::set($key, $value, $request->input('description'));

        return response()->json([
            'key' => $setting->key,
            'value' => $setting->value,
            'description' => $setting->description,
            'message' => 'Setting updated',
        ]);
    }
}
