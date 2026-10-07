<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jenis_permohonan', function (Blueprint $table) {
            $table->boolean('butuh_lpj')->default(true)->after('butuh_fasilitas_rektorat');
        });

        Schema::create('lpj', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('permohonan_id')->unique()->constrained('permohonan');
            $table->date('tanggal_pelaksanaan')->nullable();
            $table->unsignedInteger('jumlah_peserta')->nullable();
            $table->text('ringkasan')->nullable();
            $table->text('kendala')->nullable();
            $table->text('solusi')->nullable();
            $table->text('rekomendasi')->nullable();
            $table->string('tautan_instagram', 2048)->nullable();
            $table->string('tautan_video', 2048)->nullable();
            $table->string('status', 20)->default('draf');
            $table->date('batas_waktu');
            $table->timestamp('diajukan_pada')->nullable();
            $table->decimal('nilai_akhir', 5, 2)->nullable();
            $table->string('sumber_id_lama', 50)->nullable()->index();
            $table->timestamps();

            $table->index(['status', 'batas_waktu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lpj');
        Schema::table('jenis_permohonan', fn (Blueprint $t) => $t->dropColumn('butuh_lpj'));
    }
};
