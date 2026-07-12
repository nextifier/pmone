<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use Database\Factories\WebsitePageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * A dashboard-managed override for one of the six baked legal/policy pages
 * (`terms`, `privacy`, `event-policy`, `help-center`,
 * `ticket-terms-and-conditions`, `ticket-refund-and-return-policy`) on a
 * project's public event website. The baked `<p>` copy in the pmone-events
 * `.vue` pages remains the fail-open fallback whenever a project has no row
 * for a key, or the row has no translation for the requested locale - see
 * plan 011.
 *
 * @property int $id
 * @property int $project_id
 * @property string $key
 * @property array<array-key, mixed>|null $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project|null $project
 *
 * @method static \Database\Factories\WebsitePageFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class WebsitePage extends Model
{
    /** @use HasFactory<WebsitePageFactory> */
    use ClearsResponseCache;

    use HasFactory;
    use HasTranslations;

    /**
     * The six legal/policy page keys a project may override. A whitelist
     * here (not a DB enum) keeps validation app-level, mirroring how
     * Post::status/visibility are plain validated strings.
     *
     * @var array<int, string>
     */
    public const KEYS = [
        'terms',
        'privacy',
        'event-policy',
        'help-center',
        'ticket-terms-and-conditions',
        'ticket-refund-and-return-policy',
    ];

    protected $fillable = [
        'project_id',
        'key',
        'body',
    ];

    public array $translatable = ['body'];

    protected static function responseCacheTags(): array
    {
        return ['website-pages'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
