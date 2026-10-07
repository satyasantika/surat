<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Referensi pemakaian bersifat buram (idempotensi), bukan selalu UUID permohonan. */
    public function up(): void
    {
        Schema::table('pemakaian_ruangan_lokal', function (Blueprint $table) {
            $table->string('permohonan_id', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pemakaian_ruangan_lokal', function (Blueprint $table) {
            $table->uuid('permohonan_id')->nullable()->change();
        });
    }
};
