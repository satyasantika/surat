<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class PeranDanIzinSeeder extends Seeder
{
    /** Permission granular per modul (PRD §3.1). */
    public const IZIN = [
        'masuk.lihat', 'masuk.registrasi',
        'disposisi.buat', 'disposisi.teruskan', 'disposisi.tindaklanjut',
        'naskah.draf', 'naskah.paraf', 'naskah.tandatangan', 'naskah.templat', 'naskah.batalkan',
        'nomor.terbitkan',
        'arsip.lihat',
        'ormawa.lihat', 'ormawa.kelola', 'ormawa.kelola-binaan', 'ormawa.kelola-sendiri',
        'permohonan.ajukan', 'permohonan.setujui-pembina', 'permohonan.validasi', 'permohonan.putuskan',
        'ruangan.kelola',
        'lpj.isi', 'lpj.nilai', 'lpj.lihat',
        'kabar.kelola', 'kabar.usul',
        'galeri.kelola',
        'master.kelola',
        'pengguna.kelola',
        'pengaturan.kelola',
    ];

    /**
     * super-admin tidak diberi izin eksplisit (Gate::before); operator-layanan
     * tanpa izin bawaan — izin diberikan per pengguna lewat kelola pengguna.
     */
    public const PERAN = [
        'super-admin' => [],
        'admin-persuratan' => [
            'masuk.lihat', 'masuk.registrasi', 'naskah.draf', 'naskah.templat', 'naskah.batalkan', 'nomor.terbitkan', 'arsip.lihat',
            'ormawa.lihat', 'ormawa.kelola', 'permohonan.validasi', 'kabar.kelola', 'galeri.kelola',
        ],
        'operator-layanan' => [],
        'dekan' => [
            'masuk.lihat', 'disposisi.buat', 'naskah.draf', 'naskah.tandatangan', 'arsip.lihat',
            'ormawa.lihat', 'permohonan.putuskan', 'lpj.nilai',
        ],
        'wakil-dekan' => [
            'masuk.lihat', 'disposisi.teruskan', 'disposisi.tindaklanjut', 'naskah.draf', 'naskah.paraf',
            'naskah.tandatangan', 'arsip.lihat', 'ormawa.lihat', 'permohonan.putuskan', 'lpj.nilai',
        ],
        'kasubag' => [
            'masuk.lihat', 'disposisi.teruskan', 'disposisi.tindaklanjut', 'naskah.draf', 'naskah.paraf',
            'arsip.lihat', 'ormawa.lihat', 'permohonan.putuskan', 'lpj.nilai',
        ],
        'pembina-ormawa' => [
            'ormawa.kelola-binaan', 'permohonan.setujui-pembina', 'lpj.lihat',
        ],
        'pengurus-ormawa' => [
            'permohonan.ajukan', 'lpj.isi', 'ormawa.kelola-sendiri', 'kabar.usul',
        ],
        'pegawai' => [
            'masuk.lihat', 'disposisi.tindaklanjut',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::IZIN as $nama) {
            Permission::findOrCreate($nama, 'web');
        }

        foreach (self::PERAN as $nama => $izin) {
            Role::findOrCreate($nama, 'web')->syncPermissions($izin);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
