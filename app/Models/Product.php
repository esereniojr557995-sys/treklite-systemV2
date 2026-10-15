<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = ['name', 'image_id', 'brand_id', 'category_id', 'description', 'price', 'is_active'];

    protected $casts = ['price' => 'decimal:2', 'is_active' => 'boolean'];

    /** Archived products are hidden from New Sale and stock lists but keep all their history. */
    public function scopeActive($query)
    {
        return $query->where('products.is_active', true);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
    }

    /** Link to the photo kept in the database (null = no photo, the placeholder is shown). */
    public function getImageUrlAttribute(): ?string
    {
        return $this->image_id ? route('images.show', $this->image_id) : null;
    }

    /**
     * Product without a photo: one default placeholder picture (a plain image icon) and a short tooltip that says
     * which product it is, e.g. "Slippers - Edge: no photo uploaded yet".
     */
    public function getPlaceholderAttribute(): array
    {
        $kind = trim((string) $this->category?->name);
        $name = trim((string) $this->name);

        return [
            'tip' => ($kind !== '' ? $kind . ' - ' : '') . ($name !== '' ? $name . ': ' : '') . 'no photo uploaded yet',
        ];
    }

    public function getTotalStockAttribute(): int
    {
        return (int) $this->variants->sum('quantity');
    }
}
