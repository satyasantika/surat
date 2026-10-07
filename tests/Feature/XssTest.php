<?php

use App\Actions\Kabar\SimpanKabar;
use App\Actions\Kabar\TerbitkanKabar;
use App\Actions\Naskah\SimpanDraf;
use App\Models\JenisNaskah;
use App\Models\Ormawa;
use App\Models\PengurusOrmawa;
use App\Models\User;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

const SERANGAN_XSS = '<script>alert(1)</script><img src=x onerror=alert(2)><a href="javascript:alert(3)" onclick="x()">k</a><iframe src="https://e.test"></iframe><svg onload=alert(4)><style>@import "x"</style>';

beforeEach(function () {
    Queue::fake();
    $this->withoutVite();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class]);
    Cache::flush();
});

/** Isi artikel saja: Livewire dapat menyuntikkan skripnya sendiri ke halaman bila proses uji pernah merender komponen. */
function artikelXss(string $html): string
{
    return (string) preg_replace('/^.*?(<article)/s', '$1', preg_replace('/<\/article>.*$/s', '</article>', $html));
}

function bersihXss(string $html): void
{
    foreach (['<script', 'onerror', 'onclick', 'onload', 'javascript:', '<iframe', '<svg', '<style', '<img'] as $kata) {
        expect(strtolower($html))->not->toContain($kata);
    }
}

it('isi kabar disanitasi saat simpan dan saat tampil publik', function () {
    $admin = User::factory()->create()->assignRole('admin-persuratan');
    $k = app(SimpanKabar::class)->jalankan($admin, ['judul' => 'Kabar Xss', 'isi' => '<p>Aman</p>'.SERANGAN_XSS]);
    app(TerbitkanKabar::class)->jalankan($k, $admin);

    bersihXss($k->fresh()->isi);
    $html = $this->get("/kabar/{$k->slug}")->assertOk()->assertSee('Aman')->getContent();
    bersihXss(artikelXss($html));

    // Kebal terhadap data yang masuk lewat jalur lain (mis. impor/basis data): disanitasi ulang saat render.
    DB::table('kabar')->where('id', $k->id)->update(['isi' => '<p>Aman</p>'.SERANGAN_XSS]);
    Cache::flush();
    $html = $this->get("/kabar/{$k->slug}")->getContent();
    bersihXss(artikelXss($html));
});

it('judul kabar, nama ormawa, dan nama pengurus di halaman publik di-escape', function () {
    $admin = User::factory()->create()->assignRole('admin-persuratan');
    $judul = '<img src=x onerror=alert(1)>Judul';
    $k = app(SimpanKabar::class)->jalankan($admin, ['judul' => $judul, 'isi' => '<p>x</p>']);
    app(TerbitkanKabar::class)->jalankan($k, $admin);

    $o = Ormawa::create(['nama' => '<script>alert("o")</script>HIMA', 'tingkat' => 'prodi']);
    $o->sk()->create(['nomor_sk' => 'SK/1', 'tanggal_sk' => '2026-01-01', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2099-12-31']);
    PengurusOrmawa::create(['ormawa_id' => $o->id, 'user_id' => User::factory()->create()->id, 'nama' => '<b onmouseover=alert(1)>Budi</b>', 'jabatan' => 'ketua']);

    foreach (['/', '/kabar', "/kabar/{$k->slug}", '/organisasi', "/organisasi/{$o->slug}"] as $url) {
        $html = $this->get($url)->assertOk()->getContent();

        expect($html)->not->toContain('<script>alert')->not->toContain('<img src=x')->not->toContain('<b onmouseover');
    }
});

it('naskah: isi disanitasi dan pratinjau menolak skrip; variabel data di-escape', function () {
    $penyusun = User::factory()->create()->assignRole('admin-persuratan');
    $jenis = JenisNaskah::firstWhere('kode', 'surat-dinas');

    $n = app(SimpanDraf::class)->jalankan(null, [
        'jenis_naskah_id' => $jenis->id, 'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa', 'perihal' => '<script>alert("p")</script>Perihal',
        'penanda_tangan_jabatan_id' => $jenis->jabatan_penanda_tangan_bawaan_id, 'mode_tanda_tangan' => 'basah',
        'isi' => '<p>Isi</p>'.SERANGAN_XSS, 'data' => ['tujuan' => '<script>alert("t")</script>'], 'tujuan' => [['nama' => '<img src=x onerror=alert(5)>']],
    ], $penyusun);

    bersihXss((string) $n->fresh()->isi);

    $r = $this->actingAs($penyusun)->get(route('naskah.pratinjau', $n))->assertOk();
    // Livewire dapat menyuntikkan skripnya ke respons HTML bila proses uji pernah merender komponen; bukan bagian naskah.
    $html = preg_replace('/<!-- Livewire (Styles|Scripts) -->.*?(?=<\/body>|$)/s', '', $r->getContent());

    expect($html)->not->toContain('<script>alert')->not->toContain('<img src=x')->and($r->headers->get('Content-Security-Policy'))->toContain("default-src 'none'")->not->toContain('script-src');
    // teks serangan boleh tampil sebagai TEKS ter-escape, tetapi tidak boleh ada tag/atribut berbahaya yang hidup
    $tanpaGaya = preg_replace('/<style\b.*?<\/style>/is', '', $html);
    expect(preg_match('/<(script|iframe|svg|img|object|embed)\b/i', $tanpaGaya))->toBe(0)->and(preg_match('/<[^>]*\son[a-z]+\s*=/i', $tanpaGaya))->toBe(0)->and(preg_match('/href\s*=\s*["\']?\s*javascript:/i', $tanpaGaya))->toBe(0);
});
