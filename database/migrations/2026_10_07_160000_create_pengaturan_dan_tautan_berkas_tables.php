<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kunci', 60)->unique();
            $table->json('nilai');
            $table->string('grup', 30)->default('umum');
            $table->timestamps();
        });

        Schema::create('tautan_berkas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('pemilik');
            $table->string('jenis', 30);
            $table->string('label')->nullable();
            $table->string('url', 2048);
            $table->string('penyedia', 20);
            $table->string('drive_file_id', 100)->nullable();
            $table->string('status_cek', 20)->default('belum');
            $table->timestamp('dicek_pada')->nullable();
            $table->foreignUuid('ditambahkan_oleh')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pemilik_type', 'pemilik_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tautan_berkas');
        Schema::dropIfExists('pengaturan');
    }
};
