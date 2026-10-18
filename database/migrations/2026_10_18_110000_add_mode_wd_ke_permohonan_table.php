<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alur persuratan tahap 3: mode persetujuan Wakil Dekan yang dipilih dekan saat disposisi (BR-25).
 * `semua` = seluruh WD tujuan harus setuju (perilaku BR-14 lama, juga nilai bawaan permohonan yang sudah ada);
 * `salah_satu` = persetujuan WD pertama menutup tahap. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('permohonan', 'mode_wd')) {
            Schema::table('permohonan', function (Blueprint $table): void {
                $table->string('mode_wd', 12)->default('semua')->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('permohonan', 'mode_wd')) {
            Schema::table('permohonan', fn (Blueprint $table) => $table->dropColumn('mode_wd'));
        }
    }
};
