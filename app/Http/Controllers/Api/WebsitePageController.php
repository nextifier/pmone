<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWebsitePageRequest;
use App\Models\Project;
use App\Models\WebsitePage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

/**
 * Admin CRUD for the six dashboard-managed legal/policy page overrides (see
 * App\Models\WebsitePage). Gated by the same `projects.update` authorization
 * as ProjectController::updateWebsiteSettings, since this is project-scoped
 * settings-like content rather than an independent trashable resource.
 */
class WebsitePageController extends Controller
{
    use AuthorizesRequests;

    /**
     * List all six page keys for a project, each with its saved translations
     * (empty per-locale strings when never configured), so the admin editor
     * always renders a full six-key form.
     */
    public function index(string $username): JsonResponse
    {
        $project = Project::where('username', $username)->firstOrFail();
        $this->authorize('update', $project);

        $pages = WebsitePage::query()
            ->where('project_id', $project->id)
            ->get()
            ->keyBy('key');

        $data = [];
        foreach (WebsitePage::KEYS as $key) {
            $page = $pages->get($key);
            $data[$key] = [
                'key' => $key,
                'body' => $page?->getTranslations('body') ?? [],
            ];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Upsert a single page's translatable body by key.
     */
    public function update(UpdateWebsitePageRequest $request, string $username, string $key): JsonResponse
    {
        $project = Project::where('username', $username)->firstOrFail();
        $this->authorize('update', $project);

        if (! in_array($key, WebsitePage::KEYS, true)) {
            abort(404);
        }

        $page = WebsitePage::query()->firstOrNew([
            'project_id' => $project->id,
            'key' => $key,
        ]);

        $page->setTranslations('body', $request->validated('body'));
        $page->save();

        return response()->json([
            'message' => 'Page updated successfully',
            'data' => [
                'key' => $key,
                'body' => $page->getTranslations('body'),
            ],
        ]);
    }
}
