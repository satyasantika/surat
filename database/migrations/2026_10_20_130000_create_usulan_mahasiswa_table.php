<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Usulan mahasiswa kepada ormawa atau program studi/laboratorium (perantara). Perantara memilah: meneruskan satu
 * usulan atau menggabungkan beberapa usulan menjadi dasar satu permohonan ke fakultas (permohonan_id terisi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usulan_mahasiswa', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('mahasiswa_id')->constrained('users');
            $table->foreignUuid('ormawa_id')->nullable()->constrained('ormawa');
            $table->foreignUuid('unit_kerja_id')->nullable()->constrained('unit_kerja');
            $table->string('perihal');
            $table->text('uraian');
            $table->string('status', 20)->default('diajukan');
            $table->text('catatan')->nullable();
            $table->foreignUuid('permohonan_id')->nullable()->constrained('permohonan')->restrictOnDelete();
            $table->timestamps();

            $table->index(['ormawa_id', 'status']);
            $table->index(['unit_kerja_id', 'status']);
            $table->index(['mahasiswa_id', 'status']);
            $table->index('permohonan_id');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE usulan_mahasiswa ADD CONSTRAINT usulan_mahasiswa_tujuan_ck CHECK ((ormawa_id IS NOT NULL AND unit_kerja_id IS NULL) OR (ormawa_id IS NULL AND unit_kerja_id IS NOT NULL))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('usulan_mahasiswa');
    }
};
