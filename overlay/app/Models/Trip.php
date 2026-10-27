<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class Trip extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'verified' => 'Verified',
        'invoiced' => 'Invoiced',
        'paid' => 'Paid',
    ];

    public const STATUS_COLORS = [
        'draft' => 'gray',
        'verified' => 'success',
        'invoiced' => 'info',
        'paid' => 'warning',
    ];

    protected $guarded = [];

    protected $casts = [
        'trip_date' => 'date',
        'verified_at' => 'datetime',
        'tonnage' => 'decimal:2',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'distance_km' => 'decimal:2',
        'fuel_liters' => 'decimal:2',
        'fuel_price' => 'decimal:2',
        'fuel_cost' => 'decimal:2',
        'allowance' => 'decimal:2',
        'hpp' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Trip $trip) {
            if (blank($trip->number)) {
                $trip->number = static::nextNumber($trip->trip_date);
            }
            $trip->created_by ??= auth()->id();
        });

        static::saving(function (Trip $trip) {
            // Nilai trip yang sudah ditagih tidak dihitung ulang.
            if (in_array($trip->getOriginal('status'), ['invoiced', 'paid'], true)) {
                return;
            }
            $trip->recalculate();
        });
    }

    /** TRIP-YYYYMMDD-NNNN, urut ulang setiap hari. */
    public static function nextNumber($date): string
    {
        $d = Carbon::parse($date ?? now());
        $prefix = 'TRIP-'.$d->format('Ymd').'-';
        $last = static::query()->where('number', 'like', $prefix.'%')->orderByDesc('number')->value('number');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /** Tarik tarif, standar rute dan harga solar, lalu hitung revenue & HPP. */
    public function recalculate(): void
    {
        $route = HaulRoute::find($this->haul_route_id);
        $tariff = Tariff::activeFor($this->customer_id, $this->haul_route_id, $this->trip_date);

        if (! $tariff) {
            throw ValidationException::withMessages([
                'data.haul_route_id' => 'Tarif untuk customer dan rute ini belum ada / tidak berlaku pada tanggal trip. Isi dulu di Master Data > Tarif.',
            ]);
        }

        $calc = static::calculate(
            (float) $this->tonnage,
            $tariff,
            $route,
            FuelPrice::priceOn($this->trip_date),
        );

        $this->fill($calc);
    }

    /** Dipakai juga oleh form untuk pratinjau hitungan. */
    public static function calculate(float $tonnage, ?Tariff $tariff, ?HaulRoute $route, float $fuelPrice): array
    {
        $rate = (float) ($tariff?->rate ?? 0);
        $basis = $tariff?->unit_basis ?? 'ton';
        $amount = $basis === 'rit' ? $rate : round($tonnage * $rate, 2);

        $liters = (float) ($route?->std_fuel_liters ?? 0);
        $fuelCost = round($liters * $fuelPrice, 2);
        $allowance = (float) ($route?->allowance ?? 0);

        return [
            'rate_basis' => $basis,
            'rate' => $rate,
            'amount' => $amount,
            'distance_km' => (float) ($route?->distance_km ?? 0),
            'fuel_liters' => $liters,
            'fuel_price' => $fuelPrice,
            'fuel_cost' => $fuelCost,
            'allowance' => $allowance,
            'hpp' => $fuelCost + $allowance,
        ];
    }

    public function scopeBetweenDates(Builder $q, $from, $to): Builder
    {
        return $q->whereDate('trip_date', '>=', $from)->whereDate('trip_date', '<=', $to);
    }

    /** Trip yang dihitung sebagai pendapatan (bukan draft). */
    public function scopeCounted(Builder $q): Builder
    {
        return $q->where('status', '!=', 'draft');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function haulRoute(): BelongsTo
    {
        return $this->belongsTo(HaulRoute::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
