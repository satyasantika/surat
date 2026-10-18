<?php

namespace App\Actions\Masuk;

use App\Actions\Nomor\AmbilNomorBerikutnya;
use App\Contracts\PenyimpananBerkas;
use App\Enums\DerajatKecepatan;
use App\Enums\JenisPengirim;
use App\Enums\KlasifikasiKeamanan;
use App\Enums\StatusSuratMasuk;
use App\Models\RegisterNomor;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Rules\TautanBerkasValid;
use App\Services\Notifikasi\NotifikasiAlur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Registrasi surat masuk (BR-03): nomor agenda dari register agenda-masuk, tautan pindaian wajib.
 * Jenis pengirim (BR-23): ormawa, prodi (wajib unit kerja), atau eksternal (nama + kontak surel/WA wajib bila diregistrasi staf khusus). Surat yang diregistrasi staf khusus menunggu diteruskan ke dekan (BR-24); registrasi oleh admin
 * (jalur lama) langsung dianggap diteruskan.
 */
class RegistrasiSuratMasuk
{
    public function __construct(private readonly AmbilNomorBerikutnya $nomor, private readonly PenyimpananBerkas $berkas) {}

    /** @param  array<string, mixed>  $data */
    public function jalankan(array $data, User $pelaku): SuratMasuk
    {
        Gate::forUser($pelaku)->authorize('create', SuratMasuk::class);

        // Staf khusus meregistrasi atas nama pengirim eksternal: nama dan kontak wajib agar kode lacak dapat dikirim.
        $wajibKontak = $pelaku->hasRole('staf-khusus') && ($data['jenis_pengirim'] ?? JenisPengirim::Eksternal->value) === JenisPengirim::Eksternal->value;

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
            'jenis_pengirim' => ['nullable', Rule::enum(JenisPengirim::class)],
            'pengirim_unit_kerja_id' => [Rule::requiredIf(($data['jenis_pengirim'] ?? null) === JenisPengirim::Prodi->value), 'nullable', 'uuid', Rule::exists('unit_kerja', 'id')->where('aktif', true)],
            'pengirim_nama' => [Rule::requiredIf($wajibKontak), 'nullable', 'string', 'max:150'],
            'pengirim_instansi' => ['nullable', 'string', 'max:255'],
            'pengirim_surel' => ['nullable', 'email', 'max:150', Rule::requiredIf($wajibKontak && blank($data['pengirim_wa'] ?? null))],
            'pengirim_wa' => ['nullable', 'string', 'regex:/^\+?[0-9][0-9\s-]{7,18}$/', Rule::requiredIf($wajibKontak && blank($data['pengirim_surel'] ?? null))],
        ], [
            'pindaian_url.required' => 'Tautan pindaian surat wajib diisi.',
            'pengirim_unit_kerja_id.required' => 'Pilih program studi pengirim.',
            'pengirim_nama.required' => 'Nama pengirim eksternal wajib diisi.',
            'pengirim_surel.required' => 'Isi surel atau nomor WhatsApp pengirim agar kode lacak dapat dikirim.',
            'pengirim_wa.required' => 'Isi surel atau nomor WhatsApp pengirim agar kode lacak dapat dikirim.',
            'pengirim_wa.regex' => 'Nomor WhatsApp tidak valid.',
        ])->validate();

        $data['jenis_pengirim'] = $data['jenis_pengirim'] ?? JenisPengirim::Eksternal->value;

        foreach (['pengirim_unit_kerja_id', 'pengirim_nama', 'pengirim_instansi', 'pengirim_surel', 'pengirim_wa'] as $kolom) {
            $data[$kolom] = filled($data[$kolom] ?? null) ? $data[$kolom] : null;
        }

        return DB::transaction(function () use ($data, $pelaku) {
            $surat = new SuratMasuk(collect($data)->except('pindaian_url')->all());
            $surat->id = $surat->newUniqueId();
            $surat->tanggal_terima ??= now();
            $surat->status = StatusSuratMasuk::Diterima;
            $surat->diregistrasi_oleh = $pelaku->getKey();

            // Staf khusus menahan surat sampai ia meneruskannya; jalur lama (admin) langsung ke dekan.
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

            $this->berkas->simpan($surat, 'pindaian', $data['pindaian_url'], 'Pindaian surat', $pelaku);
            app(NotifikasiAlur::class)->suratMasukBaru($surat, $pelaku);

            return $surat;
        });
    }
}
