<?php

namespace Database\Seeders;

use App\Models\Jabatan;
use App\Models\JenisNaskah;
use App\Models\RegisterNomor;
use Illuminate\Database\Seeder;

class JenisNaskahSeeder extends Seeder
{
    public function run(): void
    {
        $v = fn (string $kunci, string $label, string $tipe = 'text', bool $wajib = true) => compact('kunci', 'label', 'tipe', 'wajib');

        // kode, nama, kelompok, register, variabel, mode TTD
        $jenis = [
            ['surat-dinas', 'Surat Dinas', 'korespondensi', 'naskah-dekan', [$v('tujuan', 'Tujuan surat'), $v('lampiran', 'Lampiran', 'text', false)], ['basah', 'visual']],
            ['nota-dinas', 'Nota Dinas', 'korespondensi', 'naskah-dekan', [$v('kepada', 'Kepada'), $v('dari', 'Dari')], ['basah', 'visual']],
            ['surat-tugas', 'Surat Tugas', 'khusus', 'surat-tugas', [$v('nama_ditugaskan', 'Nama ditugaskan'), $v('nip', 'NIP/NIM', 'text', false), $v('tujuan_tugas', 'Tugas', 'textarea'), $v('tanggal_mulai', 'Tanggal mulai', 'date'), $v('tanggal_selesai', 'Tanggal selesai', 'date'), $v('tempat', 'Tempat')], ['basah', 'visual']],
            ['surat-keterangan', 'Surat Keterangan', 'khusus', 'naskah-dekan', [$v('nama', 'Nama'), $v('nip_nim', 'NIP/NIM'), $v('keperluan', 'Keperluan', 'textarea')], ['basah', 'visual']],
            ['undangan', 'Surat Undangan', 'korespondensi', 'naskah-dekan', [$v('tujuan', 'Kepada'), $v('hari_tanggal', 'Hari/tanggal', 'date'), $v('waktu', 'Waktu'), $v('tempat', 'Tempat'), $v('acara', 'Acara')], ['basah', 'visual']],
            ['surat-edaran', 'Surat Edaran', 'arahan', 'naskah-dekan', [$v('tentang', 'Tentang')], ['basah']],
            ['sk', 'Surat Keputusan', 'arahan', 'sk-dekan', [$v('tentang', 'Tentang')], ['basah']],
            ['berita-acara', 'Berita Acara', 'khusus', 'naskah-dekan', [$v('tanggal_kegiatan', 'Tanggal kegiatan', 'date'), $v('tempat', 'Tempat'), $v('hasil', 'Hasil', 'textarea')], ['basah']],
            ['surat-izin-kegiatan', 'Surat Izin Kegiatan', 'korespondensi', 'naskah-dekan', [$v('ormawa', 'Organisasi'), $v('nama_kegiatan', 'Nama kegiatan'), $v('tanggal_mulai', 'Tanggal mulai', 'date'), $v('tanggal_selesai', 'Tanggal selesai', 'date'), $v('tempat', 'Tempat'), $v('penanggung_jawab', 'Penanggung jawab')], ['basah', 'visual']],
            ['pengantar-proposal', 'Pengantar Proposal', 'korespondensi', 'naskah-dekan', [$v('ormawa', 'Organisasi'), $v('nama_kegiatan', 'Nama kegiatan'), $v('tujuan', 'Tujuan surat')], ['basah', 'visual']],
        ];

        $dekan = Jabatan::firstWhere('kode', 'dekan');

        foreach ($jenis as [$kode, $nama, $kelompok, $register, $variabel, $mode]) {
            JenisNaskah::updateOrCreate(['kode' => $kode], [
                'nama' => $nama,
                'kelompok' => $kelompok,
                'register_nomor_id' => RegisterNomor::where('kode', $register)->value('id'),
                'templat_blade' => "naskah.{$kode}",
                'variabel' => $variabel,
                'mode_tanda_tangan_diizinkan' => $mode,
                'jabatan_penanda_tangan_bawaan_id' => $kode === 'nota-dinas' ? null : $dekan?->id,
            ]);
        }
    }
}
