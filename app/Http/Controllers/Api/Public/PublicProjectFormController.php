<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitFormResponseRequest;
use App\Models\Form;
use App\Models\Project;
use App\Services\Forms\PublicFormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Project-scoped public form surface consumed by the event websites (BFF
 * proxies via /api/public/projects/{username}/forms/...). Forms resolve by
 * slug WITHIN the project only: another project's form, or a form without a
 * project, 404s server-side regardless of proxy behavior. The pipeline is
 * shared with the global surface via PublicFormService.
 */
class PublicProjectFormController extends Controller
{
    public function __construct(private readonly PublicFormService $forms) {}

    public function show(string $username, string $slug): JsonResponse
    {
        return $this->forms->show($this->resolveForm($username, $slug, ['fields', 'media']));
    }

    public function submit(SubmitFormResponseRequest $request, string $username, string $slug): JsonResponse
    {
        return $this->forms->submit($request, $this->resolveForm($username, $slug, ['fields']));
    }

    public function upload(Request $request, string $username, string $slug): JsonResponse
    {
        return $this->forms->upload($request, $this->resolvePublishedForm($username, $slug));
    }

    public function revert(Request $request, string $username, string $slug): JsonResponse
    {
        return $this->forms->revert($request);
    }

    public function checkDuplicate(Request $request, string $username, string $slug): JsonResponse
    {
        return $this->forms->checkDuplicate($request, $this->resolvePublishedForm($username, $slug));
    }

    /**
     * @param  array<int, string>  $with
     */
    private function resolveForm(string $username, string $slug, array $with = []): Form
    {
        $project = Project::where('username', $username)->firstOrFail();

        return Form::where('slug', $slug)
            ->where('project_id', $project->id)
            ->with($with)
            ->firstOrFail();
    }

    private function resolvePublishedForm(string $username, string $slug): Form
    {
        $project = Project::where('username', $username)->firstOrFail();

        return Form::where('slug', $slug)
            ->where('project_id', $project->id)
            ->where('status', Form::STATUS_PUBLISHED)
            ->where('is_active', true)
            ->firstOrFail();
    }
}
