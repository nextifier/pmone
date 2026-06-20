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
 * @property-read Brand|null $brand
 * @property-read Attendee|null $attendee
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
