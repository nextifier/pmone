<?php

namespace App\Models;

use App\Traits\HasMediaManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property string $ulid
 * @property int $event_document_id
 * @property string $booth_identifier
 * @property int $event_id
 * @property Carbon|null $agreed_at
 * @property string|null $text_value
 * @property array<array-key, mixed>|null $field_values
 * @property int $document_version
 * @property int $submitted_by
 * @property Carbon $submitted_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Event|null $event
 * @property-read EventDocument $eventDocument
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read User|null $submitter
 *
 * @method static \Database\Factories\EventDocumentSubmissionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocumentSubmission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocumentSubmission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocumentSubmission query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocumentSubmission whereAgreedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocumentSubmission whereBoothIdentifier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocumentSubmission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocumentSubmission whereDocumentVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocumentSubmission whereEventDocumentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocumentSubmission whereEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocumentSubmission whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocumentSubmission whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocumentSubmission whereSubmittedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocumentSubmission whereSubmittedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocumentSubmission whereTextValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocumentSubmission whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocumentSubmission whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventDocumentSubmission whereUserAgent($value)
 *
 * @mixin \Eloquent
 */
class EventDocumentSubmission extends Model implements HasMedia
{
    use HasFactory;
    use HasMediaManager;
    use InteractsWithMedia;
    use LogsActivity;

    protected $fillable = [
        'event_document_id',
        'booth_identifier',
        'event_id',
        'agreed_at',
        'text_value',
        'field_values',
        'document_version',
        'submitted_by',
        'submitted_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'agreed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'field_values' => 'array',
            'document_version' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'event_document_id',
                'booth_identifier',
                'agreed_at',
                'document_version',
                'submitted_by',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        if ($projectId = $this->event?->project_id) {
            $activity->properties = $activity->properties->put('project_id', $projectId);
        }
    }

    public function registerMediaCollections(): void
    {
        $this->registerDynamicMediaCollections();
    }

    public function getMediaCollections(): array
    {
        return [
            'submission_file' => [
                // Multi-field mini-forms store one file per field in this
                // collection, addressed via custom_properties.field_ulid.
                'single_file' => false,
                'mime_types' => [
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ],
                'max_size' => 20480,
            ],
        ];
    }

    /**
     * Check if this submission needs re-agreement due to document version update.
     */
    public function needsReagreement(): bool
    {
        return $this->document_version < $this->eventDocument->content_version;
    }

    /**
     * The submission files that are the current version (not superseded).
     * Files uploaded before versioning have no superseded_at property and
     * are treated as current.
     *
     * @return MediaCollection<int, Media>
     */
    public function currentSubmissionFiles(): MediaCollection
    {
        return $this->getMedia('submission_file')
            ->filter(fn (Media $media) => $media->getCustomProperty('superseded_at') === null)
            ->values();
    }

    /**
     * Current-version files for a single mini-form field.
     *
     * @return MediaCollection<int, Media>
     */
    public function currentFilesForField(string $fieldUlid): MediaCollection
    {
        return $this->currentSubmissionFiles()
            ->filter(fn (Media $media) => $media->getCustomProperty('field_ulid') === $fieldUlid)
            ->values();
    }

    /**
     * All versions for a field (current + superseded), newest version first.
     *
     * @return MediaCollection<int, Media>
     */
    public function fileHistoryForField(string $fieldUlid): MediaCollection
    {
        return $this->getMedia('submission_file')
            ->filter(fn (Media $media) => ($media->getCustomProperty('field_ulid') ?? 'legacy') === $fieldUlid)
            ->sortByDesc(fn (Media $media) => (int) $media->getCustomProperty('version', 1))
            ->values();
    }

    /**
     * Structured URLs for the first current submission file, mirroring the
     * legacy getMediaUrls shape the exhibitor UI reads.
     *
     * @return array{url: string, original: string, file_name: string, caption: mixed, alt: mixed, size: int}|null
     */
    public function currentSubmissionFileUrls(): ?array
    {
        $media = $this->currentSubmissionFiles()->first();

        if (! $media) {
            return null;
        }

        $url = $media->getUrl();

        return [
            'url' => $url,
            'original' => $url,
            'file_name' => $media->file_name,
            'caption' => $media->getCustomProperty('caption'),
            'alt' => $media->getCustomProperty('alt') ?? $media->name,
            'size' => $media->size,
        ];
    }

    // Relationships

    public function eventDocument(): BelongsTo
    {
        return $this->belongsTo(EventDocument::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
