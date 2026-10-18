<?php

namespace Database\Seeders;

use App\Models\JenisPermohonan;
use App\Models\UnitPembuat;
use Illuminate\Database\Seeder;

/**
 * Contoh awal OPSIONAL (tidak dipanggil DatabaseSeeder): jalankan `php artisan db:seed --class=UnitPembuatSeeder`.
 * Idempotent dan tidak menimpa: unit yang sudah ada (kode sama) tidak diubah, dan unit bawaan jenis permohonan
 * hanya diisi bila masih kosong. Super-admin bebas mengubah atau menghapusnya (BR-26); petugas diisi di panel.
 */
class UnitPembuatSeeder extends Seeder
{
    public function run(): void
    {
        $contoh = [
            ['rektorat', 'Rektorat', 'Surat yang diterbitkan/ditandatangani tingkat universitas.'],
            ['surat-tugas-dosen', 'Surat tugas dosen', 'Pembuatan surat tugas bagi dosen.'],
            ['izin-kegiatan', 'Izin kegiatan', 'Surat izin kegiatan ormawa dan peminjaman fasilitas.'],
        ];

        foreach ($contoh as [$kode, $nama, $deskripsi]) {
            UnitPembuat::firstOrCreate(['kode' => $kode], ['nama' => $nama, 'deskripsi' => $deskripsi, 'aktif' => true]);
        }

        $izin = UnitPembuat::firstWhere('kode', 'izin-kegiatan');

        JenisPermohonan::whereNull('unit_pembuat_bawaan_id')
            ->whereIn('kode', ['kegiatan', 'kegiatan-ruangan', 'kegiatan-ruangan-rektorat', 'pengantar-proposal'])
            ->update(['unit_pembuat_bawaan_id' => $izin->getKey()]);
    }
}
