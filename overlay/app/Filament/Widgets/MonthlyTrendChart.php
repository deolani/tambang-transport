<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesMetrics;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class MonthlyTrendChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use UsesMetrics;

    protected static ?string $heading = 'Tren Revenue, HPP & Profit';

    protected static ?string $description = '6 bulan terakhir';

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '320px';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 2];

    protected function getData(): array
    {
        $d = $this->metrics()->monthlyTrend(6);

        return [
            'labels' => $d['labels'],
            'datasets' => [
                ['label' => 'Revenue', 'data' => $d['revenue'], 'backgroundColor' => self::C_REVENUE, 'borderRadius' => 4, 'order' => 2],
                ['label' => 'HPP', 'data' => $d['hpp'], 'backgroundColor' => self::C_HPP, 'borderRadius' => 4, 'order' => 3],
                [
                    'type' => 'line', 'label' => 'Profit', 'data' => $d['profit'], 'order' => 1,
                    'borderColor' => self::C_PROFIT, 'backgroundColor' => self::C_PROFIT,
                    'borderWidth' => 2, 'pointRadius' => 4, 'tension' => 0.3,
                ],
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
