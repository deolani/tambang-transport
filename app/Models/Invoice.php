<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Invoice extends Model
{
    public const STATUSES = [
        'belum_lunas' => 'Belum Lunas',
        'sebagian' => 'Dibayar Sebagian',
        'lunas' => 'Lunas',
    ];

    public const STATUS_COLORS = [
        'belum_lunas' => 'danger',
        'sebagian' => 'warning',
        'lunas' => 'success',
    ];

    protected $guarded = [];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'issue_date' => 'date',
        'due_date' => 'date',
        'total_tonnage' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'ppn_percent' => 'decimal:2',
        'ppn_amount' => 'decimal:2',
        'pph_percent' => 'decimal:2',
        'pph_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public static function nextNumber($date): string
    {
        $prefix = 'INV-'.Carbon::parse($date ?? now())->format('Ym').'-';
        $last = static::query()->where('number', 'like', $prefix.'%')->orderByDesc('number')->value('number');
        $seq = $last ? ((int) substr($last, -3)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }

    /** Trip VERIFIED milik customer dalam periode yang belum masuk invoice. */
    public static function billableTrips(int $customerId, $from, $to)
    {
        return Trip::query()
            ->where('customer_id', $customerId)
            ->where('status', 'verified')
            ->whereNull('invoice_id')
            ->betweenDates($from, $to);
    }

    /** Buat invoice dari trip VERIFIED dan kunci trip-nya. */
    public static function createFromTrips(array $data): self
    {
        return DB::transaction(function () use ($data) {
            $trips = static::billableTrips($data['customer_id'], $data['period_from'], $data['period_to'])
                ->lockForUpdate()->get();

            $customer = Customer::findOrFail($data['customer_id']);
            $subtotal = (float) $trips->sum('amount');
            $ppn = round($subtotal * (float) ($data['ppn_percent'] ?? 0) / 100, 2);
            $pph = round($subtotal * (float) ($data['pph_percent'] ?? 0) / 100, 2);

            $invoice = static::create([
                'number' => static::nextNumber($data['issue_date']),
                'customer_id' => $customer->id,
                'period_from' => $data['period_from'],
                'period_to' => $data['period_to'],
                'issue_date' => $data['issue_date'],
                'due_date' => Carbon::parse($data['issue_date'])->addDays($customer->payment_terms_days),
                'trip_count' => $trips->count(),
                'total_tonnage' => $trips->sum('tonnage'),
                'subtotal' => $subtotal,
                'ppn_percent' => $data['ppn_percent'] ?? 0,
                'ppn_amount' => $ppn,
                'pph_percent' => $data['pph_percent'] ?? 0,
                'pph_amount' => $pph,
                'total' => $subtotal + $ppn - $pph,
                'notes' => $data['notes'] ?? null,
            ]);

            Trip::whereIn('id', $trips->pluck('id'))->update([
                'invoice_id' => $invoice->id,
                'status' => 'invoiced',
            ]);

            return $invoice;
        });
    }

    /** Hitung ulang total pembayaran dan status. */
    public function refreshPaymentStatus(): void
    {
        $paid = (float) $this->payments()->sum('amount');
        $status = $paid <= 0 ? 'belum_lunas' : ($paid + 0.5 >= (float) $this->total ? 'lunas' : 'sebagian');

        $this->forceFill(['paid_amount' => $paid, 'status' => $status])->saveQuietly();

        Trip::where('invoice_id', $this->id)->update(['status' => $status === 'lunas' ? 'paid' : 'invoiced']);
    }

    public function getOutstandingAttribute(): float
    {
        return max(0, (float) $this->total - (float) $this->paid_amount);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
