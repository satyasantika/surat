<?php

namespace App\Actions\Naskah;

use App\Enums\DerajatKecepatan;
use App\Enums\KlasifikasiKeamanan;
use App\Enums\StatusNaskah;
use App\Models\Jabatan;
use App\Models\JenisNaskah;
use App\Models\Naskah;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Membuat/mengubah draf naskah. Penyusun selalu pengguna terautentikasi; isi kaya disanitasi oleh model. */
class SimpanDraf
{
    /** @param  array<string, mixed>  $input */
    public function jalankan(?Naskah $naskah, array $input, User $pelaku): Naskah
    {
        $naskah === null
            ? Gate::forUser($pelaku)->authorize('create', Naskah::class)
            : Gate::forUser($pelaku)->authorize('update', $naskah);

        $valid = Validator::make($input, [
            'jenis_naskah_id' => ['required', 'uuid', Rule::exists('jenis_naskah', 'id')->where('aktif', true)],
            'klasifikasi_arsip_id' => ['nullable', 'uuid', 'exists:klasifikasi_arsip,id'],
            'klasifikasi_keamanan' => ['required', Rule::enum(KlasifikasiKeamanan::class)],
            'derajat_kecepatan' => ['required', Rule::enum(DerajatKecepatan::class)],
            'perihal' => ['required', 'string', 'max:500'],
            'isi' => ['nullable', 'string', 'max:200000'],
            'penanda_tangan_jabatan_id' => ['required', 'uuid', Rule::exists('jabatan', 'id')->where('dapat_menandatangani', true)],
            'mode_tanda_tangan' => ['required', Rule::in(array_keys(JenisNaskah::MODE_TANDA_TANGAN))],
            'atas_nama' => ['nullable', Rule::in(['an', 'ub', 'plt', 'plh'])],
            'data' => ['array'],
            'tujuan' => ['array'],
            'tujuan.*.nama' => ['required', 'string', 'max:255'],
            'tujuan.*.user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'tembusan' => ['array'],
            'tembusan.*.nama' => ['required', 'string', 'max:255'],
            'tembusan.*.user_id' => ['nullable', 'uuid', 'exists:users,id'],
        ])->validate();

        $jenis = JenisNaskah::findOrFail($valid['jenis_naskah_id']);

        if ($naskah !== null && $naskah->jenis_naskah_id !== $jenis->getKey()) {
            throw ValidationException::withMessages(['jenis_naskah_id' => 'Jenis naskah tidak dapat diganti setelah draf dibuat.']);
        }

        if (! in_array($valid['mode_tanda_tangan'], $jenis->mode_tanda_tangan_diizinkan, true)) {
            throw ValidationException::withMessages(['mode_tanda_tangan' => 'Mode tanda tangan tidak diizinkan untuk jenis naskah ini.']);
        }

        if ($jenis->kelompok === 'korespondensi' && $valid['tujuan'] === []) {
            throw ValidationException::withMessages(['tujuan' => 'Naskah korespondensi wajib memiliki tujuan.']);
        }

        $data = $this->validasiVariabel($jenis, $valid['data'] ?? []);

        return DB::transaction(function () use ($naskah, $valid, $data, $pelaku) {
            $kolom = [
                'jenis_naskah_id' => $valid['jenis_naskah_id'],
                'klasifikasi_arsip_id' => $valid['klasifikasi_arsip_id'] ?? null,
                'klasifikasi_keamanan' => $valid['klasifikasi_keamanan'],
                'derajat_kecepatan' => $valid['derajat_kecepatan'],
                'perihal' => $valid['perihal'],
                'data' => $data,
                'isi' => $valid['isi'] ?? null,
                'penanda_tangan_jabatan_id' => $valid['penanda_tangan_jabatan_id'],
                'mode_tanda_tangan' => $valid['mode_tanda_tangan'],
                'atas_nama' => $valid['atas_nama'] ?? null,
            ];

            if ($naskah === null) {
                $naskah = new Naskah($kolom);
                $naskah->penyusun_id = $pelaku->getKey();
                $naskah->status = StatusNaskah::Draf;
                $naskah->save();
            } else {
                $naskah->fill($kolom);
                // Naskah yang dikembalikan kembali menjadi draf setelah penyusun mengubahnya.
                if ($naskah->status === StatusNaskah::Dikembalikan) {
                    $naskah->status = StatusNaskah::Draf;
                }
                $naskah->save();
            }

            $naskah->semuaTujuan()->delete();

            foreach (['tujuan', 'tembusan'] as $jenisTujuan) {
                foreach (array_values($valid[$jenisTujuan] ?? []) as $urutan => $baris) {
                    $naskah->semuaTujuan()->create([
                        'jenis' => $jenisTujuan, 'nama' => $baris['nama'], 'user_id' => $baris['user_id'] ?? null, 'urutan' => $urutan,
                    ]);
                }
            }

            return $naskah->load(['tujuan', 'tembusan']);
        });
    }

    /**
     * @param  array<string, mixed>  $nilai
     * @return array<string, mixed> hanya kunci yang didefinisikan jenis naskah
     */
    private function validasiVariabel(JenisNaskah $jenis, array $nilai): array
    {
        $aturan = [];
        $nama = [];

        foreach ($jenis->variabel as $v) {
            $kunci = "data.{$v['kunci']}";
            $nama[$kunci] = $v['label'];
            $dasar = ($v['wajib'] ?? false) ? ['required'] : ['nullable'];

            $aturan[$kunci] = [...$dasar, ...match ($v['tipe']) {
                'textarea' => ['string', 'max:5000'],
                'date' => ['date'],
                'number' => ['numeric'],
                'select' => [Rule::in(array_keys($v['opsi'] ?? []))],
                default => ['string', 'max:255'],
            }];
        }

        $validasi = Validator::make(['data' => $nilai], $aturan, [], $nama)->validate();

        return array_intersect_key($validasi['data'] ?? [], array_flip(array_column($jenis->variabel, 'kunci')));
    }

    /** @return list<Jabatan> */
    public static function jabatanPenandaTangan(): array
    {
        return Jabatan::where('dapat_menandatangani', true)->orderBy('urutan')->get()->all();
    }
}
