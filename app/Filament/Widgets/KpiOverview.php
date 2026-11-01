<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesMetrics;
use App\Support\HaulingMetrics as M;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class KpiOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;
    use UsesMetrics;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $m = $this->metrics();
        $t = $m->totals();
        $p = $m->previous()->totals();

        $trend = function (float $now, float $before, bool $higherIsBetter = true): array {
            if ($before <= 0) {
                return ['Belum ada data pembanding', 'heroicon-m-minus', 'gray'];
            }
            $pct = ($now - $before) / $before * 100;
            $up = $pct >= 0;
            $good = $up === $higherIsBetter;

            return [
                ($up ? '+' : '').number_format($pct, 1, ',', '.').'% dari periode sebelumnya',
                $up ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down',
                $good ? 'success' : 'danger',
            ];
        };

        $stat = fn (string $label, string $value, array $tr, ?string $color = null) => Stat::make($label, $value)
            ->description($tr[0])->descriptionIcon($tr[1])->color($color ?? $tr[2]);

        return [
            $stat('Revenue', M::rupiah($t['revenue'], true), $trend($t['revenue'], $p['revenue']), 'info'),
            $stat('HPP', M::rupiah($t['hpp'], true), $trend($t['hpp'], $p['hpp'], false)),
            $stat('Profit', M::rupiah($t['profit'], true), $trend($t['profit'], $p['profit']), 'success')
                ->description('Margin '.M::num($t['margin'], 1).'%'),
            $stat('Total tonase', M::num($t['tonnage']).' t', $trend($t['tonnage'], $p['tonnage']), 'warning'),
            $stat('Total rit', M::num($t['rit'], 0).' rit', $trend($t['rit'], $p['rit'])),
            $stat('Rata-rata per rit', M::num($t['ton_per_rit']).' t', $trend($t['ton_per_rit'], $p['ton_per_rit'])),
            $stat('HPP per ton', M::rupiah($t['hpp_per_ton']), $trend($t['hpp_per_ton'], $p['hpp_per_ton'], false)),
            $stat('Unit jalan', $t['units_running'].' dari '.$t['units_total'], [
                'Unit yang punya trip di periode ini', 'heroicon-m-truck', 'gray',
            ]),
        ];
    }
}
