<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicBannerResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicBannerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projectSlug = $request->input('project_slug');
        $placement = $request->input('placement', 'hero');

        $project = Project::where('username', $projectSlug)->firstOrFail();

        $banners = $project->banners()
            ->active()
            ->where('placement', $placement)
            ->ordered()
            ->with('media')
            ->get();

        return response()->json([
            'data' => PublicBannerResource::collection($banners)->resolve(),
        ]);
    }
}
