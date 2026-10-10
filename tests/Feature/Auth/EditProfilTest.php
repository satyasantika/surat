<?php

use App\Filament\Auth\EditProfil;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(PeranDanIzinSeeder::class));

function penggunaOperatorLayanan(array $atribut = []): User
{
    return User::factory()->create(['password' => Hash::make('password'), ...$atribut])
        ->assignRole('operator-layanan');
}

it('pengguna dengan wajib_ganti_sandi diarahkan ke profil', function () {
    $user = penggunaOperatorLayanan(['wajib_ganti_sandi' => true]);

    $this->actingAs($user)->get('/admin')->assertRedirect(Filament::getProfileUrl());
});

it('mengganti sandi di profil mencabut kewajiban dan mengeluarkan perangkat lain', function () {
    $user = penggunaOperatorLayanan(['wajib_ganti_sandi' => true]);
    $lama = $user->password;
    Event::fake([OtherDeviceLogout::class]);

    $this->actingAs($user);

    Livewire::test(EditProfil::class)
        ->fillForm([
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'sandi-baru-123',
            'passwordConfirmation' => 'sandi-baru-123',
            'currentPassword' => 'password',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();
    expect($user->wajib_ganti_sandi)->toBeFalse()
        ->and($user->password)->not->toBe($lama)
        ->and(Hash::check('sandi-baru-123', $user->password))->toBeTrue();
    Event::assertDispatched(OtherDeviceLogout::class);

    $this->get('/admin')->assertOk();
});
