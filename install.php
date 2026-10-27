<?php
/**
 * Installer aplikasi Tambang Transport Abadi (Laravel 12 + Filament 3 + PostgreSQL)
 *
 * Cara pakai (Windows / Mac / Linux), dari folder hasil ekstrak zip:
 *     php install.php
 *
 * Yang dilakukan:
 *  1. Cek PHP & ekstensi, cek Composer
 *  2. Membuat proyek Laravel baru + memasang Filament
 *  3. Menyalin kode aplikasi (folder overlay/) ke proyek
 *  4. Menanyakan koneksi PostgreSQL, membuat database bila belum ada, menulis .env
 *  5. Migrasi tabel + isi data contoh 1–7 Oktober 2026
 */

const DS = DIRECTORY_SEPARATOR;
$here = __DIR__;

function line(string $t = ''): void { echo $t.PHP_EOL; }
function title(string $t): void { line(); line("\033[1;33m== {$t} ==\033[0m"); }
function fail(string $t): never { line("\033[1;31mGAGAL: {$t}\033[0m"); exit(1); }
function ask(string $q, string $default = ''): string
{
    echo $q.($default !== '' ? " [{$default}]" : '').': ';
    $v = trim((string) fgets(STDIN));
    return $v === '' ? $default : $v;
}
function run(string $cmd, ?string $cwd = null): void
{
    line("> {$cmd}");
    $prev = getcwd();
    if ($cwd) { chdir($cwd); }
    passthru($cmd, $code);
    if ($cwd) { chdir($prev); }
    if ($code !== 0) { fail("perintah gagal (kode {$code}): {$cmd}"); }
}
function copyDir(string $src, string $dst): int
{
    $n = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $item) {
        $target = $dst.DS.substr($item->getPathname(), strlen($src) + 1);
        if ($item->isDir()) {
            if (! is_dir($target)) { mkdir($target, 0775, true); }
        } else {
            if (! is_dir(dirname($target))) { mkdir(dirname($target), 0775, true); }
            copy($item->getPathname(), $target);
            $n++;
        }
    }
    return $n;
}
function setEnv(string $file, array $values): void
{
    $env = file_exists($file) ? file_get_contents($file) : '';
    foreach ($values as $key => $val) {
        $val = (string) $val;
        if ($val === '' ) {
            $out = '';
        } elseif (preg_match('/[\s#"\'=$]/', $val)) {
            $out = '"'.str_replace('"', '\"', $val).'"';
        } else {
            $out = $val;
        }
        $pattern = '/^#?\s*'.preg_quote($key, '/').'=.*$/m';
        if (preg_match($pattern, $env)) {
            $env = preg_replace($pattern, $key.'='.str_replace('$', '\$', $out), $env, 1);
        } else {
            $env .= PHP_EOL.$key.'='.$out;
        }
    }
    file_put_contents($file, $env);
}

// ---------------------------------------------------------------- 1. Cek
title('1. Cek PHP & Composer');
if (PHP_VERSION_ID < 80200) { fail('butuh PHP 8.2 atau lebih baru. Versi Anda: '.PHP_VERSION); }
line('PHP '.PHP_VERSION.' OK');
$missing = array_filter(['pdo_pgsql', 'intl', 'mbstring', 'zip', 'fileinfo', 'openssl'], fn ($e) => ! extension_loaded($e));
if ($missing) {
    fail('ekstensi PHP belum aktif: '.implode(', ', $missing).".\n"
        .'Buka php.ini (lihat: php --ini), hapus tanda ; di depan extension='.implode(' / extension=', $missing).', lalu jalankan ulang.');
}
line('Ekstensi PHP OK');
exec('composer --version 2>&1', $o, $c);
if ($c !== 0) { fail('Composer belum terpasang. Unduh di https://getcomposer.org lalu jalankan ulang.'); }
line($o[0] ?? 'Composer OK');

// ---------------------------------------------------------------- 2. Proyek
title('2. Membuat proyek Laravel + Filament');
$name = ask('Nama folder proyek', 'tambang-transport');
$project = dirname($here).DS.$name;
if (is_dir($project.DS.'vendor')) {
    line("Folder {$project} sudah ada, lewati pembuatan proyek.");
} else {
    run('composer create-project laravel/laravel "'.$name.'" "^12.0" --no-interaction --prefer-dist', dirname($here));
}
if (! is_dir($project.DS.'vendor'.DS.'filament')) {
    run('composer require filament/filament:"^3.3" -W --no-interaction', $project);
}

// ---------------------------------------------------------------- 3. Salin kode
title('3. Menyalin kode aplikasi');
$n = copyDir($here.DS.'overlay', $project);
line("{$n} file disalin.");

// ---------------------------------------------------------------- 4. Database
title('4. Koneksi PostgreSQL');
$host = ask('Host', '127.0.0.1');
$port = ask('Port', '5432');
$db = ask('Nama database', 'tambang_transport');
$user = ask('Username', 'postgres');
$pass = ask('Password', '');

try {
    $pdo = new PDO("pgsql:host={$host};port={$port};dbname=postgres", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $exists = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
    $exists->execute([$db]);
    if (! $exists->fetchColumn()) {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $db)) { fail('nama database hanya boleh huruf, angka, garis bawah.'); }
        $pdo->exec('CREATE DATABASE "'.$db.'" ENCODING \'UTF8\'');
        line("Database {$db} dibuat.");
    } else {
        line("Database {$db} sudah ada, dipakai.");
    }
} catch (PDOException $e) {
    fail('tidak bisa terhubung ke PostgreSQL: '.$e->getMessage());
}

setEnv($project.DS.'.env', [
    'APP_NAME' => 'Tambang Transport Abadi',
    'APP_URL' => 'http://localhost:8000',
    'APP_LOCALE' => 'id',
    'APP_FALLBACK_LOCALE' => 'en',
    'APP_FAKER_LOCALE' => 'id_ID',
    'APP_TIMEZONE' => 'Asia/Jakarta',
    'DB_CONNECTION' => 'pgsql',
    'DB_HOST' => $host,
    'DB_PORT' => $port,
    'DB_DATABASE' => $db,
    'DB_USERNAME' => $user,
    'DB_PASSWORD' => $pass,
]);
line('.env diperbarui.');

// ---------------------------------------------------------------- 5. Migrasi
title('5. Membuat tabel & data contoh');
run('php artisan config:clear', $project);
run('php artisan filament:assets', $project);
run('php artisan migrate --force', $project);
run('php artisan db:seed --force', $project);

title('SELESAI');
line("Jalankan aplikasi:");
line("   cd {$project}");
line('   php artisan serve --host=0.0.0.0 --port=8000');
line();
line('Buka di laptop : http://localhost:8000');
line('Buka di HP     : http://<IP-laptop>:8000   (HP & laptop di WiFi yang sama)');
line();
line('Login: superadmin@tta.test / password  (atur hak akses)  ·  owner@tta.test / password');
line('Akun lain: admin@, checker@, keuangan@tta.test (password sama).');
line('Segera ganti password di menu Pengaturan > User & Role.');
