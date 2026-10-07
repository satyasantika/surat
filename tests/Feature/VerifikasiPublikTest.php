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
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class]);
});

function naskahPublik(string $status, string $keamanan, array $ubah = []): Naskah
{
    $u = User::factory()->create()->assignRole('admin-persuratan');
    $n = new Naskah(['jenis_naskah_id' => JenisNaskah::firstWhere('kode', 'surat-dinas')->id, 'klasifikasi_keamanan' => $keamanan, 'perihal' => 'PERIHAL-RAHASIA-VRF', 'isi' => '<p>ISI-RAHASIA-VRF</p>', 'data' => ['tujuan' => 'TUJUAN-RAHASIA-VRF'],
        'status' => $status, 'penyusun_id' => $u->id, 'penanda_tangan_jabatan_id' => Jabatan::firstWhere('kode', 'dekan')->id, 'mode_tanda_tangan' => 'visual']);
    $n->forceFill(['nomor' => '123/UN58.10/KM.03.02/2026', 'tanggal_naskah' => '2026-05-01', 'hash_pdf' => str_repeat('ab', 32), 'snapshot' => ['penanda_tangan' => ['nama' => 'Prof. Dekan', 'jabatan' => 'Dekan', 'jabatan_dasar' => null]], ...$ubah])->save();
    $n->tautan()->create(['jenis' => 'lampiran', 'url' => 'https://drive.google.com/file/d/1LampiranRahasia12/view', 'label' => 'Lampiran']);

    return $n;
}

it('hanya bidang putih: nomor, tanggal, jenis, penanda tangan, jabatan, mode, status, hash', function () {
    $n = naskahPublik('terbit', 'biasa');

    $r = $this->get(route('verifikasi', $n))->assertOk();

    $r->assertSee('123/UN58.10/KM.03.02/2026')->assertSee('Prof. Dekan')->assertSee('Dekan')->assertSee(str_repeat('ab', 32))
        ->assertDontSee('ISI-RAHASIA-VRF')->assertDontSee('TUJUAN-RAHASIA-VRF')->assertDontSee('drive.google.com')->assertDontSee('Lampiran');
});

it('naskah non-biasa: perihal tidak tampil; isi, tujuan, dan tautan tak pernah tampil', function (string $keamanan) {
    $n = naskahPublik('terbit', $keamanan);

    $this->get(route('verifikasi', $n))->assertOk()->assertDontSee('PERIHAL-RAHASIA-VRF')->assertDontSee('ISI-RAHASIA-VRF')->assertDontSee('TUJUAN-RAHASIA-VRF')->assertDontSee('drive.google.com');
})->with(['terbatas', 'rahasia', 'sangat_rahasia']);

it('tidak bocor lewat enumerasi: id tak sah, tak ada, draf, dikembalikan, dan menunggu memberi 404 yang sama', function () {
    $draf = naskahPublik('draf', 'biasa');
    $kembali = naskahPublik('dikembalikan', 'biasa', ['nomor' => null]);
    $ada404 = [route('verifikasi', Str::uuid()->toString()), route('verifikasi', $draf), route('verifikasi', $kembali), '/verifikasi/1', '/verifikasi/abc', '/verifikasi/'.str_repeat('a', 300)];

    foreach ($ada404 as $url) {
        $this->get($url)->assertNotFound();
    }
});

it('naskah dibatalkan tetap terverifikasi sebagai DIBATALKAN', function () {
    $n = naskahPublik('dibatalkan', 'biasa', ['dibatalkan_pada' => '2026-05-10 10:00:00', 'alasan_batal' => 'Salah ketik']);

    $this->get(route('verifikasi', $n))->assertOk()->assertSee('DIBATALKAN')->assertSee('Salah ketik');
});

it('berkas respons: tanpa cache, tanpa indeks mesin pencari, CSP ketat, tanpa cookie sesi bocor ke skrip', function () {
    $n = naskahPublik('terbit', 'biasa');

    $r = $this->get(route('verifikasi', $n))->assertOk();
    $csp = $r->headers->get('Content-Security-Policy');

    expect($r->headers->get('Cache-Control'))->toContain('no-store')->and($r->headers->get('X-Robots-Tag'))->toContain('noindex')->and($r->headers->get('Referrer-Policy'))->toBe('no-referrer')
        ->and($csp)->toContain("default-src 'none'")->toContain("frame-ancestors 'none'")->toContain("form-action 'none'")->not->toContain('unsafe-inline\'; style')
        ->and(preg_match("/script-src 'nonce-[A-Za-z0-9+\\/=]+'/", $csp))->toBe(1);
});
