<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alur persuratan tahap 4: master unit pembuat surat (dikelola super-admin), petugasnya, unit bawaan per jenis
 * permohonan, dan unit yang ditugaskan kasubag pada tiap permohonan (BR-26). Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('unit_pembuat')) {
            Schema::create('unit_pembuat', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('kode', 40)->unique();
                $table->string('nama', 150);
                $table->text('deskripsi')->nullable();
                $table->boolean('aktif')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('unit_pembuat_petugas')) {
            Schema::create('unit_pembuat_petugas', function (Blueprint $table): void {
                $table->foreignUuid('unit_pembuat_id')->constrained('unit_pembuat')->cascadeOnDelete();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['unit_pembuat_id', 'user_id']);
            });
        }

        if (! Schema::hasColumn('jenis_permohonan', 'unit_pembuat_bawaan_id')) {
            Schema::table('jenis_permohonan', function (Blueprint $table): void {
                $table->foreignUuid('unit_pembuat_bawaan_id')->nullable()->constrained('unit_pembuat')->nullOnDelete();
            });
        }

        // Unit yang ditugaskan kasubag; dibatasi (restrict) agar unit yang pernah dipakai tidak terhapus diam-diam.
        if (! Schema::hasColumn('permohonan', 'unit_pembuat_id')) {
            Schema::table('permohonan', function (Blueprint $table): void {
                $table->foreignUuid('unit_pembuat_id')->nullable()->constrained('unit_pembuat')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('permohonan', 'unit_pembuat_id')) {
            Schema::table('permohonan', fn (Blueprint $table) => $table->dropConstrainedForeignId('unit_pembuat_id'));
        }

        if (Schema::hasColumn('jenis_permohonan', 'unit_pembuat_bawaan_id')) {
            Schema::table('jenis_permohonan', fn (Blueprint $table) => $table->dropConstrainedForeignId('unit_pembuat_bawaan_id'));
        }

        Schema::dropIfExists('unit_pembuat_petugas');
        Schema::dropIfExists('unit_pembuat');
    }
};
