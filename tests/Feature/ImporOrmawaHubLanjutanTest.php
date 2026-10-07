<?php

use App\Actions\Migrasi\ImporOrmawaHub;
use App\Actions\Nomor\AmbilNomorBerikutnya;
use App\Exceptions\PemetaanTidakLengkap;
use App\Models\Disposisi;
use App\Models\Galeri;
use App\Models\ImporLog;
use App\Models\Kabar;
use App\Models\Lpj;
use App\Models\Naskah;
use App\Models\NilaiLpj;
use App\Models\NomorTerpakai;
use App\Models\PemakaianRuanganLokal;
use App\Models\Permohonan;
use App\Models\PermohonanRuangan;
use App\Models\PersetujuanWd;
use App\Models\RegisterNomor;
use App\Models\RiwayatPermohonan;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Support\Pengaturan;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\JenisPermohonanSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\RubrikLpjSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Tests\Fixtures\OrmawaHubFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, JenisPermohonanSeeder::class, RubrikLpjSeeder::class]);
    $this->dir = sys_get_temp_dir().'/impor2-'.bin2hex(random_bytes(4));
    $this->fx = OrmawaHubFixture::buat($this->dir);
    $this->admin = User::factory()->create(['email' => 'pelaksana@unsil.ac.id'])->assignRole('super-admin');
});

afterEach(fn () => File::deleteDirectory($this->dir));

function imporLengkap(array $fx, User $pelaksana, bool $dry = false)
{
    return app(ImporOrmawaHub::class)->jalankan($fx['xlsx'], $fx['pemetaan'], $dry, $pelaksana);
}

function lama(string $nomorLama): Permohonan
{
    return Permohonan::where('nomor_lama', $nomorLama)->firstOrFail();
}

function statusLog(string $sheet, string $id): ?string
{
    return ImporLog::where('sheet', $sheet)->where('id_lama', $id)->latest('created_at')->value('status');
}

describe('permohonan', function () {
    it('memberi nomor baru berurut tanggal pengajuan, id ganda diberi akhiran, contoh/booking manual/ormawa hantu tidak menjadi permohonan', function () {
        imporLengkap($this->fx, $this->admin);

        expect(lama('PR-2026-001')->nomor)->toBe('PMH-2026-0001')->and(lama('PR-2026-002')->nomor)->toBe('PMH-2026-0002')
            ->and(lama('PR-2026-003')->nomor)->toBe('PMH-2026-0003')->and(lama('PR-2026-003-dup2')->nomor)->toBe('PMH-2026-0004')
            ->and(Permohonan::count())->toBe(4)->and(Permohonan::where('sumber', 'migrasi')->count())->toBe(4)
            ->and(statusLog('Requests', 'PR-2026-003-dup2'))->toBe('peringatan')
            ->and(statusLog('Requests', 'PR-2026-004'))->toBe('lewati')
            ->and(statusLog('Requests', 'PR-2026-005'))->toBe('ok')
            ->and(statusLog('Requests', 'PR-2026-006'))->toBe('galat')
            ->and(ImporLog::where('sheet', 'Requests')->where('id_lama', 'PR-2026-006')->value('pesan'))->toContain("Ormawa 'Ormawa Hantu' tidak cocok");
        expect(Permohonan::where('nama_kegiatan', 'like', '%Robotik%')->exists())->toBeFalse()->and(Permohonan::where('nama_kegiatan', 'Rapat Dosen')->exists())->toBeFalse();
    });

    it('menentukan status dari hasil akhir dan tahap berjalan menurut nama tahap', function () {
        imporLengkap($this->fx, $this->admin);

        expect(lama('PR-2026-001')->status->value)->toBe('selesai')->and(lama('PR-2026-001')->selesai_pada)->not->toBeNull()
            ->and(lama('PR-2026-002')->status->value)->toBe('ditolak')
            ->and(lama('PR-2026-003')->status->value)->toBe('persetujuan_wd')->and(lama('PR-2026-003')->selesai_pada)->toBeNull()
            ->and(lama('PR-2026-003-dup2')->status->value)->toBe('validasi_admin');
    });

    it('memetakan data inti, penanggung jawab terenkripsi, jam, dan fasilitas', function () {
        imporLengkap($this->fx, $this->admin);
        $p = lama('PR-2026-001');

        expect($p->nama_kegiatan)->toBe('Seminar Nasional')->and($p->tanggal_mulai->toDateString())->toBe('2026-03-10')->and($p->tanggal_selesai->toDateString())->toBe('2026-03-11')
            ->and($p->diajukan_pada->toDateString())->toBe('2026-02-01')->and($p->jam_mulai)->toBe('08:00:00')
            ->and($p->jenis->kode)->toBe('kegiatan-ruangan')->and($p->ormawa->nama)->toBe('HIMA Matematika')->and($p->diajukan_oleh)->toBe($this->admin->id)
            ->and($p->penanggung_jawab['ketua']['nama'])->toBe('Ketua Rekaan')->and($p->penanggung_jawab['sekretaris']['nama'])->toBe('Sekretaris Rekaan')
            ->and($p->fasilitas_rektorat)->toBe([['nama' => 'Kursi', 'jumlah' => 1, 'keterangan' => null], ['nama' => 'Meja', 'jumlah' => 1, 'keterangan' => null]]);
        expect(json_encode(Permohonan::first()->toArray()))->not->toContain('Ketua Rekaan');
    });

    it('menulis riwayat per tahap dengan pelaku_lama dan tanpa pelaku terautentikasi', function () {
        imporLengkap($this->fx, $this->admin);

        $riwayat = RiwayatPermohonan::where('permohonan_id', lama('PR-2026-001')->id)->orderBy('created_at')->get();

        expect($riwayat)->toHaveCount(8)->and($riwayat->first()->dari_status)->toBeNull()->and($riwayat->first()->ke_status)->toBe('diajukan')
            ->and($riwayat[6]->ke_status)->toBe('penerbitan')->and($riwayat->last()->dari_status)->toBe('penerbitan')->and($riwayat->last()->ke_status)->toBe('selesai')
            ->and($riwayat->pluck('oleh')->filter()->all())->toBe([])
            ->and($riwayat[2]->pelaku_lama)->toBe('Pelaku 3')->and($riwayat[2]->catatan)->toBe('Catatan 3');
    });

    it('menjadwalkan ruangan: dikonfirmasi tercatat di jadwal lokal, berjalan hanya ditahan', function () {
        imporLengkap($this->fx, $this->admin);

        $p1 = lama('PR-2026-001');
        $p3 = lama('PR-2026-003');

        expect(PermohonanRuangan::where('permohonan_id', $p1->id)->count())->toBe(4)->and(PermohonanRuangan::where('permohonan_id', $p1->id)->pluck('status')->unique()->all())->toBe(['dikonfirmasi'])
            ->and(PermohonanRuangan::where('permohonan_id', $p1->id)->first()->kode_ruangan)->toBe('AULA-UTAMA')
            ->and(PemakaianRuanganLokal::where('permohonan_id', $p1->id)->count())->toBe(4)
            ->and(PermohonanRuangan::where('permohonan_id', $p3->id)->pluck('sesi')->all())->toBe(['seharian'])
            ->and(PermohonanRuangan::where('permohonan_id', $p3->id)->value('status'))->toBe('ditahan')
            ->and(PemakaianRuanganLokal::where('permohonan_id', $p3->id)->count())->toBe(0);
    });

    it('booking manual non-ormawa menjadi pemakaian ruangan lokal, bukan permohonan', function () {
        imporLengkap($this->fx, $this->admin);

        $pakai = PemakaianRuanganLokal::whereNull('permohonan_id')->first();

        expect($pakai->tanggal->toDateString())->toBe('2026-04-02')->and($pakai->sesi)->toBe('siang')->and($pakai->keterangan)->toBe('Rapat Dosen')->and($pakai->ruangan->kode)->toBe('R-SEMINAR');
    });

    it('mengimpor disposisi dari dekan, penerima WD, dan putusan WD menurut approvedWD', function () {
        imporLengkap($this->fx, $this->admin);

        $d = Disposisi::where('permohonan_id', lama('PR-2026-001')->id)->firstOrFail();
        $dekan = User::firstWhere('email', 'dekan.fkip@unsil.ac.id');

        expect($d->nomor)->toBe('D-001')->and($d->dari_user_id)->toBe($dekan->id)->and($d->catatan)->toBe('Silakan diproses')->and($d->penerima)->toHaveCount(2)
            ->and($d->penerima->pluck('status')->map->value->unique()->all())->toBe(['selesai']);

        $putusan = fn (string $nomorLama) => PersetujuanWd::with('jabatan')->where('permohonan_id', lama($nomorLama)->id)->get()->mapWithKeys(fn ($x) => [$x->jabatan->kode => $x->putusan])->all();
        expect($putusan('PR-2026-001'))->toBe(['wd-akademik' => 'setuju', 'wd-kemahasiswaan' => 'setuju'])
            ->and($putusan('PR-2026-002'))->toBe(['wd-akademik' => 'setuju', 'wd-kemahasiswaan' => 'tolak'])
            ->and($putusan('PR-2026-003'))->toBe(['wd-akademik' => 'setuju', 'wd-kemahasiswaan' => 'menunggu']);
        expect(PersetujuanWd::where('permohonan_id', lama('PR-2026-003')->id)->where('putusan', 'menunggu')->value('user_id'))->toBeNull();
    });

    it('menautkan surat dan proposal sah, membuang URL tak sah, dan mencatat nama berkas tanpa URL', function () {
        imporLengkap($this->fx, $this->admin);

        expect(TautanBerkas::where('pemilik_id', lama('PR-2026-001')->id)->pluck('jenis')->sort()->values()->all())->toBe(['proposal', 'surat_permohonan'])
            ->and(TautanBerkas::where('pemilik_id', lama('PR-2026-002')->id)->count())->toBe(0)
            ->and(ImporLog::where('sheet', 'Requests')->where('id_lama', 'PR-2026-002')->value('pesan'))->toContain('Nama berkas surat tanpa URL');
    });

    it('mengarsipkan naskah dari nomor surat terbit dan membuat register melanjutkan dari nomor maksimum', function () {
        imporLengkap($this->fx, $this->admin);
        $p = lama('PR-2026-001');

        $naskah = Naskah::where('permohonan_id', $p->id)->orderBy('nomor')->get();
        expect($naskah->pluck('nomor')->all())->toBe(['321/UN58.10/KM.03.02/2026', '322/UN58.10/KM.03.02/2026'])
            ->and($naskah->pluck('status.value')->unique()->all())->toBe(['terbit'])->and($naskah[0]->snapshot)->toBe(['migrasi' => true])->and($naskah[0]->hash_pdf)->toBeNull()
            ->and($naskah[0]->tanggal_naskah)->not->toBeNull()->and($naskah[0]->nomor_terpakai_id)->not->toBeNull()
            ->and($p->fresh()->naskah_izin_id)->toBe($naskah[0]->id)
            ->and(TautanBerkas::where('pemilik_id', $naskah[0]->id)->value('jenis'))->toBe('naskah_basah')
            ->and(NomorTerpakai::whereIn('urut', [321, 322])->count())->toBe(2);

        $register = RegisterNomor::firstWhere('kode', 'naskah-dekan');
        $berikut = app(AmbilNomorBerikutnya::class)->jalankan($register, $this->admin, ['klasifikasi' => 'KM.03.02', 'kode_unit' => 'UN58.10'], now()->setDate(2026, 10, 1));
        expect($berikut->urut)->toBe(323);
    });

    it('naskah arsip tidak dapat diubah dan nomornya tidak ganda', function () {
        imporLengkap($this->fx, $this->admin);

        $n = Naskah::where('nomor', '321/UN58.10/KM.03.02/2026')->firstOrFail();

        expect(fn () => $n->forceFill(['nomor' => 'lain'])->save())->toThrow(LogicException::class);
    });
});

describe('LPJ dan nilai', function () {
    it('mengimpor LPJ dengan nilai per penilai, penilai = pemangku jabatan, dan nilai akhir cocok', function () {
        imporLengkap($this->fx, $this->admin);

        $lpj = Lpj::where('sumber_id_lama', 'LAP-PR-2026-001')->firstOrFail();
        $nilai = NilaiLpj::where('lpj_id', $lpj->id)->with(['rubrik', 'jabatan'])->get();
        $kunci = fn (string $jabatan, string $rubrik) => (float) $nilai->first(fn ($n) => $n->jabatan->kode === $jabatan && $n->rubrik->kode === $rubrik)->nilai;

        expect($lpj->permohonan_id)->toBe(lama('PR-2026-001')->id)->and($lpj->status)->toBe('dinilai')->and((float) $lpj->nilai_akhir)->toBe(82.0)->and($nilai)->toHaveCount(8)
            ->and($kunci('dekan', 'ketepatan'))->toBe(18.0)->and($kunci('wd-akademik', 'kepatuhan'))->toBe(5.0)->and($kunci('wd-kemahasiswaan', 'ketepatan'))->toBe(16.0)
            ->and($kunci('kasubag-umum', 'kelengkapan'))->toBe(15.0)->and($kunci('kasubag-umum', 'kontribusi_fakultas'))->toBe(4.0)
            ->and($nilai->first(fn ($n) => $n->jabatan->kode === 'dekan')->penilai_user_id)->toBe(User::firstWhere('email', 'dekan.fkip@unsil.ac.id')->id)
            ->and($lpj->jumlah_peserta)->toBe(120)->and($lpj->diajukan_pada->toDateString())->toBe('2026-03-20')->and($lpj->batas_waktu->toDateString())->toBe('2026-03-25')
            ->and($lpj->tautan_instagram)->toBe('https://www.instagram.com/p/AbC123/')->and($lpj->tautan_video)->toBeNull()
            ->and(TautanBerkas::where('pemilik_id', $lpj->id)->value('jenis'))->toBe('lpj');
    });

    it('nilai tidak lengkap atau di luar rentang tidak memberi nilai akhir, dan LPJ tanpa permohonan galat', function () {
        imporLengkap($this->fx, $this->admin);

        $lpj = Lpj::where('sumber_id_lama', 'LAP-PR-2026-003')->firstOrFail();

        expect($lpj->status)->toBe('diajukan')->and($lpj->nilai_akhir)->toBeNull()->and(NilaiLpj::where('lpj_id', $lpj->id)->count())->toBe(1)
            ->and(ImporLog::where('sheet', 'Laporan')->where('id_lama', 'LAP-PR-2026-003')->value('pesan'))->toContain('di luar 0..5')
            ->and(statusLog('Laporan', 'LAP-PR-HANTU'))->toBe('galat');
    });
});

describe('kabar dan galeri', function () {
    it('mengimpor kabar dengan status terpetakan, isi disanitasi, dan hanya yang terbit tampil publik', function () {
        imporLengkap($this->fx, $this->admin);

        $b1 = Kabar::where('sumber_id_lama', 'B1')->firstOrFail();

        expect($b1->status)->toBe('terbit')->and($b1->isi)->toContain('Isi aman')->and($b1->isi)->not->toContain('script')->not->toContain('onerror')
            ->and($b1->tag)->toBe(['seminar', 'nasional'])->and($b1->terbit_pada->toDateString())->toBe('2026-03-03')->and($b1->penulis_id)->toBe($this->admin->id)
            ->and($b1->ormawa->nama)->toBe('HIMA Matematika')->and($b1->tautan()->where('jenis', 'foto')->count())->toBe(1)
            ->and(Kabar::where('sumber_id_lama', 'B2')->value('status'))->toBe('diajukan')
            ->and(Kabar::where('sumber_id_lama', 'B3')->first()->status)->toBe('ditolak')->and(Kabar::where('sumber_id_lama', 'B3')->first()->catatan_admin)->toBe('Kurang foto')
            ->and(Kabar::where('sumber_id_lama', 'B4')->first()->tautan()->count())->toBe(0)
            ->and(Kabar::where('sumber_id_lama', 'B5')->exists())->toBeFalse()->and(statusLog('Blogs', 'B5'))->toBe('lewati')
            ->and(Kabar::terbit()->count())->toBe(2);
    });

    it('mengimpor galeri menurut tipe, memvalidasi tautan, dan menautkan permohonan', function () {
        imporLengkap($this->fx, $this->admin);

        expect(Galeri::where('sumber_id_lama', 'G1')->first()->tipe)->toBe('foto')->and(Galeri::where('sumber_id_lama', 'G1')->first()->permohonan_id)->toBe(lama('PR-2026-001')->id)
            ->and(Galeri::where('sumber_id_lama', 'G2')->first()->tipe)->toBe('instagram')
            ->and(Galeri::where('sumber_id_lama', 'G3')->first()->tipe)->toBe('video')->and(Galeri::where('sumber_id_lama', 'G3')->first()->aktif)->toBeFalse()
            ->and(Galeri::where('sumber_id_lama', 'G4')->exists())->toBeFalse()->and(statusLog('Galleries', 'G4'))->toBe('galat')
            ->and(Galeri::aktif()->count())->toBe(2);
    });
});

describe('keseluruhan', function () {
    it('idempoten: impor kedua tidak menggandakan apa pun dan tidak mengubah register nomor', function () {
        imporLengkap($this->fx, $this->admin);
        $hitung = fn () => [Permohonan::count(), RiwayatPermohonan::count(), PermohonanRuangan::count(), PemakaianRuanganLokal::count(), Disposisi::count(), PersetujuanWd::count(), Naskah::count(), NomorTerpakai::count(), Lpj::count(), NilaiLpj::count(), Kabar::count(), Galeri::count(), TautanBerkas::count()];
        $sebelum = $hitung();

        $k = imporLengkap($this->fx, $this->admin);

        expect($hitung())->toBe($sebelum)->and($k->jumlah('Requests', 'ok') + $k->jumlah('Requests', 'peringatan'))->toBe(0)
            ->and($k->jumlah('Laporan', 'ok') + $k->jumlah('Laporan', 'peringatan'))->toBe(0)
            ->and($k->jumlah('Blogs', 'ok') + $k->jumlah('Blogs', 'peringatan'))->toBe(0);
    });

    it('dry-run tidak meninggalkan apa pun dan register nomor tidak berubah', function () {
        imporLengkap($this->fx, $this->admin, dry: true);

        expect(Permohonan::count())->toBe(0)->and(NomorTerpakai::count())->toBe(0)->and(Naskah::count())->toBe(0)->and(Kabar::count())->toBe(0)->and(ImporLog::count())->toBe(0);
    });

    it('mewajibkan pelaksana impor bila ada sheet Requests/Blogs', function () {
        expect(fn () => app(ImporOrmawaHub::class)->jalankan($this->fx['xlsx'], $this->fx['pemetaan']))->toThrow(InvalidArgumentException::class, 'Pelaksana');
    });

    it('perintah artisan menerima --pelaksana dan mengakhiri dengan kode gagal karena baris galat', function () {
        $this->artisan('ormawahub:impor', ['xlsx' => $this->fx['xlsx'], '--pemetaan' => $this->fx['pemetaan'], '--pelaksana' => 'pelaksana@unsil.ac.id'])->assertFailed();
        expect(Permohonan::count())->toBe(4)->and(File::exists($this->fx['xlsx']))->toBeTrue();

        $this->artisan('ormawahub:impor', ['xlsx' => $this->fx['xlsx'], '--pemetaan' => $this->fx['pemetaan'], '--pelaksana' => 'tidak@ada.id'])->assertFailed();
    });

    it('mode aset_api: ruangan tidak diimpor dan roomId tak terpetakan ditolak sebelum menulis', function () {
        Pengaturan::set('layanan_ruangan', 'aset_api');
        OrmawaHubFixture::csv($this->fx['pemetaan'].'/ruangan.csv', ['id_lama', 'kode_ruangan'], [['R1', 'AULA-UTAMA']]);

        expect(fn () => imporLengkap($this->fx, $this->admin))->toThrow(PemetaanTidakLengkap::class, "roomId 'R3' tidak ada di ruangan.csv");

        OrmawaHubFixture::csv($this->fx['pemetaan'].'/ruangan.csv', ['id_lama', 'kode_ruangan'], [['R1', 'AULA-UTAMA'], ['R3', 'R-SEMINAR']]);
        imporLengkap($this->fx, $this->admin);

        expect(PemakaianRuanganLokal::count())->toBe(0)->and(PermohonanRuangan::where('kode_ruangan', 'AULA-UTAMA')->count())->toBe(5)
            ->and(ImporLog::where('sheet', 'Requests')->where('id_lama', 'PR-2026-005')->value('pesan'))->toContain('perlu dicatat di sistem Aset');
    });
});
