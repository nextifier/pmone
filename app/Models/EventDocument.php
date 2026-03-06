<?php

namespace App\Models;

use App\Traits\HasMediaManager;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $ulid
 * @property int $event_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string $document_type
 * @property bool $is_required
 * @property bool $blocks_next_step
 * @property \Illuminate\Support\Carbon|null $submission_deadline
 * @property array<array-key, mixed>|null $booth_types
 * @property int|null $order_column
 * @property array<array-key, mixed>|null $settings
 * @property int $content_version
 * @property \Illuminate\Support\Carbon|null $content_updated_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Event $event
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EventDocumentSubmission> $submissions
 * @property-read int|null $submissions_count
 * @property-read \App\Models\User|null $updater
 * @method static \Database\Factories\EventDocumentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocument newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocument newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocument ordered(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocument query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocument whereBlocksNextStep($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocument whereBoothTypes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocument whereContentUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocument whereContentVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocument whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocument whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocument whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocument whereDocumentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocument whereEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocument whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocument whereIsRequired($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocument whereOrderColumn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocument whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocument whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocument whereSubmissionDeadline($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocument whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocument whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocument whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocument whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class EventDocument extends Model implements HasMedia, Sortable
{
    use HasFactory;
    use HasMediaManager;
    use InteractsWithMedia;
    use SortableTrait;

    protected $fillable = [
        'event_id',
        'title',
        'slug',
        'description',
        'document_type',
        'is_required',
        'blocks_next_step',
        'submission_deadline',
        'booth_types',
        'settings',
        'content_version',
        'content_updated_at',
    ];

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'blocks_next_step' => 'boolean',
            'submission_deadline' => 'datetime',
            'booth_types' => 'array',
            'settings' => 'array',
            'content_version' => 'integer',
            'content_updated_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }

            if (empty($model->slug) && ! empty($model->title)) {
                $baseSlug = Str::slug($model->title);

                if (empty($baseSlug)) {
                    $baseSlug = 'document';
                }

                $slug = $baseSlug;
                $counter = 1;

                while (static::where('event_id', $model->event_id)
                    ->where('slug', $slug)
                    ->exists()
                ) {
                    $slug = $baseSlug.'-'.$counter;
                    $counter++;
                }

                $model->slug = $slug;
            }

            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function buildSortQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return static::query()->where('event_id', $this->event_id);
    }

    public function registerMediaCollections(): void
    {
        $this->registerDynamicMediaCollections();
    }

    public function registerMediaConversions($media = null): void
    {
        $this->addMediaConversion('lqip')
            ->width(20)
            ->height(20)
            ->quality(10)
            ->blur(10)
            ->performOnCollections('description_images')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->width(450)
            ->quality(85)
            ->performOnCollections('description_images')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->width(900)
            ->quality(90)
            ->performOnCollections('description_images');

        $this->addMediaConversion('lg')
            ->width(1200)
            ->quality(90)
            ->performOnCollections('description_images');

        $this->addMediaConversion('xl')
            ->width(1500)
            ->quality(95)
            ->performOnCollections('description_images');
    }

    public function getMediaCollections(): array
    {
        return [
            'template_en' => [
                'single_file' => true,
                'mime_types' => ['application/pdf'],
                'max_size' => 51200,
            ],
            'template_id' => [
                'single_file' => true,
                'mime_types' => ['application/pdf'],
                'max_size' => 51200,
            ],
            'example_file' => [
                'single_file' => true,
                'mime_types' => ['application/pdf'],
                'max_size' => 51200,
            ],
            'description_images' => [
                'single_file' => false,
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'],
                'max_size' => 20480,
            ],
        ];
    }

    /**
     * Increment content version when content is updated.
     */
    public function incrementContentVersion(): void
    {
        $this->increment('content_version');
        $this->update(['content_updated_at' => now()]);
    }

    /**
     * Check if this document is an event rule.
     */
    public function isEventRule(): bool
    {
        return $this->document_type === 'checkbox_agreement' && $this->blocks_next_step;
    }

    /**
     * Check if this document applies to a given booth type.
     */
    public function appliesToBoothType(?string $boothType): bool
    {
        if ($this->booth_types === null) {
            return true;
        }

        return in_array($boothType, $this->booth_types);
    }

    // Relationships

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(EventDocumentSubmission::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
