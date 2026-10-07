<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** `referensi` pada LayananRuangan bersifat buram (idempotensi), jadi kolom ini tidak selalu id permohonan. */
    public function up(): void
    {
        Schema::table('pemakaian_ruangan_lokal', fn (Blueprint $t) => $t->dropForeign(['permohonan_id']));
    }

    public function down(): void
    {
        Schema::table('pemakaian_ruangan_lokal', fn (Blueprint $t) => $t->foreign('permohonan_id')->references('id')->on('permohonan')->nullOnDelete());
    }
};
