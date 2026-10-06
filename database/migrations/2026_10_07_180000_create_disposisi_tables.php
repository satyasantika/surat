<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disposisi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('surat_masuk_id')->nullable()->constrained('surat_masuk');
            // FK ke permohonan ditambahkan di F7
            $table->uuid('permohonan_id')->nullable()->index();
            $table->uuid('induk_penerima_id')->nullable();
            $table->string('nomor', 80)->nullable();
            $table->foreignUuid('dari_user_id')->constrained('users');
            $table->foreignUuid('dari_jabatan_id')->nullable()->constrained('jabatan')->nullOnDelete();
            $table->json('instruksi');
            $table->text('catatan')->nullable();
            $table->dateTime('batas_waktu');
            $table->string('sifat', 20);
            $table->timestamps();
        });

        Schema::create('disposisi_penerima', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('disposisi_id')->constrained('disposisi')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');
            $table->foreignUuid('jabatan_id')->nullable()->constrained('jabatan')->nullOnDelete();
            $table->string('status', 20)->default('diterima');
            $table->timestamp('dibaca_pada')->nullable();
            $table->timestamp('ditindaklanjuti_pada')->nullable();
            $table->timestamp('selesai_pada')->nullable();
            $table->text('laporan_tindak_lanjut')->nullable();
            $table->boolean('terlambat')->default(false);
            $table->timestamps();

            $table->unique(['disposisi_id', 'user_id']);
        });

        Schema::table('disposisi', function (Blueprint $table) {
            $table->foreign('induk_penerima_id')->references('id')->on('disposisi_penerima')->nullOnDelete();
        });

        // Tepat satu dari surat_masuk_id / permohonan_id terisi.
        DB::statement('ALTER TABLE disposisi ADD CONSTRAINT disposisi_satu_sumber CHECK ((surat_masuk_id IS NOT NULL) + (permohonan_id IS NOT NULL) = 1)');
    }

    public function down(): void
    {
        Schema::table('disposisi', fn (Blueprint $t) => $t->dropForeign(['induk_penerima_id']));
        Schema::dropIfExists('disposisi_penerima');
        Schema::dropIfExists('disposisi');
    }
};
