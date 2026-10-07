<?php

use App\Models\Jabatan;
use App\Models\JenisNaskah;
use App\Models\Naskah;
use App\Models\User;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class]);
});

it('semua respons web memuat header dasar keamanan', function (string $url) {
    $r = $this->get($url);

    expect($r->headers->get('X-Content-Type-Options'))->toBe('nosniff')->and($r->headers->get('X-Frame-Options'))->toBeIn(['SAMEORIGIN', 'DENY'])
        ->and($r->headers->get('Referrer-Policy'))->not->toBeNull()->and($r->headers->get('Permissions-Policy'))->toContain('camera=()');
})->with(['/', '/masuk', '/kabar', '/galeri', '/organisasi', '/verifikasi', '/admin/login', '/tidak-ada-halaman']);

it('halaman aplikasi memakai CSP berbasis nonce tanpa unsafe-inline untuk skrip', function () {
    foreach (['/masuk', '/daftar', '/lupa-sandi'] as $url) {
        $csp = $this->get($url)->assertOk()->headers->get('Content-Security-Policy');
        preg_match('/script-src ([^;]+)/', (string) $csp, $m);

        expect($m[1] ?? '')->toContain("'self'")->toMatch("/'nonce-[A-Za-z0-9+\\/=]{16,}'/")->not->toContain('unsafe-inline')->not->toContain('*')
            ->and($csp)->toContain("object-src 'none'")->toContain("base-uri 'self'")->toContain("frame-ancestors 'self'")->toContain("form-action 'self'");
    }
});

it('nonce berbeda pada setiap respons', function () {
    $ambil = fn () => preg_match("/'nonce-([^']+)'/", (string) $this->get('/masuk')->headers->get('Content-Security-Policy'), $m) ? $m[1] : null;

    expect($ambil())->not->toBe($ambil());
});

it('halaman publik tanpa skrip inline dan tidak dapat dibingkai', function () {
    $r = $this->get('/kabar');
    $csp = (string) $r->headers->get('Content-Security-Policy');

    expect($csp)->toContain("script-src 'self'")->toContain("frame-ancestors 'none'")->and(preg_match('/script-src[^;]*unsafe-(inline|eval)/', $csp))->toBe(0);
});

it('panel admin tidak dikenai CSP aplikasi (Filament memakai skrip inline) tetapi tetap memperoleh header dasar', function () {
    $r = $this->get('/admin/login');

    expect($r->headers->get('Content-Security-Policy'))->toBeNull()->and($r->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('HSTS hanya di produksi lewat HTTPS', function () {
    $this->get('/masuk')->assertHeaderMissing('Strict-Transport-Security');

    $this->app['env'] = 'production';
    $this->get('http://localhost/masuk')->assertHeaderMissing('Strict-Transport-Security');
    $this->get('https://localhost/masuk')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('verifikasi dan pratinjau naskah mempertahankan CSP sendiri yang lebih ketat', function () {
    $u = User::factory()->create()->assignRole('admin-persuratan');
    $n = new Naskah(['jenis_naskah_id' => JenisNaskah::firstWhere('kode', 'surat-dinas')->id, 'perihal' => 'x', 'data' => [], 'status' => 'draf', 'penyusun_id' => $u->id, 'penanda_tangan_jabatan_id' => Jabatan::firstWhere('kode', 'dekan')->id, 'mode_tanda_tangan' => 'basah']);
    $n->save();
    $t = new Naskah(['jenis_naskah_id' => JenisNaskah::firstWhere('kode', 'surat-dinas')->id, 'perihal' => 'y', 'data' => [], 'status' => 'terbit', 'penyusun_id' => $u->id, 'penanda_tangan_jabatan_id' => Jabatan::firstWhere('kode', 'dekan')->id, 'mode_tanda_tangan' => 'basah']);
    $t->forceFill(['nomor' => '1/X/2026', 'tanggal_naskah' => '2026-05-01', 'snapshot' => ['migrasi' => true]])->save();

    expect($this->get(route('verifikasi', $t))->headers->get('Content-Security-Policy'))->toContain("default-src 'none'")->toContain("frame-ancestors 'none'");
    expect($this->actingAs($u)->get(route('naskah.pratinjau', $n))->headers->get('Content-Security-Policy'))->toContain("default-src 'none'");
});
