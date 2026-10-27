<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class FuelPrice extends Model
{
    protected $guarded = [];

    protected $casts = [
        'valid_from' => 'date',
        'price_per_liter' => 'decimal:2',
    ];

    /** Harga solar per liter yang berlaku pada tanggal tertentu. */
    public static function priceOn($date): float
    {
        $date = Carbon::parse($date ?? now())->toDateString();

        return (float) (static::query()
            ->whereDate('valid_from', '<=', $date)
            ->orderByDesc('valid_from')
            ->value('price_per_liter') ?? 0);
    }
}
