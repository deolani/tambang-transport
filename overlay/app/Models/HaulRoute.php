<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Rute angkut: asal pit -> tujuan jetty/stockpile, dengan standar biaya per rit. */
class HaulRoute extends Model
{
    protected $guarded = [];

    protected $casts = [
        'distance_km' => 'decimal:2',
        'std_fuel_liters' => 'decimal:2',
        'allowance' => 'decimal:2',
        'std_travel_hours' => 'decimal:2',
    ];

    public function tariffs(): HasMany
    {
        return $this->hasMany(Tariff::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }
}
