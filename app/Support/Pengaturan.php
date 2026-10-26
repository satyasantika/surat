<?php

namespace App\Support;

use App\Models\Pengaturan as ModelPengaturan;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/** Pengaturan kebijakan sistem (kunci–nilai JSON), ter-cache. Dibaca di Action, bukan hanya di UI. */
class Pengaturan
{
    public const CACHE_KUNCI = 'surat:pengaturan';

    /** @var array<string, array{nilai: mixed, grup: string}> kunci yang dikenal beserta bawaannya */
    public const BAWAAN = [
        'min_hari_sebelum_kegiatan' => ['nilai' => 7, 'grup' => 'permohonan'],
        'batas_hari_lpj' => ['nilai' => 14, 'grup' => 'lpj'],
        'toleransi_lpj_hari' => ['nilai' => 0, 'grup' => 'lpj'],
        'blokir_lpj_terlambat' => ['nilai' => true, 'grup' => 'lpj'],
        'persetujuan_pembina_aktif' => ['nilai' => false, 'grup' => 'permohonan'],
        'sesi' => ['nilai' => [
            ['kode' => 'pagi', 'mulai' => '06:00', 'selesai' => '12:00'],
            ['kode' => 'siang', 'mulai' => '13:00', 'selesai' => '16:00'],
            ['kode' => 'seharian', 'mulai' => '06:00', 'selesai' => '16:00'],
        ], 'grup' => 'ruangan'],
        'kop_surat' => ['nilai' => [
            'KEMENTERIAN PENDIDIKAN TINGGI, SAINS, DAN TEKNOLOGI',
            'UNIVERSITAS SILIWANGI',
            'FAKULTAS KEGURUAN DAN ILMU PENDIDIKAN',
        ], 'grup' => 'surat'],
        // jumlah = jumlah nilai semua penilai; persen = jumlah / total maksimum × 100
        'rumus_nilai_lpj' => ['nilai' => 'jumlah', 'grup' => 'lpj'],
        'wa_aktif' => ['nilai' => false, 'grup' => 'notifikasi'],
        // pengingat terjadwal: disposisi "mendekati" bila batas waktu dalam N jam; permohonan "tertahan" bila N hari di satu tahap
        'jam_pengingat_disposisi' => ['nilai' => 24, 'grup' => 'notifikasi'],
        'hari_tertahan_permohonan' => ['nilai' => 3, 'grup' => 'notifikasi'],
        'layanan_ruangan' => ['nilai' => 'lokal', 'grup' => 'ruangan'],
        // [{nama, kategori}]: pilihan fasilitas rektorat pada permohonan (diisi impor OrmawaHub atau admin)
        'fasilitas_rektorat_katalog' => ['nilai' => [], 'grup' => 'permohonan'],
        // saklar MFA: bila menyala, peran di config unsil.peran_wajib_mfa wajib memasang MFA (hanya super-admin yang mengubah)
        'mfa_wajib' => ['nilai' => false, 'grup' => 'keamanan'],
        // batas penyalahgunaan kiriman pihak luar: pengiriman per hari per akun, dan tujuan perantara per pengiriman
        'batas_kiriman_harian' => ['nilai' => 5, 'grup' => 'kiriman'],
        'batas_tujuan_kiriman' => ['nilai' => 5, 'grup' => 'kiriman'],
    ];

    public static function get(string $kunci, mixed $bawaan = null): mixed
    {
        $semua = self::semua();

        return array_key_exists($kunci, $semua) ? $semua[$kunci] : ($bawaan ?? self::BAWAAN[$kunci]['nilai'] ?? null);
    }

    public static function set(string $kunci, mixed $nilai): void
    {
        ModelPengaturan::updateOrCreate(['kunci' => $kunci], [
            'nilai' => $nilai,
            'grup' => self::BAWAAN[$kunci]['grup'] ?? 'umum',
        ]);
    }

    /** @return array<string, mixed> */
    public static function semua(): array
    {
        $tersimpan = Cache::get(self::CACHE_KUNCI);

        if (is_array($tersimpan)) {
            return $tersimpan;
        }

        try {
            $semua = ModelPengaturan::query()->pluck('nilai', 'kunci')->all();
        } catch (QueryException) {
            // tabel belum ada (migrasi, composer install, tes awal): pakai bawaan dan jangan di-cache
            return [];
        }

        Cache::put(self::CACHE_KUNCI, $semua, 3600);

        return $semua;
    }

    public static function lupakan(): void
    {
        Cache::forget(self::CACHE_KUNCI);
    }
}
