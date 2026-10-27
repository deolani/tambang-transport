<?php

namespace App\Imports\Importers;

use App\Imports\BaseImporter;
use App\Imports\ImportRowException;
use App\Models\Tariff;

class TariffImporter extends BaseImporter
{
    public static function key(): string { return 'tariffs'; }

    public static function label(): string { return 'Tarif'; }

    public static function description(): string { return 'Tarif per customer + rute. Customer, rute dan tanggal mulai yang sama akan diperbarui.'; }


    public static function aliases(): array
    {
        return ['kode_customer' => 'customer', 'nama_customer' => 'customer', 'rute' => 'kode_rute',
            'tarif_per_ton' => 'tarif', 'harga' => 'tarif', 'mulai' => 'berlaku_mulai', 'sampai' => 'berlaku_sampai'];
    }

    public static function columns(): array
    {
        return [
            'customer' => ['Customer', true, 'WHL', 'Kode atau nama customer'],
            'kode_rute' => ['Kode Rute', true, 'BSR - 107', ''],
            'satuan' => ['Satuan', false, 'ton', 'ton / rit'],
            'tarif' => ['Tarif', true, 61200, 'Rupiah'],
            'berlaku_mulai' => ['Berlaku Mulai', true, '2026-10-01', 'Tanggal'],
            'berlaku_sampai' => ['Berlaku Sampai', false, '', 'Kosong = masih berlaku'],
            'no_kontrak' => ['No Kontrak', false, '', ''],
            'catatan' => ['Catatan', false, '', ''],
        ];
    }

    protected function handle(array $row): string
    {
        $customer = static::findCustomer($row['customer']);
        $route = static::findRoute($row['kode_rute']);
        $from = static::date($row['berlaku_mulai'], 'Berlaku mulai');
        $to = static::date($row['berlaku_sampai'] ?? null, 'Berlaku sampai');
        if ($to && $to->lt($from)) {
            throw new ImportRowException('Berlaku sampai lebih awal dari berlaku mulai.');
        }
        $rate = static::num($row['tarif'], 'Tarif');
        if ($rate <= 0) {
            throw new ImportRowException('Tarif harus lebih dari 0.');
        }

        $t = Tariff::where('customer_id', $customer->id)->where('haul_route_id', $route->id)
            ->whereDate('valid_from', $from->toDateString())->first()
            ?? new Tariff(['customer_id' => $customer->id, 'haul_route_id' => $route->id, 'valid_from' => $from->toDateString()]);

        $t->fill([
            'unit_basis' => static::choice($row['satuan'] ?? null, ['ton', 'rit'], $t->unit_basis ?: 'ton', 'Satuan'),
            'rate' => $rate,
            'valid_to' => $to?->toDateString(),
            'contract_number' => static::str($row['no_kontrak'] ?? null),
            'notes' => static::str($row['catatan'] ?? null),
        ]);
        $isNew = ! $t->exists;
        $t->save();

        return $isNew ? 'created' : 'updated';
    }
}
