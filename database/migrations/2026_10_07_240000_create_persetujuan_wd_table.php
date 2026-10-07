<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persetujuan_wd', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('permohonan_id')->constrained('permohonan')->cascadeOnDelete();
            $table->foreignUuid('jabatan_id')->constrained('jabatan');
            $table->foreignUuid('user_id')->nullable()->constrained('users');
            $table->string('putusan', 10)->default('menunggu');
            $table->text('catatan')->nullable();
            $table->timestamp('diputus_pada')->nullable();
            $table->timestamps();

            $table->unique(['permohonan_id', 'jabatan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persetujuan_wd');
    }
};
