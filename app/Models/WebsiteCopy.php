<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use Database\Factories\WebsiteCopyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * A dashboard-managed override for a single piece of page/component copy on a
 * project's public event website (SEO `<title>`/description today; body copy
 * is future rollout scope - see plan 012). `key` is a flat dot-path mirroring
 * pmone-events' `content.js` shape, e.g. `pages.home.title`. The baked
 * `content.js`/i18n value in pmone-events remains the fail-open fallback
 * whenever a project has no row for a key, or the row has no translation for
 * the requested locale - mirrors App\Models\WebsitePage (plan 011).
 *
 * The spike (plan 012) only writes/reads PAGE_KEYS x FIELDS below (Megabuild
 * `pages.home` + `pages.brands` meta). A rollout plan widens the whitelist;
 * this model and its storage shape do not need to change.
 *
 * @property int $id
 * @property int $project_id
 * @property string $key
 * @property array<array-key, mixed>|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project|null $project
 *
 * @method static \Database\Factories\WebsiteCopyFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class WebsiteCopy extends Model
{
    /** @use HasFactory<WebsiteCopyFactory> */
    use ClearsResponseCache;

    use HasFactory;
    use HasTranslations;

    /**
     * The page keys the spike's minimal admin editor may override. A
     * whitelist here (not a DB enum) keeps validation app-level, mirroring
     * WebsitePage::KEYS.
     *
     * @var array<int, string>
     */
    public const PAGE_KEYS = ['home', 'brands'];

    /**
     * The SEO meta fields the spike covers per page. Body/section copy is out
     * of scope for the spike (plan 012 deliverable 3).
     *
     * @var array<int, string>
     */
    public const FIELDS = ['title', 'description'];

    /**
     * "Copy" (marketing text) is a mass noun, not a countable one - Eloquent's
     * default pluralizer would otherwise guess `website_copies`, which reads
     * as "duplicates" rather than "website text content".
     */
    protected $table = 'website_copy';

    protected $fillable = [
        'project_id',
        'key',
        'value',
    ];

    public array $translatable = ['value'];

    /**
     * Build the flat dot-path key for a given page + field, e.g.
     * `keyFor('home', 'title') === 'pages.home.title'`.
     */
    public static function keyFor(string $page, string $field): string
    {
        return "pages.{$page}.{$field}";
    }

    /**
     * Unlike WebsitePage (which has its own dedicated public endpoint tagged
     * 'website-pages'), copy rides the existing `website-settings` payload
     * (site_config.copy - zero-round-trip, see the class docblock), so a save
     * here must invalidate the SAME tag that endpoint's
     * `TenantCacheResponse::for(86400, 'website-settings')` middleware uses -
     * see docs/site-config-contract.md rule 6 (cache-invalidation) in
     * pmone-events.
     */
    protected static function responseCacheTags(): array
    {
        return ['website-settings'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
