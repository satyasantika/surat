<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_kerja', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 20)->unique();
            $table->string('nama');
            $table->foreignUuid('induk_id')->nullable()->constrained('unit_kerja')->nullOnDelete();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('jabatan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 30)->unique();
            $table->string('nama', 150);
            $table->foreignUuid('unit_kerja_id')->constrained('unit_kerja');
            $table->string('bidang', 30)->nullable();
            $table->boolean('dapat_menandatangani')->default(false);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('pemangku_jabatan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('jabatan_id')->constrained('jabatan')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');
            $table->date('mulai');
            $table->date('selesai')->nullable();
            $table->boolean('plt')->default(false);
            $table->string('nomor_sk', 100)->nullable();
            $table->timestamps();

            $table->index(['jabatan_id', 'mulai', 'selesai']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('unit_kerja_id')->references('id')->on('unit_kerja')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['unit_kerja_id']);
        });
        Schema::dropIfExists('pemangku_jabatan');
        Schema::dropIfExists('jabatan');
        Schema::dropIfExists('unit_kerja');
    }
};
