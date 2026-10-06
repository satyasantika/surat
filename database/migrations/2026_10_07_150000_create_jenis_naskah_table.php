<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_naskah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 30)->unique();
            $table->string('nama', 150);
            $table->string('kelompok', 20);
            $table->foreignUuid('register_nomor_id')->constrained('register_nomor');
            $table->string('templat_blade', 100);
            $table->unsignedSmallInteger('versi_templat')->default(1);
            $table->json('variabel');
            $table->json('mode_tanda_tangan_diizinkan');
            $table->foreignUuid('jabatan_penanda_tangan_bawaan_id')->nullable()->constrained('jabatan')->nullOnDelete();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jenis_naskah');
    }
};
