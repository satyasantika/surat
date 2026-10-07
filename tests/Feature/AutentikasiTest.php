<?php

use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(PeranDanIzinSeeder::class);
    RateLimiter::clear('x');
});

function akun(string $peran, array $atribut = []): User
{
    return User::factory()->create(['password' => 'Rahasia12345'] + $atribut)->assignRole($peran);
}

it('tidak membuka registrasi di panel admin', function () {
    $this->get('/admin/register')->assertNotFound();
});

it('tidak memiliki kredensial bawaan', function () {
    expect(User::count())->toBe(0);

    $this->post('/masuk', ['email' => 'admin', 'password' => 'admin123'])->assertSessionHasErrors();
    $this->post('/masuk', ['email' => 'admin@unsil.ac.id', 'password' => 'admin123'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('memasukkan pengurus yang aktif dan terverifikasi', function () {
    $user = akun('pengurus-ormawa');

    $this->post('/masuk', ['email' => $user->email, 'password' => 'Rahasia12345'])->assertRedirect('/ormawa');
    $this->assertAuthenticatedAs($user);
});

it('menolak kata sandi salah, akun nonaktif, dan surel belum terverifikasi', function (array $atribut, string $sandi) {
    $user = akun('pegawai', $atribut);

    $this->post('/masuk', ['email' => $user->email, 'password' => $sandi])->assertSessionHasErrors('email');
    $this->assertGuest();
})->with([
    'sandi salah' => [[], 'salah-total'],
    'nonaktif' => [['aktif' => false], 'Rahasia12345'],
    'belum verifikasi' => [['email_verified_at' => null], 'Rahasia12345'],
]);

it('mengarahkan akun panel ke halaman admin dan menolaknya di /masuk', function () {
    $dekan = akun('dekan');

    $this->post('/masuk', ['email' => $dekan->email, 'password' => 'Rahasia12345'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('membatasi 5 percobaan per menit lalu 429', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/masuk', ['email' => 'x@unsil.ac.id', 'password' => 'salah'])->assertSessionHasErrors();
    }

    $this->post('/masuk', ['email' => 'x@unsil.ac.id', 'password' => 'salah'])->assertStatus(429);
    // kunci per surel + IP: surel lain tidak ikut terkunci
    $this->post('/masuk', ['email' => 'lain@unsil.ac.id', 'password' => 'salah'])->assertSessionHasErrors();
});

it('mengarahkan pejabat tanpa MFA ke penyiapan MFA', function (string $peran) {
    Filament::setCurrentPanel('admin');

    $this->actingAs(akun($peran))->get('/admin')
        ->assertRedirect(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
})->with(['super-admin', 'admin-persuratan', 'dekan', 'wakil-dekan']);

it('tidak memaksa MFA bagi peran lain', function () {
    Filament::setCurrentPanel('admin');

    $this->actingAs(akun('kasubag'))->get('/admin')->assertOk();
});

it('lolos bagi pejabat yang sudah mengaktifkan MFA', function () {
    Filament::setCurrentPanel('admin');
    $dekan = akun('dekan');
    $dekan->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    $this->actingAs($dekan)->get('/admin')->assertOk();
});

it('mendaftarkan pengurus hanya dengan surel mahasiswa dan mengirim verifikasi', function () {
    Notification::fake();
    $data = ['name' => 'Budi', 'nip_nim' => '2210001', 'password' => 'Rahasia12345', 'password_confirmation' => 'Rahasia12345'];

    $this->post('/daftar', $data + ['email' => 'budi@gmail.com'])->assertSessionHasErrors('email');
    $this->post('/daftar', $data + ['email' => 'budi@unsil.ac.id'])->assertSessionHasErrors('email');
    expect(User::count())->toBe(0);

    $this->post('/daftar', $data + ['email' => 'budi@student.unsil.ac.id'])->assertRedirect('/masuk');

    $user = User::firstWhere('email', 'budi@student.unsil.ac.id');
    expect($user->hasRole('pengurus-ormawa'))->toBeTrue()
        ->and($user->aktif)->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);

    $this->post('/masuk', ['email' => $user->email, 'password' => 'Rahasia12345'])->assertSessionHasErrors('email');

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
    $this->get($url)->assertRedirect('/masuk');
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('menolak tautan verifikasi tanpa tanda tangan yang sah', function () {
    $user = akun('pengurus-ormawa', ['email_verified_at' => null]);

    $this->get("/verifikasi-surel/{$user->id}/".sha1($user->email))->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('mengatur ulang kata sandi lewat tautan surel', function () {
    Notification::fake();
    $user = akun('pegawai');

    $this->post('/lupa-sandi', ['email' => $user->email])->assertSessionHas('status');
    $this->post('/lupa-sandi', ['email' => 'tidak-ada@unsil.ac.id'])->assertSessionHas('status');
    Notification::assertSentTo($user, ResetPassword::class);

    $token = Password::createToken($user);
    $this->post('/atur-ulang-sandi', [
        'token' => $token, 'email' => $user->email,
        'password' => 'SandiBaru12345', 'password_confirmation' => 'SandiBaru12345',
    ])->assertRedirect('/masuk');

    expect(Hash::check('SandiBaru12345', $user->fresh()->password))->toBeTrue();
});

it('mengganti kata sandi hanya dengan kata sandi lama yang benar', function () {
    $user = akun('pegawai');
    $baru = ['password' => 'SandiBaru12345', 'password_confirmation' => 'SandiBaru12345'];

    $this->actingAs($user)->put('/profil/sandi', $baru + ['current_password' => 'keliru'])
        ->assertSessionHasErrors('current_password');

    $this->actingAs($user)->put('/profil/sandi', $baru + ['current_password' => 'Rahasia12345'])
        ->assertSessionHas('status');
    expect(Hash::check('SandiBaru12345', $user->fresh()->password))->toBeTrue();
});

it('mengeluarkan pengguna yang dinonaktifkan di tengah sesi', function () {
    $user = akun('pegawai');
    $this->actingAs($user)->get('/profil')->assertOk();

    $user->update(['aktif' => false]);

    $this->actingAs($user->fresh())->get('/profil')->assertRedirect('/masuk');
    $this->assertGuest();
});

it('menolak akun nonaktif di panel admin', function () {
    $dekan = akun('dekan', ['aktif' => false]);

    expect($dekan->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

it('mengalihkan tamu di /profil ke /masuk', function () {
    $this->get('/profil')->assertRedirect('/masuk');
});
