<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $post_id
 * @property int $user_id
 * @property string $title
 * @property string|null $excerpt
 * @property string $content
 * @property string $content_format
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string $status
 * @property string $visibility
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property bool $featured
 * @property int|null $reading_time
 * @property array<array-key, mixed> $settings
 * @property array<array-key, mixed>|null $tmp_media
 * @property array<array-key, mixed>|null $tags
 * @property array<array-key, mixed>|null $authors
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Post|null $post
 * @property-read \App\Models\User|null $user
 *
 * @method static \Database\Factories\PostAutosaveFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostAutosave newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostAutosave newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostAutosave query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostAutosave whereAuthors($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostAutosave whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostAutosave whereContentFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostAutosave whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostAutosave whereExcerpt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostAutosave whereFeatured($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostAutosave whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostAutosave whereMetaDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostAutosave whereMetaTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostAutosave wherePostId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostAutosave wherePublishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostAutosave whereReadingTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostAutosave whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostAutosave whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostAutosave whereTags($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostAutosave whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostAutosave whereTmpMedia($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostAutosave whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostAutosave whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PostAutosave whereVisibility($value)
 *
 * @mixin \Eloquent
 */
class PostAutosave extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'user_id',
        'title',
        'excerpt',
        'content',
        'content_format',
        'meta_title',
        'meta_description',
        'status',
        'visibility',
        'published_at',
        'featured',
        'reading_time',
        'settings',
        'tmp_media',
        'tags',
        'authors',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'tmp_media' => 'array',
            'tags' => 'array',
            'authors' => 'array',
            'published_at' => 'datetime',
            'featured' => 'boolean',
            'reading_time' => 'integer',
        ];
    }

    /**
     * Get the post associated with this autosave
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Get the user who created this autosave
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this is an autosave for a new post (not yet created)
     */
    public function isForNewPost(): bool
    {
        return $this->post_id === null;
    }

    /**
     * Check if this is an autosave for an existing post
     */
    public function isForExistingPost(): bool
    {
        return $this->post_id !== null;
    }
}
