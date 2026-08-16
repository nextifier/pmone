<?php

namespace App\Models;

use Database\Factories\UserNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $author_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $author
 * @property-read User|null $user
 *
 * @method static \Database\Factories\UserNoteFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNote query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNote whereAuthorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNote whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNote whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNote whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNote whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNote whereUserId($value)
 *
 * @mixin \Eloquent
 */
class UserNote extends Model
{
    /** @use HasFactory<UserNoteFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'author_id',
        'body',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
