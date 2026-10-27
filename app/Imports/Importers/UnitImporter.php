<?php

namespace App\Imports\Importers;

use App\Imports\BaseImporter;
use App\Models\Unit;

class UnitImporter extends BaseImporter
{
    public static function key(): string { return 'units'; }

    public static function label(): string { return 'Unit / Armada'; }

    public static function description(): string { return 'Data unit dump truck. Kode unit yang sudah ada akan diperbarui.'; }

    public static function aliases(): array
    {
        return ['kode' => 'kode_unit', 'unit' => 'kode_unit', 'nopol' => 'no_polisi', 'plat' => 'no_polisi',
            'kapasitas_ton' => 'kapasitas', 'stnk' => 'masa_stnk', 'kir' => 'masa_kir'];
    }

    public static function columns(): array
    {
        return [
            'kode_unit' => ['Kode Unit', true, 'DT 1501', 'Unik'],
            'no_polisi' => ['No Polisi', true, 'BE 1234 XX', 'Unik'],
            'jenis' => ['Jenis', false, 'Dump Truck', 'Default Dump Truck'],
            'merk' => ['Merk', false, '', ''],
            'tahun' => ['Tahun', false, 2022, ''],
            'kapasitas' => ['Kapasitas (ton)', true, 50, 'Angka'],
            'no_rangka' => ['No Rangka', false, '', ''],
            'no_mesin' => ['No Mesin', false, '', ''],
            'masa_stnk' => ['Masa STNK', false, '2027-06-30', 'Tanggal'],
            'masa_kir' => ['Masa KIR', false, '2027-03-31', 'Tanggal'],
            'status' => ['Status', false, 'aktif', 'aktif / breakdown / nonaktif'],
        ];
    }

    protected function handle(array $row): string
    {
        $code = trim(preg_replace('/\s+/', ' ', (string) $row['kode_unit']));
        $unit = Unit::whereRaw("upper(replace(code, ' ', '')) = ?", [strtoupper(str_replace(' ', '', $code))])->first()
            ?? new Unit(['code' => $code]);

        $unit->fill([
            'plate_number' => strtoupper(static::str($row['no_polisi'])),
            'type' => static::str($row['jenis'] ?? null) ?? ($unit->type ?: 'Dump Truck'),
            'brand' => static::str($row['merk'] ?? null),
            'year' => ($y = static::num($row['tahun'] ?? null, 'Tahun')) ? (int) $y : null,
            'capacity_ton' => static::num($row['kapasitas'], 'Kapasitas'),
            'chassis_number' => static::str($row['no_rangka'] ?? null),
            'engine_number' => static::str($row['no_mesin'] ?? null),
            'stnk_expiry' => static::date($row['masa_stnk'] ?? null, 'Masa STNK'),
            'kir_expiry' => static::date($row['masa_kir'] ?? null, 'Masa KIR'),
            'status' => static::choice($row['status'] ?? null, array_keys(Unit::STATUSES), $unit->status ?: 'aktif', 'Status'),
        ]);
        $isNew = ! $unit->exists;
        $unit->save();

        return $isNew ? 'created' : 'updated';
    }
}
