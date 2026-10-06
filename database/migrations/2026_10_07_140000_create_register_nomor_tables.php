<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('register_nomor', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 30)->unique();
            $table->string('nama', 150);
            $table->string('pola', 150);
            $table->string('reset', 10)->default('tahunan');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('nomor_terpakai', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('register_nomor_id')->constrained('register_nomor');
            // tahun = 0 untuk register tanpa reset tahunan (satu deret berkelanjutan)
            $table->unsignedSmallInteger('tahun');
            $table->unsignedInteger('urut');
            $table->string('nomor_lengkap', 150);
            $table->uuidMorphs('pemilik');
            $table->boolean('dibatalkan')->default(false);
            $table->foreignUuid('dibuat_oleh')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['register_nomor_id', 'tahun', 'urut']);
            $table->index('nomor_lengkap');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomor_terpakai');
        Schema::dropIfExists('register_nomor');
    }
};
