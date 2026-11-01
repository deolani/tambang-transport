<?php

namespace App\Filament\Pages;

use App\Filament\Widgets;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Dashboard';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            Section::make()->columns(['default' => 2, 'md' => 3])->schema([
                DatePicker::make('startDate')->label('Dari tanggal')->native(false)
                    ->default(now()->subDays(29)->toDateString()), // 30 hari terakhir
                DatePicker::make('endDate')->label('Sampai tanggal')->native(false)
                    ->default(now()->toDateString()),
                Select::make('customerId')->label('Customer')->placeholder('Semua customer')
                    ->options(fn () => \App\Models\Customer::orderBy('name')->pluck('name', 'id'))
                    ->columnSpan(['default' => 2, 'md' => 1]),
            ]),
        ]);
    }

    public function getColumns(): int|string|array
    {
        return ['default' => 1, 'md' => 2, 'xl' => 3];
    }

    public function getWidgets(): array
    {
        return [
            // Urutan: ringkasan, yang perlu ditindaklanjuti, lalu detail.
            Widgets\KpiOverview::class,
            Widgets\AlertsWidget::class,
            Widgets\DailyTonnageChart::class,
            Widgets\HppCompositionChart::class,
            Widgets\UnitPerformanceTable::class,
            Widgets\MonthlyTrendChart::class,
            Widgets\ReceivableAgingChart::class,
            Widgets\RevenueHppPerUnitChart::class,
            Widgets\HppPerTonChart::class,
            Widgets\DriverRitChart::class,
        ];
    }
}
