<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder bawaan (`php artisan db:seed` / `migrate:fresh --seed`): hanya akun user,
 * aplikasi mulai kosong.
 *
 * Untuk data contoh lengkap:
 *   php artisan migrate:fresh --seeder=SampleDataSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(UserSeeder::class);
    }
}
