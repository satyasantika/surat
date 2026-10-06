<?php

use App\Actions\Disposisi\BuatDisposisi;
use App\Actions\Disposisi\LaporTindakLanjut;
use App\Actions\Disposisi\SelesaikanDisposisi;
use App\Actions\Disposisi\TandaiDibaca;
use App\Actions\Masuk\RegistrasiSuratMasuk;
use App\Enums\DerajatKecepatan;
use App\Enums\StatusDisposisiPenerima;
use App\Models\Aktivitas;
use App\Models\Disposisi;
use App\Models\DisposisiPenerima;
use App\Models\SuratMasuk;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->seed([PeranDanIzinSeeder::class, RegisterNomorSeeder::class]);
});

function orang(string $peran): User
{
    return User::factory()->create()->assignRole($peran);
}

function suratUji(array $ubah = []): SuratMasuk
{
    return app(RegistrasiSuratMasuk::class)->jalankan($ubah + [
        'nomor_surat' => '1/X/2026', 'tanggal_surat' => now()->toDateString(), 'asal' => 'Instansi', 'perihal' => 'Perihal uji',
        'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa',
        'pindaian_url' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view',
    ], orang('admin-persuratan'));
}

function disposisikan(SuratMasuk $surat, User $dekan, array $penerima, array $kw = []): Disposisi
{
    return app(BuatDisposisi::class)->jalankan(
        $surat, $dekan, collect($penerima)->pluck('id')->all(), $kw['instruksi'] ?? ['tindak_lanjuti'],
        $kw['catatan'] ?? null, $kw['batas'] ?? null, $kw['induk'] ?? null,
    );
}

it('memberi batas waktu bawaan menurut derajat kecepatan', function (string $derajat, string $diharapkan) {
    CarbonImmutable::setTestNow('2026-03-10 09:30:00');
    $surat = suratUji(['derajat_kecepatan' => $derajat]);

    $d = disposisikan($surat, orang('dekan'), [orang('wakil-dekan')]);

    expect($d->batas_waktu->format('Y-m-d H:i'))->toBe($diharapkan)
        ->and($d->sifat)->toBe($derajat);
    CarbonImmutable::setTestNow();
})->with([
    'sangat segera' => ['sangat_segera', '2026-03-10 23:59'],
    'segera' => ['segera', '2026-03-12 09:30'],
    'biasa' => ['biasa', '2026-03-17 09:30'],
]);

it('mengizinkan mengubah batas waktu tetapi tidak ke masa lalu', function () {
    $surat = suratUji();
    $dekan = orang('dekan');
    $wd = orang('wakil-dekan');

    $d = disposisikan($surat, $dekan, [$wd], ['batas' => now()->addDays(2)]);
    expect($d->batas_waktu->isSameDay(now()->addDays(2)))->toBeTrue();

    expect(fn () => disposisikan($surat, $dekan, [$wd], ['batas' => now()->subHour()]))->toThrow(ValidationException::class);
});

it('membuat disposisi dengan beberapa penerima, jabatan, dan mengubah status surat', function () {
    $surat = suratUji();
    $dekan = orang('dekan');
    [$a, $b] = [orang('wakil-dekan'), orang('kasubag')];

    $d = disposisikan($surat, $dekan, [$a, $b], ['instruksi' => ['tindak_lanjuti', 'hadiri'], 'catatan' => 'Segera']);

    expect($d->penerima)->toHaveCount(2)
        ->and($d->penerima->pluck('status')->unique()->all())->toBe([StatusDisposisiPenerima::Diterima])
        ->and($d->instruksi)->toBe(['tindak_lanjuti', 'hadiri'])
        ->and($d->dari_user_id)->toBe($dekan->id)
        ->and($surat->fresh()->status->value)->toBe('didisposisikan')
        ->and($d->induk_penerima_id)->toBeNull();
});

it('hanya dekan (atau super-admin) yang membuat disposisi awal', function (string $peran, bool $boleh) {
    $surat = suratUji();
    $aksi = fn () => disposisikan($surat, orang($peran), [orang('pegawai')]);

    $boleh ? expect($aksi()->exists)->toBeTrue() : expect($aksi)->toThrow(AuthorizationException::class);
})->with([
    ['dekan', true], ['super-admin', true],
    ['wakil-dekan', false], ['kasubag', false], ['admin-persuratan', false], ['pegawai', false], ['pengurus-ormawa', false],
]);

it('menolak penerima kosong, diri sendiri, tak dikenal, dan nonaktif', function (Closure $susun) {
    $surat = suratUji();
    $dekan = orang('dekan');
    [$penerima, $instruksi] = $susun($dekan);

    expect(fn () => app(BuatDisposisi::class)->jalankan($surat, $dekan, $penerima, $instruksi))->toThrow(ValidationException::class);
    expect(Disposisi::count())->toBe(0);
})->with([
    'kosong' => [fn () => [[], ['tindak_lanjuti']]],
    'diri sendiri' => [fn (User $d) => [[$d->id], ['tindak_lanjuti']]],
    'tak dikenal' => [fn () => [[(string) Str::uuid()], ['tindak_lanjuti']]],
    'nonaktif' => [fn () => [[User::factory()->create(['aktif' => false])->id], ['tindak_lanjuti']]],
    'instruksi dan catatan kosong' => [fn () => [[orang('pegawai')->id], []]],
    'instruksi tak dikenal' => [fn () => [[orang('pegawai')->id], ['hancurkan']]],
]);

it('menyusun rantai disposisi lanjutan', function () {
    $surat = suratUji();
    $dekan = orang('dekan');
    $wd = orang('wakil-dekan');
    $pegawai = orang('pegawai');

    $awal = disposisikan($surat, $dekan, [$wd]);
    $indukPenerima = $awal->penerima[0];
    $lanjutan = disposisikan($surat, $wd, [$pegawai], ['induk' => $indukPenerima, 'instruksi' => ['pelajari']]);

    expect($lanjutan->induk_penerima_id)->toBe($indukPenerima->id)
        ->and($lanjutan->dari_user_id)->toBe($wd->id)
        ->and($indukPenerima->lanjutan)->toHaveCount(1)
        ->and($lanjutan->indukPenerima->disposisi->is($awal))->toBeTrue();
});

it('menolak peneruskan oleh yang bukan penerima, tanpa izin, atau yang sudah selesai', function () {
    $surat = suratUji();
    $dekan = orang('dekan');
    $wd = orang('wakil-dekan');
    $pegawai = orang('pegawai');
    $awal = disposisikan($surat, $dekan, [$wd, $pegawai]);
    $milikWd = $awal->penerima->firstWhere('user_id', $wd->id);
    $milikPegawai = $awal->penerima->firstWhere('user_id', $pegawai->id);
    $target = orang('kasubag');

    // bukan pemilik penerima
    expect(fn () => disposisikan($surat, orang('wakil-dekan'), [$target], ['induk' => $milikWd]))->toThrow(AuthorizationException::class);
    // pemilik tetapi tanpa izin meneruskan (pegawai)
    expect(fn () => disposisikan($surat, $pegawai, [$target], ['induk' => $milikPegawai]))->toThrow(AuthorizationException::class);
    // sudah selesai
    $milikWd->update(['status' => StatusDisposisiPenerima::Selesai]);
    expect(fn () => disposisikan($surat, $wd, [$target], ['induk' => $milikWd]))->toThrow(AuthorizationException::class);
    // induk dari surat lain
    $lain = suratUji();
    $milikWd->update(['status' => StatusDisposisiPenerima::Diterima]);
    expect(fn () => disposisikan($lain, $wd, [$target], ['induk' => $milikWd]))->toThrow(AuthorizationException::class);
});

it('menandai dibaca hanya oleh penerima dan tidak memundurkan status', function () {
    $d = disposisikan(suratUji(), orang('dekan'), [$wd = orang('wakil-dekan')]);
    $p = $d->penerima[0];

    expect(fn () => app(TandaiDibaca::class)->jalankan($p, orang('kasubag')))->toThrow(AuthorizationException::class);

    app(TandaiDibaca::class)->jalankan($p, $wd);
    expect($p->fresh()->status)->toBe(StatusDisposisiPenerima::Dibaca)->and($p->fresh()->dibaca_pada)->not->toBeNull();

    $p->update(['status' => StatusDisposisiPenerima::Ditindaklanjuti]);
    app(TandaiDibaca::class)->jalankan($p->fresh(), $wd);
    expect($p->fresh()->status)->toBe(StatusDisposisiPenerima::Ditindaklanjuti);
});

it('hanya penerima yang dapat melapor dan laporan kosong ditolak', function (string $laporan) {
    $d = disposisikan(suratUji(), orang('dekan'), [$wd = orang('wakil-dekan')]);
    $p = $d->penerima[0];

    expect(fn () => app(LaporTindakLanjut::class)->jalankan($p, orang('kasubag'), 'Sudah dikerjakan'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(LaporTindakLanjut::class)->jalankan($p, $wd, $laporan))->toThrow(ValidationException::class);
    expect($p->fresh()->status)->toBe(StatusDisposisiPenerima::Diterima);
})->with(['', '   ', "\n\t"]);

it('menerima laporan tindak lanjut dan mengisi waktu', function () {
    $d = disposisikan(suratUji(), orang('dekan'), [$wd = orang('wakil-dekan')]);
    $p = $d->penerima[0];

    app(LaporTindakLanjut::class)->jalankan($p, $wd, 'Rapat telah dihadiri');

    $p = $p->fresh();
    expect($p->status)->toBe(StatusDisposisiPenerima::Ditindaklanjuti)
        ->and($p->laporan_tindak_lanjut)->toBe('Rapat telah dihadiri')
        ->and($p->dibaca_pada)->not->toBeNull()
        ->and($p->ditindaklanjuti_pada)->not->toBeNull();
});

it('mewajibkan laporan sebelum selesai dan menyelesaikan surat setelah semua penerima selesai', function () {
    $surat = suratUji();
    $dekan = orang('dekan');
    [$a, $b] = [orang('wakil-dekan'), orang('kasubag')];
    $d = disposisikan($surat, $dekan, [$a, $b]);
    [$pa, $pb] = [$d->penerima->firstWhere('user_id', $a->id), $d->penerima->firstWhere('user_id', $b->id)];

    expect(fn () => app(SelesaikanDisposisi::class)->jalankan($pa, $a))->toThrow(ValidationException::class);

    app(LaporTindakLanjut::class)->jalankan($pa, $a, 'Selesai A');
    expect(fn () => app(SelesaikanDisposisi::class)->jalankan($pa, $b))->toThrow(AuthorizationException::class);
    app(SelesaikanDisposisi::class)->jalankan($pa, $a);
    expect($surat->fresh()->status->value)->toBe('didisposisikan');

    app(LaporTindakLanjut::class)->jalankan($pb, $b, 'Selesai B');
    app(SelesaikanDisposisi::class)->jalankan($pb, $b);
    expect($surat->fresh()->status->value)->toBe('selesai')->and($pb->fresh()->selesai_pada)->not->toBeNull();
});

it('tidak menyelesaikan surat bila disposisi lanjutan masih berjalan', function () {
    $surat = suratUji();
    $wd = orang('wakil-dekan');
    $pegawai = orang('pegawai');
    $awal = disposisikan($surat, orang('dekan'), [$wd]);
    $induk = $awal->penerima[0];
    disposisikan($surat, $wd, [$pegawai], ['induk' => $induk]);

    app(LaporTindakLanjut::class)->jalankan($induk, $wd, 'Sudah diteruskan');
    app(SelesaikanDisposisi::class)->jalankan($induk, $wd);

    expect($surat->fresh()->status->value)->toBe('didisposisikan');
});

it('memberi penerima akses baca surat rahasia, termasuk penerima lanjutan', function () {
    $surat = suratUji(['klasifikasi_keamanan' => 'rahasia']);
    $wd = orang('wakil-dekan');
    $pegawai = orang('pegawai');
    $luar = orang('kasubag');

    expect($wd->can('view', $surat))->toBeFalse();

    $awal = disposisikan($surat, orang('dekan'), [$wd]);
    expect($wd->fresh()->can('view', $surat))->toBeTrue()->and($pegawai->can('view', $surat))->toBeFalse();

    disposisikan($surat, $wd, [$pegawai], ['induk' => $awal->penerima[0]]);
    expect($pegawai->fresh()->can('view', $surat))->toBeTrue()->and($luar->can('view', $surat))->toBeFalse();
});

it('memberi penerima akses ke surat biasa yang didisposisikan kepadanya saja', function () {
    $a = suratUji();
    $b = suratUji();
    $pegawai = orang('pegawai');
    disposisikan($a, orang('dekan'), [$pegawai]);

    expect($pegawai->can('view', $a))->toBeTrue()->and($pegawai->can('view', $b))->toBeFalse();
});

it('menandai penerima yang lewat batas waktu', function () {
    $d = disposisikan(suratUji(), orang('dekan'), [orang('wakil-dekan')], ['batas' => now()->addDay()]);
    $p = $d->penerima[0];

    expect($p->sudahLewatBatas())->toBeFalse()->and(DisposisiPenerima::lewatBatas()->count())->toBe(0);

    $d->update(['batas_waktu' => now()->subHour()]);
    expect($p->fresh()->sudahLewatBatas())->toBeTrue()->and(DisposisiPenerima::lewatBatas()->count())->toBe(1);

    $p->update(['status' => StatusDisposisiPenerima::Selesai]);
    expect(DisposisiPenerima::lewatBatas()->count())->toBe(0);
});

it('mencatat riwayat di log aktivitas', function () {
    $d = disposisikan(suratUji(), orang('dekan'), [$wd = orang('wakil-dekan')]);
    app(LaporTindakLanjut::class)->jalankan($d->penerima[0], $wd, 'Laporan');

    expect(Aktivitas::where('log_name', 'disposisi')->where('event', 'buat')->exists())->toBeTrue()
        ->and(Aktivitas::where('log_name', 'disposisi-penerima')->where('event', 'updated')->exists())->toBeTrue();
});

it('menegakkan CHECK satu sumber dan unik penerima di basis data', function () {
    $d = disposisikan(suratUji(), orang('dekan'), [$p = orang('pegawai')]);

    expect(fn () => DB::table('disposisi')->insert([
        'id' => (string) Str::uuid(), 'dari_user_id' => $p->id, 'instruksi' => '[]',
        'batas_waktu' => now(), 'sifat' => 'biasa', 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class)
        ->and(fn () => DisposisiPenerima::create(['disposisi_id' => $d->id, 'user_id' => $p->id]))->toThrow(QueryException::class)
        ->and(DerajatKecepatan::from('biasa')->label())->toBe('Biasa');
});
