<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ===== MASTER DATA =====
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();             // DT 1501
            $table->string('plate_number', 20)->unique();     // BE 1234 XX
            $table->string('type', 50)->default('Dump Truck');
            $table->string('brand', 80)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->decimal('capacity_ton', 8, 2)->default(0);
            $table->string('chassis_number', 60)->nullable();
            $table->string('engine_number', 60)->nullable();
            $table->date('stnk_expiry')->nullable();
            $table->date('kir_expiry')->nullable();
            $table->string('status', 20)->default('aktif');   // aktif | breakdown | nonaktif
            $table->timestamps();
        });

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('nik', 20)->nullable()->unique();
            $table->string('phone', 20);
            $table->string('license_number', 30);
            $table->string('license_type', 20)->default('B2 Umum');
            $table->date('license_expiry');
            $table->foreignId('default_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->date('joined_at')->nullable();
            $table->string('bonus_scheme', 10)->default('rit');  // rit | ton
            $table->decimal('bonus_rate', 15, 2)->default(0);
            $table->string('status', 20)->default('aktif');      // aktif | nonaktif
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('npwp', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('pic_name', 100)->nullable();
            $table->string('pic_phone', 20)->nullable();
            $table->string('billing_email', 150)->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->boolean('is_pkp')->default(true);
            $table->string('status', 20)->default('aktif');
            $table->timestamps();
        });

        Schema::create('haul_routes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();               // BSR - 107
            $table->string('origin', 150);                     // pit
            $table->string('destination', 150);                // jetty / stockpile
            $table->decimal('distance_km', 8, 2);              // per rit
            $table->decimal('std_fuel_liters', 8, 2);          // per rit
            $table->decimal('allowance', 15, 2);               // uang jalan per rit
            $table->decimal('std_travel_hours', 5, 2)->nullable();
            $table->string('status', 20)->default('aktif');
            $table->timestamps();
        });

        Schema::create('tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('haul_route_id')->constrained()->cascadeOnDelete();
            $table->string('unit_basis', 10)->default('ton');   // ton | rit
            $table->decimal('rate', 15, 2);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->string('contract_number', 60)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'haul_route_id', 'valid_from']);
        });

        Schema::create('fuel_prices', function (Blueprint $table) {
            $table->id();
            $table->date('valid_from')->unique();
            $table->decimal('price_per_liter', 12, 2);
            $table->string('vendor', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ===== KEUANGAN =====
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();             // INV-202610-001
            $table->foreignId('customer_id')->constrained();
            $table->date('period_from');
            $table->date('period_to');
            $table->date('issue_date');
            $table->date('due_date');
            $table->unsignedInteger('trip_count')->default(0);
            $table->decimal('total_tonnage', 14, 2)->default(0);
            $table->decimal('subtotal', 16, 2)->default(0);     // DPP
            $table->decimal('ppn_percent', 5, 2)->default(0);
            $table->decimal('ppn_amount', 16, 2)->default(0);
            $table->decimal('pph_percent', 5, 2)->default(0);
            $table->decimal('pph_amount', 16, 2)->default(0);
            $table->decimal('total', 16, 2)->default(0);
            $table->decimal('paid_amount', 16, 2)->default(0);
            $table->string('status', 20)->default('belum_lunas'); // belum_lunas | sebagian | lunas
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->date('paid_at');
            $table->decimal('amount', 16, 2);
            $table->string('method', 20)->default('transfer');  // transfer | tunai | giro
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ===== OPERASIONAL =====
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();             // TRIP-20261003-0001
            $table->date('trip_date');
            $table->foreignId('unit_id')->constrained();
            $table->foreignId('driver_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('haul_route_id')->constrained();
            $table->string('ticket_number', 40)->unique();      // CE261003175907
            $table->decimal('tonnage', 10, 2);                 // netto
            // nilai yang dibekukan saat trip disimpan
            $table->string('rate_basis', 10)->default('ton');
            $table->decimal('rate', 15, 2)->default(0);
            $table->decimal('amount', 16, 2)->default(0);      // revenue
            $table->decimal('distance_km', 8, 2)->default(0);
            $table->decimal('fuel_liters', 8, 2)->default(0);
            $table->decimal('fuel_price', 12, 2)->default(0);
            $table->decimal('fuel_cost', 16, 2)->default(0);
            $table->decimal('allowance', 15, 2)->default(0);   // uang jalan
            $table->decimal('hpp', 16, 2)->default(0);         // fuel_cost + allowance
            $table->string('status', 20)->default('draft');    // draft | verified | invoiced | paid
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['trip_date', 'status']);
            $table->index(['unit_id', 'trip_date']);
        });

        Schema::create('breakdowns', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();             // BD-20261002-01
            $table->foreignId('unit_id')->constrained();
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->string('description', 255);
            $table->string('action_taken', 255)->nullable();
            $table->decimal('cost', 16, 2)->default(0);
            $table->string('status', 20)->default('proses');   // proses | selesai
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('breakdowns');
        Schema::dropIfExists('trips');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('fuel_prices');
        Schema::dropIfExists('tariffs');
        Schema::dropIfExists('haul_routes');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('units');
    }
};
