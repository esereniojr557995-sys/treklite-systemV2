<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'size_id', 'color_id', 'price', 'initial_stock', 'quantity', 'low_stock_threshold'];

    protected $casts = ['price' => 'decimal:2'];

    /** The size and color names are always needed to show a variant ("40 / Black"), so they load together with it. */
    protected $with = ['size', 'color'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    /** Variant price if set, otherwise the product's price. */
    public function getSellingPriceAttribute(): float
    {
        return (float) ($this->price ?? $this->product->price);
    }

    /** "40 / Black", "M", or "Standard" when there is no size or color. */
    public function getLabelAttribute(): string
    {
        return collect([$this->size?->name, $this->color?->name])->filter()->implode(' / ') ?: 'Standard';
    }

    public function isOutOfStock(): bool
    {
        return $this->quantity <= 0;
    }

    public function isLowStock(): bool
    {
        return $this->quantity > 0 && $this->quantity <= $this->low_stock_threshold;
    }
}
