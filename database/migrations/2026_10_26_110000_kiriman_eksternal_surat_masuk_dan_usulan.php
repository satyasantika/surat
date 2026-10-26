<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kiriman pengirim eksternal (akun Google) ke fakultas: surat masuk berstatus `diajukan` belum bernomor agenda,
 * belum memiliki nomor surat pengirim, dan belum diregistrasi petugas. Nomor agenda diberikan saat admin menerima.
 * Kolom `pengirim_user_id` menautkan kiriman ke akun pengirim (daftar kiriman miliknya).
 * `usulan_mahasiswa.asal` membedakan usulan mahasiswa dari kiriman pihak luar di kotak usulan perantara.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_masuk', function (Blueprint $table): void {
            // UNIQUE tetap berlaku; NULL boleh berulang (MariaDB/PostgreSQL/SQLite).
            $table->string('nomor_agenda', 50)->nullable()->change();
            $table->string('nomor_surat', 150)->nullable()->change();
            $table->uuid('diregistrasi_oleh')->nullable()->change();
        });

        Schema::table('surat_masuk', function (Blueprint $table): void {
            if (! Schema::hasColumn('surat_masuk', 'pengirim_user_id')) {
                $table->foreignUuid('pengirim_user_id')->nullable()->after('pengirim_wa')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('usulan_mahasiswa', function (Blueprint $table): void {
            if (! Schema::hasColumn('usulan_mahasiswa', 'asal')) {
                $table->string('asal', 20)->default('mahasiswa')->after('mahasiswa_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('usulan_mahasiswa', fn (Blueprint $table) => $table->dropColumn('asal'));

        Schema::table('surat_masuk', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pengirim_user_id');
        });

        // Mengembalikan NOT NULL hanya aman bila tidak ada kiriman yang belum diterima.
        Schema::table('surat_masuk', function (Blueprint $table): void {
            $table->string('nomor_agenda', 50)->nullable(false)->change();
            $table->string('nomor_surat', 150)->nullable(false)->change();
            $table->uuid('diregistrasi_oleh')->nullable(false)->change();
        });
    }
};
