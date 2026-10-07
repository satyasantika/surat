<?php

namespace App\Actions\Naskah;

use App\Actions\Nomor\AmbilNomorBerikutnya;
use App\Contracts\PenandaTangan;
use App\Enums\StatusNaskah;
use App\Exceptions\ModeTidakDidukung;
use App\Jobs\TerbitkanNaskah;
use App\Models\Naskah;
use App\Models\User;
use App\Services\TandaTangan\Basah;
use App\Services\TandaTangan\Tte;
use App\Services\TandaTangan\Visual;
use App\Support\KonteksNaskah;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Tanda tangan naskah (BR-07, BR-08, BR-09): nomor dibentuk di sini (bukan saat draf), tanggal naskah =
 * hari ini, isi dibekukan sebagai snapshot. Hanya pemangku jabatan penanda tangan (atau pejabat yang
 * menandatangani a.n./u.b.) pada mode yang diizinkan jenis naskah.
 */
class TandaTangani
{
    public function __construct(private readonly TransisiNaskah $transisi, private readonly AmbilNomorBerikutnya $nomor) {}

    public function jalankan(Naskah $naskah, User $pelaku): Naskah
    {
        $hasil = $this->transisi->dalamKunci($naskah, function (Naskah $segar) use ($pelaku) {
            $this->transisi->pastikanStatus($segar, [StatusNaskah::MenungguTandaTangan], 'Naskah belum siap atau sudah ditandatangani.');
            $segar->load(['jenis.register', 'penandaTanganJabatan', 'klasifikasiArsip']);

            $this->otorisasi($segar, $pelaku);

            if (! in_array($segar->mode_tanda_tangan, $segar->jenis->mode_tanda_tangan_diizinkan, true)) {
                throw ValidationException::withMessages(['mode_tanda_tangan' => 'Mode tanda tangan tidak diizinkan untuk jenis naskah ini.']);
            }

            $mode = $this->penandaTangan($segar->mode_tanda_tangan)->siapkan($segar, $pelaku);

            $register = $segar->jenis->register;

            if (str_contains($register->pola, '{klasifikasi}') && $segar->klasifikasiArsip === null) {
                throw ValidationException::withMessages(['klasifikasi_arsip_id' => 'Klasifikasi arsip wajib diisi sebelum penomoran.']);
            }

            $tanggal = now();
            $nomor = $this->nomor->jalankan($register, $segar, [
                'klasifikasi' => $segar->klasifikasiArsip ? $segar->klasifikasiArsip->kode : '',
                'kode_jenis' => strtoupper($segar->jenis->kode),
            ], $tanggal);

            $segar->nomor = $nomor->nomor_lengkap;
            $segar->tanggal_naskah = $tanggal;
            $segar->nomor_terpakai_id = $nomor->getKey();
            $segar->penanda_tangan_user_id = $pelaku->getKey();
            $segar->ditandatangani_pada = $tanggal;
            $segar->snapshot = $this->snapshot($segar, $pelaku, $mode);
            $this->transisi->ke($segar, StatusNaskah::Ditandatangani, $pelaku, "Ditandatangani, nomor {$nomor->nomor_lengkap}");

            return $segar;
        });

        TerbitkanNaskah::dispatch($hasil)->afterCommit();

        return $hasil;
    }

    private function otorisasi(Naskah $naskah, User $pelaku): void
    {
        $jabatanDiemban = $pelaku->jabatanAktif();

        if ($jabatanDiemban->contains('id', $naskah->penanda_tangan_jabatan_id)) {
            return;
        }

        // a.n./u.b.: pejabat penanda tangan lain atas nama jabatan naskah.
        if (in_array($naskah->atas_nama, ['an', 'ub'], true)
            && $pelaku->can('naskah.tandatangan')
            && $jabatanDiemban->contains('dapat_menandatangani', true)) {
            return;
        }

        throw new AuthorizationException('Anda bukan pemangku jabatan penanda tangan naskah ini.');
    }

    private function penandaTangan(string $mode): PenandaTangan
    {
        return match ($mode) {
            'basah' => new Basah,
            'visual' => new Visual,
            'tte' => new Tte,
            default => throw new ModeTidakDidukung("Mode {$mode} tidak dikenal."),
        };
    }

    /**
     * @param  array<string, mixed>  $mode
     * @return array<string, mixed>
     */
    private function snapshot(Naskah $naskah, User $penandatangan, array $mode): array
    {
        $konteks = KonteksNaskah::dariDraf($naskah);

        $jabatanPenandatangan = $penandatangan->jabatanAktif()->firstWhere('id', $naskah->penanda_tangan_jabatan_id)
            ?? $penandatangan->jabatanAktif()->firstWhere('dapat_menandatangani', true);
        $atasNama = $naskah->atas_nama !== null && $jabatanPenandatangan?->getKey() !== $naskah->penanda_tangan_jabatan_id;

        $konteks['nomor'] = $naskah->nomor;
        $konteks['tanggal'] = $naskah->tanggal_naskah?->toDateString();
        $konteks['status'] = StatusNaskah::Ditandatangani->value;
        $konteks['penanda_tangan'] = [
            'nama' => $penandatangan->name,
            'nip' => $penandatangan->nip_nim,
            'jabatan' => $atasNama ? $jabatanPenandatangan->nama : $naskah->penandaTanganJabatan->nama,
            'jabatan_dasar' => $atasNama || $naskah->atas_nama !== null ? $naskah->penandaTanganJabatan->nama : null,
        ];
        $konteks['mode'] = $mode['mode'];
        if (isset($mode['ttd_gambar'])) {
            $konteks['ttd_gambar'] = $mode['ttd_gambar'];
        }
        $konteks['ditandatangani_pada'] = now()->toIso8601String();

        // Pola nomor ikut dibekukan agar jelas pola mana yang berlaku saat itu.
        $konteks['pola_nomor'] = $naskah->jenis->register->pola;

        return $konteks;
    }
}
