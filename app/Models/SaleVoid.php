<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The record created when the Owner voids a sale: which sale, who, when and why (table: voids). */
class SaleVoid extends Model
{
    protected $table = 'voids';

    protected $fillable = ['sale_id', 'voided_by', 'void_reason', 'voided_at'];

    protected $casts = ['voided_at' => 'datetime'];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
