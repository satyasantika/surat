<?php

use App\Actions\Naskah\AjukanParaf;
use App\Actions\Naskah\KembalikanNaskah;
use App\Actions\Naskah\ParafiNaskah;
use App\Actions\Naskah\SimpanDraf;
use App\Enums\StatusNaskah;
use App\Filament\Resources\Naskahs\Pages\EditNaskah;
use App\Livewire\Pimpinan\KotakMasuk;
use App\Models\Jabatan;
use App\Models\JenisNaskah;
use App\Models\Naskah;
use App\Models\PemangkuJabatan;
use App\Models\RiwayatNaskah;
use App\Models\User;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, PengaturanSeeder::class]);
    Filament::setCurrentPanel('admin');
});

function aktorNaskah(string $peran): User
{
    $u = User::factory()->create()->assignRole($peran);
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    return $u;
}

function drafBaru(User $penyusun): Naskah
{
    $jenis = JenisNaskah::firstWhere('kode', 'surat-dinas');

    return app(SimpanDraf::class)->jalankan(null, [
        'jenis_naskah_id' => $jenis->id, 'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa', 'perihal' => 'Uji paraf',
        'penanda_tangan_jabatan_id' => $jenis->jabatan_penanda_tangan_bawaan_id, 'mode_tanda_tangan' => 'basah',
        'data' => ['tujuan' => 'Dinas'], 'tujuan' => [['nama' => 'Dinas']],
    ], $penyusun);
}

it('mengajukan tanpa pemaraf langsung menunggu tanda tangan', function () {
    $p = aktorNaskah('admin-persuratan');
    $n = app(AjukanParaf::class)->jalankan(drafBaru($p), $p);

    expect($n->status)->toBe(StatusNaskah::MenungguTandaTangan)->and($n->paraf)->toHaveCount(0)
        ->and(RiwayatNaskah::where('naskah_id', $n->id)->pluck('ke_status')->all())->toBe(['menunggu_tanda_tangan']);
});

it('menjalankan alur paraf berurutan sampai menunggu tanda tangan dengan riwayat lengkap', function () {
    $p = aktorNaskah('admin-persuratan');
    [$a, $b] = [aktorNaskah('kasubag'), aktorNaskah('wakil-dekan')];
    $n = drafBaru($p);

    $n = app(AjukanParaf::class)->jalankan($n, $p, [$a->id, $b->id]);
    expect($n->status)->toBe(StatusNaskah::Paraf)->and($n->paraf->pluck('user_id')->all())->toBe([$a->id, $b->id]);

    app(ParafiNaskah::class)->jalankan($n, $a, 'OK dari A');
    expect($n->fresh()->status)->toBe(StatusNaskah::Paraf)->and($n->fresh()->parafBerjalan()->user_id)->toBe($b->id);

    app(ParafiNaskah::class)->jalankan($n, $b);
    $n = $n->fresh();

    expect($n->status)->toBe(StatusNaskah::MenungguTandaTangan)
        ->and($n->paraf->pluck('status')->unique()->all())->toBe(['disetujui'])
        ->and($n->paraf[0]->catatan)->toBe('OK dari A')
        ->and($n->riwayat->map(fn ($r) => "{$r->dari_status}>{$r->ke_status}")->all())
        ->toBe(['draf>paraf', 'paraf>menunggu_tanda_tangan']);
});

it('hanya pemaraf yang gilirannya berjalan yang boleh memaraf', function () {
    $p = aktorNaskah('admin-persuratan');
    [$a, $b] = [aktorNaskah('kasubag'), aktorNaskah('wakil-dekan')];
    $n = app(AjukanParaf::class)->jalankan(drafBaru($p), $p, [$a->id, $b->id]);

    expect(fn () => app(ParafiNaskah::class)->jalankan($n, $b))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ParafiNaskah::class)->jalankan($n, aktorNaskah('dekan')))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ParafiNaskah::class)->jalankan($n, $p))->toThrow(AuthorizationException::class);
    expect($n->fresh()->parafBerjalan()->user_id)->toBe($a->id);
});

it('mencegah klik ganda: paraf kedua kali ditolak dan tidak menggeser giliran', function () {
    $p = aktorNaskah('admin-persuratan');
    [$a, $b] = [aktorNaskah('kasubag'), aktorNaskah('wakil-dekan')];
    $n = app(AjukanParaf::class)->jalankan(drafBaru($p), $p, [$a->id, $b->id]);

    app(ParafiNaskah::class)->jalankan($n, $a);
    expect(fn () => app(ParafiNaskah::class)->jalankan($n, $a))->toThrow(AuthorizationException::class);
    expect($n->fresh()->paraf->where('status', 'disetujui'))->toHaveCount(1);

    $c = aktorNaskah('dekan');
    $solo = app(AjukanParaf::class)->jalankan(drafBaru($p), $p, []);
    expect(fn () => app(AjukanParaf::class)->jalankan($solo, $p, []))->toThrow(ValidationException::class);
    expect(RiwayatNaskah::where('naskah_id', $solo->id)->count())->toBe(1)->and($c->exists)->toBeTrue();
});

it('menolak pemaraf tanpa izin, nonaktif, penyusun sendiri, atau pengaju non-penyusun', function () {
    $p = aktorNaskah('admin-persuratan');
    $n = drafBaru($p);

    expect(fn () => app(AjukanParaf::class)->jalankan($n, $p, [aktorNaskah('pegawai')->id]))->toThrow(ValidationException::class)
        ->and(fn () => app(AjukanParaf::class)->jalankan($n, $p, [User::factory()->create(['aktif' => false])->assignRole('kasubag')->id]))->toThrow(ValidationException::class)
        ->and(fn () => app(AjukanParaf::class)->jalankan($n, aktorNaskah('kasubag'), []))->toThrow(AuthorizationException::class);

    $kasubagPenyusun = aktorNaskah('kasubag');
    expect(fn () => app(AjukanParaf::class)->jalankan(drafBaru($kasubagPenyusun), $kasubagPenyusun, [$kasubagPenyusun->id]))->toThrow(ValidationException::class);
    expect($n->fresh()->status)->toBe(StatusNaskah::Draf);
});

it('mengunci naskah yang sedang diparaf dari perubahan penyusun', function () {
    $p = aktorNaskah('admin-persuratan');
    $n = app(AjukanParaf::class)->jalankan(drafBaru($p), $p, [aktorNaskah('kasubag')->id]);

    expect($p->can('update', $n->fresh()))->toBeFalse();
});

it('mengembalikan dari paraf dengan catatan wajib, lalu penyusun memperbaiki dan mengajukan lagi', function () {
    $p = aktorNaskah('admin-persuratan');
    $a = aktorNaskah('kasubag');
    $n = app(AjukanParaf::class)->jalankan(drafBaru($p), $p, [$a->id]);

    expect(fn () => app(KembalikanNaskah::class)->jalankan($n, $a, '   '))->toThrow(ValidationException::class)
        ->and(fn () => app(KembalikanNaskah::class)->jalankan($n, aktorNaskah('kasubag'), 'Salah'))->toThrow(AuthorizationException::class);

    app(KembalikanNaskah::class)->jalankan($n, $a, 'Perbaiki tujuan surat');
    $n = $n->fresh();
    expect($n->status)->toBe(StatusNaskah::Dikembalikan)->and($n->paraf[0]->status)->toBe('dikembalikan')
        ->and($n->riwayat->last()->catatan)->toBe('Perbaiki tujuan surat');

    // penyusun mengubah → kembali draf → ajukan putaran baru
    $ubah = app(SimpanDraf::class)->jalankan($n, ['jenis_naskah_id' => $n->jenis_naskah_id, 'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa', 'perihal' => 'Perbaikan',
        'penanda_tangan_jabatan_id' => $n->penanda_tangan_jabatan_id, 'mode_tanda_tangan' => 'basah', 'data' => ['tujuan' => 'Dinas'], 'tujuan' => [['nama' => 'Dinas Baru']]], $p);
    expect($ubah->status)->toBe(StatusNaskah::Draf);

    $baru = app(AjukanParaf::class)->jalankan($ubah, $p, [$a->id]);
    expect($baru->status)->toBe(StatusNaskah::Paraf)->and($baru->paraf)->toHaveCount(1)->and($baru->paraf[0]->status)->toBe('menunggu');
});

it('mengembalikan dari menunggu tanda tangan hanya oleh pemangku penanda tangan', function () {
    $p = aktorNaskah('admin-persuratan');
    $dekan = aktorNaskah('dekan');
    PemangkuJabatan::create(['jabatan_id' => Jabatan::firstWhere('kode', 'dekan')->id, 'user_id' => $dekan->id, 'mulai' => now()->subYear()]);
    $n = app(AjukanParaf::class)->jalankan(drafBaru($p), $p, []);

    expect(fn () => app(KembalikanNaskah::class)->jalankan($n, aktorNaskah('dekan'), 'Tolak'))->toThrow(AuthorizationException::class);

    app(KembalikanNaskah::class)->jalankan($n, $dekan, 'Isi kurang lengkap');
    expect($n->fresh()->status)->toBe(StatusNaskah::Dikembalikan);
});

it('menolak aksi pada status yang tidak sesuai', function () {
    $p = aktorNaskah('admin-persuratan');
    $a = aktorNaskah('kasubag');
    $draf = drafBaru($p);

    expect(fn () => app(ParafiNaskah::class)->jalankan($draf, $a))->toThrow(ValidationException::class)
        ->and(fn () => app(KembalikanNaskah::class)->jalankan($draf, $a, 'x'))->toThrow(ValidationException::class);
});

it('menjaga riwayat naskah tidak dapat diubah atau dihapus', function () {
    $p = aktorNaskah('admin-persuratan');
    $n = app(AjukanParaf::class)->jalankan(drafBaru($p), $p, []);
    $r = $n->riwayat[0];

    expect(fn () => $r->update(['catatan' => 'palsu']))->toThrow(LogicException::class)
        ->and(fn () => $r->delete())->toThrow(LogicException::class);
});

it('memberi pemaraf akses lihat naskah dan memuatnya di daftar', function () {
    $p = aktorNaskah('admin-persuratan');
    $a = aktorNaskah('kasubag');
    $n = drafBaru($p);

    expect($a->can('view', $n))->toBeFalse();
    app(AjukanParaf::class)->jalankan($n, $p, [$a->id]);
    expect($a->fresh()->can('view', $n))->toBeTrue()
        ->and(Naskah::terlihatOleh($a)->pluck('id')->all())->toBe([$n->id]);
});

it('menampilkan tab paraf di kotak masuk dan memaraf lewat komponen', function () {
    $p = aktorNaskah('admin-persuratan');
    [$a, $b] = [aktorNaskah('kasubag'), aktorNaskah('wakil-dekan')];
    $n = app(AjukanParaf::class)->jalankan(drafBaru($p), $p, [$a->id, $b->id]);

    Livewire::actingAs($b)->test(KotakMasuk::class)->call('pilihTab', 'naskah')->assertDontSee('Uji paraf');

    $komponen = Livewire::actingAs($a)->test(KotakMasuk::class)->call('pilihTab', 'naskah')->assertSee('Uji paraf');
    $komponen->call('buka', "naskah:{$n->id}")->set('catatanNaskah', 'Setuju')->call('parafi', $n->id);

    expect($n->fresh()->parafBerjalan()->user_id)->toBe($b->id);
    Livewire::actingAs($b)->test(KotakMasuk::class)->call('pilihTab', 'naskah')->assertSee('Uji paraf');
});

it('mengembalikan naskah lewat komponen dan menolak pemaraf yang bukan gilirannya', function () {
    $p = aktorNaskah('admin-persuratan');
    [$a, $b] = [aktorNaskah('kasubag'), aktorNaskah('wakil-dekan')];
    $n = app(AjukanParaf::class)->jalankan(drafBaru($p), $p, [$a->id, $b->id]);

    Livewire::actingAs($b)->test(KotakMasuk::class)->call('parafi', $n->id)->assertForbidden();

    Livewire::actingAs($a)->test(KotakMasuk::class)->set('catatanNaskah', '')->call('kembalikanNaskah', $n->id)->assertHasErrors('catatan');
    Livewire::actingAs($a)->test(KotakMasuk::class)->set('catatanNaskah', 'Revisi')->call('kembalikanNaskah', $n->id)->assertHasNoErrors();
    expect($n->fresh()->status)->toBe(StatusNaskah::Dikembalikan);
});

it('mengajukan paraf lewat aksi di halaman ubah naskah', function () {
    $p = aktorNaskah('admin-persuratan');
    $a = aktorNaskah('kasubag');
    $n = drafBaru($p);

    Livewire::actingAs($p)->test(EditNaskah::class, ['record' => $n->getKey()])
        ->callAction('ajukanParaf', ['pemaraf' => [['user_id' => $a->id]]]);

    expect($n->fresh()->status)->toBe(StatusNaskah::Paraf)->and($n->fresh()->paraf[0]->user_id)->toBe($a->id);
});
