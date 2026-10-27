<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Console\Command;

/**
 * Buat atau jadikan user sebagai Super Admin.
 *   php artisan hauling:super-admin
 */
class MakeSuperAdmin extends Command
{
    protected $signature = 'hauling:super-admin {email?} {--name=} {--password=}';

    protected $description = 'Buat akun Super Admin (atau ubah user yang ada menjadi Super Admin)';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Email Super Admin', 'superadmin@tta.test');
        $user = User::where('email', $email)->first();

        if ($user) {
            $user->update(['role' => Permissions::SUPER_ADMIN, 'is_active' => true]);
            if ($this->confirm('User sudah ada. Ganti password-nya juga?', false)) {
                $user->update(['password' => $this->askPassword()]);
            }
            $this->info("{$email} sekarang berperan sebagai Super Admin.");

            return self::SUCCESS;
        }

        $name = $this->option('name') ?: $this->ask('Nama', 'Super Admin');
        $password = $this->option('password') ?: $this->askPassword();

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => Permissions::SUPER_ADMIN,
            'is_active' => true,
        ]);
        $this->info("Super Admin {$email} dibuat. Login lalu buka Pengaturan › Role & Hak Akses.");

        return self::SUCCESS;
    }

    protected function askPassword(): string
    {
        while (true) {
            $p = (string) $this->secret('Password (min. 8 karakter, tidak tampil saat diketik)');
            if (strlen($p) < 8) {
                $this->error('Minimal 8 karakter.');

                continue;
            }
            if ($p !== (string) $this->secret('Ulangi password')) {
                $this->error('Password tidak sama, ulangi.');

                continue;
            }

            return $p;
        }
    }
}
