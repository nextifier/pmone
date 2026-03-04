<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitFormResponseRequest;
use App\Http\Resources\PublicFormResource;
use App\Models\Form;
use App\Models\FormResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicFormController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $form = Form::where('slug', $slug)
            ->with(['fields', 'media'])
            ->firstOrFail();

        if (! $form->is_active || $form->status !== Form::STATUS_PUBLISHED) {
            return response()->json(['message' => 'Form is not available'], 404);
        }

        if ($form->opens_at && $form->opens_at->isFuture()) {
            return response()->json(['message' => 'Form is not yet open'], 403);
        }

        if ($form->closes_at && $form->closes_at->isPast()) {
            return response()->json(['message' => 'Form is closed'], 403);
        }

        if ($form->isResponseLimitReached()) {
            return response()->json(['message' => 'Form has reached its response limit'], 403);
        }

        return response()->json([
            'data' => new PublicFormResource($form),
        ]);
    }

    public function submit(SubmitFormResponseRequest $request, string $slug): JsonResponse
    {
        $form = Form::where('slug', $slug)
            ->with(['fields'])
            ->firstOrFail();

        if (! $form->isOpen()) {
            return response()->json(['message' => 'Form is not accepting responses'], 403);
        }

        if ($form->isResponseLimitReached()) {
            return response()->json(['message' => 'Form has reached its response limit'], 403);
        }

        // Dynamic per-field validation
        $fieldValidation = $this->buildFieldValidationRules($form);
        if (! empty($fieldValidation['rules'])) {
            $request->validate($fieldValidation['rules'], [], $fieldValidation['attributes']);
        }

        // Check duplicate submission
        $duplicateConfig = $form->getPreventDuplicateConfig();
        if ($duplicateConfig['prevent_duplicate']) {
            $isDuplicate = $this->checkDuplicateSubmission(
                $form,
                $duplicateConfig['prevent_duplicate_by'],
                $request->input('respondent_email'),
                $request->input('browser_fingerprint')
            );

            if ($isDuplicate) {
                return response()->json(['message' => 'You have already submitted this form'], 409);
            }
        }

        $formResponse = FormResponse::create([
            'form_id' => $form->id,
            'response_data' => $request->input('responses'),
            'respondent_email' => $request->input('respondent_email'),
            'browser_fingerprint' => $request->input('browser_fingerprint'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Handle file fields - move from tmp
        $this->processFileFields($form, $formResponse);

        $settings = $form->settings ?? [];

        return response()->json([
            'message' => $settings['confirmation_message'] ?? 'Thank you for your response!',
            'redirect_url' => $settings['redirect_url'] ?? null,
        ], 201);
    }

    public function upload(Request $request, string $slug): JsonResponse
    {
        $form = Form::where('slug', $slug)
            ->where('status', Form::STATUS_PUBLISHED)
            ->where('is_active', true)
            ->firstOrFail();

        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('file');
        $folder = uniqid('form-', true);
        $filename = $file->getClientOriginalName();

        Storage::disk('local')->putFileAs(
            "tmp/uploads/{$folder}",
            $file,
            $filename
        );

        Storage::disk('local')->put(
            "tmp/uploads/{$folder}/metadata.json",
            json_encode([
                'original_name' => $filename,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_at' => now()->toISOString(),
            ])
        );

        return response()->json(['folder' => $folder], 200);
    }

    public function revert(Request $request, string $slug): JsonResponse
    {
        $folder = $request->getContent();

        if (! $folder || ! Str::startsWith($folder, 'form-')) {
            return response()->json(['error' => 'Invalid folder'], 400);
        }

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$folder}");

        return response()->json([], 200);
    }

    public function checkDuplicate(Request $request, string $slug): JsonResponse
    {
        $form = Form::where('slug', $slug)->firstOrFail();

        $duplicateConfig = $form->getPreventDuplicateConfig();

        if (! $duplicateConfig['prevent_duplicate']) {
            return response()->json(['already_submitted' => false]);
        }

        $isDuplicate = $this->checkDuplicateSubmission(
            $form,
            $duplicateConfig['prevent_duplicate_by'],
            $request->input('email'),
            $request->input('fingerprint')
        );

        return response()->json(['already_submitted' => $isDuplicate]);
    }

    /**
     * @return array{rules: array, attributes: array}
     */
    private function buildFieldValidationRules(Form $form): array
    {
        $rules = [];
        $attributes = [];

        foreach ($form->fields as $field) {
            $key = 'responses.'.$field->ulid;
            $fieldRules = [];
            $validation = $field->validation ?? [];

            if (! empty($validation['required'])) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            // Type-based validation
            switch ($field->type) {
                case 'email':
                    $fieldRules[] = 'email';
                    break;
                case 'number':
                    $fieldRules[] = 'numeric';
                    break;
                case 'url':
                    $fieldRules[] = 'url';
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                case 'multi_select':
                case 'checkbox_group':
                    $fieldRules[] = 'array';
                    break;
            }

            if (isset($validation['min'])) {
                $fieldRules[] = 'min:'.$validation['min'];
            }
            if (isset($validation['max'])) {
                $fieldRules[] = 'max:'.$validation['max'];
            }

            if (! empty($fieldRules)) {
                $rules[$key] = $fieldRules;
                $attributes[$key] = $field->label;
            }
        }

        return ['rules' => $rules, 'attributes' => $attributes];
    }

    private function checkDuplicateSubmission(Form $form, ?string $by, ?string $email, ?string $fingerprint): bool
    {
        $query = $form->responses();

        return match ($by) {
            'email' => $email ? $query->where('respondent_email', $email)->exists() : false,
            'fingerprint' => $fingerprint ? $query->where('browser_fingerprint', $fingerprint)->exists() : false,
            'both' => ($email && $query->clone()->where('respondent_email', $email)->exists())
                || ($fingerprint && $query->clone()->where('browser_fingerprint', $fingerprint)->exists()),
            default => false,
        };
    }

    private function processFileFields(Form $form, FormResponse $formResponse): void
    {
        $responseData = $formResponse->response_data;
        $updated = false;

        foreach ($form->fields as $field) {
            if ($field->type !== 'file') {
                continue;
            }

            $folder = $responseData[$field->ulid] ?? null;
            if (! $folder || ! Str::startsWith($folder, 'form-')) {
                continue;
            }

            $metadataPath = "tmp/uploads/{$folder}/metadata.json";
            if (! Storage::disk('local')->exists($metadataPath)) {
                continue;
            }

            $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);
            $filename = $metadata['original_name'];
            $tempFilePath = "tmp/uploads/{$folder}/{$filename}";

            if (! Storage::disk('local')->exists($tempFilePath)) {
                continue;
            }

            // Move to permanent storage
            $permanentPath = "form-uploads/{$form->id}/{$formResponse->id}/{$filename}";
            Storage::disk('local')->move($tempFilePath, $permanentPath);

            // Update response data with permanent path
            $responseData[$field->ulid] = $permanentPath;
            $updated = true;

            // Clean up temp
            Storage::disk('local')->deleteDirectory("tmp/uploads/{$folder}");
        }

        if ($updated) {
            $formResponse->update(['response_data' => $responseData]);
        }
    }
}
