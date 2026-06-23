<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $event_product_id
 * @property string $product_name
 * @property string|null $product_image_url
 * @property numeric $unit_price
 * @property int $quantity
 * @property numeric $total_price
 * @property string|null $notes
 * @property string|null $internal_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $category_id
 * @property-read EventProduct|null $eventProduct
 * @property-read Order $order
 * @property-read EventProductCategory|null $productCategory
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereEventProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereProductImageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereProductName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereTotalPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereUnitPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'event_product_id',
        'category_id',
        'product_name',
        'product_image_url',
        'unit_price',
        'quantity',
        'total_price',
        'notes',
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    // Relationships

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function eventProduct(): BelongsTo
    {
        return $this->belongsTo(EventProduct::class);
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(EventProductCategory::class, 'category_id');
    }
}
