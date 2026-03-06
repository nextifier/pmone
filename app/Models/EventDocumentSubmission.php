<?php

namespace App\Models;

use App\Traits\HasMediaManager;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $ulid
 * @property int $event_document_id
 * @property string $booth_identifier
 * @property int $event_id
 * @property \Illuminate\Support\Carbon|null $agreed_at
 * @property string|null $text_value
 * @property int $document_version
 * @property int $submitted_by
 * @property \Illuminate\Support\Carbon $submitted_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Event $event
 * @property-read \App\Models\EventDocument $eventDocument
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \App\Models\User $submitter
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
 * @mixin \Eloquent
 */
class EventDocumentSubmission extends Model implements HasMedia
{
    use HasFactory;
    use HasMediaManager;
    use InteractsWithMedia;

    protected $fillable = [
        'event_document_id',
        'booth_identifier',
        'event_id',
        'agreed_at',
        'text_value',
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

    public function registerMediaCollections(): void
    {
        $this->registerDynamicMediaCollections();
    }

    public function getMediaCollections(): array
    {
        return [
            'submission_file' => [
                'single_file' => true,
                'mime_types' => ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                'max_size' => 51200,
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
