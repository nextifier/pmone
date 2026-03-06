<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property \Illuminate\Support\Carbon $submitted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Form $form
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
 * @mixin \Eloquent
 */
class FormResponse extends Model
{
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
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
