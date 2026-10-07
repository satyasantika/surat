<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_masuk', function (Blueprint $table) {
            // Penanda pengarsipan (RS-09): tanggal ini menjadi dasar retensi arsip surat masuk.
            $table->timestamp('diarsipkan_pada')->nullable()->after('status');
            $table->foreignUuid('diarsipkan_oleh')->nullable()->after('diarsipkan_pada')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('surat_masuk', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diarsipkan_oleh');
            $table->dropColumn('diarsipkan_pada');
        });
    }
};
