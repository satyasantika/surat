<?php

use App\Actions\Ormawa\TautkanAkunPengurus;
use App\Filament\Resources\Ormawas\Pages\EditOrmawa;
use App\Filament\Resources\Ormawas\PengurusRelationManager;
use App\Models\Aktivitas;
use App\Models\Ormawa;
use App\Models\PengurusOrmawa;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel('admin');
});

function ormawaDenganSk(string $nama = 'HIMA Mat', string $mulai = '2026-01-01', string $selesai = '2026-12-31'): Ormawa
{
    $o = Ormawa::create(['nama' => $nama, 'tingkat' => 'prodi']);
    $o->sk()->create(['nomor_sk' => "SK/{$nama}", 'tanggal_sk' => $mulai, 'periode_mulai' => $mulai, 'periode_selesai' => $selesai]);

    return $o;
}

function pengurusUntuk(Ormawa $o, string $jabatan, ?User $user = null, array $ubah = []): PengurusOrmawa
{
    return PengurusOrmawa::create($ubah + [
        'ormawa_id' => $o->id, 'user_id' => $user?->id, 'nama' => $user?->name ?? 'Tanpa Akun', 'nim' => $user?->nip_nim, 'jabatan' => $jabatan,
    ]);
}

function mahasiswa(?string $nim = null, array $ubah = []): User
{
    return User::factory()->create($ubah + ['nip_nim' => $nim ?? (string) random_int(2000000, 2999999)])->assignRole('pengurus-ormawa');
}

beforeEach(fn () => CarbonImmutable::setTestNow('2026-06-15 10:00:00'));
afterEach(fn () => CarbonImmutable::setTestNow());

describe('ormawaAktif', function () {
    it('memuat ormawa pengurus aktif dengan SK berlaku', function () {
        $o = ormawaDenganSk();
        $u = mahasiswa();
        pengurusUntuk($o, 'anggota', $u);

        expect($u->ormawaAktif()->pluck('id')->all())->toBe([$o->id]);
    });

    it('kosong bila ormawa tidak punya SK berlaku', function (string $mulai, string $selesai) {
        $o = ormawaDenganSk('Tanpa SK', $mulai, $selesai);
        $u = mahasiswa();
        pengurusUntuk($o, 'ketua', $u);

        expect($u->ormawaAktif())->toBeEmpty()->and($u->dapatMengelolaOrmawa($o))->toBeFalse();
    })->with([['2024-01-01', '2024-12-31'], ['2027-01-01', '2027-12-31']]);

    it('kosong bila ormawa tidak punya SK sama sekali', function () {
        $o = Ormawa::create(['nama' => 'Tanpa SK Sama Sekali', 'tingkat' => 'ukm']);
        $u = mahasiswa();
        pengurusUntuk($o, 'ketua', $u);

        expect($u->ormawaAktif())->toBeEmpty();
    });

    it('kosong bila masa jabatan pengurus belum mulai, sudah berakhir, atau ormawa nonaktif', function (array $ubah) {
        $o = ormawaDenganSk();
        $u = mahasiswa();
        pengurusUntuk($o, 'ketua', $u, $ubah);
        if (isset($ubah['_nonaktif'])) {
            $o->update(['aktif' => false]);
        }

        expect($u->fresh()->ormawaAktif())->toBeEmpty();
    })->with([
        'belum mulai' => [['mulai' => '2026-07-01']],
        'sudah berakhir' => [['selesai' => '2026-06-14']],
    ]);

    it('kosong untuk ormawa nonaktif', function () {
        $o = ormawaDenganSk();
        $u = mahasiswa();
        pengurusUntuk($o, 'ketua', $u);
        $o->update(['aktif' => false]);

        expect($u->fresh()->ormawaAktif())->toBeEmpty();
    });

    it('memakai SK yang dikaitkan pengurus: SK kedaluwarsa membuat tidak aktif walau ormawa punya SK lain', function () {
        $o = ormawaDenganSk('Dua SK', '2026-01-01', '2026-12-31');
        $lama = $o->sk()->create(['nomor_sk' => 'SK/lama', 'tanggal_sk' => '2025-01-01', 'periode_mulai' => '2025-01-01', 'periode_selesai' => '2025-12-31']);
        $terkait = mahasiswa();
        $takTerkait = mahasiswa();
        pengurusUntuk($o, 'anggota', $terkait, ['sk_kepengurusan_id' => $lama->id]);
        pengurusUntuk($o, 'anggota', $takTerkait);

        expect($terkait->ormawaAktif())->toBeEmpty()->and($takTerkait->ormawaAktif())->toHaveCount(1);
    });

    it('mendukung banyak ormawa dan tidak memuat ormawa orang lain', function () {
        $a = ormawaDenganSk('A');
        $b = ormawaDenganSk('B');
        $u = mahasiswa();
        pengurusUntuk($a, 'anggota', $u);
        pengurusUntuk($b, 'bendahara', $u);
        pengurusUntuk(ormawaDenganSk('C'), 'ketua', mahasiswa());

        expect($u->ormawaAktif()->pluck('nama')->sort()->values()->all())->toBe(['A', 'B']);
    });

    it('tidak mengikutkan pengurus yang belum punya akun', function () {
        $o = ormawaDenganSk();
        pengurusUntuk($o, 'ketua');

        expect(mahasiswa()->ormawaAktif())->toBeEmpty();
    });
});

describe('dapatMengelolaOrmawa dan kebijakan', function () {
    it('hanya ketua dan sekretaris aktif yang dapat mengelola ormawanya sendiri', function (string $jabatan, bool $boleh) {
        $o = ormawaDenganSk();
        $lain = ormawaDenganSk('Lain');
        $u = mahasiswa();
        pengurusUntuk($o, $jabatan, $u);

        expect($u->dapatMengelolaOrmawa($o))->toBe($boleh)->and($u->dapatMengelolaOrmawa($lain))->toBeFalse()
            ->and($u->can('update', $o))->toBe($boleh)->and($u->can('update', $lain))->toBeFalse()
            ->and($u->can('view', $o))->toBeTrue()->and($u->can('view', $lain))->toBeFalse();
    })->with([['ketua', true], ['sekretaris', true], ['wakil_ketua', false], ['bendahara', false], ['anggota', false], ['lainnya', false]]);

    it('tidak mengelola tanpa izin ormawa.kelola-sendiri', function () {
        $o = ormawaDenganSk();
        $u = User::factory()->create(['nip_nim' => '2111111'])->assignRole('pegawai');
        pengurusUntuk($o, 'ketua', $u);

        expect($u->dapatMengelolaOrmawa($o))->toBeFalse();
    });

    it('menerapkan hak data pribadi per peran', function () {
        $o = ormawaDenganSk();
        $lain = ormawaDenganSk('Lain');
        $ketua = mahasiswa();
        $bendahara = mahasiswa();
        $anggota = mahasiswa();
        $ketuaLain = mahasiswa();
        pengurusUntuk($o, 'ketua', $ketua);
        pengurusUntuk($o, 'bendahara', $bendahara);
        $target = pengurusUntuk($o, 'anggota', $anggota, ['telepon' => '0812']);
        pengurusUntuk($lain, 'ketua', $ketuaLain);
        $pembina = User::factory()->create()->assignRole('pembina-ormawa');
        $o->update(['pembina_user_id' => $pembina->id]);

        $cek = fn (User $u) => $u->can('lihatDataPribadi', $target);

        expect($cek($anggota))->toBeTrue('diri sendiri')
            ->and($cek($ketua))->toBeTrue('ketua')
            ->and($cek(User::factory()->create()->assignRole('admin-persuratan')))->toBeTrue('admin')
            ->and($cek($pembina))->toBeTrue('pembina binaan')
            ->and($cek($bendahara))->toBeFalse('bendahara')
            ->and($cek($ketuaLain))->toBeFalse('ketua ormawa lain')
            ->and($cek(User::factory()->create()->assignRole('dekan')))->toBeFalse('dekan')
            ->and($cek(User::factory()->create()->assignRole('pembina-ormawa')))->toBeFalse('pembina lain');
    });

    it('membatasi pengubahan pengurus ke yang berhak atas ormawanya', function () {
        $o = ormawaDenganSk();
        $ketua = mahasiswa();
        $bendahara = mahasiswa();
        $ketuaLain = mahasiswa();
        pengurusUntuk($o, 'ketua', $ketua);
        pengurusUntuk($o, 'bendahara', $bendahara);
        pengurusUntuk(ormawaDenganSk('Lain'), 'ketua', $ketuaLain);
        $target = pengurusUntuk($o, 'anggota', null, ['nama' => 'Anggota']);

        expect($ketua->can('update', $target))->toBeTrue()->and($ketua->can('delete', $target))->toBeTrue()
            ->and($bendahara->can('update', $target))->toBeFalse()
            ->and($ketuaLain->can('update', $target))->toBeFalse()->and($ketuaLain->can('view', $target))->toBeFalse();
    });
});

describe('penyimpanan data pribadi', function () {
    it('mengenkripsi telepon, menyimpan NIM polos untuk penautan, dan menyembunyikan keduanya dari serialisasi', function () {
        $o = ormawaDenganSk();
        $p = pengurusUntuk($o, 'anggota', null, ['nama' => 'A', 'nim' => '2012345', 'telepon' => '081234567890']);

        $mentah = DB::table('pengurus_ormawa')->where('id', $p->id)->first();

        expect($mentah->telepon)->not->toContain('081234567890')->and($mentah->nim)->toBe('2012345')
            ->and($p->fresh()->telepon)->toBe('081234567890')
            ->and(PengurusOrmawa::where('nim', '2012345')->count())->toBe(1)
            ->and($p->fresh()->toArray())->not->toHaveKey('telepon')->not->toHaveKey('nim');
    });

    it('tidak mencatat telepon polos di log aktivitas', function () {
        $o = ormawaDenganSk();
        pengurusUntuk($o, 'anggota', null, ['nama' => 'A', 'telepon' => '081299998888']);

        $p = pengurusUntuk($o, 'anggota', null, ['nama' => 'B', 'nim' => '2012345', 'telepon' => '081211112222']);
        $p->update(['telepon' => '081233334444', 'nim' => '2012346']);
        $log = Aktivitas::where('log_name', 'pengurus-ormawa')->get()->map(fn ($a) => json_encode([$a->properties, $a->attribute_changes]))->implode('');

        expect($log)->not->toContain('081299998888')->not->toContain('081211112222')->not->toContain('081233334444')->not->toContain('2012346');
        expect($log)->not->toContain('2012345');
    });
});

describe('validasi model', function () {
    it('menolak jabatan tak dikenal, SK ormawa lain, masa jabatan terbalik, dan akun ganda', function () {
        $o = ormawaDenganSk();
        $lain = ormawaDenganSk('Lain');
        $skLain = $lain->sk()->first();
        $u = mahasiswa();

        expect(fn () => pengurusUntuk($o, 'raja'))->toThrow(ValidationException::class)
            ->and(fn () => pengurusUntuk($o, 'anggota', null, ['sk_kepengurusan_id' => $skLain->id]))->toThrow(ValidationException::class)
            ->and(fn () => pengurusUntuk($o, 'anggota', null, ['mulai' => '2026-05-01', 'selesai' => '2026-04-01']))->toThrow(ValidationException::class);

        pengurusUntuk($o, 'anggota', $u);
        expect(fn () => pengurusUntuk($o, 'bendahara', $u))->toThrow(QueryException::class);
    });
});

describe('TautkanAkunPengurus', function () {
    it('menautkan akun berNIM sama dan memberi peran pengurus-ormawa', function () {
        $o = ormawaDenganSk();
        $akun = User::factory()->create(['nip_nim' => '2255555']);
        $p = pengurusUntuk($o, 'anggota', null, ['nama' => 'Calon', 'nim' => '2255555']);
        $admin = User::factory()->create()->assignRole('admin-persuratan');

        app(TautkanAkunPengurus::class)->jalankan($p, $admin);

        expect($p->fresh()->user_id)->toBe($akun->id)->and($akun->fresh()->hasRole('pengurus-ormawa'))->toBeTrue()
            ->and(Aktivitas::where('log_name', 'pengurus-ormawa')->where('event', 'tautkan')->exists())->toBeTrue();
    });

    it('menolak bila tidak ada akun cocok, belum verifikasi, nonaktif, NIM kosong, atau sudah tertaut', function (Closure $susun) {
        $o = ormawaDenganSk();
        $admin = User::factory()->create()->assignRole('admin-persuratan');
        $p = $susun($o);

        expect(fn () => app(TautkanAkunPengurus::class)->jalankan($p, $admin))->toThrow(ValidationException::class);
        expect($p->fresh()->user_id)->toBe($p->user_id);
    })->with([
        'tak ada akun' => [fn (Ormawa $o) => pengurusUntuk($o, 'anggota', null, ['nim' => '9999999'])],
        'belum verifikasi' => [function (Ormawa $o) {
            User::factory()->create(['nip_nim' => '2300001', 'email_verified_at' => null]);

            return pengurusUntuk($o, 'anggota', null, ['nim' => '2300001']);
        }],
        'nonaktif' => [function (Ormawa $o) {
            User::factory()->create(['nip_nim' => '2300002', 'aktif' => false]);

            return pengurusUntuk($o, 'anggota', null, ['nim' => '2300002']);
        }],
        'nim kosong' => [fn (Ormawa $o) => pengurusUntuk($o, 'anggota', null, ['nim' => null])],
        'sudah tertaut' => [fn (Ormawa $o) => pengurusUntuk($o, 'anggota', mahasiswa('2300003'))],
    ]);

    it('menolak akun yang sudah menjadi pengurus lain di ormawa yang sama', function () {
        $o = ormawaDenganSk();
        $akun = mahasiswa('2400001');
        pengurusUntuk($o, 'anggota', $akun);
        $kembar = pengurusUntuk($o, 'bendahara', null, ['nama' => 'Dobel', 'nim' => '2400001']);

        expect(fn () => app(TautkanAkunPengurus::class)->jalankan($kembar, User::factory()->create()->assignRole('admin-persuratan')))->toThrow(ValidationException::class);
    });

    it('hanya admin atau ketua/sekretaris ormawa yang bersangkutan yang boleh menautkan', function (string $pelaku, bool $boleh) {
        $o = ormawaDenganSk();
        $lain = ormawaDenganSk('Lain');
        User::factory()->create(['nip_nim' => '2500001']);
        $p = pengurusUntuk($o, 'anggota', null, ['nim' => '2500001']);

        $aktor = match ($pelaku) {
            'admin' => User::factory()->create()->assignRole('admin-persuratan'),
            'ketua' => tap(mahasiswa(), fn ($u) => pengurusUntuk($o, 'ketua', $u)),
            'sekretaris' => tap(mahasiswa(), fn ($u) => pengurusUntuk($o, 'sekretaris', $u)),
            'bendahara' => tap(mahasiswa(), fn ($u) => pengurusUntuk($o, 'bendahara', $u)),
            'ketua lain' => tap(mahasiswa(), fn ($u) => pengurusUntuk($lain, 'ketua', $u)),
            'pegawai' => User::factory()->create()->assignRole('pegawai'),
        };

        $aksi = fn () => app(TautkanAkunPengurus::class)->jalankan($p, $aktor);

        $boleh ? expect($aksi()->user_id)->not->toBeNull() : expect($aksi)->toThrow(AuthorizationException::class);
    })->with([['admin', true], ['ketua', true], ['sekretaris', true], ['bendahara', false], ['ketua lain', false], ['pegawai', false]]);

    it('menautkan semua yang cocok dan melewati yang belum punya akun', function () {
        $o = ormawaDenganSk();
        User::factory()->create(['nip_nim' => '2600001']);
        User::factory()->create(['nip_nim' => '2600002']);
        pengurusUntuk($o, 'anggota', null, ['nim' => '2600001']);
        pengurusUntuk($o, 'anggota', null, ['nim' => '2600002']);
        pengurusUntuk($o, 'anggota', null, ['nim' => '2600003']);

        $jumlah = app(TautkanAkunPengurus::class)->semuaDiOrmawa($o, User::factory()->create()->assignRole('admin-persuratan'));

        expect($jumlah)->toBe(2)->and($o->pengurus()->whereNotNull('user_id')->count())->toBe(2);
    });
});

describe('relation manager pengurus', function () {
    it('menampilkan data pribadi bagi admin dan menyembunyikannya bagi peninjau', function () {
        $o = ormawaDenganSk();
        pengurusUntuk($o, 'anggota', null, ['nama' => 'Siti Pengurus', 'nim' => '2700001', 'telepon' => '081277776666']);
        $admin = User::factory()->create()->assignRole('admin-persuratan');
        $dekan = User::factory()->create()->assignRole('dekan');

        Livewire::actingAs($admin)->test(PengurusRelationManager::class, ['ownerRecord' => $o, 'pageClass' => EditOrmawa::class])
            ->assertSee('Siti Pengurus')->assertSee('2700001')->assertSee('081277776666');

        Livewire::actingAs($dekan)->test(PengurusRelationManager::class, ['ownerRecord' => $o, 'pageClass' => EditOrmawa::class])
            ->assertSee('Siti Pengurus')->assertDontSee('2700001')->assertDontSee('081277776666')->assertSee('disembunyikan');
    });

    it('menautkan akun lewat aksi baris', function () {
        $o = ormawaDenganSk();
        $akun = User::factory()->create(['nip_nim' => '2800001']);
        $p = pengurusUntuk($o, 'anggota', null, ['nim' => '2800001']);
        $admin = User::factory()->create()->assignRole('admin-persuratan');

        Livewire::actingAs($admin)->test(PengurusRelationManager::class, ['ownerRecord' => $o, 'pageClass' => EditOrmawa::class])
            ->callTableAction('tautkan', $p);

        expect($p->fresh()->user_id)->toBe($akun->id);
    });
});
