<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_permohonan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 40)->unique();
            $table->string('nama', 150);
            $table->boolean('butuh_ruangan')->default(false);
            $table->boolean('butuh_fasilitas_rektorat')->default(false);
            $table->foreignUuid('jenis_naskah_id')->nullable()->constrained('jenis_naskah')->nullOnDelete();
            $table->json('berkas_wajib');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('ruangan_lokal', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 30)->unique();
            $table->string('nama', 150);
            $table->string('gedung', 100)->nullable();
            $table->unsignedSmallInteger('kapasitas')->nullable();
            $table->text('fasilitas')->nullable();
            $table->boolean('dalam_perawatan')->default(false);
            $table->boolean('tampil_katalog')->default(true);
            $table->string('sumber_id_lama', 50)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('pemakaian_ruangan_lokal', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ruangan_lokal_id')->constrained('ruangan_lokal')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('sesi', 20);
            $table->string('keterangan')->nullable();
            // FK ke permohonan ditambahkan pada F7.2
            $table->uuid('permohonan_id')->nullable()->index();
            $table->timestamps();

            $table->index(['ruangan_lokal_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemakaian_ruangan_lokal');
        Schema::dropIfExists('ruangan_lokal');
        Schema::dropIfExists('jenis_permohonan');
    }
};
