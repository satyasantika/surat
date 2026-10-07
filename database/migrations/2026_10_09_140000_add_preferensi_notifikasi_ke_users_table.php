<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // {kategori: {mail: bool, whatsapp: bool}}; null = bawaan (surel aktif, WhatsApp mati)
            $table->json('preferensi_notifikasi')->nullable()->after('diundang_pada');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('preferensi_notifikasi'));
    }
};
