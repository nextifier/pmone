<?php

namespace App\Services\Forms;

use App\Http\Requests\SubmitFormResponseRequest;
use App\Http\Resources\PublicFormResource;
use App\Jobs\ProcessFormResponseNotification;
use App\Models\CustomField;
use App\Models\Form;
use App\Models\FormResponse;
use App\Notifications\FormResponseReceivedNotification;
use App\Support\FormFieldTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Email;
use Spatie\ResponseCache\Facades\ResponseCache;

/**
 * The full public form pipeline (availability gating, dynamic validation,
 * duplicate prevention, file handling, notifications), extracted from
 * PublicFormController so the global /f/{slug} surface and the project-scoped
 * event-website embed surface behave identically. Callers resolve the Form
 * (globally by slug, or constrained to a project) and delegate here.
 */
class PublicFormService
{
    public function show(Form $form): JsonResponse
    {
        if (! $form->is_active || $form->status !== Form::STATUS_PUBLISHED) {
            return response()->json(['message' => 'Form is not available'], 404);
        }

        if ($form->opens_at && $form->opens_at->isFuture()) {
            return response()->json(['message' => 'Form is not yet open'], 403);
        }

        $closedMessage = $form->settings['closed_message'] ?? null;

        if ($form->closes_at && $form->closes_at->isPast()) {
            return response()->json(['message' => $closedMessage ?? 'Form is closed'], 403);
        }

        if ($form->isResponseLimitReached()) {
            return response()->json(['message' => $closedMessage ?? 'Form has reached its response limit'], 403);
        }

        return response()->json([
            'data' => new PublicFormResource($form),
        ]);
    }

    public function submit(SubmitFormResponseRequest $request, Form $form): JsonResponse
    {
        $closedMessage = $form->settings['closed_message'] ?? null;

        if (! $form->isOpen()) {
            return response()->json(['message' => $closedMessage ?? 'Form is not accepting responses'], 403);
        }

        if ($form->isResponseLimitReached()) {
            return response()->json(['message' => $closedMessage ?? 'Form has reached its response limit'], 403);
        }

        $settings = $form->settings ?? [];

        if (! empty($settings['require_email'])) {
            $request->validate(['respondent_email' => ['required', Email::default(), 'max:255']]);
        }

        $fieldValidation = $this->buildFieldValidationRules($form);
        if (! empty($fieldValidation['rules'])) {
            $request->validate($fieldValidation['rules'], [], $fieldValidation['attributes']);
        }

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
            'response_data' => $this->onlyKnownFieldValues($form, $request->input('responses', [])),
            'respondent_email' => $request->input('respondent_email'),
            'browser_fingerprint' => $request->input('browser_fingerprint'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $this->processFileFields($form, $formResponse);

        if (! empty(array_filter($settings['notification_emails'] ?? []))) {
            ProcessFormResponseNotification::dispatch($formResponse);
        }

        collect([$form->user, $form->creator])
            ->filter()
            ->unique('id')
            ->each
            ->notify(new FormResponseReceivedNotification($formResponse));

        if ($form->response_limit) {
            ResponseCache::clear(['forms-public']);
        }

        return response()->json([
            'message' => $settings['confirmation_message'] ?? 'Thank you for your response!',
            'redirect_url' => $settings['redirect_url'] ?? null,
        ], 201);
    }

    public function upload(Request $request, Form $form): JsonResponse
    {
        $fileRules = ['required', 'file', 'max:20480'];

        if ($fieldUlid = $request->input('field')) {
            $field = $form->fields()
                ->where('ulid', $fieldUlid)
                ->where('type', CustomField::TYPE_FILE)
                ->firstOrFail();

            $validation = $field->validation ?? [];

            if (! empty($validation['max_file_size'])) {
                $fileRules[] = 'max:'.min((int) $validation['max_file_size'], 20480);
            }

            if (! empty($validation['allowed_file_types'])) {
                $extensions = array_map(
                    fn ($ext) => strtolower(ltrim((string) $ext, '.')),
                    $validation['allowed_file_types']
                );
                $fileRules[] = 'extensions:'.implode(',', $extensions);
            }
        }

        $request->validate(['file' => $fileRules]);

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

    public function revert(Request $request): JsonResponse
    {
        $folder = $request->getContent();

        if (! $folder || ! Str::startsWith($folder, 'form-') || Str::contains($folder, ['/', '\\', '..'])) {
            return response()->json(['error' => 'Invalid folder'], 400);
        }

        Storage::disk('local')->deleteDirectory("tmp/uploads/{$folder}");

        return response()->json([], 200);
    }

    public function checkDuplicate(Request $request, Form $form): JsonResponse
    {
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

            foreach (FormFieldTypes::rulesFor($field, $key) as $ruleKey => $fieldRules) {
                $rules[$ruleKey] = $fieldRules;
                $attributes[$ruleKey] = $field->label;
            }
        }

        return ['rules' => $rules, 'attributes' => $attributes];
    }

    private function onlyKnownFieldValues(Form $form, array $responses): array
    {
        $allowedUlids = $form->fields
            ->reject(fn (CustomField $field) => $field->type === CustomField::TYPE_SECTION)
            ->pluck('ulid')
            ->all();

        return array_intersect_key($responses, array_flip($allowedUlids));
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
            if ($field->type !== CustomField::TYPE_FILE) {
                continue;
            }

            $value = $responseData[$field->ulid] ?? null;
            if (! $value) {
                continue;
            }

            $isMultiple = is_array($value);
            $folders = $isMultiple ? $value : [$value];
            $permanentPaths = [];

            foreach ($folders as $folder) {
                $permanentPath = $this->moveUploadedFile($form, $formResponse, $folder);

                if ($permanentPath !== null) {
                    $permanentPaths[] = $permanentPath;
                }
            }

            if ($permanentPaths) {
                $responseData[$field->ulid] = $isMultiple ? $permanentPaths : $permanentPaths[0];
                $updated = true;
            }
        }

        if ($updated) {
            $formResponse->update(['response_data' => $responseData]);
        }
    }

    private function moveUploadedFile(Form $form, FormResponse $formResponse, mixed $folder): ?string
    {
        if (! is_string($folder) || ! Str::startsWith($folder, 'form-') || Str::contains($folder, ['/', '..'])) {
            return null;
        }

        $metadataPath = "tmp/uploads/{$folder}/metadata.json";
        if (! Storage::disk('local')->exists($metadataPath)) {
            return null;
        }

        $metadata = json_decode(Storage::disk('local')->get($metadataPath), true);
        $filename = basename($metadata['original_name'] ?? '');
        $tempFilePath = "tmp/uploads/{$folder}/{$filename}";

        if (! $filename || ! Storage::disk('local')->exists($tempFilePath)) {
            return null;
        }

        $permanentPath = "form-uploads/{$form->id}/{$formResponse->id}/{$filename}";

        if (Storage::disk('local')->exists($permanentPath)) {
            $permanentPath = "form-uploads/{$form->id}/{$formResponse->id}/".Str::random(6).'-'.$filename;
        }

        Storage::disk('local')->move($tempFilePath, $permanentPath);
        Storage::disk('local')->deleteDirectory("tmp/uploads/{$folder}");

        return $permanentPath;
    }
}
