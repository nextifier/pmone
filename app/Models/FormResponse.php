<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property int $form_id
 * @property array<array-key, mixed> $response_data
 * @property string|null $respondent_email
 * @property string|null $browser_fingerprint
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string $status
 * @property Carbon $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Form|null $form
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FormResponse newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FormResponse newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FormResponse query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FormResponse whereBrowserFingerprint($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FormResponse whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FormResponse whereFormId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FormResponse whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FormResponse whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FormResponse whereRespondentEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FormResponse whereResponseData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FormResponse whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FormResponse whereSubmittedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FormResponse whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FormResponse whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FormResponse whereUserAgent($value)
 *
 * @mixin \Eloquent
 */
class FormResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'ulid',
        'form_id',
        'response_data',
        'respondent_email',
        'browser_fingerprint',
        'ip_address',
        'user_agent',
        'status',
        'submitted_at',
    ];

    public const STATUS_NEW = 'new';

    public const STATUS_READ = 'read';

    public const STATUS_STARRED = 'starred';

    public const STATUS_SPAM = 'spam';

    protected function casts(): array
    {
        return [
            'response_data' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }

            if (empty($model->submitted_at)) {
                $model->submitted_at = now();
            }
        });

        static::deleted(function ($model) {
            Storage::disk('local')->deleteDirectory("form-uploads/{$model->form_id}/{$model->id}");
        });
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * Search respondent email always; answer contents only on PostgreSQL
     * (the response_data::text cast is not portable to SQLite).
     */
    public function scopeSearch($query, string $search)
    {
        $term = '%'.strtolower($search).'%';

        return $query->where(function ($q) use ($term) {
            $q->whereRaw('LOWER(respondent_email) LIKE ?', [$term]);

            if ($q->getConnection()->getDriverName() === 'pgsql') {
                $q->orWhereRaw('LOWER(response_data::text) LIKE ?', [$term]);
            }
        });
    }
}
