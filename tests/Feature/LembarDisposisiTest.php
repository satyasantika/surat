<?php

use App\Actions\Disposisi\BuatDisposisi;
use App\Actions\Disposisi\HtmlLembarDisposisi;
use App\Actions\Masuk\RegistrasiSuratMasuk;
use App\Models\DisposisiPenerima;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Support\Pengaturan;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    config(['pdf.driver' => 'dompdf']);
    $this->seed([PeranDanIzinSeeder::class, RegisterNomorSeeder::class, PengaturanSeeder::class]);
});

function orangLembar(string $peran): User
{
    return User::factory()->create()->assignRole($peran);
}

function suratLembar(array $ubah = []): SuratMasuk
{
    return app(RegistrasiSuratMasuk::class)->jalankan($ubah + [
        'nomor_surat' => '55/Z/2026', 'tanggal_surat' => now()->toDateString(), 'asal' => 'Kementerian Uji',
        'perihal' => 'Perihal lembar', 'ringkasan' => 'Ringkasan lembar', 'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'segera',
        'pindaian_url' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view',
    ], orangLembar('admin-persuratan'));
}

it('men-stream pdf untuk yang berhak tanpa menyimpannya', function () {
    $surat = suratLembar();

    $respons = $this->actingAs(orangLembar('admin-persuratan'))->get(route('surat-masuk.lembar-disposisi', $surat));

    $respons->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($respons->getContent())->toStartWith('%PDF')
        ->and($respons->headers->get('Content-Disposition'))->toContain('inline')->toContain('lembar-disposisi-')
        ->and($respons->headers->get('Cache-Control'))->toContain('no-store');
});

it('menolak pengguna tanpa hak lihat dan tamu', function (string $peran) {
    $surat = suratLembar();

    $this->actingAs(orangLembar($peran))->get(route('surat-masuk.lembar-disposisi', $surat))->assertForbidden();
})->with(['wakil-dekan', 'kasubag', 'pegawai', 'pengurus-ormawa']);

it('mengalihkan tamu ke halaman masuk', function () {
    $this->get(route('surat-masuk.lembar-disposisi', suratLembar()))->assertRedirect('/masuk');
});

it('mengizinkan penerima disposisi mencetak lembar', function () {
    $surat = suratLembar();
    $pegawai = orangLembar('pegawai');
    app(BuatDisposisi::class)->jalankan($surat, orangLembar('dekan'), [$pegawai->id], ['tindak_lanjuti']);

    $this->actingAs($pegawai)->get(route('surat-masuk.lembar-disposisi', $surat))->assertOk();
});

it('memuat kop, data surat, instruksi tercentang, penerima, dan rantai lanjutan', function () {
    $surat = suratLembar();
    $dekan = orangLembar('dekan');
    $wd = orangLembar('wakil-dekan');
    $pegawai = orangLembar('pegawai');
    $awal = app(BuatDisposisi::class)->jalankan($surat, $dekan, [$wd->id], ['tindak_lanjuti', 'hadiri'], 'Catatan dekan');
    app(BuatDisposisi::class)->jalankan($surat, $wd, [$pegawai->id], ['pelajari'], 'Catatan WD', null, $awal->penerima[0]);

    $html = app(HtmlLembarDisposisi::class)->jalankan($surat->fresh(), $dekan);

    expect($html)
        ->toContain('UNIVERSITAS SILIWANGI')->toContain('LEMBAR DISPOSISI')
        ->toContain($surat->nomor_agenda)->toContain('55/Z/2026')->toContain('Kementerian Uji')->toContain('Perihal lembar')->toContain('Ringkasan lembar')
        ->toContain('[x] Tindak lanjuti')->toContain('[x] Hadiri')->toContain('[x] Pelajari')->toContain('[ ] Arsipkan')
        ->toContain($wd->name)->toContain($pegawai->name)->toContain('Catatan dekan')->toContain('Catatan WD')
        ->toContain('Disposisi lanjutan')->toContain('margin-left: 18px');
});

it('mengambil kop dari pengaturan dan meloloskan karakter khusus', function () {
    Pengaturan::set('kop_surat', ['BARIS <b>KOP</b> & "UJI"']);
    $surat = suratLembar(['perihal' => 'Perihal <script>alert(1)</script>']);

    $html = app(HtmlLembarDisposisi::class)->jalankan($surat, orangLembar('admin-persuratan'));

    expect($html)->toContain('BARIS &lt;b&gt;KOP&lt;/b&gt; &amp; &quot;UJI&quot;')
        ->not->toContain('<script>alert(1)</script>')->toContain('&lt;script&gt;');
});

it('tidak menampilkan perihal dan ringkasan surat rahasia untuk admin tetapi untuk dekan ya', function () {
    $surat = suratLembar(['klasifikasi_keamanan' => 'rahasia', 'perihal' => 'Perihal rahasia ZXY', 'ringkasan' => 'Ringkasan rahasia ZXY']);

    $untukAdmin = app(HtmlLembarDisposisi::class)->jalankan($surat, orangLembar('admin-persuratan'));
    $untukDekan = app(HtmlLembarDisposisi::class)->jalankan($surat, orangLembar('dekan'));

    expect($untukAdmin)->toContain('[RAHASIA]')->not->toContain('ZXY')
        ->and($untukDekan)->toContain('Perihal rahasia ZXY')->toContain('Ringkasan rahasia ZXY');

    $this->actingAs(orangLembar('admin-persuratan'))->get(route('surat-masuk.lembar-disposisi', $surat))->assertOk();
});

it('menampilkan pesan bila belum ada disposisi', function () {
    $html = app(HtmlLembarDisposisi::class)->jalankan(suratLembar(), orangLembar('dekan'));

    expect($html)->toContain('Belum ada disposisi.')->and(DisposisiPenerima::count())->toBe(0);
});
