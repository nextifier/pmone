<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWebsitePageRequest;
use App\Models\Project;
use App\Models\WebsitePage;
use App\Support\WebsitePageTemplates;
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
     * (empty per-locale strings when never configured) and its "Last updated"
     * date, so the admin editor always renders a full six-key form. The
     * project's resolved website URL is included so the editor can link each
     * page to its live location.
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
                'last_updated_at' => $page?->last_updated_at?->toDateString(),
            ];
        }

        return response()->json([
            'data' => $data,
            'website_url' => $project->websiteUrl(),
        ]);
    }

    /**
     * Return the built-in English starting-point HTML for a page key, with the
     * project's identity/contact values interpolated, so an admin can load it
     * into the editor and customize instead of starting from a blank page. This
     * does not persist anything - the live site still fails open to the baked
     * copy until the admin saves an override.
     */
    public function template(string $username, string $key): JsonResponse
    {
        $project = Project::where('username', $username)->firstOrFail();
        $this->authorize('update', $project);

        if (! in_array($key, WebsitePage::KEYS, true)) {
            abort(404);
        }

        return response()->json([
            'data' => [
                'body' => WebsitePageTemplates::render($project, $key),
            ],
        ]);
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
        // Explicit assignment (not update($validated)) so the translatable
        // `body` set above is never clobbered - see the project's known
        // strips-siblings gotcha with validated() persistence.
        $page->last_updated_at = $request->validated('last_updated_at');
        $page->save();

        return response()->json([
            'message' => 'Page updated successfully',
            'data' => [
                'key' => $key,
                'body' => $page->getTranslations('body'),
                'last_updated_at' => $page->last_updated_at?->toDateString(),
            ],
        ]);
    }
}
