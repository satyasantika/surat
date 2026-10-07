<?php

use App\Actions\Disposisi\BuatDisposisi;
use App\Models\Jabatan;
use App\Models\JenisNaskah;
use App\Models\Naskah;
use App\Models\SuratMasuk;
use App\Models\User;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class]);
});

const MATRIKS_PERAN = ['super-admin', 'admin-persuratan', 'operator-layanan', 'dekan', 'wakil-dekan', 'kasubag', 'pembina-ormawa', 'pengurus-ormawa', 'pegawai'];

/**
 * Matriks hak akses PRD §3.1 dalam bentuk izin → peran yang berhak (super-admin selalu lewat Gate::before;
 * operator-layanan tanpa izin bawaan). Setiap izin sistem WAJIB muncul di sini: izin baru tak boleh luput dari tinjauan.
 *
 * @return array<string, list<string>>
 */
function matriksIzin(): array
{
    $admin = 'admin-persuratan';
    $pimpinan = ['dekan', 'wakil-dekan', 'kasubag'];

    return [
        'masuk.registrasi' => [$admin],
        'masuk.lihat' => [$admin, ...$pimpinan, 'pegawai'],
        'disposisi.buat' => ['dekan'],
        'disposisi.teruskan' => ['wakil-dekan', 'kasubag'],
        'disposisi.tindaklanjut' => ['wakil-dekan', 'kasubag', 'pegawai'],
        'naskah.draf' => [$admin, ...$pimpinan],
        'naskah.paraf' => ['wakil-dekan', 'kasubag'],
        'naskah.tandatangan' => ['dekan', 'wakil-dekan'],
        'naskah.templat' => [$admin],
        'naskah.batalkan' => [$admin],
        'nomor.terbitkan' => [$admin],
        'arsip.lihat' => [$admin, ...$pimpinan],
        'ormawa.lihat' => [$admin, ...$pimpinan],
        'ormawa.kelola' => [$admin],
        'ormawa.kelola-binaan' => ['pembina-ormawa'],
        'ormawa.kelola-sendiri' => ['pengurus-ormawa'],
        'permohonan.ajukan' => ['pengurus-ormawa'],
        'permohonan.setujui-pembina' => ['pembina-ormawa'],
        'permohonan.validasi' => [$admin],
        'permohonan.putuskan' => $pimpinan,
        'ruangan.kelola' => [],
        'ruangan.kelola-jadwal' => [$admin],
        'lpj.isi' => ['pengurus-ormawa'],
        'lpj.nilai' => $pimpinan,
        'lpj.lihat' => ['pembina-ormawa'],
        'kabar.kelola' => [$admin],
        'kabar.usul' => ['pengurus-ormawa'],
        'galeri.kelola' => [$admin],
        'laporan.lihat' => [$admin, ...$pimpinan],
        'master.kelola' => [],
        'pengguna.kelola' => [],
        'pengaturan.kelola' => [],
    ];
}

it('matriks mencakup tepat seluruh izin sistem (tidak ada izin tanpa tinjauan)', function () {
    expect(array_keys(matriksIzin()))->toEqualCanonicalizing(PeranDanIzinSeeder::IZIN);
});

it('setiap sel matriks PRD §3.1 sesuai: izin × peran', function (string $izin, array $berhak) {
    foreach (MATRIKS_PERAN as $peran) {
        $diharapkan = $peran === 'super-admin' || in_array($peran, $berhak, true);
        $user = User::factory()->create()->assignRole($peran);

        expect($user->can($izin))->toBe($diharapkan, "{$peran} → {$izin} seharusnya ".($diharapkan ? 'diizinkan' : 'ditolak'));
    }
})->with(fn () => collect(matriksIzin())->map(fn ($berhak, $izin) => [$izin, $berhak])->all());

it('operator-layanan tanpa izin bawaan; hanya izin yang diberikan yang berlaku', function () {
    $op = User::factory()->create()->assignRole('operator-layanan');

    expect(collect(PeranDanIzinSeeder::IZIN)->filter(fn ($i) => $op->can($i))->all())->toBe([]);

    $op->givePermissionTo(['kabar.kelola', 'galeri.kelola']);
    $op = $op->fresh();

    expect($op->can('kabar.kelola'))->toBeTrue()->and($op->can('galeri.kelola'))->toBeTrue()->and($op->can('masuk.registrasi'))->toBeFalse()->and($op->can('pengguna.kelola'))->toBeFalse();
});

it('pengguna tanpa peran tidak memiliki izin apa pun', function () {
    $u = User::factory()->create();

    expect(collect(PeranDanIzinSeeder::IZIN)->filter(fn ($i) => $u->can($i))->all())->toBe([]);
});

function suratMatriks(string $keamanan = 'biasa'): SuratMasuk
{
    $admin = User::factory()->create()->assignRole('admin-persuratan');
    $s = (new SuratMasuk(['nomor_surat' => 'S/1', 'tanggal_surat' => '2026-05-20', 'asal' => 'X', 'perihal' => 'P', 'klasifikasi_keamanan' => $keamanan, 'derajat_kecepatan' => 'biasa']))
        ->forceFill(['nomor_agenda' => 'A/'.random_int(1000, 9999), 'tanggal_terima' => '2026-05-20 09:00:00', 'status' => 'diterima', 'diregistrasi_oleh' => $admin->id]);
    $s->save();

    return $s;
}

it('sel "T" (yang ditujukan): surat masuk terbuka bagi penerima disposisi, tertutup bagi pegawai/WD/kasubag lain', function (string $peran) {
    $surat = suratMatriks();
    $dekan = User::factory()->create()->assignRole('dekan');
    $penerima = User::factory()->create()->assignRole($peran);
    $lain = User::factory()->create()->assignRole($peran);

    expect($penerima->can('view', $surat))->toBeFalse();

    app(BuatDisposisi::class)->jalankan($surat, $dekan, [$penerima->id], ['tindak_lanjuti'], null, now()->addDay());

    expect($penerima->fresh()->can('view', $surat))->toBeTrue()->and($lain->can('view', $surat))->toBeFalse();
})->with(['wakil-dekan', 'kasubag', 'pegawai']);

it('surat rahasia: hanya dekan dan penerima disposisi; admin hanya metadata; pembina/pengurus tidak sama sekali', function () {
    $rahasia = suratMatriks('rahasia');
    $admin = User::factory()->create()->assignRole('admin-persuratan');
    $dekan = User::factory()->create()->assignRole('dekan');
    $pegawai = User::factory()->create()->assignRole('pegawai');

    expect($dekan->can('view', $rahasia))->toBeTrue()->and($admin->can('view', $rahasia))->toBeFalse()->and($admin->can('lihatMetadata', $rahasia))->toBeTrue()
        ->and($admin->can('update', $rahasia))->toBeFalse()->and($pegawai->can('view', $rahasia))->toBeFalse();

    foreach (['pembina-ormawa', 'pengurus-ormawa'] as $peran) {
        $u = User::factory()->create()->assignRole($peran);
        expect($u->can('view', $rahasia))->toBeFalse()->and($u->can('lihatMetadata', $rahasia))->toBeFalse()->and($u->can('create', SuratMasuk::class))->toBeFalse();
    }

    app(BuatDisposisi::class)->jalankan($rahasia, $dekan, [$pegawai->id], ['tindak_lanjuti'], null, now()->addDay());
    expect($pegawai->fresh()->can('view', $rahasia))->toBeTrue();
});

it('naskah keluar: publik/ormawa/pegawai tak dapat membuka; penyusun dan penanda tangan dapat', function () {
    $penyusun = User::factory()->create()->assignRole('admin-persuratan');
    $n = new Naskah(['jenis_naskah_id' => JenisNaskah::firstWhere('kode', 'surat-dinas')->id, 'perihal' => 'N', 'data' => [], 'status' => 'draf', 'penyusun_id' => $penyusun->id,
        'penanda_tangan_jabatan_id' => Jabatan::firstWhere('kode', 'dekan')->id, 'mode_tanda_tangan' => 'basah']);
    $n->save();

    expect($penyusun->can('view', $n))->toBeTrue();

    foreach (['pengurus-ormawa', 'pembina-ormawa', 'pegawai'] as $peran) {
        expect(User::factory()->create()->assignRole($peran)->can('view', $n))->toBeFalse();
    }
});
