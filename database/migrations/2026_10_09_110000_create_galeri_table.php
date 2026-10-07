<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galeri', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ormawa_id')->nullable()->constrained('ormawa');
            // Referensi buram (bukan FK) seperti pemakaian_ruangan_lokal: galeri tidak boleh menghambat arsip permohonan.
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

    public function down(): void
    {
        Schema::dropIfExists('galeri');
    }
};
