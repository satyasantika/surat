<?php

use App\Actions\Migrasi\ImporOrmawaHub;
use App\Exceptions\PemetaanTidakLengkap;
use App\Models\ImporLog;
use App\Models\Ormawa;
use App\Models\PemangkuJabatan;
use App\Models\PengurusOrmawa;
use App\Models\RuanganLokal;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Support\Migrasi\PenguraiTanggal;
use App\Support\Pengaturan;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Tests\Fixtures\OrmawaHubFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class]);
    $this->dir = sys_get_temp_dir().'/impor-'.bin2hex(random_bytes(4));
    $this->fx = OrmawaHubFixture::buat($this->dir);
});

afterEach(fn () => File::deleteDirectory($this->dir));

function impor(array $fx, bool $dry = false)
{
    return app(ImporOrmawaHub::class)->jalankan($fx['xlsx'], $fx['pemetaan'], $dry);
}

describe('dry-run', function () {
    it('menjalankan semuanya lalu membatalkan seluruh perubahan', function () {
        $k = impor($this->fx, dry: true);

        expect(User::count())->toBe(0)->and(Ormawa::count())->toBe(0)->and(PengurusOrmawa::count())->toBe(0)
            ->and(RuanganLokal::count())->toBe(0)->and(ImporLog::count())->toBe(0)->and(PemangkuJabatan::count())->toBe(0)
            ->and($k->jumlah('Users', 'ok') + $k->jumlah('Users', 'peringatan'))->toBe(6);
        expect(File::exists($this->fx['xlsx']))->toBeTrue();
    });
});

describe('impor pengguna', function () {
    it('membuat akun menurut pemetaan dengan peran dan pemangku jabatan', function () {
        impor($this->fx);

        $dekan = User::firstWhere('email', 'dekan.fkip@unsil.ac.id');
        $wd1 = User::firstWhere('email', 'wd1.fkip@unsil.ac.id');
        $op = User::firstWhere('email', 'operator.fkip@unsil.ac.id');

        expect($dekan->hasRole('dekan'))->toBeTrue()->and($dekan->nip_nim)->toBe('197001011999031001')->and($dekan->sumber_id_lama)->toBe('U2')
            ->and($dekan->email_verified_at)->not->toBeNull()
            ->and(User::firstWhere('email', 'admin.fkip@unsil.ac.id')->hasRole('admin-persuratan'))->toBeTrue()
            ->and($wd1->hasRole('wakil-dekan'))->toBeTrue()
            ->and(PemangkuJabatan::where('user_id', $wd1->id)->first()->jabatan->kode)->toBe('wd-akademik')
            ->and(PemangkuJabatan::where('user_id', User::firstWhere('email', 'wd2.fkip@unsil.ac.id')->id)->first()->jabatan->kode)->toBe('wd-kemahasiswaan')
            ->and(PemangkuJabatan::where('user_id', $dekan->id)->first()->jabatan->kode)->toBe('dekan')
            ->and(PemangkuJabatan::where('user_id', User::firstWhere('email', 'kasubag.fkip@unsil.ac.id')->id)->first()->jabatan->kode)->toBe('kasubag-umum')
            ->and($op->hasRole('operator-layanan'))->toBeTrue()
            ->and($op->getDirectPermissions()->pluck('name')->sort()->values()->all())->toBe(['galeri.kelola', 'kabar.kelola', 'permohonan.validasi']);
    });

    it('tidak menyimpan kata sandi lama dan tidak membuat akun ormawa bersama atau contoh', function () {
        impor($this->fx);

        foreach (User::all() as $u) {
            expect(Hash::check('admin123', $u->password))->toBeFalse()->and($u->password)->not->toContain('admin123');
        }
        expect(User::where('email', 'like', '%lama.test')->count())->toBe(0)
            ->and(User::where('email', 'like', '%ormawahub.ac.id')->count())->toBe(0)
            ->and(User::count())->toBe(6)
            ->and(ImporLog::where('sheet', 'Users')->where('id_lama', 'U7')->value('status'))->toBe('lewati')
            ->and(ImporLog::where('sheet', 'Users')->where('id_lama', 'U8')->value('status'))->toBe('lewati');
    });

    it('memberi peringatan untuk urlTte dan tab tanpa padanan, tanpa memigrasikan urlTte', function () {
        impor($this->fx);

        $dekan = ImporLog::where('sheet', 'Users')->where('id_lama', 'U2')->first();
        $op = ImporLog::where('sheet', 'Users')->where('id_lama', 'U6')->first();

        expect($dekan->status)->toBe('peringatan')->and($dekan->pesan)->toContain('urlTte')
            ->and($op->pesan)->toContain("Tab 'laporan_aneh'")
            ->and(TautanBerkas::where('jenis', 'ttd_visual')->count())->toBe(0);
    });

    it('menautkan akun yang sudah ada dengan surel sama tanpa mengubah kata sandinya', function () {
        $ada = User::factory()->create(['email' => 'admin.fkip@unsil.ac.id']);
        $sandi = $ada->password;

        impor($this->fx);

        expect(User::where('email', 'admin.fkip@unsil.ac.id')->count())->toBe(1)->and($ada->fresh()->password)->toBe($sandi)
            ->and($ada->fresh()->sumber_id_lama)->toBe('U1')->and($ada->fresh()->hasRole('admin-persuratan'))->toBeTrue();
    });
});

describe('impor ormawa dan pengurus', function () {
    it('mengimpor ormawa menurut pemetaan, membuang contoh dan yang ditandai buang', function () {
        impor($this->fx);

        $o = Ormawa::firstWhere('sumber_id_lama', 'O1');
        expect(Ormawa::count())->toBe(2)->and($o->nama)->toBe('HIMA Matematika')->and($o->slug)->toBe('hima-matematika')->and($o->tingkat)->toBe('prodi')
            ->and($o->akun_media)->toBe('@himamat')->and($o->visi)->toBe('Visi rekaan')
            ->and(Ormawa::where('nama', 'like', '%BEM FT%')->count())->toBe(0)->and(Ormawa::where('nama', 'UKM Lama')->count())->toBe(0)
            ->and(ImporLog::where('sheet', 'Ormawa_Profiles')->where('id_lama', 'O3')->value('status'))->toBe('lewati')
            ->and(ImporLog::where('sheet', 'Ormawa_Profiles')->where('id_lama', 'O4')->value('status'))->toBe('lewati');
    });

    it('mengimpor logo dan SK sebagai tautan, membuang foto stok, dan menandai SK perlu dilengkapi', function () {
        impor($this->fx);

        $o1 = Ormawa::firstWhere('sumber_id_lama', 'O1');
        $o2 = Ormawa::firstWhere('sumber_id_lama', 'O2');

        expect($o1->tautan()->pluck('jenis')->sort()->values()->all())->toBe(['logo', 'sk'])->and($o2->tautan()->count())->toBe(0)
            ->and($o2->akun_media)->toBeNull()->and($o1->sk()->count())->toBe(0)
            ->and(ImporLog::where('sheet', 'Ormawa_Profiles')->where('id_lama', 'O1')->value('pesan'))->toContain('perlu_dilengkapi')
            ->and(TautanBerkas::where('url', 'like', '%unsplash%')->count())->toBe(0);
    });

    it('mengimpor pengurus dengan jabatan terpetakan, telepon terenkripsi, dan tanpa akun', function () {
        impor($this->fx);

        $o1 = Ormawa::firstWhere('sumber_id_lama', 'O1');
        $ketua = PengurusOrmawa::firstWhere('nim', '2012345678');
        $sek = PengurusOrmawa::firstWhere('nim', '2012345679');
        $humas = PengurusOrmawa::firstWhere('nim', '2012345680');

        expect(PengurusOrmawa::count())->toBe(4)->and($ketua->ormawa_id)->toBe($o1->id)->and($ketua->jabatan)->toBe('ketua')->and($ketua->narahubung)->toBeTrue()
            ->and($ketua->telepon)->toBe('081234567890')->and($ketua->user_id)->toBeNull()->and($ketua->tampil_publik)->toBeTrue()
            ->and($ketua->prodi)->toBe('Pendidikan Matematika')->and($ketua->tautan()->where('jenis', 'foto')->count())->toBe(1)
            ->and($sek->jabatan)->toBe('sekretaris')->and($sek->jabatan_teks)->toBe('Sekretaris Umum')->and($sek->telepon)->toBeNull()
            ->and($humas->jabatan)->toBe('lainnya')->and($humas->jabatan_teks)->toBe('Koordinator Humas')->and($humas->tautan()->count())->toBe(0)
            ->and(PengurusOrmawa::firstWhere('nim', '2012345681')->jabatan)->toBe('wakil_ketua')
            ->and(PengurusOrmawa::where('nama', 'like', '%Robotik%')->exists())->toBeFalse()
            ->and(PengurusOrmawa::where('nama', 'Anggota UKM Buang')->exists())->toBeFalse();
        expect(DB::table('pengurus_ormawa')->where('nim', '2012345678')->value('telepon'))->not->toBe('081234567890');
    });
});

describe('impor ruangan (mode lokal)', function () {
    it('mengimpor ruangan lokal tanpa PIC, membuang contoh, dan memuat katalog rektorat tanpa kontak', function () {
        impor($this->fx);

        $aula = RuanganLokal::firstWhere('kode', 'AULA-UTAMA');
        $sem = RuanganLokal::firstWhere('kode', 'R-SEMINAR');

        expect(RuanganLokal::count())->toBe(2)->and($aula->kapasitas)->toBe(300)->and($aula->dalam_perawatan)->toBeFalse()
            ->and($sem->dalam_perawatan)->toBeTrue()->and($sem->tampil_katalog)->toBeFalse()
            ->and(RuanganLokal::where('nama', 'like', '%Teknik%')->exists())->toBeFalse();

        $katalog = Pengaturan::get('fasilitas_rektorat_katalog');
        expect(array_column($katalog, 'nama'))->toBe(['Gedung Serbaguna', 'Sound System'])
            ->and(json_encode($katalog))->not->toContain('Kontak');
    });

    it('mode aset_api tidak mengimpor ruangan dan mewajibkan ruangan.csv', function () {
        Pengaturan::set('layanan_ruangan', 'aset_api');
        $k = impor($this->fx);

        expect(RuanganLokal::count())->toBe(0)->and($k->peta['ruangan'])->toBe(['R1' => 'AULA-UTAMA', 'R3' => 'R-SEMINAR']);

        File::delete($this->fx['pemetaan'].'/ruangan.csv');
        expect(fn () => impor($this->fx))->toThrow(PemetaanTidakLengkap::class, 'Pemetaan tidak lengkap');
    });
});

describe('idempotensi', function () {
    it('impor kedua tidak menggandakan data dan mencatat semua baris sebagai lewati', function () {
        impor($this->fx);
        $hitung = fn () => [User::count(), Ormawa::count(), PengurusOrmawa::count(), RuanganLokal::count(), PemangkuJabatan::count(), TautanBerkas::count(), DB::table('model_has_roles')->count()];
        $sebelum = $hitung();

        $k = impor($this->fx);

        expect($hitung())->toBe($sebelum)->and($k->jumlah('Users', 'ok'))->toBe(0)->and($k->jumlah('Users', 'galat'))->toBe(0)
            ->and($k->jumlah('Ormawa_Profiles', 'ok') + $k->jumlah('Ormawa_Profiles', 'peringatan'))->toBe(0)
            ->and($k->jumlah('Pengurus', 'ok') + $k->jumlah('Pengurus', 'peringatan'))->toBe(0)
            ->and($k->jumlah('Users', 'lewati'))->toBe(8)->and($k->jumlah('Pengurus', 'lewati'))->toBe(6);
        expect(ImporLog::distinct('batch')->count('batch'))->toBe(2);
    });

    it('dry-run setelah impor sungguhan tetap tidak mengubah apa pun', function () {
        impor($this->fx);
        $sebelum = [User::count(), ImporLog::count()];

        impor($this->fx, dry: true);

        expect([User::count(), ImporLog::count()])->toBe($sebelum);
    });
});

describe('validasi pemetaan', function () {
    it('menolak sebelum menulis bila pengguna tidak terpetakan, surel salah/ganda/domain asing, atau jabatan hantu', function (callable $ubah, string $pesan) {
        $ubah($this->fx['pemetaan']);

        try {
            impor($this->fx);
            $this->fail('Seharusnya melempar PemetaanTidakLengkap');
        } catch (PemetaanTidakLengkap $e) {
            expect(implode("\n", $e->galat))->toContain($pesan);
        }

        expect(User::count())->toBe(0)->and(Ormawa::count())->toBe(0)->and(ImporLog::count())->toBe(0);
    })->with([
        'pengguna tak terpetakan' => [fn ($d) => OrmawaHubFixture::csv($d.'/pengguna.csv', ['id_lama', 'email', 'peran', 'jabatan'], [['U1', 'a@unsil.ac.id', '', '']]), 'Users U2: tidak ada baris di pengguna.csv'],
        'domain asing' => [fn ($d) => OrmawaHubFixture::csv($d.'/pengguna.csv', ['id_lama', 'email', 'peran', 'jabatan'], [['U1', 'a@gmail.com', '', ''], ['U2', 'b@unsil.ac.id', '', ''], ['U3', 'c@unsil.ac.id', '', ''], ['U4', 'd@unsil.ac.id', '', ''], ['U5', 'e@unsil.ac.id', '', ''], ['U6', 'f@unsil.ac.id', '', '']]), "surel 'a@gmail.com' tidak sah"],
        'surel ganda' => [fn ($d) => OrmawaHubFixture::csv($d.'/pengguna.csv', ['id_lama', 'email', 'peran', 'jabatan'], [['U1', 'a@unsil.ac.id', '', ''], ['U2', 'A@unsil.ac.id', '', ''], ['U3', 'c@unsil.ac.id', '', ''], ['U4', 'd@unsil.ac.id', '', ''], ['U5', 'e@unsil.ac.id', '', ''], ['U6', 'f@unsil.ac.id', '', '']]), 'dipakai ganda'],
        'jabatan hantu' => [fn ($d) => OrmawaHubFixture::csv($d.'/pengguna.csv', ['id_lama', 'email', 'peran', 'jabatan'], [['U1', 'a@unsil.ac.id', '', 'jabatan-hantu'], ['U2', 'b@unsil.ac.id', '', ''], ['U3', 'c@unsil.ac.id', '', ''], ['U4', 'd@unsil.ac.id', '', ''], ['U5', 'e@unsil.ac.id', '', ''], ['U6', 'f@unsil.ac.id', '', '']]), "jabatan 'jabatan-hantu' tidak ada"],
        'ormawa tak terpetakan' => [fn ($d) => OrmawaHubFixture::csv($d.'/ormawa.csv', ['id_lama', 'nama', 'slug', 'tingkat', 'buang'], [['O4', '', '', 'ukm', 'ya']]), 'Ormawa_Profiles O1: tidak ada baris di ormawa.csv'],
        'tingkat tak sah' => [fn ($d) => OrmawaHubFixture::csv($d.'/ormawa.csv', ['id_lama', 'nama', 'slug', 'tingkat', 'buang'], [['O1', '', '', 'galaksi', 'tidak'], ['O2', '', '', 'fakultas', 'tidak'], ['O4', '', '', 'ukm', 'ya']]), "tingkat 'galaksi' tidak sah"],
        'slug ganda' => [fn ($d) => OrmawaHubFixture::csv($d.'/ormawa.csv', ['id_lama', 'nama', 'slug', 'tingkat', 'buang'], [['O1', '', 'sama', 'prodi', 'tidak'], ['O2', '', 'sama', 'fakultas', 'tidak'], ['O4', '', '', 'ukm', 'ya']]), "slug 'sama' dipakai ganda"],
        'kode jabatan wd hantu' => [fn ($d) => OrmawaHubFixture::csv($d.'/wd.csv', ['kode_lama', 'kode_jabatan'], [['wd1', 'hantu'], ['wd2', 'wd-kemahasiswaan']]), "kode jabatan 'hantu'"],
        'kolom wajib hilang' => [fn ($d) => OrmawaHubFixture::csv($d.'/wd.csv', ['kode_lama'], [['wd1']]), 'kolom wajib hilang: kode_jabatan'],
        'berkas pemetaan hilang' => [fn ($d) => File::delete($d.'/ormawa.csv'), 'ormawa.csv tidak ditemukan'],
    ]);

    it('mengabaikan baris contoh dan akun ormawa bersama saat memvalidasi (tak perlu dipetakan)', function () {
        expect(fn () => impor($this->fx, dry: true))->not->toThrow(PemetaanTidakLengkap::class);
    });

    it('menolak sheet wajib yang hilang', function () {
        $w = SimpleExcelWriter::create($this->dir.'/kosong.xlsx')->nameCurrentSheet('Users')->addRow(['id' => 'U1']);
        $w->close();

        expect(fn () => app(ImporOrmawaHub::class)->jalankan($this->dir.'/kosong.xlsx', $this->fx['pemetaan']))->toThrow(InvalidArgumentException::class, 'Sheet wajib');
    });
});

describe('perintah artisan', function () {
    it('dry-run mempertahankan berkas; impor sungguhan menghapus XLSX dan CSV bila tanpa galat', function () {
        $this->artisan('ormawahub:impor', ['xlsx' => $this->fx['xlsx'], '--pemetaan' => $this->fx['pemetaan'], '--dry-run' => true])->assertSuccessful();
        expect(File::exists($this->fx['xlsx']))->toBeTrue()->and(User::count())->toBe(0);

        $this->artisan('ormawahub:impor', ['xlsx' => $this->fx['xlsx'], '--pemetaan' => $this->fx['pemetaan']])->assertSuccessful();
        expect(File::exists($this->fx['xlsx']))->toBeFalse()->and(File::glob($this->fx['pemetaan'].'/*.csv'))->toBe([])->and(User::count())->toBe(6);
    });

    it('gagal dengan daftar masalah bila pemetaan tak lengkap, dan tanpa --pemetaan', function () {
        File::delete($this->fx['pemetaan'].'/wd.csv');

        $this->artisan('ormawahub:impor', ['xlsx' => $this->fx['xlsx'], '--pemetaan' => $this->fx['pemetaan']])
            ->expectsOutputToContain('wd.csv tidak ditemukan')->assertFailed();
        $this->artisan('ormawahub:impor', ['xlsx' => $this->fx['xlsx']])->assertFailed();
        expect(File::exists($this->fx['xlsx']))->toBeTrue();
    });

    it('berkode keluar gagal dan mempertahankan berkas bila ada baris galat', function () {
        Ormawa::create(['nama' => 'HIMA Matematika', 'tingkat' => 'prodi']);

        $this->artisan('ormawahub:impor', ['xlsx' => $this->fx['xlsx'], '--pemetaan' => $this->fx['pemetaan']])->assertFailed();

        expect(ImporLog::where('status', 'galat')->where('sheet', 'Ormawa_Profiles')->count())->toBe(1)->and(File::exists($this->fx['xlsx']))->toBeTrue();
    });
});

describe('pengurai tanggal', function () {
    it('mengurai format campuran', function (mixed $masukan, ?string $hasil) {
        expect(PenguraiTanggal::urai($masukan)?->toDateString())->toBe($hasil);
    })->with([
        'ISO' => ['2026-07-15', '2026-07-15'],
        'ISO waktu' => ['2026-07-15T08:30:00.000Z', '2026-07-15'],
        'id singkat' => ['15 Jul 2026', '2026-07-15'],
        'id Mei' => ['3 Mei 2026', '2026-05-03'],
        'id Agustus' => ['17 Agustus 2026', '2026-08-17'],
        'id Agu' => ['17 Agu 2026', '2026-08-17'],
        'id Okt dengan titik' => ['1 Okt. 2025', '2025-10-01'],
        'id Des' => ['31 Des 2025', '2025-12-31'],
        'dmy' => ['15/07/2026', '2026-07-15'],
        'objek tanggal' => [new DateTimeImmutable('2026-07-15 10:00'), '2026-07-15'],
        'nomor seri Excel' => [46218, '2026-07-15'],
        'seri string' => ['46218', '2026-07-15'],
        'kosong' => ['', null],
        'null' => [null, null],
        'sampah' => ['bukan tanggal', null],
        'tanggal mustahil' => ['31 Feb 2026', null],
        'bulan tak dikenal' => ['5 Xyz 2026', null],
        'tahun aneh' => ['2026-13-40', null],
    ]);
});
