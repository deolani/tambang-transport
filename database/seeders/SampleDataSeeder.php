<?php

namespace Database\Seeders;

use App\Models\Breakdown;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\FuelPrice;
use App\Models\HaulRoute;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tariff;
use App\Models\Trip;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Data contoh lengkap: akun, master data, trip 15 Sep – 31 Okt 2026, breakdown,
 * invoice (lunas, sebagian, belum lunas, lewat jatuh tempo, batal) dan pembayaran.
 *
 *   php artisan migrate:fresh --seeder=SampleDataSeeder
 *
 * Periode 1–7 Oktober 2026 sengaja sama persis dengan dashboard aplikasi lama:
 * revenue Rp 84.392.964 · HPP Rp 34.048.000 · 32 rit · 1.378,97 ton.
 * Nama sopir "Sopir DT…", no polisi, lokasi dan dokumen adalah data contoh — silakan ganti.
 */
class SampleDataSeeder extends Seeder
{
    /** @var array<string, Unit> */
    protected array $units = [];

    /** @var array<string, Driver> */
    protected array $drivers = [];

    protected int $ticketSeq = 0;

    public function run(): void
    {
        $this->call(UserSeeder::class);

        if (Trip::exists()) {
            $this->command?->warn('Trip sudah ada; data contoh tidak ditambahkan lagi. Pakai migrate:fresh untuk mengulang dari nol.');

            return;
        }

        $admin = User::where('role', 'admin')->first();
        $checker = User::where('role', 'checker')->first();
        $keuangan = User::where('role', 'keuangan')->first();

        auth()->setUser($admin);
        [$whl, $bct, $bsr107, $bsr112] = $this->masterData();
        $this->breakdowns();

        // ---------- Trip ----------
        $this->octoberFirstWeek($whl, $bsr107, $checker);
        mt_srand(20260915); // angka acak tetap sama setiap seeding
        $this->tripsBetween('2026-09-15', '2026-09-30', $whl, $bsr107, ['1501', '1502', '1503', '1504', '1505', '1506', '1507'], 'verified', $checker);
        $this->tripsBetween('2026-10-08', '2026-10-28', $whl, $bsr107, ['1501', '1502', '1504', '1505', '1506', '1507'], 'verified', $checker);
        $this->tripsBetween('2026-10-10', '2026-10-25', $bct, $bsr112, ['1506', '1507'], 'verified', $checker, maxRit: 1);
        $this->tripsBetween('2026-10-29', '2026-10-31', $whl, $bsr107, ['1501', '1502', '1505'], 'draft', $checker, maxRit: 1);

        // ---------- Invoice & pembayaran ----------
        auth()->setUser($keuangan);
        $tax = fn (Customer $c) => Invoice::defaultTaxes($c);

        $sep = Invoice::createFromTrips(['customer_id' => $whl->id, 'period_from' => '2026-09-15', 'period_to' => '2026-09-30', 'issue_date' => '2026-10-01'] + $tax($whl));
        $half = round((float) $sep->total / 2);
        $this->pay($sep, '2026-10-15', $half, 'TRF-WHL-1015');
        $this->pay($sep, '2026-10-29', (float) $sep->total - $half, 'TRF-WHL-1029');

        $oct1 = Invoice::createFromTrips(['customer_id' => $whl->id, 'period_from' => '2026-10-01', 'period_to' => '2026-10-07', 'issue_date' => '2026-10-08'] + $tax($whl));
        $this->pay($oct1, '2026-10-27', 40000000, 'TRF-WHL-1027');

        $wrong = Invoice::createFromTrips(['customer_id' => $bct->id, 'period_from' => '2026-10-10', 'period_to' => '2026-10-17', 'issue_date' => '2026-10-18'] + $tax($bct));
        $wrong->cancel('Periode salah, seharusnya sampai 20 Oktober.');

        Invoice::createFromTrips(['customer_id' => $bct->id, 'period_from' => '2026-10-10', 'period_to' => '2026-10-20', 'issue_date' => '2026-10-21'] + $tax($bct));
        Invoice::createFromTrips(['customer_id' => $whl->id, 'period_from' => '2026-10-08', 'period_to' => '2026-10-14', 'issue_date' => '2026-10-15'] + $tax($whl));
        // Sisanya sengaja belum ditagih: WHL 15–28 Okt dan BCT 21–25 Okt; 29–31 Okt masih Draft.

        auth()->forgetUser();
    }

    /** @return array{0: Customer, 1: Customer, 2: HaulRoute, 3: HaulRoute} */
    protected function masterData(): array
    {
        foreach (['1501', '1502', '1503', '1504', '1505', '1506', '1507'] as $no) {
            $this->units[$no] = Unit::updateOrCreate(['code' => "DT {$no}"], [
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
        Unit::updateOrCreate(['code' => 'DT 1408'], [
            'plate_number' => 'BE 1408 TA', 'type' => 'Dump Truck', 'year' => 2016, 'capacity_ton' => 40,
            'stnk_expiry' => '2026-12-31', 'kir_expiry' => '2026-08-31', 'status' => 'nonaktif',
        ]);

        $names = [
            '1501' => 'Awanda', '1502' => 'Syahrul', '1503' => 'Sopir DT1503', '1504' => 'Rahmat',
            '1505' => 'Ponijan', '1506' => 'Sopir DT1506', '1507' => 'Zulfan',
        ];
        foreach ($names as $no => $name) {
            $no = (string) $no; // kunci array numerik berubah jadi int di PHP
            $this->drivers[$no] = Driver::updateOrCreate(['name' => $name], [
                'phone' => '0812'.$no.'0000',
                'license_number' => 'SIM-'.$no,
                'license_type' => 'B2 Umum',
                'license_expiry' => $no === '1507' ? '2026-11-20' : '2028-01-31',
                'default_unit_id' => $this->units[$no]->id,
                // Contoh premi: Rahmat dibayar per ton. Sopir lain 0 agar angka 1–7 Okt tetap sama.
                'bonus_scheme' => $no === '1504' ? 'ton' : 'rit',
                'bonus_rate' => $no === '1504' ? 1500 : 0,
                'status' => 'aktif',
            ]);
        }

        $whl = Customer::updateOrCreate(['code' => 'WHL'], [
            'name' => 'PT Whale Holder Logistics', 'payment_terms_days' => 30, 'is_pkp' => true, 'status' => 'aktif',
        ]);
        $bct = Customer::updateOrCreate(['code' => 'BCT'], [
            'name' => 'CV Borneo Coal Trading', 'payment_terms_days' => 7, 'is_pkp' => false, 'status' => 'aktif',
        ]);

        $bsr107 = HaulRoute::updateOrCreate(['code' => 'BSR - 107'], [
            'origin' => 'Pit (isi lokasi)', 'destination' => 'Jetty / stockpile (isi lokasi)',
            'distance_km' => 36, 'std_fuel_liters' => 80, 'allowance' => 200000, 'status' => 'aktif',
        ]);
        $bsr112 = HaulRoute::updateOrCreate(['code' => 'BSR - 112'], [
            'origin' => 'Pit Selatan (isi lokasi)', 'destination' => 'Stockpile pelabuhan (isi lokasi)',
            'distance_km' => 48, 'std_fuel_liters' => 105, 'allowance' => 260000, 'status' => 'aktif',
        ]);

        Tariff::updateOrCreate(
            ['customer_id' => $whl->id, 'haul_route_id' => $bsr107->id, 'valid_from' => '2026-01-01'],
            ['unit_basis' => 'ton', 'rate' => 61200],
        );
        Tariff::updateOrCreate( // contoh tarif borongan per rit
            ['customer_id' => $bct->id, 'haul_route_id' => $bsr112->id, 'valid_from' => '2026-09-01'],
            ['unit_basis' => 'rit', 'rate' => 3150000],
        );

        FuelPrice::updateOrCreate(['valid_from' => '2026-01-01'], ['price_per_liter' => 10800]);
        FuelPrice::updateOrCreate(['valid_from' => '2026-10-15'], ['price_per_liter' => 11200, 'notes' => 'Kenaikan harga solar industri']);

        return [$whl, $bct, $bsr107, $bsr112];
    }

    /** Dibuat sebelum trip supaya cek "unit sedang breakdown" ikut berlaku. */
    protected function breakdowns(): void
    {
        $rows = [
            ['1504', '2026-09-28 13:00', '2026-10-01 10:00', 'Ganti ban belakang', 'Ban diganti 2 pcs', 3500000],
            ['1503', '2026-10-02 08:00', null, 'Kampas rem aus', null, 0],
            ['1506', '2026-10-18 07:00', '2026-10-18 17:00', 'Service berkala 10.000 km', 'Ganti oli & filter', 2750000],
            ['1502', '2026-10-22 09:00', '2026-10-24 15:00', 'Radiator bocor', 'Radiator diganti', 1850000],
        ];
        foreach ($rows as [$unit, $start, $end, $desc, $action, $cost]) {
            Breakdown::create([
                'unit_id' => $this->units[$unit]->id,
                'started_at' => $start,
                'finished_at' => $end,
                'description' => $desc,
                'action_taken' => $action,
                'cost' => $cost,
            ]);
        }
    }

    /** 32 trip 1–7 Oktober 2026, total per unit sama dengan dashboard lama. */
    protected function octoberFirstWeek(Customer $customer, HaulRoute $route, User $checker): void
    {
        $make = function (string $unit, string $date, float $ton, ?string $ticket = null) use ($customer, $route, $checker) {
            static $seq = 0;
            $seq++;
            Trip::create([
                'trip_date' => $date,
                'unit_id' => $this->units[$unit]->id,
                'driver_id' => $this->drivers[$unit]->id,
                'customer_id' => $customer->id,
                'haul_route_id' => $route->id,
                'ticket_number' => $ticket ?? 'CE'.date('ymd', strtotime($date)).str_pad((string) (200000 + $seq * 7919 % 99999), 6, '0', STR_PAD_LEFT),
                'tonnage' => $ton,
                'status' => 'verified',
                'verified_at' => $date.' 18:00:00',
                'verified_by' => $checker->id,
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
    }

    /** Trip harian acak (tetap sama tiap seeding) untuk unit yang tersedia pada tanggal itu. */
    protected function tripsBetween(string $from, string $to, Customer $customer, HaulRoute $route, array $units, string $status, User $checker, int $maxRit = 2): void
    {
        for ($d = Carbon::parse($from); $d->lte(Carbon::parse($to)); $d->addDay()) {
            $date = $d->toDateString();
            foreach ($units as $no) {
                $unit = $this->units[$no];
                if ($unit->breakdownOn($date)) {
                    continue;
                }
                $rit = mt_rand(0, 9) < 2 ? 0 : mt_rand(1, $maxRit); // ±20% hari libur / antre
                for ($k = 0; $k < $rit; $k++) {
                    $this->ticketSeq++;
                    $std = (float) $route->std_fuel_liters;
                    Trip::create([
                        'trip_date' => $date,
                        'unit_id' => $unit->id,
                        'driver_id' => $this->drivers[$no]->id,
                        'customer_id' => $customer->id,
                        'haul_route_id' => $route->id,
                        'ticket_number' => 'CE'.$d->format('ymd').$no.str_pad((string) $this->ticketSeq, 4, '0', STR_PAD_LEFT),
                        'tonnage' => round(38 + mt_rand(0, 900) / 100, 2),
                        // Sebagian trip punya data solar aktual; beberapa sengaja boros (> 5% di atas standar).
                        'fuel_actual_liters' => $this->ticketSeq % 5 === 0 ? $std + mt_rand(-4, 6) : null,
                        'status' => $status,
                        'verified_at' => $status === 'verified' ? $date.' 18:00:00' : null,
                        'verified_by' => $status === 'verified' ? $checker->id : null,
                    ]);
                }
            }
        }
    }

    protected function pay(Invoice $invoice, string $date, float $amount, string $reference): void
    {
        Payment::create([
            'invoice_id' => $invoice->id,
            'paid_at' => $date,
            'amount' => $amount,
            'method' => 'transfer',
            'reference' => $reference,
        ]);
    }
}
