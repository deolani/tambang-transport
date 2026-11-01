<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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

    /**
     * Breakdown yang membuat unit tidak bisa jalan seharian pada tanggal itu.
     * Hari mulai dan hari selesai perbaikan tetap boleh ada trip (rusak/selesai di tengah hari).
     */
    public function breakdownOn($date): ?Breakdown
    {
        return $this->breakdowns()->tap(fn ($q) => static::coveringDay($q, $date))->first();
    }

    /** Unit yang bisa dipakai pada tanggal tertentu (tidak nonaktif, tidak sedang breakdown). */
    public function scopeAvailableOn(Builder $query, $date): Builder
    {
        return $query->where('status', '!=', 'nonaktif')
            ->whereDoesntHave('breakdowns', fn ($q) => static::coveringDay($q, $date));
    }

    protected static function coveringDay($q, $date): void
    {
        $d = Carbon::parse($date ?? now())->toDateString();
        $q->whereDate('started_at', '<', $d)
            ->where(fn ($w) => $w->whereNull('finished_at')->orWhereDate('finished_at', '>', $d));
    }
}
