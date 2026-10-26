<?php

namespace App\Actions\Kiriman;

use App\Actions\Nomor\AmbilNomorBerikutnya;
use App\Enums\StatusSuratMasuk;
use App\Models\RegisterNomor;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Services\Notifikasi\NotifikasiAlur;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Petugas registrasi menerima kiriman pengirim eksternal: nomor agenda diambil dari register `agenda-masuk` (sama
 * seperti RegistrasiSuratMasuk), tanggal terima menjadi saat diterima, dan aturan penerusan sama: staf khusus
 * menahan surat sampai diteruskan, petugas lain (admin) langsung meneruskan ke dekan. Baris dikunci agar dua petugas
 * yang menerima bersamaan tidak menghasilkan dua nomor.
 */
class TerimaKirimanEksternal
{
    public function __construct(private readonly AmbilNomorBerikutnya $nomor) {}

    public function jalankan(SuratMasuk $surat, User $pelaku): SuratMasuk
    {
        if (! $pelaku->can('masuk.registrasi')) {
            throw new AuthorizationException('Anda tidak berhak menerima kiriman.');
        }

        return DB::transaction(function () use ($surat, $pelaku) {
            $surat = SuratMasuk::lockForUpdate()->findOrFail($surat->getKey());

            if (! $surat->masihDiajukan()) {
                throw ValidationException::withMessages(['status' => 'Kiriman ini sudah diterima.']);
            }

            Gate::forUser($pelaku)->authorize('terima', $surat);

            $surat->status = StatusSuratMasuk::Diterima;
            $surat->tanggal_terima = now();
            $surat->diregistrasi_oleh = $pelaku->getKey();

            if (! $pelaku->hasRole('staf-khusus')) {
                $surat->diteruskan_pada = now();
                $surat->diteruskan_oleh = $pelaku->getKey();
            }

            $agenda = $this->nomor->jalankan(
                RegisterNomor::where('kode', 'agenda-masuk')->firstOrFail(),
                $surat,
                [],
                $surat->tanggal_terima,
            );

            $surat->nomor_agenda = $agenda->nomor_lengkap;
            $surat->save();

            app(NotifikasiAlur::class)->suratMasukBaru($surat, $pelaku);

            return $surat;
        });
    }
}
