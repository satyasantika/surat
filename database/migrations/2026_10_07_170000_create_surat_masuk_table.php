<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_masuk', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nomor_agenda', 50)->unique();
            $table->dateTime('tanggal_terima');
            $table->string('nomor_surat', 150);
            $table->date('tanggal_surat');
            $table->string('asal');
            $table->string('perihal', 500);
            $table->text('ringkasan')->nullable();
            $table->string('lampiran', 100)->nullable();
            $table->foreignUuid('klasifikasi_arsip_id')->nullable()->constrained('klasifikasi_arsip')->nullOnDelete();
            $table->string('klasifikasi_keamanan', 20)->default('biasa');
            $table->string('derajat_kecepatan', 20)->default('biasa');
            $table->foreignUuid('unit_pengolah_id')->nullable()->constrained('unit_kerja')->nullOnDelete();
            $table->string('status', 20)->default('diterima');
            $table->foreignUuid('diregistrasi_oleh')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tanggal_terima');
            $table->index('klasifikasi_keamanan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_masuk');
    }
};
