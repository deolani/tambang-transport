<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesMetrics;
use App\Support\HaulingMetrics as M;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class HppCompositionChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use UsesMetrics;

    protected static ?string $heading = 'Komposisi HPP';

    protected static ?int $sort = 3;

    protected static ?string $maxHeight = '320px';

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 1];

    public function getDescription(): ?string
    {
        $t = $this->metrics()->totals();

        return 'Total '.M::rupiah($t['hpp'], true);
    }

    protected function getData(): array
    {
        $t = $this->metrics()->totals();
        $total = max(1, $t['hpp']);
        $pct = fn ($v) => number_format($v / $total * 100, 1, ',', '.').'%';

        return [
            'labels' => [
                'Solar '.$pct($t['fuel_cost']),
                'Uang jalan '.$pct($t['allowance']),
                'Premi sopir '.$pct($t['driver_bonus']),
                'Perbaikan '.$pct($t['breakdown_cost']),
            ],
            'datasets' => [[
                'label' => 'HPP',
                'data' => [round($t['fuel_cost']), round($t['allowance']), round($t['driver_bonus']), round($t['breakdown_cost'])],
                'backgroundColor' => [self::C_REVENUE, self::C_HPP, self::C_PROFIT, '#A1A1AA'],
                'borderColor' => 'rgba(255,255,255,0.9)',
                'borderWidth' => 2,
                'hoverOffset' => 6,
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            maintainAspectRatio: false,
            cutout: '62%',
            scales: { x: { display: false }, y: { display: false } },
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 10 } },
                tooltip: { callbacks: { label: (c) => ' Rp ' + Number(c.raw).toLocaleString('id-ID') } },
            },
        }
        JS);
    }
}
