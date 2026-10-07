<?php

namespace App\Actions\Permohonan;

use App\Actions\Naskah\SimpanDraf;
use App\Models\Jabatan;
use App\Models\JenisNaskah;
use App\Models\KlasifikasiArsip;
use App\Models\Naskah;
use App\Models\Permohonan;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Naskah terpisah: surat pengantar ke rektorat untuk fasilitas yang bukan wewenang fakultas (BR-13). */
class BuatPengantarRektorat
{
    public function __construct(private readonly SimpanDraf $draf) {}

    public function jalankan(Permohonan $permohonan, User $admin): Naskah
    {
        Gate::forUser($admin)->authorize('pengantarRektorat', $permohonan);

        $permohonan->loadMissing(['ormawa', 'jenis']);

        if (! $permohonan->jenis->butuh_fasilitas_rektorat || empty($permohonan->fasilitas_rektorat)) {
            throw ValidationException::withMessages(['fasilitas' => 'Permohonan ini tidak memuat fasilitas rektorat.']);
        }

        if (Naskah::where('permohonan_id', $permohonan->getKey())
            ->when($permohonan->naskah_izin_id, fn ($q, $izin) => $q->where('id', '!=', $izin))
            ->where('status', '!=', 'dibatalkan')->exists()) {
            throw ValidationException::withMessages(['naskah' => 'Surat pengantar rektorat sudah dibuat.']);
        }

        $jenis = JenisNaskah::where('kode', 'surat-dinas')->firstOrFail();

        $daftar = collect($permohonan->fasilitas_rektorat)
            ->map(fn (array $f) => '<li>'.e($f['nama']).' ('.(int) $f['jumlah'].')'.(filled($f['keterangan'] ?? null) ? ' — '.e($f['keterangan']) : '').'</li>')->implode('');

        $naskah = $this->draf->jalankan(null, [
            'jenis_naskah_id' => $jenis->getKey(),
            'klasifikasi_arsip_id' => KlasifikasiArsip::where('kode', 'KM.03.02')->value('id'),
            'klasifikasi_keamanan' => 'biasa',
            'derajat_kecepatan' => 'biasa',
            'perihal' => 'Permohonan fasilitas kegiatan '.$permohonan->nama_kegiatan,
            'penanda_tangan_jabatan_id' => $jenis->jabatan_penanda_tangan_bawaan_id ?? Jabatan::where('kode', 'dekan')->value('id'),
            'mode_tanda_tangan' => $jenis->mode_tanda_tangan_diizinkan[0],
            'data' => ['tujuan' => 'Rektor Universitas Siliwangi'],
            'tujuan' => [['nama' => 'Rektor Universitas Siliwangi']],
            'isi' => "<p>Sehubungan dengan kegiatan {$permohonan->nama_kegiatan} oleh {$permohonan->ormawa->nama}, mohon bantuan fasilitas berikut:</p><ul>{$daftar}</ul>",
        ], $admin);

        $naskah->forceFill(['permohonan_id' => $permohonan->getKey()])->save();

        return $naskah;
    }
}
