<?php

use App\Actions\Naskah\RenderHtmlNaskah;
use App\Actions\Naskah\SimpanDraf;
use App\Filament\Resources\Naskahs\Pages\CreateNaskah;
use App\Filament\Resources\Naskahs\Pages\ListNaskahs;
use App\Models\Jabatan;
use App\Models\JenisNaskah;
use App\Models\Naskah;
use App\Models\User;
use App\Support\SanitasiHtml;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, PengaturanSeeder::class]);
    Filament::setCurrentPanel('admin');
});

function penyusun(string $peran = 'admin-persuratan'): User
{
    $u = User::factory()->create()->assignRole($peran);
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    return $u;
}

function inputDraf(string $kodeJenis = 'surat-dinas', array $ubah = []): array
{
    $jenis = JenisNaskah::firstWhere('kode', $kodeJenis);

    return $ubah + [
        'jenis_naskah_id' => $jenis->id,
        'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa',
        'perihal' => 'Permohonan data', 'isi' => '<p>Isi surat</p>',
        'penanda_tangan_jabatan_id' => $jenis->jabatan_penanda_tangan_bawaan_id ?? Jabatan::firstWhere('kode', 'dekan')->id,
        'mode_tanda_tangan' => 'basah',
        'data' => ['tujuan' => 'Kepala Dinas'],
        'tujuan' => [['nama' => 'Kepala Dinas Pendidikan']],
        'tembusan' => [['nama' => 'Arsip']],
    ];
}

it('membuat draf dengan penyusun dari autentikasi dan tujuan/tembusan', function () {
    $pelaku = penyusun();

    $n = app(SimpanDraf::class)->jalankan(null, inputDraf(), $pelaku);

    expect($n->status->value)->toBe('draf')
        ->and($n->penyusun_id)->toBe($pelaku->id)
        ->and($n->nomor)->toBeNull()
        ->and($n->data)->toBe(['tujuan' => 'Kepala Dinas'])
        ->and($n->tujuan->pluck('nama')->all())->toBe(['Kepala Dinas Pendidikan'])
        ->and($n->tembusan->pluck('nama')->all())->toBe(['Arsip']);
});

it('tidak mengambil penyusun dari input', function () {
    $pelaku = penyusun();
    $lain = penyusun();

    $n = app(SimpanDraf::class)->jalankan(null, inputDraf(ubah: ['penyusun_id' => $lain->id, 'status' => 'terbit', 'nomor' => 'X/1']), $pelaku);

    expect($n->penyusun_id)->toBe($pelaku->id)->and($n->status->value)->toBe('draf')->and($n->nomor)->toBeNull();
});

it('mewajibkan izin naskah.draf untuk membuat draf', function (string $peran, bool $boleh) {
    $aksi = fn () => app(SimpanDraf::class)->jalankan(null, inputDraf(), penyusun($peran));

    $boleh ? expect($aksi()->exists)->toBeTrue() : expect($aksi)->toThrow(AuthorizationException::class);
})->with([['admin-persuratan', true], ['dekan', true], ['kasubag', true], ['pegawai', false], ['pengurus-ormawa', false]]);

it('memvalidasi variabel wajib dan tipe sesuai definisi jenis', function (string $jenis, array $data, bool $lolos) {
    $aksi = fn () => app(SimpanDraf::class)->jalankan(null, inputDraf($jenis, ['data' => $data, 'tujuan' => [['nama' => 'X']]]), penyusun());

    $lolos ? expect($aksi()->exists)->toBeTrue() : expect($aksi)->toThrow(ValidationException::class);
})->with([
    'surat-dinas lengkap' => ['surat-dinas', ['tujuan' => 'Dinas'], true],
    'surat-dinas wajib kosong' => ['surat-dinas', ['tujuan' => ''], false],
    'surat-dinas tanpa data' => ['surat-dinas', [], false],
    'tugas tanggal salah' => ['surat-tugas', ['nama_ditugaskan' => 'A', 'tujuan_tugas' => 'B', 'tanggal_mulai' => 'bukan-tanggal', 'tanggal_selesai' => '2026-01-02', 'tempat' => 'C'], false],
    'tugas lengkap' => ['surat-tugas', ['nama_ditugaskan' => 'A', 'tujuan_tugas' => 'B', 'tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => '2026-01-02', 'tempat' => 'C'], true],
    'teks terlalu panjang' => ['surat-dinas', ['tujuan' => str_repeat('a', 300)], false],
]);

it('membuang kunci data yang tidak didefinisikan jenis', function () {
    $n = app(SimpanDraf::class)->jalankan(null, inputDraf(ubah: ['data' => ['tujuan' => 'Dinas', 'liar' => '<x>', 'penyusun_id' => 'x']]), penyusun());

    expect($n->data)->toBe(['tujuan' => 'Dinas']);
});

it('menolak mode tanda tangan yang tidak diizinkan jenis, jabatan non-penanda tangan, dan korespondensi tanpa tujuan', function () {
    $p = penyusun();

    expect(fn () => app(SimpanDraf::class)->jalankan(null, inputDraf('sk', ['mode_tanda_tangan' => 'visual', 'data' => ['tentang' => 'X']]), $p))->toThrow(ValidationException::class)
        ->and(fn () => app(SimpanDraf::class)->jalankan(null, inputDraf(ubah: ['penanda_tangan_jabatan_id' => Jabatan::firstWhere('kode', 'kasubag-umum')->id]), $p))->toThrow(ValidationException::class)
        ->and(fn () => app(SimpanDraf::class)->jalankan(null, inputDraf(ubah: ['tujuan' => []]), $p))->toThrow(ValidationException::class)
        ->and(Naskah::count())->toBe(0);
});

it('hanya penyusun yang mengubah draf dan jenis tidak dapat diganti', function () {
    $p = penyusun();
    $n = app(SimpanDraf::class)->jalankan(null, inputDraf(), $p);

    expect(fn () => app(SimpanDraf::class)->jalankan($n, inputDraf(ubah: ['perihal' => 'Dibajak']), penyusun()))->toThrow(AuthorizationException::class);

    $ubah = app(SimpanDraf::class)->jalankan($n, inputDraf(ubah: ['perihal' => 'Revisi', 'tujuan' => [['nama' => 'A'], ['nama' => 'B']]]), $p);
    expect($ubah->perihal)->toBe('Revisi')->and($ubah->tujuan)->toHaveCount(2)->and($ubah->tembusan)->toHaveCount(1);

    expect(fn () => app(SimpanDraf::class)->jalankan($n, inputDraf('nota-dinas', ['data' => ['kepada' => 'A', 'dari' => 'B']]), $p))->toThrow(ValidationException::class);
});

it('mengembalikan naskah dikembalikan menjadi draf saat penyusun mengubahnya', function () {
    $p = penyusun();
    $n = app(SimpanDraf::class)->jalankan(null, inputDraf(), $p);
    $n->update(['status' => 'dikembalikan']);

    $ubah = app(SimpanDraf::class)->jalankan($n->fresh(), inputDraf(), $p);

    expect($ubah->status->value)->toBe('draf');

    $ubah->update(['status' => 'paraf']);
    expect(fn () => app(SimpanDraf::class)->jalankan($ubah->fresh(), inputDraf(), $p))->toThrow(AuthorizationException::class);
});

it('membuang script, atribut event, gaya, dan tautan berbahaya dari isi (XSS)', function (string $masuk, array $tidakBoleh) {
    $hasil = SanitasiHtml::bersihkan($masuk);

    foreach ($tidakBoleh as $kata) {
        expect(strtolower($hasil))->not->toContain($kata);
    }
})->with([
    'script' => ['<p>a</p><script>alert(1)</script>', ['<script', 'alert(1)']],
    'onerror' => ['<img src=x onerror=alert(1)>', ['onerror', '<img']],
    'onclick' => ['<p onclick="alert(1)">x</p>', ['onclick']],
    'javascript href' => ['<a href="javascript:alert(1)">x</a>', ['javascript:']],
    'data href' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', ['data:']],
    'style' => ['<p style="background:url(javascript:alert(1))">x</p><style>p{}</style>', ['style', 'javascript']],
    'iframe' => ['<iframe src="https://evil.example"></iframe>', ['<iframe']],
    'svg' => ['<svg onload=alert(1)></svg>', ['<svg', 'onload']],
    'form' => ['<form action="https://evil"><input></form>', ['<form', '<input']],
]);

it('mempertahankan tag putih dan tautan https', function () {
    $hasil = SanitasiHtml::bersihkan('<h2>Judul</h2><p><strong>Tebal</strong> <em>miring</em></p><ul><li>satu</li></ul><a href="https://unsil.ac.id">tautan</a><table><tr><td colspan="2">sel</td></tr></table>');

    expect($hasil)->toContain('<h2>Judul</h2>')->toContain('<strong>Tebal</strong>')->toContain('<li>satu</li>')
        ->toContain('href="https://unsil.ac.id"')->toContain('rel="noopener noreferrer"')->toContain('colspan="2"');
});

it('menyanitasi isi yang disimpan lewat model langsung dan menyimpannya bersih', function () {
    $n = app(SimpanDraf::class)->jalankan(null, inputDraf(ubah: ['isi' => '<p>Halo</p><script>alert(1)</script><img src=x onerror=alert(1)>']), penyusun());

    expect($n->fresh()->isi)->toBe('<p>Halo</p>');

    $n->isi = '<p onclick="x()">Baru</p>';
    $n->save();
    expect($n->fresh()->isi)->toBe('<p>Baru</p>');
});

it('me-render pratinjau dengan semua nilai ter-escape dan banner draf', function () {
    $pelaku = penyusun();
    $n = app(SimpanDraf::class)->jalankan(null, inputDraf(ubah: [
        'perihal' => 'Hal <script>alert("p")</script>',
        'data' => ['tujuan' => '<img src=x onerror=alert(1)>'],
        'tujuan' => [['nama' => '<b>Pak</b> "Kepala"']],
        'isi' => '<p>Paragraf</p><script>alert(3)</script>',
    ]), $pelaku);

    $html = app(RenderHtmlNaskah::class)->jalankan($n->konteksRender());

    expect($html)
        ->toContain('DRAF — BELUM BERLAKU')->toContain('UNIVERSITAS SILIWANGI')->toContain('(diberikan saat ditandatangani)')
        ->toContain('Hal &lt;script&gt;')->toContain('&lt;img src=x onerror=alert(1)&gt;')->toContain('&lt;b&gt;Pak&lt;/b&gt;')
        ->toContain('<p>Paragraf</p>')
        ->not->toContain('<script')->not->toContain('onerror=alert(1)>')->not->toContain('<b>Pak');
});

it('menyajikan pratinjau hanya kepada yang berhak dengan CSP ketat', function () {
    $pelaku = penyusun();
    $n = app(SimpanDraf::class)->jalankan(null, inputDraf(), $pelaku);

    $r = $this->actingAs($pelaku)->get(route('naskah.pratinjau', $n));
    $r->assertOk()->assertSee('Permohonan data');
    expect($r->headers->get('Content-Security-Policy'))->toContain("default-src 'none'");

    $this->actingAs(penyusun('kasubag'))->get(route('naskah.pratinjau', $n))->assertForbidden();
    $this->actingAs(penyusun('admin-persuratan'))->get(route('naskah.pratinjau', $n))->assertOk();
    auth()->logout();
    $this->get(route('naskah.pratinjau', $n))->assertRedirect('/masuk');
});

it('hanya mengizinkan penghapusan lunak untuk draf', function () {
    $p = penyusun();
    $n = app(SimpanDraf::class)->jalankan(null, inputDraf(), $p);

    $n->delete();
    expect(Naskah::count())->toBe(0)->and(Naskah::withTrashed()->count())->toBe(1);

    $n2 = app(SimpanDraf::class)->jalankan(null, inputDraf(), $p);
    $n2->update(['status' => 'terbit']);
    expect(fn () => $n2->fresh()->delete())->toThrow(LogicException::class)
        ->and(fn () => $n2->fresh()->forceDelete())->toThrow(LogicException::class);
});

it('membatasi daftar naskah ke yang terkait pengguna', function () {
    $a = penyusun('dekan');
    $b = penyusun('kasubag');
    $milikA = app(SimpanDraf::class)->jalankan(null, inputDraf(), $a);
    $milikB = app(SimpanDraf::class)->jalankan(null, inputDraf(ubah: ['perihal' => 'Milik B']), $b);

    Livewire::actingAs($a)->test(ListNaskahs::class)->assertCanSeeTableRecords([$milikA])->assertCanNotSeeTableRecords([$milikB]);
    Livewire::actingAs(penyusun('admin-persuratan'))->test(ListNaskahs::class)->assertCanSeeTableRecords([$milikA, $milikB]);
});

it('membuat draf lewat formulir panel dengan isian templat dinamis', function () {
    $jenis = JenisNaskah::firstWhere('kode', 'surat-dinas');
    $pelaku = penyusun();

    Livewire::actingAs($pelaku)->test(CreateNaskah::class)
        ->fillForm([
            'jenis_naskah_id' => $jenis->id, 'perihal' => 'Via formulir', 'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa',
            'data' => ['tujuan' => 'Dinas X'], 'isi' => '<p>Isi</p>',
            'penanda_tangan_jabatan_id' => $jenis->jabatan_penanda_tangan_bawaan_id, 'mode_tanda_tangan' => 'basah',
            'tujuan' => [['nama' => 'Dinas X']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $n = Naskah::firstWhere('perihal', 'Via formulir');
    expect($n->data['tujuan'])->toBe('Dinas X')->and($n->penyusun_id)->toBe($pelaku->id);
});

it('menolak formulir bila variabel wajib templat kosong', function () {
    $jenis = JenisNaskah::firstWhere('kode', 'surat-dinas');

    Livewire::actingAs(penyusun())->test(CreateNaskah::class)
        ->fillForm(['jenis_naskah_id' => $jenis->id, 'perihal' => 'X', 'penanda_tangan_jabatan_id' => $jenis->jabatan_penanda_tangan_bawaan_id, 'mode_tanda_tangan' => 'basah', 'tujuan' => [['nama' => 'A']]])
        ->call('create')
        ->assertHasFormErrors(['data.tujuan']);
});

it('membatasi keluaran HTML mentah pada satu komponen terpusat', function () {
    $berkas = collect(File::allFiles(resource_path('views')))->filter(fn ($f) => str_ends_with($f->getFilename(), '.blade.php'));
    $pelanggar = $berkas->filter(fn ($f) => str_contains(file_get_contents($f->getPathname()), '{!!'))
        ->map(fn ($f) => str_replace(resource_path('views').'/', '', $f->getPathname()))->values()->all();

    expect($berkas)->not->toBeEmpty()->and($pelanggar)->toBe(['components/isi-aman.blade.php']);
});
