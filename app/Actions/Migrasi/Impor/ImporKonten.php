<?php

namespace App\Actions\Migrasi\Impor;

use App\Contracts\PenyimpananBerkas;
use App\Models\Galeri;
use App\Models\ImporLog;
use App\Models\Kabar;
use App\Support\Migrasi\Bantu;
use App\Support\Migrasi\KonteksImpor;
use App\Support\Migrasi\PenguraiTanggal;
use App\Support\Migrasi\PolaContoh;
use App\Support\Migrasi\Sel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/** Blogs → kabar (isi disanitasi oleh model) dan Galleries → galeri (tautan divalidasi menurut tipe). */
class ImporKonten
{
    /** @param  list<array<string, mixed>>  $baris */
    public function blogs(KonteksImpor $k, array $baris): void
    {
        $this->tiap($k, 'Blogs', $baris, 'kabar', function (array $r, string $id) use ($k) {
            $peringatan = [];
            $judul = Sel::teks($r['judul'] ?? null) ?? throw new RuntimeException('Judul kosong.');
            $namaOrmawa = Sel::teks($r['ormawa'] ?? null);
            $ormawaId = Bantu::ormawaDariNama($k, $namaOrmawa);

            if ($namaOrmawa !== null && $ormawaId === null && mb_strtolower($namaOrmawa) !== 'fakultas') {
                $peringatan[] = "Ormawa '{$namaOrmawa}' tidak ditemukan; kabar dicatat sebagai kabar fakultas.";
            }

            $status = match (strtolower((string) Sel::teks($r['status'] ?? null))) {
                'approved' => Kabar::TERBIT, 'pending' => Kabar::DIAJUKAN, 'rejected' => Kabar::DITOLAK, default => null,
            };
            $status ?? $peringatan[] = 'Status tidak dikenal; diset draf.';
            $status ??= Kabar::DRAF;

            $tag = Sel::json($r['tag'] ?? null);
            $tag = is_array($tag) ? $tag : (Sel::teks($r['tag'] ?? null) !== null ? preg_split('/[,;]+/', (string) Sel::teks($r['tag'] ?? null)) : []);
            $tag = array_values(array_unique(array_filter(array_map(fn ($t) => Str::limit(trim((string) $t), 30, ''), $tag ?: []))));

            $pelaksana = $k->pelaksana ?? throw new RuntimeException('Pelaksana impor tidak ditentukan.');
            $kabar = new Kabar(['ormawa_id' => $ormawaId, 'judul' => Str::limit($judul, 200, ''), 'subjudul' => Str::limit((string) Sel::teks($r['subjudul'] ?? null), 250, '') ?: null, 'isi' => (string) Sel::teks($r['uraian'] ?? null), 'tag' => array_slice($tag, 0, 10) ?: null]);
            $kabar->forceFill([
                'status' => $status, 'catatan_admin' => Sel::teks($r['adminNote'] ?? null), 'penulis_id' => $pelaksana->getKey(),
                'disetujui_oleh' => $status === Kabar::TERBIT ? $pelaksana->getKey() : null,
                'terbit_pada' => $status === Kabar::TERBIT ? (PenguraiTanggal::urai($r['tanggal'] ?? null) ?? now()) : null,
                'sumber_id_lama' => $id,
            ])->save();

            $foto = Sel::teks($r['foto'] ?? null);

            if ($foto !== null) {
                Bantu::urlBerkas($foto) !== null
                    ? app(PenyimpananBerkas::class)->simpan($kabar, 'foto', $foto, 'Foto sampul', null)
                    : $peringatan[] = 'Foto sampul dibuang (contoh/tidak memenuhi kebijakan berkas).';
            }

            return [$kabar->getKey(), $peringatan];
        });
    }

    /** @param  list<array<string, mixed>>  $baris */
    public function galeri(KonteksImpor $k, array $baris): void
    {
        $this->tiap($k, 'Galleries', $baris, 'galeri', function (array $r, string $id) use ($k) {
            $peringatan = [];
            $url = Sel::teks($r['url'] ?? null) ?? throw new RuntimeException('URL kosong.');
            $tipeLama = strtolower((string) Sel::teks($r['type'] ?? null));
            $tipe = match (true) {
                $tipeLama === 'instagram' || str_contains(strtolower($url), 'instagram.com') => 'instagram',
                $tipeLama === 'video' => 'video',
                in_array($tipeLama, ['photo', 'foto', 'image'], true) => 'foto',
                default => throw new RuntimeException("Tipe '{$tipeLama}' tidak dikenal."),
            };

            $requestId = Sel::teks($r['requestId'] ?? null);

            try {
                $g = Galeri::create([
                    'ormawa_id' => Bantu::ormawaDariNama($k, Sel::teks($r['ormawa'] ?? null)),
                    'permohonan_id' => $requestId !== null ? ($k->peta['permohonan'][$requestId] ?? $k->sebelumnya('Requests', $requestId)) : null,
                    'judul' => Str::limit(Sel::teks($r['title'] ?? null) ?? 'Galeri', 200, ''), 'tipe' => $tipe, 'url' => $url,
                    'aktif' => Sel::bool($r['isActive'] ?? null),
                ]);
            } catch (ValidationException $e) {
                throw new RuntimeException('Tautan tidak lolos validasi galeri: '.collect($e->errors())->flatten()->first());
            }

            $g->forceFill(['sumber_id_lama' => $id])->save();

            return [$g->getKey(), $peringatan];
        });
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @param  callable(array<string, mixed>, string): array{0: string, 1: list<string>}  $buat
     */
    private function tiap(KonteksImpor $k, string $sheet, array $baris, string $tabel, callable $buat): void
    {
        foreach ($baris as $r) {
            $id = Sel::teks($r['id'] ?? null);

            if ($id === null) {
                continue;
            }

            if (PolaContoh::barisContoh($r)) {
                $k->catat($sheet, $id, ImporLog::LEWATI, 'Data contoh templat (S-13) tidak diimpor.');

                continue;
            }

            if ($baru = $k->sebelumnya($sheet, $id)) {
                $k->catat($sheet, $id, ImporLog::LEWATI, 'Sudah diimpor pada impor sebelumnya.', $tabel, $baru);

                continue;
            }

            try {
                [$idBaru, $peringatan] = DB::transaction(fn () => $buat($r, $id));
                $k->catat($sheet, $id, $peringatan === [] ? ImporLog::OK : ImporLog::PERINGATAN, $peringatan === [] ? null : implode(' ', $peringatan), $tabel, $idBaru);
            } catch (Throwable $e) {
                $k->catat($sheet, $id, ImporLog::GALAT, Str::limit($e->getMessage(), 300));
            }
        }
    }
}
