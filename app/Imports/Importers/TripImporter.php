<?php

namespace App\Imports\Importers;

use App\Imports\BaseImporter;
use App\Imports\ImportRowException;
use App\Models\Trip;
use App\Support\Permissions;

class TripImporter extends BaseImporter
{
    public static function key(): string { return 'trips'; }

    public static function label(): string { return 'Trip / Ritase'; }

    public static function description(): string
    {
        return 'Trip harian dari rekap tiket timbang. Tarif, solar, uang jalan dan HPP dihitung otomatis. '
            .'No tiket yang sudah ada akan diperbarui (kecuali sudah ditagih).';
    }

    public static function previewKeys(): array
    {
        return ['tanggal', 'unit', 'no_tiket', 'tonase'];
    }

    public static function aliases(): array
    {
        return [
            'tgl' => 'tanggal', 'tanggal_trip' => 'tanggal', 'date' => 'tanggal',
            'kode_unit' => 'unit', 'no_unit' => 'unit', 'unit_dt' => 'unit',
            'nama_sopir' => 'sopir', 'driver' => 'sopir',
            'kode_customer' => 'customer', 'nama_customer' => 'customer', 'pelanggan' => 'customer',
            'kode_rute' => 'rute', 'route' => 'rute',
            'tiket' => 'no_tiket', 'nomor_tiket' => 'no_tiket', 'no_tiket_timbang' => 'no_tiket', 'ticket' => 'no_tiket',
            'netto' => 'tonase', 'tonase_netto' => 'tonase', 'berat' => 'tonase', 'berat_netto' => 'tonase', 'ton' => 'tonase',
        ];
    }

    public static function columns(): array
    {
        return [
            'tanggal' => ['Tanggal', true, '2026-10-03', 'Tanggal'],
            'unit' => ['Unit', true, 'DT 1505', 'Kode unit'],
            'sopir' => ['Sopir', true, 'Ponijan', 'Nama sopir'],
            'customer' => ['Customer', true, 'WHL', 'Kode atau nama customer'],
            'rute' => ['Rute', true, 'BSR - 107', 'Kode rute'],
            'no_tiket' => ['No Tiket', true, 'CE261003175907', 'Unik'],
            'tonase' => ['Tonase', true, '46,64', 'Ton netto'],
            'status' => ['Status', false, 'draft', 'draft / verified (default draft)'],
            'catatan' => ['Catatan', false, '', ''],
        ];
    }

    protected function handle(array $row): string
    {
        $ticket = strtoupper(preg_replace('/\s+/', '', (string) $row['no_tiket']));
        $date = static::date($row['tanggal'], 'Tanggal');
        $ton = static::num($row['tonase'], 'Tonase');
        if ($ton <= 0 || $ton > 100) {
            throw new ImportRowException("Tonase {$ton} di luar batas wajar (0–100 ton).");
        }

        $unit = static::findUnit($row['unit']);
        if ($unit->status === 'nonaktif') {
            throw new ImportRowException("Unit {$unit->code} berstatus nonaktif.");
        }
        $driver = static::findDriver($row['sopir']);
        $customer = static::findCustomer($row['customer']);
        $route = static::findRoute($row['rute']);

        $user = auth()->user();
        $trip = Trip::where('ticket_number', $ticket)->first();
        if ($trip && in_array($trip->status, ['invoiced', 'paid'], true)) {
            throw new ImportRowException("Tiket {$ticket} sudah masuk invoice {$trip->invoice?->number}, tidak bisa diubah.");
        }
        if ($trip && $trip->status === 'verified' && ! Permissions::can($user, 'trips.edit_verified')) {
            throw new ImportRowException("Tiket {$ticket} sudah VERIFIED; Anda tidak punya hak mengubah trip VERIFIED.");
        }

        // Kolom status kosong: trip lama tetap statusnya, trip baru = draft.
        $status = static::choice($row['status'] ?? null, ['draft', 'verified'], $trip?->status ?? 'draft', 'Status');
        if ($status === 'verified' && ! Permissions::can($user, 'trips.verify') && $trip?->status !== 'verified') {
            $status = 'draft'; // hanya yang punya hak verifikasi boleh langsung verified
        }
        $trip ??= new Trip(['ticket_number' => $ticket]);

        $trip->fill([
            'trip_date' => $date->toDateString(),
            'unit_id' => $unit->id,
            'driver_id' => $driver->id,
            'customer_id' => $customer->id,
            'haul_route_id' => $route->id,
            'tonnage' => round($ton, 2),
            'status' => $status,
            'notes' => static::str($row['catatan'] ?? null),
        ]);
        if ($status === 'verified' && ! $trip->verified_at) {
            $trip->verified_by = auth()->id();
            $trip->verified_at = now();
        }
        $isNew = ! $trip->exists;
        $trip->save(); // hitung otomatis di Trip::recalculate()

        return $isNew ? 'created' : 'updated';
    }
}
