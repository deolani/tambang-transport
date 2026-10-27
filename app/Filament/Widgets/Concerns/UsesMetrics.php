<?php

namespace App\Filament\Widgets\Concerns;

use App\Support\HaulingMetrics;
use Filament\Support\RawJs;

trait UsesMetrics
{
    // Palet kategori (sudah dicek aman untuk buta warna):
    public const C_REVENUE = '#2563C9';   // biru

    public const C_HPP = '#F0A030';       // kuning-oranye

    public const C_PROFIT = '#1A9A8A';    // hijau toska

    protected function metrics(): HaulingMetrics
    {
        return HaulingMetrics::fromFilters($this->filters ?? []);
    }

    /** Opsi Chart.js: sumbu nilai dalam juta rupiah. */
    protected static function moneyAxisOptions(string $axis = 'y', bool $legend = true): RawJs
    {
        $legendJs = $legend ? "{ position: 'bottom', labels: { usePointStyle: true, boxWidth: 10 } }" : '{ display: false }';

        return RawJs::make(<<<JS
        {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {$legendJs},
                tooltip: { callbacks: { label: (c) => ' ' + c.dataset.label + ': Rp ' + Number(c.raw).toLocaleString('id-ID') } },
            },
            scales: {
                {$axis}: { beginAtZero: true, grid: { color: 'rgba(120,113,108,0.15)' },
                    ticks: { callback: (v) => 'Rp ' + (v / 1000000).toLocaleString('id-ID') + ' jt' } },
            },
            borderRadius: 4,
        }
        JS);
    }
}
