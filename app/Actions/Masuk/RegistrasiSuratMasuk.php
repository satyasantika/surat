<?php

namespace App\Actions\Masuk;

use App\Actions\Nomor\AmbilNomorBerikutnya;
use App\Contracts\PenyimpananBerkas;
use App\Enums\DerajatKecepatan;
use App\Enums\KlasifikasiKeamanan;
use App\Enums\StatusSuratMasuk;
use App\Models\RegisterNomor;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Rules\TautanBerkasValid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Registrasi surat masuk (BR-03): nomor agenda dari register agenda-masuk, tautan pindaian wajib. */
class RegistrasiSuratMasuk
{
    public function __construct(private readonly AmbilNomorBerikutnya $nomor, private readonly PenyimpananBerkas $berkas) {}

    /** @param  array<string, mixed>  $data */
    public function jalankan(array $data, User $pelaku): SuratMasuk
    {
        Gate::forUser($pelaku)->authorize('create', SuratMasuk::class);

        $data = Validator::make($data, [
            'tanggal_terima' => ['nullable', 'date'],
            'nomor_surat' => ['required', 'string', 'max:150'],
            'tanggal_surat' => ['required', 'date', 'before_or_equal:tomorrow'],
            'asal' => ['required', 'string', 'max:255'],
            'perihal' => ['required', 'string', 'max:500'],
            'ringkasan' => ['nullable', 'string', 'max:5000'],
            'lampiran' => ['nullable', 'string', 'max:100'],
            'klasifikasi_arsip_id' => ['nullable', 'uuid', 'exists:klasifikasi_arsip,id'],
            'klasifikasi_keamanan' => ['required', Rule::enum(KlasifikasiKeamanan::class)],
            'derajat_kecepatan' => ['required', Rule::enum(DerajatKecepatan::class)],
            'unit_pengolah_id' => ['nullable', 'uuid', 'exists:unit_kerja,id'],
            'pindaian_url' => ['required', 'string', new TautanBerkasValid],
        ], ['pindaian_url.required' => 'Tautan pindaian surat wajib diisi.'])->validate();

        return DB::transaction(function () use ($data, $pelaku) {
            $surat = new SuratMasuk(collect($data)->except('pindaian_url')->all());
            $surat->id = $surat->newUniqueId();
            $surat->tanggal_terima ??= now();
            $surat->status = StatusSuratMasuk::Diterima;
            $surat->diregistrasi_oleh = $pelaku->getKey();

            $agenda = $this->nomor->jalankan(
                RegisterNomor::where('kode', 'agenda-masuk')->firstOrFail(),
                $surat,
                [],
                $surat->tanggal_terima,
            );

            $surat->nomor_agenda = $agenda->nomor_lengkap;
            $surat->save();

            $this->berkas->simpan($surat, 'pindaian', $data['pindaian_url'], 'Pindaian surat', $pelaku);

            return $surat;
        });
    }
}
