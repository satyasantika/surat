<?php

namespace App\Support;

use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Pola nomor dengan token: {urut}, {urut:N}, {kode_unit}, {klasifikasi}, {bulan_romawi}, {tahun}, {kode_jenis}.
 */
class PolaNomor
{
    private const TOKEN = '/\{(urut(?::(\d{1,2}))?|kode_unit|klasifikasi|bulan_romawi|tahun|kode_jenis)\}/';

    private const ROMAWI = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    /** Pola sah: hanya token dikenal, tepat satu {urut}. */
    public static function valid(string $pola): bool
    {
        $sisa = preg_replace(self::TOKEN, '', $pola);

        return ! str_contains((string) $sisa, '{') && ! str_contains((string) $sisa, '}')
            && preg_match_all('/\{urut(?::\d{1,2})?\}/', $pola) === 1;
    }

    /**
     * @param  array<string, string>  $konteks  klasifikasi, kode_jenis, kode_unit (opsional)
     */
    public static function render(string $pola, int $urut, CarbonInterface $tanggal, array $konteks = []): string
    {
        if (! self::valid($pola)) {
            throw new InvalidArgumentException("Pola nomor tidak sah: {$pola}");
        }

        return (string) preg_replace_callback(self::TOKEN, function (array $m) use ($pola, $urut, $tanggal, $konteks) {
            $token = explode(':', $m[1])[0];

            return match ($token) {
                'urut' => isset($m[2]) ? str_pad((string) $urut, (int) $m[2], '0', STR_PAD_LEFT) : (string) $urut,
                'tahun' => (string) $tanggal->year,
                'bulan_romawi' => self::ROMAWI[$tanggal->month],
                'kode_unit' => $konteks['kode_unit'] ?? (string) config('unsil.kode_unit'),
                'klasifikasi', 'kode_jenis' => $konteks[$token]
                    ?? throw new InvalidArgumentException("Konteks '{$token}' wajib untuk pola {$pola}."),
                default => throw new InvalidArgumentException("Token tidak dikenal: {$token}"),
            };
        }, $pola);
    }
}
