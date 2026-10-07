<?php

use App\Actions\Permohonan\AjukanPermohonan;
use App\Contracts\LayananRuangan;
use App\Enums\StatusPermohonan;
use App\Exceptions\LayananRuanganTidakTersedia;
use App\Models\JenisPermohonan;
use App\Models\Ormawa;
use App\Models\PengurusOrmawa;
use App\Models\Permohonan;
use App\Models\PermohonanRuangan;
use App\Models\RiwayatPermohonan;
use App\Models\RuanganLokal;
use App\Models\User;
use App\Services\Ruangan\LayananRuanganLokal;
use App\Support\Pengaturan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\JenisPermohonanSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->seed([PeranDanIzinSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, JenisPermohonanSeeder::class]);
    CarbonImmutable::setTestNow('2026-06-01 09:00:00');
    RateLimiter::clear('x');
});

afterEach(fn () => CarbonImmutable::setTestNow());

function ormawaAktifDenganPengurus(string $nama = 'HIMA Mat', string $jabatan = 'anggota'): array
{
    $o = Ormawa::create(['nama' => $nama, 'tingkat' => 'prodi']);
    $o->sk()->create(['nomor_sk' => "SK/{$nama}", 'tanggal_sk' => '2026-01-01', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2026-12-31']);
    $u = User::factory()->create(['nip_nim' => (string) random_int(4000000, 4999999)])->assignRole('pengurus-ormawa');
    PengurusOrmawa::create(['ormawa_id' => $o->id, 'user_id' => $u->id, 'nama' => $u->name, 'nim' => $u->nip_nim, 'jabatan' => $jabatan]);
    RateLimiter::clear('ajukan-permohonan:'.$u->id);

    return [$o, $u];
}

function dataPermohonan(array $ubah = [], string $jenis = 'kegiatan'): array
{
    $dasar = [
        'nama_kegiatan' => 'Seminar Nasional', 'perihal' => 'Izin kegiatan seminar', 'nomor_surat_ormawa' => '12/HIMA/2026',
        'tanggal_mulai' => '2026-06-20', 'tanggal_selesai' => '2026-06-21', 'jam_mulai' => '08:00', 'jam_selesai' => '16:00',
        'deskripsi' => 'Seminar tentang pendidikan matematika.',
        'penanggung_jawab' => ['ketua' => ['nama' => 'Budi', 'nim' => '2210001', 'hp' => '081200001111', 'prodi' => 'Matematika', 'email' => 'budi@student.unsil.ac.id'], 'liar' => ['x' => 1]],
        'berkas' => [
            'surat_permohonan' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view',
            'proposal' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view',
        ],
    ];

    if ($jenis === 'pengantar-proposal') {
        $dasar['berkas'] = ['proposal' => $dasar['berkas']['proposal']];
    }

    return $ubah + $dasar;
}

function jenisP(string $kode = 'kegiatan'): JenisPermohonan
{
    return JenisPermohonan::firstWhere('kode', $kode);
}

function ruanganData(string $kode = 'A101', string $tanggal = '2026-06-20', string $sesi = 'pagi'): array
{
    return ['ruangan' => [['kode' => $kode, 'tanggal' => $tanggal, 'sesi' => $sesi]]];
}

function ajukan(User $u, Ormawa $o, array $data = [], string $jenis = 'kegiatan'): Permohonan
{
    return app(AjukanPermohonan::class)->jalankan($u, $o, jenisP($jenis), $data + dataPermohonan(jenis: $jenis));
}

function ruangLokal(string $kode = 'A101'): RuanganLokal
{
    return RuanganLokal::create(['kode' => $kode, 'nama' => "Ruang {$kode}", 'kapasitas' => 50]);
}

describe('pengajuan dasar', function () {
    it('membuat permohonan bernomor PMH dengan status validasi admin, riwayat, dan tautan berkas', function () {
        [$o, $u] = ormawaAktifDenganPengurus();

        $p = ajukan($u, $o);

        expect($p->nomor)->toBe('PMH-2026-0001')->and($p->status)->toBe(StatusPermohonan::ValidasiAdmin)
            ->and($p->diajukan_oleh)->toBe($u->id)->and($p->ormawa_id)->toBe($o->id)->and($p->sumber)->toBe('aplikasi')
            ->and($p->tautan->pluck('jenis')->sort()->values()->all())->toBe(['proposal', 'surat_permohonan'])
            ->and($p->riwayat->map(fn ($r) => ($r->dari_status ?? '∅').'>'.$r->ke_status)->all())->toBe(['∅>diajukan', 'diajukan>validasi_admin'])
            ->and($p->ruangan)->toBeEmpty();
    });

    it('memberi nomor berurutan dan unik', function () {
        [$o, $u] = ormawaAktifDenganPengurus();

        expect(ajukan($u, $o)->nomor)->toBe('PMH-2026-0001')->and(ajukan($u, $o)->nomor)->toBe('PMH-2026-0002');
    });

    it('menyimpan penanggung jawab terenkripsi, hanya bidang dikenal, dan tersembunyi dari serialisasi', function () {
        [$o, $u] = ormawaAktifDenganPengurus();
        $p = ajukan($u, $o);

        $mentah = DB::table('permohonan')->where('id', $p->id)->value('penanggung_jawab');

        expect($mentah)->not->toContain('2210001')->not->toContain('081200001111')
            ->and($p->fresh()->penanggung_jawab)->toBe(['ketua' => ['nama' => 'Budi', 'nim' => '2210001', 'hp' => '081200001111', 'prodi' => 'Matematika', 'email' => 'budi@student.unsil.ac.id']])
            ->and($p->fresh()->toArray())->not->toHaveKey('penanggung_jawab');
    });

    it('mengarahkan ke persetujuan pembina bila diaktifkan dan ormawa punya pembina', function () {
        Pengaturan::set('persetujuan_pembina_aktif', true);
        [$o, $u] = ormawaAktifDenganPengurus('Berpembina');
        [$tanpa, $u2] = ormawaAktifDenganPengurus('Tanpa Pembina');
        $o->update(['pembina_user_id' => User::factory()->create()->assignRole('pembina-ormawa')->id]);

        expect(ajukan($u, $o)->status)->toBe(StatusPermohonan::PersetujuanPembina)
            ->and(ajukan($u2, $tanpa)->status)->toBe(StatusPermohonan::ValidasiAdmin);
    });

    it('menjalankan jenis pengantar proposal dengan berkas wajib berbeda', function () {
        [$o, $u] = ormawaAktifDenganPengurus();

        $p = ajukan($u, $o, jenis: 'pengantar-proposal');

        expect($p->tautan->pluck('jenis')->all())->toBe(['proposal']);
    });
});

describe('otorisasi dan blokir', function () {
    it('hanya pengurus aktif ormawa itu yang dapat mengajukan', function (string $kasus) {
        [$o, $pengurus] = ormawaAktifDenganPengurus('Milik A');
        [$lain, $penguruLain] = ormawaAktifDenganPengurus('Milik B');

        $pelaku = match ($kasus) {
            'ormawa lain' => $penguruLain,
            'pegawai' => User::factory()->create()->assignRole('pegawai'),
            'tanpa peran' => User::factory()->create(),
            'admin' => User::factory()->create()->assignRole('admin-persuratan'),
        };

        expect(fn () => ajukan($pelaku, $o))->toThrow(AuthorizationException::class);
        expect(Permohonan::count())->toBe(0)->and($pengurus->exists)->toBeTrue();
    })->with(['ormawa lain', 'pegawai', 'tanpa peran', 'admin']);

    it('menolak pengurus tanpa SK berlaku atau masa jabatan berakhir', function (string $kasus) {
        [$o, $u] = ormawaAktifDenganPengurus();

        if ($kasus === 'sk') {
            $o->sk()->delete();
            $o->sk()->create(['nomor_sk' => 'SK/lama', 'tanggal_sk' => '2024-01-01', 'periode_mulai' => '2024-01-01', 'periode_selesai' => '2024-12-31']);
        } elseif ($kasus === 'selesai') {
            PengurusOrmawa::where('user_id', $u->id)->update(['selesai' => '2026-05-31']);
        } else {
            $o->update(['aktif' => false]);
        }

        expect(fn () => ajukan($u->fresh(), $o->fresh()))->toThrow(AuthorizationException::class);
    })->with(['sk', 'selesai', 'ormawa nonaktif']);

    it('menolak ormawa yang diblokir LPJ, dan mengizinkannya bila kebijakan dinonaktifkan', function () {
        [$o, $u] = ormawaAktifDenganPengurus();
        $o->forceFill(['diblokir_lpj' => true, 'diblokir_sejak' => now()])->save();

        expect(fn () => ajukan($u, $o->fresh()))->toThrow(ValidationException::class);
        expect(Permohonan::count())->toBe(0);

        Pengaturan::set('blokir_lpj_terlambat', false);
        expect(ajukan($u, $o->fresh())->exists)->toBeTrue();
    });

    it('membatasi 10 percobaan per jam per pengguna lalu menolak', function () {
        [$o, $u] = ormawaAktifDenganPengurus();

        foreach (range(1, 10) as $i) {
            try {
                app(AjukanPermohonan::class)->jalankan($u, $o, jenisP(), ['nama_kegiatan' => '']);
            } catch (ValidationException) {
            }
        }

        expect(fn () => ajukan($u, $o))->toThrow(TooManyRequestsHttpException::class);

        [$o2, $u2] = ormawaAktifDenganPengurus('Lain');
        expect(ajukan($u2, $o2)->exists)->toBeTrue();
    });
});

describe('validasi isian', function () {
    it('menerapkan BR-15: terlalu dekat butuh alasan mendesak', function (string $mulai, ?string $alasan, bool $lolos) {
        [$o, $u] = ormawaAktifDenganPengurus();
        $data = ['tanggal_mulai' => $mulai, 'tanggal_selesai' => $mulai] + ($alasan !== null ? ['alasan_mendesak' => $alasan] : []);

        $aksi = fn () => ajukan($u, $o, $data);

        $lolos ? expect($aksi()->exists)->toBeTrue() : expect($aksi)->toThrow(ValidationException::class);
    })->with([
        'tepat 7 hari' => ['2026-06-08', null, true],
        'lebih dari 7 hari' => ['2026-06-30', null, true],
        '6 hari tanpa alasan' => ['2026-06-07', null, false],
        'besok tanpa alasan' => ['2026-06-02', '  ', false],
        '6 hari dengan alasan' => ['2026-06-07', 'Undangan mendadak dari dinas', true],
        'hari ini dengan alasan' => ['2026-06-01', 'Darurat', true],
    ]);

    it('mengikuti min_hari_sebelum_kegiatan dari pengaturan', function () {
        Pengaturan::set('min_hari_sebelum_kegiatan', 14);
        [$o, $u] = ormawaAktifDenganPengurus();

        expect(fn () => ajukan($u, $o, ['tanggal_mulai' => '2026-06-10', 'tanggal_selesai' => '2026-06-10']))->toThrow(ValidationException::class);
    });

    it('menolak isian wajib kosong, tanggal salah, jam terbalik, dan masa lalu', function (array $ubah) {
        [$o, $u] = ormawaAktifDenganPengurus();

        expect(fn () => ajukan($u, $o, $ubah))->toThrow(ValidationException::class);
        expect(Permohonan::count())->toBe(0);
    })->with([
        'nama kosong' => [['nama_kegiatan' => '']],
        'deskripsi kosong' => [['deskripsi' => '']],
        'tanpa ketua' => [['penanggung_jawab' => ['wakil' => ['nama' => 'X']]]],
        'selesai sebelum mulai' => [['tanggal_mulai' => '2026-06-21', 'tanggal_selesai' => '2026-06-20']],
        'masa lalu' => [['tanggal_mulai' => '2026-05-01', 'tanggal_selesai' => '2026-05-02', 'alasan_mendesak' => 'x']],
        'jam terbalik' => [['jam_mulai' => '16:00', 'jam_selesai' => '08:00']],
        'email penanggung jawab salah' => [['penanggung_jawab' => ['ketua' => ['nama' => 'B', 'email' => 'bukan-email']]]],
    ]);

    it('mewajibkan tautan berkas yang sah dan tidak menghabiskan nomor saat gagal', function (array $berkas) {
        [$o, $u] = ormawaAktifDenganPengurus();

        expect(fn () => ajukan($u, $o, ['berkas' => $berkas]))->toThrow(ValidationException::class);
        expect(Permohonan::count())->toBe(0);
        expect(ajukan($u, $o)->nomor)->toBe('PMH-2026-0001');
    })->with([
        'kosong' => [[]],
        'proposal saja' => [['proposal' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view']],
        'domain asing' => [['surat_permohonan' => 'https://evil.example.com/a.pdf', 'proposal' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view']],
        'folder' => [['surat_permohonan' => 'https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOpQr', 'proposal' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view']],
        'http' => [['surat_permohonan' => 'http://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view', 'proposal' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view']],
    ]);

    it('mewajibkan rincian fasilitas rektorat hanya untuk jenisnya', function () {
        [$o, $u] = ormawaAktifDenganPengurus();
        ruangLokal();
        $ruang = ruanganData(tanggal: '2026-06-20');

        expect(fn () => ajukan($u, $o, $ruang, 'kegiatan-ruangan-rektorat'))->toThrow(ValidationException::class)
            ->and(fn () => ajukan($u, $o, $ruang + ['fasilitas_rektorat' => [['nama' => 'Aula', 'jumlah' => 1]]], 'kegiatan-ruangan'))->toThrow(ValidationException::class)
            ->and(fn () => ajukan($u, $o, $ruang + ['fasilitas_rektorat' => [['nama' => '', 'jumlah' => 0]]], 'kegiatan-ruangan-rektorat'))->toThrow(ValidationException::class);

        $p = ajukan($u, $o, $ruang + ['fasilitas_rektorat' => [['nama' => 'Aula Rektorat', 'jumlah' => 1, 'keterangan' => 'Pagi']]], 'kegiatan-ruangan-rektorat');
        expect($p->fasilitas_rektorat)->toBe([['nama' => 'Aula Rektorat', 'jumlah' => 1, 'keterangan' => 'Pagi']]);
    });

    it('menolak jenis permohonan nonaktif', function () {
        [$o, $u] = ormawaAktifDenganPengurus();
        jenisP()->update(['aktif' => false]);

        expect(fn () => ajukan($u, $o))->toThrow(ValidationException::class);
    });
});

describe('ruangan', function () {
    it('menahan ruangan per tanggal×sesi dan memuat nama ruangan', function () {
        [$o, $u] = ormawaAktifDenganPengurus();
        ruangLokal();

        $p = ajukan($u, $o, ['ruangan' => [
            ['kode' => 'A101', 'tanggal' => '2026-06-20', 'sesi' => 'pagi'],
            ['kode' => 'A101', 'tanggal' => '2026-06-20', 'sesi' => 'siang'],
            ['kode' => 'A101', 'tanggal' => '2026-06-21', 'sesi' => 'seharian'],
        ]], 'kegiatan-ruangan');

        expect($p->ruangan)->toHaveCount(3)->and($p->ruangan->pluck('status')->unique()->all())->toBe(['ditahan'])
            ->and($p->ruangan->pluck('nama_ruangan')->unique()->all())->toBe(['Ruang A101']);
    });

    it('mewajibkan ruangan bagi jenis yang membutuhkannya dan mengabaikannya bagi yang tidak', function () {
        [$o, $u] = ormawaAktifDenganPengurus();
        ruangLokal();

        expect(fn () => ajukan($u, $o, [], 'kegiatan-ruangan'))->toThrow(ValidationException::class);

        $p = ajukan($u, $o, ruanganData());
        expect($p->ruangan)->toBeEmpty();
    });

    it('menolak ruangan tak dikenal, sesi tak dikenal, tanggal di luar rentang, dan butir saling bentrok', function (array $ruangan) {
        [$o, $u] = ormawaAktifDenganPengurus();
        ruangLokal();

        expect(fn () => ajukan($u, $o, ['ruangan' => $ruangan], 'kegiatan-ruangan'))->toThrow(ValidationException::class);
        expect(PermohonanRuangan::count())->toBe(0)->and(Permohonan::count())->toBe(0);
    })->with([
        'kode tak dikenal' => [[['kode' => 'ZZZ', 'tanggal' => '2026-06-20', 'sesi' => 'pagi']]],
        'sesi tak dikenal' => [[['kode' => 'A101', 'tanggal' => '2026-06-20', 'sesi' => 'malam']]],
        'sebelum rentang' => [[['kode' => 'A101', 'tanggal' => '2026-06-19', 'sesi' => 'pagi']]],
        'sesudah rentang' => [[['kode' => 'A101', 'tanggal' => '2026-06-22', 'sesi' => 'pagi']]],
        'format tanggal salah' => [[['kode' => 'A101', 'tanggal' => '20/06/2026', 'sesi' => 'pagi']]],
        'pagi dan seharian sama hari' => [[['kode' => 'A101', 'tanggal' => '2026-06-20', 'sesi' => 'pagi'], ['kode' => 'A101', 'tanggal' => '2026-06-20', 'sesi' => 'seharian']]],
        'dobel' => [[['kode' => 'A101', 'tanggal' => '2026-06-20', 'sesi' => 'pagi'], ['kode' => 'A101', 'tanggal' => '2026-06-20', 'sesi' => 'pagi']]],
    ]);

    it('menolak ruangan yang sudah dipakai di layanan, menyebut tanggal dan sesi', function () {
        [$o, $u] = ormawaAktifDenganPengurus();
        ruangLokal();
        (new LayananRuanganLokal)->catatPemakaian('A101', '2026-06-20', 'pagi', null, 'Kuliah');

        try {
            ajukan($u, $o, ruanganData(sesi: 'pagi'), 'kegiatan-ruangan');
            $this->fail('seharusnya bentrok');
        } catch (ValidationException $e) {
            expect($e->errors()['ruangan'][0])->toContain('Ruang A101')->toContain('20 Juni 2026')->toContain('pagi');
        }

        expect(ajukan($u, $o, ruanganData(sesi: 'siang'), 'kegiatan-ruangan')->ruangan)->toHaveCount(1);
    });

    it('menolak bentrok dengan permohonan lain yang menahan atau mengonfirmasi, tetapi bukan yang dilepas', function (string $statusLain, string $sesiLain, string $sesiBaru, bool $bentrok) {
        [$o1, $u1] = ormawaAktifDenganPengurus('Pertama');
        [$o2, $u2] = ormawaAktifDenganPengurus('Kedua');
        ruangLokal();

        $pertama = ajukan($u1, $o1, ruanganData(sesi: $sesiLain), 'kegiatan-ruangan');
        $pertama->ruangan()->update(['status' => $statusLain]);

        $aksi = fn () => ajukan($u2, $o2, ruanganData(sesi: $sesiBaru), 'kegiatan-ruangan');

        $bentrok ? expect($aksi)->toThrow(ValidationException::class) : expect($aksi()->ruangan)->toHaveCount(1);
    })->with([
        'ditahan sesi sama' => ['ditahan', 'pagi', 'pagi', true],
        'dikonfirmasi sesi sama' => ['dikonfirmasi', 'pagi', 'pagi', true],
        'ditahan seharian vs siang' => ['ditahan', 'seharian', 'siang', true],
        'ditahan pagi vs seharian' => ['ditahan', 'pagi', 'seharian', true],
        'ditahan pagi vs siang' => ['ditahan', 'pagi', 'siang', false],
        'dilepas sesi sama' => ['dilepas', 'pagi', 'pagi', false],
    ]);

    it('menggagalkan seluruh pengajuan bila satu butir bentrok (atomik) tanpa menghabiskan nomor', function () {
        [$o1, $u1] = ormawaAktifDenganPengurus('Pertama');
        [$o2, $u2] = ormawaAktifDenganPengurus('Kedua');
        ruangLokal();
        ajukan($u1, $o1, ruanganData(tanggal: '2026-06-21', sesi: 'pagi'), 'kegiatan-ruangan');

        expect(fn () => ajukan($u2, $o2, ['ruangan' => [
            ['kode' => 'A101', 'tanggal' => '2026-06-20', 'sesi' => 'pagi'],
            ['kode' => 'A101', 'tanggal' => '2026-06-21', 'sesi' => 'pagi'],
        ]], 'kegiatan-ruangan'))->toThrow(ValidationException::class);

        expect(Permohonan::where('ormawa_id', $o2->id)->count())->toBe(0)
            ->and(PermohonanRuangan::count())->toBe(1)
            ->and(ajukan($u2, $o2)->nomor)->toBe('PMH-2026-0002');
    });

    it('meneruskan galat bila layanan ruangan tidak tersedia dan tidak menyimpan apa pun', function () {
        [$o, $u] = ormawaAktifDenganPengurus();
        $gagal = new class implements LayananRuangan
        {
            public function daftar(): array
            {
                return [['kode' => 'A101', 'nama' => 'Ruang A101', 'gedung' => null, 'kapasitas' => null, 'fasilitas' => null]];
            }

            public function jadwal(string $kode, CarbonInterface $dari, CarbonInterface $sampai): array
            {
                throw new LayananRuanganTidakTersedia('Aset down');
            }

            public function catatPemakaian(string $kode, string $tanggal, string $sesi, ?string $referensi = null, ?string $keterangan = null): string
            {
                throw new LayananRuanganTidakTersedia('Aset down');
            }
        };
        app()->bind(LayananRuangan::class, fn () => $gagal);

        expect(fn () => ajukan($u, $o, ruanganData(), 'kegiatan-ruangan'))->toThrow(LayananRuanganTidakTersedia::class);
        expect(Permohonan::count())->toBe(0)->and(RiwayatPermohonan::count())->toBe(0);
    });
});

it('menjaga riwayat permohonan tidak dapat diubah atau dihapus', function () {
    [$o, $u] = ormawaAktifDenganPengurus();
    $r = ajukan($u, $o)->riwayat[0];

    expect(fn () => $r->update(['catatan' => 'x']))->toThrow(LogicException::class)->and(fn () => $r->delete())->toThrow(LogicException::class);
});
