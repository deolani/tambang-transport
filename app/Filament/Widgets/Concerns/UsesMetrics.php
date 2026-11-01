<?php

namespace App\Filament\Widgets\Concerns;

use App\Support\HaulingMetrics;
use Filament\Support\RawJs;

trait UsesMetrics
{
    // Palet kategori: biru, amber, hijau (beda terang-gelap, bukan hanya warna):
    public const C_REVENUE = '#1D4ED8';   // biru korporat

    public const C_HPP = '#F59E0B';       // amber

    public const C_PROFIT = '#15803D';    // hijau

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
