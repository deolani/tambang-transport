# Tambang Transport Abadi — Aplikasi Hauling Batubara

Laravel 12 + Filament 3 + PostgreSQL. Tampilan responsif (laptop, tablet, HP) dengan dashboard grafik berwarna.

## Yang dibutuhkan di komputer

| Kebutuhan | Cek dengan |
|---|---|
| PHP 8.2 atau lebih baru | `php -v` |
| Ekstensi PHP: `pdo_pgsql`, `intl`, `zip`, `mbstring`, `fileinfo`, `openssl` | `php -m` |
| Composer | `composer --version` |
| PostgreSQL (sudah ada) | — |

> **Windows (XAMPP / Laragon):** buka `php.ini` (lokasinya terlihat dari `php --ini`), lalu hapus tanda `;` di depan
> `extension=pdo_pgsql`, `extension=pgsql`, `extension=intl`, `extension=zip`. Simpan, tutup dan buka lagi terminal.

## Instalasi (sekali saja)

1. Ekstrak zip ini, misalnya ke `D:\proyek\`.
2. Buka terminal / Command Prompt di folder hasil ekstrak (`tambang-transport-installer`).
3. Jalankan:

   ```
   php install.php
   ```

4. Ikuti pertanyaannya (nama folder, host, port, nama database, user & password PostgreSQL).
   Installer akan mengunduh Laravel & Filament, menyalin kode, membuat database bila belum ada,
   membuat tabel, dan mengisi data contoh 1–7 Oktober 2026.

## Menjalankan aplikasi

```
cd ..\tambang-transport        (Mac/Linux: cd ../tambang-transport)
php artisan serve --host=0.0.0.0 --port=8000
```

- Laptop: <http://localhost:8000>
- **HP**: sambungkan HP ke WiFi yang sama dengan laptop, lalu buka `http://IP-LAPTOP:8000`.
  IP laptop bisa dilihat dengan `ipconfig` (Windows, baris *IPv4 Address*) atau `ifconfig` / `ip a` (Mac/Linux).
  Bila tidak terbuka, izinkan PHP di Windows Firewall.
- Di HP, pilih menu browser **Tambahkan ke layar utama** supaya terasa seperti aplikasi.

## Akun contoh (password semua: `password`)

| Email | Role | Bisa |
|---|---|---|
| owner@tta.test | Owner | Semua menu |
| admin@tta.test | Admin operasional | Input trip, master data, breakdown |
| checker@tta.test | Checker | Verifikasi trip |
| keuangan@tta.test | Keuangan | Tarif, harga solar, invoice, pembayaran |

Ganti password di **Pengaturan › User** sebelum dipakai sungguhan. Ada juga `superadmin@tta.test` (Super Admin, pengatur hak akses).

## Isi aplikasi

- **Dashboard** — filter tanggal & customer; 8 kartu KPI (revenue, HPP, profit + margin, tonase, rit,
  ton/rit, HPP/ton, unit jalan) dengan perbandingan periode sebelumnya; grafik tren bulanan,
  komposisi HPP, revenue vs HPP per unit, HPP/ton per unit, tonase harian, tonase per sopir,
  umur piutang; kotak peringatan; tabel performa unit.
- **Operasional › Trip** — input trip (tarif, solar, uang jalan dan margin dihitung otomatis),
  tab *Belum verifikasi*, verifikasi satu-satu atau massal, export CSV sesuai filter.
- **Master Data** — Unit, Sopir, Customer, Rute (jarak, solar standar, uang jalan), Tarif (bertanggal), Harga Solar.
- **Keuangan** — Invoice dibuat otomatis dari trip VERIFIED per customer & periode (PPN / PPh 23 opsional),
  pembayaran sebagian/lunas, status piutang.
- **Armada › Breakdown** — lapor kerusakan, unit otomatis berstatus Breakdown dan hilang dari pilihan trip,
  downtime dihitung otomatis, biaya masuk HPP unit.
- **Pengaturan › User** dan **Pengaturan › Role & Hak Akses** (khusus Super Admin).
- **Operasional › Upload / Import Data** — lihat bagian di bawah.

## Super Admin & Hak Akses

- Akun **Super Admin** (`superadmin@tta.test` / `password` pada instalasi baru) selalu punya semua akses
  dan satu-satunya yang melihat menu **Pengaturan › Role & Hak Akses**.
- Di menu itu Super Admin bisa:
  - **Atur hak akses** tiap role dengan mencentang aksi per menu (Lihat / Tambah / Edit / Hapus, Verifikasi,
    Edit trip VERIFIED, Export, Import per jenis data, Buat / Batalkan invoice, grafik piutang).
  - **Tambah role baru** (misalnya "Supervisor Lapangan"), bisa menyalin hak akses dari role lain sebagai awal.
  - Hapus role buatan sendiri yang tidak dipakai user mana pun.
- Menu tanpa centang **Lihat** otomatis hilang dari role itu. Perubahan berlaku saat user membuka halaman berikutnya.
- Pengaman: role Super Admin tidak bisa diubah/dihapus; user biasa tidak bisa memberi role Super Admin
  atau mengubah akun Super Admin; role Owner tidak bisa dihapus.
- Membuat / menjadikan Super Admin lewat terminal: `php artisan hauling:super-admin`

## Update versi baru (data tidak hilang)

Ekstrak zip versi baru, buka terminal di folder installer, lalu:

```
php update.php
```

Skrip ini menyalin kode baru, menambah tabel baru, membersihkan cache, dan menawarkan pembuatan akun Super Admin.
**Jangan** memakai `install.php` untuk update.

## Upload / Import Data

1. **Pilih jenis data**: Unit, Sopir, Customer, Rute, Tarif, Harga Solar, Trip, atau Breakdown.
   Daftar kolom yang dibutuhkan langsung tampil.
2. Klik **Unduh template** bila belum punya file (Excel, kolom wajib bertanda `*`, baris 2 berisi contoh).
3. **Pilih file** `.xlsx` atau `.csv` (maks 5.000 baris / 10 MB).
4. Klik **Cek data** — sistem mensimulasikan import tanpa menyimpan apa pun, lalu menampilkan
   jumlah data baru / diperbarui / error dan keterangan error per baris.
5. Klik **Proses import** untuk menyimpan. Riwayat import tercatat di bagian bawah halaman.

Catatan:
- Untuk import pertama, ikuti urutan nomor (master data dulu, baru Trip).
- Data yang sudah ada **diperbarui**, bukan digandakan: unit dicocokkan dengan kode, sopir dengan NIK/nama,
  customer dengan kode, rute dengan kode, tarif dengan customer + rute + tanggal mulai, trip dengan **no tiket**.
- Angka format Indonesia (`46,64`, `61.200`, `Rp 61.200`) dan tanggal `2026-10-03` / `03/10/2026` / tanggal Excel diterima.
- Nama kolom fleksibel: misalnya `Kode Unit`, `No. Tiket`, `Netto`, `Nama Sopir` juga dikenali.
- Import trip menghitung tarif, solar, uang jalan dan HPP otomatis. Trip baru berstatus DRAFT
  (Owner/Checker boleh mengisi kolom Status = `verified`). Trip yang sudah ditagih tidak bisa diubah lewat import.
- Opsi **Lewati baris yang error** (default aktif): baris yang benar tetap disimpan. Bila dimatikan, satu error = semua batal.
- File contoh untuk dicoba: `contoh-file/contoh-import-trip.csv` (3 baris benar, 1 baris sengaja salah).

### Alur status trip

`DRAFT` (admin input) → `VERIFIED` (checker) → `INVOICED` (masuk invoice, terkunci) → `PAID` (invoice lunas).
Trip VERIFIED hanya bisa diedit Owner. Membatalkan invoice mengembalikan trip ke VERIFIED.

### Rumus

| Indikator | Rumus |
|---|---|
| Revenue | tonase × tarif per ton |
| Biaya solar | solar standar rute × harga solar per liter |
| HPP | biaya solar + uang jalan + premi sopir (+ biaya breakdown di periode) |
| Profit | revenue − HPP |
| HPP/ton, Solar/ton, Ton/KM | dibagi tonase / KM |
| Hari jalan | jumlah tanggal unik unit punya trip |

Data contoh memberi angka yang sama dengan dashboard lama untuk 1–7 Okt 2026:
revenue Rp 84.392.964 · HPP Rp 34.048.000 · 32 rit · 1.378,97 ton.

## Lokasi kode penting

| File | Isi |
|---|---|
| `app/Models/Trip.php` | Penomoran trip & hitung otomatis revenue / HPP |
| `app/Support/HaulingMetrics.php` | Semua rumus dashboard |
| `app/Filament/Widgets/` | Kartu KPI, grafik, tabel, peringatan |
| `app/Filament/Resources/` | Menu & form setiap modul |
| `app/Filament/Pages/ImportData.php` | Halaman upload / import |
| `app/Imports/Importers/` | Aturan import per jenis data (kolom, pencocokan, validasi) |
| `database/seeders/UserSeeder.php` | Akun awal (dipakai `db:seed` bawaan) |
| `database/seeders/SampleDataSeeder.php` | Data contoh lengkap (silakan ubah/ganti) |

## Mengisi database

Kedua perintah `migrate:fresh` di bawah **menghapus semua data** lalu membuat tabel dari nol.

| Kebutuhan | Perintah |
|---|---|
| Mulai kosong, hanya akun user | `php artisan migrate:fresh --seed` |
| Isi data contoh lengkap | `php artisan migrate:fresh --seeder=SampleDataSeeder` |
| Tambah akun awal tanpa menghapus data | `php artisan db:seed` |

Akun yang dibuat: `superadmin@`, `owner@`, `admin@`, `checker@`, `keuangan@tta.test`, password `password`.

Isi data contoh (15 Sep – 31 Okt 2026):
- 8 unit (1 nonaktif), 7 sopir (Rahmat dengan premi per ton), 2 customer (WHL per ton, BCT borongan per rit),
  2 rute, harga solar naik per 15 Okt.
- ±370 trip: Lunas, Ditagih, Verified belum ditagih, dan Draft 29–31 Okt; sebagian berisi solar aktual.
- 4 breakdown (DT 1503 masih proses), 5 invoice: lunas, dibayar sebagian, belum lunas, lewat jatuh tempo, batal.

## Nanti saat upload ke Hostinger

Hosting web Hostinger (Premium/Business) memakai **MySQL**, bukan PostgreSQL. Kode ini memakai query standar
Laravel, jadi cukup buat database MySQL di hPanel lalu ubah `.env`: `DB_CONNECTION=mysql` dan `DB_PORT=3306`,
kemudian `php artisan migrate --seed` (hanya akun; tambahkan `--seeder=SampleDataSeeder` bila ingin data contoh).
Arahkan domain/subdomain ke folder `public`.