<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesMetrics;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class RevenueHppPerUnitChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use UsesMetrics;

    protected static ?string $heading = 'Revenue vs HPP per Unit';

    protected static ?string $description = 'Selisih kedua batang = profit unit';

    protected static ?int $sort = 4;

    protected static ?string $maxHeight = '320px';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 2];

    protected function getData(): array
    {
        $rows = $this->metrics()->perUnit()->sortByDesc('revenue')->values();

        return [
            'labels' => $rows->pluck('unit')->all(),
            'datasets' => [
                ['label' => 'Revenue', 'data' => $rows->map(fn ($r) => round($r['revenue']))->all(), 'backgroundColor' => self::C_REVENUE, 'borderRadius' => 4],
                ['label' => 'HPP', 'data' => $rows->map(fn ($r) => round($r['hpp']))->all(), 'backgroundColor' => self::C_HPP, 'borderRadius' => 4],
                ['label' => 'Profit', 'data' => $rows->map(fn ($r) => round($r['profit']))->all(), 'backgroundColor' => self::C_PROFIT, 'borderRadius' => 4],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): RawJs
    {
        return self::moneyAxisOptions();
    }
}
