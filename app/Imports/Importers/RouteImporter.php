<?php

namespace App\Imports\Importers;

use App\Imports\BaseImporter;
use App\Models\HaulRoute;

class RouteImporter extends BaseImporter
{
    public static function key(): string { return 'routes'; }

    public static function label(): string { return 'Rute'; }

    public static function description(): string { return 'Rute angkut beserta standar jarak, solar dan uang jalan per rit.'; }

    public static function columns(): array
    {
        return [
            'kode_rute' => ['Kode Rute', true, 'BSR - 107', 'Unik'],
            'asal' => ['Asal', true, 'Pit A', 'Lokasi pit'],
            'tujuan' => ['Tujuan', true, 'Jetty B', 'Jetty / stockpile'],
            'jarak' => ['Jarak (km)', true, 36, 'Per rit'],
            'solar' => ['Solar (liter)', true, 80, 'Per rit'],
            'uang_jalan' => ['Uang Jalan', true, 200000, 'Rupiah per rit'],
            'waktu_tempuh' => ['Waktu Tempuh (jam)', false, '', ''],
            'status' => ['Status', false, 'aktif', 'aktif / nonaktif'],
        ];
    }

    protected function handle(array $row): string
    {
        $code = trim(preg_replace('/\s+/', ' ', (string) $row['kode_rute']));
        $r = HaulRoute::whereRaw("upper(replace(code, ' ', '')) = ?", [strtoupper(str_replace(' ', '', $code))])->first()
            ?? new HaulRoute(['code' => $code]);

        $r->fill([
            'origin' => static::str($row['asal']),
            'destination' => static::str($row['tujuan']),
            'distance_km' => static::num($row['jarak'], 'Jarak'),
            'std_fuel_liters' => static::num($row['solar'], 'Solar'),
            'allowance' => static::num($row['uang_jalan'], 'Uang jalan'),
            'std_travel_hours' => static::num($row['waktu_tempuh'] ?? null, 'Waktu tempuh'),
            'status' => static::choice($row['status'] ?? null, ['aktif', 'nonaktif'], $r->status ?: 'aktif', 'Status'),
        ]);
        $isNew = ! $r->exists;
        $r->save();

        return $isNew ? 'created' : 'updated';
    }
}
