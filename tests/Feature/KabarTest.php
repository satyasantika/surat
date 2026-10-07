<?php

use App\Actions\Kabar\AjukanKabar;
use App\Actions\Kabar\SimpanKabar;
use App\Actions\Kabar\TerbitkanKabar;
use App\Actions\Kabar\TolakKabar;
use App\Filament\Resources\Kabars\Pages\ManageKabars;
use App\Livewire\Ormawa\Kabar as KomponenKabar;
use App\Models\Kabar;
use App\Models\Ormawa;
use App\Models\PengurusOrmawa;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->withoutVite();
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel('admin');
});

/** @return array{0: Ormawa, 1: User} */
function ormawaDenganPengurus(string $nama = 'HIMA Kabar'): array
{
    $o = Ormawa::create(['nama' => $nama, 'tingkat' => 'prodi']);
    $o->sk()->create(['nomor_sk' => 'SK/1', 'tanggal_sk' => '2026-01-01', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2026-12-31']);
    $u = User::factory()->create(['nip_nim' => (string) random_int(1000000, 9999999)])->assignRole('pengurus-ormawa');
    PengurusOrmawa::create(['ormawa_id' => $o->id, 'user_id' => $u->id, 'nama' => $u->name, 'jabatan' => 'ketua']);

    return [$o, $u];
}

function adminKabar(): User
{
    $u = User::factory()->create()->assignRole('admin-persuratan');
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    return $u;
}

function dataKabar(Ormawa $o, array $tambahan = []): array
{
    return ['ormawa_id' => $o->id, 'judul' => 'Seminar Nasional', 'isi' => '<p>Isi kabar.</p>', ...$tambahan];
}

describe('penyimpanan', function () {
    it('membuang XSS dari isi dan mempertahankan format aman', function () {
        [$o, $u] = ormawaDenganPengurus();

        $k = app(SimpanKabar::class)->jalankan($u, dataKabar($o, ['isi' => '<p onclick="x()">Halo <strong>dunia</strong></p><script>alert(1)</script><img src=x onerror=alert(2)><iframe src="https://e.com"></iframe><a href="javascript:alert(3)">klik</a><a href="https://unsil.ac.id">ok</a>']));

        $isi = $k->fresh()->isi;
        expect($isi)->toContain('<strong>dunia</strong>')->toContain('https://unsil.ac.id')
            ->not->toContain('script')->not->toContain('onclick')->not->toContain('onerror')
            ->not->toContain('iframe')->not->toContain('<img')->not->toContain('javascript:');
    });

    it('membuat slug unik yang tetap saat judul diubah, berstatus draf, penulis = pelaku', function () {
        [$o, $u] = ormawaDenganPengurus();

        $a = app(SimpanKabar::class)->jalankan($u, dataKabar($o));
        $b = app(SimpanKabar::class)->jalankan($u, dataKabar($o));
        app(SimpanKabar::class)->jalankan($u, dataKabar($o, ['judul' => 'Judul baru']), $a);

        expect($a->slug)->toBe('seminar-nasional')->and($b->slug)->toBe('seminar-nasional-2')->and($a->fresh()->slug)->toBe('seminar-nasional')
            ->and($a->status)->toBe('draf')->and($a->penulis_id)->toBe($u->id);
    });

    it('memvalidasi isian dan menolak sampul dari domain tak diizinkan atau folder', function (array $tambahan, string $kolom) {
        [$o, $u] = ormawaDenganPengurus();

        expect(fn () => app(SimpanKabar::class)->jalankan($u, dataKabar($o, $tambahan)))->toThrow(ValidationException::class);
        expect(Kabar::count())->toBe(0);
    })->with([
        'judul kosong' => [['judul' => ''], 'judul'],
        'isi kosong' => [['isi' => ''], 'isi'],
        'sampul domain asing' => [['sampul' => 'https://evil.example.com/x.jpg'], 'sampul'],
        'sampul http' => [['sampul' => 'http://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view'], 'sampul'],
        'sampul folder' => [['sampul' => 'https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOpQr'], 'sampul'],
        'tag terlalu banyak' => [['tag' => array_fill(0, 11, 'a')], 'tag'],
    ]);

    it('menyimpan sampul Drive sebagai tautan foto dan menampilkannya lewat lh3', function () {
        [$o, $u] = ormawaDenganPengurus();

        $k = app(SimpanKabar::class)->jalankan($u, dataKabar($o, ['sampul' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view']));

        expect($k->tautan()->where('jenis', 'foto')->count())->toBe(1)
            ->and($k->fresh()->sampulUrl())->toBe('https://lh3.googleusercontent.com/d/1AbCdEfGhIjKlMnOpQr');

        app(SimpanKabar::class)->jalankan($u, dataKabar($o, ['sampul' => '']), $k);
        expect($k->tautan()->count())->toBe(0)->and($k->fresh()->sampulUrl())->toBeNull();
    });
});

describe('otorisasi', function () {
    it('pengurus hanya menulis untuk ormawa tempat ia aktif', function () {
        [$o, $u] = ormawaDenganPengurus();
        [$lain] = ormawaDenganPengurus('HIMA Lain');

        expect(fn () => app(SimpanKabar::class)->jalankan($u, dataKabar($lain)))->toThrow(ValidationException::class)
            ->and(fn () => app(SimpanKabar::class)->jalankan($u, ['ormawa_id' => null] + dataKabar($o)))->toThrow(ValidationException::class);
    });

    it('ormawa lain dan pengguna tanpa izin tidak dapat mengubah, mengajukan, atau menerbitkan', function () {
        [$o, $u] = ormawaDenganPengurus();
        [, $asing] = ormawaDenganPengurus('HIMA Lain');
        $k = app(SimpanKabar::class)->jalankan($u, dataKabar($o));

        expect(fn () => app(SimpanKabar::class)->jalankan($asing, dataKabar($o, ['judul' => 'Retas']), $k))->toThrow(AuthorizationException::class)
            ->and(fn () => app(AjukanKabar::class)->jalankan($k, $asing))->toThrow(AuthorizationException::class)
            ->and(fn () => app(TerbitkanKabar::class)->jalankan($k, $u))->toThrow(AuthorizationException::class)
            ->and(fn () => app(SimpanKabar::class)->jalankan(User::factory()->create()->assignRole('pegawai'), dataKabar($o)))->toThrow(AuthorizationException::class);
        expect($k->fresh()->judul)->toBe('Seminar Nasional');
    });

    it('pengurus tidak dapat mengubah kabar setelah diajukan atau terbit', function () {
        [$o, $u] = ormawaDenganPengurus();
        $k = app(SimpanKabar::class)->jalankan($u, dataKabar($o));
        app(AjukanKabar::class)->jalankan($k, $u);

        expect(fn () => app(SimpanKabar::class)->jalankan($u, dataKabar($o, ['judul' => 'X']), $k->fresh()))->toThrow(AuthorizationException::class)
            ->and(fn () => app(AjukanKabar::class)->jalankan($k, $u))->toThrow(AuthorizationException::class);
    });

    it('pengurus yang masa jabatannya berakhir kehilangan akses', function () {
        [$o, $u] = ormawaDenganPengurus();
        $k = app(SimpanKabar::class)->jalankan($u, dataKabar($o));
        $o->sk()->update(['periode_selesai' => '2020-01-01', 'periode_mulai' => '2019-01-01']);

        expect(fn () => app(SimpanKabar::class)->jalankan($u, dataKabar($o, ['judul' => 'X']), $k))->toThrow(AuthorizationException::class);
    });
});

describe('alur persetujuan', function () {
    it('draf → diajukan → terbit oleh admin dan hanya yang terbit tampil di scope publik', function () {
        [$o, $u] = ormawaDenganPengurus();
        $admin = adminKabar();
        $k = app(SimpanKabar::class)->jalankan($u, dataKabar($o));
        expect(Kabar::terbit()->count())->toBe(0);

        app(AjukanKabar::class)->jalankan($k, $u);
        expect($k->fresh()->status)->toBe('diajukan')->and(Kabar::terbit()->count())->toBe(0);

        app(TerbitkanKabar::class)->jalankan($k, $admin);
        $k->refresh();

        expect($k->status)->toBe('terbit')->and($k->disetujui_oleh)->toBe($admin->id)->and($k->terbit_pada)->not->toBeNull()
            ->and(Kabar::terbit()->pluck('id')->all())->toBe([$k->id]);
    });

    it('penolakan wajib bercatatan; penulis memperbaiki dan mengajukan ulang', function () {
        [$o, $u] = ormawaDenganPengurus();
        $admin = adminKabar();
        $k = app(SimpanKabar::class)->jalankan($u, dataKabar($o));
        app(AjukanKabar::class)->jalankan($k, $u);

        expect(fn () => app(TolakKabar::class)->jalankan($k, $admin, ''))->toThrow(ValidationException::class);
        app(TolakKabar::class)->jalankan($k, $admin, 'Tambahkan foto.');
        expect($k->fresh()->status)->toBe('ditolak')->and($k->fresh()->catatan_admin)->toBe('Tambahkan foto.');

        app(SimpanKabar::class)->jalankan($u, dataKabar($o, ['isi' => '<p>Revisi</p>']), $k->fresh());
        app(AjukanKabar::class)->jalankan($k->fresh(), $u);

        expect($k->fresh()->status)->toBe('diajukan')->and($k->fresh()->catatan_admin)->toBeNull();
    });

    it('tidak dapat menolak kabar yang belum diajukan atau menerbitkan ulang yang sudah terbit', function () {
        [$o, $u] = ormawaDenganPengurus();
        $admin = adminKabar();
        $k = app(SimpanKabar::class)->jalankan($u, dataKabar($o));

        expect(fn () => app(TolakKabar::class)->jalankan($k, $admin, 'x'))->toThrow(AuthorizationException::class);

        app(TerbitkanKabar::class)->jalankan($k, $admin);
        expect(fn () => app(TerbitkanKabar::class)->jalankan($k, $admin))->toThrow(AuthorizationException::class);
    });

    it('admin dapat membuat kabar fakultas tanpa ormawa', function () {
        $admin = adminKabar();

        $k = app(SimpanKabar::class)->jalankan($admin, ['judul' => 'Pengumuman Fakultas', 'isi' => '<p>Isi</p>']);
        app(TerbitkanKabar::class)->jalankan($k, $admin);

        expect($k->fresh()->ormawa_id)->toBeNull()->and($k->fresh()->status)->toBe('terbit');
    });
});

describe('antarmuka', function () {
    it('pengurus menyimpan draf, mengajukan, dan melihat catatan penolakan', function () {
        [$o, $u] = ormawaDenganPengurus();
        $admin = adminKabar();

        $t = Livewire::actingAs($u)->test(KomponenKabar::class, ['ormawa' => $o->id])
            ->set('judul', 'Kabar Baru')->set('isi', '<p>Isi</p>')->set('tag', 'seminar, nasional')->call('simpan')->assertHasNoErrors();
        $k = Kabar::firstOrFail();
        expect($k->status)->toBe('draf')->and($k->tag)->toBe(['seminar', 'nasional']);

        $t->call('ajukan', $k->id)->assertSee('Diajukan');
        app(TolakKabar::class)->jalankan($k->fresh(), $admin, 'Perbaiki judul.');

        Livewire::actingAs($u)->test(KomponenKabar::class, ['ormawa' => $o->id])->assertSee('Catatan admin: Perbaiki judul.')
            ->call('ubah', $k->id)->assertSet('judul', 'Kabar Baru');
    });

    it('menampilkan galat validasi dan menolak ormawa orang lain', function () {
        [$o, $u] = ormawaDenganPengurus();
        [$lain] = ormawaDenganPengurus('HIMA Lain');

        Livewire::actingAs($u)->test(KomponenKabar::class, ['ormawa' => $o->id])->call('simpanDanAjukan')->assertHasErrors(['judul', 'isi']);
        Livewire::actingAs($u)->test(KomponenKabar::class, ['ormawa' => $lain->id])->assertForbidden();
    });

    it('admin mengelola dari panel: membuat, menerbitkan, menolak; aksi tersembunyi sesuai status', function () {
        [$o, $u] = ormawaDenganPengurus();
        $admin = adminKabar();
        $k = app(SimpanKabar::class)->jalankan($u, dataKabar($o));

        $t = Livewire::actingAs($admin)->test(ManageKabars::class)->assertCanSeeTableRecords([$k]);
        $t->assertTableActionVisible('terbitkan', $k->fresh())->assertTableActionHidden('tolak', $k->fresh());

        app(AjukanKabar::class)->jalankan($k, $u);
        $t->callTableAction('tolak', $k->fresh(), ['catatan' => 'Kurang lengkap'])->assertNotified();
        expect($k->fresh()->status)->toBe('ditolak');

        app(AjukanKabar::class)->jalankan($k->fresh(), $u);
        Livewire::actingAs($admin)->test(ManageKabars::class)->callTableAction('terbitkan', $k->fresh())->assertNotified();
        expect($k->fresh()->status)->toBe('terbit');

        Livewire::actingAs($admin)->test(ManageKabars::class)->assertTableActionHidden('terbitkan', $k->fresh())->assertTableActionHidden('tolak', $k->fresh())
            ->callAction(TestAction::make(CreateAction::class), ['judul' => 'Dari Admin', 'isi' => '<p>x</p>'])->assertHasNoActionErrors();
        expect(Kabar::where('judul', 'Dari Admin')->value('ormawa_id'))->toBeNull();
    });

    it('pengurus tidak dapat membuka panel kabar', function () {
        [, $u] = ormawaDenganPengurus();

        $this->actingAs($u)->get('/admin/kabar')->assertForbidden();
    });
});
