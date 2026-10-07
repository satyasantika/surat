<?php

use App\Actions\Permohonan\AjukanPermohonan;
use App\Actions\Permohonan\AjukanUlangPermohonan;
use App\Actions\Permohonan\BatalkanPermohonan;
use App\Actions\Permohonan\KembalikanPermohonan;
use App\Actions\Permohonan\SetujuiPembina;
use App\Actions\Permohonan\TolakPermohonan;
use App\Actions\Permohonan\TransisiPermohonan;
use App\Actions\Permohonan\ValidasiPermohonan;
use App\Enums\StatusPermohonan;
use App\Exceptions\TransisiTidakSah;
use App\Filament\Resources\Permohonans\Pages\ListPermohonans;
use App\Filament\Resources\Permohonans\Pages\ViewPermohonan;
use App\Filament\Resources\Permohonans\PermohonanRiwayatRelationManager;
use App\Models\JenisPermohonan;
use App\Models\Ormawa;
use App\Models\PengurusOrmawa;
use App\Models\Permohonan;
use App\Models\PermohonanRuangan;
use App\Models\RuanganLokal;
use App\Models\User;
use App\Support\AlurPermohonan;
use App\Support\Pengaturan;
use Carbon\CarbonImmutable;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\JenisPermohonanSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->seed([PeranDanIzinSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, JenisPermohonanSeeder::class]);
    Filament::setCurrentPanel('admin');
    CarbonImmutable::setTestNow('2026-06-01 09:00:00');
    RuanganLokal::create(['kode' => 'A101', 'nama' => 'Ruang A101']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function ormawaAlur(string $nama = 'HIMA Mat', bool $pembina = false): array
{
    $o = Ormawa::create(['nama' => $nama, 'tingkat' => 'prodi']);
    $o->sk()->create(['nomor_sk' => "SK/{$nama}", 'tanggal_sk' => '2026-01-01', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2026-12-31']);
    $u = User::factory()->create(['nip_nim' => (string) random_int(7000000, 7999999)])->assignRole('pengurus-ormawa');
    PengurusOrmawa::create(['ormawa_id' => $o->id, 'user_id' => $u->id, 'nama' => $u->name, 'jabatan' => 'ketua']);
    RateLimiter::clear('ajukan-permohonan:'.$u->id);
    $b = null;

    if ($pembina) {
        $b = User::factory()->create()->assignRole('pembina-ormawa');
        $o->update(['pembina_user_id' => $b->id]);
    }

    return [$o, $u, $b];
}

function ajukanAlur(Ormawa $o, User $u, array $ubah = [], string $jenis = 'kegiatan-ruangan'): Permohonan
{
    return app(AjukanPermohonan::class)->jalankan($u, $o, JenisPermohonan::firstWhere('kode', $jenis), $ubah + [
        'nama_kegiatan' => 'Seminar', 'perihal' => 'Izin', 'tanggal_mulai' => '2026-06-20', 'tanggal_selesai' => '2026-06-20', 'deskripsi' => 'Deskripsi',
        'penanggung_jawab' => ['ketua' => ['nama' => 'Budi']],
        'berkas' => ['surat_permohonan' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view', 'proposal' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view'],
        'ruangan' => [['kode' => 'A101', 'tanggal' => '2026-06-20', 'sesi' => 'pagi']],
    ]);
}

function adminAlur(): User
{
    $u = User::factory()->create()->assignRole('admin-persuratan');
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    return $u;
}

function paksaStatus(Permohonan $p, StatusPermohonan $s): Permohonan
{
    $p->forceFill(['status' => $s])->saveQuietly();

    return $p->fresh();
}

describe('peta transisi', function () {
    it('mendefinisikan transisi sah dan menolak seluruh sisanya', function () {
        $sah = [
            'diajukan' => ['persetujuan_pembina', 'validasi_admin'],
            'persetujuan_pembina' => ['validasi_admin', 'dikembalikan', 'ditolak', 'dibatalkan'],
            'validasi_admin' => ['disposisi_dekan', 'dikembalikan', 'ditolak', 'dibatalkan'],
            'dikembalikan' => ['validasi_admin', 'dibatalkan'],
            'disposisi_dekan' => ['persetujuan_wd', 'ditolak', 'dibatalkan'],
            'persetujuan_wd' => ['rekomendasi_kasubag', 'ditolak', 'dibatalkan'],
            'rekomendasi_kasubag' => ['penerbitan', 'ditolak', 'dibatalkan'],
            'penerbitan' => ['selesai', 'dibatalkan'],
            'selesai' => [], 'ditolak' => [], 'dibatalkan' => [],
        ];

        foreach (StatusPermohonan::cases() as $dari) {
            foreach (StatusPermohonan::cases() as $ke) {
                expect(AlurPermohonan::boleh($dari, $ke))->toBe(in_array($ke->value, $sah[$dari->value], true), "{$dari->value} → {$ke->value}");
            }
        }
    });

    it('status akhir tidak punya tujuan dan melepas ruangan hanya untuk ditolak/dibatalkan', function () {
        foreach ([StatusPermohonan::Selesai, StatusPermohonan::Ditolak, StatusPermohonan::Dibatalkan] as $s) {
            expect(AlurPermohonan::tujuan($s))->toBeEmpty()->and($s->akhir())->toBeTrue();
        }

        expect(AlurPermohonan::menahanRuangan(StatusPermohonan::Ditolak))->toBeFalse()->and(AlurPermohonan::menahanRuangan(StatusPermohonan::Dibatalkan))->toBeFalse()
            ->and(AlurPermohonan::menahanRuangan(StatusPermohonan::Selesai))->toBeTrue()->and(AlurPermohonan::menahanRuangan(StatusPermohonan::ValidasiAdmin))->toBeTrue();
    });

    it('transisi tidak sah melempar TransisiTidakSah dan tidak mengubah apa pun', function () {
        [$o, $u] = ormawaAlur();
        $p = ajukanAlur($o, $u);

        expect(fn () => app(TransisiPermohonan::class)->dalamKunci($p, fn (Permohonan $s) => app(TransisiPermohonan::class)->ke($s, StatusPermohonan::Selesai, $u)))
            ->toThrow(TransisiTidakSah::class);
        expect($p->fresh()->status)->toBe(StatusPermohonan::ValidasiAdmin)->and($p->fresh()->riwayat)->toHaveCount(2);
    });
});

describe('persetujuan pembina dan validasi admin', function () {
    it('pembina ormawa itu menyetujui lalu permohonan ke validasi admin', function () {
        Pengaturan::set('persetujuan_pembina_aktif', true);
        [$o, $u, $pembina] = ormawaAlur(pembina: true);
        $p = ajukanAlur($o, $u);
        expect($p->status)->toBe(StatusPermohonan::PersetujuanPembina);

        app(SetujuiPembina::class)->jalankan($p, $pembina, 'Setuju');

        $p = $p->fresh();
        expect($p->status)->toBe(StatusPermohonan::ValidasiAdmin)->and($p->riwayat->last()->catatan)->toBe('Setuju');
    });

    it('menolak pembina lain, admin, atau status yang tidak sesuai pada persetujuan pembina', function () {
        Pengaturan::set('persetujuan_pembina_aktif', true);
        [$o, $u] = ormawaAlur(pembina: true);
        $p = ajukanAlur($o, $u);
        $pembinaLain = User::factory()->create()->assignRole('pembina-ormawa');

        expect(fn () => app(SetujuiPembina::class)->jalankan($p, $pembinaLain))->toThrow(AuthorizationException::class)
            ->and(fn () => app(SetujuiPembina::class)->jalankan($p, adminAlur()))->toThrow(AuthorizationException::class);

        $p->forceFill(['status' => StatusPermohonan::ValidasiAdmin])->saveQuietly();
        expect(fn () => app(SetujuiPembina::class)->jalankan($p->fresh(), $o->pembina))->toThrow(ValidationException::class);
    });

    it('admin memvalidasi: ke disposisi dekan, hanya pemegang permohonan.validasi', function () {
        [$o, $u] = ormawaAlur();
        $p = ajukanAlur($o, $u);

        expect(fn () => app(ValidasiPermohonan::class)->jalankan($p, User::factory()->create()->assignRole('kasubag')))->toThrow(AuthorizationException::class)
            ->and(fn () => app(ValidasiPermohonan::class)->jalankan($p, $u))->toThrow(AuthorizationException::class);

        app(ValidasiPermohonan::class)->jalankan($p, adminAlur());
        expect($p->fresh()->status)->toBe(StatusPermohonan::DisposisiDekan);
    });

    it('menolak klik ganda: validasi kedua kali gagal tanpa riwayat tambahan', function () {
        [$o, $u] = ormawaAlur();
        $p = ajukanAlur($o, $u);
        $admin = adminAlur();

        app(ValidasiPermohonan::class)->jalankan($p, $admin);
        expect(fn () => app(ValidasiPermohonan::class)->jalankan($p, $admin))->toThrow(ValidationException::class);

        expect($p->fresh()->riwayat->where('ke_status', 'disposisi_dekan'))->toHaveCount(1);
    });
});

describe('pengembalian dan ajukan ulang', function () {
    it('mewajibkan catatan, mempertahankan ruangan, lalu ormawa mengajukan ulang', function () {
        [$o, $u] = ormawaAlur();
        $p = ajukanAlur($o, $u);
        $admin = adminAlur();

        expect(fn () => app(KembalikanPermohonan::class)->jalankan($p, $admin, '  '))->toThrow(ValidationException::class);

        app(KembalikanPermohonan::class)->jalankan($p, $admin, 'Lengkapi proposal');
        $p = $p->fresh();

        expect($p->status)->toBe(StatusPermohonan::Dikembalikan)->and($p->ruangan->pluck('status')->all())->toBe(['ditahan'])
            ->and($p->riwayat->last()->catatan)->toBe('Lengkapi proposal');

        app(AjukanUlangPermohonan::class)->jalankan($p, $u, [
            'nama_kegiatan' => 'Seminar (revisi)', 'deskripsi' => 'Deskripsi lengkap',
            'berkas' => ['proposal' => 'https://drive.google.com/file/d/1NewProposalXXXXXXXX/view'],
        ], 'Proposal sudah dilengkapi');

        $p = $p->fresh();
        expect($p->status)->toBe(StatusPermohonan::ValidasiAdmin)->and($p->nama_kegiatan)->toBe('Seminar (revisi)')
            ->and($p->tautan->firstWhere('jenis', 'proposal')->url)->toContain('1NewProposalXXXXXXXX')
            ->and($p->tautan->where('jenis', 'proposal'))->toHaveCount(1)
            ->and($p->riwayat->map(fn ($r) => $r->ke_status)->all())->toBe(['diajukan', 'validasi_admin', 'dikembalikan', 'validasi_admin']);
    });

    it('pembina dapat mengembalikan pada tahapnya sendiri dan admin tidak pada tahap pembina', function () {
        Pengaturan::set('persetujuan_pembina_aktif', true);
        [$o, $u, $pembina] = ormawaAlur(pembina: true);
        $p = ajukanAlur($o, $u);

        expect(fn () => app(KembalikanPermohonan::class)->jalankan($p, adminAlur(), 'x'))->toThrow(AuthorizationException::class);

        app(KembalikanPermohonan::class)->jalankan($p, $pembina, 'Perbaiki dulu');
        expect($p->fresh()->status)->toBe(StatusPermohonan::Dikembalikan);
    });

    it('hanya pengurus aktif ormawa itu yang mengajukan ulang, dan menolak berkas tak sah', function () {
        [$o, $u] = ormawaAlur();
        [, $uLain] = ormawaAlur('Lain');
        $p = ajukanAlur($o, $u);
        app(KembalikanPermohonan::class)->jalankan($p, adminAlur(), 'Revisi');
        $p = $p->fresh();

        expect(fn () => app(AjukanUlangPermohonan::class)->jalankan($p, $uLain))->toThrow(AuthorizationException::class)
            ->and(fn () => app(AjukanUlangPermohonan::class)->jalankan($p, adminAlur()))->toThrow(AuthorizationException::class)
            ->and(fn () => app(AjukanUlangPermohonan::class)->jalankan($p, $u, ['berkas' => ['proposal' => 'https://evil.example.com/p.pdf']]))->toThrow(ValidationException::class)
            ->and(fn () => app(AjukanUlangPermohonan::class)->jalankan($p, $u, ['nama_kegiatan' => '']))->toThrow(ValidationException::class);
        expect($p->fresh()->status)->toBe(StatusPermohonan::Dikembalikan);
    });

    it('tidak dapat mengajukan ulang permohonan yang tidak dikembalikan', function () {
        [$o, $u] = ormawaAlur();
        $p = ajukanAlur($o, $u);

        expect(fn () => app(AjukanUlangPermohonan::class)->jalankan($p, $u))->toThrow(ValidationException::class);
    });
});

describe('penolakan dan ruangan dilepas', function () {
    it('menolak dengan alasan wajib dan melepas ruangan sehingga dapat dipesan ormawa lain', function () {
        [$o, $u] = ormawaAlur();
        [$o2, $u2] = ormawaAlur('Kedua');
        $p = ajukanAlur($o, $u);

        expect(fn () => ajukanAlur($o2, $u2))->toThrow(ValidationException::class);
        expect(fn () => app(TolakPermohonan::class)->jalankan($p, adminAlur(), ' '))->toThrow(ValidationException::class);

        app(TolakPermohonan::class)->jalankan($p, adminAlur(), 'Jadwal bentrok dengan agenda fakultas');
        $p = $p->fresh();

        expect($p->status)->toBe(StatusPermohonan::Ditolak)->and($p->ruangan->pluck('status')->all())->toBe(['dilepas']);
        expect(ajukanAlur($o2, $u2)->ruangan)->toHaveCount(1);
    });

    it('tidak melepas ruangan yang sudah dikonfirmasi dan menolak transisi dari status akhir', function () {
        [$o, $u] = ormawaAlur();
        $p = ajukanAlur($o, $u);
        $p->ruangan()->update(['status' => PermohonanRuangan::DIKONFIRMASI]);

        app(TolakPermohonan::class)->jalankan($p, adminAlur(), 'Alasan');

        expect($p->fresh()->ruangan->pluck('status')->all())->toBe(['dikonfirmasi']);
        expect(fn () => app(ValidasiPermohonan::class)->jalankan($p->fresh(), adminAlur()))->toThrow(ValidationException::class)
            ->and(fn () => app(TolakPermohonan::class)->jalankan($p->fresh(), adminAlur(), 'Lagi'))->toThrow(ValidationException::class);
    });

    it('hanya pihak berwenang pada tahapnya yang menolak', function () {
        [$o, $u] = ormawaAlur();
        $p = ajukanAlur($o, $u);

        expect(fn () => app(TolakPermohonan::class)->jalankan($p, $u, 'x'))->toThrow(AuthorizationException::class)
            ->and(fn () => app(TolakPermohonan::class)->jalankan($p, User::factory()->create()->assignRole('dekan'), 'x'))->toThrow(AuthorizationException::class);
        expect($p->fresh()->status)->toBe(StatusPermohonan::ValidasiAdmin);
    });
});

describe('pembatalan oleh ormawa', function () {
    it('pengaju atau ketua membatalkan dan ruangan dilepas', function () {
        [$o, $u] = ormawaAlur();
        $p = ajukanAlur($o, $u);

        app(BatalkanPermohonan::class)->jalankan($p, $u, 'Kegiatan ditunda');

        expect($p->fresh()->status)->toBe(StatusPermohonan::Dibatalkan)->and($p->fresh()->ruangan->pluck('status')->all())->toBe(['dilepas']);
    });

    it('menolak pembatalan oleh orang lain, tanpa alasan, saat penerbitan, atau status akhir', function () {
        [$o, $u] = ormawaAlur();
        [, $uLain] = ormawaAlur('Lain');
        $p = ajukanAlur($o, $u);

        expect(fn () => app(BatalkanPermohonan::class)->jalankan($p, $uLain, 'x'))->toThrow(AuthorizationException::class)
            ->and(fn () => app(BatalkanPermohonan::class)->jalankan($p, $u, ''))->toThrow(ValidationException::class);

        $penerbitan = paksaStatus($p, StatusPermohonan::Penerbitan);
        expect(fn () => app(BatalkanPermohonan::class)->jalankan($penerbitan, $u, 'x'))->toThrow(AuthorizationException::class);

        $selesai = paksaStatus($p, StatusPermohonan::Selesai);
        expect(fn () => app(BatalkanPermohonan::class)->jalankan($selesai, $u, 'x'))->toThrow(AuthorizationException::class);
    });
});

describe('panel permohonan', function () {
    it('membatasi akses, menampilkan tab per status, dan hanya binaan bagi pembina', function () {
        [$o, $u, $pembina] = ormawaAlur(pembina: true);
        [$lain, $uLain] = ormawaAlur('Lain');
        $p1 = ajukanAlur($o, $u);
        $p2 = paksaStatus(ajukanAlur($lain, $uLain, ['ruangan' => [['kode' => 'A101', 'tanggal' => '2026-06-20', 'sesi' => 'siang']]]), StatusPermohonan::Ditolak);
        $pembina->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

        $this->actingAs(adminAlur())->get('/admin/permohonan')->assertOk();
        $this->actingAs(User::factory()->create()->assignRole('pegawai'))->get('/admin/permohonan')->assertForbidden();

        Livewire::actingAs(adminAlur())->test(ListPermohonans::class)
            ->assertCanSeeTableRecords([$p1, $p2])
            ->set('activeTab', 'ditolak')->assertCanSeeTableRecords([$p2])->assertCanNotSeeTableRecords([$p1])
            ->set('activeTab', 'validasi_admin')->assertCanSeeTableRecords([$p1])->assertCanNotSeeTableRecords([$p2]);

        Livewire::actingAs($pembina)->test(ListPermohonans::class)->assertCanSeeTableRecords([$p1])->assertCanNotSeeTableRecords([$p2]);
    });

    it('menjalankan validasi, kembalikan, dan tolak dari halaman detail sesuai status dan izin', function () {
        [$o, $u] = ormawaAlur();
        $p = ajukanAlur($o, $u);
        $admin = adminAlur();

        Livewire::actingAs($admin)->test(ViewPermohonan::class, ['record' => $p->getKey()])
            ->assertActionVisible('validasi')->assertActionVisible('kembalikan')->assertActionVisible('tolak')->assertActionHidden('setujuiPembina')
            ->callAction('kembalikan', ['catatan' => 'Lengkapi'])->assertNotified();
        expect($p->fresh()->status)->toBe(StatusPermohonan::Dikembalikan);

        Livewire::actingAs($admin)->test(ViewPermohonan::class, ['record' => $p->getKey()])->assertActionHidden('validasi')->assertActionHidden('tolak');

        $p2 = ajukanAlur(...ormawaAlurDua());
        Livewire::actingAs($admin)->test(ViewPermohonan::class, ['record' => $p2->getKey()])->callAction('validasi')->assertNotified();
        expect($p2->fresh()->status)->toBe(StatusPermohonan::DisposisiDekan);

        $p3 = ajukanAlur(...ormawaAlurDua());
        Livewire::actingAs($admin)->test(ViewPermohonan::class, ['record' => $p3->getKey()])->callAction('tolak', ['alasan' => 'Tidak sesuai']);
        expect($p3->fresh()->status)->toBe(StatusPermohonan::Ditolak)->and($p3->fresh()->ruangan->pluck('status')->all())->toBe(['dilepas']);
    });

    it('menyembunyikan aksi dari pengguna tanpa hak dan menampilkan riwayat', function () {
        [$o, $u] = ormawaAlur();
        $p = ajukanAlur($o, $u);
        $dekan = User::factory()->create()->assignRole('dekan');
        $dekan->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

        Livewire::actingAs($dekan)->test(ViewPermohonan::class, ['record' => $p->getKey()])
            ->assertActionHidden('validasi')->assertActionHidden('tolak')->assertActionHidden('kembalikan');

        Livewire::actingAs($dekan)->test(PermohonanRiwayatRelationManager::class, ['ownerRecord' => $p, 'pageClass' => ViewPermohonan::class])
            ->assertCanSeeTableRecords($p->riwayat)->assertSee('validasi_admin');
    });
});

function ormawaAlurDua(): array
{
    static $n = 0;
    $n++;
    [$o, $u] = ormawaAlur("Ormawa Dua {$n}");

    return [$o, $u, ['ruangan' => [['kode' => 'A101', 'tanggal' => '2026-06-21', 'sesi' => $n === 1 ? 'pagi' : 'siang']], 'tanggal_selesai' => '2026-06-21', 'tanggal_mulai' => '2026-06-21'], 'kegiatan-ruangan'];
}
