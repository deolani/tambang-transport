<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesMetrics;
use App\Support\HaulingMetrics;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class ReceivableAgingChart extends ChartWidget
{
    use UsesMetrics;

    protected static ?string $heading = 'Umur Piutang';

    protected static ?int $sort = 8;

    protected static ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 1];

    public static function canView(): bool
    {
        return \App\Support\Permissions::can(auth()->user(), 'dashboard.receivable');
    }

    public function getDescription(): ?string
    {
        $total = array_sum(HaulingMetrics::receivableAging());

        return 'Total belum lunas '.HaulingMetrics::rupiah($total, true);
    }

    protected function getData(): array
    {
        $b = HaulingMetrics::receivableAging();

        return [
            'labels' => array_keys($b),
            'datasets' => [[
                'label' => 'Piutang',
                'data' => array_map('round', array_values($b)),
                // makin lama makin gelap (sequential oranye)
                'backgroundColor' => ['#FCD9A8', '#F5B25C', '#E07B2E', '#A9471A'],
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
        return self::moneyAxisOptions('y', false);
    }
}
