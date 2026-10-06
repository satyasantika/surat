<?php

use App\Actions\Pengguna\SimpanPengguna;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Aktivitas;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel('admin');
});

function pengelola(string $peran = 'super-admin', array $izin = []): User
{
    $u = User::factory()->create()->assignRole($peran);
    $u->givePermissionTo($izin);
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    return $u;
}

function dataPengguna(array $ubah = []): array
{
    return $ubah + [
        'name' => 'Pegawai Baru', 'email' => 'baru@unsil.ac.id', 'nip_nim' => '1980', 'telepon' => '0811',
        'aktif' => true, 'peran' => ['pegawai'], 'izin_langsung' => [],
    ];
}

it('membatasi akses menu pengguna ke pemegang izin pengguna.kelola', function () {
    $this->actingAs(pengelola('super-admin'))->get('/admin/pengguna')->assertOk();
    $this->actingAs(pengelola('operator-layanan', ['pengguna.kelola']))->get('/admin/pengguna')->assertOk();
    $this->actingAs(pengelola('admin-persuratan'))->get('/admin/pengguna')->assertForbidden();
    $this->actingAs(pengelola('operator-layanan'))->get('/admin/pengguna')->assertForbidden();
});

it('membuat akun dan mengirim surel atur kata sandi', function () {
    Notification::fake();

    $user = app(SimpanPengguna::class)->jalankan(null, dataPengguna(), pengelola());

    expect($user->hasRole('pegawai'))->toBeTrue()
        ->and($user->nip_nim)->toBe('1980')
        ->and($user->aktif)->toBeTrue();
    Notification::assertSentTo($user, ResetPassword::class);
});

it('membuat akun lewat formulir panel', function () {
    Notification::fake();
    $this->actingAs(pengelola());

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Via Form', 'email' => 'form@unsil.ac.id', 'peran' => ['kasubag'], 'aktif' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::firstWhere('email', 'form@unsil.ac.id')->hasRole('kasubag'))->toBeTrue();
});

it('menolak surel di luar domain unsil', function () {
    expect(fn () => app(SimpanPengguna::class)->jalankan(null, dataPengguna(['email' => 'x@gmail.com']), pengelola()))
        ->toThrow(ValidationException::class);
});

it('memberi operator hanya izin yang dicentang', function () {
    Notification::fake();

    $op = app(SimpanPengguna::class)->jalankan(null, dataPengguna([
        'peran' => ['operator-layanan'], 'izin_langsung' => ['kabar.kelola', 'galeri.kelola'],
    ]), pengelola());

    expect($op->getAllPermissions()->pluck('name')->sort()->values()->all())->toBe(['galeri.kelola', 'kabar.kelola'])
        ->and($op->can('kabar.kelola'))->toBeTrue()
        ->and($op->can('ruangan.kelola'))->toBeFalse()
        ->and($op->can('masuk.registrasi'))->toBeFalse();
});

it('mencabut izin langsung saat izin dihapus atau peran bukan operator', function () {
    Notification::fake();
    $aksi = app(SimpanPengguna::class);
    $pelaku = pengelola();
    $op = $aksi->jalankan(null, dataPengguna(['peran' => ['operator-layanan'], 'izin_langsung' => ['kabar.kelola', 'galeri.kelola']]), $pelaku);

    $aksi->jalankan($op, dataPengguna(['peran' => ['operator-layanan'], 'izin_langsung' => ['galeri.kelola']]), $pelaku);
    expect($op->fresh()->can('kabar.kelola'))->toBeFalse()->and($op->fresh()->can('galeri.kelola'))->toBeTrue();

    $aksi->jalankan($op, dataPengguna(['peran' => ['pegawai'], 'izin_langsung' => ['galeri.kelola']]), $pelaku);
    expect($op->fresh()->getDirectPermissions())->toBeEmpty();
});

it('hanya super-admin yang memberi peran super-admin atau admin-persuratan', function (string $peran) {
    Notification::fake();
    $bukanSuper = pengelola('operator-layanan', ['pengguna.kelola']);

    expect(fn () => app(SimpanPengguna::class)->jalankan(null, dataPengguna(['peran' => [$peran]]), $bukanSuper))
        ->toThrow(ValidationException::class);

    $user = app(SimpanPengguna::class)->jalankan(null, dataPengguna(['peran' => [$peran]]), pengelola());
    expect($user->hasRole($peran))->toBeTrue();
})->with(['super-admin', 'admin-persuratan']);

it('tidak mengizinkan non-super-admin mencabut peran terbatas', function () {
    Notification::fake();
    $admin = app(SimpanPengguna::class)->jalankan(null, dataPengguna(['peran' => ['admin-persuratan']]), pengelola());
    $bukanSuper = pengelola('operator-layanan', ['pengguna.kelola']);

    expect(fn () => app(SimpanPengguna::class)->jalankan($admin, dataPengguna(['peran' => ['pegawai']]), $bukanSuper))
        ->toThrow(ValidationException::class);
});

it('tidak mengizinkan memberi izin yang tidak dimiliki pelaku', function () {
    Notification::fake();
    $pelaku = pengelola('operator-layanan', ['pengguna.kelola', 'kabar.kelola']);

    expect(fn () => app(SimpanPengguna::class)->jalankan(null, dataPengguna([
        'peran' => ['operator-layanan'], 'izin_langsung' => ['kabar.kelola', 'pengaturan.kelola'],
    ]), $pelaku))->toThrow(ValidationException::class);

    $ok = app(SimpanPengguna::class)->jalankan(null, dataPengguna([
        'peran' => ['operator-layanan'], 'izin_langsung' => ['kabar.kelola'],
    ]), $pelaku);
    expect($ok->can('kabar.kelola'))->toBeTrue();
});

it('menolak peran dan izin yang tidak dikenal', function () {
    expect(fn () => app(SimpanPengguna::class)->jalankan(null, dataPengguna(['peran' => ['dewa']]), pengelola()))
        ->toThrow(ValidationException::class);
    expect(fn () => app(SimpanPengguna::class)->jalankan(null, dataPengguna(['peran' => ['operator-layanan'], 'izin_langsung' => ['hack.semua']]), pengelola()))
        ->toThrow(ValidationException::class);
});

it('melarang menonaktifkan akun sendiri', function () {
    $pelaku = pengelola();

    expect(fn () => app(SimpanPengguna::class)->jalankan($pelaku, dataPengguna(['email' => $pelaku->email, 'peran' => ['super-admin'], 'aktif' => false]), $pelaku))
        ->toThrow(ValidationException::class);
});

it('mencatat perubahan peran dan izin di log aktivitas', function () {
    Notification::fake();
    $pelaku = pengelola();
    $user = app(SimpanPengguna::class)->jalankan(null, dataPengguna(), $pelaku);

    $this->actingAs($pelaku);
    app(SimpanPengguna::class)->jalankan($user, dataPengguna(['peran' => ['kasubag']]), $pelaku);

    $log = Aktivitas::where('event', 'hak-akses')->orderByDesc('id')->first();
    expect($log->properties['peran_baru'])->toBe(['kasubag'])->and($log->properties['peran_lama'])->toBe(['pegawai']);
});

it('melarang non-super-admin membuka akun admin-persuratan dan tidak menyediakan hapus', function () {
    Notification::fake();
    $admin = app(SimpanPengguna::class)->jalankan(null, dataPengguna(['peran' => ['admin-persuratan']]), pengelola());
    $biasa = app(SimpanPengguna::class)->jalankan(null, dataPengguna(['email' => 'b@unsil.ac.id', 'nip_nim' => '2', 'peran' => ['pegawai']]), pengelola());
    $pengelola = pengelola('operator-layanan', ['pengguna.kelola']);

    expect($pengelola->can('update', $admin))->toBeFalse()
        ->and($pengelola->can('update', $biasa))->toBeTrue()
        ->and($pengelola->can('delete', $biasa))->toBeFalse();
});

it('mengubah akun lewat formulir dan memuat peran serta izin', function () {
    Notification::fake();
    $pelaku = pengelola();
    $op = app(SimpanPengguna::class)->jalankan(null, dataPengguna(['peran' => ['operator-layanan'], 'izin_langsung' => ['kabar.kelola']]), $pelaku);
    $this->actingAs($pelaku);

    Livewire::test(EditUser::class, ['record' => $op->getKey()])
        ->assertFormSet(['peran' => ['operator-layanan'], 'izin_langsung' => ['kabar.kelola']])
        ->fillForm(['telepon' => '0822', 'izin_langsung' => ['galeri.kelola']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($op->fresh()->telepon)->toBe('0822')->and($op->fresh()->can('galeri.kelola'))->toBeTrue();
});

it('menampilkan daftar pengguna dengan filter peran', function () {
    Notification::fake();
    $pelaku = pengelola();
    $a = app(SimpanPengguna::class)->jalankan(null, dataPengguna(), $pelaku);
    $b = app(SimpanPengguna::class)->jalankan(null, dataPengguna(['email' => 'k@unsil.ac.id', 'nip_nim' => '9', 'peran' => ['kasubag']]), $pelaku);
    $this->actingAs($pelaku);

    Livewire::test(ListUsers::class)
        ->filterTable('peran', 'kasubag')
        ->assertCanSeeTableRecords([$b])
        ->assertCanNotSeeTableRecords([$a]);
});

it('menyediakan aksi masuk sebagai hanya untuk super-admin', function () {
    Notification::fake();
    $super = pengelola();
    $target = app(SimpanPengguna::class)->jalankan(null, dataPengguna(), $super);

    $this->actingAs($super);
    Livewire::test(ListUsers::class)->assertTableActionVisible('masukSebagai', $target);

    $this->actingAs(pengelola('operator-layanan', ['pengguna.kelola']));
    Livewire::test(ListUsers::class)->assertTableActionHidden('masukSebagai', $target);
});
