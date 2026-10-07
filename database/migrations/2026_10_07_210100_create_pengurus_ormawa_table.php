<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengurus_ormawa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ormawa_id')->constrained('ormawa')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('sk_kepengurusan_id')->nullable()->constrained('sk_kepengurusan')->nullOnDelete();
            $table->string('nama', 150);
            $table->string('nim', 20)->nullable()->index();
            $table->string('prodi', 100)->nullable();
            $table->string('jabatan', 20);
            $table->string('jabatan_teks', 100)->nullable();
            // dienkripsi (cast encrypted): panjang kolom memuat ciphertext
            $table->text('telepon')->nullable();
            $table->boolean('tampil_publik')->default(true);
            $table->boolean('narahubung')->default(false);
            $table->date('mulai')->nullable();
            $table->date('selesai')->nullable();
            $table->timestamps();

            $table->index(['ormawa_id', 'jabatan']);
            $table->unique(['ormawa_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengurus_ormawa');
    }
};
