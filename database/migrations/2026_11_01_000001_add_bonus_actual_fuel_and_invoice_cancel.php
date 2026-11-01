<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            // Premi sopir per trip (ikut HPP) dan solar aktual untuk dibandingkan dengan standar rute.
            $table->decimal('driver_bonus', 14, 2)->default(0)->after('allowance');
            $table->decimal('fuel_actual_liters', 8, 2)->nullable()->after('fuel_liters');
        });

        Schema::table('invoices', function (Blueprint $table) {
            // Invoice dibatalkan, bukan dihapus: nomor & jejak audit tetap ada.
            $table->timestamp('cancelled_at')->nullable()->after('status');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancel_reason']);
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['driver_bonus', 'fuel_actual_liters']);
        });
    }
};
