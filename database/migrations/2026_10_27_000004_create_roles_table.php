<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique();        // dipakai di users.role
            $table->string('name', 80);
            $table->string('description', 255)->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_system')->default(false); // tidak bisa dihapus
            $table->timestamps();
        });

        $now = now();
        DB::table('roles')->insert([
            'key' => Permissions::SUPER_ADMIN,
            'name' => 'Super Admin',
            'description' => 'Mengatur hak akses semua role. Selalu punya semua akses.',
            'permissions' => json_encode([]),
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (Permissions::defaults() as $key => $role) {
            DB::table('roles')->insert([
                'key' => $key,
                'name' => $role['name'],
                'description' => $role['description'],
                'permissions' => json_encode(array_values($role['permissions'])),
                'is_system' => $key === 'owner',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
