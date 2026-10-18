<?php

use App\Support\KodeLacak;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alur persuratan tahap 5-6: kode lacak publik (BR-27), penyelesaian dengan hasil cetak (BR-28), dan unit pembuat
 * pada surat masuk. Idempotent: kolom dibuat hanya bila belum ada, kode lacak hanya diisi pada baris yang masih kosong
 * (data lama ikut terisi), indeks unik dibuat sesudah pengisian.
 */
return new class extends Migration
{
    private const TABEL = ['permohonan', 'surat_masuk'];

    public function up(): void
    {
        foreach (self::TABEL as $nama) {
            Schema::table($nama, function (Blueprint $table) use ($nama): void {
                if (! Schema::hasColumn($nama, 'kode_lacak')) {
                    $table->string('kode_lacak', 12)->nullable();
                }
                if (! Schema::hasColumn($nama, 'hasil_dikirim_pada')) {
                    $table->timestamp('hasil_dikirim_pada')->nullable();
                }
                if (! Schema::hasColumn($nama, 'hasil_dikirim_oleh')) {
                    $table->foreignUuid('hasil_dikirim_oleh')->nullable()->constrained('users')->nullOnDelete();
                }
            });
        }

        if (! Schema::hasColumn('surat_masuk', 'unit_pembuat_id')) {
            Schema::table('surat_masuk', function (Blueprint $table): void {
                $table->foreignUuid('unit_pembuat_id')->nullable()->constrained('unit_pembuat')->restrictOnDelete();
            });
        }

        $this->isiKodeLama();

        foreach (self::TABEL as $nama) {
            if (! Schema::hasIndex($nama, ['kode_lacak'], 'unique')) {
                Schema::table($nama, fn (Blueprint $table) => $table->unique('kode_lacak'));
            }
        }
    }

    private function isiKodeLama(): void
    {
        $ada = fn (string $k): bool => DB::table('permohonan')->where('kode_lacak', $k)->exists()
            || DB::table('surat_masuk')->where('kode_lacak', $k)->exists();

        foreach (self::TABEL as $nama) {
            DB::table($nama)->whereNull('kode_lacak')->orderBy('id')->select('id')->chunkById(200, function ($baris) use ($nama, $ada): void {
                foreach ($baris as $b) {
                    DB::table($nama)->where('id', $b->id)->update(['kode_lacak' => KodeLacak::baru($ada)]);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('surat_masuk', 'unit_pembuat_id')) {
            Schema::table('surat_masuk', fn (Blueprint $table) => $table->dropConstrainedForeignId('unit_pembuat_id'));
        }

        foreach (self::TABEL as $nama) {
            Schema::table($nama, function (Blueprint $table) use ($nama): void {
                if (Schema::hasIndex($nama, ['kode_lacak'], 'unique')) {
                    $table->dropUnique(['kode_lacak']);
                }
                if (Schema::hasColumn($nama, 'hasil_dikirim_oleh')) {
                    $table->dropConstrainedForeignId('hasil_dikirim_oleh');
                }
            });
            Schema::table($nama, fn (Blueprint $table) => $table->dropColumn(['kode_lacak', 'hasil_dikirim_pada']));
        }
    }
};
