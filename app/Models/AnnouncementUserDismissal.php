<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $announcement_id
 * @property int $user_id
 * @property Carbon $dismissed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Announcement|null $announcement
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementUserDismissal newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementUserDismissal newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementUserDismissal query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementUserDismissal whereAnnouncementId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementUserDismissal whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementUserDismissal whereDismissedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementUserDismissal whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementUserDismissal whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnnouncementUserDismissal whereUserId($value)
 *
 * @mixin \Eloquent
 */
class AnnouncementUserDismissal extends Model
{
    protected $table = 'announcement_user_dismissals';

    protected $fillable = [
        'announcement_id',
        'user_id',
        'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'dismissed_at' => 'datetime',
        ];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
