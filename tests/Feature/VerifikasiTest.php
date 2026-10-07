<?php

use App\Actions\Naskah\AjukanParaf;
use App\Actions\Naskah\SimpanDraf;
use App\Actions\Naskah\TandaTangani;
use App\Actions\Naskah\TransisiNaskah;
use App\Enums\StatusNaskah;
use App\Jobs\TerbitkanNaskah;
use App\Models\Jabatan;
use App\Models\JenisNaskah;
use App\Models\KlasifikasiArsip;
use App\Models\Naskah;
use App\Models\PemangkuJabatan;
use App\Models\User;
use App\Services\Naskah\QrNaskah;
use App\Services\Naskah\RenderNaskah;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\KlasifikasiArsipSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, PengaturanSeeder::class, KlasifikasiArsipSeeder::class]);
});

function naskahTerbit(array $ubah = []): Naskah
{
    $dekan = User::factory()->create(['name' => 'Prof. Dekan', 'nip_nim' => '19700101'])->assignRole('dekan');
    PemangkuJabatan::create(['jabatan_id' => Jabatan::firstWhere('kode', 'dekan')->id, 'user_id' => $dekan->id, 'mulai' => now()->subYear()]);
    $penyusun = User::factory()->create(['name' => 'Penyusun Rahasia'])->assignRole('admin-persuratan');
    $jenis = JenisNaskah::firstWhere('kode', 'surat-dinas');

    $n = app(SimpanDraf::class)->jalankan(null, $ubah + [
        'jenis_naskah_id' => $jenis->id, 'klasifikasi_arsip_id' => KlasifikasiArsip::firstWhere('kode', 'KM.03.02')->id,
        'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa', 'perihal' => 'Undangan rapat publik',
        'penanda_tangan_jabatan_id' => $jenis->jabatan_penanda_tangan_bawaan_id, 'mode_tanda_tangan' => 'basah',
        'data' => ['tujuan' => 'Kepala Dinas'], 'tujuan' => [['nama' => 'Kepala Dinas']], 'isi' => '<p>ISI-RAHASIA-NASKAH</p>',
    ], $penyusun);

    $n = app(TandaTangani::class)->jalankan(app(AjukanParaf::class)->jalankan($n, $penyusun, []), $dekan);
    (new TerbitkanNaskah($n))->handle(app(RenderNaskah::class), app(TransisiNaskah::class));

    return $n->fresh();
}

it('menampilkan bidang putih naskah terbit tanpa login', function () {
    $n = naskahTerbit();

    $r = $this->get(route('verifikasi', $n))->assertOk();

    $r->assertSee('BERLAKU')->assertSee($n->nomor)->assertSee('Surat Dinas')->assertSee('Undangan rapat publik')
        ->assertSee('Prof. Dekan')->assertSee('Dekan')->assertSee('Tanda tangan basah')->assertSee($n->hash_pdf);
});

it('tidak membocorkan isi, tujuan, penyusun, NIP, atau tautan', function () {
    $n = naskahTerbit();
    $html = $this->get(route('verifikasi', $n))->getContent();

    expect($html)->not->toContain('ISI-RAHASIA-NASKAH')->not->toContain('Kepala Dinas')->not->toContain('Penyusun Rahasia')
        ->not->toContain('19700101')->not->toContain($n->penyusun->email)->not->toContain('drive.google.com');
});

it('menyamarkan perihal naskah selain klasifikasi biasa', function (string $klas) {
    $n = naskahTerbit(['klasifikasi_keamanan' => $klas, 'perihal' => 'Perihal sensitif QQQ']);

    $this->get(route('verifikasi', $n))->assertOk()->assertDontSee('Perihal sensitif QQQ')->assertSee('Tidak ditampilkan')->assertSee($n->nomor);
})->with(['terbatas', 'rahasia', 'sangat_rahasia']);

it('menampilkan status dibatalkan dengan tanggal dan alasan ringkas', function () {
    $n = naskahTerbit();
    $n->forceFill(['status' => StatusNaskah::Dibatalkan, 'dibatalkan_pada' => now(), 'alasan_batal' => 'Salah penerima. '.str_repeat('x', 300)])->save();

    $r = $this->get(route('verifikasi', $n))->assertOk()->assertSee('DIBATALKAN')->assertDontSee('BERLAKU')->assertSee('Salah penerima');

    expect($r->getContent())->not->toContain(str_repeat('x', 200));
});

it('mengembalikan 404 generik untuk id tak valid, tak ada, dan naskah yang belum berlaku', function () {
    $n = naskahTerbit();
    $draf = app(SimpanDraf::class)->jalankan(null, [
        'jenis_naskah_id' => JenisNaskah::firstWhere('kode', 'surat-dinas')->id, 'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa', 'perihal' => 'x',
        'penanda_tangan_jabatan_id' => Jabatan::firstWhere('kode', 'dekan')->id, 'mode_tanda_tangan' => 'basah', 'data' => ['tujuan' => 'D'], 'tujuan' => [['nama' => 'D']],
    ], User::factory()->create()->assignRole('admin-persuratan'));

    foreach (['bukan-uuid', (string) Str::uuid(), $draf->id, '1'] as $id) {
        $this->get('/verifikasi/'.$id)->assertNotFound();
    }

    $n->forceFill(['status' => StatusNaskah::Dikembalikan])->saveQuietly();
    $this->get(route('verifikasi', $n))->assertNotFound();
});

it('tidak menyediakan daftar naskah publik', function () {
    $n = naskahTerbit();

    // /verifikasi hanya formulir kode (F9.3), bukan daftar: tidak memuat data naskah apa pun.
    foreach (['/verifikasi', '/verifikasi/'] as $url) {
        $this->get($url)->assertOk()->assertSee('Verifikasi surat')->assertDontSee($n->nomor)->assertDontSee($n->perihal);
    }
});

it('membatasi 30 permintaan per menit per IP', function () {
    $n = naskahTerbit();

    foreach (range(1, 30) as $i) {
        $this->get(route('verifikasi', $n))->assertOk();
    }

    $this->get(route('verifikasi', $n))->assertStatus(429);
    $this->get(route('verifikasi', (string) Str::uuid()))->assertStatus(429);
});

it('menandai naskah migrasi tanpa hash dan tanpa snapshot', function () {
    $n = naskahTerbit();
    DB::table('naskah')->where('id', $n->id)->update(['hash_pdf' => null, 'snapshot' => null]);

    $this->get(route('verifikasi', $n))->assertOk()->assertSee('Naskah migrasi')->assertDontSee('Cocokkan dokumen');
});

it('memasang header keamanan dan skrip ber-nonce untuk pencocokan di peramban', function () {
    $n = naskahTerbit();

    $r = $this->get(route('verifikasi', $n))->assertOk();
    $csp = $r->headers->get('Content-Security-Policy');

    preg_match("/script-src 'nonce-([^']+)'/", $csp, $m);

    expect($csp)->toContain("default-src 'none'")->toContain("form-action 'none'")->toContain("frame-ancestors 'none'")
        ->and($m[1] ?? null)->not->toBeNull()
        ->and($r->getContent())->toContain('nonce="'.$m[1].'"')->toContain('crypto.subtle.digest')->toContain('Cocokkan dokumen')
        ->and($r->headers->get('X-Robots-Tag'))->toContain('noindex')
        ->and($r->headers->get('Cache-Control'))->toContain('no-store');
});

it('menyisipkan url verifikasi ber-uuid pada QR, bukan id berurutan', function () {
    $n = naskahTerbit();

    expect(QrNaskah::url($n))->toBe(route('verifikasi', $n->id))->and(Str::isUuid($n->id))->toBeTrue();
});
