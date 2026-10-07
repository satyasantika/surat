<?php

namespace App\Enums;

enum StatusPermohonan: string
{
    case Diajukan = 'diajukan';
    case PersetujuanPembina = 'persetujuan_pembina';
    case ValidasiAdmin = 'validasi_admin';
    case Dikembalikan = 'dikembalikan';
    case DisposisiDekan = 'disposisi_dekan';
    case PersetujuanWd = 'persetujuan_wd';
    case RekomendasiKasubag = 'rekomendasi_kasubag';
    case Penerbitan = 'penerbitan';
    case Selesai = 'selesai';
    case Ditolak = 'ditolak';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::PersetujuanPembina => 'Persetujuan pembina',
            self::ValidasiAdmin => 'Validasi admin',
            self::DisposisiDekan => 'Disposisi dekan',
            self::PersetujuanWd => 'Persetujuan WD',
            self::RekomendasiKasubag => 'Rekomendasi kasubag',
            default => ucfirst($this->value),
        };
    }

    /** Status akhir: tidak ada transisi lanjut. */
    public function akhir(): bool
    {
        return in_array($this, [self::Selesai, self::Ditolak, self::Dibatalkan], true);
    }

    /** @return array<string, string> */
    public static function pilihan(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c) => $c->label(), self::cases()));
    }
}
