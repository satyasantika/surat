<?php

namespace App\Actions\Permohonan;

use App\Enums\StatusPermohonan;
use App\Models\Disposisi;
use App\Models\DisposisiPenerima;
use App\Models\Jabatan;
use App\Models\Permohonan;
use App\Models\PersetujuanWd;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Dekan mendisposisikan permohonan ke satu atau lebih jabatan WD (BR-14): membuat disposisi bagi pemangku
 * saat ini dan baris persetujuan_wd menunggu. Dekan juga dapat menolak pada tahap ini.
 */
class DisposisiPermohonan
{
    public function __construct(private readonly TransisiPermohonan $transisi) {}

    /** @param  list<string>  $jabatanWdIds */
    public function jalankan(Permohonan $permohonan, User $dekan, array $jabatanWdIds, ?string $catatan = null): Disposisi
    {
        $jabatanWdIds = array_values(array_unique($jabatanWdIds));

        if ($jabatanWdIds === []) {
            throw ValidationException::withMessages(['jabatan' => 'Pilih minimal satu Wakil Dekan.']);
        }

        return $this->transisi->dalamKunci($permohonan, function (Permohonan $segar) use ($dekan, $jabatanWdIds, $catatan) {
            $this->transisi->pastikanStatus($segar, [StatusPermohonan::DisposisiDekan]);
            Gate::forUser($dekan)->authorize('disposisiDekan', $segar);

            $jabatan = Jabatan::whereIn('id', $jabatanWdIds)->where('kode', 'like', 'wd-%')->get();

            if ($jabatan->count() !== count($jabatanWdIds)) {
                throw ValidationException::withMessages(['jabatan' => 'Hanya jabatan Wakil Dekan yang dapat dipilih.']);
            }

            $pemangku = [];

            foreach ($jabatan as $j) {
                $orang = $j->pemangkuPada(now())?->user;

                if ($orang === null || ! $orang->aktif) {
                    throw ValidationException::withMessages(['jabatan' => "Jabatan {$j->nama} tidak memiliki pemangku aktif."]);
                }

                $pemangku[$j->getKey()] = $orang;
            }

            $disposisi = Disposisi::create([
                'permohonan_id' => $segar->getKey(),
                'dari_user_id' => $dekan->getKey(),
                'dari_jabatan_id' => $dekan->jabatanAktif()->first()?->getKey(),
                'instruksi' => ['tindak_lanjuti'],
                'catatan' => $catatan,
                'batas_waktu' => now()->addDays(7),
                'sifat' => 'biasa',
            ]);

            foreach ($pemangku as $jabatanId => $orang) {
                DisposisiPenerima::create(['disposisi_id' => $disposisi->getKey(), 'user_id' => $orang->getKey(), 'jabatan_id' => $jabatanId]);
                PersetujuanWd::create(['permohonan_id' => $segar->getKey(), 'jabatan_id' => $jabatanId]);
            }

            $this->transisi->ke($segar, StatusPermohonan::PersetujuanWd, $dekan, $catatan ?: 'Didisposisikan ke '.$jabatan->pluck('nama')->implode(', '));

            return $disposisi;
        });
    }

    public function tolak(Permohonan $permohonan, User $dekan, string $alasan): Permohonan
    {
        $alasan = trim($alasan);

        if ($alasan === '') {
            throw ValidationException::withMessages(['alasan' => 'Alasan penolakan wajib diisi.']);
        }

        return $this->transisi->dalamKunci($permohonan, function (Permohonan $segar) use ($dekan, $alasan) {
            $this->transisi->pastikanStatus($segar, [StatusPermohonan::DisposisiDekan]);
            Gate::forUser($dekan)->authorize('disposisiDekan', $segar);
            $this->transisi->ke($segar, StatusPermohonan::Ditolak, $dekan, $alasan);

            return $segar;
        });
    }
}
