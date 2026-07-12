<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWebsiteCopyRequest;
use App\Models\Project;
use App\Models\WebsiteCopy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

/**
 * Admin CRUD for the SEO-meta copy editor (see App\Models\WebsiteCopy).
 * Covers every `WebsiteCopy::PAGE_KEYS` page x `FIELDS` (title/description) -
 * plan 012. Gated by the same `projects.update` authorization as
 * WebsitePageController, since this is project-scoped settings-like content
 * rather than an independent trashable resource.
 */
class WebsiteCopyController extends Controller
{
    use AuthorizesRequests;

    /**
     * List the full page x field grid, each with its saved translations
     * (empty per-locale strings when never configured), so the admin editor
     * always renders a full grid.
     */
    public function index(string $username): JsonResponse
    {
        $project = Project::where('username', $username)->firstOrFail();
        $this->authorize('update', $project);

        $rows = WebsiteCopy::query()
            ->where('project_id', $project->id)
            ->get()
            ->keyBy('key');

        $data = [];
        foreach (WebsiteCopy::PAGE_KEYS as $page) {
            foreach (WebsiteCopy::FIELDS as $field) {
                $key = WebsiteCopy::keyFor($page, $field);
                $row = $rows->get($key);
                $data[$page][$field] = $row?->getTranslations('value') ?? [];
            }
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Upsert a single page/field's translatable value.
     */
    public function update(UpdateWebsiteCopyRequest $request, string $username, string $page, string $field): JsonResponse
    {
        $project = Project::where('username', $username)->firstOrFail();
        $this->authorize('update', $project);

        if (! in_array($page, WebsiteCopy::PAGE_KEYS, true) || ! in_array($field, WebsiteCopy::FIELDS, true)) {
            abort(404);
        }

        $key = WebsiteCopy::keyFor($page, $field);

        $row = WebsiteCopy::query()->firstOrNew([
            'project_id' => $project->id,
            'key' => $key,
        ]);

        $row->setTranslations('value', $request->validated('value'));
        $row->save();

        return response()->json([
            'message' => 'Copy updated successfully',
            'data' => [
                'key' => $key,
                'value' => $row->getTranslations('value'),
            ],
        ]);
    }
}
