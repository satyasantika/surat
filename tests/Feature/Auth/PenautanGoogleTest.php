<?php

use App\Models\IdentitasGoogle;
use App\Models\User;
use App\Services\Auth\GagalMasukGoogle;
use App\Services\Auth\PenautanGoogle;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(PeranDanIzinSeeder::class));

function masukGoogle(string $googleId, string $surel, bool $terverifikasi = true, string $nama = 'Nama', ?string $hd = null): User
{
    return app(PenautanGoogle::class)->masuk($googleId, $surel, $terverifikasi, $nama, $hd);
}

it('membuat mahasiswa dari surel mahasiswa berhuruf campuran', function () {
    $user = masukGoogle('g-1', 'Budi@Student.Unsil.ac.id', nama: 'Budi');

    expect($user->email)->toBe('budi@student.unsil.ac.id')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->getRoleNames()->all())->toBe(['mahasiswa'])
        ->and(IdentitasGoogle::where('user_id', $user->id)->where('google_id', 'g-1')->exists())->toBeTrue();
});

it('membuat pengirim-eksternal dari surel gmail', function () {
    expect(masukGoogle('g-2', 'orang@gmail.com')->getRoleNames()->all())->toBe(['pengirim-eksternal']);
});

it('tidak menjadikan surel pegawai unsil sebagai pegawai', function () {
    expect(masukGoogle('g-3', 'pegawai@unsil.ac.id')->getRoleNames()->all())->toBe(['pengirim-eksternal']);
});

it('menganggap domain mirip sebagai pengirim-eksternal', function (string $surel) {
    expect(masukGoogle('g-4', $surel)->getRoleNames()->all())->toBe(['pengirim-eksternal']);
})->with(['x@student.unsil.ac.id.evil.com', 'x@xstudent.unsil.ac.id']);

it('menolak surel yang belum terverifikasi tanpa membuat pengguna', function () {
    $sebelum = User::count();

    expect(fn () => masukGoogle('g-5', 'a@gmail.com', false))->toThrow(GagalMasukGoogle::class);
    expect(User::count())->toBe($sebelum)->and(IdentitasGoogle::count())->toBe(0);
});

it('menolak hd yang berbeda dari domain surel', function () {
    expect(fn () => masukGoogle('g-6', 'a@gmail.com', hd: 'evil.com'))->toThrow(GagalMasukGoogle::class);
    expect(User::count())->toBe(0);
});

it('menautkan akun lama aktif tanpa mengubah peran atau menggandakan akun', function () {
    $lama = User::factory()->create(['email' => 'lama@gmail.com'])->assignRole('pengurus-ormawa');

    $user = masukGoogle('g-7', 'Lama@Gmail.com');

    expect($user->is($lama))->toBeTrue()
        ->and($user->getRoleNames()->all())->toBe(['pengurus-ormawa'])
        ->and(User::count())->toBe(1)
        ->and(IdentitasGoogle::where('user_id', $lama->id)->exists())->toBeTrue();
});

it('menautkan akun dekan lama tanpa menambah peran', function () {
    $dekan = User::factory()->create(['email' => 'dekan@unsil.ac.id'])->assignRole('dekan');

    $user = masukGoogle('g-8', 'dekan@unsil.ac.id');

    expect($user->is($dekan))->toBeTrue()->and($user->getRoleNames()->all())->toBe(['dekan']);
});

it('menolak akun lama nonaktif dengan pesan umum dan tanpa baris tertinggal', function () {
    User::factory()->create(['email' => 'mati@gmail.com', 'aktif' => false]);

    try {
        masukGoogle('g-9', 'mati@gmail.com');
        $this->fail('Seharusnya gagal.');
    } catch (GagalMasukGoogle $e) {
        expect($e->getMessage())->toBe('Surel atau kata sandi salah.');
    }

    expect(IdentitasGoogle::count())->toBe(0);
});

it('mengganti kata sandi dan mengakhiri sesi akun lama yang belum terverifikasi', function () {
    $lama = User::factory()->unverified()->create(['email' => 'belum@gmail.com', 'password' => Hash::make('rahasia-lama')]);
    DB::table('sessions')->insert(['id' => 's1', 'user_id' => $lama->id, 'payload' => '', 'last_activity' => time()]);
    DB::table('sessions')->insert(['id' => 's2', 'user_id' => null, 'payload' => '', 'last_activity' => time()]);

    $user = masukGoogle('g-10', 'belum@gmail.com')->fresh();

    expect(Hash::check('rahasia-lama', $user->password))->toBeFalse()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $lama->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('id', 's2')->count())->toBe(1);
});

it('mempertahankan kata sandi akun lama yang sudah terverifikasi', function () {
    User::factory()->create(['email' => 'ok@gmail.com', 'password' => Hash::make('rahasia-lama')]);

    $user = masukGoogle('g-11', 'ok@gmail.com')->fresh();

    expect(Hash::check('rahasia-lama', $user->password))->toBeTrue();
});

it('memakai pengguna yang sama untuk google_id sama walau surel berbeda', function () {
    $pertama = masukGoogle('g-12', 'asli@gmail.com');
    $kedua = masukGoogle('g-12', 'lain@gmail.com');

    expect($kedua->is($pertama))->toBeTrue()
        ->and($pertama->fresh()->email)->toBe('asli@gmail.com')
        ->and(User::count())->toBe(1);
});

it('menolak google_id baru untuk surel milik akun yang sudah tertaut ke Google lain', function () {
    masukGoogle('g-13', 'dua@gmail.com');

    expect(fn () => masukGoogle('g-14', 'dua@gmail.com'))->toThrow(GagalMasukGoogle::class);
    expect(IdentitasGoogle::count())->toBe(1)->and(User::count())->toBe(1);
});
