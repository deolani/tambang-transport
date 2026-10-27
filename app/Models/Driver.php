<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    protected $guarded = [];

    protected $casts = [
        'license_expiry' => 'date',
        'joined_at' => 'date',
        'bonus_rate' => 'decimal:2',
    ];

    public function defaultUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'default_unit_id');
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }
}
