<?php

namespace App\Actions\Permohonan;

use App\Contracts\PenyimpananBerkas;
use App\Enums\StatusPermohonan;
use App\Models\Permohonan;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Rules\TautanBerkasValid;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * Ormawa merevisi permohonan yang dikembalikan lalu mengajukannya kembali ke validasi admin. Tanggal dan
 * ruangan tidak dapat diubah di sini (ruangan sudah ditahan); ajukan permohonan baru untuk jadwal berbeda.
 */
class AjukanUlangPermohonan
{
    public function __construct(private readonly TransisiPermohonan $transisi, private readonly PenyimpananBerkas $berkas) {}

    /** @param  array<string, mixed>  $perubahan  nama_kegiatan, perihal, nomor_surat_ormawa, deskripsi, tempat_lain, alasan_mendesak, berkas */
    public function jalankan(Permohonan $permohonan, User $pelaku, array $perubahan = [], ?string $catatan = null): Permohonan
    {
        $valid = Validator::make($perubahan, [
            'nama_kegiatan' => ['sometimes', 'required', 'string', 'max:255'],
            'perihal' => ['sometimes', 'required', 'string', 'max:255'],
            'nomor_surat_ormawa' => ['sometimes', 'nullable', 'string', 'max:100'],
            'deskripsi' => ['sometimes', 'required', 'string', 'max:10000'],
            'tempat_lain' => ['sometimes', 'nullable', 'string', 'max:255'],
            'alasan_mendesak' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'berkas' => ['sometimes', 'array'],
            'berkas.*' => ['string', new TautanBerkasValid],
        ])->validate();

        return $this->transisi->dalamKunci($permohonan, function (Permohonan $segar) use ($pelaku, $valid, $catatan) {
            $this->transisi->pastikanStatus($segar, [StatusPermohonan::Dikembalikan]);
            $segar->loadMissing('ormawa');
            Gate::forUser($pelaku)->authorize('revisi', $segar);

            $segar->fill(collect($valid)->except('berkas')->all());

            foreach ($valid['berkas'] ?? [] as $jenis => $url) {
                if (! in_array($jenis, $segar->jenis->berkas_wajib, true)) {
                    continue;
                }

                $tautan = TautanBerkas::where('pemilik_type', $segar->getMorphClass())->where('pemilik_id', $segar->getKey())->where('jenis', $jenis)->first();
                $tautan ? $tautan->update(['url' => $url]) : $this->berkas->simpan($segar, $jenis, $url, null, $pelaku);
            }

            $this->transisi->ke($segar, StatusPermohonan::ValidasiAdmin, $pelaku, $catatan ?: 'Diajukan ulang setelah revisi');

            return $segar;
        });
    }
}
