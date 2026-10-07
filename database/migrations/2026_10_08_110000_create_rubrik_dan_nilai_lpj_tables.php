<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubrik_lpj', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 40)->unique();
            $table->string('nama', 150);
            $table->unsignedTinyInteger('nilai_maks');
            $table->json('penilai_jabatan');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('nilai_lpj', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lpj_id')->constrained('lpj')->cascadeOnDelete();
            $table->foreignUuid('rubrik_lpj_id')->constrained('rubrik_lpj');
            $table->foreignUuid('penilai_jabatan_id')->constrained('jabatan');
            $table->foreignUuid('penilai_user_id')->constrained('users');
            $table->decimal('nilai', 5, 2);
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['lpj_id', 'rubrik_lpj_id', 'penilai_jabatan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai_lpj');
        Schema::dropIfExists('rubrik_lpj');
    }
};
