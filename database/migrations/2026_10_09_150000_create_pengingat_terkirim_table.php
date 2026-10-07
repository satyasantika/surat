<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengingat_terkirim', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Kunci deterministik (mis. disposisi:{id}:lewat) agar pengingat terjadwal tidak terkirim berulang.
            $table->string('kunci', 190)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengingat_terkirim');
    }
};
