<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesMetrics;
use App\Support\HaulingMetrics as M;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class HppPerTonChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use UsesMetrics;

    protected static ?string $heading = 'HPP per Ton per Unit';

    protected static ?int $sort = 5;

    protected static ?string $maxHeight = '320px';

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 1];

    public function getDescription(): ?string
    {
        return 'Oranye = di atas rata-rata '.M::rupiah($this->metrics()->totals()['hpp_per_ton']).'/ton';
    }

    protected function getData(): array
    {
        $avg = $this->metrics()->totals()['hpp_per_ton'];
        $rows = $this->metrics()->perUnit()->sortByDesc('hpp_per_ton')->values();

        return [
            'labels' => $rows->pluck('unit')->all(),
            'datasets' => [[
                'label' => 'HPP/ton',
                'data' => $rows->map(fn ($r) => round($r['hpp_per_ton']))->all(),
                'backgroundColor' => $rows->map(fn ($r) => $r['hpp_per_ton'] > $avg ? '#D97706' : '#A1A1AA')->all(),
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
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (c) => ' Rp ' + Number(c.raw).toLocaleString('id-ID') + ' / ton' } },
            },
            scales: {
                x: { beginAtZero: true, grid: { color: 'rgba(120,113,108,0.15)' },
                     ticks: { callback: (v) => 'Rp ' + Number(v).toLocaleString('id-ID') } },
                y: { grid: { display: false } },
            },
        }
        JS);
    }
}
