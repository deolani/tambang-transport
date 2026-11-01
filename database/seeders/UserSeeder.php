<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Akun awal saja (role & hak akses sudah dibuat oleh migrasi).
 * Password semua akun: "password" — segera ganti di Pengaturan › User.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
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
    }
}
