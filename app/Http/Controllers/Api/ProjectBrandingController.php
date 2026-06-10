<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Traits\HandlesTmpMediaUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectBrandingController extends Controller
{
    use HandlesTmpMediaUpload;

    public function show(Project $project): JsonResponse
    {
        return response()->json([
            'project_id' => $project->id,
            'branding' => $project->branding,
        ]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        if (! auth()->user()?->can('events.update_branding')) {
            abort(403);
        }

        $data = $request->validate([
            'branding' => ['nullable', 'array'],
            'branding.logo_url' => ['nullable', 'string', 'max:1000'],
            'branding.company_name' => ['nullable', 'string', 'max:255'],
            'branding.address' => ['nullable', 'string', 'max:500'],
            'branding.phone' => ['nullable', 'string', 'max:50'],
            'branding.email' => ['nullable', 'email', 'max:255'],
            'branding.website' => ['nullable', 'url', 'max:500'],
            'branding.tax_id' => ['nullable', 'string', 'max:100'],
            'branding.footer_note' => ['nullable', 'string', 'max:1000'],
            'tmp_logo' => ['nullable', 'string', 'starts_with:tmp-'],
            'delete_logo' => ['nullable', 'boolean'],
        ]);

        $branding = $data['branding'] ?? null;

        if ($branding !== null) {
            if ($request->boolean('delete_logo')) {
                $project->clearMediaCollection('branding_logo');
                $branding['logo_url'] = null;
            }

            if ($tmp = $request->input('tmp_logo')) {
                $this->moveTempToMediaCollection($project, $tmp, 'branding_logo');
                $branding['logo_url'] = $project->getFirstMediaUrl('branding_logo');
            }
        } else {
            $project->clearMediaCollection('branding_logo');
        }

        $project->update(['branding' => $branding]);

        return response()->json([
            'project_id' => $project->id,
            'branding' => $project->branding,
            'message' => 'Branding updated',
        ]);
    }
}
