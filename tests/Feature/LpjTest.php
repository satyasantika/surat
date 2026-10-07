<?php

use App\Actions\Lpj\IsiLpj;
use App\Actions\Permohonan\AjukanPermohonan;
use App\Actions\Permohonan\BuatLpj;
use App\Actions\Permohonan\TransisiPermohonan;
use App\Enums\StatusPermohonan;
use App\Livewire\Ormawa\Beranda;
use App\Livewire\Ormawa\IsiLpj as KomponenLpj;
use App\Models\JenisPermohonan;
use App\Models\Lpj;
use App\Models\Ormawa;
use App\Models\PengurusOrmawa;
use App\Models\Permohonan;
use App\Models\User;
use App\Rules\TautanMediaValid;
use App\Support\Pengaturan;
use Carbon\CarbonImmutable;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\JenisPermohonanSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->withoutVite();
    $this->seed([PeranDanIzinSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, JenisPermohonanSeeder::class]);
    CarbonImmutable::setTestNow('2026-06-01 09:00:00');
});

afterEach(fn () => CarbonImmutable::setTestNow());

function ormawaLpj(string $nama = 'HIMA Mat', string $jabatan = 'ketua'): array
{
    $o = Ormawa::create(['nama' => $nama, 'tingkat' => 'prodi']);
    $o->sk()->create(['nomor_sk' => "SK/{$nama}", 'tanggal_sk' => '2026-01-01', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2026-12-31']);
    $u = User::factory()->create(['nip_nim' => (string) random_int(1000000, 9999999)])->assignRole('pengurus-ormawa');
    PengurusOrmawa::create(['ormawa_id' => $o->id, 'user_id' => $u->id, 'nama' => $u->name, 'jabatan' => $jabatan]);
    RateLimiter::clear('ajukan-permohonan:'.$u->id);

    return [$o, $u];
}

/** Permohonan yang diselesaikan lewat TransisiPermohonan agar LPJ dibuat otomatis. */
function permohonanSelesai(Ormawa $o, User $u, string $mulai = '2026-05-10', string $selesai = '2026-05-11', string $jenis = 'kegiatan'): Permohonan
{
    CarbonImmutable::setTestNow('2026-04-01 09:00:00');
    $p = app(AjukanPermohonan::class)->jalankan($u, $o, JenisPermohonan::firstWhere('kode', $jenis), [
        'nama_kegiatan' => 'Seminar '.$mulai, 'perihal' => 'Izin', 'tanggal_mulai' => $mulai, 'tanggal_selesai' => $selesai, 'deskripsi' => 'D',
        'penanggung_jawab' => ['ketua' => ['nama' => 'B']],
        'berkas' => ['surat_permohonan' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view', 'proposal' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view'],
    ]);
    CarbonImmutable::setTestNow('2026-06-01 09:00:00');

    $p->forceFill(['status' => StatusPermohonan::Penerbitan])->saveQuietly();
    app(TransisiPermohonan::class)->dalamKunci($p, fn (Permohonan $s) => app(TransisiPermohonan::class)->ke($s, StatusPermohonan::Selesai, $u));

    return $p->fresh();
}

function dataLpj(array $ubah = []): array
{
    return $ubah + [
        'tanggal_pelaksanaan' => '2026-05-10', 'jumlah_peserta' => 120, 'ringkasan' => 'Kegiatan berjalan lancar.', 'kendala' => 'Cuaca', 'solusi' => 'Tenda',
        'rekomendasi' => 'Lanjutkan', 'tautan_instagram' => 'https://www.instagram.com/p/abc', 'tautan_video' => 'https://youtu.be/xyz',
        'berkas_lpj' => 'https://drive.google.com/file/d/1LpjLpjLpjLpjLpjLpj/view',
    ];
}

describe('pembuatan otomatis', function () {
    it('membuat LPJ draf saat permohonan selesai dengan batas waktu dari pengaturan', function () {
        [$o, $u] = ormawaLpj();

        $p = permohonanSelesai($o, $u, '2026-05-10', '2026-05-11');
        $lpj = Lpj::where('permohonan_id', $p->id)->firstOrFail();

        expect($lpj->status)->toBe('draf')->and($lpj->batas_waktu->toDateString())->toBe('2026-05-25')->and($lpj->nilai_akhir)->toBeNull();

        Pengaturan::set('batas_hari_lpj', 30);
        $p2 = permohonanSelesai($o, $u, '2026-05-12', '2026-05-12');
        expect(Lpj::where('permohonan_id', $p2->id)->first()->batas_waktu->toDateString())->toBe('2026-06-11');
    });

    it('tidak membuat LPJ untuk jenis tanpa LPJ dan tidak ganda', function () {
        [$o, $u] = ormawaLpj();
        $p = permohonanSelesai($o, $u, '2026-05-10', '2026-05-11', 'pengantar-proposal');

        expect(Lpj::count())->toBe(0);

        $kegiatan = permohonanSelesai($o, $u);
        app(BuatLpj::class)->jalankan($kegiatan);
        expect(Lpj::where('permohonan_id', $kegiatan->id)->count())->toBe(1)->and($p->exists)->toBeTrue();
    });
});

describe('IsiLpj', function () {
    it('mengajukan LPJ lengkap: status diajukan, tautan berkas tersimpan', function () {
        [$o, $u] = ormawaLpj();
        $lpj = Lpj::where('permohonan_id', permohonanSelesai($o, $u)->id)->first();

        app(IsiLpj::class)->jalankan($lpj, $u, dataLpj());
        $lpj = $lpj->fresh();

        expect($lpj->status)->toBe('diajukan')->and($lpj->diajukan_pada)->not->toBeNull()->and($lpj->jumlah_peserta)->toBe(120)
            ->and($lpj->tautan_instagram)->toBe('https://www.instagram.com/p/abc')
            ->and($lpj->tautan->pluck('jenis')->all())->toBe(['lpj'])->and($lpj->tautan[0]->url)->toContain('1LpjLpjLpj');
    });

    it('mewajibkan berkas LPJ dan isian utama', function (array $ubah) {
        [$o, $u] = ormawaLpj();
        $lpj = Lpj::where('permohonan_id', permohonanSelesai($o, $u)->id)->first();

        expect(fn () => app(IsiLpj::class)->jalankan($lpj, $u, $ubah + dataLpj()))->toThrow(ValidationException::class);
        expect($lpj->fresh()->status)->toBe('draf')->and($lpj->tautan()->count())->toBe(0);
    })->with([
        'tanpa berkas' => [['berkas_lpj' => '']],
        'berkas asing' => [['berkas_lpj' => 'https://evil.example.com/lpj.pdf']],
        'berkas folder' => [['berkas_lpj' => 'https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOpQr']],
        'ringkasan kosong' => [['ringkasan' => '']],
        'peserta negatif' => [['jumlah_peserta' => -1]],
        'tanggal sebelum kegiatan' => [['tanggal_pelaksanaan' => '2026-05-01']],
        'tanggal masa depan' => [['tanggal_pelaksanaan' => '2026-06-30']],
        'instagram bukan domain media' => [['tautan_instagram' => 'https://evil.example.com/p']],
        'video http' => [['tautan_video' => 'http://youtu.be/xyz']],
    ]);

    it('tautan media opsional dan hanya domain Instagram/YouTube', function (string $url, bool $lolos) {
        expect(Validator::make(['u' => $url], ['u' => [new TautanMediaValid]])->passes())->toBe($lolos);
    })->with([
        ['https://www.instagram.com/reel/abc', true], ['https://instagram.com/x', true], ['https://m.youtube.com/watch?v=1', true], ['https://youtu.be/abc', true],
        ['https://youtube.com.evil.com/x', false], ['https://fakeinstagram.com/x', false], ['https://tiktok.com/x', false], ['javascript:alert(1)', false], ['https://user:p@instagram.com/x', false],
    ]);

    it('LPJ tanpa tautan media tetap dapat diajukan', function () {
        [$o, $u] = ormawaLpj();
        $lpj = Lpj::where('permohonan_id', permohonanSelesai($o, $u)->id)->first();

        app(IsiLpj::class)->jalankan($lpj, $u, dataLpj(['tautan_instagram' => null, 'tautan_video' => null]));

        expect($lpj->fresh()->status)->toBe('diajukan')->and($lpj->fresh()->tautan_instagram)->toBeNull();
    });

    it('menolak ormawa lain, bukan pengurus, dan LPJ yang sudah diajukan', function () {
        [$o, $u] = ormawaLpj();
        [, $uLain] = ormawaLpj('Lain');
        $lpj = Lpj::where('permohonan_id', permohonanSelesai($o, $u)->id)->first();

        expect(fn () => app(IsiLpj::class)->jalankan($lpj, $uLain, dataLpj()))->toThrow(AuthorizationException::class)
            ->and(fn () => app(IsiLpj::class)->jalankan($lpj, User::factory()->create()->assignRole('pegawai'), dataLpj()))->toThrow(AuthorizationException::class)
            ->and(fn () => app(IsiLpj::class)->jalankan($lpj, User::factory()->create()->assignRole('admin-persuratan'), dataLpj()))->toThrow(AuthorizationException::class);

        app(IsiLpj::class)->jalankan($lpj, $u, dataLpj());
        expect(fn () => app(IsiLpj::class)->jalankan($lpj->fresh(), $u, dataLpj(['ringkasan' => 'Diubah'])))->toThrow(AuthorizationException::class)
            ->and($lpj->fresh()->ringkasan)->toBe('Kegiatan berjalan lancar.');
    });
});

describe('blokir keterlambatan (BR-16)', function () {
    it('lpjTerlambat memperhitungkan batas waktu, toleransi, dan status', function (string $sekarang, int $toleransi, string $status, bool $terlambat) {
        [$o, $u] = ormawaLpj();
        $lpj = Lpj::where('permohonan_id', permohonanSelesai($o, $u, '2026-05-10', '2026-05-11')->id)->first(); // batas 25 Mei
        $lpj->forceFill(['status' => $status])->saveQuietly();
        Pengaturan::set('toleransi_lpj_hari', $toleransi);
        CarbonImmutable::setTestNow($sekarang);

        expect($o->fresh()->lpjTerlambat())->toBe($terlambat)->and($lpj->fresh()->terlambat())->toBe($status === 'draf' && $terlambat);
    })->with([
        'sebelum batas' => ['2026-05-24 10:00:00', 0, 'draf', false],
        'tepat batas' => ['2026-05-25 10:00:00', 0, 'draf', false],
        'sehari lewat' => ['2026-05-26 10:00:00', 0, 'draf', true],
        'lewat dalam toleransi' => ['2026-05-28 10:00:00', 5, 'draf', false],
        'lewat toleransi' => ['2026-06-01 10:00:00', 5, 'draf', true],
        'sudah diajukan' => ['2026-07-01 10:00:00', 0, 'diajukan', false],
        'sudah dinilai' => ['2026-07-01 10:00:00', 0, 'dinilai', false],
    ]);

    it('ormawa tanpa LPJ tidak terlambat', function () {
        [$o] = ormawaLpj();

        expect($o->lpjTerlambat())->toBeFalse();
    });

    it('memblokir pengajuan permohonan baru saat LPJ terlambat dan dapat dimatikan di pengaturan', function () {
        [$o, $u] = ormawaLpj();
        permohonanSelesai($o, $u, '2026-05-01', '2026-05-02'); // batas 16 Mei → terlambat pada 1 Juni
        $ajukan = fn () => app(AjukanPermohonan::class)->jalankan($u, $o->fresh(), JenisPermohonan::firstWhere('kode', 'kegiatan'), [
            'nama_kegiatan' => 'Baru', 'perihal' => 'Izin', 'tanggal_mulai' => '2026-07-10', 'tanggal_selesai' => '2026-07-10', 'deskripsi' => 'D',
            'penanggung_jawab' => ['ketua' => ['nama' => 'B']],
            'berkas' => ['surat_permohonan' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view', 'proposal' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view'],
        ]);

        expect($ajukan)->toThrow(ValidationException::class);

        Pengaturan::set('blokir_lpj_terlambat', false);
        expect($ajukan()->exists)->toBeTrue();
    });

    it('blokir hilang setelah LPJ diajukan', function () {
        [$o, $u] = ormawaLpj();
        $lpj = Lpj::where('permohonan_id', permohonanSelesai($o, $u, '2026-05-01', '2026-05-02')->id)->first();
        expect($o->lpjTerlambat())->toBeTrue();

        app(IsiLpj::class)->jalankan($lpj, $u, dataLpj(['tanggal_pelaksanaan' => '2026-05-01']));

        expect($o->fresh()->lpjTerlambat())->toBeFalse();
    });
});

describe('antarmuka LPJ', function () {
    it('mengisi dan mengajukan LPJ lewat formulir lalu menjadi hanya-baca', function () {
        [$o, $u] = ormawaLpj();
        $lpj = Lpj::where('permohonan_id', permohonanSelesai($o, $u)->id)->first();

        Livewire::actingAs($u)->test(KomponenLpj::class, ['ormawa' => $o->id, 'lpj' => $lpj->id])->assertSee('Ajukan LPJ')->assertSee('Seminar 2026-05-10')
            ->set('ringkasan', '')->call('kirim')->assertHasErrors();

        Livewire::actingAs($u)->test(KomponenLpj::class, ['ormawa' => $o->id, 'lpj' => $lpj->id])->set('tanggal_pelaksanaan', '2026-05-10')->set('jumlah_peserta', '50')->set('ringkasan', 'Sukses')->set('berkas_lpj', 'https://drive.google.com/file/d/1LpjLpjLpjLpjLpjLpj/view')
            ->call('kirim')->assertHasNoErrors()->assertRedirect(route('ormawa', ['o' => $o->id]));

        expect($lpj->fresh()->status)->toBe('diajukan');
        Livewire::actingAs($u)->test(KomponenLpj::class, ['ormawa' => $o->id, 'lpj' => $lpj->id])->assertDontSee('Ajukan LPJ')->assertSee('tidak dapat diubah');
    });

    it('menolak ormawa lain dan ketidakcocokan ormawa', function () {
        [$o, $u] = ormawaLpj();
        [$lain, $uLain] = ormawaLpj('Lain');
        $lpj = Lpj::where('permohonan_id', permohonanSelesai($o, $u)->id)->first();

        Livewire::actingAs($uLain)->test(KomponenLpj::class, ['ormawa' => $lain->id, 'lpj' => $lpj->id])->assertNotFound();
        Livewire::actingAs($uLain)->test(KomponenLpj::class, ['ormawa' => $o->id, 'lpj' => $lpj->id])->assertForbidden();
    });

    it('menampilkan LPJ jatuh tempo dan penanda terlambat di beranda ormawa', function () {
        [$o, $u] = ormawaLpj();
        permohonanSelesai($o, $u, '2026-05-01', '2026-05-02');

        Livewire::actingAs($u)->test(Beranda::class)->assertSee('Seminar 2026-05-01')->assertSee('Terlambat')->assertDontSee('Tidak ada LPJ yang jatuh tempo');
    });
});
