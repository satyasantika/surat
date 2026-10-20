<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/** Fitur kabar dan galeri ditiadakan: tabel, tautan berkas milik kabar, dan izin terkait ikut dibuang. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tautan_berkas')->where('pemilik_type', 'App\\Models\\Kabar')->delete();
        DB::table('permissions')->whereIn('name', ['kabar.kelola', 'kabar.usul', 'galeri.kelola'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::dropIfExists('kabar');
        Schema::dropIfExists('galeri');
    }

    /** Hanya membuat ulang struktur kosong; data dan izin yang dibuang tidak dipulihkan. */
    public function down(): void
    {
        Schema::create('kabar', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 190)->unique();
            $table->foreignUuid('ormawa_id')->nullable()->constrained('ormawa');
            $table->string('judul', 200);
            $table->string('subjudul', 250)->nullable();
            $table->mediumText('isi');
            $table->json('tag')->nullable();
            $table->string('status', 20)->default('draf');
            $table->text('catatan_admin')->nullable();
            $table->foreignUuid('penulis_id')->constrained('users');
            $table->foreignUuid('disetujui_oleh')->nullable()->constrained('users');
            $table->timestamp('terbit_pada')->nullable();
            $table->string('sumber_id_lama', 50)->nullable();
            $table->timestamps();

            $table->index(['status', 'terbit_pada']);
            $table->index(['ormawa_id', 'status']);
        });

        Schema::create('galeri', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ormawa_id')->nullable()->constrained('ormawa');
            $table->string('permohonan_id', 36)->nullable()->index();
            $table->string('judul', 200);
            $table->string('tipe', 12);
            $table->string('url', 2048);
            $table->boolean('aktif')->default(false);
            $table->unsignedInteger('urutan')->default(0);
            $table->string('sumber_id_lama', 50)->nullable();
            $table->timestamps();

            $table->index(['aktif', 'tipe', 'urutan']);
        });
    }
};
