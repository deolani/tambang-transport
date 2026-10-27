<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    public const STATUSES = [
        'aktif' => 'Aktif',
        'breakdown' => 'Breakdown',
        'nonaktif' => 'Nonaktif',
    ];

    protected $guarded = [];

    protected $casts = [
        'stnk_expiry' => 'date',
        'kir_expiry' => 'date',
        'capacity_ton' => 'decimal:2',
    ];

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function breakdowns(): HasMany
    {
        return $this->hasMany(Breakdown::class);
    }
}
