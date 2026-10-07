<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Penanda undangan atur kata sandi hasil migrasi (ormawahub:undang-pengguna) agar tidak terkirim dua kali.
            $table->timestamp('diundang_pada')->nullable()->after('sumber_id_lama');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('diundang_pada'));
    }
};
