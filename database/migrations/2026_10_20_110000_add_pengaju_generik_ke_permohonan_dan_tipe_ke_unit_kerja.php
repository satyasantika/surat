<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaju generik (tahap 1): permohonan tidak lagi harus milik ormawa. Pengaju prodi/laboratorium memakai
 * unit_kerja_id. Data lama tetap berjenis ormawa; perilaku ormawa tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_kerja', function (Blueprint $table) {
            $table->string('tipe', 20)->default('lainnya')->after('nama');
        });

        DB::table('unit_kerja')->where('kode', 'UN58.10')->update(['tipe' => 'fakultas']);

        Schema::table('permohonan', function (Blueprint $table) {
            $table->string('jenis_pengaju', 20)->default('ormawa')->after('nomor_lama');
            $table->foreignUuid('unit_kerja_id')->nullable()->after('ormawa_id')->constrained('unit_kerja');
            // Nama mahasiswa pengaju bila bukan pengurus (diisi pada tahap berikutnya); data pribadi, dienkripsi model.
            $table->text('diajukan_atas_nama')->nullable()->after('diajukan_oleh');
            $table->index(['jenis_pengaju', 'unit_kerja_id', 'status']);
        });

        Schema::table('permohonan', function (Blueprint $table) {
            $table->foreignUuid('ormawa_id')->nullable()->change();
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE permohonan ADD CONSTRAINT permohonan_pengaju_ck CHECK ((jenis_pengaju = 'ormawa' AND ormawa_id IS NOT NULL) OR (jenis_pengaju IN ('prodi', 'laboratorium') AND unit_kerja_id IS NOT NULL))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE permohonan DROP CONSTRAINT permohonan_pengaju_ck');
        }

        // Baris pengaju unit tidak punya ormawa: dibuang agar kolom dapat kembali NOT NULL.
        DB::table('permohonan')->whereNull('ormawa_id')->delete();

        Schema::table('permohonan', function (Blueprint $table) {
            $table->dropIndex(['jenis_pengaju', 'unit_kerja_id', 'status']);
            $table->dropConstrainedForeignId('unit_kerja_id');
            $table->dropColumn(['jenis_pengaju', 'diajukan_atas_nama']);
        });

        Schema::table('permohonan', function (Blueprint $table) {
            $table->foreignUuid('ormawa_id')->nullable(false)->change();
        });

        Schema::table('unit_kerja', function (Blueprint $table) {
            $table->dropColumn('tipe');
        });
    }
};
