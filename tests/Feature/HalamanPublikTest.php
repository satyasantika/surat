<?php

use App\Models\Galeri;
use App\Models\Kabar;
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

function kabarDi(string $status, array $ubah = [], ?Ormawa $o = null): Kabar
{
    $k = new Kabar(['ormawa_id' => $o?->id, 'judul' => 'Judul '.str()->random(6), 'isi' => '<p>Isi kabar</p>']);
    $k->forceFill(['status' => $status, 'penulis_id' => User::factory()->create(['name' => 'Penulis Rahasia'])->id, 'terbit_pada' => $status === 'terbit' ? now() : null, ...$ubah])->save();

    return $k;
}

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

describe('kabar', function () {
    it('hanya kabar terbit yang tampil di beranda, daftar, dan detail', function () {
        $terbit = kabarDi('terbit', ['judul' => 'Kabar Terbit Satu']);
        foreach (['draf', 'diajukan', 'ditolak'] as $s) {
            kabarDi($s, ['judul' => "Kabar {$s} tersembunyi"]);
        }

        foreach (['/', '/kabar'] as $url) {
            $this->get($url)->assertOk()->assertSee('Kabar Terbit Satu')->assertDontSee('tersembunyi');
        }
        $this->get("/kabar/{$terbit->slug}")->assertOk()->assertSee('Kabar Terbit Satu')->assertSee('Isi kabar');
        foreach (Kabar::where('status', '!=', 'terbit')->get() as $k) {
            $this->get("/kabar/{$k->slug}")->assertNotFound();
        }
        $this->get('/kabar/tidak-ada')->assertNotFound();
    });

    it('tidak membocorkan penulis dan merender isi dengan aman walau basis data berisi skrip', function () {
        $k = kabarDi('terbit');
        DB::table('kabar')->where('id', $k->id)->update(['isi' => '<p>Aman</p><script>alert(1)</script><img src=x onerror=alert(2)><a href="javascript:alert(3)">x</a>']);
        CachePublik::segarkan();

        $this->get("/kabar/{$k->slug}")->assertOk()->assertSee('Aman')->assertDontSee('Penulis Rahasia')
            ->assertDontSee('<script', false)->assertDontSee('onerror', false)->assertDontSee('javascript:', false);
    });

    it('menampilkan sampul Drive lewat lh3 dan membuang halaman yang tidak ada', function () {
        $k = kabarDi('terbit');
        $k->tautan()->create(['jenis' => 'foto', 'url' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view', 'label' => 'Sampul']);

        $this->get('/kabar')->assertSee('https://lh3.googleusercontent.com/d/1AbCdEfGhIjKlMnOpQr', false);
        $this->get('/kabar?page=abc')->assertOk();
        $this->get('/kabar?page=9999')->assertOk();
    });
});

describe('galeri', function () {
    it('hanya item aktif; video lewat embed tanpa cookie; instagram hanya tautan tanpa iframe/skrip', function () {
        Galeri::create(['judul' => 'Foto Aktif', 'tipe' => 'foto', 'url' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view', 'aktif' => true]);
        Galeri::create(['judul' => 'Video Aktif', 'tipe' => 'video', 'url' => 'https://youtu.be/dQw4w9WgXcQ', 'aktif' => true]);
        Galeri::create(['judul' => 'IG Aktif', 'tipe' => 'instagram', 'url' => 'https://www.instagram.com/p/AbC/', 'aktif' => true]);
        Galeri::create(['judul' => 'Nonaktif Rahasia', 'tipe' => 'foto', 'url' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view']);

        $r = $this->get('/galeri')->assertOk()->assertSee('Foto Aktif')->assertSee('Video Aktif')->assertSee('IG Aktif')->assertDontSee('Nonaktif Rahasia')
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)->assertSee('lh3.googleusercontent.com/d/1AbCdEfGhIjKlMnOpQr', false)
            ->assertSee('https://www.instagram.com/p/AbC/', false)->assertDontSee('instagram.com/embed', false)->assertDontSee('platform.instagram.com', false);
        expect(substr_count($r->getContent(), '<iframe'))->toBe(1)->and(substr_count($r->getContent(), 'instagram'))->toBeGreaterThan(0);
        expect($r->getContent())->not->toContain('<script');
    });
});

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
        $k = kabarDi('terbit', [], $o);
        Galeri::create(['judul' => 'G', 'tipe' => 'instagram', 'url' => 'https://www.instagram.com/p/AbC/', 'aktif' => true, 'ormawa_id' => $o->id]);

        $halaman = ['/', '/kabar', "/kabar/{$k->slug}", '/galeri', '/organisasi', "/organisasi/{$o->slug}", '/verifikasi'];
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
    it('memasang CSP yang membatasi sumber gambar dan bingkai', function () {
        $csp = $this->get('/')->assertOk()->headers->get('Content-Security-Policy');

        expect($csp)->toContain("default-src 'self'")->toContain('https://lh3.googleusercontent.com')->toContain('youtube-nocookie.com')
            ->toContain("frame-ancestors 'none'")->not->toContain('unsafe-eval');
    });

    it('tidak ada rute publik yang mengembalikan JSON daftar selain kesehatan', function () {
        $publik = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => in_array('GET', $r->methods(), true)
            && ! collect($r->gatherMiddleware())->contains(fn ($m) => is_string($m) && (str_starts_with($m, 'auth') || str_contains($m, 'Authenticate'))))
            ->map(fn ($r) => $r->uri())->filter(fn ($u) => str_starts_with($u, 'api'))->values()->all();

        expect($publik)->toBe(['api/health']);
        $this->get('/kabar', ['Accept' => 'application/json'])->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    });

    it('cache halaman tersegarkan saat konten berubah dan bertahan bila tidak', function () {
        $k = kabarDi('terbit', ['judul' => 'Kabar Cache']);
        $this->get('/kabar')->assertSee('Kabar Cache');

        DB::table('kabar')->where('id', $k->id)->update(['status' => 'draf']);
        $this->get('/kabar')->assertSee('Kabar Cache');

        $k->refresh()->forceFill(['status' => 'draf'])->save();
        $this->get('/kabar')->assertDontSee('Kabar Cache');
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
