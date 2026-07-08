<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitFormResponseRequest;
use App\Models\Form;
use App\Services\Forms\PublicFormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global public form surface (pmone.id /f/{slug}): forms resolve by slug
 * alone, regardless of project. The project-scoped event-website embed lives
 * in Public\PublicProjectFormController; both delegate to PublicFormService.
 */
class PublicFormController extends Controller
{
    public function __construct(private readonly PublicFormService $forms) {}

    public function show(string $slug): JsonResponse
    {
        $form = Form::where('slug', $slug)
            ->with(['fields', 'media'])
            ->firstOrFail();

        return $this->forms->show($form);
    }

    public function submit(SubmitFormResponseRequest $request, string $slug): JsonResponse
    {
        $form = Form::where('slug', $slug)
            ->with(['fields'])
            ->firstOrFail();

        return $this->forms->submit($request, $form);
    }

    public function upload(Request $request, string $slug): JsonResponse
    {
        $form = Form::where('slug', $slug)
            ->where('status', Form::STATUS_PUBLISHED)
            ->where('is_active', true)
            ->firstOrFail();

        return $this->forms->upload($request, $form);
    }

    public function revert(Request $request, string $slug): JsonResponse
    {
        return $this->forms->revert($request);
    }

    public function checkDuplicate(Request $request, string $slug): JsonResponse
    {
        $form = Form::where('slug', $slug)
            ->where('status', Form::STATUS_PUBLISHED)
            ->where('is_active', true)
            ->firstOrFail();

        return $this->forms->checkDuplicate($request, $form);
    }
}
