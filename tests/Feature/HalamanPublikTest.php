<?php

use App\Models\Ormawa;
use App\Models\PengurusOrmawa;
use App\Models\User;
use App\Support\CachePublik;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->withoutVite();
    $this->seed(PeranDanIzinSeeder::class);
    Cache::flush();
});

function ormawaPublik(string $nama = 'HIMA Publik'): Ormawa
{
    $o = Ormawa::create(['nama' => $nama, 'tingkat' => 'prodi', 'singkatan' => 'HP', 'visi' => 'Visi hebat', 'misi' => 'Misi mulia', 'akun_media' => '@himapublik', 'surel_organisasi' => 'rahasia-org@unsil.ac.id']);
    $o->sk()->create(['nomor_sk' => 'SK/1', 'tanggal_sk' => '2026-01-01', 'periode_mulai' => '2020-01-01', 'periode_selesai' => '2099-12-31']);

    return $o;
}

function tambahPengurus(Ormawa $o, string $nama, array $ubah = []): PengurusOrmawa
{
    $u = User::factory()->create(['email' => strtolower(str_replace(' ', '.', $nama)).'@student.unsil.ac.id']);

    return PengurusOrmawa::create(['ormawa_id' => $o->id, 'user_id' => $u->id, 'nama' => $nama, 'jabatan' => 'ketua', 'nim' => '2012345678', 'telepon' => '081234567890', ...$ubah]);
}

describe('ormawa (BR-18)', function () {
    it('daftar dan profil hanya memuat ormawa aktif dan data ringkas', function () {
        $o = ormawaPublik();
        $nonaktif = ormawaPublik('HIMA Mati');
        $nonaktif->update(['aktif' => false]);

        $this->get('/organisasi')->assertOk()->assertSee('HIMA Publik')->assertDontSee('HIMA Mati');
        $this->get("/organisasi/{$o->slug}")->assertOk()->assertSee('Visi hebat')->assertSee('Misi mulia')->assertSee('@himapublik');
        $this->get("/organisasi/{$nonaktif->slug}")->assertNotFound();
        $this->get('/organisasi/tidak-ada')->assertNotFound();
    });

    it('tidak ada NIM, telepon, atau surel di HTML publik mana pun', function () {
        $o = ormawaPublik();
        $p = tambahPengurus($o, 'Budi Santoso');

        $halaman = ['/', '/organisasi', "/organisasi/{$o->slug}", '/verifikasi'];
        foreach ($halaman as $url) {
            $r = $this->get($url)->assertOk();
            $r->assertDontSee('2012345678')->assertDontSee('081234567890')->assertDontSee('student.unsil.ac.id')
                ->assertDontSee('rahasia-org@unsil.ac.id')->assertDontSee('@student')->assertDontSee($p->user->email);
        }
        $this->get("/organisasi/{$o->slug}")->assertSee('Budi Santoso')->assertSee('Ketua');
    });

    it('hanya pengurus aktif yang bersedia tampil', function () {
        $o = ormawaPublik();
        tambahPengurus($o, 'Tampil Aktif');
        tambahPengurus($o, 'Menolak Tampil', ['tampil_publik' => false]);
        tambahPengurus($o, 'Sudah Selesai', ['jabatan' => 'anggota', 'mulai' => '2021-01-01', 'selesai' => '2022-01-01']);
        tambahPengurus($o, 'Belum Mulai', ['jabatan' => 'anggota', 'mulai' => '2098-01-01']);

        $this->get("/organisasi/{$o->slug}")->assertOk()->assertSee('Tampil Aktif')
            ->assertDontSee('Menolak Tampil')->assertDontSee('Sudah Selesai')->assertDontSee('Belum Mulai');
    });

    it('pengurus kehilangan tampilan saat SK berakhir', function () {
        $o = ormawaPublik();
        tambahPengurus($o, 'Mantan Pengurus');
        $o->sk()->update(['periode_mulai' => '2020-01-01', 'periode_selesai' => '2021-01-01']);

        $this->get("/organisasi/{$o->slug}")->assertOk()->assertDontSee('Mantan Pengurus');
    });
});

describe('keamanan dan performa', function () {
    it('memasang CSP yang membatasi sumber gambar dan melarang bingkai', function () {
        $csp = $this->get('/')->assertOk()->headers->get('Content-Security-Policy');

        expect($csp)->toContain("default-src 'self'")->toContain('https://lh3.googleusercontent.com')
            ->toContain("frame-ancestors 'none'")->not->toContain('unsafe-eval');
    });

    it('tidak ada rute publik yang mengembalikan JSON daftar selain kesehatan', function () {
        $publik = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => in_array('GET', $r->methods(), true)
            && ! collect($r->gatherMiddleware())->contains(fn ($m) => is_string($m) && (str_starts_with($m, 'auth') || str_contains($m, 'Authenticate'))))
            ->map(fn ($r) => $r->uri())->filter(fn ($u) => str_starts_with($u, 'api'))->values()->all();

        expect($publik)->toBe(['api/health']);
        $this->get('/organisasi', ['Accept' => 'application/json'])->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    });

    it('cache halaman tersegarkan saat konten berubah dan bertahan bila tidak', function () {
        $o = ormawaPublik('HIMA Cache');
        $this->get('/organisasi')->assertSee('HIMA Cache');

        DB::table('ormawa')->where('id', $o->id)->update(['aktif' => false]);
        $this->get('/organisasi')->assertSee('HIMA Cache');

        $o->refresh()->forceFill(['aktif' => false])->save();
        $this->get('/organisasi')->assertDontSee('HIMA Cache');
        expect(CachePublik::DETIK)->toBe(300);
    });

    it('formulir verifikasi mengalihkan kode atau tautan sah dan menolak yang lain', function () {
        $id = '0190a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b';

        $this->get('/verifikasi')->assertOk()->assertSee('Verifikasi surat');
        $this->get('/verifikasi?kode='.$id)->assertRedirect(route('verifikasi', $id));
        $this->get('/verifikasi?kode='.urlencode("https://supportfkip.unsil.ac.id/surat/verifikasi/{$id}"))->assertRedirect(route('verifikasi', $id));
        $this->get('/verifikasi?kode=bukan-kode')->assertOk()->assertSee('Kode tidak dikenali');
    });
});
