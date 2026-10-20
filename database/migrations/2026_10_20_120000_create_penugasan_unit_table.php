<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penugasan akun pada unit kerja (prodi/laboratorium): pengaju (mengajukan permohonan atas nama unit) atau
 * penyetuju (kepala prodi/laboratorium yang menyetujui sebelum permohonan masuk ke fakultas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penugasan_unit', function (Blueprint $table): void {
            $table->foreignUuid('unit_kerja_id')->constrained('unit_kerja')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('peran', 20);
            $table->timestamps();

            $table->primary(['unit_kerja_id', 'user_id', 'peran']);
            $table->index(['user_id', 'peran']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penugasan_unit');
    }
};
