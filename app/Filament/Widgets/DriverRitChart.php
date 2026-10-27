<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesMetrics;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class DriverRitChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use UsesMetrics;

    protected static ?string $heading = 'Tonase per Sopir';

    protected static ?string $description = 'Dasar perhitungan premi';

    protected static ?int $sort = 7;

    protected static ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 1];

    protected function getData(): array
    {
        $rows = $this->metrics()->perDriver(8);

        return [
            'labels' => $rows->map(fn ($r) => $r->name.' ('.$r->rit.' rit)')->all(),
            'datasets' => [[
                'label' => 'Tonase',
                'data' => $rows->map(fn ($r) => round((float) $r->tonnage, 2))->all(),
                'backgroundColor' => self::C_PROFIT,
                'borderRadius' => 4,
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            indexAxis: 'y',
            maintainAspectRatio: false,
            plugins: { legend: { display: false },
                tooltip: { callbacks: { label: (c) => ' ' + Number(c.raw).toLocaleString('id-ID') + ' ton' } } },
            scales: { x: { beginAtZero: true, grid: { color: 'rgba(120,113,108,0.15)' } }, y: { grid: { display: false } } },
        }
        JS);
    }
}
