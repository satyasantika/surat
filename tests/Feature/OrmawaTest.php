<?php

use App\Filament\Resources\Ormawas\OrmawaResource;
use App\Filament\Resources\Ormawas\Pages\CreateOrmawa;
use App\Filament\Resources\Ormawas\Pages\EditOrmawa;
use App\Filament\Resources\Ormawas\Pages\ListOrmawas;
use App\Filament\Resources\Ormawas\SkRelationManager;
use App\Models\Ormawa;
use App\Models\SkKepengurusan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel('admin');
});

function aktorOrm(string $peran, array $izin = []): User
{
    $u = User::factory()->create()->assignRole($peran);
    $u->givePermissionTo($izin);
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    return $u;
}

function buatOrmawa(array $ubah = []): Ormawa
{
    return Ormawa::create($ubah + ['nama' => 'Himpunan Mahasiswa Matematika', 'tingkat' => 'prodi']);
}

function buatSk(Ormawa $o, string $mulai, string $selesai, string $nomor = 'SK/1', ?string $tanggalSk = null): SkKepengurusan
{
    return $o->sk()->create(['nomor_sk' => $nomor, 'tanggal_sk' => $tanggalSk ?? $mulai, 'periode_mulai' => $mulai, 'periode_selesai' => $selesai]);
}

it('membuat slug unik otomatis dari nama', function () {
    $a = buatOrmawa(['nama' => 'BEM FKIP']);
    $b = buatOrmawa(['nama' => 'BEM  FKIP!!', 'tingkat' => 'fakultas']);
    $c = buatOrmawa(['nama' => 'Himpunan A', 'slug' => 'kustom-slug']);

    expect($a->slug)->toBe('bem-fkip')->and($b->slug)->toBe('bem-fkip-2')->and($c->slug)->toBe('kustom-slug');
});

it('menolak slug dan nama ganda di basis data', function () {
    buatOrmawa(['nama' => 'Satu', 'slug' => 'sama']);

    expect(fn () => buatOrmawa(['nama' => 'Dua', 'slug' => 'sama']))->toThrow(QueryException::class)
        ->and(fn () => buatOrmawa(['nama' => 'Satu']))->toThrow(QueryException::class);
});

it('menolak tingkat tak dikenal dan pembina bukan pembina-ormawa', function () {
    expect(fn () => buatOrmawa(['tingkat' => 'kerajaan']))->toThrow(ValidationException::class)
        ->and(fn () => buatOrmawa(['nama' => 'X', 'pembina_user_id' => User::factory()->create()->assignRole('pegawai')->id]))->toThrow(ValidationException::class);

    $pembina = User::factory()->create()->assignRole('pembina-ormawa');
    expect(buatOrmawa(['nama' => 'Y', 'pembina_user_id' => $pembina->id])->pembina->is($pembina))->toBeTrue();
});

it('menghitung SK berlaku dari periode', function (string $hari, ?string $diharapkan) {
    $o = buatOrmawa();
    buatSk($o, '2024-01-01', '2024-12-31', 'SK/2024');
    buatSk($o, '2025-01-01', '2025-12-31', 'SK/2025');

    $sk = $o->skBerlaku(CarbonImmutable::parse($hari));

    expect($sk?->nomor_sk)->toBe($diharapkan);
})->with([
    'tengah 2024' => ['2024-06-15', 'SK/2024'],
    'hari pertama 2025' => ['2025-01-01', 'SK/2025'],
    'hari terakhir 2024' => ['2024-12-31', 'SK/2024'],
    'hari terakhir 2025' => ['2025-12-31', 'SK/2025'],
    'sebelum semua' => ['2023-12-31', null],
    'sesudah semua' => ['2026-01-01', null],
]);

it('memilih SK terbaru bila beberapa periode bertumpuk dan menghitung berlaku() per SK', function () {
    $o = buatOrmawa();
    buatSk($o, '2025-01-01', '2025-12-31', 'SK/lama', '2025-01-05');
    buatSk($o, '2025-06-01', '2026-05-31', 'SK/baru', '2025-06-10');

    expect($o->skBerlaku(CarbonImmutable::parse('2025-07-01'))->nomor_sk)->toBe('SK/baru')
        ->and($o->skBerlaku(CarbonImmutable::parse('2025-03-01'))->nomor_sk)->toBe('SK/lama');

    $sk = $o->sk()->where('nomor_sk', 'SK/lama')->first();
    expect($sk->berlaku(CarbonImmutable::parse('2025-12-31 23:00')))->toBeTrue()
        ->and($sk->berlaku(CarbonImmutable::parse('2026-01-01')))->toBeFalse();
});

it('menolak SK dengan periode terbalik', function () {
    expect(fn () => buatSk(buatOrmawa(), '2025-12-31', '2025-01-01'))->toThrow(ValidationException::class);
});

it('mengelola ormawa lewat panel dan membatasi akses', function () {
    $admin = aktorOrm('admin-persuratan');
    $pembina = User::factory()->create()->assignRole('pembina-ormawa');

    Livewire::actingAs($admin)->test(CreateOrmawa::class)
        ->fillForm(['nama' => 'UKM Seni', 'tingkat' => 'ukm', 'singkatan' => 'SENI', 'akun_media' => '@ukmseni', 'pembina_user_id' => $pembina->id, 'aktif' => true])
        ->call('create')->assertHasNoFormErrors();

    $o = Ormawa::firstWhere('nama', 'UKM Seni');
    expect($o->slug)->toBe('ukm-seni')->and($o->pembina->is($pembina))->toBeTrue();

    $this->actingAs(aktorOrm('pegawai'))->get('/admin/ormawa')->assertForbidden();
    $this->actingAs(aktorOrm('dekan'))->get('/admin/ormawa')->assertOk();
});

it('menolak akun media tidak wajar lewat formulir', function () {
    Livewire::actingAs(aktorOrm('admin-persuratan'))->test(CreateOrmawa::class)
        ->fillForm(['nama' => 'X', 'tingkat' => 'ukm', 'akun_media' => 'bukan akun!'])
        ->call('create')->assertHasFormErrors(['akun_media']);
});

it('membatasi pembina pada binaannya dan hanya admin yang membuat ormawa', function () {
    $pembina = User::factory()->create()->assignRole('pembina-ormawa');
    $pembina->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    $binaan = buatOrmawa(['nama' => 'Binaan', 'pembina_user_id' => $pembina->id]);
    $lain = buatOrmawa(['nama' => 'Bukan Binaan']);

    Livewire::actingAs($pembina)->test(ListOrmawas::class)->assertCanSeeTableRecords([$binaan])->assertCanNotSeeTableRecords([$lain]);

    expect($pembina->can('view', $binaan))->toBeTrue()->and($pembina->can('view', $lain))->toBeFalse()
        ->and($pembina->can('update', $binaan))->toBeTrue()->and($pembina->can('update', $lain))->toBeFalse()
        ->and($pembina->can('create', Ormawa::class))->toBeFalse()
        ->and(aktorOrm('admin-persuratan')->can('create', Ormawa::class))->toBeTrue()
        ->and(aktorOrm('dekan')->can('update', $binaan))->toBeFalse()->and(aktorOrm('dekan')->can('view', $binaan))->toBeTrue();
});

it('menambah SK lewat relation manager beserta tautan dokumen', function () {
    $o = buatOrmawa();
    $admin = aktorOrm('admin-persuratan');

    Livewire::actingAs($admin)->test(SkRelationManager::class, ['ownerRecord' => $o, 'pageClass' => EditOrmawa::class])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'nomor_sk' => 'SK/77/2026', 'tanggal_sk' => '2026-01-10', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2026-12-31',
            'disahkan_oleh' => 'Dekan', 'url_sk' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view',
        ])->assertHasNoActionErrors();

    $sk = $o->sk()->first();
    expect($sk->nomor_sk)->toBe('SK/77/2026')->and($sk->tautan)->toHaveCount(1)->and($sk->tautan[0]->jenis)->toBe('sk');

    $this->actingAs($admin)->get(route('berkas.buka', $sk->tautan[0]))->assertRedirect('https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view');
    $this->actingAs(aktorOrm('pegawai'))->get(route('berkas.buka', $sk->tautan[0]))->assertForbidden();
});

it('menolak tautan SK di luar daftar putih dan periode terbalik lewat relation manager', function () {
    $o = buatOrmawa();

    Livewire::actingAs(aktorOrm('admin-persuratan'))->test(SkRelationManager::class, ['ownerRecord' => $o, 'pageClass' => EditOrmawa::class])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'nomor_sk' => 'SK/1', 'tanggal_sk' => '2026-01-10', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2026-12-31', 'url_sk' => 'https://evil.example.com/sk.pdf',
        ])->assertHasActionErrors(['url_sk']);

    expect($o->sk()->count())->toBe(0);
});

it('mengunci resource sesuai kebijakan', function () {
    expect(OrmawaResource::canViewAny())->toBeFalse();
    $this->actingAs(aktorOrm('admin-persuratan'));
    expect(OrmawaResource::canViewAny())->toBeTrue();
});
