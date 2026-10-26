<?php

use App\Actions\Pengguna\SimpanPengguna;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Aktivitas;
use App\Models\IdentitasGoogle;
use App\Models\User;
use App\Services\Auth\PenautanGoogle;
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
        'peran' => ['operator-layanan'], 'izin_langsung' => ['ruangan.kelola-jadwal', 'laporan.lihat'],
    ]), pengelola());

    expect($op->getAllPermissions()->pluck('name')->sort()->values()->all())->toBe(['laporan.lihat', 'ruangan.kelola-jadwal'])
        ->and($op->can('ruangan.kelola-jadwal'))->toBeTrue()
        ->and($op->can('ruangan.kelola'))->toBeFalse()
        ->and($op->can('masuk.registrasi'))->toBeFalse();
});

it('mencabut izin langsung saat izin dihapus atau peran bukan operator', function () {
    Notification::fake();
    $aksi = app(SimpanPengguna::class);
    $pelaku = pengelola();
    $op = $aksi->jalankan(null, dataPengguna(['peran' => ['operator-layanan'], 'izin_langsung' => ['ruangan.kelola-jadwal', 'laporan.lihat']]), $pelaku);

    $aksi->jalankan($op, dataPengguna(['peran' => ['operator-layanan'], 'izin_langsung' => ['laporan.lihat']]), $pelaku);
    expect($op->fresh()->can('ruangan.kelola-jadwal'))->toBeFalse()->and($op->fresh()->can('laporan.lihat'))->toBeTrue();

    $aksi->jalankan($op, dataPengguna(['peran' => ['pegawai'], 'izin_langsung' => ['laporan.lihat']]), $pelaku);
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
    $pelaku = pengelola('operator-layanan', ['pengguna.kelola', 'ruangan.kelola-jadwal']);

    expect(fn () => app(SimpanPengguna::class)->jalankan(null, dataPengguna([
        'peran' => ['operator-layanan'], 'izin_langsung' => ['ruangan.kelola-jadwal', 'pengaturan.kelola'],
    ]), $pelaku))->toThrow(ValidationException::class);

    $ok = app(SimpanPengguna::class)->jalankan(null, dataPengguna([
        'peran' => ['operator-layanan'], 'izin_langsung' => ['ruangan.kelola-jadwal'],
    ]), $pelaku);
    expect($ok->can('ruangan.kelola-jadwal'))->toBeTrue();
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
    $op = app(SimpanPengguna::class)->jalankan(null, dataPengguna(['peran' => ['operator-layanan'], 'izin_langsung' => ['ruangan.kelola-jadwal']]), $pelaku);
    $this->actingAs($pelaku);

    Livewire::test(EditUser::class, ['record' => $op->getKey()])
        ->assertFormSet(['peran' => ['operator-layanan'], 'izin_langsung' => ['ruangan.kelola-jadwal']])
        ->fillForm(['telepon' => '0822', 'izin_langsung' => ['laporan.lihat']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($op->fresh()->telepon)->toBe('0822')->and($op->fresh()->can('laporan.lihat'))->toBeTrue();
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

it('admin menonaktifkan akun pengirim-eksternal bersurel gmail lewat aksi dan formulir panel', function () {
    $pelaku = pengelola();
    $luar = User::factory()->create(['email' => 'luar@gmail.com'])->assignRole('pengirim-eksternal');

    app(SimpanPengguna::class)->jalankan($luar, dataPengguna(['name' => $luar->name, 'email' => 'luar@gmail.com', 'nip_nim' => null, 'aktif' => false, 'peran' => ['pengirim-eksternal']]), $pelaku);
    expect($luar->fresh()->aktif)->toBeFalse();

    $luar2 = User::factory()->create(['email' => 'luar2@gmail.com'])->assignRole('pengirim-eksternal');
    $this->actingAs($pelaku);
    Livewire::test(EditUser::class, ['record' => $luar2->getKey()])
        ->fillForm(['aktif' => false])
        ->call('save')
        ->assertHasNoFormErrors();
    expect($luar2->fresh()->aktif)->toBeFalse()->and($luar2->fresh()->email)->toBe('luar2@gmail.com');
});

it('tetap menolak mengganti surel akun menjadi domain di luar unsil, lewat aksi maupun formulir', function () {
    Notification::fake();
    $pelaku = pengelola();
    $user = app(SimpanPengguna::class)->jalankan(null, dataPengguna(), $pelaku);

    expect(fn () => app(SimpanPengguna::class)->jalankan($user, dataPengguna(['email' => 'pindah@gmail.com']), $pelaku))
        ->toThrow(ValidationException::class);

    $luar = User::factory()->create(['email' => 'luar@gmail.com'])->assignRole('pengirim-eksternal');
    $this->actingAs($pelaku);
    Livewire::test(EditUser::class, ['record' => $luar->getKey()])
        ->fillForm(['email' => 'lain@gmail.com'])
        ->call('save')
        ->assertHasFormErrors(['email']);
    expect($user->fresh()->email)->toBe('baru@unsil.ac.id')->and($luar->fresh()->email)->toBe('luar@gmail.com');
});

it('mengganti surel akun memutus tautan Google lamanya', function () {
    Notification::fake();
    $pelaku = pengelola();
    $user = app(SimpanPengguna::class)->jalankan(null, dataPengguna(['email' => 'lama@unsil.ac.id']), $pelaku);
    IdentitasGoogle::create(['user_id' => $user->id, 'google_id' => 'g-lama', 'surel' => 'lama@unsil.ac.id']);

    // Simpan tanpa mengubah surel: tautan tetap.
    app(SimpanPengguna::class)->jalankan($user, dataPengguna(['email' => 'lama@unsil.ac.id', 'telepon' => '0899']), $pelaku);
    expect(IdentitasGoogle::where('user_id', $user->id)->exists())->toBeTrue();

    app(SimpanPengguna::class)->jalankan($user, dataPengguna(['email' => 'baru2@unsil.ac.id']), $pelaku);
    expect(IdentitasGoogle::where('user_id', $user->id)->exists())->toBeFalse();

    // Google lama tidak lagi masuk sebagai akun ini; ia diperlakukan sebagai akun baru menurut aturan biasa.
    $hasil = app(PenautanGoogle::class)->masuk('g-lama', 'lama@unsil.ac.id', true, 'Lama');
    expect($hasil->is($user))->toBeFalse()->and($hasil->hasRole('pengirim-eksternal'))->toBeTrue();
});
