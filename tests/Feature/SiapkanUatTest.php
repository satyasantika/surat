<?php

use App\Models\Lpj;
use App\Models\Ormawa;
use App\Models\Permohonan;
use App\Models\SuratMasuk;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\UsulanMahasiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Mail::fake();
});

it('menolak dijalankan di produksi', function () {
    $this->app['env'] = 'production';

    $this->artisan('surat:siapkan-uat')->expectsOutputToContain('ditolak di produksi')->assertFailed();

    expect(User::count())->toBe(0);
});

it('menyiapkan akun per peran, tiga ormawa, dan permohonan di setiap tahap', function () {
    $this->artisan('surat:siapkan-uat')->assertSuccessful();

    $peran = User::with('roles')->get()->flatMap(fn ($u) => $u->roles->pluck('name'))->countBy();
    expect($peran->all())->toMatchArray(['admin-persuratan' => 1, 'operator-layanan' => 1, 'dekan' => 1, 'wakil-dekan' => 3, 'kasubag' => 1, 'pembina-ormawa' => 1, 'pegawai' => 1, 'pengurus-ormawa' => 3, 'pengaju-unit' => 1, 'kaprodi' => 1, 'kalab' => 1, 'mahasiswa' => 1])
        ->and(Ormawa::count())->toBe(3)->and(SuratMasuk::count())->toBe(4);

    $status = Permohonan::whereNotNull('ormawa_id')->where('nama_kegiatan', 'not like', '%gabungan usulan%')->pluck('status')->map->value->countBy();
    expect($status->all())->toMatchArray(['validasi_admin' => 1, 'disposisi_dekan' => 1, 'persetujuan_wd' => 1, 'rekomendasi_kasubag' => 1, 'penerbitan' => 1, 'dikembalikan' => 1, 'ditolak' => 1, 'selesai' => 3]);

    // Alur unit: prodi/lab mengajukan sendiri, dan usulan mahasiswa (dua digabung menjadi dasar satu permohonan ormawa).
    $unit = Permohonan::whereNotNull('unit_kerja_id')->pluck('status')->map->value->countBy();
    expect($unit->all())->toBe(['persetujuan_unit' => 1, 'validasi_admin' => 1, 'dikembalikan' => 1])
        ->and(UsulanMahasiswa::pluck('status')->map->value->countBy()->all())->toMatchArray(['diajukan' => 2, 'dikembalikan' => 1, 'dipakai' => 2])
        ->and(Permohonan::firstWhere('nama_kegiatan', 'like', '%gabungan usulan%')->usulanDasar()->count())->toBe(2)
        ->and(UnitKerja::whereIn('tipe', ['prodi', 'laboratorium'])->count())->toBe(2);

    $lpj = Lpj::pluck('status')->countBy();
    expect($lpj->all())->toMatchArray(['draf' => 1, 'diajukan' => 1, 'dinilai' => 1]);
});

it('data rekaan memakai domain contoh.test, tanpa data pribadi asli, dan kata sandi hanya berupa hash', function () {
    $this->artisan('surat:siapkan-uat')->assertSuccessful();

    foreach (User::all() as $u) {
        expect($u->email)->toEndWith('@contoh.test')->and($u->password)->toStartWith('$2y$')->and(strlen($u->password))->toBeGreaterThan(50);
    }

    expect(User::where('email', 'like', '%@unsil.ac.id')->count())->toBe(0)->and(Ormawa::pluck('nama')->all())->each->toContain('Uji');
});

it('kata sandi acak dicetak sekali dan akun pejabat bermasa jabatan', function () {
    $this->artisan('surat:siapkan-uat')->expectsOutputToContain('uat.dekan@contoh.test')->assertSuccessful();

    $dekan = User::firstWhere('email', 'uat.dekan@contoh.test');
    expect($dekan->jabatanAktif()->pluck('kode')->all())->toBe(['dekan'])->and(User::firstWhere('email', 'uat.kasubag@contoh.test')->jabatanAktif()->pluck('kode')->all())->toBe(['kasubag-umum']);
});

it('idempoten: menjalankan ulang tidak menggandakan akun/data dan tidak mengubah kata sandi', function () {
    $this->artisan('surat:siapkan-uat')->assertSuccessful();
    $hash = User::firstWhere('email', 'uat.dekan@contoh.test')->password;
    $hitung = fn () => [User::count(), Ormawa::count(), SuratMasuk::count(), Permohonan::count(), Lpj::count(), UsulanMahasiswa::count()];
    $sebelum = $hitung();

    $this->artisan('surat:siapkan-uat')->expectsOutputToContain('akun sudah ada')->assertSuccessful();

    expect($hitung())->toBe($sebelum)->and(User::firstWhere('email', 'uat.dekan@contoh.test')->password)->toBe($hash);
});

it('tidak mengirim surel atau notifikasi saat menyiapkan data', function () {
    $this->artisan('surat:siapkan-uat')->assertSuccessful();

    Mail::assertNothingSent();
    expect(DB::table('notifications')->count())->toBe(0);
});

it('akun UAT dapat masuk dengan kata sandi yang dicetak', function () {
    $this->withoutVite();
    $sandi = null;
    $this->artisan('surat:siapkan-uat')->assertSuccessful();

    $user = User::firstWhere('email', 'uat.pengurus-bem@contoh.test');
    $user->forceFill(['password' => Hash::make('KataSandiUji123')])->save();

    $this->post('/masuk', ['email' => 'uat.pengurus-bem@contoh.test', 'password' => 'KataSandiUji123'])->assertRedirect();
    $this->assertAuthenticatedAs($user);
});
