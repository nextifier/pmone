<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * When each project's PRERENDERED content last changed, and what changed.
 *
 * Event-website pages are built ahead of time, so an edit here only reaches
 * visitors on the next build. This answers the two questions the Event Websites
 * page asks: does this site need rebuilding, and what is it waiting to publish?
 *
 * It deliberately counts ONLY content that gets baked into static HTML. Brands,
 * guests, speakers, the rundown, partners, hotels and blog posts all live on
 * pages that stayed server-rendered, so editing them reaches visitors within the
 * API cache window and needs no build. Counting them would cry wolf on every
 * blog post and train the operator to ignore the signal.
 *
 * Everything here reads `updated_at` rather than the activity log. The activity
 * log only records whitelisted fields — a Faq logs `question` and `is_active`
 * but not `answer`, a Ticket logs neither price nor quota, gallery uploads are
 * never logged at all, and the whole table is pruned after 30 days. It cannot
 * explain a staleness signal it does not see.
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
     * Every content source, in the order a reader scans them, with the label the
     * dashboard shows.
     *
     * @var array<string, string>
     */
    public const SOURCES = [
        'project' => 'Project details',
        'event' => 'Event details',
        'faqs' => 'FAQ',
        'programs' => 'Programs',
        'media_coverages' => 'Media coverage',
        'tickets' => 'Tickets',
        'gallery' => 'Gallery photos',
    ];

    /**
     * Where each source keeps the text that names one of its rows. `json` marks
     * a Spatie translatable column, which is stored as a language map.
     *
     * Gallery is absent on purpose: it is aggregated, never listed row by row.
     *
     * @var array<string, array{table: string, column: string, json: bool}>
     */
    protected const TITLES = [
        'project' => ['table' => 'projects', 'column' => 'name', 'json' => false],
        'event' => ['table' => 'events', 'column' => 'title', 'json' => false],
        'faqs' => ['table' => 'faqs', 'column' => 'question', 'json' => true],
        'programs' => ['table' => 'programs', 'column' => 'title', 'json' => true],
        'media_coverages' => ['table' => 'media_coverages', 'column' => 'title', 'json' => false],
        'tickets' => ['table' => 'tickets', 'column' => 'title', 'json' => true],
    ];

    /** A title longer than this is a paragraph, not a name. */
    protected const TITLE_LIMIT = 120;

    /**
     * Latest prerender-visible content change per project username.
     *
     * @param  string[]  $usernames
     * @return array<string, Carbon>
     */
    public static function lastChangedAt(array $usernames): array
    {
        return array_map(
            fn (array $sources): Carbon => max($sources),
            static::breakdown($usernames),
        );
    }

    /**
     * Latest change per source per project, newest source first.
     *
     * One query for every project on the page. Grouping by source instead of
     * only by username costs nothing extra — it is the same scan with a wider
     * GROUP BY — and it is what turns "something changed" into "the FAQ changed".
     *
     * @param  string[]  $usernames
     * @return array<string, array<string, Carbon>>
     */
    public static function breakdown(array $usernames): array
    {
        if (empty($usernames)) {
            return [];
        }

        $rows = DB::query()
            ->fromSub(static::union(static::sourceSelects($usernames)), 'changes')
            ->groupBy('username', 'source')
            ->selectRaw('username, source, max(updated_at) as last_changed_at')
            ->get();

        $breakdown = [];

        foreach ($rows as $row) {
            if ($row->last_changed_at === null) {
                continue;
            }

            $breakdown[$row->username][$row->source] = Carbon::parse($row->last_changed_at);
        }

        foreach ($breakdown as &$sources) {
            uasort($sources, fn (Carbon $a, Carbon $b) => $b <=> $a);
        }

        return $breakdown;
    }

    /**
     * The individual edits behind one project's staleness, newest first.
     *
     * `$since` is normally the moment the site last published something, so the
     * result reads as "what this site is waiting to ship". Without it the caller
     * gets the most recent edits regardless of whether they already shipped.
     *
     * @return array<int, array{source: string, label: string, title: string|null, changed_at: Carbon, editor: string|null, deleted: bool, count: int|null, id: int|null}>
     */
    public static function items(string $username, ?Carbon $since = null, int $limit = 25): array
    {
        // Cloudflare reports UTC while these columns hold local time, so a raw
        // UTC bind would shift every comparison by the app's offset.
        $since = $since?->copy()->setTimezone(config('app.timezone'));

        $rows = DB::query()
            ->fromSub(static::union(static::itemSelects($username, $since)), 'changes')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        // A soft delete goes through saveQuietly(), so the `updating` event never
        // fires and `updated_by` still names whoever edited the row BEFORE it was
        // removed. For a deleted row, `deleted_by` is the one that is true.
        $editorOf = fn ($row) => $row->deleted_at ? $row->deleted_by : $row->updated_by;

        $editorIds = $rows->map($editorOf)->filter()->unique()->all();

        $editors = $editorIds
            ? DB::table('users')->whereIn('id', $editorIds)->pluck('name', 'id')
            : collect();

        $items = $rows->map(fn ($row) => [
            'source' => $row->source,
            'label' => static::SOURCES[$row->source] ?? $row->source,
            'id' => $row->item_id !== null ? (int) $row->item_id : null,
            'title' => static::readTitle($row->title, static::TITLES[$row->source]['json'] ?? false),
            'changed_at' => Carbon::parse($row->updated_at),
            'editor' => ($id = $editorOf($row)) ? ($editors[$id] ?? null) : null,
            'deleted' => (bool) $row->deleted_at,
            'count' => null,
        ])->all();

        if ($gallery = static::galleryItem($username, $since)) {
            $items[] = $gallery;
        }

        usort($items, fn (array $a, array $b) => $b['changed_at'] <=> $a['changed_at']);

        return array_slice($items, 0, $limit);
    }

    /**
     * Gallery as a single row.
     *
     * One import drops hundreds of photos in a few seconds; listed individually
     * they would push every other edit out of the limit and say nothing a single
     * line cannot. The count is only reported when the caller gave a `$since` —
     * without a cut-off "1,551 photos" would describe the whole gallery rather
     * than anything that changed.
     *
     * @return array<string, mixed>|null
     */
    protected static function galleryItem(string $username, ?Carbon $since): ?array
    {
        $query = static::galleryScope($username);

        if ($since) {
            $query->where('media.updated_at', '>', $since);
        }

        $row = $query
            ->selectRaw('count(*) as total, max(media.updated_at) as last_changed_at')
            ->first();

        if (! $row || ! $row->last_changed_at) {
            return null;
        }

        return [
            'source' => 'gallery',
            'label' => static::SOURCES['gallery'],
            'id' => null,
            'title' => null,
            'changed_at' => Carbon::parse($row->last_changed_at),
            'editor' => null,
            'deleted' => false,
            'count' => $since ? (int) $row->total : null,
        ];
    }

    /**
     * One SELECT per source, each yielding `username, source, updated_at`.
     *
     * @param  string[]  $usernames
     * @return Builder[]
     */
    protected static function sourceSelects(array $usernames): array
    {
        $selects = [];

        // The project row itself: name, contact details and social links all
        // render into the footer of every prerendered page.
        $selects[] = DB::table('projects')
            ->whereIn('projects.username', $usernames)
            ->selectRaw("projects.username, 'project' as source, projects.updated_at");

        // The active event supplies the hero: dates, venue, edition, poster.
        $selects[] = DB::table('events')
            ->join('projects', 'projects.id', '=', 'events.project_id')
            ->whereIn('projects.username', $usernames)
            ->selectRaw("projects.username, 'event' as source, events.updated_at");

        // $table comes from a class constant, never from a request.
        foreach (static::EVENT_SCOPED as $table => $foreignKey) {
            $selects[] = DB::table($table)
                ->join('events', 'events.id', '=', "{$table}.{$foreignKey}")
                ->join('projects', 'projects.id', '=', 'events.project_id')
                ->whereIn('projects.username', $usernames)
                ->selectRaw("projects.username, '{$table}' as source, {$table}.updated_at");
        }

        // Gallery photos are Spatie media hanging off the event.
        $selects[] = static::galleryScope($usernames)
            ->selectRaw("projects.username, 'gallery' as source, media.updated_at");

        return $selects;
    }

    /**
     * One SELECT per titled source for a single project.
     *
     * Every branch casts its title to text. Postgres resolves a UNION's column
     * type across ALL branches and refuses to match `json` (faqs.question,
     * programs.title, tickets.title) with `varchar` — and SQLite, which the test
     * suite runs on, is happy to let that bug through untouched.
     *
     * @return Builder[]
     */
    protected static function itemSelects(string $username, ?Carbon $since): array
    {
        $selects = [];

        foreach (static::TITLES as $source => $meta) {
            $table = $meta['table'];

            $query = DB::table($table)->selectRaw(
                "'{$source}' as source,
                 {$table}.id as item_id,
                 cast({$table}.{$meta['column']} as text) as title,
                 {$table}.updated_at,
                 {$table}.updated_by,
                 {$table}.deleted_by,
                 {$table}.deleted_at"
            );

            match ($source) {
                'project' => $query->where('projects.username', $username),
                'event' => $query
                    ->join('projects', 'projects.id', '=', 'events.project_id')
                    ->where('projects.username', $username),
                default => $query
                    ->join('events', 'events.id', '=', "{$table}.".static::EVENT_SCOPED[$source])
                    ->join('projects', 'projects.id', '=', 'events.project_id')
                    ->where('projects.username', $username),
            };

            // A null timestamp sorts ahead of every real one under Postgres's
            // DESC default, which would hand back rows that changed at no known
            // time and hide the ones that did.
            $query->whereNotNull("{$table}.updated_at");

            if ($since) {
                $query->where("{$table}.updated_at", '>', $since);
            }

            $selects[] = $query;
        }

        return $selects;
    }

    /** Gallery media for one or more projects, without a SELECT list. */
    protected static function galleryScope(array|string $usernames): Builder
    {
        return DB::table('media')
            ->join('events', function ($join) {
                $join->on('events.id', '=', 'media.model_id')
                    ->where('media.model_type', '=', 'App\Models\Event');
            })
            ->join('projects', 'projects.id', '=', 'events.project_id')
            ->whereIn('projects.username', (array) $usernames)
            ->where('media.collection_name', 'gallery');
    }

    /**
     * @param  Builder[]  $selects
     */
    protected static function union(array $selects): Builder
    {
        $union = array_shift($selects);

        foreach ($selects as $select) {
            $union->unionAll($select);
        }

        return $union;
    }

    /**
     * The readable name of a row, from a plain or translatable column.
     *
     * Placeholders such as `{{event_title}}` are left alone: resolving them
     * would mean re-implementing the websites' own rendering here, and an
     * operator recognises the FAQ either way.
     */
    protected static function readTitle(?string $raw, bool $json): ?string
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        if (! $json) {
            return Str::limit($raw, static::TITLE_LIMIT);
        }

        $decoded = json_decode($raw, true);

        // Rows written before the column became translatable hold a bare string.
        if (! is_array($decoded)) {
            return Str::limit($raw, static::TITLE_LIMIT);
        }

        $value = $decoded[app()->getLocale()]
            ?? $decoded[config('app.fallback_locale')]
            ?? collect($decoded)->first(fn ($translation) => filled($translation));

        return filled($value) ? Str::limit((string) $value, static::TITLE_LIMIT) : null;
    }
}
