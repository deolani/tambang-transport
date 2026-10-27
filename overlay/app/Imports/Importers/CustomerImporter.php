<?php

namespace App\Imports\Importers;

use App\Imports\BaseImporter;
use App\Models\Customer;

class CustomerImporter extends BaseImporter
{
    public static function key(): string { return 'customers'; }

    public static function label(): string { return 'Customer'; }

    public static function description(): string { return 'Data perusahaan customer. Kode yang sudah ada akan diperbarui.'; }


    public static function columns(): array
    {
        return [
            'kode' => ['Kode', true, 'WHL', 'Unik'],
            'nama' => ['Nama', true, 'PT Whale Holder Logistics', ''],
            'npwp' => ['NPWP', false, '', ''],
            'alamat' => ['Alamat', false, '', ''],
            'nama_pic' => ['Nama PIC', false, '', ''],
            'hp_pic' => ['HP PIC', false, '', ''],
            'email_tagihan' => ['Email Tagihan', false, '', ''],
            'termin' => ['Termin (hari)', false, 30, 'Default 30'],
            'pkp' => ['PKP', false, 'ya', 'ya / tidak'],
            'status' => ['Status', false, 'aktif', 'aktif / nonaktif'],
        ];
    }

    protected function handle(array $row): string
    {
        $code = strtoupper(static::str($row['kode']));
        $c = Customer::whereRaw('upper(code) = ?', [$code])->first() ?? new Customer(['code' => $code]);

        $email = static::str($row['email_tagihan'] ?? null);
        if ($email && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \App\Imports\ImportRowException("Email tagihan tidak valid: {$email}");
        }

        $c->fill([
            'name' => static::str($row['nama']),
            'npwp' => static::str($row['npwp'] ?? null),
            'address' => static::str($row['alamat'] ?? null),
            'pic_name' => static::str($row['nama_pic'] ?? null),
            'pic_phone' => static::str($row['hp_pic'] ?? null),
            'billing_email' => $email,
            'payment_terms_days' => (int) (static::num($row['termin'] ?? null, 'Termin') ?? ($c->payment_terms_days ?: 30)),
            'is_pkp' => static::bool($row['pkp'] ?? null, $c->exists ? $c->is_pkp : true),
            'status' => static::choice($row['status'] ?? null, ['aktif', 'nonaktif'], $c->status ?: 'aktif', 'Status'),
        ]);
        $isNew = ! $c->exists;
        $c->save();

        return $isNew ? 'created' : 'updated';
    }
}
