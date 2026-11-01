<?php

namespace App\Support;

use App\Models\Breakdown;
use App\Models\Invoice;
use App\Models\Trip;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Semua angka dashboard dihitung di sini supaya widget, tabel dan laporan
 * memakai rumus yang sama.
 *
 *   Revenue  = Σ tonase × tarif
 *   HPP      = Σ (solar × harga solar + uang jalan + premi sopir) + biaya breakdown
 *   Profit   = Revenue − HPP
 */
class HaulingMetrics
{
    public Carbon $from;

    public Carbon $to;

    public function __construct($from = null, $to = null, public ?int $customerId = null)
    {
        // Bawaan 30 hari terakhir supaya dashboard tidak kosong di awal bulan.
        $this->from = Carbon::parse($from ?? now()->subDays(29))->startOfDay();
        $this->to = Carbon::parse($to ?? now())->endOfDay();
    }

    public static function fromFilters(?array $filters): self
    {
        return new self(
            $filters['startDate'] ?? null,
            $filters['endDate'] ?? null,
            filled($filters['customerId'] ?? null) ? (int) $filters['customerId'] : null,
        );
    }

    /** Scope trip yang dihitung: bukan draft, dalam periode, filter customer. */
    public function tripScope(): \Closure
    {
        // Kolom selalu diberi nama tabel (trips.status) supaya aman saat di-join.
        return function (Builder $q) {
            $q->where($q->qualifyColumn('status'), '!=', 'draft')
                ->whereDate($q->qualifyColumn('trip_date'), '>=', $this->from->toDateString())
                ->whereDate($q->qualifyColumn('trip_date'), '<=', $this->to->toDateString());
            if ($this->customerId) {
                $q->where($q->qualifyColumn('customer_id'), $this->customerId);
            }
        };
    }

    public function breakdownScope(): \Closure
    {
        return fn (Builder $q) => $q->whereBetween($q->qualifyColumn('started_at'), [$this->from, $this->to]);
    }

    /** @var array<int, float>|null */
    protected ?array $breakdownCostCache = null;

    /** Biaya breakdown per unit untuk periode ini (sudah dibagi per customer bila difilter). */
    public function breakdownCosts(): array
    {
        return $this->breakdownCostCache ??= static::breakdownCostsBetween($this->from, $this->to, $this->customerId);
    }

    /**
     * Biaya breakdown per unit. Biaya perbaikan tidak terikat customer, jadi saat
     * difilter satu customer biaya tiap unit dibagi menurut porsi rit customer itu
     * pada unit tersebut di periode yang sama. Unit tanpa rit customer itu = 0.
     *
     * @return array<int, float> unit_id => biaya
     */
    public static function breakdownCostsBetween(Carbon $from, Carbon $to, ?int $customerId = null): array
    {
        $costs = Breakdown::query()->whereBetween('started_at', [$from, $to])
            ->groupBy('unit_id')->selectRaw('unit_id, sum(cost) as total')
            ->pluck('total', 'unit_id')->map(fn ($v) => (float) $v);

        if (! $customerId || $costs->isEmpty()) {
            return $costs->all();
        }

        $rit = fn (?int $customer) => Trip::query()->where('status', '!=', 'draft')
            ->whereDate('trip_date', '>=', $from->toDateString())->whereDate('trip_date', '<=', $to->toDateString())
            ->whereIn('unit_id', $costs->keys())
            ->when($customer, fn ($q) => $q->where('customer_id', $customer))
            ->groupBy('unit_id')->selectRaw('unit_id, count(*) as n')->pluck('n', 'unit_id');
        $all = $rit(null);
        $mine = $rit($customerId);

        return $costs->map(fn ($cost, $unitId) => ($all[$unitId] ?? 0) > 0
            ? round($cost * ($mine[$unitId] ?? 0) / $all[$unitId], 2)
            : 0.0)->all();
    }

    public function trips(): Builder
    {
        return Trip::query()->where($this->tripScope());
    }

    /** Ringkasan periode. */
    public function totals(): array
    {
        $row = $this->trips()->selectRaw(
            'count(*) as rit, coalesce(sum(tonnage),0) as tonnage, coalesce(sum(amount),0) as revenue,
             coalesce(sum(hpp),0) as hpp_trip, coalesce(sum(fuel_cost),0) as fuel_cost,
             coalesce(sum(allowance),0) as allowance, coalesce(sum(driver_bonus),0) as driver_bonus,
             coalesce(sum(fuel_liters),0) as fuel_liters, coalesce(sum(distance_km),0) as km'
        )->first();

        $bdCost = (float) array_sum($this->breakdownCosts());

        $rit = (int) $row->rit;
        $tonnage = (float) $row->tonnage;
        $revenue = (float) $row->revenue;
        $hpp = (float) $row->hpp_trip + $bdCost;
        $profit = $revenue - $hpp;

        return [
            'rit' => $rit,
            'tonnage' => $tonnage,
            'revenue' => $revenue,
            'fuel_cost' => (float) $row->fuel_cost,
            'allowance' => (float) $row->allowance,
            'driver_bonus' => (float) $row->driver_bonus,
            'fuel_liters' => (float) $row->fuel_liters,
            'km' => (float) $row->km,
            'breakdown_cost' => $bdCost,
            'hpp' => $hpp,
            'profit' => $profit,
            'margin' => $revenue > 0 ? $profit / $revenue * 100 : 0,
            'ton_per_rit' => $rit > 0 ? $tonnage / $rit : 0,
            'hpp_per_ton' => $tonnage > 0 ? $hpp / $tonnage : 0,
            'units_running' => (int) $this->trips()->distinct()->count('unit_id'),
            'units_total' => Unit::where('status', '!=', 'nonaktif')->count(),
        ];
    }

    /** Periode sebelumnya dengan panjang yang sama, untuk perbandingan KPI. */
    public function previous(): self
    {
        $days = $this->from->diffInDays($this->to) + 1;

        return new self(
            $this->from->copy()->subDays($days),
            $this->from->copy()->subDay(),
            $this->customerId,
        );
    }

    /** Query unit dengan agregat trip pada periode (dipakai tabel & grafik). */
    public function unitQuery(): Builder
    {
        $scope = $this->tripScope();

        return Unit::query()
            ->whereHas('trips', $scope)
            ->withCount(['trips as rit' => $scope])
            ->withSum(['trips as tonnage_sum' => $scope], 'tonnage')
            ->withSum(['trips as km_sum' => $scope], 'distance_km')
            ->withSum(['trips as fuel_liters_sum' => $scope], 'fuel_liters')
            ->withSum(['trips as fuel_cost_sum' => $scope], 'fuel_cost')
            ->withSum(['trips as allowance_sum' => $scope], 'allowance')
            ->withSum(['trips as driver_bonus_sum' => $scope], 'driver_bonus')
            ->withSum(['trips as amount_sum' => $scope], 'amount')
            ->addSelect(['days_running' => Trip::query()
                ->selectRaw('count(distinct trip_date)')
                ->whereColumn('trips.unit_id', 'units.id')
                ->where($scope),
            ]);
    }

    /** Baris performa per unit, urut profit terbesar. */
    public function perUnit(): Collection
    {
        return $this->unitQuery()->get()->map(fn (Unit $u) => $this->row($u))
            ->sortByDesc('profit')->values();
    }

    /** Baris performa satu unit dengan biaya breakdown periode ini. */
    public function row(Unit $u): array
    {
        return static::unitRow($u, $this->breakdownCosts()[$u->id] ?? 0.0);
    }

    public static function unitRow(Unit $u, float $breakdownCost = 0.0): array
    {
        $ton = (float) $u->tonnage_sum;
        $km = (float) $u->km_sum;
        $fuelCost = (float) $u->fuel_cost_sum;
        $hpp = $fuelCost + (float) $u->allowance_sum + (float) $u->driver_bonus_sum + $breakdownCost;
        $revenue = (float) $u->amount_sum;

        return [
            'unit' => $u->code,
            'days' => (int) $u->days_running,
            'rit' => (int) $u->rit,
            'tonnage' => $ton,
            'km' => $km,
            'ton_per_km' => $km > 0 ? $ton / $km : 0,
            'fuel_liters' => (float) $u->fuel_liters_sum,
            'fuel_per_ton' => $ton > 0 ? $fuelCost / $ton : 0,
            'revenue' => $revenue,
            'hpp' => $hpp,
            'hpp_per_ton' => $ton > 0 ? $hpp / $ton : 0,
            'profit' => $revenue - $hpp,
        ];
    }

    /** Revenue, HPP, profit per bulan untuk N bulan terakhir s/d akhir periode. */
    public function monthlyTrend(int $months = 6): array
    {
        $end = $this->to->copy()->endOfMonth();
        $start = $this->to->copy()->startOfMonth()->subMonths($months - 1);

        $q = Trip::query()->where('status', '!=', 'draft')
            ->whereDate('trip_date', '>=', $start->toDateString())
            ->whereDate('trip_date', '<=', $end->toDateString());
        if ($this->customerId) {
            $q->where('customer_id', $this->customerId);
        }
        $trips = $q->get(['trip_date', 'amount', 'hpp']);

        $labels = $revenue = $hpp = $profit = [];
        for ($m = $start->copy(); $m <= $end; $m->addMonth()) {
            $key = $m->format('Y-m');
            $rev = (float) $trips->filter(fn ($t) => $t->trip_date->format('Y-m') === $key)->sum('amount');
            $cost = (float) $trips->filter(fn ($t) => $t->trip_date->format('Y-m') === $key)->sum('hpp')
                + array_sum(static::breakdownCostsBetween($m->copy()->startOfMonth(), $m->copy()->endOfMonth(), $this->customerId));
            $labels[] = $m->translatedFormat('M Y');
            $revenue[] = round($rev);
            $hpp[] = round($cost);
            $profit[] = round($rev - $cost);
        }

        return compact('labels', 'revenue', 'hpp', 'profit');
    }

    /** Tonase per hari dalam periode. */
    public function dailyTonnage(): array
    {
        $rows = $this->trips()->get(['trip_date', 'tonnage'])
            ->groupBy(fn ($t) => $t->trip_date->toDateString());

        $labels = $values = [];
        for ($d = $this->from->copy(); $d <= $this->to; $d->addDay()) {
            $labels[] = $d->format('d/m');
            $values[] = round((float) ($rows[$d->toDateString()] ?? collect())->sum('tonnage'), 2);
        }

        return compact('labels', 'values');
    }

    /** Rit dan tonase per sopir. */
    public function perDriver(int $limit = 10): Collection
    {
        return $this->trips()
            ->join('drivers', 'drivers.id', '=', 'trips.driver_id')
            ->groupBy('drivers.id', 'drivers.name')
            ->selectRaw('drivers.name as name, count(*) as rit, sum(trips.tonnage) as tonnage')
            ->orderByDesc('tonnage')
            ->limit($limit)
            ->get();
    }

    /** Umur piutang (invoice belum lunas) per kelompok hari. */
    public static function receivableAging(): array
    {
        $buckets = ['Belum jatuh tempo' => 0, '1–30 hari' => 0, '31–60 hari' => 0, '> 60 hari' => 0];

        Invoice::open()->get()->each(function (Invoice $inv) use (&$buckets) {
            $late = (int) $inv->due_date->diffInDays(now(), false);
            $key = match (true) {
                $late <= 0 => 'Belum jatuh tempo',
                $late <= 30 => '1–30 hari',
                $late <= 60 => '31–60 hari',
                default => '> 60 hari',
            };
            $buckets[$key] += $inv->outstanding;
        });

        return $buckets;
    }

    public static function rupiah(float $v, bool $short = false): string
    {
        if ($short && abs($v) >= 1_000_000) {
            return 'Rp '.number_format($v / 1_000_000, 2, ',', '.').' jt';
        }

        return 'Rp '.number_format($v, 0, ',', '.');
    }

    public static function num(float $v, int $dec = 2): string
    {
        return number_format($v, $dec, ',', '.');
    }
}
