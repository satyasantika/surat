<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('naskah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('jenis_naskah_id')->constrained('jenis_naskah');
            $table->foreignUuid('klasifikasi_arsip_id')->nullable()->constrained('klasifikasi_arsip')->nullOnDelete();
            $table->string('klasifikasi_keamanan', 20)->default('biasa');
            $table->string('derajat_kecepatan', 20)->default('biasa');
            $table->string('perihal', 500);
            $table->json('data');
            $table->mediumText('isi')->nullable();
            $table->string('status', 30)->default('draf');
            $table->foreignUuid('penyusun_id')->constrained('users');
            $table->foreignUuid('penanda_tangan_jabatan_id')->constrained('jabatan');
            $table->foreignUuid('penanda_tangan_user_id')->nullable()->constrained('users');
            $table->string('atas_nama', 10)->nullable();
            $table->string('mode_tanda_tangan', 10);
            $table->string('nomor', 150)->nullable()->unique();
            $table->date('tanggal_naskah')->nullable();
            $table->foreignUuid('nomor_terpakai_id')->nullable()->unique()->constrained('nomor_terpakai');
            $table->json('snapshot')->nullable();
            $table->char('hash_pdf', 64)->nullable();
            $table->timestamp('ditandatangani_pada')->nullable();
            $table->timestamp('diterbitkan_pada')->nullable();
            $table->timestamp('dibatalkan_pada')->nullable();
            $table->text('alasan_batal')->nullable();
            // FK ke permohonan ditambahkan di F7
            $table->uuid('permohonan_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        Schema::create('naskah_tujuan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('naskah_id')->constrained('naskah')->cascadeOnDelete();
            $table->string('jenis', 10);
            $table->string('nama');
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('naskah_tujuan');
        Schema::dropIfExists('naskah');
    }
};
