<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Ormawa;
use App\Models\PengurusOrmawa;
use App\Support\CachePublik;
use Illuminate\Support\Str;

/**
 * Halaman publik (BR-18): hanya profil ringkas ormawa aktif.
 * Pengurus ditampilkan hanya nama dan jabatan; NIM, telepon, dan surel tidak pernah diambil dari basis data.
 * Tidak ada keluaran JSON; HTML jadi di-cache maksimal 5 menit (CachePublik).
 */
class HalamanController extends Controller
{
    public function beranda(): string
    {
        return CachePublik::ingat('beranda', fn () => view('publik.beranda')->render());
    }

    public function organisasiIndex(): string
    {
        return CachePublik::ingat('organisasi', fn () => view('publik.organisasi-index', [
            'daftar' => Ormawa::where('aktif', true)->with('tautan')->orderBy('nama')->get(['id', 'slug', 'nama', 'singkatan', 'tingkat']),
        ])->render());
    }

    public function organisasiShow(string $slug): string
    {
        $ormawa = Str::length($slug) <= 190
            ? Ormawa::where('aktif', true)->where('slug', $slug)->with('tautan')->first(['id', 'slug', 'nama', 'singkatan', 'tingkat', 'akun_media', 'visi', 'misi'])
            : null;
        abort_if($ormawa === null, 404);

        return CachePublik::ingat("organisasi:{$ormawa->slug}", function () use ($ormawa) {
            // Kolom dibatasi: nim dan telepon (data pribadi) tidak pernah dimuat. Hanya pengurus aktif yang bersedia tampil.
            $pengurus = PengurusOrmawa::where('ormawa_id', $ormawa->getKey())->where('tampil_publik', true)
                ->get(['id', 'ormawa_id', 'sk_kepengurusan_id', 'nama', 'jabatan', 'jabatan_teks', 'mulai', 'selesai'])
                ->filter(fn (PengurusOrmawa $p) => $p->aktifPada())
                ->sortBy(fn (PengurusOrmawa $p) => array_search($p->jabatan, array_keys(PengurusOrmawa::JABATAN), true).'-'.$p->nama)
                ->values();

            return view('publik.organisasi-show', ['ormawa' => $ormawa, 'pengurus' => $pengurus])->render();
        });
    }
}
