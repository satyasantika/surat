<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('naskah_paraf', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('naskah_id')->constrained('naskah')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');
            $table->foreignUuid('jabatan_id')->nullable()->constrained('jabatan')->nullOnDelete();
            $table->unsignedTinyInteger('urutan');
            $table->string('status', 20)->default('menunggu');
            $table->text('catatan')->nullable();
            $table->timestamp('diputus_pada')->nullable();
            $table->timestamps();

            $table->unique(['naskah_id', 'urutan']);
        });

        Schema::create('riwayat_naskah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('naskah_id')->constrained('naskah');
            $table->string('dari_status', 30);
            $table->string('ke_status', 30);
            $table->foreignUuid('oleh')->constrained('users');
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_naskah');
        Schema::dropIfExists('naskah_paraf');
    }
};
