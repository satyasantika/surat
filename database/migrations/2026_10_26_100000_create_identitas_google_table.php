<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Penautan satu akun Google (sub) ke satu pengguna; surel di sini hanya catatan, bukan kunci masuk. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identitas_google', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('google_id')->unique();
            $table->string('surel');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identitas_google');
    }
};
