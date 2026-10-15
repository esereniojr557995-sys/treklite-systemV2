<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /** Brand names are always saved in CAPITAL LETTERS (adding, editing, quick-add from the product form, seeders...). */
    public function setNameAttribute(?string $value): void
    {
        $this->attributes['name'] = mb_strtoupper(trim((string) $value));
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
