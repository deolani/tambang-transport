<?php

namespace App\Filament\Resources\TripResource\Pages;

use App\Filament\Resources\TripResource;
use App\Models\Trip;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTrips extends ListRecords
{
    protected static string $resource = TripResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export')->label('Export CSV')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->visible(fn () => TripResource::canExport())
                ->action(fn () => $this->exportCsv()),
            Actions\CreateAction::make()->label('Input Trip')->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua'),
            'draft' => Tab::make('Belum verifikasi')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft'))
                ->badge(Trip::where('status', 'draft')->count() ?: null)->badgeColor('warning'),
            'verified' => Tab::make('Verified')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'verified')),
            'ditagih' => Tab::make('Sudah ditagih')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['invoiced', 'paid'])),
        ];
    }

    /** Export baris sesuai filter & tab yang sedang aktif. */
    protected function exportCsv()
    {
        $rows = $this->getFilteredTableQuery()->with(['unit', 'driver', 'customer', 'haulRoute'])
            ->orderBy('trip_date')->orderBy('number')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, ['No Trip', 'Tanggal', 'Unit', 'Sopir', 'Customer', 'Rute', 'No Tiket', 'Tonase', 'Tarif',
                'Nilai', 'Solar (L)', 'Biaya Solar', 'Uang Jalan', 'HPP', 'Status'], ';');
            foreach ($rows as $t) {
                fputcsv($out, [
                    $t->number, $t->trip_date->format('Y-m-d'), $t->unit?->code, $t->driver?->name,
                    $t->customer?->name, $t->haulRoute?->code, $t->ticket_number,
                    number_format((float) $t->tonnage, 2, ',', ''), (float) $t->rate, (float) $t->amount,
                    (float) $t->fuel_liters, (float) $t->fuel_cost, (float) $t->allowance, (float) $t->hpp,
                    strtoupper($t->status),
                ], ';');
            }
            fclose($out);
        }, 'trip-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
