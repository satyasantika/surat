<?php

namespace App\Actions\Migrasi\Impor;

use App\Contracts\PenyimpananBerkas;
use App\Models\ImporLog;
use App\Models\Ormawa;
use App\Rules\TautanBerkasValid;
use App\Support\Migrasi\KonteksImpor;
use App\Support\Migrasi\Pemetaan;
use App\Support\Migrasi\PolaContoh;
use App\Support\Migrasi\Sel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * Ormawa_Profiles → ormawa + tautan logo/SK (§4). Nomor/periode SK tidak ada di sumber sehingga tidak
 * dikarang: SK berupa tautan dan dicatat "perlu dilengkapi" untuk diisi admin.
 */
class ImporOrmawa
{
    /** @param  list<array<string, mixed>>  $baris */
    public function jalankan(KonteksImpor $k, array $baris): void
    {
        foreach ($baris as $r) {
            $id = Sel::teks($r['id'] ?? null);

            if ($id === null) {
                continue;
            }

            if (PolaContoh::barisContoh($r)) {
                $k->catat('Ormawa_Profiles', $id, ImporLog::LEWATI, 'Data contoh templat (S-13) tidak diimpor.');

                continue;
            }

            $m = $k->pemetaan->ormawa($id);

            if ($m !== null && Pemetaan::ya($m['buang'] ?? '')) {
                $k->catat('Ormawa_Profiles', $id, ImporLog::LEWATI, 'Ditandai buang di ormawa.csv.');

                continue;
            }

            if ($baru = $k->sebelumnya('Ormawa_Profiles', $id)) {
                $k->catat('Ormawa_Profiles', $id, ImporLog::LEWATI, 'Sudah diimpor pada impor sebelumnya.', 'ormawa', $baru);

                continue;
            }

            try {
                [$ormawa, $peringatan] = DB::transaction(fn () => $this->buat($r, $id, $m ?? throw new \RuntimeException('Tidak ada baris di ormawa.csv.')));
                $k->catat('Ormawa_Profiles', $id, $peringatan === [] ? ImporLog::OK : ImporLog::PERINGATAN, $peringatan === [] ? null : implode(' ', $peringatan), 'ormawa', $ormawa->getKey());
            } catch (Throwable $e) {
                $k->catat('Ormawa_Profiles', $id, ImporLog::GALAT, Str::limit($e->getMessage(), 300));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $r
     * @param  array<string, string>  $m
     * @return array{0: Ormawa, 1: list<string>}
     */
    private function buat(array $r, string $id, array $m): array
    {
        $peringatan = [];
        $nama = ($m['nama'] ?? '') !== '' ? $m['nama'] : (string) Sel::teks($r['nama'] ?? null);

        if (Ormawa::where('nama', $nama)->where(fn ($q) => $q->whereNull('sumber_id_lama')->orWhere('sumber_id_lama', '!=', $id))->exists()) {
            throw new \RuntimeException("Nama ormawa '{$nama}' sudah ada; perbaiki ormawa.csv.");
        }

        $handle = Sel::teks($r['handle'] ?? null);

        if ($handle !== null && preg_match('/^@?[A-Za-z0-9._]{1,100}$/', $handle) !== 1) {
            $peringatan[] = "Akun media '{$handle}' tidak sah; dikosongkan.";
            $handle = null;
        }

        $surel = Sel::teks($r['email'] ?? null);

        if ($surel !== null && (! filter_var($surel, FILTER_VALIDATE_EMAIL) || strlen($surel) > 150 || PolaContoh::kolomContoh($surel))) {
            $peringatan[] = 'Surel organisasi tidak sah; dikosongkan.';
            $surel = null;
        }

        $ormawa = new Ormawa([
            'nama' => $nama, 'slug' => ($m['slug'] ?? '') !== '' ? $m['slug'] : null, 'tingkat' => $m['tingkat'],
            'akun_media' => $handle, 'surel_organisasi' => $surel,
            'visi' => Str::limit((string) Sel::teks($r['visi'] ?? null), 5000, ''), 'misi' => Str::limit((string) Sel::teks($r['misi'] ?? null), 5000, ''),
            'aktif' => true, 'sumber_id_lama' => $id,
        ]);
        $ormawa->visi = $ormawa->visi ?: null;
        $ormawa->misi = $ormawa->misi ?: null;
        $ormawa->save();

        $this->tautan($ormawa, 'logo', Sel::teks($r['logoUrl'] ?? null), 'Logo', $peringatan, 'Logo');
        $skUrl = Sel::teks($r['skUrl'] ?? null);
        $this->tautan($ormawa, 'sk', $skUrl, 'SK Kepengurusan (lama)', $peringatan, 'SK');
        $peringatan[] = 'perlu_dilengkapi: nomor dan periode SK kepengurusan diisi manual'.($skUrl === null ? ' (tidak ada tautan SK).' : '.');

        return [$ormawa, $peringatan];
    }

    /** @param  list<string>  $peringatan */
    private function tautan(Model $pemilik, string $jenis, ?string $url, string $label, array &$peringatan, string $nama): void
    {
        if ($url === null) {
            return;
        }

        if (PolaContoh::kolomContoh($url) || Validator::make(['u' => $url], ['u' => [new TautanBerkasValid(false)]])->fails()) {
            $peringatan[] = "Tautan {$nama} dibuang (contoh/tidak memenuhi kebijakan berkas).";

            return;
        }

        app(PenyimpananBerkas::class)->simpan($pemilik, $jenis, $url, $label, null);
    }
}
