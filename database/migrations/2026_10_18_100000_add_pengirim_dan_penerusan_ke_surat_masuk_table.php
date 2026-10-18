<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alur persuratan tahap 2: jenis & data pengirim (ormawa/prodi/eksternal) serta penerusan staf khusus -> dekan.
 * Idempotent: tiap kolom dibuat hanya bila belum ada. Surat lama bernilai bawaan `eksternal` (asal luar fakultas)
 * dan dianggap sudah diteruskan agar dekan tetap melihat/mendisposisikannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $kolomBaru = ! Schema::hasColumn('surat_masuk', 'diteruskan_pada');

        Schema::table('surat_masuk', function (Blueprint $table): void {
            if (! Schema::hasColumn('surat_masuk', 'jenis_pengirim')) {
                $table->string('jenis_pengirim', 20)->default('eksternal')->after('asal');
            }
            if (! Schema::hasColumn('surat_masuk', 'pengirim_unit_kerja_id')) {
                $table->foreignUuid('pengirim_unit_kerja_id')->nullable()->after('jenis_pengirim')->constrained('unit_kerja')->nullOnDelete();
            }
            // Data pribadi pengirim (UU PDP): dienkripsi oleh model, tidak masuk jejak audit.
            if (! Schema::hasColumn('surat_masuk', 'pengirim_nama')) {
                $table->string('pengirim_nama', 150)->nullable()->after('pengirim_unit_kerja_id');
            }
            if (! Schema::hasColumn('surat_masuk', 'pengirim_instansi')) {
                $table->string('pengirim_instansi', 255)->nullable()->after('pengirim_nama');
            }
            if (! Schema::hasColumn('surat_masuk', 'pengirim_surel')) {
                $table->text('pengirim_surel')->nullable()->after('pengirim_instansi');
            }
            if (! Schema::hasColumn('surat_masuk', 'pengirim_wa')) {
                $table->text('pengirim_wa')->nullable()->after('pengirim_surel');
            }
            if (! Schema::hasColumn('surat_masuk', 'diteruskan_pada')) {
                $table->timestamp('diteruskan_pada')->nullable()->after('status');
            }
            if (! Schema::hasColumn('surat_masuk', 'diteruskan_oleh')) {
                $table->foreignUuid('diteruskan_oleh')->nullable()->after('diteruskan_pada')->constrained('users')->nullOnDelete();
            }
        });

        // Surat yang sudah ada tiba sebelum aturan penerusan: dianggap telah diteruskan (tanpa pelaku).
        // Hanya saat kolom baru dibuat, agar menjalankan ulang tidak meneruskan surat yang memang menunggu.
        if ($kolomBaru) {
            DB::table('surat_masuk')->update(['diteruskan_pada' => DB::raw('tanggal_terima')]);
        }

        if (! Schema::hasIndex('surat_masuk', ['diteruskan_pada'])) {
            Schema::table('surat_masuk', fn (Blueprint $table) => $table->index('diteruskan_pada'));
        }
    }

    public function down(): void
    {
        Schema::table('surat_masuk', function (Blueprint $table): void {
            $table->dropIndex(['diteruskan_pada']);
            $table->dropConstrainedForeignId('diteruskan_oleh');
            $table->dropConstrainedForeignId('pengirim_unit_kerja_id');
            $table->dropColumn(['jenis_pengirim', 'pengirim_nama', 'pengirim_instansi', 'pengirim_surel', 'pengirim_wa', 'diteruskan_pada']);
        });
    }
};
