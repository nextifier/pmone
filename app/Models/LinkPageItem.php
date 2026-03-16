<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use App\Traits\HasMediaManager;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class LinkPageItem extends Model implements HasMedia
{
    use ClearsResponseCache, HasFactory, HasMediaManager, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'link_page_id',
        'label',
        'url',
        'description',
        'is_active',
        'sort_order',
        'og_title',
        'og_description',
        'og_image',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
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

    public function linkPage(): BelongsTo
    {
        return $this->belongsTo(LinkPage::class);
    }

    public function clicks(): MorphMany
    {
        return $this->morphMany(Click::class, 'clickable');
    }

    public function getClicksCountAttribute(): int
    {
        return $this->clicks()->count();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('poster')->singleFile();
    }

    public function registerMediaConversions($media = null): void
    {
        $this->addMediaConversion('lqip')
            ->width(20)
            ->height(20)
            ->quality(10)
            ->blur(10)
            ->performOnCollections('poster')
            ->nonQueued();

        $this->addMediaConversion('sm')
            ->width(300)
            ->quality(85)
            ->performOnCollections('poster')
            ->nonQueued();

        $this->addMediaConversion('md')
            ->width(600)
            ->quality(90)
            ->performOnCollections('poster');
    }

    public function getPosterAttribute(): ?array
    {
        return $this->getMediaUrls('poster');
    }

    protected static function responseCacheTags(): array
    {
        return ['short-links'];
    }
}
