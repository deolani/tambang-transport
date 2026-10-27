<?php

namespace App\Imports\Importers;

use App\Imports\BaseImporter;
use App\Imports\ImportRowException;
use App\Models\Breakdown;

class BreakdownImporter extends BaseImporter
{
    public static function key(): string { return 'breakdowns'; }

    public static function label(): string { return 'Breakdown Unit'; }

    public static function description(): string
    {
        return 'Riwayat kerusakan unit. Unit + waktu mulai yang sama akan diperbarui. Status unit ikut menyesuaikan.';
    }

    public static function aliases(): array
    {
        return ['kode_unit' => 'unit', 'mulai_rusak' => 'mulai', 'tanggal_mulai' => 'mulai',
            'selesai_diperbaiki' => 'selesai', 'tanggal_selesai' => 'selesai', 'keterangan' => 'kerusakan', 'biaya_perbaikan' => 'biaya'];
    }

    public static function columns(): array
    {
        return [
            'unit' => ['Unit', true, 'DT 1503', 'Kode unit'],
            'mulai' => ['Mulai', true, '2026-10-02 08:00', 'Tanggal + jam'],
            'selesai' => ['Selesai', false, '', 'Kosong = masih perbaikan'],
            'kerusakan' => ['Kerusakan', true, 'Kampas rem aus', ''],
            'tindakan' => ['Tindakan', false, '', 'Perbaikan / sparepart'],
            'biaya' => ['Biaya', false, 0, 'Rupiah'],
        ];
    }

    protected function handle(array $row): string
    {
        $unit = static::findUnit($row['unit']);
        $start = static::date($row['mulai'], 'Mulai');
        $end = static::date($row['selesai'] ?? null, 'Selesai');
        if ($end && $end->lt($start)) {
            throw new ImportRowException('Waktu selesai lebih awal dari waktu mulai.');
        }

        $bd = Breakdown::where('unit_id', $unit->id)->where('started_at', $start->format('Y-m-d H:i:s'))->first()
            ?? new Breakdown(['unit_id' => $unit->id, 'started_at' => $start]);

        $bd->fill([
            'finished_at' => $end,
            'description' => static::str($row['kerusakan']),
            'action_taken' => static::str($row['tindakan'] ?? null),
            'cost' => static::num($row['biaya'] ?? null, 'Biaya') ?? 0,
        ]);
        $isNew = ! $bd->exists;
        $bd->save();

        return $isNew ? 'created' : 'updated';
    }
}
