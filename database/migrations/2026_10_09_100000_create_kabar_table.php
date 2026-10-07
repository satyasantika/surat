<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kabar', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 190)->unique();
            $table->foreignUuid('ormawa_id')->nullable()->constrained('ormawa');
            $table->string('judul', 200);
            $table->string('subjudul', 250)->nullable();
            $table->mediumText('isi');
            $table->json('tag')->nullable();
            $table->string('status', 20)->default('draf');
            $table->text('catatan_admin')->nullable();
            $table->foreignUuid('penulis_id')->constrained('users');
            $table->foreignUuid('disetujui_oleh')->nullable()->constrained('users');
            $table->timestamp('terbit_pada')->nullable();
            $table->string('sumber_id_lama', 50)->nullable();
            $table->timestamps();

            $table->index(['status', 'terbit_pada']);
            $table->index(['ormawa_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kabar');
    }
};
