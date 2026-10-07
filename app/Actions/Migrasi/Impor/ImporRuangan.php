<?php

namespace App\Actions\Migrasi\Impor;

use App\Models\ImporLog;
use App\Models\RuanganLokal;
use App\Support\Migrasi\KonteksImpor;
use App\Support\Migrasi\PolaContoh;
use App\Support\Migrasi\Sel;
use App\Support\Pengaturan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Rooms → ruangan_lokal (mode lokal) atau hanya dicocokkan ke kode Aset (mode aset_api);
 * RektoratRooms → katalog fasilitas rektorat di pengaturan (nama & kategori; kontak dibuang, BR-13).
 */
class ImporRuangan
{
    /** @param  list<array<string, mixed>>  $baris */
    public function rooms(KonteksImpor $k, array $baris): void
    {
        foreach ($baris as $r) {
            $id = Sel::teks($r['id'] ?? null);

            if ($id === null) {
                continue;
            }

            if (PolaContoh::barisContoh($r)) {
                $k->catat('Rooms', $id, ImporLog::LEWATI, 'Data contoh templat (S-13) tidak diimpor.');

                continue;
            }

            $kode = $k->pemetaan->ruangan($id);

            if ($kode !== null) {
                $k->peta['ruangan'][$id] = $kode;
            }

            if ($k->modeRuangan === 'aset_api') {
                $k->catat('Rooms', $id, ImporLog::LEWATI, "Mode aset_api: dicocokkan ke ruangan Aset '{$kode}'; tidak diimpor.");

                continue;
            }

            if ($baru = $k->sebelumnya('Rooms', $id)) {
                $k->catat('Rooms', $id, ImporLog::LEWATI, 'Sudah diimpor pada impor sebelumnya.', 'ruangan_lokal', $baru);

                continue;
            }

            try {
                $ruangan = DB::transaction(function () use ($r, $id, $kode) {
                    $nama = Sel::teks($r['nama'] ?? null) ?? throw new \RuntimeException('Nama ruangan kosong.');
                    $kodeBaru = $kode ?? Str::upper(Str::limit(Str::slug($nama), 26, '')).'-'.substr(md5($id), 0, 3);

                    return RuanganLokal::create([
                        'kode' => $kodeBaru, 'nama' => Str::limit($nama, 150, ''), 'gedung' => Str::limit((string) Sel::teks($r['gedung'] ?? null), 100, '') ?: null,
                        'kapasitas' => Sel::int($r['kapasitas'] ?? null), 'fasilitas' => Sel::teks($r['fasilitas'] ?? null),
                        'dalam_perawatan' => Sel::bool($r['isMaintenance'] ?? null), 'tampil_katalog' => Sel::bool($r['tampilKatalog'] ?? null, true),
                        'sumber_id_lama' => $id,
                    ]);
                });
                $k->peta['ruangan'][$id] = $ruangan->kode;
                $k->catat('Rooms', $id, ImporLog::OK, 'PIC ruangan tidak diimpor (dikelola di Aset).', 'ruangan_lokal', $ruangan->getKey());
            } catch (Throwable $e) {
                $k->catat('Rooms', $id, ImporLog::GALAT, Str::limit($e->getMessage(), 300));
            }
        }
    }

    /** @param  list<array<string, mixed>>  $baris */
    public function rektorat(KonteksImpor $k, array $baris): void
    {
        /** @var list<array{nama: string, kategori: ?string}> $katalog */
        $katalog = Pengaturan::get('fasilitas_rektorat_katalog', []);
        $ada = array_map(fn ($f) => mb_strtolower($f['nama']), $katalog);
        $berubah = false;

        foreach ($baris as $r) {
            $id = Sel::teks($r['id'] ?? null) ?? Sel::teks($r['nama'] ?? null);
            $nama = Sel::teks($r['nama'] ?? null);

            if ($id === null) {
                continue;
            }

            if ($nama === null || PolaContoh::barisContoh($r)) {
                $k->catat('RektoratRooms', $id, ImporLog::LEWATI, 'Kosong atau data contoh templat (S-13).');

                continue;
            }

            if (in_array(mb_strtolower($nama), $ada, true)) {
                $k->catat('RektoratRooms', $id, ImporLog::LEWATI, 'Sudah ada di katalog.');

                continue;
            }

            $katalog[] = ['nama' => Str::limit($nama, 150, ''), 'kategori' => Sel::teks($r['kategori'] ?? null)];
            $ada[] = mb_strtolower($nama);
            $berubah = true;
            $k->catat('RektoratRooms', $id, ImporLog::OK, 'Kontak dibuang (bukan wewenang fakultas, BR-13).', 'pengaturan');
        }

        $berubah ? Pengaturan::set('fasilitas_rektorat_katalog', $katalog) : null;
    }
}
