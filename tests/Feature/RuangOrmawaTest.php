<?php

use App\Livewire\Ormawa\Beranda;
use App\Livewire\Ormawa\Profil;
use App\Models\Ormawa;
use App\Models\PengurusOrmawa;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->withoutVite();
    $this->seed(PeranDanIzinSeeder::class);
    CarbonImmutable::setTestNow('2026-06-15 10:00:00');
});

afterEach(fn () => CarbonImmutable::setTestNow());

function himpunan(string $nama = 'HIMA Mat'): Ormawa
{
    $o = Ormawa::create(['nama' => $nama, 'singkatan' => strtoupper(substr($nama, 0, 4)), 'tingkat' => 'prodi']);
    $o->sk()->create(['nomor_sk' => "SK/{$nama}", 'tanggal_sk' => '2026-01-01', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2026-12-31']);

    return $o;
}

function jadiPengurus(Ormawa $o, string $jabatan, ?User $u = null, array $ubah = []): User
{
    $u ??= User::factory()->create(['nip_nim' => (string) random_int(3000000, 3999999)])->assignRole('pengurus-ormawa');
    PengurusOrmawa::create($ubah + ['ormawa_id' => $o->id, 'user_id' => $u->id, 'nama' => $u->name, 'nim' => $u->nip_nim, 'jabatan' => $jabatan, 'telepon' => '0811000'.random_int(1000, 9999)]);

    return $u;
}

describe('beranda', function () {
    it('menolak yang bukan pengurus dan tamu', function () {
        $this->get('/ormawa')->assertRedirect('/masuk');
        Livewire::actingAs(User::factory()->create()->assignRole('pegawai'))->test(Beranda::class)->assertForbidden();
    });

    it('menampilkan ormawa aktif, SK berlaku, dan kartu permohonan/LPJ', function () {
        $o = himpunan();
        $u = jadiPengurus($o, 'anggota');

        Livewire::actingAs($u)->test(Beranda::class)->assertSee('HIMA Mat')->assertSee('SK/HIMA Mat')->assertSee('Permohonan')->assertSee('LPJ jatuh tempo')
            ->assertSee('Lihat profil dan pengurus')->assertDontSee('Ubah profil dan pengurus');
        Livewire::actingAs(jadiPengurus($o, 'ketua'))->test(Beranda::class)->assertSee('Ubah profil dan pengurus');
    });

    it('memilih ormawa bila pengurus di lebih dari satu dan menolak ormawa orang lain', function () {
        $a = himpunan('Alfa');
        $b = himpunan('Beta');
        $lain = himpunan('Lain');
        $u = jadiPengurus($a, 'anggota');
        jadiPengurus($b, 'bendahara', $u);

        Livewire::actingAs($u)->test(Beranda::class)->assertSee('ALFA')->assertSee('BETA')
            ->call('pilih', $b->id)->assertSee('Beta')->assertSee('SK/Beta')
            ->call('pilih', $lain->id)->assertForbidden();
    });

    it('menjelaskan bila belum terhubung atau keanggotaan belum aktif', function () {
        $tanpa = User::factory()->create(['nip_nim' => '3111111'])->assignRole('pengurus-ormawa');
        Livewire::actingAs($tanpa)->test(Beranda::class)->assertSee('belum terhubung')->assertSee('3111111');

        $o = Ormawa::create(['nama' => 'Tanpa SK', 'tingkat' => 'ukm']);
        $u = jadiPengurus($o, 'ketua');
        Livewire::actingAs($u)->test(Beranda::class)->assertSee('belum aktif')->assertSee('SK kepengurusan');
    });
});

describe('profil dan pengurus', function () {
    it('mengizinkan ketua dan sekretaris mengubah profil', function (string $jabatan) {
        $o = himpunan();
        $u = jadiPengurus($o, $jabatan);

        Livewire::actingAs($u)->test(Profil::class, ['ormawa' => $o->id])
            ->set('singkatan', 'HM')->set('akun_media', '@himamat')->set('surel_organisasi', 'hima@unsil.ac.id')->set('visi', 'Visi baru')->set('misi', 'Misi baru')
            ->call('simpanProfil')->assertHasNoErrors();

        $o = $o->fresh();
        expect($o->singkatan)->toBe('HM')->and($o->akun_media)->toBe('@himamat')->and($o->visi)->toBe('Visi baru')->and($o->nama)->toBe('HIMA Mat');
    })->with(['ketua', 'sekretaris']);

    it('menolak bendahara dan anggota mengubah profil atau pengurus, dan menyembunyikan formulir', function (string $jabatan) {
        $o = himpunan();
        jadiPengurus($o, 'ketua');
        $u = jadiPengurus($o, $jabatan);

        Livewire::actingAs($u)->test(Profil::class, ['ormawa' => $o->id])->assertDontSee('Simpan profil')->assertDontSee('Tambah pengurus');
        Livewire::actingAs($u)->test(Profil::class, ['ormawa' => $o->id])->call('simpanProfil')->assertForbidden();
        Livewire::actingAs($u)->test(Profil::class, ['ormawa' => $o->id])
            ->set('form.nama', 'Penyusup')->set('form.jabatan', 'ketua')->call('simpanPengurus')->assertForbidden();

        expect($o->pengurus()->where('nama', 'Penyusup')->exists())->toBeFalse();
    })->with(['bendahara', 'anggota', 'wakil_ketua']);

    it('menolak pengurus ormawa lain', function () {
        $a = himpunan('Alfa');
        $b = himpunan('Beta');
        $ketuaA = jadiPengurus($a, 'ketua');

        Livewire::actingAs($ketuaA)->test(Profil::class, ['ormawa' => $b->id])->assertForbidden();
        Livewire::actingAs($ketuaA)->test(Profil::class, ['ormawa' => $a->id])->assertOk();
        expect($a->fresh()->nama)->toBe('Alfa');
    });

    it('menambah, mengubah, dan menghapus pengurus oleh ketua dengan validasi', function () {
        $o = himpunan();
        $ketua = jadiPengurus($o, 'ketua');
        $k = Livewire::actingAs($ketua)->test(Profil::class, ['ormawa' => $o->id]);

        $k->set('form.nama', '')->call('simpanPengurus')->assertHasErrors();
        $k->set('form.nama', 'Dina')->set('form.jabatan', 'raja')->call('simpanPengurus')->assertHasErrors();
        $k->set('form.jabatan', 'bendahara')->set('form.nim', '3222222')->set('form.telepon', 'abc')->call('simpanPengurus')->assertHasErrors();

        $k->set('form.telepon', '081234')->call('simpanPengurus')->assertHasNoErrors();
        $dina = $o->pengurus()->firstWhere('nama', 'Dina');
        expect($dina->jabatan)->toBe('bendahara')->and($dina->telepon)->toBe('081234')->and($dina->user_id)->toBeNull();

        $k->call('ubah', $dina->id)->set('form.jabatan_teks', 'Bendahara Umum')->call('simpanPengurus')->assertHasNoErrors();
        expect($dina->fresh()->jabatan_teks)->toBe('Bendahara Umum');

        $k->call('hapus', $dina->id);
        expect($o->pengurus()->where('nama', 'Dina')->exists())->toBeFalse();
    });

    it('tidak membiarkan ormawa kehilangan ketua/sekretaris bertaut akun', function () {
        $o = himpunan();
        $ketua = jadiPengurus($o, 'ketua');
        $milikKetua = $o->pengurus()->where('user_id', $ketua->id)->first();
        $k = Livewire::actingAs($ketua)->test(Profil::class, ['ormawa' => $o->id]);

        $k->call('hapus', $milikKetua->id)->assertHasErrors('jabatan');
        $k->call('ubah', $milikKetua->id)->set('form.jabatan', 'anggota')->call('simpanPengurus')->assertHasErrors('jabatan');

        expect($milikKetua->fresh()->jabatan)->toBe('ketua')->and($o->pengurus()->count())->toBe(1);

        // dengan sekretaris lain bertaut, ketua boleh turun jabatan
        jadiPengurus($o, 'sekretaris');
        $k->call('ubah', $milikKetua->id)->set('form.jabatan', 'anggota')->call('simpanPengurus')->assertHasNoErrors();
        expect($milikKetua->fresh()->jabatan)->toBe('anggota');
    });

    it('tidak dapat mengubah atau menghapus pengurus ormawa lain lewat id', function () {
        $a = himpunan('Alfa');
        $b = himpunan('Beta');
        $ketuaA = jadiPengurus($a, 'ketua');
        jadiPengurus($b, 'ketua');
        $targetB = $b->pengurus()->first();

        Livewire::actingAs($ketuaA)->test(Profil::class, ['ormawa' => $a->id])->call('ubah', $targetB->id)->assertNotFound();
        Livewire::actingAs($ketuaA)->test(Profil::class, ['ormawa' => $a->id])->call('hapus', $targetB->id)->assertNotFound();

        expect($b->pengurus()->count())->toBe(1);
    });
});

describe('privasi data pengurus (BR-18)', function () {
    it('menampilkan NIM dan telepon hanya kepada yang berhak', function () {
        $o = himpunan();
        jadiPengurus($o, 'ketua');
        $target = jadiPengurus($o, 'anggota', ubah: ['nim' => '3777777', 'telepon' => '081377778888']);
        $bendahara = jadiPengurus($o, 'bendahara');
        $ketua = $o->pengurus()->where('jabatan', 'ketua')->first()->user;

        Livewire::actingAs($ketua)->test(Profil::class, ['ormawa' => $o->id])->assertSee('3777777')->assertSee('081377778888');
        Livewire::actingAs($bendahara)->test(Profil::class, ['ormawa' => $o->id])->assertSee($target->name)->assertDontSee('3777777')->assertDontSee('081377778888');
        Livewire::actingAs($target)->test(Profil::class, ['ormawa' => $o->id])->assertSee('3777777');
    });

    it('menampilkan penanda belum bertaut dan narahubung', function () {
        $o = himpunan();
        $ketua = jadiPengurus($o, 'ketua');
        PengurusOrmawa::create(['ormawa_id' => $o->id, 'nama' => 'Belum Akun', 'jabatan' => 'anggota', 'narahubung' => true]);

        Livewire::actingAs($ketua)->test(Profil::class, ['ormawa' => $o->id])->assertSee('Belum Akun')->assertSee('Belum bertaut akun')->assertSee('Narahubung');
    });
});

it('mengarahkan beranda sesuai peran', function () {
    expect(App\Support\Beranda::url(User::factory()->create()->assignRole('dekan')))->toEndWith('/disposisi')
        ->and(App\Support\Beranda::url(User::factory()->create()->assignRole('pengurus-ormawa')))->toEndWith('/ormawa')
        ->and(App\Support\Beranda::url(User::factory()->create()->assignRole('pembina-ormawa')))->toEndWith('/profil');
});
