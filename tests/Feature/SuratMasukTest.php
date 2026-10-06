<?php

use App\Actions\Masuk\RegistrasiSuratMasuk;
use App\Enums\KlasifikasiKeamanan;
use App\Filament\Resources\SuratMasuks\Pages\CreateSuratMasuk;
use App\Filament\Resources\SuratMasuks\Pages\ListSuratMasuks;
use App\Models\SuratMasuk;
use App\Models\TautanBerkas;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->seed([PeranDanIzinSeeder::class, RegisterNomorSeeder::class]);
    Filament::setCurrentPanel('admin');
});

const PINDAIAN = 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view';

function aktor(string $peran, array $izin = []): User
{
    $u = User::factory()->create()->assignRole($peran);
    $u->givePermissionTo($izin);
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    return $u;
}

function dataSurat(array $ubah = []): array
{
    return $ubah + [
        'nomor_surat' => '123/ABC/2026', 'tanggal_surat' => now()->toDateString(), 'asal' => 'Dinas Pendidikan',
        'perihal' => 'Undangan rapat koordinasi', 'ringkasan' => 'Isi ringkas rahasia', 'klasifikasi_keamanan' => 'biasa',
        'derajat_kecepatan' => 'segera', 'pindaian_url' => PINDAIAN,
    ];
}

function daftarkan(array $ubah = [], ?User $oleh = null): SuratMasuk
{
    return app(RegistrasiSuratMasuk::class)->jalankan(dataSurat($ubah), $oleh ?? aktor('admin-persuratan'));
}

it('memberi nomor agenda berurutan dari register agenda-masuk', function () {
    $tahun = now()->year;

    expect(daftarkan()->nomor_agenda)->toBe("0001/AGD/{$tahun}")
        ->and(daftarkan()->nomor_agenda)->toBe("0002/AGD/{$tahun}");
});

it('menyimpan tautan pindaian, pelaku, dan status awal', function () {
    $admin = aktor('admin-persuratan');
    $surat = daftarkan(oleh: $admin);

    expect($surat->status->value)->toBe('diterima')
        ->and($surat->diregistrasi_oleh)->toBe($admin->id)
        ->and($surat->tautan)->toHaveCount(1)
        ->and($surat->tautan[0]->jenis)->toBe('pindaian')
        ->and($surat->tanggal_terima)->not->toBeNull();
});

it('mewajibkan tautan pindaian yang sah dan tidak menghabiskan nomor saat gagal', function (?string $url) {
    $data = dataSurat(['pindaian_url' => $url]);
    if ($url === null) {
        unset($data['pindaian_url']);
    }

    expect(fn () => app(RegistrasiSuratMasuk::class)->jalankan($data, aktor('admin-persuratan')))
        ->toThrow(ValidationException::class);
    expect(SuratMasuk::count())->toBe(0)->and(TautanBerkas::count())->toBe(0);
    expect(daftarkan()->nomor_agenda)->toEndWith('0001/AGD/'.now()->year);
})->with([null, '', 'https://evil.example.com/a.pdf', 'http://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr']);

it('memvalidasi isian wajib dan enum', function (array $ubah) {
    expect(fn () => app(RegistrasiSuratMasuk::class)->jalankan(dataSurat($ubah), aktor('admin-persuratan')))
        ->toThrow(ValidationException::class);
})->with([
    'perihal kosong' => [['perihal' => '']],
    'asal kosong' => [['asal' => '']],
    'keamanan tak dikenal' => [['klasifikasi_keamanan' => 'super']],
    'kecepatan tak dikenal' => [['derajat_kecepatan' => 'kilat']],
    'tanggal surat di masa depan' => [['tanggal_surat' => now()->addDays(10)->toDateString()]],
]);

it('hanya mengizinkan pemegang masuk.registrasi mendaftarkan surat', function (string $peran, array $izin, bool $boleh) {
    $pelaku = aktor($peran, $izin);
    $aksi = fn () => app(RegistrasiSuratMasuk::class)->jalankan(dataSurat(), $pelaku);

    $boleh ? expect($aksi()->exists)->toBeTrue() : expect($aksi)->toThrow(AuthorizationException::class);
})->with([
    ['admin-persuratan', [], true],
    ['super-admin', [], true],
    ['operator-layanan', ['masuk.registrasi'], true],
    ['operator-layanan', [], false],
    ['dekan', [], false],
    ['pegawai', [], false],
    ['pengurus-ormawa', [], false],
]);

it('menerapkan policy klasifikasi keamanan per peran', function (string $peran, array $izin, string $klas, bool $isi, bool $metadata) {
    $surat = daftarkan(['klasifikasi_keamanan' => $klas]);
    $pelaku = aktor($peran, $izin);

    expect($pelaku->can('view', $surat))->toBe($isi, "{$peran}/{$klas} isi")
        ->and($pelaku->can('lihatMetadata', $surat))->toBe($metadata, "{$peran}/{$klas} metadata");
})->with([
    'admin biasa' => ['admin-persuratan', [], 'biasa', true, true],
    'admin terbatas' => ['admin-persuratan', [], 'terbatas', true, true],
    'admin rahasia' => ['admin-persuratan', [], 'rahasia', false, true],
    'admin sangat rahasia' => ['admin-persuratan', [], 'sangat_rahasia', false, true],
    'dekan biasa' => ['dekan', [], 'biasa', true, true],
    'dekan rahasia' => ['dekan', [], 'rahasia', true, true],
    'dekan sangat rahasia' => ['dekan', [], 'sangat_rahasia', true, true],
    'super sangat rahasia' => ['super-admin', [], 'sangat_rahasia', true, true],
    'operator izin biasa' => ['operator-layanan', ['masuk.lihat'], 'biasa', true, true],
    'operator izin rahasia' => ['operator-layanan', ['masuk.lihat'], 'rahasia', false, true],
    'operator tanpa izin' => ['operator-layanan', [], 'biasa', false, false],
    'wakil dekan biasa (bukan penerima)' => ['wakil-dekan', [], 'biasa', false, false],
    'kasubag rahasia' => ['kasubag', [], 'rahasia', false, false],
    'pegawai biasa' => ['pegawai', [], 'biasa', false, false],
    'pengurus ormawa' => ['pengurus-ormawa', [], 'biasa', false, false],
]);

it('menyamarkan perihal surat rahasia bagi yang tidak berhak', function () {
    $rahasia = daftarkan(['klasifikasi_keamanan' => 'rahasia', 'perihal' => 'Perihal sangat sensitif']);
    $biasa = daftarkan();

    expect($rahasia->perihalUntuk(aktor('admin-persuratan')))->toBe('[RAHASIA]')
        ->and($rahasia->perihalUntuk(aktor('dekan')))->toBe('Perihal sangat sensitif')
        ->and($rahasia->perihalUntuk(null))->toBe('[RAHASIA]')
        ->and($biasa->perihalUntuk(aktor('admin-persuratan')))->toBe('Undangan rapat koordinasi');
});

it('menyembunyikan tautan pindaian surat rahasia dari admin', function () {
    $surat = daftarkan(['klasifikasi_keamanan' => 'rahasia']);
    $tautan = $surat->tautan[0];

    $this->actingAs(aktor('admin-persuratan'))->get(route('berkas.buka', $tautan))->assertForbidden();
    $this->actingAs(aktor('dekan'))->get(route('berkas.buka', $tautan))->assertRedirect(PINDAIAN);
});

it('mendaftarkan surat lewat formulir panel', function () {
    $this->actingAs(aktor('admin-persuratan'));

    Livewire::test(CreateSuratMasuk::class)
        ->fillForm([
            'tanggal_terima' => now()->toDateTimeString(), 'nomor_surat' => '77/X/2026', 'tanggal_surat' => now()->toDateString(),
            'asal' => 'Kemendikbud', 'perihal' => 'Edaran', 'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa',
            'pindaian_url' => PINDAIAN,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(SuratMasuk::firstWhere('nomor_surat', '77/X/2026')->nomor_agenda)->toStartWith('0001/AGD/');
});

it('menolak formulir tanpa tautan pindaian', function () {
    $this->actingAs(aktor('admin-persuratan'));

    Livewire::test(CreateSuratMasuk::class)
        ->fillForm(['nomor_surat' => '1', 'tanggal_surat' => now()->toDateString(), 'asal' => 'A', 'perihal' => 'P'])
        ->call('create')
        ->assertHasFormErrors(['pindaian_url']);
});

it('menampilkan daftar dengan perihal rahasia tersamar dan menyaring', function () {
    $biasa = daftarkan(['asal' => 'Dinas A']);
    $rahasia = daftarkan(['klasifikasi_keamanan' => 'rahasia', 'perihal' => 'Perihal sensitif XYZ', 'asal' => 'Instansi B']);
    $this->actingAs(aktor('admin-persuratan'));

    Livewire::test(ListSuratMasuks::class)
        ->assertCanSeeTableRecords([$biasa, $rahasia])
        ->assertSee('[RAHASIA]')
        ->assertDontSee('Perihal sensitif XYZ')
        ->filterTable('klasifikasi_keamanan', 'rahasia')
        ->assertCanSeeTableRecords([$rahasia])->assertCanNotSeeTableRecords([$biasa])
        ->resetTableFilters()
        ->filterTable('asal', ['asal' => 'Dinas A'])
        ->assertCanSeeTableRecords([$biasa])->assertCanNotSeeTableRecords([$rahasia]);
});

it('tidak membocorkan perihal rahasia lewat pencarian', function () {
    $rahasia = daftarkan(['klasifikasi_keamanan' => 'rahasia', 'perihal' => 'Rahasia negara kode ZZZ']);
    $this->actingAs(aktor('admin-persuratan'));

    Livewire::test(ListSuratMasuks::class)->searchTable('ZZZ')->assertCanNotSeeTableRecords([$rahasia]);
});

it('menolak admin membuka form ubah surat rahasia tetapi mengizinkan surat biasa', function () {
    $admin = aktor('admin-persuratan');
    $rahasia = daftarkan(['klasifikasi_keamanan' => 'rahasia']);
    $biasa = daftarkan();

    $this->actingAs($admin)->get("/admin/surat-masuk/{$rahasia->id}/edit")->assertForbidden();
    $this->actingAs($admin)->get("/admin/surat-masuk/{$biasa->id}/edit")->assertOk();
});

it('membatasi menu surat masuk ke yang berhak', function () {
    $this->actingAs(aktor('admin-persuratan'))->get('/admin/surat-masuk')->assertOk();
    $this->actingAs(aktor('dekan'))->get('/admin/surat-masuk')->assertOk();
    $this->actingAs(aktor('kasubag'))->get('/admin/surat-masuk')->assertForbidden();
});

it('tidak mengubah nomor agenda saat surat diperbarui', function () {
    $surat = daftarkan();
    $awal = $surat->nomor_agenda;

    $surat->update(['perihal' => 'Baru']);

    expect($surat->fresh()->nomor_agenda)->toBe($awal)->and(KlasifikasiKeamanan::from('rahasia')->tertutup())->toBeTrue();
});
