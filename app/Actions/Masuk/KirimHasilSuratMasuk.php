<?php

namespace App\Actions\Masuk;

use App\Contracts\PenyimpananBerkas;
use App\Enums\StatusDisposisiPenerima;
use App\Enums\StatusSuratMasuk;
use App\Models\DisposisiPenerima;
use App\Models\SuratMasuk;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Rules\TautanBerkasValid;
use App\Services\Notifikasi\NotifikasiAlur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * "Unggah hasil & kirim ke pengirim" untuk surat masuk (BR-28): petugas unit pembuat yang ditugaskan kasubag
 * mengunggah ulang TAUTAN hasil cetak (jenis hasil_cetak, §1a), surat menjadi `selesai`, seluruh penerima disposisi
 * yang masih terbuka diselesaikan otomatis, dan pengirim diberi tahu dengan tautan lacak. Tercatat di jejak aktivitas
 * dan linimasa; kontak pengirim tidak pernah dicatat.
 */
class KirimHasilSuratMasuk
{
    public function __construct(private readonly PenyimpananBerkas $berkas) {}

    public function jalankan(SuratMasuk $surat, User $petugas, string $url, ?string $catatan = null): SuratMasuk
    {
        $data = Validator::make(['url' => $url, 'catatan' => $catatan], [
            'url' => ['required', 'string', new TautanBerkasValid],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ], ['url.required' => 'Tautan hasil cetak wajib diisi.'])->validate();

        return DB::transaction(function () use ($surat, $petugas, $data) {
            $surat = SuratMasuk::lockForUpdate()->findOrFail($surat->getKey());
            Gate::forUser($petugas)->authorize('kirimHasil', $surat);

            if ($surat->status !== StatusSuratMasuk::Didisposisikan) {
                throw ValidationException::withMessages(['status' => 'Surat ini tidak sedang diproses.']);
            }

            $this->berkas->simpan($surat, TautanBerkas::JENIS_HASIL, $data['url'], 'Hasil cetak', $petugas);

            $surat->forceFill([
                'status' => StatusSuratMasuk::Selesai,
                'hasil_dikirim_pada' => now(),
                'hasil_dikirim_oleh' => $petugas->getKey(),
            ])->save();

            DisposisiPenerima::whereHas('disposisi', fn ($q) => $q->where('surat_masuk_id', $surat->getKey()))
                ->where('status', '!=', StatusDisposisiPenerima::Selesai->value)
                ->update(['status' => StatusDisposisiPenerima::Selesai->value, 'selesai_pada' => now()]);

            activity('surat-masuk')->event('hasil-dikirim')->performedOn($surat)->causedBy($petugas)
                ->withProperties(array_filter(['catatan' => $data['catatan']]))
                ->log('Hasil dikirim kepada pengirim; surat selesai');

            app(NotifikasiAlur::class)->hasilSuratMasukDikirim($surat, $petugas);

            return $surat;
        });
    }
}
