<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ormawa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 100)->unique();
            $table->string('nama', 150)->unique();
            $table->string('singkatan', 30)->nullable();
            $table->string('tingkat', 20);
            $table->string('prodi', 100)->nullable();
            $table->string('akun_media', 100)->nullable();
            $table->string('surel_organisasi', 150)->nullable();
            $table->text('visi')->nullable();
            $table->text('misi')->nullable();
            $table->foreignUuid('pembina_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('aktif')->default(true);
            $table->string('sumber_id_lama', 50)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('sk_kepengurusan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ormawa_id')->constrained('ormawa')->cascadeOnDelete();
            $table->string('nomor_sk', 150);
            $table->date('tanggal_sk');
            $table->date('periode_mulai');
            $table->date('periode_selesai');
            $table->string('disahkan_oleh', 150)->nullable();
            $table->timestamps();

            $table->index(['ormawa_id', 'periode_mulai', 'periode_selesai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sk_kepengurusan');
        Schema::dropIfExists('ormawa');
    }
};
