<?php
/**
 * Update aplikasi Tambang Transport Abadi TANPA menghapus data.
 *
 *     php update.php
 *
 *  1. Menyalin kode terbaru (folder overlay/) ke proyek yang sudah terpasang
 *  2. Menjalankan migrasi tabel baru (data lama tetap)
 *  3. Membersihkan cache
 *  4. (Opsional) membuat akun Super Admin
 */

const DS = DIRECTORY_SEPARATOR;
$here = __DIR__;

function line(string $t = ''): void { echo $t.PHP_EOL; }
function title(string $t): void { line(); line("== {$t} =="); }
function fail(string $t): never { line("GAGAL: {$t}"); exit(1); }
function ask(string $q, string $default = ''): string
{
    echo $q.($default !== '' ? " [{$default}]" : '').': ';
    $v = trim((string) fgets(STDIN));
    return $v === '' ? $default : $v;
}
function run(string $cmd, string $cwd): void
{
    line("> {$cmd}");
    $prev = getcwd();
    chdir($cwd);
    passthru($cmd, $code);
    chdir($prev);
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

title('Update aplikasi (data tidak dihapus)');
$name = ask('Nama folder proyek yang sudah terpasang', 'tambang-transport');
$project = dirname($here).DS.$name;
if (! file_exists($project.DS.'artisan')) {
    fail("proyek tidak ditemukan di {$project}. Jalankan install.php untuk pemasangan pertama.");
}

title('1. Menyalin kode terbaru');
line(copyDir($here.DS.'overlay', $project).' file disalin.');

title('2. Migrasi tabel baru');
run('php artisan migrate --force', $project);

title('3. Bersihkan cache');
run('php artisan optimize:clear', $project);
run('php artisan filament:assets', $project);

title('4. Akun Super Admin');
$yn = strtolower(ask('Buat / tetapkan akun Super Admin sekarang? (y/n)', 'y'));
if ($yn === 'y' || $yn === 'ya') {
    run('php artisan hauling:super-admin', $project);
}

title('SELESAI');
line('Jalankan lagi aplikasi:');
line("   cd {$project}");
line('   php artisan serve --host=0.0.0.0 --port=8000 --no-reload');
line('Login sebagai Super Admin, lalu buka Pengaturan > Role & Hak Akses.');
