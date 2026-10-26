<?php

use App\Models\IdentitasGoogle;
use App\Models\User;
use App\Services\Auth\PenautanGoogle;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as UserGoogle;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(PeranDanIzinSeeder::class);
    config(['services.google' => ['client_id' => 'id-uji', 'client_secret' => 'rahasia', 'redirect' => 'http://localhost/masuk/google/callback']]);
});

function fakeGoogle(string $id = 'g-1', string $surel = 'budi@student.unsil.ac.id', bool $terverifikasi = true, string $nama = 'Budi'): void
{
    Socialite::fake('google', (new UserGoogle)->setRaw(['email_verified' => $terverifikasi])->map([
        'id' => $id, 'name' => $nama, 'email' => $surel,
    ]));
}

function bentrokUnik(): UniqueConstraintViolationException
{
    return new UniqueConstraintViolationException('mariadb', 'insert into identitas_google', [], new PDOException('Duplicate entry'));
}

it('mengarahkan ke Google dengan cakupan openid, email, profile', function () {
    $lokasi = $this->get(route('login.google'))->assertRedirect()->headers->get('Location');

    expect($lokasi)->toStartWith('https://accounts.google.com/')
        ->and(urldecode($lokasi))->toContain('openid')->toContain('email')->toContain('profile');
});

it('mengingat bahwa alur dimulai dari panel admin', function () {
    fakeGoogle();

    $this->get(route('login.google', ['dari' => 'admin']))->assertRedirect();

    expect(session('google_dari'))->toBe('admin');
});

it('memasukkan mahasiswa baru dan mengarahkan ke berandanya', function () {
    fakeGoogle();

    $this->get(route('login.google.callback'))->assertRedirect(route('mahasiswa'));

    $this->assertAuthenticated();
    expect(auth()->user()->email)->toBe('budi@student.unsil.ac.id')
        ->and(auth()->user()->hasRole('mahasiswa'))->toBeTrue();
});

it('mengganti ID sesi dan mengingat pengguna saat masuk', function () {
    fakeGoogle();
    $this->get(route('login'));
    $idLama = session()->getId();

    $r = $this->get(route('login.google.callback'));

    expect(session()->getId())->not->toBe($idLama);
    $this->assertAuthenticated();
    expect(collect($r->headers->getCookies())->contains(fn ($c) => str_starts_with($c->getName(), 'remember_web_')))->toBeTrue();
});

it('mengarahkan pengguna berperan panel ke /admin meski mulai dari halaman masuk publik', function () {
    $dekan = User::factory()->create(['email' => 'dekan@unsil.ac.id'])->assignRole('dekan');
    fakeGoogle('g-dekan', 'dekan@unsil.ac.id');

    $this->get(route('login.google.callback'))->assertRedirect(url('admin'));

    $this->assertAuthenticatedAs($dekan);
});

it('mengarahkan mahasiswa yang mulai dari panel ke berandanya dengan pesan', function () {
    fakeGoogle();

    $this->withSession(['google_dari' => 'admin'])
        ->get(route('login.google.callback'))
        ->assertRedirect(route('mahasiswa'))
        ->assertSessionHas('status', 'Akun ini tidak memakai panel admin.');

    $this->assertAuthenticated();
    expect(session('google_dari'))->toBeNull();
});

it('menolak surel Google yang belum terverifikasi', function () {
    fakeGoogle(terverifikasi: false);

    $this->get(route('login.google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('menolak akun nonaktif tanpa sesi', function () {
    User::factory()->create(['email' => 'mati@gmail.com', 'aktif' => false]);
    fakeGoogle('g-mati', 'mati@gmail.com');

    $this->get(route('login.google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('kembali ke halaman masuk dengan pesan ramah bila izin ditolak di Google', function () {
    fakeGoogle();

    $this->get(route('login.google.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('login'))->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('kembali ke halaman masuk bila state OAuth tidak cocok', function () {
    Socialite::fake('google', fn () => throw new InvalidStateException);

    $this->get(route('login.google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('mencoba sekali lagi bila dua login pertama bertabrakan', function () {
    $user = User::factory()->create(['email' => 'budi@student.unsil.ac.id'])->assignRole('mahasiswa');
    fakeGoogle();
    $this->mock(PenautanGoogle::class)->shouldReceive('masuk')->twice()
        ->andReturnUsing(fn () => throw bentrokUnik(), fn () => $user);

    $this->get(route('login.google.callback'))->assertRedirect(route('mahasiswa'));

    $this->assertAuthenticatedAs($user);
});

it('meminta mencoba lagi bila tabrakan berulang, tanpa galat 500', function () {
    fakeGoogle();
    $this->mock(PenautanGoogle::class)->shouldReceive('masuk')->twice()->andThrow(bentrokUnik());

    $this->get(route('login.google.callback'))->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Masuk dengan Google gagal sesaat. Silakan coba lagi.']);

    $this->assertGuest();
});

it('menolak masuk Google untuk akun yang memasang verifikasi dua langkah', function () {
    $dekan = User::factory()->create(['email' => 'dekan@unsil.ac.id'])->assignRole('dekan');
    $dekan->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    IdentitasGoogle::create(['user_id' => $dekan->id, 'google_id' => 'g-dekan', 'surel' => 'dekan@unsil.ac.id']);
    fakeGoogle('g-dekan', 'dekan@unsil.ac.id');

    $this->get(route('login.google.callback'))->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Akun ini memakai verifikasi dua langkah; masuk lewat halaman admin.']);

    $this->assertGuest();
});

it('menolak akun ber-MFA yang surelnya belum terverifikasi tanpa mengubah kata sandi, sesi, atau tautan', function () {
    $dekan = User::factory()->unverified()->create(['email' => 'dekan@unsil.ac.id', 'password' => Hash::make('rahasia-lama')])->assignRole('dekan');
    $dekan->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    DB::table('sessions')->insert(['id' => 's-dekan', 'user_id' => $dekan->id, 'payload' => '', 'last_activity' => time()]);
    fakeGoogle('g-dekan', 'dekan@unsil.ac.id');

    $this->get(route('login.google.callback'))->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Akun ini memakai verifikasi dua langkah; masuk lewat halaman admin.']);

    $this->assertGuest();
    $dekan->refresh();
    expect(Hash::check('rahasia-lama', $dekan->password))->toBeTrue()
        ->and($dekan->email_verified_at)->toBeNull()
        ->and(DB::table('sessions')->where('id', 's-dekan')->exists())->toBeTrue()
        ->and(IdentitasGoogle::count())->toBe(0);
});

it('membatasi rute Google per IP, 60 per menit, tanpa terpengaruh parameter email', function () {
    config(['services.google.client_id' => null]);

    foreach (range(1, 60) as $i) {
        $this->get(route('login.google', ['email' => "acak{$i}@contoh.test"]))->assertRedirect();
    }

    $this->get(route('login.google.callback', ['email' => 'lain@contoh.test']))->assertStatus(429);
});

it('menampilkan pesan ramah bila kunci Google belum diatur, dan tombol tetap tampil', function () {
    config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

    $this->get(route('login.google'))->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Masuk dengan Google belum tersedia.']);

    $this->get(route('login'))->assertSee('Masuk dengan Google');
    $this->get('/admin/login')->assertSee('Masuk dengan Google');
});

it('halaman masuk publik memuat tombol Google', function () {
    $this->get(route('login'))->assertOk()
        ->assertSee(route('login.google'), false)
        ->assertSee('Masuk dengan Google');
});

it('halaman masuk panel memuat tombol Google dengan dari=admin', function () {
    $this->get('/admin/login')->assertOk()
        ->assertSee(e(route('login.google', ['dari' => 'admin'])), false)
        ->assertSee('Masuk dengan Google');
});
