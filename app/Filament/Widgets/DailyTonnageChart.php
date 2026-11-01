<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesMetrics;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class DailyTonnageChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use UsesMetrics;

    protected static ?string $heading = 'Tonase Harian';

    protected static ?string $description = 'Ton batubara terangkut per hari';

    protected static ?int $sort = 6;

    protected static ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 2];

    protected function getData(): array
    {
        $d = $this->metrics()->dailyTonnage();

        return [
            'labels' => $d['labels'],
            'datasets' => [[
                'label' => 'Tonase',
                'data' => $d['values'],
                'borderColor' => self::C_REVENUE,
                'backgroundColor' => 'rgba(29,78,216,0.12)',
                'fill' => true,
                'tension' => 0.35,
                'borderWidth' => 2,
                'pointRadius' => 4,
                'pointBackgroundColor' => self::C_REVENUE,
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (c) => ' ' + Number(c.raw).toLocaleString('id-ID') + ' ton' } },
            },
            scales: { y: { beginAtZero: true, grid: { color: 'rgba(120,113,108,0.15)' },
                ticks: { callback: (v) => v + ' t' } }, x: { grid: { display: false } } },
        }
        JS);
    }
}
