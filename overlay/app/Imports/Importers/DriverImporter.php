<?php

namespace App\Imports\Importers;

use App\Imports\BaseImporter;
use App\Models\Driver;

class DriverImporter extends BaseImporter
{
    public static function key(): string { return 'drivers'; }

    public static function label(): string { return 'Sopir'; }

    public static function description(): string { return 'Data sopir. Dicocokkan dengan NIK, atau nama bila NIK kosong.'; }

    public static function aliases(): array
    {
        return ['nama_sopir' => 'nama', 'hp' => 'no_hp', 'telepon' => 'no_hp', 'sim' => 'no_sim',
            'masa_berlaku_sim' => 'masa_sim', 'sim_berlaku' => 'masa_sim', 'unit' => 'unit_default'];
    }

    public static function columns(): array
    {
        return [
            'nama' => ['Nama', true, 'Ponijan', ''],
            'nik' => ['NIK', false, '', '16 digit, unik'],
            'no_hp' => ['No HP', true, '081234567890', ''],
            'no_sim' => ['No SIM', true, '1234-5678-000001', ''],
            'jenis_sim' => ['Jenis SIM', false, 'B2 Umum', 'B1 Umum / B2 Umum'],
            'masa_sim' => ['Masa SIM', true, '2028-01-31', 'Tanggal'],
            'unit_default' => ['Unit Default', false, 'DT 1505', 'Kode unit'],
            'tanggal_masuk' => ['Tanggal Masuk', false, '2024-01-15', 'Tanggal'],
            'skema_premi' => ['Skema Premi', false, 'rit', 'rit / ton'],
            'nilai_premi' => ['Nilai Premi', false, 0, 'Rupiah'],
            'status' => ['Status', false, 'aktif', 'aktif / nonaktif'],
        ];
    }

    protected function handle(array $row): string
    {
        $nik = static::str($row['nik'] ?? null);
        $nik = $nik ? preg_replace('/\D/', '', $nik) : null;
        $name = static::str($row['nama']);

        $driver = ($nik ? Driver::where('nik', $nik)->first() : null)
            ?? Driver::whereRaw('lower(name) = ?', [strtolower($name)])->first()
            ?? new Driver;

        $unitId = static::str($row['unit_default'] ?? null) ? static::findUnit($row['unit_default'])->id : $driver->default_unit_id;

        $driver->fill([
            'name' => $name,
            'nik' => $nik ?? $driver->nik,
            'phone' => static::str($row['no_hp']),
            'license_number' => static::str($row['no_sim']),
            'license_type' => static::str($row['jenis_sim'] ?? null) ?? ($driver->license_type ?: 'B2 Umum'),
            'license_expiry' => static::date($row['masa_sim'], 'Masa SIM'),
            'default_unit_id' => $unitId,
            'joined_at' => static::date($row['tanggal_masuk'] ?? null, 'Tanggal masuk'),
            'bonus_scheme' => static::choice($row['skema_premi'] ?? null, ['rit', 'ton'], $driver->bonus_scheme ?: 'rit', 'Skema premi'),
            'bonus_rate' => static::num($row['nilai_premi'] ?? null, 'Nilai premi') ?? 0,
            'status' => static::choice($row['status'] ?? null, ['aktif', 'nonaktif'], $driver->status ?: 'aktif', 'Status'),
        ]);
        $isNew = ! $driver->exists;
        $driver->save();

        return $isNew ? 'created' : 'updated';
    }
}
