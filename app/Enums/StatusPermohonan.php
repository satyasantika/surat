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
    case Pembuatan = 'pembuatan';
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
            self::Pembuatan => 'Pembuatan surat',
            default => ucfirst($this->value),
        };
    }

    /** Nama tahap untuk pengirim dan halaman lacak publik (tanpa istilah internal). */
    public function labelPublik(): string
    {
        return match ($this) {
            self::Diajukan => 'Permohonan diajukan',
            self::PersetujuanPembina => 'Menunggu persetujuan pembina',
            self::ValidasiAdmin => 'Validasi administrasi',
            self::Dikembalikan => 'Dikembalikan untuk diperbaiki',
            self::DisposisiDekan => 'Menunggu disposisi dekan',
            self::PersetujuanWd => 'Persetujuan wakil dekan',
            self::RekomendasiKasubag => 'Rekomendasi kepala subbagian',
            self::Pembuatan => 'Surat sedang dibuat',
            self::Penerbitan => 'Surat dalam proses penerbitan',
            self::Selesai => 'Selesai',
            self::Ditolak => 'Ditolak',
            self::Dibatalkan => 'Dibatalkan',
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
