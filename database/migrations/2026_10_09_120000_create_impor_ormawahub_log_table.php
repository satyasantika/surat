<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impor_ormawahub_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('batch');
            $table->string('sheet', 30);
            $table->string('id_lama', 100);
            $table->string('tabel_baru', 50)->nullable();
            $table->char('id_baru', 36)->nullable();
            $table->string('status', 12);
            $table->text('pesan')->nullable();
            $table->timestamps();

            $table->index(['sheet', 'id_lama']);
            $table->index('batch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impor_ormawahub_log');
    }
};
