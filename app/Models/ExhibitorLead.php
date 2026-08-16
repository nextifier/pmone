<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A lead captured when an exhibitor (Brand) scans a visitor's badge. One lead
 * per (brand, attendee) even if scanned repeatedly. `snapshot` stores the
 * attendee data (real or placeholder) at scan time.
 *
 * @property int $id
 * @property int $brand_id
 * @property int $attendee_id
 * @property int $event_id
 * @property int|null $scanned_by
 * @property Carbon $scanned_at
 * @property array<array-key, mixed>|null $snapshot
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Attendee|null $attendee
 * @property-read Brand|null $brand
 * @property-read Event|null $event
 * @property-read User|null $scannedByUser
 *
 * @method static \Database\Factories\ExhibitorLeadFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExhibitorLead newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExhibitorLead newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExhibitorLead onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExhibitorLead query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExhibitorLead whereAttendeeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExhibitorLead whereBrandId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExhibitorLead whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExhibitorLead whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExhibitorLead whereEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExhibitorLead whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExhibitorLead whereScannedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExhibitorLead whereScannedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExhibitorLead whereSnapshot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExhibitorLead whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExhibitorLead withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExhibitorLead withoutTrashed()
 *
 * @mixin \Eloquent
 */
class ExhibitorLead extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'brand_id',
        'attendee_id',
        'event_id',
        'scanned_by',
        'scanned_at',
        'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'snapshot' => 'array',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function scannedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
