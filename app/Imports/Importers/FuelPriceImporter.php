<?php

namespace App\Imports\Importers;

use App\Imports\BaseImporter;
use App\Imports\ImportRowException;
use App\Models\FuelPrice;

class FuelPriceImporter extends BaseImporter
{
    public static function key(): string { return 'fuel_prices'; }

    public static function label(): string { return 'Harga Solar'; }

    public static function description(): string { return 'Riwayat harga solar per liter. Tanggal yang sama akan diperbarui.'; }


    public static function columns(): array
    {
        return [
            'berlaku_mulai' => ['Berlaku Mulai', true, '2026-10-01', 'Tanggal'],
            'harga_per_liter' => ['Harga per Liter', true, 10800, 'Rupiah'],
            'vendor' => ['Vendor', false, '', 'SPBU / pemasok'],
            'catatan' => ['Catatan', false, '', ''],
        ];
    }

    protected function handle(array $row): string
    {
        $date = static::date($row['berlaku_mulai'], 'Berlaku mulai')->toDateString();
        $price = static::num($row['harga_per_liter'], 'Harga');
        if ($price <= 0) {
            throw new ImportRowException('Harga harus lebih dari 0.');
        }

        $p = FuelPrice::whereDate('valid_from', $date)->first() ?? new FuelPrice(['valid_from' => $date]);
        $p->fill([
            'price_per_liter' => $price,
            'vendor' => static::str($row['vendor'] ?? null),
            'notes' => static::str($row['catatan'] ?? null),
        ]);
        $isNew = ! $p->exists;
        $p->save();

        return $isNew ? 'created' : 'updated';
    }
}
