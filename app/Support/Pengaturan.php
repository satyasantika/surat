<?php

namespace App\Support;

use App\Models\Pengaturan as ModelPengaturan;
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
        'layanan_ruangan' => ['nilai' => 'lokal', 'grup' => 'ruangan'],
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
        return Cache::remember(self::CACHE_KUNCI, 3600, fn () => ModelPengaturan::query()->pluck('nilai', 'kunci')->all());
    }

    public static function lupakan(): void
    {
        Cache::forget(self::CACHE_KUNCI);
    }
}
