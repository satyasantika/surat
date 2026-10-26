<?php

use App\Support\KodeLacak;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kiriman pihak luar ke ormawa/prodi/lab menjadi baris `usulan_mahasiswa` (asal = eksternal). Tiap baris kini
 * berkode lacak publik sendiri (unik bersama permohonan dan surat masuk), dan menyimpan instansi pengirim pihak luar
 * agar perantara mengetahui asal kiriman. Baris lama diberi kode lacak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usulan_mahasiswa', function (Blueprint $table): void {
            if (! Schema::hasColumn('usulan_mahasiswa', 'kode_lacak')) {
                $table->string('kode_lacak', 12)->nullable()->after('id');
            }

            if (! Schema::hasColumn('usulan_mahasiswa', 'instansi')) {
                $table->string('instansi')->nullable()->after('asal');
            }
        });

        $ada = fn (string $k): bool => DB::table('permohonan')->where('kode_lacak', $k)->exists()
            || DB::table('surat_masuk')->where('kode_lacak', $k)->exists()
            || DB::table('usulan_mahasiswa')->where('kode_lacak', $k)->exists();

        DB::table('usulan_mahasiswa')->whereNull('kode_lacak')->orderBy('id')->select('id')->chunkById(200, function ($baris) use ($ada): void {
            foreach ($baris as $b) {
                DB::table('usulan_mahasiswa')->where('id', $b->id)->update(['kode_lacak' => KodeLacak::baru($ada)]);
            }
        });

        if (! Schema::hasIndex('usulan_mahasiswa', ['kode_lacak'], 'unique')) {
            Schema::table('usulan_mahasiswa', fn (Blueprint $table) => $table->unique('kode_lacak'));
        }
    }

    public function down(): void
    {
        Schema::table('usulan_mahasiswa', function (Blueprint $table): void {
            $table->dropUnique(['kode_lacak']);
            $table->dropColumn(['kode_lacak', 'instansi']);
        });
    }
};
