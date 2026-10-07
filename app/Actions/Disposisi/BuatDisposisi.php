<?php

namespace App\Actions\Disposisi;

use App\Enums\StatusDisposisiPenerima;
use App\Enums\StatusSuratMasuk;
use App\Models\Disposisi;
use App\Models\DisposisiPenerima;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Services\Notifikasi\NotifikasiAlur;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Membuat disposisi atas surat masuk (BR-05). Disposisi awal hanya oleh dekan (izin disposisi.buat) atau
 * super-admin; disposisi lanjutan oleh penerima yang berhak meneruskan (izin disposisi.teruskan).
 */
class BuatDisposisi
{
    /**
     * @param  list<string>  $penerimaIds
     * @param  list<string>  $instruksi
     */
    public function jalankan(
        SuratMasuk $surat,
        User $pembuat,
        array $penerimaIds,
        array $instruksi,
        ?string $catatan = null,
        ?CarbonInterface $batasWaktu = null,
        ?DisposisiPenerima $induk = null,
    ): Disposisi {
        $this->otorisasi($surat, $pembuat, $induk);

        $penerimaIds = array_values(array_unique($penerimaIds));
        $this->validasi($pembuat, $penerimaIds, $instruksi, $catatan, $batasWaktu);

        return DB::transaction(function () use ($surat, $pembuat, $penerimaIds, $instruksi, $catatan, $batasWaktu, $induk) {
            $disposisi = Disposisi::create([
                'surat_masuk_id' => $surat->getKey(),
                'induk_penerima_id' => $induk?->getKey(),
                'dari_user_id' => $pembuat->getKey(),
                'dari_jabatan_id' => $pembuat->jabatanAktif()->first()?->getKey(),
                'instruksi' => $instruksi,
                'catatan' => $catatan,
                'batas_waktu' => $batasWaktu ?? $surat->derajat_kecepatan->batasWaktu(now()),
                'sifat' => $surat->derajat_kecepatan->value,
            ]);

            foreach (User::whereKey($penerimaIds)->get() as $penerima) {
                DisposisiPenerima::create([
                    'disposisi_id' => $disposisi->getKey(),
                    'user_id' => $penerima->getKey(),
                    'jabatan_id' => $penerima->jabatanAktif()->first()?->getKey(),
                    'status' => StatusDisposisiPenerima::Diterima,
                ]);
            }

            if ($surat->status !== StatusSuratMasuk::Didisposisikan) {
                $surat->update(['status' => StatusSuratMasuk::Didisposisikan]);
            }

            activity('disposisi')->event($induk ? 'teruskan' : 'buat')->performedOn($surat)
                ->withProperties(['disposisi_id' => $disposisi->getKey(), 'penerima' => $penerimaIds, 'instruksi' => $instruksi])
                ->log($induk ? 'Disposisi diteruskan' : 'Disposisi dibuat');

            app(NotifikasiAlur::class)->disposisiBaru($disposisi, $induk);

            return $disposisi;
        });
    }

    private function otorisasi(SuratMasuk $surat, User $pembuat, ?DisposisiPenerima $induk): void
    {
        if ($induk === null) {
            if (! ($pembuat->can('disposisi.buat') && ($pembuat->hasRole('dekan') || $pembuat->hasRole('super-admin')))) {
                throw new AuthorizationException('Hanya dekan yang dapat membuat disposisi awal.');
            }

            return;
        }

        $induk->loadMissing('disposisi');

        if ($induk->user_id !== $pembuat->getKey()
            || $induk->disposisi->surat_masuk_id !== $surat->getKey()
            || $induk->status === StatusDisposisiPenerima::Selesai
            || ! $pembuat->can('disposisi.teruskan')) {
            throw new AuthorizationException('Anda tidak berhak meneruskan disposisi ini.');
        }
    }

    /**
     * @param  list<string>  $penerimaIds
     * @param  list<string>  $instruksi
     */
    private function validasi(User $pembuat, array $penerimaIds, array $instruksi, ?string $catatan, ?CarbonInterface $batasWaktu): void
    {
        $v = validator(
            ['penerima' => $penerimaIds, 'instruksi' => $instruksi, 'catatan' => $catatan, 'batas_waktu' => $batasWaktu],
            [
                'penerima' => ['required', 'array', 'min:1'],
                'penerima.*' => ['uuid', Rule::exists('users', 'id')->where('aktif', true), 'not_in:'.$pembuat->getKey()],
                'instruksi' => ['array', 'required_without:catatan'],
                'instruksi.*' => ['string', Rule::in(array_keys(Disposisi::INSTRUKSI))],
                'catatan' => ['nullable', 'string', 'max:2000', 'required_without:instruksi'],
                'batas_waktu' => ['nullable', 'date', 'after:now'],
            ],
            [
                'penerima.required' => 'Pilih minimal satu penerima.',
                'penerima.*.not_in' => 'Tidak dapat mendisposisikan kepada diri sendiri.',
                'penerima.*.exists' => 'Penerima tidak ditemukan atau tidak aktif.',
                'instruksi.required_without' => 'Pilih instruksi atau isi catatan.',
                'batas_waktu.after' => 'Batas waktu harus di masa depan.',
            ],
        );

        if ($instruksi === [] && blank($catatan)) {
            throw ValidationException::withMessages(['instruksi' => 'Pilih instruksi atau isi catatan.']);
        }

        $v->validate();
    }
}
