<?php

namespace App\Actions\Migrasi\Impor;

use App\Contracts\PenyimpananBerkas;
use App\Models\ImporLog;
use App\Models\PengurusOrmawa;
use App\Rules\TautanBerkasValid;
use App\Support\Migrasi\KonteksImpor;
use App\Support\Migrasi\Pemetaan;
use App\Support\Migrasi\PolaContoh;
use App\Support\Migrasi\Sel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/** Pengurus → pengurus_ormawa (§4): tanpa akun (user_id null, ditautkan via NIM); nama & jabatan saja yang tampil publik. */
class ImporPengurus
{
    /** @param  list<array<string, mixed>>  $baris */
    public function jalankan(KonteksImpor $k, array $baris): void
    {
        foreach ($baris as $r) {
            $nim = Sel::teks($r['id'] ?? null);
            $ormawaLama = Sel::teks($r['ormawaId'] ?? null);
            $nama = Sel::teks($r['nama'] ?? null);

            if ($nim === null && $nama === null) {
                continue;
            }

            $kunci = ($ormawaLama ?? '?').'|'.($nim ?? $nama);

            if (PolaContoh::barisContoh($r)) {
                $k->catat('Pengurus', $kunci, ImporLog::LEWATI, 'Data contoh templat (S-13) tidak diimpor.');

                continue;
            }

            if ($ormawaLama !== null && ($m = $k->pemetaan->ormawa($ormawaLama)) !== null && Pemetaan::ya($m['buang'] ?? '')) {
                $k->catat('Pengurus', $kunci, ImporLog::LEWATI, 'Ormawa ditandai buang.');

                continue;
            }

            if ($baru = $k->sebelumnya('Pengurus', $kunci)) {
                $k->catat('Pengurus', $kunci, ImporLog::LEWATI, 'Sudah diimpor pada impor sebelumnya.', 'pengurus_ormawa', $baru);

                continue;
            }

            try {
                $ormawaId = $k->peta['Ormawa_Profiles'][$ormawaLama ?? ''] ?? $k->sebelumnya('Ormawa_Profiles', $ormawaLama ?? '')
                    ?? throw new \RuntimeException("Ormawa '{$ormawaLama}' belum diimpor.");
                [$p, $peringatan] = DB::transaction(fn () => $this->buat($r, $ormawaId, $nim, $nama));
                $k->catat('Pengurus', $kunci, $peringatan === [] ? ImporLog::OK : ImporLog::PERINGATAN, $peringatan === [] ? null : implode(' ', $peringatan), 'pengurus_ormawa', $p->getKey());
            } catch (Throwable $e) {
                $k->catat('Pengurus', $kunci, ImporLog::GALAT, Str::limit($e->getMessage(), 300));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array{0: PengurusOrmawa, 1: list<string>}
     */
    private function buat(array $r, string $ormawaId, ?string $nim, ?string $nama): array
    {
        $peringatan = [];
        $nama ?? throw new \RuntimeException('Nama pengurus kosong.');

        if ($nim !== null && preg_match('/^[0-9A-Za-z.-]{1,20}$/', $nim) !== 1) {
            $peringatan[] = "NIM '{$nim}' tidak sah; dikosongkan.";
            $nim = null;
        }

        [$jabatan, $teks] = $this->jabatan(Sel::teks($r['jabatan'] ?? null));
        $hp = Sel::telepon($r['hp'] ?? null);

        if ($hp === null && Sel::teks($r['hp'] ?? null) !== null) {
            $peringatan[] = 'Nomor telepon tidak sah; dikosongkan.';
        }

        $p = new PengurusOrmawa([
            'ormawa_id' => $ormawaId, 'nama' => Str::limit($nama, 150, ''), 'nim' => $nim,
            'prodi' => Str::limit((string) Sel::teks($r['jurusan'] ?? null), 100, '') ?: null,
            'jabatan' => $jabatan, 'jabatan_teks' => $teks, 'telepon' => $hp,
            'tampil_publik' => true, 'narahubung' => Sel::bool($r['isPic'] ?? null),
        ]);
        $p->save();

        $foto = Sel::teks($r['foto'] ?? null);

        if ($foto !== null) {
            if (PolaContoh::kolomContoh($foto) || Validator::make(['u' => $foto], ['u' => [new TautanBerkasValid(false)]])->fails()) {
                $peringatan[] = 'Tautan foto dibuang (contoh/tidak memenuhi kebijakan berkas).';
            } else {
                app(PenyimpananBerkas::class)->simpan($p, 'foto', $foto, 'Foto pengurus', null);
            }
        }

        return [$p, $peringatan];
    }

    /** @return array{0: string, 1: ?string} [enum jabatan, jabatan_teks] */
    private function jabatan(?string $teks): array
    {
        $kecil = mb_strtolower($teks ?? '');

        return match (true) {
            $kecil === 'ketua' => ['ketua', null],
            $kecil === 'wakil ketua' => ['wakil_ketua', null],
            str_starts_with($kecil, 'sekretaris') => ['sekretaris', $kecil === 'sekretaris' ? null : $teks],
            str_starts_with($kecil, 'bendahara') => ['bendahara', $kecil === 'bendahara' ? null : $teks],
            $kecil === 'anggota' => ['anggota', null],
            default => ['lainnya', $teks !== null ? Str::limit($teks, 100, '') : null],
        };
    }
}
