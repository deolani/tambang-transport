<?php

namespace Database\Seeders;

use App\Models\Breakdown;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\FuelPrice;
use App\Models\HaulRoute;
use App\Models\Tariff;
use App\Models\Trip;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data contoh periode 1–7 Oktober 2026.
 * Total per unit dibuat sama persis dengan dashboard aplikasi lama:
 * revenue Rp 84.392.964 · HPP Rp 34.048.000 · 32 rit · 1.378,97 ton.
 * Nama sopir "Sopir DT…", no polisi dan dokumen adalah data contoh — silakan ganti.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---------- User ----------
        foreach ([
            ['Super Admin', 'superadmin@tta.test', 'super_admin'],
            ['Owner', 'owner@tta.test', 'owner'],
            ['Admin Operasional', 'admin@tta.test', 'admin'],
            ['Checker', 'checker@tta.test', 'checker'],
            ['Keuangan', 'keuangan@tta.test', 'keuangan'],
        ] as [$name, $email, $role]) {
            // firstOrCreate: akun yang sudah ada (dan password-nya) tidak diubah bila seeder dijalankan ulang
            User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'password', 'role' => $role, 'is_active' => true]);
        }
        $owner = User::where('role', 'owner')->first();
        auth()->setUser($owner);

        // ---------- Master ----------
        $units = [];
        foreach (['1501', '1502', '1503', '1504', '1505', '1506', '1507'] as $no) {
            $units[$no] = Unit::updateOrCreate(['code' => "DT {$no}"], [
                'plate_number' => "BE {$no} TA",
                'type' => 'Dump Truck',
                'brand' => null,
                'year' => 2022,
                'capacity_ton' => 50,
                'stnk_expiry' => '2027-06-30',
                'kir_expiry' => $no === '1506' ? '2026-11-15' : '2027-03-31',
                'status' => 'aktif',
            ]);
        }

        $driverNames = [
            '1501' => 'Awanda', '1502' => 'Syahrul', '1503' => 'Sopir DT1503', '1504' => 'Sopir DT1504',
            '1505' => 'Ponijan', '1506' => 'Sopir DT1506', '1507' => 'Zulfan',
        ];
        $drivers = [];
        foreach ($driverNames as $no => $name) {
            $drivers[$no] = Driver::updateOrCreate(['name' => $name], [
                'phone' => '0812'.$no.'0000',
                'license_number' => 'SIM-'.$no,
                'license_type' => 'B2 Umum',
                'license_expiry' => '2028-01-31',
                'default_unit_id' => $units[$no]->id,
                'bonus_scheme' => 'rit',
                'bonus_rate' => 0,
                'status' => 'aktif',
            ]);
        }

        $customer = Customer::updateOrCreate(['code' => 'WHL'], [
            'name' => 'PT Whale Holder Logistics',
            'payment_terms_days' => 30,
            'is_pkp' => true,
            'status' => 'aktif',
        ]);

        $route = HaulRoute::updateOrCreate(['code' => 'BSR - 107'], [
            'origin' => 'Pit (isi lokasi)',
            'destination' => 'Jetty / stockpile (isi lokasi)',
            'distance_km' => 36,
            'std_fuel_liters' => 80,
            'allowance' => 200000,
            'status' => 'aktif',
        ]);

        Tariff::updateOrCreate(
            ['customer_id' => $customer->id, 'haul_route_id' => $route->id, 'valid_from' => '2026-01-01'],
            ['unit_basis' => 'ton', 'rate' => 61200],
        );

        FuelPrice::updateOrCreate(['valid_from' => '2026-01-01'], ['price_per_liter' => 10800]);

        if (Trip::exists()) {
            return; // jangan dobel bila seeder dijalankan ulang
        }

        // ---------- Trip ----------
        $make = function (string $unit, string $date, float $ton, ?string $ticket = null) use ($units, $drivers, $customer, $route) {
            static $seq = 0;
            $seq++;
            Trip::create([
                'trip_date' => $date,
                'unit_id' => $units[$unit]->id,
                'driver_id' => $drivers[$unit]->id,
                'customer_id' => $customer->id,
                'haul_route_id' => $route->id,
                'ticket_number' => $ticket ?? 'CE'.date('ymd', strtotime($date)).str_pad((string) (200000 + $seq * 7919 % 99999), 6, '0', STR_PAD_LEFT),
                'tonnage' => $ton,
                'status' => 'verified',
                'verified_at' => $date.' 18:00:00',
                'verified_by' => auth()->id(),
            ]);
        };

        // 5 trip asli 3 Oktober 2026 (nomor TRIP-20261003-0001 s/d 0005)
        $real = [
            ['1505', 45.44, 'CE261003010057'],
            ['1507', 43.59, 'CE261003093845'],
            ['1502', 46.16, 'CE261003005935'],
            ['1501', 46.95, 'CE261003011505'],
            ['1505', 46.64, 'CE261003175907'],
        ];
        foreach ($real as [$u, $t, $tk]) {
            $make($u, '2026-10-03', $t, $tk);
        }

        // Sisa trip agar total per unit sama dengan dashboard lama.
        // unit => [total tonase, [tanggal => jumlah rit (di luar trip asli)]]
        $plan = [
            '1501' => [448.22, ['2026-10-01' => 3, '2026-10-03' => 2, '2026-10-05' => 2, '2026-10-07' => 2]],
            '1506' => [272.88, ['2026-10-01' => 2, '2026-10-02' => 1, '2026-10-04' => 2, '2026-10-06' => 1, '2026-10-07' => 1]],
            '1502' => [230.72, ['2026-10-02' => 2, '2026-10-03' => 1, '2026-10-06' => 1]],
            '1505' => [222.98, ['2026-10-04' => 2, '2026-10-06' => 1]],
            '1507' => [204.17, ['2026-10-03' => 1, '2026-10-05' => 2, '2026-10-07' => 1]],
        ];

        foreach ($plan as $unit => [$total, $days]) {
            $already = collect($real)->where(0, $unit)->sum(1);
            $n = array_sum($days);
            $remaining = round($total - $already, 2);
            $base = $remaining / $n;
            $wiggle = [0.62, -0.41, 0.28, -0.55, 0.37, -0.19, 0.44, -0.33, 0.12];

            $i = 0;
            $sum = 0.0;
            foreach ($days as $date => $count) {
                for ($k = 0; $k < $count; $k++) {
                    $i++;
                    $ton = $i === $n ? round($remaining - $sum, 2) : round($base + $wiggle[($i - 1) % count($wiggle)], 2);
                    $sum += $ton;
                    $make($unit, $date, $ton);
                }
            }
        }

        // ---------- Breakdown ----------
        Breakdown::create([
            'unit_id' => $units['1504']->id,
            'started_at' => '2026-09-28 13:00:00',
            'finished_at' => '2026-10-01 10:00:00',
            'description' => 'Ganti ban belakang',
            'action_taken' => 'Ban diganti 2 pcs',
            'cost' => 3500000,
        ]);
        Breakdown::create([
            'unit_id' => $units['1503']->id,
            'started_at' => '2026-10-02 08:00:00',
            'description' => 'Kampas rem aus',
            'cost' => 0,
        ]);

        auth()->forgetUser();
    }
}
