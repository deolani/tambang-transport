<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const METHODS = [
        'transfer' => 'Transfer Bank',
        'tunai' => 'Tunai',
        'giro' => 'Giro / Cek',
    ];

    protected $guarded = [];

    protected $casts = [
        'paid_at' => 'date',
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saved(fn (Payment $p) => $p->invoice?->refreshPaymentStatus());
        static::deleted(fn (Payment $p) => $p->invoice?->refreshPaymentStatus());
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
