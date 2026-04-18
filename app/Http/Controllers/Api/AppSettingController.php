<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppSettingController extends Controller
{
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
        ]);

        $setting = AppSetting::set($key, $request->input('value'), $request->input('description'));

        return response()->json([
            'key' => $setting->key,
            'value' => $setting->value,
            'description' => $setting->description,
            'message' => 'Setting updated',
        ]);
    }
}
