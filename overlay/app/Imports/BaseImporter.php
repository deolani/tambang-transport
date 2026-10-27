<?php

namespace App\Imports;

use App\Models\Customer;
use App\Models\Driver;
use App\Models\HaulRoute;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Dasar semua importer. Satu importer = satu jenis data.
 * Setiap baris diproses di savepoint sendiri, jadi baris yang error tidak merusak baris lain.
 */
abstract class BaseImporter
{
    abstract public static function key(): string;

    abstract public static function label(): string;

    abstract public static function description(): string;

    /**
     * Kolom template: key => [label, wajib?, contoh, keterangan]
     *
     * @return array<string, array{0:string,1:bool,2:mixed,3:string}>
     */
    abstract public static function columns(): array;

    /** Simpan satu baris. Kembalikan 'created' atau 'updated'. Lempar ImportRowException bila salah. */
    abstract protected function handle(array $row): string;


    /**
     * Nama kolom lain yang juga diterima: alias => key resmi.
     * Supaya file rekap yang sudah ada (mis. "Kode Unit", "No. Tiket") tetap terbaca.
     */
    public static function aliases(): array
    {
        return [];
    }

    /** Ubah nama kolom alias menjadi key resmi. */
    public static function applyAliases(array $data): array
    {
        foreach (static::aliases() as $alias => $key) {
            if (array_key_exists($alias, $data) && ! array_key_exists($key, $data)) {
                $data[$key] = $data[$alias];
            }
        }

        return $data;
    }

    /** Kolom template yang tidak ditemukan di header file (untuk peringatan). */
    public static function missingRequiredHeaders(array $rows): array
    {
        $first = static::applyAliases($rows[0]['data'] ?? []);
        $missing = [];
        foreach (static::columns() as $key => [$label, $required]) {
            if ($required && ! array_key_exists($key, $first)) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    /** Kolom yang ditampilkan di pratinjau. */
    public static function previewKeys(): array
    {
        return array_slice(array_keys(static::columns()), 0, 4);
    }

    /**
     * Jalankan import. $dryRun = true -> semua perubahan dibatalkan (untuk tombol "Cek data").
     *
     * @return array{total:int, created:int, updated:int, failed:int, rows:array}
     */
    public function run(array $rows, bool $dryRun, bool $skipErrors = true): array
    {
        $result = ['total' => count($rows), 'created' => 0, 'updated' => 0, 'failed' => 0, 'rows' => []];

        DB::beginTransaction();
        try {
            foreach ($rows as $r) {
                $r['data'] = static::applyAliases($r['data']);
                $preview = collect(static::previewKeys())->mapWithKeys(fn ($k) => [$k => static::display($r['data'][$k] ?? null)])->all();
                try {
                    $this->checkRequired($r['data']);
                    $status = DB::transaction(fn () => $this->handle($r['data']));
                    $result[$status]++;
                    $result['rows'][] = ['line' => $r['line'], 'ok' => true, 'message' => $status === 'created' ? 'Data baru' : 'Diperbarui', 'values' => $preview];
                } catch (Throwable $e) {
                    $result['failed']++;
                    $result['rows'][] = ['line' => $r['line'], 'ok' => false, 'message' => static::friendlyError($e), 'values' => $preview];
                }
            }

            if ($dryRun || (! $skipErrors && $result['failed'] > 0)) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $result;
    }

    protected function checkRequired(array $row): void
    {
        $missing = [];
        foreach (static::columns() as $key => [$label, $required]) {
            if ($required && (! array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '')) {
                $missing[] = $label;
            }
        }
        if ($missing) {
            throw new ImportRowException('Kolom wajib kosong / tidak ada: '.implode(', ', $missing));
        }
    }

    protected static function friendlyError(Throwable $e): string
    {
        if ($e instanceof ImportRowException) {
            return $e->getMessage();
        }
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return collect($e->errors())->flatten()->implode(' ');
        }
        if ($e instanceof \Illuminate\Database\QueryException) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'unique') || str_contains($msg, 'Duplicate')) {
                return 'Data ganda: nilai unik (kode / no polisi / NIK / no tiket) sudah dipakai data lain.';
            }

            return 'Kesalahan database: '.str($msg)->before('(Connection')->limit(160);
        }

        return str($e->getMessage())->limit(200)->toString();
    }

    public static function display($v): string
    {
        if ($v instanceof \DateTimeInterface) {
            return $v->format('d/m/Y');
        }

        return str((string) $v)->limit(30)->toString();
    }

    // ===================================================== Parser nilai

    public static function str($v): ?string
    {
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }

    /** Terima 46,64 · 46.64 · 61.200 · Rp 61.200 · 1.234.567,89 · angka Excel. */
    public static function num($v, string $label = 'Angka'): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $s = preg_replace('/(rp|idr|\s|ton|liter|km|l\b|t\b)/i', '', (string) $v);
        $s = rtrim($s, '.');
        if ($s === '' || $s === '-') {
            return null;
        }
        if (str_contains($s, ',')) {
            // format Indonesia: titik = ribuan, koma = desimal
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $s)) {
            $s = str_replace('.', '', $s); // 61.200 -> 61200
        }
        if (! is_numeric($s)) {
            throw new ImportRowException("{$label} tidak valid: \"{$v}\"");
        }

        return (float) $s;
    }

    /** Terima tanggal Excel, 2026-10-03, 03/10/2026, 03-10-2026, 3/10/26, dengan / tanpa jam. */
    public static function date($v, string $label = 'Tanggal', bool $withTime = false): ?Carbon
    {
        if ($v === null || $v === '') {
            return null;
        }
        if ($v instanceof \DateTimeInterface) {
            return Carbon::instance($v);
        }
        if (is_numeric($v) && (float) $v > 20000 && (float) $v < 80000) {
            // nomor seri tanggal Excel
            return Carbon::create(1899, 12, 30)->addSeconds((int) round(((float) $v) * 86400));
        }
        $s = trim((string) $v);
        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'd-m-Y H:i', 'd-m-Y',
            'd.m.Y', 'Y/m/d', 'd/m/y', 'd-m-y', 'j/n/Y', 'j-n-Y'];
        foreach ($formats as $f) {
            $d = \DateTime::createFromFormat('!'.$f, $s);
            $errors = \DateTime::getLastErrors();
            if ($d && (! $errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return Carbon::instance($d);
            }
        }
        throw new ImportRowException("{$label} tidak dikenali: \"{$s}\" (pakai format 2026-10-03 atau 03/10/2026)");
    }

    public static function bool($v, bool $default = false): bool
    {
        if ($v === null || $v === '') {
            return $default;
        }
        if (is_bool($v)) {
            return $v;
        }

        return in_array(strtolower(trim((string) $v)), ['1', 'ya', 'y', 'yes', 'true', 'pkp', 'aktif'], true);
    }

    /** Ambil nilai dari daftar pilihan (huruf besar/kecil diabaikan). */
    public static function choice($v, array $allowed, string $default, string $label): string
    {
        $s = strtolower(trim((string) $v));
        if ($s === '') {
            return $default;
        }
        if (! in_array($s, $allowed, true)) {
            throw new ImportRowException("{$label} harus salah satu: ".implode(' / ', $allowed)." (isi: \"{$v}\")");
        }

        return $s;
    }

    // ===================================================== Pencarian master

    protected static function normKey($v): string
    {
        return strtoupper(preg_replace('/[\s\-]+/', '', (string) $v));
    }

    public static function findUnit($code): Unit
    {
        $unit = Unit::whereRaw("upper(replace(replace(code, ' ', ''), '-', '')) = ?", [static::normKey($code)])->first();
        if (! $unit) {
            throw new ImportRowException("Unit \"{$code}\" tidak ada di Master Data > Unit.");
        }

        return $unit;
    }

    public static function findDriver($name): Driver
    {
        $driver = Driver::whereRaw('lower(name) = ?', [strtolower(trim((string) $name))])->first();
        if (! $driver) {
            throw new ImportRowException("Sopir \"{$name}\" tidak ada di Master Data > Sopir.");
        }

        return $driver;
    }

    public static function findCustomer($codeOrName): Customer
    {
        $s = trim((string) $codeOrName);
        $c = Customer::whereRaw('upper(code) = ?', [strtoupper($s)])->first()
            ?? Customer::whereRaw('lower(name) = ?', [strtolower($s)])->first();
        if (! $c) {
            throw new ImportRowException("Customer \"{$s}\" tidak ada di Master Data > Customer (isi kode atau nama).");
        }

        return $c;
    }

    public static function findRoute($code): HaulRoute
    {
        $r = HaulRoute::whereRaw("upper(replace(replace(code, ' ', ''), '-', '')) = ?", [static::normKey($code)])->first();
        if (! $r) {
            throw new ImportRowException("Rute \"{$code}\" tidak ada di Master Data > Rute.");
        }

        return $r;
    }
}
