<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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
