<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Sale extends Model
{
    protected $fillable = [
        'invoice_number', 'user_id', 'customer_name', 'customer_address', 'customer_contact',
        'payment_method', 'payment_reference', 'payment_proof_id', 'payment_proof_hash',
        'amount_tendered', 'change_amount', 'total_amount', 'sold_at',
    ];

    protected $casts = [
        'sold_at'         => 'datetime',
        'total_amount'    => 'decimal:2',
        'amount_tendered' => 'decimal:2',
        'change_amount'   => 'decimal:2',
    ];

    /** The void record (if any) loads together with the sale, so lists can show the VOIDED badge without extra queries. */
    protected $with = ['voidRecord'];

    /** Payment methods the cashier can choose today. */
    public const PAYMENT_METHODS = [
        'cash' => 'Cash', 'gcash' => 'GCash', 'bank_transfer' => 'Bank Transfer',
    ];

    /** Every label, including methods that were removed but still exist on old sales. */
    public const PAYMENT_LABELS = self::PAYMENT_METHODS + [
        'paymaya' => 'PayMaya (old)', 'card' => 'Card (old)',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /** Photo of the payment confirmation, kept in the database. */
    public function paymentProof(): BelongsTo
    {
        return $this->belongsTo(Image::class, 'payment_proof_id');
    }

    /** Who / when / why this sale was voided (none = the sale is valid). */
    public function voidRecord(): HasOne
    {
        return $this->hasOne(SaleVoid::class);
    }

    /** The Owner who voided the sale. */
    public function voidedBy(): HasOneThrough
    {
        return $this->hasOneThrough(User::class, SaleVoid::class, 'sale_id', 'id', 'id', 'voided_by');
    }

    /** Sales that count in totals and reports (a voided sale does not). */
    public function scopeActive($query)
    {
        return $query->whereDoesntHave('voidRecord');
    }

    public function isVoided(): bool
    {
        return $this->voidRecord !== null;
    }

    public function getVoidedAtAttribute()
    {
        return $this->voidRecord?->voided_at;
    }

    public function getVoidReasonAttribute(): ?string
    {
        return $this->voidRecord?->void_reason;
    }

    public function getPaymentLabelAttribute(): string
    {
        return self::PAYMENT_LABELS[$this->payment_method] ?? ucfirst($this->payment_method);
    }

    public function getPaymentProofUrlAttribute(): ?string
    {
        return $this->payment_proof_id ? route('images.show', $this->payment_proof_id) : null;
    }
}
