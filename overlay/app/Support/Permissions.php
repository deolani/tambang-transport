<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;

/**
 * Daftar semua hak akses aplikasi + pengecekan.
 *
 * Kunci hak akses: "<modul>.<aksi>", misalnya "trips.verify".
 * Role "super_admin" selalu punya semua hak akses dan tidak bisa diubah.
 */
class Permissions
{
    public const SUPER_ADMIN = 'super_admin';

    /** @var array<string, array<int,string>> cache per request: role => permissions */
    protected static array $cache = [];

    /**
     * Modul => [label modul, [aksi => label aksi]]
     */
    public static function groups(): array
    {
        $crud = ['view' => 'Lihat', 'create' => 'Tambah', 'edit' => 'Edit', 'delete' => 'Hapus'];

        return [
            'dashboard' => ['Dashboard', [
                'receivable' => 'Lihat grafik umur piutang',
            ]],
            'trips' => ['Operasional › Trip / Ritase', [
                'view' => 'Lihat',
                'create' => 'Input trip',
                'edit' => 'Edit trip DRAFT',
                'delete' => 'Hapus trip DRAFT',
                'verify' => 'Verifikasi trip',
                'edit_verified' => 'Edit trip yang sudah VERIFIED',
                'export' => 'Export CSV',
            ]],
            'import' => ['Operasional › Upload / Import Data', [
                'units' => 'Import Unit',
                'drivers' => 'Import Sopir',
                'customers' => 'Import Customer',
                'routes' => 'Import Rute',
                'tariffs' => 'Import Tarif',
                'fuel_prices' => 'Import Harga Solar',
                'trips' => 'Import Trip',
                'breakdowns' => 'Import Breakdown',
            ]],
            'units' => ['Master Data › Unit', $crud],
            'drivers' => ['Master Data › Sopir', $crud],
            'customers' => ['Master Data › Customer', $crud],
            'routes' => ['Master Data › Rute', $crud],
            'tariffs' => ['Master Data › Tarif', $crud],
            'fuel_prices' => ['Master Data › Harga Solar', $crud],
            'invoices' => ['Keuangan › Invoice', [
                'view' => 'Lihat',
                'create' => 'Buat invoice',
                'cancel' => 'Batalkan invoice (belum dibayar)',
            ]],
            'payments' => ['Keuangan › Pembayaran', [
                'view' => 'Lihat',
                'create' => 'Catat pembayaran',
                'delete' => 'Hapus pembayaran',
            ]],
            'breakdowns' => ['Armada › Breakdown', $crud],
            'users' => ['Pengaturan › User', $crud],
        ];
    }

    /** Semua kunci hak akses. */
    public static function all(): array
    {
        $out = [];
        foreach (static::groups() as $module => [, $actions]) {
            foreach (array_keys($actions) as $action) {
                $out[] = "{$module}.{$action}";
            }
        }

        return $out;
    }

    /** Hak akses bawaan setiap role (sama dengan pembagian awal aplikasi). */
    public static function defaults(): array
    {
        $crud = fn (string $m, array $actions = ['view', 'create', 'edit', 'delete']) => array_map(fn ($a) => "{$m}.{$a}", $actions);

        return [
            'owner' => [
                'name' => 'Owner',
                'description' => 'Pemilik usaha, akses ke semua menu.',
                'permissions' => static::all(),
            ],
            'admin' => [
                'name' => 'Admin Operasional',
                'description' => 'Input trip, master data operasional dan breakdown.',
                'permissions' => array_merge(
                    $crud('trips', ['view', 'create', 'edit', 'delete', 'export']),
                    $crud('import', ['units', 'drivers', 'customers', 'routes', 'trips', 'breakdowns']),
                    $crud('units'), $crud('drivers'), $crud('customers'), $crud('routes'),
                    ['tariffs.view', 'fuel_prices.view'],
                    $crud('breakdowns'),
                ),
            ],
            'checker' => [
                'name' => 'Checker / Verifikator',
                'description' => 'Mencocokkan tiket timbang dan memverifikasi trip.',
                'permissions' => array_merge(
                    ['trips.view', 'trips.verify', 'trips.export'],
                    ['units.view', 'drivers.view', 'customers.view', 'routes.view', 'breakdowns.view'],
                ),
            ],
            'keuangan' => [
                'name' => 'Keuangan',
                'description' => 'Tarif, harga solar, invoice dan pembayaran.',
                'permissions' => array_merge(
                    ['dashboard.receivable', 'trips.view', 'trips.export'],
                    $crud('import', ['customers', 'tariffs', 'fuel_prices']),
                    ['units.view', 'drivers.view', 'routes.view', 'breakdowns.view'],
                    $crud('customers'), $crud('tariffs'), $crud('fuel_prices'),
                    ['invoices.view', 'invoices.create'],
                    $crud('payments', ['view', 'create', 'delete']),
                ),
            ],
        ];
    }

    public static function isSuperAdmin(?User $user): bool
    {
        return $user?->role === self::SUPER_ADMIN;
    }

    /** Apakah user boleh melakukan aksi ini? */
    public static function can(?User $user, string $permission): bool
    {
        if (! $user || ! $user->is_active) {
            return false;
        }
        if (static::isSuperAdmin($user)) {
            return true;
        }

        return in_array($permission, static::forRole($user->role), true);
    }

    /** Boleh salah satu dari daftar hak akses. */
    public static function canAny(?User $user, array $permissions): bool
    {
        foreach ($permissions as $p) {
            if (static::can($user, $p)) {
                return true;
            }
        }

        return false;
    }

    public static function forRole(?string $role): array
    {
        if (! $role) {
            return [];
        }

        return static::$cache[$role] ??= (array) (Role::where('key', $role)->value('permissions') ?? []);
    }

    public static function flush(): void
    {
        static::$cache = [];
    }
}
