<?php

namespace App\Actions\Permohonan;

use App\Contracts\PenyimpananBerkas;
use App\Enums\StatusPermohonan;
use App\Jobs\CatatPemakaianRuangan;
use App\Models\Permohonan;
use App\Models\PermohonanRuangan;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Rules\TautanBerkasValid;
use App\Services\Ruangan\KetersediaanRuangan;
use App\Support\KunciRuangan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * "Kirim hasil ke pengirim" (BR-28): petugas unit pembuat yang ditugaskan kasubag mengunggah ulang TAUTAN hasil cetak
 * (tanpa unggah berkas, STANDAR-TEKNIS §1a) lalu permohonan langsung `selesai` dan pengirim diberi tahu beserta
 * tautan lacak. Jalur ini berlaku untuk semua jenis permohonan, termasuk yang tanpa surat izin otomatis; jalur izin
 * lama (draf naskah -> terbit -> selesai oleh listener) tidak berubah.
 *
 * Ruangan yang ditahan dicek ulang di dalam kunci lalu dikonfirmasi, sama seperti saat naskah izin terbit.
 */
class KirimHasilPermohonan
{
    public function __construct(
        private readonly TransisiPermohonan $transisi,
        private readonly PenyimpananBerkas $berkas,
        private readonly KetersediaanRuangan $ketersediaan,
    ) {}

    public function jalankan(Permohonan $permohonan, User $petugas, string $url, ?string $catatan = null): Permohonan
    {
        $data = Validator::make(['url' => $url, 'catatan' => $catatan], [
            'url' => ['required', 'string', new TautanBerkasValid],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ], ['url.required' => 'Tautan hasil cetak wajib diisi.'])->validate();

        $butir = $permohonan->ruangan()->where('status', PermohonanRuangan::DITAHAN)->get()
            ->map(fn (PermohonanRuangan $r) => ['kode' => $r->kode_ruangan, 'tanggal' => $r->tanggal->toDateString()])->all();

        $selesai = KunciRuangan::dengan($butir, fn () => $this->transisi->dalamKunci($permohonan, function (Permohonan $segar) use ($petugas, $data) {
            $this->transisi->pastikanStatus($segar, [StatusPermohonan::Pembuatan]);
            Gate::forUser($petugas)->authorize('kirimHasil', $segar);

            $baris = $segar->ruangan()->where('status', PermohonanRuangan::DITAHAN)->get();

            foreach ($baris as $r) {
                if ($this->ketersediaan->terpakai($r->kode_ruangan, $r->tanggal->toDateString(), $r->sesi, $segar->getKey())) {
                    throw ValidationException::withMessages(['ruangan' => "Ruangan {$r->nama_ruangan} kini sudah dipakai pihak lain; hasil tidak dapat dikirim sebelum admin meninjau."]);
                }
            }

            $segar->ruangan()->where('status', PermohonanRuangan::DITAHAN)->update(['status' => PermohonanRuangan::DIKONFIRMASI]);
            $this->berkas->simpan($segar, TautanBerkas::JENIS_HASIL, $data['url'], 'Hasil cetak', $petugas);

            $this->transisi->ke($segar, StatusPermohonan::Selesai, $petugas, 'Hasil dikirim kepada pengirim'.(filled($data['catatan']) ? ": {$data['catatan']}" : ''));

            return $segar;
        }));

        CatatPemakaianRuangan::dispatch($selesai)->afterCommit();

        return $selesai;
    }
}
