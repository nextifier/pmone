<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * When each project's PRERENDERED content last changed.
 *
 * Event-website pages are built ahead of time, so an edit here only reaches
 * visitors on the next build. This answers the only question that matters on the
 * Event Websites page: does this site need rebuilding?
 *
 * It deliberately counts ONLY content that gets baked into static HTML. Brands,
 * guests, speakers, the rundown, partners, hotels and blog posts all live on
 * pages that stayed server-rendered, so editing them reaches visitors within the
 * API cache window and needs no build. Counting them would cry wolf on every
 * blog post and train the operator to ignore the signal.
 *
 * @see config/edge-sites.php for the project -> website map
 */
class ProjectContentActivity
{
    /**
     * Tables whose rows are baked into prerendered HTML, keyed by the column
     * that ties a row to an event.
     *
     * @var array<string, string>
     */
    protected const EVENT_SCOPED = [
        'faqs' => 'event_id',              // /faq + the home FAQ section
        'programs' => 'event_id',          // /programs + the home section
        'media_coverages' => 'event_id',   // the home slider
        'tickets' => 'event_id',           // /tickets (baked, then self-corrected on mount)
    ];

    /**
     * Latest prerender-visible content change per project username.
     *
     * @param  string[]  $usernames
     * @return array<string, Carbon>
     */
    public static function lastChangedAt(array $usernames): array
    {
        if (empty($usernames)) {
            return [];
        }

        $selects = [];

        // The project row itself: name, contact details and social links all
        // render into the footer of every prerendered page.
        $selects[] = DB::table('projects')
            ->whereIn('username', $usernames)
            ->selectRaw('username, updated_at');

        // The active event supplies the hero: dates, venue, edition, poster.
        $selects[] = DB::table('events')
            ->join('projects', 'projects.id', '=', 'events.project_id')
            ->whereIn('projects.username', $usernames)
            ->selectRaw('projects.username, events.updated_at');

        foreach (static::EVENT_SCOPED as $table => $foreignKey) {
            $selects[] = DB::table($table)
                ->join('events', 'events.id', '=', "{$table}.{$foreignKey}")
                ->join('projects', 'projects.id', '=', 'events.project_id')
                ->whereIn('projects.username', $usernames)
                ->selectRaw("projects.username, {$table}.updated_at");
        }

        // Gallery photos are Spatie media hanging off the event.
        $selects[] = DB::table('media')
            ->join('events', function ($join) {
                $join->on('events.id', '=', 'media.model_id')
                    ->where('media.model_type', '=', 'App\Models\Event');
            })
            ->join('projects', 'projects.id', '=', 'events.project_id')
            ->whereIn('projects.username', $usernames)
            ->where('media.collection_name', 'gallery')
            ->selectRaw('projects.username, media.updated_at');

        $union = array_shift($selects);
        foreach ($selects as $select) {
            $union->unionAll($select);
        }

        return DB::query()
            ->fromSub($union, 'changes')
            ->groupBy('username')
            ->selectRaw('username, max(updated_at) as last_changed_at')
            ->pluck('last_changed_at', 'username')
            ->map(fn ($value) => Carbon::parse($value))
            ->all();
    }
}
