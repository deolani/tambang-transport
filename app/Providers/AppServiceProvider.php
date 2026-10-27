<?php

namespace App\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Zona waktu & bahasa Indonesia untuk tanggal dan nama bulan.
        date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Jakarta'));
        config(['app.timezone' => env('APP_TIMEZONE', 'Asia/Jakarta')]);
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale', 'id'));
    }
}
