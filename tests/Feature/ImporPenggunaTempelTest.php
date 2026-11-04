<?php

use App\Actions\Pengguna\ImporPenggunaTempel;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel('admin');
    Notification::fake();
});

function pengelolaTempel(string $peran = 'super-admin'): User
{
    $u = User::factory()->create()->assignRole($peran);
    $u->givePermissionTo('pengguna.kelola');
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    return $u;
}

function teksExcel(): string
{
    return "Nama\tSurel\tPeran\tNIP/NIM\tTelepon\n"
        ."Budi Santoso\tBudi.Santoso@unsil.ac.id\tpegawai\t198001012010011001\t081234567890\n"
        ."Siti Aminah\tsiti@student.unsil.ac.id\tPengurus Ormawa|mahasiswa\t221010001\n"
        ."Rina\trina@unsil.ac.id";
}

it('membuat akun dari tempelan Excel beserta peran, NIP/NIM, dan tautan atur sandi', function () {
    $hasil = app(ImporPenggunaTempel::class)->handle(teksExcel(), pengelolaTempel());

    expect($hasil)->toBe(['dibuat' => 3, 'dilewati' => [], 'galat' => []]);

    $budi = User::where('email', 'budi.santoso@unsil.ac.id')->firstOrFail();
    $siti = User::where('email', 'siti@student.unsil.ac.id')->firstOrFail();

    expect($budi->hasRole('pegawai'))->toBeTrue()
        ->and($budi->nip_nim)->toBe('198001012010011001')
        ->and($budi->telepon)->toBe('081234567890')
        ->and($budi->aktif)->toBeTrue()
        ->and($siti->getRoleNames()->sort()->values()->all())->toBe(['mahasiswa', 'pengurus-ormawa'])
        ->and(User::where('email', 'rina@unsil.ac.id')->firstOrFail()->roles)->toBeEmpty();

    Notification::assertSentTo([$budi, $siti], ResetPassword::class);
    expect(Activity::where('event', 'impor-tempel')->count())->toBe(1);
});

it('mengenali pemisah titik koma dan koma, serta tanpa baris judul', function () {
    $teks = "Andi;andi@unsil.ac.id;pegawai\nDedi,dedi@unsil.ac.id,kaprodi";

    expect(app(ImporPenggunaTempel::class)->handle($teks, pengelolaTempel())['dibuat'])->toBe(2)
        ->and(User::where('email', 'dedi@unsil.ac.id')->first()?->hasRole('kaprodi'))->toBeTrue();
});

it('menerima surel berdomain staff.unsil.ac.id', function () {
    expect(app(ImporPenggunaTempel::class)->handle("Tata Usaha\ttu@staff.unsil.ac.id\tpegawai", pengelolaTempel())['dibuat'])->toBe(1)
        ->and(User::where('email', 'tu@staff.unsil.ac.id')->first()?->hasRole('pegawai'))->toBeTrue();
});

it('idempoten: surel terdaftar dilewati tanpa diubah', function () {
    $lama = User::factory()->create(['email' => 'budi.santoso@unsil.ac.id', 'name' => 'Nama Lama']);
    $pelaku = pengelolaTempel();

    $hasil = app(ImporPenggunaTempel::class)->handle(teksExcel(), $pelaku);

    expect($hasil['dibuat'])->toBe(2)
        ->and($hasil['dilewati'])->toBe(['budi.santoso@unsil.ac.id'])
        ->and($lama->refresh()->name)->toBe('Nama Lama')
        ->and($lama->roles)->toBeEmpty();

    expect(app(ImporPenggunaTempel::class)->handle(teksExcel(), $pelaku)['dibuat'])->toBe(0)
        ->and(User::count())->toBe(4);
});

it('membatalkan seluruh impor bila satu baris salah, dengan pesan per baris', function () {
    User::factory()->create(['nip_nim' => '111']);
    $teks = "Ani\tani@unsil.ac.id\tpegawai\n"
        ."Gmail\tgmail@gmail.com\n"
        ."Peran\tperan@unsil.ac.id\tpenyihir\n"
        ."Ganda\tANI@unsil.ac.id\n"
        ."NIM\tnim@unsil.ac.id\t\t111\n"
        .'Kosong';

    $hasil = app(ImporPenggunaTempel::class)->handle($teks, pengelolaTempel());

    expect($hasil['dibuat'])->toBe(0)
        ->and(User::where('email', 'ani@unsil.ac.id')->exists())->toBeFalse()
        ->and(implode("\n", $hasil['galat']))
        ->toContain('Baris 2: Surel harus memakai domain')
        ->toContain("Baris 3: peran 'penyihir' tidak dikenal")
        ->toContain('Baris 4: surel ani@unsil.ac.id ganda dalam data tempel (baris 1)')
        ->toContain('Baris 5: NIP/NIM 111 sudah dipakai akun lain')
        ->toContain('Baris 6: minimal berisi nama dan surel');

    Notification::assertNothingSent();
});

it('hanya super-admin yang boleh memberi peran terbatas lewat impor', function () {
    $hasil = app(ImporPenggunaTempel::class)->handle("Ali\tali@unsil.ac.id\tadmin-persuratan", pengelolaTempel('admin-persuratan'));

    expect($hasil['galat'])->toBe(['Baris 1: hanya super-admin yang dapat memberi peran admin-persuratan.'])
        ->and(User::where('email', 'ali@unsil.ac.id')->exists())->toBeFalse();

    expect(app(ImporPenggunaTempel::class)->handle("Ali\tali@unsil.ac.id\tadmin-persuratan", pengelolaTempel())['dibuat'])->toBe(1);
});

it('menolak pelaku tanpa izin pengguna.kelola', function () {
    app(ImporPenggunaTempel::class)->handle("Ali\tali@unsil.ac.id", User::factory()->create()->assignRole('pegawai'));
})->throws(AuthorizationException::class);

it('tidak mengirim surel atur sandi bila sakelar dimatikan', function () {
    app(ImporPenggunaTempel::class)->handle("Ali\tali@unsil.ac.id", pengelolaTempel(), kirimTautan: false);

    expect(User::where('email', 'ali@unsil.ac.id')->exists())->toBeTrue();
    Notification::assertNothingSent();
});

it('membatasi jumlah baris per impor', function () {
    $teks = collect(range(1, ImporPenggunaTempel::MAKS_BARIS + 1))->map(fn (int $i): string => "P{$i}\tp{$i}@unsil.ac.id")->implode("\n");

    expect(app(ImporPenggunaTempel::class)->periksa($teks, pengelolaTempel())['galat'])->toBe(['Maksimal 500 baris per impor.']);
});

it('aksi panel menampilkan pratinjau lalu membuat akun', function () {
    $pelaku = pengelolaTempel();
    $this->actingAs($pelaku);

    expect(ListUsers::ringkasPratinjau(teksExcel()))
        ->toContain('3 akun baru akan dibuat, 0 dilewati karena sudah terdaftar.')
        ->toContain('Budi Santoso <budi.santoso@unsil.ac.id> — pegawai');

    Livewire::actingAs($pelaku)->test(ListUsers::class)
        ->callAction('imporTempel', ['teks' => teksExcel(), 'kirim_tautan' => true])
        ->assertHasNoActionErrors()
        ->assertNotified('3 pengguna dibuat, 0 dilewati (sudah terdaftar)');

    expect(User::where('email', 'siti@student.unsil.ac.id')->exists())->toBeTrue();
});
