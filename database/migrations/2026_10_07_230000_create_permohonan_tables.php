<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ormawa', function (Blueprint $table) {
            // Ditandai penjadwal blokir LPJ (BR-16, F8).
            $table->boolean('diblokir_lpj')->default(false)->after('aktif');
            $table->timestamp('diblokir_sejak')->nullable()->after('diblokir_lpj');
        });

        Schema::create('permohonan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nomor', 30)->unique();
            $table->string('nomor_lama', 30)->nullable()->unique();
            $table->foreignUuid('ormawa_id')->constrained('ormawa');
            $table->foreignUuid('jenis_permohonan_id')->constrained('jenis_permohonan');
            $table->foreignUuid('diajukan_oleh')->constrained('users');
            $table->string('nama_kegiatan');
            $table->string('perihal');
            $table->string('nomor_surat_ormawa', 100)->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->string('tempat_lain')->nullable();
            $table->text('deskripsi');
            // data pribadi (BR-18): disimpan terenkripsi
            $table->longText('penanggung_jawab');
            $table->json('fasilitas_rektorat')->nullable();
            $table->string('status', 30);
            $table->text('alasan_mendesak')->nullable();
            $table->foreignUuid('naskah_izin_id')->nullable()->constrained('naskah')->nullOnDelete();
            $table->timestamp('diajukan_pada');
            $table->timestamp('selesai_pada')->nullable();
            $table->string('sumber', 20)->default('aplikasi');
            $table->timestamps();

            $table->index(['ormawa_id', 'status']);
            $table->index('tanggal_mulai');
        });

        Schema::create('permohonan_ruangan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('permohonan_id')->constrained('permohonan')->cascadeOnDelete();
            $table->string('kode_ruangan', 30);
            $table->string('nama_ruangan', 150);
            $table->date('tanggal');
            $table->string('sesi', 20);
            $table->string('status', 20)->default('ditahan');
            $table->string('id_pemakaian_aset', 50)->nullable();
            $table->timestamps();

            $table->index(['kode_ruangan', 'tanggal', 'status']);
        });

        Schema::create('riwayat_permohonan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('permohonan_id')->constrained('permohonan');
            $table->string('dari_status', 30)->nullable();
            $table->string('ke_status', 30);
            $table->foreignUuid('oleh')->nullable()->constrained('users');
            $table->string('pelaku_lama', 150)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at', 6)->useCurrent();
        });

        // Kolom permohonan_id yang dibuat lebih awal kini berkait ke tabel permohonan.
        Schema::table('disposisi', fn (Blueprint $t) => $t->foreign('permohonan_id')->references('id')->on('permohonan'));
        Schema::table('naskah', fn (Blueprint $t) => $t->foreign('permohonan_id')->references('id')->on('permohonan')->nullOnDelete());
        Schema::table('pemakaian_ruangan_lokal', fn (Blueprint $t) => $t->foreign('permohonan_id')->references('id')->on('permohonan')->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('pemakaian_ruangan_lokal', fn (Blueprint $t) => $t->dropForeign(['permohonan_id']));
        Schema::table('naskah', fn (Blueprint $t) => $t->dropForeign(['permohonan_id']));
        Schema::table('disposisi', fn (Blueprint $t) => $t->dropForeign(['permohonan_id']));
        Schema::dropIfExists('riwayat_permohonan');
        Schema::dropIfExists('permohonan_ruangan');
        Schema::dropIfExists('permohonan');
        Schema::table('ormawa', fn (Blueprint $t) => $t->dropColumn(['diblokir_lpj', 'diblokir_sejak']));
    }
};
