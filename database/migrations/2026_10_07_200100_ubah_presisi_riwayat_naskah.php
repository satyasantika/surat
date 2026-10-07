<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Urutan riwayat harus pasti: created_at berpresisi mikrodetik. */
    public function up(): void
    {
        Schema::table('riwayat_naskah', function (Blueprint $table) {
            $table->timestamp('created_at', 6)->useCurrent()->change();
        });
    }

    public function down(): void
    {
        Schema::table('riwayat_naskah', function (Blueprint $table) {
            $table->timestamp('created_at')->useCurrent()->change();
        });
    }
};
