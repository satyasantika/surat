<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('klasifikasi_arsip', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 20)->unique();
            $table->string('nama');
            $table->foreignUuid('induk_id')->nullable()->constrained('klasifikasi_arsip')->nullOnDelete();
            $table->unsignedTinyInteger('retensi_aktif_tahun')->nullable();
            $table->unsignedTinyInteger('retensi_inaktif_tahun')->nullable();
            $table->string('keterangan_akhir', 20)->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('klasifikasi_arsip');
    }
};
