<?php

use App\Models\Event;
use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Move hotel reservation configuration from event level to project level.
 *
 * - Adds projects.hotel_reservation_enabled (boolean) + projects.branding (jsonb).
 * - Backfill: a project is enabled when any of its non-trashed events was enabled;
 *   project branding is taken from the most recently updated event that has one,
 *   and that event's branding_logo media is moved to the project via
 *   Media::move() (CollectionBasedPathGenerator derives the file path from
 *   model_type/model_id, so a raw DB re-point would orphan the file on disk).
 *   Leftover branding_logo media on other events is deleted via the Media model
 *   so the files are removed from disk.
 * - Drops events.hotel_reservation_enabled + events.branding.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('hotel_reservation_enabled')->default(false)->after('visibility');
            $table->jsonb('branding')->nullable()->after('settings');
        });

        DB::transaction(function () {
            DB::table('projects')->whereIn('id',
                DB::table('events')
                    ->where('hotel_reservation_enabled', true)
                    ->whereNull('deleted_at')
                    ->pluck('project_id')
            )->update(['hotel_reservation_enabled' => true]);

            $brandedEvents = DB::table('events')
                ->whereNotNull('branding')
                ->whereNull('deleted_at')
                ->orderByDesc('updated_at')
                ->get(['id', 'project_id', 'branding']);

            $chosenEventIds = [];

            foreach ($brandedEvents->groupBy('project_id') as $projectId => $events) {
                $chosen = $events->first();
                $chosenEventIds[] = $chosen->id;

                DB::table('projects')
                    ->where('id', $projectId)
                    ->update(['branding' => $chosen->branding]);

                $project = Project::find($projectId);
                if (! $project) {
                    continue;
                }

                Media::query()
                    ->where('model_type', Event::class)
                    ->where('model_id', $chosen->id)
                    ->where('collection_name', 'branding_logo')
                    ->get()
                    ->each(fn (Media $media) => $media->move($project, 'branding_logo'));

                if ($logoUrl = $project->getFirstMediaUrl('branding_logo')) {
                    $branding = json_decode($chosen->branding, true) ?: [];
                    $branding['logo_url'] = $logoUrl;
                    DB::table('projects')
                        ->where('id', $projectId)
                        ->update(['branding' => json_encode($branding)]);
                }
            }

            Media::query()
                ->where('model_type', Event::class)
                ->where('collection_name', 'branding_logo')
                ->whereNotIn('model_id', $chosenEventIds)
                ->get()
                ->each(fn (Media $media) => $media->delete());
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['hotel_reservation_enabled', 'branding']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('hotel_reservation_enabled')->default(false)->after('visibility');
            $table->jsonb('branding')->nullable()->after('custom_fields');
        });

        DB::transaction(function () {
            $projects = DB::table('projects')
                ->where('hotel_reservation_enabled', true)
                ->orWhereNotNull('branding')
                ->get(['id', 'hotel_reservation_enabled', 'branding']);

            foreach ($projects as $project) {
                if ($project->hotel_reservation_enabled) {
                    DB::table('events')
                        ->where('project_id', $project->id)
                        ->whereNull('deleted_at')
                        ->update(['hotel_reservation_enabled' => true]);
                }

                if ($project->branding === null) {
                    continue;
                }

                $latestEventId = DB::table('events')
                    ->where('project_id', $project->id)
                    ->whereNull('deleted_at')
                    ->orderByDesc('updated_at')
                    ->value('id');

                if ($latestEventId) {
                    DB::table('events')
                        ->where('id', $latestEventId)
                        ->update(['branding' => $project->branding]);

                    $event = Event::find($latestEventId);
                    if ($event) {
                        Media::query()
                            ->where('model_type', Project::class)
                            ->where('model_id', $project->id)
                            ->where('collection_name', 'branding_logo')
                            ->get()
                            ->each(fn (Media $media) => $media->move($event, 'branding_logo'));
                    }
                }
            }
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['hotel_reservation_enabled', 'branding']);
        });
    }
};
