<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tariff extends Model
{
    protected $guarded = [];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
        'rate' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function haulRoute(): BelongsTo
    {
        return $this->belongsTo(HaulRoute::class);
    }

    /** Tarif yang berlaku untuk customer + rute pada tanggal tertentu. */
    public static function activeFor(?int $customerId, ?int $routeId, $date): ?self
    {
        if (! $customerId || ! $routeId || ! $date) {
            return null;
        }

        $date = \Illuminate\Support\Carbon::parse($date)->toDateString();

        return static::query()
            ->where('customer_id', $customerId)
            ->where('haul_route_id', $routeId)
            ->whereDate('valid_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date))
            ->orderByDesc('valid_from')
            ->first();
    }
}
