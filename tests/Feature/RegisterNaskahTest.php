<?php

use App\Actions\Masuk\RegistrasiSuratMasuk;
use App\Actions\Naskah\AjukanParaf;
use App\Actions\Naskah\BatalkanNaskah;
use App\Actions\Naskah\SimpanDraf;
use App\Actions\Naskah\TandaTangani;
use App\Actions\Naskah\TransisiNaskah;
use App\Enums\StatusNaskah;
use App\Filament\Resources\Naskahs\Pages\ViewNaskah;
use App\Filament\Resources\RegisterSuratKeluars\Pages\ListRegisterSuratKeluars;
use App\Filament\Resources\RegisterSuratKeluars\RegisterSuratKeluarResource;
use App\Filament\Resources\RegisterSuratMasuks\Pages\ListRegisterSuratMasuks;
use App\Jobs\TerbitkanNaskah;
use App\Models\Aktivitas;
use App\Models\Jabatan;
use App\Models\JenisNaskah;
use App\Models\KlasifikasiArsip;
use App\Models\Naskah;
use App\Models\NomorTerpakai;
use App\Models\PemangkuJabatan;
use App\Models\RegisterNomor;
use App\Models\User;
use App\Services\Naskah\RenderNaskah;
use App\Services\Register\EksporXlsx;
use Carbon\CarbonImmutable;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\KlasifikasiArsipSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelReader;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, PengaturanSeeder::class, KlasifikasiArsipSeeder::class]);
    Filament::setCurrentPanel('admin');
    File::cleanDirectory(storage_path('app/tmp'));
    touch(storage_path('app/tmp/.gitkeep'));
});

afterEach(function () {
    foreach (File::files(storage_path('app/tmp')) as $f) {
        if ($f->getFilename() !== '.gitkeep') {
            File::delete($f->getPathname());
        }
    }
});

function dekanReg(): User
{
    $jabatan = Jabatan::firstWhere('kode', 'dekan');

    if ($pemangku = $jabatan->pemangkuPada(now())) {
        return $pemangku->user;
    }

    $dekan = User::factory()->create()->assignRole('dekan');
    PemangkuJabatan::create(['jabatan_id' => $jabatan->id, 'user_id' => $dekan->id, 'mulai' => now()->subYear()]);

    return $dekan;
}

function adminReg(): User
{
    $u = User::factory()->create()->assignRole('admin-persuratan');
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    return $u;
}

function terbitkan(array $ubah = [], ?User $dekan = null, string $kodeJenis = 'surat-dinas', ?string $tanggal = null): Naskah
{
    $dekan ??= dekanReg();
    $penyusun = adminReg();
    $jenis = JenisNaskah::firstWhere('kode', $kodeJenis);

    $data = $kodeJenis === 'surat-dinas' ? ['tujuan' => 'Dinas'] : ['tentang' => 'Hal'];

    $n = app(SimpanDraf::class)->jalankan(null, $ubah + [
        'jenis_naskah_id' => $jenis->id, 'klasifikasi_arsip_id' => KlasifikasiArsip::firstWhere('kode', 'KM.03.02')->id,
        'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa', 'perihal' => 'Perihal register',
        'penanda_tangan_jabatan_id' => $jenis->jabatan_penanda_tangan_bawaan_id, 'mode_tanda_tangan' => 'basah',
        'data' => $data, 'tujuan' => [['nama' => 'Tujuan A'], ['nama' => 'Tujuan B']],
    ], $penyusun);

    if ($tanggal) {
        CarbonImmutable::setTestNow($tanggal);
    }
    $n = app(TandaTangani::class)->jalankan(app(AjukanParaf::class)->jalankan($n, $penyusun, []), $dekan);
    (new TerbitkanNaskah($n))->handle(app(RenderNaskah::class), app(TransisiNaskah::class));
    CarbonImmutable::setTestNow();

    return $n->fresh();
}

describe('pembatalan', function () {
    it('membatalkan naskah terbit: status, alasan, nomor batal, riwayat, tetap di register', function () {
        $n = terbitkan();
        $admin = adminReg();

        app(BatalkanNaskah::class)->jalankan($n, $admin, 'Salah alamat tujuan');
        $n = $n->fresh();

        expect($n->status)->toBe(StatusNaskah::Dibatalkan)->and($n->alasan_batal)->toBe('Salah alamat tujuan')->and($n->dibatalkan_pada)->not->toBeNull()
            ->and($n->nomor)->not->toBeNull()
            ->and(NomorTerpakai::find($n->nomor_terpakai_id)->dibatalkan)->toBeTrue()
            ->and($n->riwayat->last()->catatan)->toBe('Salah alamat tujuan');
    });

    it('tidak memakai ulang nomor yang dibatalkan', function () {
        $a = terbitkan();
        app(BatalkanNaskah::class)->jalankan($a, adminReg(), 'Dibatalkan uji');
        $b = terbitkan();

        expect($a->nomor)->toStartWith('1/')->and($b->nomor)->toStartWith('2/')->and(Naskah::whereNotNull('nomor')->count())->toBe(2);
    });

    it('mewajibkan alasan dan izin naskah.batalkan', function () {
        $n = terbitkan();

        expect(fn () => app(BatalkanNaskah::class)->jalankan($n, adminReg(), '  '))->toThrow(ValidationException::class)
            ->and(fn () => app(BatalkanNaskah::class)->jalankan($n, adminReg(), 'abc'))->toThrow(ValidationException::class)
            ->and(fn () => app(BatalkanNaskah::class)->jalankan($n, dekanReg(), 'Alasan cukup panjang'))->toThrow(AuthorizationException::class)
            ->and(fn () => app(BatalkanNaskah::class)->jalankan($n, User::factory()->create()->assignRole('kasubag'), 'Alasan cukup panjang'))->toThrow(AuthorizationException::class);
        expect($n->fresh()->status)->toBe(StatusNaskah::Terbit);
    });

    it('hanya untuk naskah ditandatangani atau terbit dan tidak dua kali', function () {
        $admin = adminReg();
        $draf = app(SimpanDraf::class)->jalankan(null, [
            'jenis_naskah_id' => JenisNaskah::firstWhere('kode', 'surat-dinas')->id, 'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa', 'perihal' => 'x',
            'penanda_tangan_jabatan_id' => Jabatan::firstWhere('kode', 'dekan')->id, 'mode_tanda_tangan' => 'basah', 'data' => ['tujuan' => 'D'], 'tujuan' => [['nama' => 'D']],
        ], $admin);

        expect(fn () => app(BatalkanNaskah::class)->jalankan($draf, $admin, 'Alasan cukup panjang'))->toThrow(AuthorizationException::class);

        $n = terbitkan();
        app(BatalkanNaskah::class)->jalankan($n, $admin, 'Alasan pertama');
        expect(fn () => app(BatalkanNaskah::class)->jalankan($n->fresh(), $admin, 'Alasan kedua'))->toThrow(AuthorizationException::class);
        expect(Aktivitas::where('log_name', 'nomor')->where('event', 'batal')->count())->toBe(1);
    });

    it('menampilkan status dibatalkan di verifikasi publik dan menutup unduhan PDF', function () {
        $n = terbitkan();
        app(BatalkanNaskah::class)->jalankan($n, adminReg(), 'Dibatalkan karena revisi');

        $this->get(route('verifikasi', $n))->assertOk()->assertSee('DIBATALKAN')->assertSee('Dibatalkan karena revisi');
        $this->actingAs(adminReg())->get(route('naskah.pdf', $n))->assertNotFound();
    });

    it('membatalkan lewat aksi di halaman naskah', function () {
        $n = terbitkan();
        $admin = adminReg();

        Livewire::actingAs($admin)->test(ViewNaskah::class, ['record' => $n->getKey()])
            ->assertActionVisible('batalkan')
            ->callAction('batalkan', ['alasan' => 'Dibatalkan lewat panel']);

        expect($n->fresh()->status)->toBe(StatusNaskah::Dibatalkan);

        Livewire::actingAs($admin)->test(ViewNaskah::class, ['record' => $n->getKey()])->assertActionHidden('batalkan');
    });
});

describe('register surat keluar', function () {
    it('hanya memuat naskah bernomor dan tetap menampilkan yang dibatalkan', function () {
        $terbit = terbitkan();
        $batal = terbitkan();
        app(BatalkanNaskah::class)->jalankan($batal, adminReg(), 'Batal uji');
        $draf = app(SimpanDraf::class)->jalankan(null, [
            'jenis_naskah_id' => JenisNaskah::firstWhere('kode', 'surat-dinas')->id, 'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa', 'perihal' => 'Masih draf',
            'penanda_tangan_jabatan_id' => Jabatan::firstWhere('kode', 'dekan')->id, 'mode_tanda_tangan' => 'basah', 'data' => ['tujuan' => 'D'], 'tujuan' => [['nama' => 'D']],
        ], adminReg());

        Livewire::actingAs(adminReg())->test(ListRegisterSuratKeluars::class)
            ->assertCanSeeTableRecords([$terbit, $batal])->assertCanNotSeeTableRecords([$draf])
            ->assertSee('Dibatalkan')->assertSee('Tujuan A; Tujuan B');
    });

    it('menyaring menurut periode, register, jenis, dan status', function () {
        $maret = terbitkan(tanggal: '2026-03-10 09:00:00');
        $juni = terbitkan(tanggal: '2026-06-10 09:00:00');
        $sk = terbitkan(['perihal' => 'SK uji'], kodeJenis: 'sk', tanggal: '2026-06-11 09:00:00');
        app(BatalkanNaskah::class)->jalankan($juni, adminReg(), 'Batal uji');
        $this->actingAs(adminReg());

        Livewire::test(ListRegisterSuratKeluars::class)
            ->filterTable('periode', ['dari' => '2026-06-01', 'sampai' => '2026-06-30'])
            ->assertCanSeeTableRecords([$juni, $sk])->assertCanNotSeeTableRecords([$maret])
            ->resetTableFilters()
            ->filterTable('register', RegisterNomor::firstWhere('kode', 'sk-dekan')->id)
            ->assertCanSeeTableRecords([$sk])->assertCanNotSeeTableRecords([$maret, $juni])
            ->resetTableFilters()
            ->filterTable('status', 'dibatalkan')
            ->assertCanSeeTableRecords([$juni])->assertCanNotSeeTableRecords([$maret, $sk])
            ->resetTableFilters()
            ->filterTable('jenis_naskah_id', JenisNaskah::firstWhere('kode', 'sk')->id)
            ->assertCanSeeTableRecords([$sk])->assertCanNotSeeTableRecords([$maret]);
    });

    it('membatasi akses ke pemegang arsip.lihat dan menyamarkan perihal non-biasa', function () {
        $rahasia = terbitkan(['klasifikasi_keamanan' => 'rahasia', 'perihal' => 'Perihal rahasia QWE']);
        $kasubag = User::factory()->create()->assignRole('kasubag');
        $kasubag->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $pegawai = User::factory()->create()->assignRole('pegawai');
        $pegawai->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

        $this->actingAs($pegawai)->get('/admin/register-surat-keluar')->assertForbidden();
        $this->actingAs($kasubag)->get('/admin/register-surat-keluar')->assertOk();

        Livewire::actingAs($kasubag)->test(ListRegisterSuratKeluars::class)->assertSee('[DIBATASI]')->assertDontSee('Perihal rahasia QWE')
            ->searchTable('QWE')->assertCanNotSeeTableRecords([$rahasia]);
        Livewire::actingAs(adminReg())->test(ListRegisterSuratKeluars::class)->assertSee('Perihal rahasia QWE');
    });

    it('bersifat hanya-baca', function () {
        $n = terbitkan();
        $resource = RegisterSuratKeluarResource::class;
        $this->actingAs(adminReg());

        expect($resource::canCreate())->toBeFalse()->and($resource::canEdit($n))->toBeFalse()->and($resource::canDelete($n))->toBeFalse();
    });
});

describe('register surat masuk', function () {
    it('menampilkan surat masuk dengan perihal rahasia tersamar bagi yang tidak berhak', function () {
        $admin = adminReg();
        $surat = app(RegistrasiSuratMasuk::class)->jalankan([
            'nomor_surat' => '1/A', 'tanggal_surat' => now()->toDateString(), 'asal' => 'Instansi', 'perihal' => 'Perihal masuk rahasia RTY',
            'klasifikasi_keamanan' => 'rahasia', 'derajat_kecepatan' => 'biasa', 'pindaian_url' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view',
        ], $admin);

        Livewire::actingAs($admin)->test(ListRegisterSuratMasuks::class)->assertCanSeeTableRecords([$surat])->assertSee('[RAHASIA]')->assertDontSee('Perihal masuk rahasia RTY');
        $this->actingAs($admin)->get('/admin/register-surat-masuk')->assertOk();
    });
});

describe('ekspor XLSX', function () {
    it('menulis berkas xlsx dengan baris register dan perihal tersamar', function () {
        $biasa = terbitkan(['perihal' => 'Perihal biasa']);
        $rahasia = terbitkan(['klasifikasi_keamanan' => 'rahasia', 'perihal' => 'Perihal rahasia ZZZ']);
        $kasubag = User::factory()->create()->assignRole('kasubag');
        $kasubag->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

        Livewire::actingAs($kasubag)->test(ListRegisterSuratKeluars::class)->callAction('ekspor');

        $berkas = collect(File::files(storage_path('app/tmp')))->first(fn ($f) => str_ends_with($f->getFilename(), '.xlsx'));
        expect($berkas)->not->toBeNull()->and($berkas->getFilename())->toStartWith("register-keluar-{$kasubag->id}-");

        $baris = SimpleExcelReader::create($berkas->getPathname())->getRows()->all();
        $nomor = collect($baris)->pluck('Nomor')->all();
        $perihal = collect($baris)->pluck('Perihal')->all();

        expect($nomor)->toContain($biasa->nomor)->toContain($rahasia->nomor)
            ->and($perihal)->toContain('Perihal biasa')->toContain('[DIBATASI]')->not->toContain('Perihal rahasia ZZZ');
    });

    it('menetralkan sel berawalan rumus', function (string $nilai, string $hasil) {
        expect(EksporXlsx::netralkan($nilai))->toBe($hasil);
    })->with([['=SUM(A1)', "'=SUM(A1)"], ['+cmd', "'+cmd"], ['-1+1', "'-1+1"], ['@x', "'@x"], ['aman', 'aman'], ['2026-01-01', '2026-01-01']]);

    it('mengunduh hanya lewat URL bertanda tangan milik pengguna dan menghapus berkas setelahnya', function () {
        terbitkan();
        $admin = adminReg();
        Livewire::actingAs($admin)->test(ListRegisterSuratKeluars::class)->callAction('ekspor');
        $nama = collect(File::files(storage_path('app/tmp')))->first(fn ($f) => str_ends_with($f->getFilename(), '.xlsx'))->getFilename();

        // tanpa tanda tangan
        $this->actingAs($admin)->get(route('ekspor.unduh', $nama))->assertForbidden();

        $url = URL::temporarySignedRoute('ekspor.unduh', now()->addMinutes(30), ['berkas' => $nama]);

        // pengguna lain tidak boleh
        $this->actingAs(adminReg())->get($url)->assertForbidden();
        // tamu diarahkan masuk
        auth()->logout();
        $this->get($url)->assertRedirect('/masuk');

        $respons = $this->actingAs($admin)->get($url)->assertOk()->assertDownload($nama);
        // berkas dihapus saat respons dikirim
        ob_start();
        $respons->baseResponse->sendContent();
        ob_end_clean();
        expect(file_exists(storage_path("app/tmp/{$nama}")))->toBeFalse();
    });

    it('menolak nama berkas tidak wajar dan tautan kedaluwarsa', function () {
        $admin = adminReg();
        $this->actingAs($admin);

        foreach (['..%2F..%2F.env', 'bebas.xlsx', 'register-keluar-x-y.xlsx'] as $nama) {
            $url = URL::temporarySignedRoute('ekspor.unduh', now()->addMinutes(30), ['berkas' => $nama]);
            $this->get($url)->assertNotFound();
        }

        $kedaluwarsa = URL::temporarySignedRoute('ekspor.unduh', now()->subMinute(), ['berkas' => "register-keluar-{$admin->id}-".Str::uuid().'.xlsx']);
        $this->get($kedaluwarsa)->assertForbidden();
    });

    it('menolak ekspor bagi pengguna tanpa izin arsip', function () {
        $pegawai = User::factory()->create()->assignRole('pegawai');

        Livewire::actingAs($pegawai)->test(ListRegisterSuratKeluars::class)->assertForbidden();
    });
});

describe('pembersihan berkas sementara', function () {
    it('menghapus berkas lebih dari 24 jam dan mempertahankan yang baru serta .gitkeep', function () {
        $lama = storage_path('app/tmp/lama.xlsx');
        $baru = storage_path('app/tmp/baru.xlsx');
        file_put_contents($lama, 'x');
        file_put_contents($baru, 'x');
        touch($lama, now()->subHours(25)->getTimestamp());

        $this->artisan('surat:bersihkan-tmp')->assertSuccessful();

        expect(file_exists($lama))->toBeFalse()->and(file_exists($baru))->toBeTrue()->and(file_exists(storage_path('app/tmp/.gitkeep')))->toBeTrue();
    });

    it('terdaftar di penjadwal tiap jam', function () {
        $perintah = collect(app(Schedule::class)->events())->pluck('command')->implode(' ');

        expect($perintah)->toContain('surat:bersihkan-tmp');
    });
});
