<?php

use App\Actions\Naskah\AjukanParaf;
use App\Actions\Naskah\SimpanDraf;
use App\Actions\Naskah\TandaTangani;
use App\Enums\StatusNaskah;
use App\Exceptions\ModeTidakDidukung;
use App\Livewire\Pimpinan\KotakMasuk;
use App\Models\Jabatan;
use App\Models\JenisNaskah;
use App\Models\KlasifikasiArsip;
use App\Models\Naskah;
use App\Models\NomorTerpakai;
use App\Models\PemangkuJabatan;
use App\Models\TautanBerkas;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\KlasifikasiArsipSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->withoutVite();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, PengaturanSeeder::class, KlasifikasiArsipSeeder::class]);
});

function pejabat(string $peran, string $kodeJabatan, array $atribut = [], bool $plt = false, ?string $mulai = null): User
{
    $u = User::factory()->create($atribut)->assignRole($peran);
    PemangkuJabatan::create(['jabatan_id' => Jabatan::firstWhere('kode', $kodeJabatan)->id, 'user_id' => $u->id, 'mulai' => $mulai ?? now()->subYear(), 'plt' => $plt]);

    return $u;
}

function siapTtd(array $ubah = [], string $kodeJenis = 'surat-dinas', ?User $penyusun = null): Naskah
{
    $penyusun ??= User::factory()->create()->assignRole('admin-persuratan');
    $jenis = JenisNaskah::firstWhere('kode', $kodeJenis);

    $n = app(SimpanDraf::class)->jalankan(null, $ubah + [
        'jenis_naskah_id' => $jenis->id, 'klasifikasi_arsip_id' => KlasifikasiArsip::firstWhere('kode', 'KM.03.02')->id,
        'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa', 'perihal' => 'Uji tanda tangan',
        'penanda_tangan_jabatan_id' => Jabatan::firstWhere('kode', 'dekan')->id, 'mode_tanda_tangan' => 'basah',
        'data' => ['tujuan' => 'Dinas'], 'tujuan' => [['nama' => 'Dinas']], 'isi' => '<p>Isi tetap</p>',
    ], $penyusun);

    return app(AjukanParaf::class)->jalankan($n, $penyusun, []);
}

it('membentuk nomor saat tanda tangan, bukan saat draf', function () {
    $dekan = pejabat('dekan', 'dekan');
    $n = siapTtd();

    expect($n->nomor)->toBeNull()->and(NomorTerpakai::count())->toBe(0);

    CarbonImmutable::setTestNow('2026-05-04 10:00:00');
    $n = app(TandaTangani::class)->jalankan($n, $dekan);

    expect($n->nomor)->toBe('1/UN58.10/KM.03.02/2026')
        ->and($n->tanggal_naskah->toDateString())->toBe('2026-05-04')
        ->and($n->status)->toBe(StatusNaskah::Ditandatangani)
        ->and($n->penanda_tangan_user_id)->toBe($dekan->id)
        ->and($n->nomor_terpakai_id)->not->toBeNull()
        ->and(NomorTerpakai::first()->pemilik->is($n))->toBeTrue()
        ->and($n->riwayat->pluck('ke_status')->all())->toContain('ditandatangani');
    CarbonImmutable::setTestNow();
});

it('memberi nomor berurutan pada register yang sama', function () {
    $dekan = pejabat('dekan', 'dekan');

    $a = app(TandaTangani::class)->jalankan(siapTtd(), $dekan);
    $b = app(TandaTangani::class)->jalankan(siapTtd(), $dekan);

    expect($a->nomor)->toStartWith('1/')->and($b->nomor)->toStartWith('2/');
});

it('menolak penanda tangan yang bukan pemangku jabatan', function (string $kasus) {
    $n = siapTtd();
    $pelaku = match ($kasus) {
        'tanpa jabatan' => User::factory()->create()->assignRole('dekan'),
        'jabatan lain' => pejabat('wakil-dekan', 'wd-akademik'),
        'pemangku berakhir' => (function () {
            $u = User::factory()->create()->assignRole('dekan');
            PemangkuJabatan::create(['jabatan_id' => Jabatan::firstWhere('kode', 'dekan')->id, 'user_id' => $u->id, 'mulai' => now()->subYears(3), 'selesai' => now()->subYear()]);

            return $u;
        })(),
        'penyusun' => User::factory()->create()->assignRole('admin-persuratan'),
    };

    expect(fn () => app(TandaTangani::class)->jalankan($n, $pelaku))->toThrow(AuthorizationException::class);
    expect($n->fresh()->nomor)->toBeNull()->and(NomorTerpakai::count())->toBe(0);
})->with(['tanpa jabatan', 'jabatan lain', 'pemangku berakhir', 'penyusun']);

it('mengizinkan Plt jabatan menandatangani', function () {
    $plt = pejabat('wakil-dekan', 'dekan', plt: true);

    $n = app(TandaTangani::class)->jalankan(siapTtd(), $plt);

    expect($n->penanda_tangan_user_id)->toBe($plt->id);
});

it('mengizinkan wakil dekan menandatangani a.n. dekan hanya bila naskah ber-a.n.', function () {
    $wd = pejabat('wakil-dekan', 'wd-akademik', ['name' => 'Wakil Satu', 'nip_nim' => '1980']);

    expect(fn () => app(TandaTangani::class)->jalankan(siapTtd(), $wd))->toThrow(AuthorizationException::class);

    $n = app(TandaTangani::class)->jalankan(siapTtd(['atas_nama' => 'an']), $wd);
    $k = $n->snapshot['penanda_tangan'];

    expect($n->atas_nama)->toBe('an')
        ->and($k['nama'])->toBe('Wakil Satu')->and($k['jabatan'])->toBe('Wakil Dekan Bidang Akademik')->and($k['jabatan_dasar'])->toBe('Dekan');
});

it('menolak a.n. bagi pengguna tanpa izin tanda tangan atau tanpa jabatan penanda tangan', function () {
    $kasubag = pejabat('kasubag', 'kasubag-umum');
    $wdTanpaJabatan = User::factory()->create()->assignRole('wakil-dekan');

    expect(fn () => app(TandaTangani::class)->jalankan(siapTtd(['atas_nama' => 'ub']), $kasubag))->toThrow(AuthorizationException::class)
        ->and(fn () => app(TandaTangani::class)->jalankan(siapTtd(['atas_nama' => 'ub']), $wdTanpaJabatan))->toThrow(AuthorizationException::class);
});

it('mewajibkan status menunggu tanda tangan dan tidak dapat ditandatangani dua kali', function () {
    $dekan = pejabat('dekan', 'dekan');
    $draf = app(SimpanDraf::class)->jalankan(null, [
        'jenis_naskah_id' => JenisNaskah::firstWhere('kode', 'surat-dinas')->id, 'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa', 'perihal' => 'x',
        'penanda_tangan_jabatan_id' => Jabatan::firstWhere('kode', 'dekan')->id, 'mode_tanda_tangan' => 'basah', 'data' => ['tujuan' => 'D'], 'tujuan' => [['nama' => 'D']],
    ], User::factory()->create()->assignRole('admin-persuratan'));

    expect(fn () => app(TandaTangani::class)->jalankan($draf, $dekan))->toThrow(ValidationException::class);

    $n = siapTtd();
    app(TandaTangani::class)->jalankan($n, $dekan);
    expect(fn () => app(TandaTangani::class)->jalankan($n, $dekan))->toThrow(ValidationException::class)
        ->and(NomorTerpakai::count())->toBe(1);
});

it('menolak mode yang tidak diizinkan jenis naskah dan mode TTE yang belum didukung', function () {
    $dekan = pejabat('dekan', 'dekan');
    $n = siapTtd();

    JenisNaskah::firstWhere('kode', 'surat-dinas')->update(['mode_tanda_tangan_diizinkan' => ['visual']]);
    expect(fn () => app(TandaTangani::class)->jalankan($n->fresh(), $dekan))->toThrow(ValidationException::class);

    JenisNaskah::firstWhere('kode', 'surat-dinas')->update(['mode_tanda_tangan_diizinkan' => ['basah', 'tte']]);
    $n->forceFill(['mode_tanda_tangan' => 'tte'])->saveQuietly();
    expect(fn () => app(TandaTangani::class)->jalankan($n->fresh(), $dekan))->toThrow(ModeTidakDidukung::class);
    expect(NomorTerpakai::count())->toBe(0);
});

it('mewajibkan gambar tanda tangan visual untuk mode visual', function () {
    $dekan = pejabat('dekan', 'dekan');
    $n = siapTtd(['mode_tanda_tangan' => 'visual']);

    expect(fn () => app(TandaTangani::class)->jalankan($n, $dekan))->toThrow(ValidationException::class);

    TautanBerkas::create([
        'pemilik_type' => $dekan->getMorphClass(), 'pemilik_id' => $dekan->id, 'jenis' => 'ttd_visual',
        'url' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view', 'ditambahkan_oleh' => $dekan->id,
    ]);

    expect(app(TandaTangani::class)->jalankan($n->fresh(), $dekan)->snapshot['mode'])->toBe('visual');
});

it('mewajibkan klasifikasi arsip bila pola nomor memakainya dan tidak menghabiskan nomor', function () {
    $dekan = pejabat('dekan', 'dekan');
    $n = siapTtd();
    $n->forceFill(['klasifikasi_arsip_id' => null])->saveQuietly();

    expect(fn () => app(TandaTangani::class)->jalankan($n->fresh(), $dekan))->toThrow(ValidationException::class);
    expect(NomorTerpakai::count())->toBe(0);

    $n->forceFill(['klasifikasi_arsip_id' => KlasifikasiArsip::firstWhere('kode', 'KM')->id])->saveQuietly();
    expect(app(TandaTangani::class)->jalankan($n->fresh(), $dekan)->nomor)->toStartWith('1/UN58.10/KM/');
});

it('membekukan snapshot dengan seluruh isi naskah', function () {
    $dekan = pejabat('dekan', 'dekan', ['name' => 'Prof. Dekan', 'nip_nim' => '19700101']);
    $n = app(TandaTangani::class)->jalankan(siapTtd(), $dekan);
    $s = $n->snapshot;

    expect($s)->toMatchArray(['nomor' => $n->nomor, 'perihal' => 'Uji tanda tangan', 'mode' => 'basah', 'versi_templat' => 1, 'templat' => 'naskah.surat-dinas', 'status' => 'ditandatangani'])
        ->and($s['data'])->toBe(['tujuan' => 'Dinas'])
        ->and($s['isi'])->toBe('<p>Isi tetap</p>')
        ->and($s['tujuan'])->toBe(['Dinas'])
        ->and($s['kop'])->toContain('UNIVERSITAS SILIWANGI')
        ->and($s['penanda_tangan'])->toMatchArray(['nama' => 'Prof. Dekan', 'nip' => '19700101', 'jabatan' => 'Dekan', 'jabatan_dasar' => null])
        ->and($s['pola_nomor'])->toContain('{urut}');
});

it('tidak mengubah snapshot saat pejabat berganti atau nama berubah', function () {
    $dekanLama = pejabat('dekan', 'dekan', ['name' => 'Dekan Lama']);
    $n = app(TandaTangani::class)->jalankan(siapTtd(), $dekanLama);
    $awal = $n->snapshot;

    $dekanLama->update(['name' => 'Nama Diubah']);
    PemangkuJabatan::where('user_id', $dekanLama->id)->update(['selesai' => now()->subDay()]);
    pejabat('dekan', 'dekan', ['name' => 'Dekan Baru'], mulai: now()->toDateString());
    Jabatan::firstWhere('kode', 'dekan')->update(['nama' => 'Dekan (nama jabatan baru)']);
    JenisNaskah::firstWhere('kode', 'surat-dinas')->update(['versi_templat' => 5]);

    $n = $n->fresh();
    expect($n->snapshot)->toBe($awal)->and($n->konteksRender()['penanda_tangan']['nama'])->toBe('Dekan Lama')
        ->and($n->konteksRender()['versi_templat'])->toBe(1);
});

it('menolak perubahan kolom immutable dan isi setelah ditandatangani', function (string $kolom, mixed $nilai) {
    $dekan = pejabat('dekan', 'dekan');
    $n = app(TandaTangani::class)->jalankan(siapTtd(), $dekan)->fresh();

    expect(fn () => $n->forceFill([$kolom => $nilai])->save())->toThrow(LogicException::class);
    expect($n->fresh()->getRawOriginal($kolom))->toBe($n->getRawOriginal($kolom));
})->with([
    ['nomor', '999/X'],
    ['tanggal_naskah', '2020-01-01'],
    ['snapshot', ['palsu' => true]],
    ['perihal', 'Dibajak'],
    ['isi', '<p>Diganti</p>'],
    ['penanda_tangan_jabatan_id', null],
    ['mode_tanda_tangan', 'visual'],
]);

it('tetap mengizinkan transisi status setelah ditandatangani', function () {
    $n = app(TandaTangani::class)->jalankan(siapTtd(), pejabat('dekan', 'dekan'))->fresh();

    $n->forceFill(['status' => StatusNaskah::Terbit, 'diterbitkan_pada' => now()])->save();
    $n->forceFill(['hash_pdf' => str_repeat('a', 64)])->save();

    expect($n->fresh()->status)->toBe(StatusNaskah::Terbit);
    expect(fn () => $n->fresh()->forceFill(['hash_pdf' => str_repeat('b', 64)])->save())->toThrow(LogicException::class);
});

it('menandatangani lewat komponen kotak masuk', function () {
    $dekan = pejabat('dekan', 'dekan');
    $n = siapTtd();

    Livewire::actingAs($dekan)->test(KotakMasuk::class)->call('pilihTab', 'naskah')->assertSee('Uji tanda tangan')
        ->call('tandatangani', $n->id)->assertHasNoErrors();

    expect($n->fresh()->status)->toBe(StatusNaskah::Ditandatangani)->and($n->fresh()->nomor)->not->toBeNull();

    Livewire::actingAs(User::factory()->create()->assignRole('kasubag'))->test(KotakMasuk::class)
        ->call('tandatangani', siapTtd()->id)->assertForbidden();
});
