<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\BreakdownResource;
use App\Filament\Resources\DriverResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\TripResource;
use App\Filament\Resources\UnitResource;
use App\Filament\Widgets\Concerns\UsesMetrics;
use App\Models\Breakdown;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Trip;
use App\Models\Unit;
use App\Support\HaulingMetrics as M;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

class AlertsWidget extends Widget
{
    use InteractsWithPageFilters;
    use UsesMetrics;

    protected static string $view = 'filament.widgets.alerts';

    protected static ?int $sort = 9;

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 1, 'xl' => 2];

    protected function getViewData(): array
    {
        $soon = now()->addDays(30)->toDateString();
        $alerts = [];

        $draft = Trip::where('status', 'draft')->count();
        if ($draft) {
            $alerts[] = ['danger', 'heroicon-o-clipboard-document-check', "{$draft} trip belum diverifikasi", TripResource::getUrl('index')];
        }

        $bd = Breakdown::whereNull('finished_at')->with('unit')->get();
        if ($bd->isNotEmpty()) {
            $alerts[] = ['danger', 'heroicon-o-wrench-screwdriver',
                $bd->count().' unit breakdown: '.$bd->pluck('unit.code')->join(', '), BreakdownResource::getUrl('index')];
        }

        $docs = Unit::where('status', '!=', 'nonaktif')
            ->where(fn ($q) => $q->whereDate('stnk_expiry', '<=', $soon)->orWhereDate('kir_expiry', '<=', $soon))
            ->pluck('code');
        if ($docs->isNotEmpty()) {
            $alerts[] = ['warning', 'heroicon-o-document-text', 'STNK/KIR habis ≤ 30 hari: '.$docs->join(', '), UnitResource::getUrl('index')];
        }

        $sim = Driver::where('status', 'aktif')->whereDate('license_expiry', '<=', $soon)->pluck('name');
        if ($sim->isNotEmpty()) {
            $alerts[] = ['warning', 'heroicon-o-identification', 'SIM habis ≤ 30 hari: '.$sim->join(', '), DriverResource::getUrl('index')];
        }

        $m = $this->metrics();
        $avg = $m->totals()['hpp_per_ton'];
        $costly = $m->perUnit()->filter(fn ($r) => $avg > 0 && $r['hpp_per_ton'] > $avg * 1.05)->pluck('unit');
        if ($costly->isNotEmpty()) {
            $alerts[] = ['warning', 'heroicon-o-fire', 'HPP/ton > 5% di atas rata-rata ('.M::rupiah($avg).'): '.$costly->join(', '), null];
        }

        $overdue = Invoice::where('status', '!=', 'lunas')->whereDate('due_date', '<', now()->toDateString())->get();
        if ($overdue->isNotEmpty()) {
            $alerts[] = ['danger', 'heroicon-o-banknotes',
                $overdue->count().' invoice lewat jatuh tempo, total '.M::rupiah($overdue->sum(fn ($i) => $i->outstanding), true),
                InvoiceResource::getUrl('index')];
        }

        $unbilled = Trip::where('status', 'verified')->whereNull('invoice_id')->sum('amount');
        if ($unbilled > 0) {
            $alerts[] = ['info', 'heroicon-o-document-plus', 'Trip verified belum ditagih: '.M::rupiah((float) $unbilled, true),
                InvoiceResource::canCreate() ? InvoiceResource::getUrl('create') : null];
        }

        return ['alerts' => $alerts];
    }
}
