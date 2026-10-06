<?php

use App\Actions\Naskah\AjukanParaf;
use App\Actions\Naskah\SimpanDraf;
use App\Actions\Naskah\TandaTangani;
use App\Actions\Naskah\TransisiNaskah;
use App\Enums\StatusNaskah;
use App\Filament\Resources\Naskahs\Pages\ViewNaskah;
use App\Jobs\TerbitkanNaskah;
use App\Models\Jabatan;
use App\Models\JenisNaskah;
use App\Models\KlasifikasiArsip;
use App\Models\Naskah;
use App\Models\PemangkuJabatan;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Services\Naskah\PengambilGambarTtd;
use App\Services\Naskah\QrNaskah;
use App\Services\Naskah\RenderNaskah;
use App\Support\HostAman;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\KlasifikasiArsipSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, PengaturanSeeder::class, KlasifikasiArsipSeeder::class]);
    Filament::setCurrentPanel('admin');
    HostAman::$penyelesai = null;
});

afterEach(fn () => HostAman::$penyelesai = null);

function dekanAktif(): User
{
    $u = User::factory()->create(['name' => 'Prof. Dekan', 'nip_nim' => '19700101'])->assignRole('dekan');
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    PemangkuJabatan::create(['jabatan_id' => Jabatan::firstWhere('kode', 'dekan')->id, 'user_id' => $u->id, 'mulai' => now()->subYear()]);

    return $u;
}

function ditandatangani(User $dekan, array $ubah = [], ?User $penyusun = null): Naskah
{
    Queue::fake();
    $penyusun ??= User::factory()->create()->assignRole('admin-persuratan');
    $jenis = JenisNaskah::firstWhere('kode', 'surat-dinas');
    $n = app(SimpanDraf::class)->jalankan(null, $ubah + [
        'jenis_naskah_id' => $jenis->id, 'klasifikasi_arsip_id' => KlasifikasiArsip::firstWhere('kode', 'KM.03.02')->id,
        'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa', 'perihal' => 'Undangan rapat',
        'penanda_tangan_jabatan_id' => $jenis->jabatan_penanda_tangan_bawaan_id, 'mode_tanda_tangan' => 'basah',
        'data' => ['tujuan' => 'Kepala Dinas'], 'tujuan' => [['nama' => 'Kepala Dinas']], 'isi' => '<p>Mohon hadir.</p>',
    ], $penyusun);

    return app(TandaTangani::class)->jalankan(app(AjukanParaf::class)->jalankan($n, $penyusun, []), $dekan);
}

it('mengantrekan penerbitan di antrean pdf setelah tanda tangan', function () {
    ditandatangani(dekanAktif());

    Queue::assertPushedOn('pdf', TerbitkanNaskah::class);
});

it('menerbitkan: hash PDF dicatat, status terbit, dan idempoten', function () {
    $n = ditandatangani(dekanAktif());

    (new TerbitkanNaskah($n))->handle(app(RenderNaskah::class), app(TransisiNaskah::class));
    $n = $n->fresh();

    expect($n->status)->toBe(StatusNaskah::Terbit)
        ->and($n->hash_pdf)->toMatch('/^[a-f0-9]{64}$/')
        ->and($n->diterbitkan_pada)->not->toBeNull()
        ->and($n->riwayat->pluck('ke_status')->all())->toContain('terbit');

    $hash = $n->hash_pdf;
    (new TerbitkanNaskah($n))->handle(app(RenderNaskah::class), app(TransisiNaskah::class));
    expect($n->fresh()->hash_pdf)->toBe($hash)->and($n->fresh()->riwayat->where('ke_status', 'terbit'))->toHaveCount(1);
});

it('tidak menerbitkan naskah yang belum ditandatangani', function () {
    $draf = app(SimpanDraf::class)->jalankan(null, [
        'jenis_naskah_id' => JenisNaskah::firstWhere('kode', 'surat-dinas')->id, 'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa', 'perihal' => 'x',
        'penanda_tangan_jabatan_id' => Jabatan::firstWhere('kode', 'dekan')->id, 'mode_tanda_tangan' => 'basah', 'data' => ['tujuan' => 'D'], 'tujuan' => [['nama' => 'D']],
    ], User::factory()->create()->assignRole('admin-persuratan'));

    (new TerbitkanNaskah($draf))->handle(app(RenderNaskah::class), app(TransisiNaskah::class));

    expect($draf->fresh()->status)->toBe(StatusNaskah::Draf)->and($draf->fresh()->hash_pdf)->toBeNull();
});

it('menghasilkan hash yang sama untuk dua render snapshot yang sama', function () {
    $n = ditandatangani(dekanAktif());
    $render = app(RenderNaskah::class);

    $a = $render->pdf($n->fresh());
    sleep(2);
    $b = $render->pdf($n->fresh());

    expect($a)->toStartWith('%PDF')->and(RenderNaskah::hash($a))->toBe(RenderNaskah::hash($b));
});

it('memuat nomor, QR verifikasi, dan penanda tangan pada render', function () {
    $n = ditandatangani(dekanAktif())->fresh();

    $html = app(RenderNaskah::class)->html($n);

    expect($html)->toContain($n->nomor)->toContain('Undangan rapat')->toContain('Prof. Dekan')->toContain('NIP 19700101')
        ->toContain('data:image/png;base64,')->toContain('Kode QR verifikasi')->not->toContain('DRAF');
    expect(QrNaskah::url($n))->toEndWith('/verifikasi/'.$n->id);
});

it('merender dari snapshot, bukan data hidup', function () {
    $n = ditandatangani(dekanAktif())->fresh();
    DB::table('naskah')->where('id', $n->id)->update(['perihal' => 'DIUBAH DI DB', 'isi' => '<p>Isi palsu</p>']);

    $html = app(RenderNaskah::class)->html($n->fresh());

    expect($html)->toContain('Undangan rapat')->toContain('Mohon hadir.')->not->toContain('DIUBAH DI DB')->not->toContain('Isi palsu');
});

it('menolak render naskah tanpa snapshot', function () {
    $draf = app(SimpanDraf::class)->jalankan(null, [
        'jenis_naskah_id' => JenisNaskah::firstWhere('kode', 'surat-dinas')->id, 'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa', 'perihal' => 'x',
        'penanda_tangan_jabatan_id' => Jabatan::firstWhere('kode', 'dekan')->id, 'mode_tanda_tangan' => 'basah', 'data' => ['tujuan' => 'D'], 'tujuan' => [['nama' => 'D']],
    ], User::factory()->create()->assignRole('admin-persuratan'));

    expect(fn () => app(RenderNaskah::class)->html($draf))->toThrow(LogicException::class);
});

describe('tanda tangan visual', function () {
    function dekanDenganTtd(): User
    {
        $dekan = dekanAktif();
        TautanBerkas::create([
            'pemilik_type' => $dekan->getMorphClass(), 'pemilik_id' => $dekan->id, 'jenis' => 'ttd_visual',
            'url' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view', 'ditambahkan_oleh' => $dekan->id,
        ]);

        return $dekan;
    }

    beforeEach(function () {
        Queue::fake(); // jangan jalankan pemeriksaan tautan sungguhan saat tautan dibuat
        HostAman::$penyelesai = fn () => ['142.250.4.100'];
    });

    it('menyisipkan gambar sebagai data URI dan tidak pernah sebagai URL', function () {
        Http::fake(['lh3.googleusercontent.com/*' => Http::response(base64_decode(PNG), 200, ['Content-Type' => 'image/png'])]);
        $dekan = dekanDenganTtd();
        $n = ditandatangani($dekan, ['mode_tanda_tangan' => 'visual'])->fresh();

        $html = app(RenderNaskah::class)->html($n);

        expect($html)->toContain('<img src="data:image/png;base64,')
            ->not->toContain('drive.google.com')->not->toContain('googleusercontent')->not->toContain('ttd_visual');
        Http::assertSentCount(1);

        // render ulang tidak menembak jaringan lagi (gambar dibekukan di snapshot)
        app(RenderNaskah::class)->pdf($n);
        Http::assertSentCount(1);
    });

    it('menolak gambar yang bukan PNG/JPEG, terlalu besar, palsu, atau gagal diambil', function (Closure $respons) {
        Http::fake(['lh3.googleusercontent.com/*' => $respons]);
        $dekan = dekanDenganTtd();

        expect(fn () => app(PengambilGambarTtd::class)->dataUri($dekan))->toThrow(ValidationException::class);
    })->with([
        'html' => [fn () => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])],
        'svg' => [fn () => Http::response('<svg onload=alert(1)/>', 200, ['Content-Type' => 'image/svg+xml'])],
        'terlalu besar' => [fn () => Http::response(str_repeat('a', 1_100_000), 200, ['Content-Type' => 'image/png'])],
        'png palsu' => [fn () => Http::response('bukan gambar', 200, ['Content-Type' => 'image/png'])],
        'kosong' => [fn () => Http::response('', 200, ['Content-Type' => 'image/png'])],
        '404' => [fn () => Http::response('', 404)],
        'pengalihan' => [fn () => Http::response('', 302, ['Location' => 'http://169.254.169.254/'])],
        'galat jaringan' => [fn () => fn () => throw new ConnectionException('timeout')],
    ]);

    it('menolak host yang resolve ke IP pribadi tanpa mengirim permintaan', function () {
        Http::fake();
        HostAman::$penyelesai = fn () => ['10.0.0.9'];
        $dekan = dekanDenganTtd();

        expect(fn () => app(PengambilGambarTtd::class)->dataUri($dekan))->toThrow(ValidationException::class);
        Http::assertNothingSent();
    });

    it('menolak tautan yang domainnya sudah tidak diizinkan dan pejabat tanpa tautan', function () {
        Http::fake();
        $dekan = dekanDenganTtd();
        config(['berkas.domain_putih' => ['docs.google.com']]);
        TautanBerkas::query()->update(['drive_file_id' => null, 'url' => 'https://evil.example.com/a.png']);

        expect(fn () => app(PengambilGambarTtd::class)->dataUri($dekan))->toThrow(ValidationException::class)
            ->and(fn () => app(PengambilGambarTtd::class)->dataUri(User::factory()->create()))->toThrow(ValidationException::class);
        Http::assertNothingSent();
    });
});

describe('unduh PDF', function () {
    it('men-stream PDF dari snapshot bagi yang berhak', function () {
        $penyusun = User::factory()->create()->assignRole('admin-persuratan');
        $n = ditandatangani(dekanAktif(), penyusun: $penyusun);

        $r = $this->actingAs($penyusun)->get(route('naskah.pdf', $n));

        $r->assertOk()->assertHeader('Content-Type', 'application/pdf');
        expect($r->getContent())->toStartWith('%PDF')->and($r->headers->get('Cache-Control'))->toContain('no-store');
    });

    it('menolak yang tidak berhak dan tamu', function () {
        $n = ditandatangani(dekanAktif());

        $this->actingAs(User::factory()->create()->assignRole('kasubag'))->get(route('naskah.pdf', $n))->assertForbidden();
        auth()->logout();
        $this->get(route('naskah.pdf', $n))->assertRedirect('/masuk');
    });

    it('mengembalikan 404 untuk draf dan naskah dibatalkan', function () {
        $admin = User::factory()->create()->assignRole('admin-persuratan');
        $draf = app(SimpanDraf::class)->jalankan(null, [
            'jenis_naskah_id' => JenisNaskah::firstWhere('kode', 'surat-dinas')->id, 'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa', 'perihal' => 'x',
            'penanda_tangan_jabatan_id' => Jabatan::firstWhere('kode', 'dekan')->id, 'mode_tanda_tangan' => 'basah', 'data' => ['tujuan' => 'D'], 'tujuan' => [['nama' => 'D']],
        ], $admin);
        $this->actingAs($admin)->get(route('naskah.pdf', $draf))->assertNotFound();

        $n = ditandatangani(dekanAktif());
        $n->forceFill(['status' => StatusNaskah::Dibatalkan])->save();
        $this->actingAs($admin)->get(route('naskah.pdf', $n))->assertNotFound();
    });

    it('mencatat peringatan bila hash render ulang tidak cocok', function () {
        $admin = User::factory()->create()->assignRole('admin-persuratan');
        $n = ditandatangani(dekanAktif());
        $n->forceFill(['hash_pdf' => str_repeat('0', 64)])->save();
        Log::spy();

        $this->actingAs($admin)->get(route('naskah.pdf', $n))->assertOk();

        Log::shouldHaveReceived('warning')->once();
    });

    it('tidak memberi peringatan bila hash cocok', function () {
        $admin = User::factory()->create()->assignRole('admin-persuratan');
        $n = ditandatangani(dekanAktif());
        (new TerbitkanNaskah($n))->handle(app(RenderNaskah::class), app(TransisiNaskah::class));
        Log::spy();

        $this->actingAs($admin)->get(route('naskah.pdf', $n->fresh()))->assertOk();

        Log::shouldNotHaveReceived('warning');
    });
});

describe('pindaian tanda tangan basah', function () {
    it('dicatat admin penomoran sebagai tautan naskah_basah', function () {
        $admin = User::factory()->create()->assignRole('admin-persuratan');
        $admin->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $n = ditandatangani(dekanAktif());

        Livewire::actingAs($admin)->test(ViewNaskah::class, ['record' => $n->getKey()])
            ->assertActionVisible('catatPindaian')
            ->callAction('catatPindaian', ['url' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view'])
            ->assertHasNoActionErrors();

        expect($n->tautan()->where('jenis', 'naskah_basah')->count())->toBe(1);
    });

    it('tidak tersedia untuk mode visual atau pengguna lain', function () {
        $admin = User::factory()->create()->assignRole('admin-persuratan');
        $admin->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $n = ditandatangani(dekanAktif());

        expect($admin->can('catatPindaian', $n))->toBeTrue()
            ->and(User::factory()->create()->assignRole('kasubag')->can('catatPindaian', $n))->toBeFalse();

        $n->forceFill(['mode_tanda_tangan' => 'visual'])->saveQuietly();
        expect($admin->can('catatPindaian', $n->fresh()))->toBeFalse();
    });
});
