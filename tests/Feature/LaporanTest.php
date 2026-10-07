<?php

use App\Actions\Disposisi\BuatDisposisi;
use App\Filament\Pages\Laporan as HalamanLaporan;
use App\Filament\Widgets\DisposisiTerlambatPerPejabat;
use App\Filament\Widgets\RingkasanLayananOrmawa;
use App\Filament\Widgets\RingkasanPersuratan;
use App\Jobs\EksporLaporan;
use App\Models\Disposisi;
use App\Models\DisposisiPenerima;
use App\Models\Jabatan;
use App\Models\JenisNaskah;
use App\Models\JenisPermohonan;
use App\Models\Lpj;
use App\Models\Naskah;
use App\Models\NilaiLpj;
use App\Models\Ormawa;
use App\Models\PemangkuJabatan;
use App\Models\PengurusOrmawa;
use App\Models\Permohonan;
use App\Models\PermohonanRuangan;
use App\Models\RiwayatPermohonan;
use App\Models\RubrikLpj;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Services\Laporan\DaftarLaporan;
use App\Services\Register\EksporXlsx;
use Carbon\CarbonImmutable;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\JenisPermohonanSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\RubrikLpjSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelReader;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, JenisPermohonanSeeder::class, RubrikLpjSeeder::class]);
    Filament::setCurrentPanel('admin');
    CarbonImmutable::setTestNow('2026-06-01 09:00:00');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    File::delete(File::glob(storage_path('app/tmp/laporan-*.xlsx')));
});

function lapUser(string $peran, ?string $jabatan = null): User
{
    $u = User::factory()->create()->assignRole($peran);
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    if ($jabatan !== null) {
        PemangkuJabatan::create(['jabatan_id' => Jabatan::firstWhere('kode', $jabatan)->id, 'user_id' => $u->id, 'mulai' => '2026-01-01']);
    }

    return $u;
}

function lapOrmawa(string $nama, string $tingkat = 'prodi'): Ormawa
{
    return Ormawa::create(['nama' => $nama, 'tingkat' => $tingkat]);
}

function lapPermohonan(Ormawa $o, string $status, array $ubah = []): Permohonan
{
    static $n = 0;
    $p = (new Permohonan)->forceFill([
        'nomor' => 'PMH-2026-'.(8000 + ++$n), 'ormawa_id' => $o->id, 'jenis_permohonan_id' => JenisPermohonan::firstWhere('kode', 'kegiatan')->id,
        'diajukan_oleh' => User::factory()->create()->id, 'nama_kegiatan' => 'Kegiatan '.$n, 'perihal' => 'Izin', 'tanggal_mulai' => '2026-03-10', 'tanggal_selesai' => '2026-03-11',
        'deskripsi' => 'D', 'penanggung_jawab' => ['ketua' => ['nama' => 'B']], 'status' => $status, 'diajukan_pada' => '2026-03-05 08:00:00', ...$ubah,
    ]);
    $p->saveQuietly();

    return $p;
}

function lapRiwayat(Permohonan $p, ?string $dari, string $ke, string $waktu): void
{
    (new RiwayatPermohonan(['permohonan_id' => $p->id, 'dari_status' => $dari, 'ke_status' => $ke]))->forceFill(['created_at' => $waktu])->save();
}

function lapPeriode(string $dari, string $sampai): array
{
    return [CarbonImmutable::parse($dari)->startOfDay(), CarbonImmutable::parse($sampai)->endOfDay()];
}

function lapTabel(string $kode, string $dari, string $sampai): array
{
    return DaftarLaporan::cari($kode)->susun(...lapPeriode($dari, $sampai));
}

describe('LAP-01 register', function () {
    it('memuat surat masuk dan keluar pada periode, merahasiakan perihal non-biasa', function () {
        $admin = lapUser('admin-persuratan');
        $surat = fn (string $agenda, string $terima, string $keamanan, string $perihal) => (new SuratMasuk(['nomor_surat' => 'X/1', 'tanggal_surat' => '2026-03-01', 'asal' => 'Dinas A', 'perihal' => $perihal, 'klasifikasi_keamanan' => $keamanan, 'derajat_kecepatan' => 'biasa']))
            ->forceFill(['nomor_agenda' => $agenda, 'tanggal_terima' => $terima, 'status' => 'diterima', 'diregistrasi_oleh' => $admin->id])->save();
        $surat('0001/AGD/2026', '2026-03-05 09:00:00', 'biasa', 'Undangan Rapat');
        $surat('0002/AGD/2026', '2026-03-20 09:00:00', 'rahasia', 'RAHASIA-LAP-01');
        $surat('0003/AGD/2026', '2026-04-02 09:00:00', 'biasa', 'Di luar periode');

        $jenis = JenisNaskah::firstWhere('kode', 'surat-dinas');
        $n = new Naskah(['jenis_naskah_id' => $jenis->id, 'perihal' => 'Balasan Undangan', 'data' => [], 'status' => 'terbit', 'penyusun_id' => $admin->id, 'penanda_tangan_jabatan_id' => Jabatan::firstWhere('kode', 'dekan')->id, 'mode_tanda_tangan' => 'basah']);
        $n->forceFill(['nomor' => '10/UN58.10/KM.03.02/2026', 'tanggal_naskah' => '2026-03-15'])->save();
        $n->tujuan()->create(['jenis' => 'tujuan', 'nama' => 'Dinas A', 'urutan' => 1]);

        [$masuk, $keluar] = lapTabel('lap-01', '2026-03-01', '2026-03-31');

        expect($masuk['baris'])->toHaveCount(2)->and($masuk['baris'][0])->toBe(['0001/AGD/2026', '2026-03-05', 'Dinas A', 'Undangan Rapat', 'Biasa', 'Diterima'])
            ->and($masuk['baris'][1][3])->toBe('(dirahasiakan: Rahasia)')->and(json_encode($masuk))->not->toContain('RAHASIA-LAP-01')->not->toContain('Di luar periode')
            ->and($keluar['baris'])->toBe([['10/UN58.10/KM.03.02/2026', '2026-03-15', 'Dinas A', 'Balasan Undangan', 'Biasa', 'Terbit']]);
    });
});

describe('LAP-02 disposisi', function () {
    it('merekap per pejabat: diterima, selesai, terlambat, belum ditindaklanjuti, dan rata-rata hari', function () {
        Notification::fake();
        $dekan = lapUser('dekan', 'dekan');
        $wd = lapUser('wakil-dekan', 'wd-akademik');
        $admin = lapUser('admin-persuratan');

        $ids = [];
        foreach ([1, 2, 3] as $i) {
            $surat = (new SuratMasuk(['nomor_surat' => "S/{$i}", 'tanggal_surat' => '2026-05-20', 'asal' => 'X', 'perihal' => 'P', 'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa']))
                ->forceFill(['nomor_agenda' => "000{$i}/AGD/2026", 'tanggal_terima' => '2026-05-20 09:00:00', 'status' => 'diterima', 'diregistrasi_oleh' => $admin->id]);
            $surat->save();
            $d = app(BuatDisposisi::class)->jalankan($surat, $dekan, [$wd->id], ['tindak_lanjuti'], null, now()->addDays(10));
            $ids[$i] = $d->penerima->first()->id;
        }

        DisposisiPenerima::whereKey($ids[1])->update(['status' => 'selesai', 'selesai_pada' => now()->addDays(2)]);
        Disposisi::whereHas('penerima', fn ($q) => $q->whereKey($ids[2]))->update(['batas_waktu' => '2026-05-25 10:00:00']);
        DisposisiPenerima::whereKey($ids[3])->update(['status' => 'dibaca']);

        [$t] = lapTabel('lap-02', '2026-05-01', '2026-06-30');

        expect($t['baris'])->toBe([[$wd->name, 'Wakil Dekan Bidang Akademik', 3, 1, 1, 2, 2.0]]);
    });
});

describe('LAP-03 permohonan', function () {
    it('merekap per bulan, ormawa, dan status serta rata-rata waktu per tahap dari riwayat', function () {
        $a = lapOrmawa('HIMA A');
        $b = lapOrmawa('HIMA B');
        $p1 = lapPermohonan($a, 'selesai');
        foreach ([[null, 'diajukan', '2026-03-05 08:00:00'], ['diajukan', 'validasi_admin', '2026-03-07 08:00:00'], ['validasi_admin', 'disposisi_dekan', '2026-03-11 08:00:00'], ['disposisi_dekan', 'selesai', '2026-03-12 08:00:00']] as [$d, $k, $w]) {
            lapRiwayat($p1, $d, $k, $w);
        }
        $p2 = lapPermohonan($a, 'ditolak', ['diajukan_pada' => '2026-03-20 08:00:00']);
        foreach ([[null, 'diajukan', '2026-03-20 08:00:00'], ['diajukan', 'validasi_admin', '2026-03-21 08:00:00'], ['validasi_admin', 'ditolak', '2026-03-24 08:00:00']] as [$d, $k, $w]) {
            lapRiwayat($p2, $d, $k, $w);
        }
        lapPermohonan($b, 'validasi_admin', ['diajukan_pada' => '2026-04-02 08:00:00']);
        lapPermohonan($b, 'validasi_admin', ['diajukan_pada' => '2026-07-02 08:00:00']);

        [$rekap, $tahap] = lapTabel('lap-03', '2026-03-01', '2026-04-30');

        expect($rekap['baris'])->toBe([['2026-03', 'HIMA A', 'Ditolak', 1], ['2026-03', 'HIMA A', 'Selesai', 1], ['2026-04', 'HIMA B', 'Validasi admin', 1]])
            ->and($tahap['baris'])->toBe([['Diajukan', 2, 1.5, 2.0], ['Disposisi dekan', 1, 1.0, 1.0], ['Validasi admin', 2, 3.5, 4.0]]);
    });
});

describe('LAP-04 LPJ', function () {
    it('merekap LPJ per ormawa dengan nilai akhir dan rata-rata aspek rubrik', function () {
        $a = lapOrmawa('HIMA A');
        $b = lapOrmawa('HIMA B', 'ukm');
        $dibuat = function (Permohonan $p, string $status, string $batas, ?float $akhir = null) {
            $l = new Lpj;
            $l->forceFill(['permohonan_id' => $p->id, 'status' => $status, 'batas_waktu' => $batas, 'nilai_akhir' => $akhir])->save();

            return $l;
        };
        $l1 = $dibuat(lapPermohonan($a, 'selesai'), 'dinilai', '2026-03-25', 80);
        $dibuat(lapPermohonan($a, 'selesai'), 'diajukan', '2026-03-25');
        $dibuat(lapPermohonan($a, 'selesai'), 'draf', '2026-02-01');
        $dibuat(lapPermohonan($b, 'selesai', ['tanggal_selesai' => '2026-08-01']), 'dinilai', '2026-08-15', 99);

        $rubrik = RubrikLpj::firstWhere('kode', 'ketepatan');
        foreach ([['dekan', 18], ['wd-akademik', 16]] as [$kodeJabatan, $nilai]) {
            $j = Jabatan::firstWhere('kode', $kodeJabatan);
            NilaiLpj::create(['lpj_id' => $l1->id, 'rubrik_lpj_id' => $rubrik->id, 'penilai_jabatan_id' => $j->id, 'penilai_user_id' => User::factory()->create()->id, 'nilai' => $nilai]);
        }

        [$t] = lapTabel('lap-04', '2026-03-01', '2026-03-31');

        expect($t['kolom'])->toBe(['Ormawa', 'Tingkat', 'LPJ', 'Diajukan', 'Dinilai', 'Terlambat', 'Rata-rata nilai akhir', 'Ketepatan pelaksanaan (maks 20)', 'Kepatuhan tenggat (maks 5)', 'Kelengkapan laporan (maks 20)', 'Kontribusi bagi fakultas (maks 5)'])
            ->and($t['baris'])->toBe([['HIMA A', 'Program studi', 3, 2, 1, 1, 80.0, 17.0, null, null, null]]);
    });
});

describe('LAP-05 ruangan', function () {
    it('merekap pemakaian dikonfirmasi per bulan, ruangan, dan ormawa', function () {
        $a = lapOrmawa('HIMA A');
        $p = lapPermohonan($a, 'selesai');
        $baris = fn (string $tgl, string $sesi, string $status = 'dikonfirmasi') => PermohonanRuangan::create(['permohonan_id' => $p->id, 'kode_ruangan' => 'AULA', 'nama_ruangan' => 'Aula Utama', 'tanggal' => $tgl, 'sesi' => $sesi, 'status' => $status]);
        $baris('2026-03-10', 'pagi');
        $baris('2026-03-10', 'siang');
        $baris('2026-03-11', 'pagi');
        $baris('2026-03-12', 'pagi', 'ditahan');
        $baris('2026-04-02', 'pagi');

        [$t] = lapTabel('lap-05', '2026-03-01', '2026-03-31');

        expect($t['baris'])->toBe([['2026-03', 'AULA — Aula Utama', 'HIMA A', 3, 2]]);
    });
});

it('tidak ada data pribadi pada laporan mana pun', function () {
    $o = lapOrmawa('HIMA Privasi');
    $u = User::factory()->create(['email' => 'rahasia.pengurus@student.unsil.ac.id']);
    PengurusOrmawa::create(['ormawa_id' => $o->id, 'user_id' => $u->id, 'nama' => 'Nama Pengurus', 'jabatan' => 'ketua', 'nim' => '2012349999', 'telepon' => '081299998888']);
    $p = lapPermohonan($o, 'selesai');
    PermohonanRuangan::create(['permohonan_id' => $p->id, 'kode_ruangan' => 'AULA', 'nama_ruangan' => 'Aula', 'tanggal' => '2026-03-10', 'sesi' => 'pagi', 'status' => 'dikonfirmasi']);
    $l = new Lpj;
    $l->forceFill(['permohonan_id' => $p->id, 'status' => 'draf', 'batas_waktu' => '2026-03-25'])->save();

    foreach (array_keys(DaftarLaporan::semua()) as $kode) {
        $json = json_encode(lapTabel($kode, '2020-01-01', '2026-12-31'));

        expect($json)->not->toContain('2012349999')->not->toContain('081299998888')->not->toContain('rahasia.pengurus')->not->toContain('Nama Pengurus')->not->toContain('penanggung');
    }
});

describe('ekspor XLSX', function () {
    function lapDataRuangan(): void
    {
        $o = lapOrmawa('=HYPERLINK("http://x")');
        $p = lapPermohonan($o, 'selesai');
        PermohonanRuangan::create(['permohonan_id' => $p->id, 'kode_ruangan' => 'AULA', 'nama_ruangan' => 'Aula', 'tanggal' => '2026-03-10', 'sesi' => 'pagi', 'status' => 'dikonfirmasi']);
    }

    it('menulis XLSX bernama pemilik, menetralkan rumus, dan memberi notifikasi tautan unduh', function () {
        lapDataRuangan();
        $dekan = lapUser('dekan', 'dekan');

        (new EksporLaporan('lap-05', '2026-03-01', '2026-03-31', $dekan->id))->handle(app(EksporXlsx::class));

        $berkas = File::glob(storage_path("app/tmp/laporan-lap-05-{$dekan->id}-*.xlsx"));
        expect($berkas)->toHaveCount(1);

        $baris = SimpleExcelReader::create($berkas[0])->getRows()->all();
        expect($baris)->toHaveCount(1)->and($baris[0]['Ormawa'])->toBe("'=HYPERLINK(\"http://x\")")->and($baris[0]['Jumlah sesi'])->toBe(1);

        $n = $dekan->notifications()->first();
        expect($n->data['title'])->toBe('Laporan siap diunduh')->and($n->data['actions'][0]['url'])->toContain('/ekspor/laporan-lap-05-');
    });

    it('mengunduh hanya oleh pemilik bertanda tangan dengan izin; berkas dihapus setelah dikirim', function () {
        lapDataRuangan();
        $dekan = lapUser('dekan', 'dekan');
        $lain = lapUser('kasubag', 'kasubag-umum');
        (new EksporLaporan('lap-05', '2026-03-01', '2026-03-31', $dekan->id))->handle(app(EksporXlsx::class));
        $nama = basename(File::glob(storage_path('app/tmp/laporan-lap-05-*.xlsx'))[0]);
        $url = URL::temporarySignedRoute('ekspor.unduh', now()->addHour(), ['berkas' => $nama]);

        $this->get($url)->assertRedirect();
        $this->actingAs($lain)->get($url)->assertForbidden();
        $this->actingAs($dekan)->get(route('ekspor.unduh', ['berkas' => $nama]))->assertForbidden();

        $dekan->revokePermissionTo('laporan.lihat');
        $dekan->syncRoles(['pegawai']);
        $this->actingAs($dekan->fresh())->get($url)->assertForbidden();

        $dekan->syncRoles(['dekan']);
        $respons = $this->actingAs($dekan->fresh())->get($url)->assertOk()->assertDownload($nama);
        ob_start();
        $respons->baseResponse->sendContent();
        ob_end_clean();
        expect(File::exists(storage_path("app/tmp/{$nama}")))->toBeFalse();
        $this->actingAs($dekan->fresh())->get($url)->assertNotFound();
        $this->actingAs($dekan->fresh())->get(URL::temporarySignedRoute('ekspor.unduh', now()->addHour(), ['berkas' => 'laporan-lap-99-x.xlsx']))->assertNotFound();
    });

    it('pekerja memeriksa ulang izin: pengguna tanpa izin tidak mendapat berkas', function () {
        lapDataRuangan();
        $pegawai = lapUser('pegawai');

        (new EksporLaporan('lap-05', '2026-03-01', '2026-03-31', $pegawai->id))->handle(app(EksporXlsx::class));
        (new EksporLaporan('lap-99', '2026-03-01', '2026-03-31', lapUser('dekan', 'dekan')->id))->handle(app(EksporXlsx::class));

        expect(File::glob(storage_path('app/tmp/laporan-*.xlsx')))->toBe([])->and($pegawai->notifications()->count())->toBe(0);
    });
});

describe('halaman laporan', function () {
    it('menampilkan laporan terpilih untuk pemegang izin; ditolak bagi yang lain', function () {
        lapDataRuangan();
        $admin = lapUser('admin-persuratan');

        Livewire::actingAs($admin)->test(HalamanLaporan::class)->fillForm(['jenis' => 'lap-05', 'dari' => '2026-03-01', 'sampai' => '2026-03-31'])
            ->call('tampilkan')->assertSee('LAP-05 Pemakaian ruangan oleh ormawa')->assertSee('AULA — Aula');

        $this->actingAs(lapUser('pegawai'))->get('/admin/laporan')->assertForbidden();
        $this->actingAs(lapUser('pengurus-ormawa'))->get('/admin/laporan')->assertForbidden();
    });

    it('ekspor dari halaman masuk antrean dengan parameter sah; rentang tak sah ditolak', function () {
        Queue::fake();
        $admin = lapUser('admin-persuratan');

        Livewire::actingAs($admin)->test(HalamanLaporan::class)->fillForm(['jenis' => 'lap-03', 'dari' => '2026-03-01', 'sampai' => '2026-03-31'])->callAction('ekspor')->assertNotified();
        Queue::assertPushed(EksporLaporan::class, fn ($j) => $j->kode === 'lap-03' && $j->dari === '2026-03-01' && $j->sampai === '2026-03-31' && $j->penggunaId === $admin->id);

        Queue::fake();
        Livewire::actingAs($admin)->test(HalamanLaporan::class)->fillForm(['jenis' => 'lap-03', 'dari' => '2026-03-31', 'sampai' => '2026-03-01'])->call('tampilkan')->assertHasErrors();
        Queue::assertNothingPushed();
    });

    it('pimpinan memiliki izin laporan, pegawai dan pengurus tidak', function (string $peran, bool $boleh) {
        expect(User::factory()->create()->assignRole($peran)->can('laporan.lihat'))->toBe($boleh);
    })->with([['admin-persuratan', true], ['dekan', true], ['wakil-dekan', true], ['kasubag', true], ['pegawai', false], ['pengurus-ormawa', false], ['pembina-ormawa', false], ['operator-layanan', false]]);
});

describe('dasbor', function () {
    function lapStat(string $widget): array
    {
        $w = new $widget;
        $stats = (new ReflectionMethod($w, 'getStats'))->invoke($w);

        return collect($stats)->mapWithKeys(fn ($s) => [$s->getLabel() => $s->getValue()])->all();
    }

    it('widget tampil menurut peran', function (string $peran, array $tampil) {
        $this->actingAs(lapUser($peran));

        expect([RingkasanPersuratan::canView(), RingkasanLayananOrmawa::canView(), DisposisiTerlambatPerPejabat::canView()])->toBe($tampil);
    })->with([
        'dekan' => ['dekan', [true, true, true]],
        'admin' => ['admin-persuratan', [true, true, true]],
        'pegawai' => ['pegawai', [true, false, false]],
        'pengurus' => ['pengurus-ormawa', [false, false, false]],
        'pembina' => ['pembina-ormawa', [false, false, false]],
    ]);

    it('menghitung angka persuratan dan layanan ormawa; pejabat biasa hanya melihat disposisinya sendiri', function () {
        Notification::fake();
        $dekan = lapUser('dekan', 'dekan');
        $wd = lapUser('wakil-dekan', 'wd-akademik');
        $kasubag = lapUser('kasubag', 'kasubag-umum');
        $admin = lapUser('admin-persuratan');

        $surat = function (string $agenda, string $status) use ($admin) {
            $s = (new SuratMasuk(['nomor_surat' => $agenda, 'tanggal_surat' => '2026-05-20', 'asal' => 'X', 'perihal' => 'P', 'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa']))
                ->forceFill(['nomor_agenda' => $agenda, 'tanggal_terima' => '2026-05-20 09:00:00', 'status' => $status, 'diregistrasi_oleh' => $admin->id]);
            $s->save();

            return $s;
        };
        $surat('A1', 'diterima');
        $surat('A2', 'diterima');
        foreach (['A3' => $wd, 'A4' => $kasubag] as $agenda => $penerima) {
            $d = app(BuatDisposisi::class)->jalankan($surat($agenda, 'diterima'), $dekan, [$penerima->id], ['tindak_lanjuti'], null, now()->addDays(5));
            Disposisi::whereKey($d->id)->update(['batas_waktu' => '2026-05-25 10:00:00']);
        }

        $o = lapOrmawa('HIMA D');
        lapPermohonan($o, 'validasi_admin');
        lapPermohonan($o, 'persetujuan_wd');
        lapPermohonan($o, 'persetujuan_wd');
        lapPermohonan($o, 'dikembalikan');
        $o->forceFill(['diblokir_lpj' => true])->save();
        $l = new Lpj;
        $l->forceFill(['permohonan_id' => lapPermohonan($o, 'selesai')->id, 'status' => 'draf', 'batas_waktu' => '2026-05-01'])->save();

        $this->actingAs($dekan);
        $p = lapStat(RingkasanPersuratan::class);
        expect($p['Surat masuk belum didisposisikan'])->toBe(2)->and($p['Disposisi terlambat'])->toBe(2);

        $o2 = lapStat(RingkasanLayananOrmawa::class);
        expect($o2['Menunggu validasi'])->toBe(1)->and($o2['Menunggu keputusan WD'])->toBe(2)->and($o2['Dikembalikan ke ormawa'])->toBe(1)->and($o2['LPJ terlambat'])->toBe(1)->and($o2['Ormawa terblokir (LPJ)'])->toBe(1);

        $this->actingAs($wd);
        expect(lapStat(RingkasanPersuratan::class))->toHaveKey('Disposisi terlambat')->and(lapStat(RingkasanPersuratan::class)['Disposisi terlambat'])->toBe(2);

        $this->actingAs(lapUser('pegawai'));
        $saya = lapStat(RingkasanPersuratan::class);
        expect($saya)->toHaveKey('Disposisi Anda yang terlambat')->and($saya['Disposisi Anda yang terlambat'])->toBe(0);

        $this->actingAs($dekan);
        $w = new DisposisiTerlambatPerPejabat;
        expect($w->baris())->toHaveCount(2)->and($w->baris()[0]['terlambat'])->toBe(1);
    });

    it('dasbor panel dapat dirender untuk pimpinan', function () {
        $dekan = lapUser('dekan', 'dekan');

        $this->actingAs($dekan)->get('/admin')->assertOk()->assertSee('Persuratan')->assertSee('Layanan Ormawa');
    });
});
