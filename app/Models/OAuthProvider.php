<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $provider_id
 * @property string|null $provider_email
 * @property array<array-key, mixed>|null $provider_data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OAuthProvider forProvider(string $provider)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OAuthProvider newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OAuthProvider newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OAuthProvider query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OAuthProvider whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OAuthProvider whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OAuthProvider whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OAuthProvider whereProviderData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OAuthProvider whereProviderEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OAuthProvider whereProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OAuthProvider whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OAuthProvider whereUserId($value)
 *
 * @mixin \Eloquent
 */
class OAuthProvider extends Model
{
    use HasFactory;

    protected $table = 'oauth_providers';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'provider_email',
        'provider_data',
    ];

    protected $casts = [
        'provider_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }
}
