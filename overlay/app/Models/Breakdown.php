<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Breakdown extends Model
{
    public const STATUSES = [
        'proses' => 'Proses',
        'selesai' => 'Selesai',
    ];

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'cost' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Breakdown $bd) {
            if (blank($bd->number)) {
                $prefix = 'BD-'.Carbon::parse($bd->started_at ?? now())->format('Ymd').'-';
                $count = static::where('number', 'like', $prefix.'%')->count();
                $bd->number = $prefix.str_pad((string) ($count + 1), 2, '0', STR_PAD_LEFT);
            }
        });

        static::saving(function (Breakdown $bd) {
            $bd->status = $bd->finished_at ? 'selesai' : 'proses';
        });

        // Status unit ikut berubah: ada breakdown terbuka => unit "breakdown".
        static::saved(fn (Breakdown $bd) => static::syncUnitStatus($bd->unit_id));
        static::deleted(fn (Breakdown $bd) => static::syncUnitStatus($bd->unit_id));
    }

    public static function syncUnitStatus(int $unitId): void
    {
        $unit = Unit::find($unitId);
        if (! $unit || $unit->status === 'nonaktif') {
            return;
        }
        $open = static::where('unit_id', $unitId)->whereNull('finished_at')->exists();
        $unit->update(['status' => $open ? 'breakdown' : 'aktif']);
    }

    /** Lama downtime dalam jam (sampai sekarang jika belum selesai). */
    public function getDowntimeHoursAttribute(): float
    {
        if (! $this->started_at) {
            return 0;
        }
        $end = $this->finished_at ?? now();

        return round($this->started_at->diffInMinutes($end) / 60, 1);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
