<?php

use App\Actions\Disposisi\BuatDisposisi;
use App\Actions\Masuk\RegistrasiSuratMasuk;
use App\Enums\StatusDisposisiPenerima;
use App\Filament\Resources\SuratMasuks\DisposisiRelationManager;
use App\Filament\Resources\SuratMasuks\Pages\EditSuratMasuk;
use App\Livewire\Pimpinan\KotakMasuk;
use App\Models\Disposisi;
use App\Models\DisposisiPenerima;
use App\Models\SuratMasuk;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->withoutVite();
    $this->seed([PeranDanIzinSeeder::class, RegisterNomorSeeder::class]);
});

function pengguna(string $peran, array $atribut = []): User
{
    return User::factory()->create($atribut)->assignRole($peran);
}

function suratKotak(array $ubah = []): SuratMasuk
{
    return app(RegistrasiSuratMasuk::class)->jalankan($ubah + [
        'nomor_surat' => '9/X/2026', 'tanggal_surat' => now()->toDateString(), 'asal' => 'Dinas', 'perihal' => 'Perihal uji kotak',
        'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa',
        'pindaian_url' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view',
    ], pengguna('admin-persuratan'));
}

function kirim(SuratMasuk $s, User $dekan, array $penerima, ?DisposisiPenerima $induk = null): Disposisi
{
    return app(BuatDisposisi::class)->jalankan($s, $dekan, collect($penerima)->pluck('id')->all(), ['tindak_lanjuti'], 'Catatan uji', null, $induk);
}

it('menolak peran di luar pimpinan dan pegawai', function (string $peran) {
    Livewire::actingAs(pengguna($peran))->test(KotakMasuk::class)->assertForbidden();
})->with(['admin-persuratan', 'pengurus-ormawa', 'operator-layanan', 'pembina-ormawa']);

it('mengalihkan tamu ke halaman masuk', function () {
    $this->get('/disposisi')->assertRedirect('/masuk');
});

it('menampilkan surat belum didisposisikan hanya kepada dekan', function () {
    $surat = suratKotak();

    Livewire::actingAs(pengguna('dekan'))->test(KotakMasuk::class)->assertSee($surat->nomor_agenda)->assertSee('Perihal uji kotak');
    Livewire::actingAs(pengguna('wakil-dekan'))->test(KotakMasuk::class)->assertDontSee($surat->nomor_agenda)->assertSee('Tidak ada item');
});

it('menampilkan hanya disposisi milik pengguna pada tab sesuai status', function () {
    $dekan = pengguna('dekan');
    $a = pengguna('pegawai');
    $b = pengguna('pegawai');
    $s1 = suratKotak(['perihal' => 'Surat untuk A']);
    $s2 = suratKotak(['perihal' => 'Surat untuk B']);
    kirim($s1, $dekan, [$a]);
    kirim($s2, $dekan, [$b]);

    Livewire::actingAs($a)->test(KotakMasuk::class)->assertSee('Surat untuk A')->assertDontSee('Surat untuk B');

    DisposisiPenerima::where('user_id', $a->id)->update(['status' => StatusDisposisiPenerima::Selesai]);
    Livewire::actingAs($a)->test(KotakMasuk::class)->assertDontSee('Surat untuk A')
        ->call('pilihTab', 'selesai')->assertSee('Surat untuk A');
});

it('menandai terlambat dan menampilkan tab terkirim', function () {
    $dekan = pengguna('dekan');
    $wd = pengguna('wakil-dekan');
    $d = kirim(suratKotak(), $dekan, [$wd]);
    $d->update(['batas_waktu' => now()->subHour()]);

    Livewire::actingAs($wd)->test(KotakMasuk::class)->assertSee('Terlambat');
    Livewire::actingAs($dekan)->test(KotakMasuk::class)->call('pilihTab', 'terkirim')->assertSee($wd->name)->assertSee('(terlambat)');
});

it('menyamarkan perihal rahasia bagi yang belum berhak dan membukanya bagi penerima', function () {
    $dekan = pengguna('dekan');
    $wd = pengguna('wakil-dekan');
    $surat = suratKotak(['klasifikasi_keamanan' => 'rahasia', 'perihal' => 'Perihal sangat rahasia']);

    Livewire::actingAs($dekan)->test(KotakMasuk::class)->assertSee('Perihal sangat rahasia');

    kirim($surat, $dekan, [$wd]);
    Livewire::actingAs($wd)->test(KotakMasuk::class)->assertSee('Perihal sangat rahasia');
});

it('dekan mendisposisikan surat lewat komponen dan Action dipanggil', function () {
    $dekan = pengguna('dekan');
    $wd = pengguna('wakil-dekan');
    $surat = suratKotak();

    Livewire::actingAs($dekan)->test(KotakMasuk::class)
        ->call('buka', "surat:{$surat->id}")->call('mulai', 'disposisi')
        ->set('penerima', [$wd->id])->set('instruksi', ['hadiri'])->set('catatan', 'Mohon hadir')
        ->call('disposisikan', $surat->id)
        ->assertHasNoErrors();

    $d = Disposisi::firstWhere('surat_masuk_id', $surat->id);
    expect($d->penerima->pluck('user_id')->all())->toBe([$wd->id])
        ->and($d->instruksi)->toBe(['hadiri'])
        ->and($surat->fresh()->status->value)->toBe('didisposisikan');
});

it('menampilkan galat validasi saat disposisi tanpa penerima', function () {
    $surat = suratKotak();

    Livewire::actingAs(pengguna('dekan'))->test(KotakMasuk::class)
        ->call('buka', "surat:{$surat->id}")->call('mulai', 'disposisi')
        ->set('instruksi', ['hadiri'])->call('disposisikan', $surat->id)
        ->assertHasErrors('penerima');

    expect(Disposisi::count())->toBe(0);
});

it('menolak pengguna lain mendisposisikan atau membuka kartu surat', function (string $peran) {
    $surat = suratKotak();

    Livewire::actingAs(pengguna($peran))->test(KotakMasuk::class)
        ->call('buka', "surat:{$surat->id}")->assertForbidden();
    Livewire::actingAs(pengguna($peran))->test(KotakMasuk::class)
        ->set('penerima', [pengguna('pegawai')->id])->set('instruksi', ['hadiri'])->call('disposisikan', $surat->id)->assertForbidden();
    expect(Disposisi::count())->toBe(0);
})->with(['wakil-dekan', 'kasubag', 'pegawai']);

it('menandai dibaca saat kartu dibuka dan menolak kartu milik orang lain', function () {
    $dekan = pengguna('dekan');
    $a = pengguna('pegawai');
    $b = pengguna('pegawai');
    kirim(suratKotak(), $dekan, [$a]);
    $p = DisposisiPenerima::firstWhere('user_id', $a->id);

    Livewire::actingAs($b)->test(KotakMasuk::class)->call('buka', "penerima:{$p->id}")->assertNotFound();
    expect($p->fresh()->status)->toBe(StatusDisposisiPenerima::Diterima);

    Livewire::actingAs($a)->test(KotakMasuk::class)->call('buka', "penerima:{$p->id}");
    expect($p->fresh()->status)->toBe(StatusDisposisiPenerima::Dibaca);
});

it('melapor, mewajibkan isi laporan, lalu menyelesaikan', function () {
    $dekan = pengguna('dekan');
    $a = pengguna('pegawai');
    kirim(suratKotak(), $dekan, [$a]);
    $p = DisposisiPenerima::firstWhere('user_id', $a->id);

    $komponen = Livewire::actingAs($a)->test(KotakMasuk::class)->call('buka', "penerima:{$p->id}")->call('mulai', 'lapor');

    $komponen->set('laporan', '   ')->call('lapor', $p->id)->assertHasErrors('laporan');
    $komponen->set('laporan', 'Sudah dilaksanakan')->call('lapor', $p->id)->assertHasNoErrors();
    expect($p->fresh()->status)->toBe(StatusDisposisiPenerima::Ditindaklanjuti);

    $komponen->call('selesai', $p->id);
    expect($p->fresh()->status)->toBe(StatusDisposisiPenerima::Selesai)
        ->and(SuratMasuk::first()->status->value)->toBe('selesai');
});

it('mengizinkan wakil dekan meneruskan tetapi tidak pegawai', function () {
    $dekan = pengguna('dekan');
    $wd = pengguna('wakil-dekan');
    $pegawai = pengguna('pegawai');
    $surat = suratKotak();
    kirim($surat, $dekan, [$wd, $pegawai]);
    $milikWd = DisposisiPenerima::firstWhere('user_id', $wd->id);
    $milikPegawai = DisposisiPenerima::firstWhere('user_id', $pegawai->id);
    $target = pengguna('kasubag');

    Livewire::actingAs($wd)->test(KotakMasuk::class)->call('buka', "penerima:{$milikWd->id}")->call('mulai', 'teruskan')
        ->set('penerima', [$target->id])->set('instruksi', ['pelajari'])->call('teruskan', $milikWd->id)->assertHasNoErrors();
    expect(Disposisi::where('induk_penerima_id', $milikWd->id)->count())->toBe(1);

    Livewire::actingAs($pegawai)->test(KotakMasuk::class)->call('buka', "penerima:{$milikPegawai->id}")
        ->set('penerima', [$target->id])->set('instruksi', ['pelajari'])->call('teruskan', $milikPegawai->id)->assertForbidden();
    expect(Disposisi::where('induk_penerima_id', $milikPegawai->id)->count())->toBe(0);
});

it('menampilkan status penerima di panel surat masuk', function () {
    Filament::setCurrentPanel('admin');
    $dekan = pengguna('dekan');
    $wd = pengguna('wakil-dekan');
    $surat = suratKotak();
    kirim($surat, $dekan, [$wd]);

    Livewire::actingAs($dekan)->test(DisposisiRelationManager::class, ['ownerRecord' => $surat, 'pageClass' => EditSuratMasuk::class])
        ->assertCanSeeTableRecords($surat->penerimaDisposisi)
        ->assertSee($wd->name)->assertSee('Diterima');
});

it('mengarahkan pegawai ke kotak masuk setelah masuk', function () {
    $pegawai = pengguna('pegawai', ['password' => 'Rahasia12345']);

    $this->post('/masuk', ['email' => $pegawai->email, 'password' => 'Rahasia12345'])->assertRedirect('/disposisi');
});
