<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nip_nim', 30)->nullable()->unique()->after('email');
            $table->string('telepon', 20)->nullable()->after('nip_nim');
            // FK ke unit_kerja ditambahkan di F3
            $table->uuid('unit_kerja_id')->nullable()->after('telepon');
            $table->boolean('aktif')->default(true)->after('unit_kerja_id');
            $table->string('sumber_id_lama', 50)->nullable()->after('aktif');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nip_nim', 'telepon', 'unit_kerja_id', 'aktif', 'sumber_id_lama']);
        });
    }
};
