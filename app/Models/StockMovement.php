<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = ['variant_id', 'user_id', 'type', 'quantity_before', 'quantity_change', 'quantity_after', 'note'];

    /** receive = delivery, count = physical count, sale = sold at the counter, void = a sale was reversed and the items went back to stock. */
    public const TYPE_LABELS = ['receive' => 'Received', 'count' => 'Counted', 'sale' => 'Sold', 'void' => 'Sale voided'];

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? ucfirst($this->type);
    }

    /** Stock quantity before this movement (works for old rows that did not store it). */
    public function getExpectedAttribute(): int
    {
        return (int) ($this->quantity_before ?? ($this->quantity_after - $this->quantity_change));
    }

    /** A count that did not match what the system expected. */
    public function isMismatch(): bool
    {
        return $this->type === 'count' && (int) $this->quantity_change !== 0;
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
