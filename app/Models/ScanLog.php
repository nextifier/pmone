<?php

namespace App\Models;

use App\Enums\Ticketing\ScanAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only audit ledger of every scan action against an attendee
 * (check-in / reprint / reissue). Doubles as the offline-reconciliation
 * source: `idempotency_key` (client UUID) dedupes pushes from many devices.
 *
 * @property int $id
 * @property int $attendee_id
 * @property ScanAction $action
 * @property int $event_id
 * @property int|null $staff_id
 * @property Carbon $scanned_at
 * @property string $idempotency_key
 * @property array<array-key, mixed>|null $meta
 * @property-read Attendee|null $attendee
 *
 * @mixin \Eloquent
 */
class ScanLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendee_id',
        'action',
        'event_id',
        'staff_id',
        'scanned_at',
        'idempotency_key',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'action' => ScanAction::class,
            'scanned_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
