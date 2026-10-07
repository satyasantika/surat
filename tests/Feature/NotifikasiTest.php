<?php

use App\Actions\Disposisi\BuatDisposisi;
use App\Actions\Disposisi\LaporTindakLanjut;
use App\Actions\Kabar\AjukanKabar;
use App\Actions\Kabar\SimpanKabar;
use App\Actions\Kabar\TerbitkanKabar;
use App\Actions\Kabar\TolakKabar;
use App\Actions\Lpj\IsiLpj;
use App\Actions\Lpj\NilaiLpj;
use App\Actions\Masuk\RegistrasiSuratMasuk;
use App\Actions\Naskah\AjukanParaf;
use App\Actions\Naskah\KembalikanNaskah;
use App\Actions\Naskah\ParafiNaskah;
use App\Actions\Naskah\SimpanDraf;
use App\Actions\Notifikasi\SimpanPreferensiNotifikasi;
use App\Actions\Permohonan\AjukanPermohonan;
use App\Actions\Permohonan\BatalkanPermohonan;
use App\Actions\Permohonan\DisposisiPermohonan;
use App\Actions\Permohonan\KembalikanPermohonan;
use App\Actions\Permohonan\PutusanWd;
use App\Actions\Permohonan\RekomendasiKasubag;
use App\Actions\Permohonan\ValidasiPermohonan;
use App\Contracts\GerbangWhatsapp;
use App\Models\DisposisiPenerima;
use App\Models\Jabatan;
use App\Models\JenisNaskah;
use App\Models\JenisPermohonan;
use App\Models\Lpj;
use App\Models\Ormawa;
use App\Models\PemangkuJabatan;
use App\Models\PengurusOrmawa;
use App\Models\Permohonan;
use App\Models\User;
use App\Notifications\NotifikasiTahap;
use App\Notifications\Saluran\SaluranWhatsapp;
use App\Services\Notifikasi\GerbangWhatsappHttp;
use App\Support\Pengaturan;
use Carbon\CarbonImmutable;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\JenisPermohonanSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\RubrikLpjSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->withoutVite();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, JenisPermohonanSeeder::class, RubrikLpjSeeder::class]);
    Notification::fake();
    CarbonImmutable::setTestNow('2026-06-01 09:00:00');
});

afterEach(fn () => CarbonImmutable::setTestNow());

function pejabatNotif(string $peran, ?string $kodeJabatan = null): User
{
    $u = User::factory()->create()->assignRole($peran);

    if ($kodeJabatan !== null) {
        PemangkuJabatan::create(['jabatan_id' => Jabatan::firstWhere('kode', $kodeJabatan)->id, 'user_id' => $u->id, 'mulai' => '2026-01-01']);
    }

    return $u;
}

/** @return array<int, NotifikasiTahap> notifikasi yang dikirim ke pengguna */
function terkirim(User $u, ?string $kategori = null): array
{
    return Notification::sent($u, NotifikasiTahap::class)->filter(fn ($n) => $kategori === null || $n->kategori === $kategori)->values()->all();
}

function judulTerkirim(User $u): array
{
    return array_map(fn ($n) => $n->judul, terkirim($u));
}

function semuaTeks(NotifikasiTahap $n, User $u): string
{
    return $n->judul."\n".$n->ringkas."\n".$n->toWhatsapp($u)."\n".json_encode($n->toDatabase($u))."\n".$n->toMail($u)->render();
}

describe('surat masuk dan disposisi', function () {
    function suratNotif(array $ubah = [], ?User $admin = null)
    {
        return app(RegistrasiSuratMasuk::class)->jalankan($ubah + [
            'nomor_surat' => '1/X/2026', 'tanggal_surat' => now()->toDateString(), 'asal' => 'Instansi', 'perihal' => 'Perihal Biasa Uji',
            'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa', 'pindaian_url' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view',
        ], $admin ?? pejabatNotif('admin-persuratan'));
    }

    it('memberitahu dekan saat surat masuk dicatat, bukan pencatat maupun peran lain', function () {
        $dekan = pejabatNotif('dekan', 'dekan');
        $admin = pejabatNotif('admin-persuratan');
        $wd = pejabatNotif('wakil-dekan', 'wd-akademik');

        suratNotif(admin: $admin);

        $n = terkirim($dekan, 'surat-masuk');
        expect($n)->toHaveCount(1)->and($n[0]->judul)->toBe('Surat masuk menunggu disposisi')->and($n[0]->ringkas)->toContain('Perihal Biasa Uji')->and($n[0]->url)->toBe(route('disposisi'))
            ->and(terkirim($admin))->toBe([])->and(terkirim($wd))->toBe([]);
    });

    it('surat rahasia tidak menyertakan perihal di surel, WhatsApp, maupun basis data', function (string $klasifikasi) {
        $dekan = pejabatNotif('dekan', 'dekan');

        suratNotif(['perihal' => 'RAHASIA-PERIHAL-XYZ', 'klasifikasi_keamanan' => $klasifikasi]);

        $n = terkirim($dekan, 'surat-masuk');
        expect($n)->toHaveCount(1)->and(semuaTeks($n[0], $dekan))->not->toContain('RAHASIA-PERIHAL-XYZ')->toContain($klasifikasi);
    })->with(['terbatas', 'rahasia', 'sangat_rahasia']);

    it('disposisi: diterima, diteruskan (penerima baru dan pemberi awal), dan tindak lanjut dilaporkan', function () {
        $dekan = pejabatNotif('dekan', 'dekan');
        $wd = pejabatNotif('wakil-dekan', 'wd-akademik');
        $kasubag = pejabatNotif('kasubag', 'kasubag-umum');
        $surat = suratNotif(['perihal' => 'RAHASIA-D', 'klasifikasi_keamanan' => 'rahasia']);

        $d = app(BuatDisposisi::class)->jalankan($surat, $dekan, [$wd->id], ['tindak_lanjuti']);

        expect(judulTerkirim($wd))->toBe(['Disposisi diterima'])->and(terkirim($dekan, 'disposisi'))->toBe([])
            ->and(semuaTeks(terkirim($wd)[0], $wd))->not->toContain('RAHASIA-D')->toContain($surat->nomor_agenda);

        $induk = $d->penerima->first();
        app(BuatDisposisi::class)->jalankan($surat, $wd, [$kasubag->id], ['tindak_lanjuti'], null, null, $induk);

        expect(judulTerkirim($kasubag))->toBe(['Disposisi diteruskan kepada Anda'])->and(judulTerkirim($dekan))->toBe(['Disposisi Anda diteruskan', 'Surat masuk menunggu disposisi'][0] === 'x' ? [] : judulTerkirim($dekan));
        expect(collect(judulTerkirim($dekan))->contains('Disposisi Anda diteruskan'))->toBeTrue();

        $penerimaKasubag = $kasubag->fresh()->hasMany(DisposisiPenerima::class)->first();
        app(LaporTindakLanjut::class)->jalankan($penerimaKasubag, $kasubag, 'Sudah ditindaklanjuti');

        expect(collect(judulTerkirim($wd))->contains('Tindak lanjut disposisi dilaporkan'))->toBeTrue();
    });
});

describe('naskah', function () {
    function drafNotif(User $penyusun, string $keamanan = 'biasa')
    {
        $jenis = JenisNaskah::firstWhere('kode', 'surat-dinas');

        return app(SimpanDraf::class)->jalankan(null, [
            'jenis_naskah_id' => $jenis->id, 'klasifikasi_keamanan' => $keamanan, 'derajat_kecepatan' => 'biasa', 'perihal' => 'Perihal Naskah Uji',
            'penanda_tangan_jabatan_id' => $jenis->jabatan_penanda_tangan_bawaan_id, 'mode_tanda_tangan' => 'basah',
            'data' => ['tujuan' => 'Dinas'], 'tujuan' => [['nama' => 'Dinas']],
        ], $penyusun);
    }

    it('memberitahu pemaraf berurutan, penanda tangan, lalu penyusun saat dikembalikan', function () {
        $penyusun = pejabatNotif('admin-persuratan');
        $a = pejabatNotif('kasubag');
        $b = pejabatNotif('wakil-dekan');
        $jenis = JenisNaskah::firstWhere('kode', 'surat-dinas');
        $ttd = pejabatNotif('dekan', Jabatan::find($jenis->jabatan_penanda_tangan_bawaan_id)->kode);

        $n = app(AjukanParaf::class)->jalankan(drafNotif($penyusun), $penyusun, [$a->id, $b->id]);
        expect(judulTerkirim($a))->toBe(['Naskah menunggu paraf Anda'])->and(judulTerkirim($b))->toBe([]);

        app(ParafiNaskah::class)->jalankan($n, $a);
        expect(judulTerkirim($b))->toBe(['Naskah menunggu paraf Anda'])->and(judulTerkirim($ttd))->toBe([]);

        app(ParafiNaskah::class)->jalankan($n->fresh(), $b);
        expect(judulTerkirim($ttd))->toBe(['Naskah menunggu tanda tangan Anda']);

        app(KembalikanNaskah::class)->jalankan($n->fresh(), $ttd, 'Perbaiki lampiran');
        expect(judulTerkirim($penyusun))->toBe(['Naskah dikembalikan'])->and(terkirim($penyusun)[0]->url)->toContain('/naskah');
    });

    it('naskah rahasia tidak membocorkan perihal di kanal mana pun', function () {
        $penyusun = pejabatNotif('admin-persuratan');
        $a = pejabatNotif('kasubag');

        app(AjukanParaf::class)->jalankan(drafNotif($penyusun, 'rahasia'), $penyusun, [$a->id]);

        expect(semuaTeks(terkirim($a)[0], $a))->not->toContain('Perihal Naskah Uji')->toContain('rahasia');
    });
});

describe('permohonan ormawa', function () {
    function permohonanNotif(): array
    {
        $o = Ormawa::create(['nama' => 'HIMA Notif', 'tingkat' => 'prodi']);
        $o->sk()->create(['nomor_sk' => 'SK/1', 'tanggal_sk' => '2026-01-01', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2026-12-31']);
        $u = User::factory()->create(['nip_nim' => (string) random_int(1000000, 9999999)])->assignRole('pengurus-ormawa');
        PengurusOrmawa::create(['ormawa_id' => $o->id, 'user_id' => $u->id, 'nama' => $u->name, 'jabatan' => 'ketua']);
        RateLimiter::clear('ajukan-permohonan:'.$u->id);

        $p = app(AjukanPermohonan::class)->jalankan($u, $o, JenisPermohonan::firstWhere('kode', 'kegiatan'), [
            'nama_kegiatan' => 'Seminar Notif', 'perihal' => 'Izin', 'tanggal_mulai' => '2026-06-20', 'tanggal_selesai' => '2026-06-21', 'deskripsi' => 'D',
            'penanggung_jawab' => ['ketua' => ['nama' => 'B']],
            'berkas' => ['surat_permohonan' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view', 'proposal' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view'],
        ]);

        return [$p, $o, $u];
    }

    it('menyalurkan setiap tahap ke pihak berikutnya dan ormawa sepanjang alur', function () {
        $admin = pejabatNotif('admin-persuratan');
        $dekan = pejabatNotif('dekan', 'dekan');
        $wd1 = pejabatNotif('wakil-dekan', 'wd-akademik');
        $wd3 = pejabatNotif('wakil-dekan', 'wd-kemahasiswaan');
        $kasubag = pejabatNotif('kasubag', 'kasubag-umum');
        $penerbit = pejabatNotif('admin-persuratan');

        [$p, $o, $pengurus] = permohonanNotif();
        expect(judulTerkirim($admin))->toBe(['Permohonan baru menunggu validasi'])->and(judulTerkirim($penerbit))->toBe(['Permohonan baru menunggu validasi'])
            ->and(terkirim($pengurus))->toBe([]);

        app(ValidasiPermohonan::class)->jalankan($p, $admin);
        expect(judulTerkirim($dekan))->toBe(['Permohonan menunggu disposisi Dekan'])->and(judulTerkirim($pengurus))->toBe(['Permohonan: Disposisi dekan'])
            ->and(terkirim($pengurus)[0]->url)->toBe(route('ormawa.permohonan', $o));

        app(DisposisiPermohonan::class)->jalankan($p->fresh(), $dekan, Jabatan::whereIn('kode', ['wd-akademik', 'wd-kemahasiswaan'])->pluck('id')->all());
        expect(judulTerkirim($wd1))->toBe(['Permohonan menunggu keputusan Anda'])->and(judulTerkirim($wd3))->toBe(['Permohonan menunggu keputusan Anda']);

        app(PutusanWd::class)->jalankan($p->fresh(), $wd1, 'setuju');
        expect(judulTerkirim($kasubag))->toBe([]);
        app(PutusanWd::class)->jalankan($p->fresh(), $wd3, 'setuju');
        expect(judulTerkirim($kasubag))->toBe(['Permohonan menunggu rekomendasi Kasubag']);

        app(RekomendasiKasubag::class)->jalankan($p->fresh(), $kasubag);
        expect(collect(judulTerkirim($penerbit))->contains('Permohonan siap diterbitkan suratnya'))->toBeTrue()
            ->and(collect(judulTerkirim($pengurus))->last())->toBe('Permohonan: Penerbitan');
    });

    it('pengembalian dan penolakan memberi tahu ormawa dengan nomor permohonan', function () {
        $admin = pejabatNotif('admin-persuratan');
        [$p, , $pengurus] = permohonanNotif();

        app(KembalikanPermohonan::class)->jalankan($p, $admin, 'Lengkapi proposal');

        $n = terkirim($pengurus)[0];
        expect($n->judul)->toBe('Permohonan: Dikembalikan')->and($n->ringkas)->toContain($p->fresh()->nomor)->toContain('Seminar Notif')->toContain('HIMA Notif');
    });

    it('pelaku tidak menerima notifikasi atas aksinya sendiri', function () {
        $admin = pejabatNotif('admin-persuratan');
        [$p, , $pengurus] = permohonanNotif();

        app(KembalikanPermohonan::class)->jalankan($p, $admin, 'x');
        app(BatalkanPermohonan::class)->jalankan($p->fresh(), $pengurus, 'Batal');

        expect(collect(judulTerkirim($pengurus))->contains('Permohonan: Dibatalkan'))->toBeFalse()->and(collect(judulTerkirim($admin))->contains('Permohonan: Dikembalikan'))->toBeFalse();
    });
});

describe('LPJ dan kabar', function () {
    it('LPJ diajukan memberitahu penilai; nilai akhir memberitahu ormawa', function () {
        $o = Ormawa::create(['nama' => 'HIMA Lpj', 'tingkat' => 'prodi']);
        $o->sk()->create(['nomor_sk' => 'SK/1', 'tanggal_sk' => '2026-01-01', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2026-12-31']);
        $u = User::factory()->create(['nip_nim' => '1234567'])->assignRole('pengurus-ormawa');
        PengurusOrmawa::create(['ormawa_id' => $o->id, 'user_id' => $u->id, 'nama' => $u->name, 'jabatan' => 'ketua']);
        $dekan = pejabatNotif('dekan', 'dekan');
        $wd1 = pejabatNotif('wakil-dekan', 'wd-akademik');
        $wd3 = pejabatNotif('wakil-dekan', 'wd-kemahasiswaan');
        $kasubag = pejabatNotif('kasubag', 'kasubag-umum');

        $p = (new Permohonan)->forceFill([
            'nomor' => 'PMH-2026-9001', 'ormawa_id' => $o->id, 'jenis_permohonan_id' => JenisPermohonan::firstWhere('kode', 'kegiatan')->id, 'diajukan_oleh' => $u->id,
            'nama_kegiatan' => 'Seminar LPJ', 'perihal' => 'Izin', 'tanggal_mulai' => '2026-05-10', 'tanggal_selesai' => '2026-05-11', 'deskripsi' => 'D',
            'penanggung_jawab' => ['ketua' => ['nama' => 'B']], 'status' => 'selesai', 'diajukan_pada' => now(),
        ]);
        $p->saveQuietly();
        $lpj = new Lpj;
        $lpj->forceFill(['permohonan_id' => $p->id, 'batas_waktu' => '2026-05-25'])->save();

        app(IsiLpj::class)->jalankan($lpj, $u, ['tanggal_pelaksanaan' => '2026-05-10', 'jumlah_peserta' => 10, 'ringkasan' => 'Sukses', 'berkas_lpj' => 'https://drive.google.com/file/d/1LpjLpjLpjLpjLpjLpj/view']);

        foreach ([$dekan, $wd1, $wd3, $kasubag] as $penilai) {
            expect(judulTerkirim($penilai))->toBe(['LPJ menunggu penilaian']);
        }
        expect(terkirim($u, 'lpj'))->toBe([]);

        foreach ([[$dekan, ['ketepatan' => ['nilai' => 20], 'kepatuhan' => ['nilai' => 5]]], [$wd1, ['ketepatan' => ['nilai' => 20], 'kepatuhan' => ['nilai' => 5]]], [$wd3, ['ketepatan' => ['nilai' => 20], 'kepatuhan' => ['nilai' => 5]]], [$kasubag, ['kelengkapan' => ['nilai' => 20], 'kontribusi_fakultas' => ['nilai' => 5]]]] as [$penilai, $isian]) {
            expect(terkirim($u, 'lpj'))->toBe([]);
            app(NilaiLpj::class)->jalankan($lpj->fresh(), $penilai, $isian);
        }

        $n = terkirim($u, 'lpj');
        expect($n)->toHaveCount(1)->and($n[0]->judul)->toBe('LPJ telah dinilai')->and($n[0]->ringkas)->toContain('100');
    });

    it('kabar: diajukan ke admin, disetujui/ditolak ke penulis', function () {
        $admin = pejabatNotif('admin-persuratan');
        $o = Ormawa::create(['nama' => 'HIMA Kabar N', 'tingkat' => 'prodi']);
        $o->sk()->create(['nomor_sk' => 'SK/1', 'tanggal_sk' => '2026-01-01', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2026-12-31']);
        $u = User::factory()->create(['nip_nim' => '7654321'])->assignRole('pengurus-ormawa');
        PengurusOrmawa::create(['ormawa_id' => $o->id, 'user_id' => $u->id, 'nama' => $u->name, 'jabatan' => 'ketua']);

        $k = app(SimpanKabar::class)->jalankan($u, ['ormawa_id' => $o->id, 'judul' => 'Kabar Notif', 'isi' => '<p>x</p>']);
        app(AjukanKabar::class)->jalankan($k, $u);
        expect(judulTerkirim($admin))->toBe(['Kabar menunggu persetujuan']);

        app(TolakKabar::class)->jalankan($k->fresh(), $admin, 'Tambah foto');
        $n = terkirim($u, 'kabar');
        expect($n[0]->judul)->toBe('Kabar Anda ditolak')->and($n[0]->ringkas)->toContain('Tambah foto')->and($n[0]->url)->toBe(route('ormawa.kabar', $o));

        app(AjukanKabar::class)->jalankan($k->fresh(), $u);
        app(TerbitkanKabar::class)->jalankan($k->fresh(), $admin);
        expect(collect(judulTerkirim($u))->last())->toBe('Kabar Anda disetujui dan terbit');
    });
});

describe('kanal dan preferensi', function () {
    it('selalu database; surel menurut preferensi; WhatsApp hanya bila aktif, dipilih, dan bernomor sah', function () {
        $u = User::factory()->create(['telepon' => '081234567890']);
        $n = new NotifikasiTahap('naskah', 'Judul', 'Isi', 'https://x.test/a');

        expect($n->via($u))->toBe(['database', 'mail']);

        $u->forceFill(['preferensi_notifikasi' => ['naskah' => ['mail' => false, 'whatsapp' => true]]])->save();
        expect($n->via($u->fresh()))->toBe(['database']);

        Pengaturan::set('wa_aktif', true);
        expect($n->via($u->fresh()))->toBe(['database', SaluranWhatsapp::class]);

        $u->forceFill(['telepon' => 'abc'])->save();
        expect($n->via($u->fresh()))->toBe(['database']);

        $u->forceFill(['telepon' => '0812', 'preferensi_notifikasi' => ['naskah' => ['mail' => true, 'whatsapp' => true]]])->save();
        expect($n->via($u->fresh()))->toBe(['database', 'mail']);
    });

    it('pengguna menyimpan preferensi dan nomor miliknya; masukan tak sah ditolak', function () {
        $u = User::factory()->create();

        app(SimpanPreferensiNotifikasi::class)->jalankan($u, ['telepon' => '0812 3456 7890', 'pref' => ['naskah' => ['mail' => '1'], 'lpj' => ['whatsapp' => '1', 'mail' => '1'], 'tidak-ada' => ['mail' => '1']]]);

        $u->refresh();
        expect($u->preferensiNotifikasi('naskah'))->toBe(['mail' => true, 'whatsapp' => false])->and($u->preferensiNotifikasi('lpj'))->toBe(['mail' => true, 'whatsapp' => true])
            ->and($u->preferensiNotifikasi('kabar'))->toBe(['mail' => false, 'whatsapp' => false])->and($u->telepon)->toBe('0812 3456 7890')
            ->and(array_keys($u->preferensi_notifikasi))->not->toContain('tidak-ada');

        expect(fn () => app(SimpanPreferensiNotifikasi::class)->jalankan($u, ['telepon' => '<script>']))->toThrow(ValidationException::class);
    });

    it('bawaan: surel aktif, WhatsApp mati', function () {
        expect(User::factory()->create()->preferensiNotifikasi('naskah'))->toBe(['mail' => true, 'whatsapp' => false]);
    });

    it('halaman profil menyimpan preferensi dan halaman notifikasi hanya menampilkan milik sendiri', function () {
        $u = User::factory()->create();
        $lain = User::factory()->create();
        $u->notifications()->create(['id' => Str::uuid()->toString(), 'type' => NotifikasiTahap::class, 'data' => ['title' => 'Milik saya', 'body' => 'Isi saya', 'url' => url('/profil')]]);
        $lain->notifications()->create(['id' => Str::uuid()->toString(), 'type' => NotifikasiTahap::class, 'data' => ['title' => 'Milik orang lain', 'body' => 'Rahasia lain']]);

        $this->actingAs($u)->put(route('profil.notifikasi'), ['telepon' => '081234567890', 'pref' => ['naskah' => ['mail' => '1']]])->assertRedirect();
        expect($u->fresh()->telepon)->toBe('081234567890');

        $this->actingAs($u)->get(route('notifikasi'))->assertOk()->assertSee('Milik saya')->assertDontSee('Milik orang lain')->assertSee('1 belum dibaca');
        $this->actingAs($u)->get(route('profil'))->assertOk()->assertSee('Simpan preferensi')->assertSee('Nomor WhatsApp');
    });

    it('membuka notifikasi menandainya dibaca dan hanya mengalihkan ke URL aplikasi sendiri', function () {
        Notification::fake();
        $u = User::factory()->create();
        $u->notifications()->create(['id' => Str::uuid()->toString(), 'type' => NotifikasiTahap::class, 'data' => ['title' => 'A', 'url' => url('/profil')]]);
        $u->notifications()->create(['id' => Str::uuid()->toString(), 'type' => NotifikasiTahap::class, 'data' => ['title' => 'B', 'url' => 'https://evil.example.com/x']]);
        $dalam = $u->notifications()->where('data->title', 'A')->first();
        $luar = $u->notifications()->where('data->title', 'B')->first();

        $this->actingAs($u)->get(route('notifikasi.buka', $dalam->id))->assertRedirect(url('/profil'));
        $this->actingAs($u)->get(route('notifikasi.buka', $luar->id))->assertRedirect(route('notifikasi'));
        expect($u->unreadNotifications()->count())->toBe(0);

        $lain = User::factory()->create();
        $this->actingAs($lain)->get(route('notifikasi.buka', $dalam->id))->assertNotFound();

        $u->notifications()->update(['read_at' => null]);
        $this->actingAs($u)->post(route('notifikasi.baca-semua'))->assertRedirect();
        expect($u->unreadNotifications()->count())->toBe(0);
    });
});

describe('gerbang WhatsApp', function () {
    it('mengirim ringkasan dan tautan saja lewat HTTP dengan token Bearer; gagal tidak melempar', function () {
        Http::fake(['gw.test/*' => Http::sequence()->push(['ok' => true], 200)->push('x', 500)->pushFailedConnection('mati')]);
        $g = new GerbangWhatsappHttp('https://gw.test/send', 'token-rahasia', 5);

        expect($g->tersedia())->toBeTrue()->and($g->kirim('62812345678', "Judul\nIsi\nhttps://x.test"))->toBeTrue();
        Http::assertSent(fn ($r) => $r->url() === 'https://gw.test/send' && $r->hasHeader('Authorization', 'Bearer token-rahasia') && $r['target'] === '62812345678' && $r['message'] === "Judul\nIsi\nhttps://x.test");

        Http::fake(['gw.test/*' => Http::response('x', 500)]);
        expect($g->kirim('62812345678', 'x'))->toBeFalse();
        Http::fake(['gw.test/*' => fn () => throw new ConnectionException('mati')]);
        expect($g->kirim('62812345678', 'x'))->toBeFalse()->and((new GerbangWhatsappHttp(null, null))->tersedia())->toBeFalse();
    });

    it('saluran menormalkan nomor dan mengirim toWhatsapp; tanpa nomor sah tidak mengirim', function () {
        $gerbang = Mockery::mock(GerbangWhatsapp::class);
        $gerbang->shouldReceive('tersedia')->andReturn(true);
        $gerbang->shouldReceive('kirim')->once()->with('6281234567890', Mockery::on(fn ($p) => str_contains($p, 'Judul') && str_contains($p, 'https://x.test/a')))->andReturnTrue();
        $saluran = new SaluranWhatsapp($gerbang);
        $n = new NotifikasiTahap('naskah', 'Judul', 'Isi', 'https://x.test/a');

        $saluran->send(User::factory()->create(['telepon' => '0812-3456-7890']), $n);
        $saluran->send(User::factory()->create(['telepon' => '12']), $n);

        expect(SaluranWhatsapp::normalisasi('+62 812 3456 7890'))->toBe('6281234567890')->and(SaluranWhatsapp::normalisasi('0812'))->toBeNull()->and(SaluranWhatsapp::normalisasi('812345678901'))->toBe('62812345678901');
    });

    it('terpasang pada kontainer dari .env dan tidak tersedia tanpa konfigurasi', function () {
        config(['whatsapp.url' => null, 'whatsapp.token' => null]);

        expect(app(GerbangWhatsapp::class)->tersedia())->toBeFalse();

        config(['whatsapp.url' => 'https://gw.test/send', 'whatsapp.token' => 't']);
        expect(app(GerbangWhatsapp::class)->tersedia())->toBeTrue();
    });
});

it('kegagalan notifikasi tidak menggagalkan alur bisnis', function () {
    Notification::shouldReceive('send')->andThrow(new RuntimeException('surel mati'));
    $dekan = pejabatNotif('dekan', 'dekan');

    $surat = app(RegistrasiSuratMasuk::class)->jalankan([
        'nomor_surat' => '2/X/2026', 'tanggal_surat' => now()->toDateString(), 'asal' => 'Instansi', 'perihal' => 'Tetap tercatat',
        'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa', 'pindaian_url' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view',
    ], pejabatNotif('admin-persuratan'));

    expect($surat->fresh()->perihal)->toBe('Tetap tercatat');
});
