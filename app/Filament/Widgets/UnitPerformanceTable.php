<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsesMetrics;
use App\Models\Unit;
use App\Support\HaulingMetrics;
use App\Support\HaulingMetrics as M;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

class UnitPerformanceTable extends TableWidget
{
    use InteractsWithPageFilters;
    use UsesMetrics;

    protected static ?string $heading = 'Performa Masing-Masing Unit';

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $row = fn (Unit $record) => HaulingMetrics::unitRow($record);

        return $table
            ->query(fn () => $this->metrics()->unitQuery()->orderByDesc('amount_sum'))
            ->description('Rit, tonase, KM, solar, revenue, HPP/ton dan profit per unit (periode filter)')
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Unit')->weight('bold'),
                Tables\Columns\TextColumn::make('days_running')->label('Hari jalan')->alignEnd(),
                Tables\Columns\TextColumn::make('rit')->label('Rit')->alignEnd(),
                Tables\Columns\TextColumn::make('tonnage_sum')->label('Tonase')->alignEnd()
                    ->formatStateUsing(fn ($state) => M::num((float) $state)),
                Tables\Columns\TextColumn::make('km_sum')->label('KM')->alignEnd()
                    ->formatStateUsing(fn ($state) => M::num((float) $state, 0)),
                Tables\Columns\TextColumn::make('ton_per_km')->label('Ton/KM')->alignEnd()
                    ->state(fn (Unit $record) => M::num($row($record)['ton_per_km'])),
                Tables\Columns\TextColumn::make('fuel_liters_sum')->label('Solar')->alignEnd()
                    ->formatStateUsing(fn ($state) => M::num((float) $state, 0).' L'),
                Tables\Columns\TextColumn::make('fuel_per_ton')->label('Solar/Ton')->alignEnd()
                    ->state(fn (Unit $record) => M::rupiah($row($record)['fuel_per_ton'])),
                Tables\Columns\TextColumn::make('amount_sum')->label('Revenue')->alignEnd()
                    ->formatStateUsing(fn ($state) => M::rupiah((float) $state)),
                Tables\Columns\TextColumn::make('hpp_total')->label('HPP')->alignEnd()
                    ->state(fn (Unit $record) => M::rupiah($row($record)['hpp'])),
                Tables\Columns\TextColumn::make('hpp_per_ton')->label('HPP/Ton')->alignEnd()
                    ->state(fn (Unit $record) => M::rupiah($row($record)['hpp_per_ton'])),
                Tables\Columns\TextColumn::make('profit')->label('Profit')->alignEnd()->weight('bold')
                    ->state(fn (Unit $record) => M::rupiah($row($record)['profit']))
                    ->color(fn (Unit $record) => $row($record)['profit'] >= 0 ? 'success' : 'danger'),
            ]);
    }
}
