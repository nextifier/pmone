<?php

namespace App\Models;

use Database\Factories\UserPageViewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single admin-app page visit, recorded via the presence heartbeat on
 * navigation. Powers the user-activity analytics dashboard. Retained for a
 * fixed window and mass-pruned on a schedule.
 *
 * @property int $id
 * @property int $user_id
 * @property string $path
 * @property string|null $title
 * @property Carbon $visited_at
 */
class UserPageView extends Model
{
    /** @use HasFactory<UserPageViewFactory> */
    use HasFactory, MassPrunable;

    /**
     * Page views are append-only events, so the created_at/updated_at pair adds
     * nothing over visited_at.
     */
    public $timestamps = false;

    /**
     * Rows older than this are removed by the scheduled model:prune command.
     */
    public const RETENTION_DAYS = 90;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'path',
        'title',
        'visited_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
        ];
    }

    /**
     * @return Builder<UserPageView>
     */
    public function prunable(): Builder
    {
        return static::where('visited_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    /**
     * @return BelongsTo<User, UserPageView>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
