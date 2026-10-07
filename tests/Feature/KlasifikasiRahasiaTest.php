<?php

use App\Actions\Disposisi\BuatDisposisi;
use App\Filament\Pages\Arsip;
use App\Filament\Resources\RegisterSuratKeluars\Pages\ListRegisterSuratKeluars;
use App\Filament\Resources\RegisterSuratMasuks\Pages\ListRegisterSuratMasuks;
use App\Filament\Resources\SuratMasuks\Pages\ListSuratMasuks;
use App\Models\Jabatan;
use App\Models\JenisNaskah;
use App\Models\Naskah;
use App\Models\PemangkuJabatan;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Notifications\NotifikasiTahap;
use App\Services\Laporan\DaftarLaporan;
use Carbon\CarbonImmutable;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelReader;

uses(RefreshDatabase::class);

const RAHASIA_SURAT = 'PERIHAL-SURAT-RAHASIA-QQ1';
const RAHASIA_NASKAH = 'PERIHAL-NASKAH-RAHASIA-QQ2';

beforeEach(function () {
    Queue::fake();
    $this->withoutVite();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class]);
    Filament::setCurrentPanel('admin');
    CarbonImmutable::setTestNow('2026-06-01 09:00:00');
    File::delete(File::glob(storage_path('app/tmp/register-*.xlsx')));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    File::delete(File::glob(storage_path('app/tmp/register-*.xlsx')));
});

function rhsPengguna(string $peran, ?string $jabatan = null): User
{
    $u = User::factory()->create()->assignRole($peran);
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    $jabatan !== null && PemangkuJabatan::create(['jabatan_id' => Jabatan::firstWhere('kode', $jabatan)->id, 'user_id' => $u->id, 'mulai' => '2026-01-01']);

    return $u;
}

function rhsData(User $admin): array
{
    $surat = (new SuratMasuk(['nomor_surat' => 'S/9', 'tanggal_surat' => '2026-05-20', 'asal' => 'Instansi', 'perihal' => RAHASIA_SURAT, 'klasifikasi_keamanan' => 'rahasia', 'derajat_kecepatan' => 'biasa']))
        ->forceFill(['nomor_agenda' => '0009/AGD/2026', 'tanggal_terima' => '2026-05-20 09:00:00', 'status' => 'diterima', 'diregistrasi_oleh' => $admin->id]);
    $surat->save();

    $naskah = new Naskah(['jenis_naskah_id' => JenisNaskah::firstWhere('kode', 'surat-dinas')->id, 'klasifikasi_keamanan' => 'rahasia', 'perihal' => RAHASIA_NASKAH, 'data' => [], 'status' => 'terbit',
        'penyusun_id' => $admin->id, 'penanda_tangan_jabatan_id' => Jabatan::firstWhere('kode', 'dekan')->id, 'mode_tanda_tangan' => 'basah']);
    $naskah->forceFill(['nomor' => '99/UN58.10/KM.03.02/2026', 'tanggal_naskah' => '2026-05-25'])->save();

    return [$surat, $naskah];
}

it('perihal rahasia tidak tampil di register, panel, arsip, laporan, notifikasi, dan ekspor bagi yang tak berhak', function () {
    $admin = rhsPengguna('admin-persuratan');
    $kasubag = rhsPengguna('kasubag', 'kasubag-umum');
    $dekan = rhsPengguna('dekan', 'dekan');
    [$surat, $naskah] = rhsData($admin);

    // Register & panel surat masuk (admin: metadata saja)
    Livewire::actingAs($admin)->test(ListRegisterSuratMasuks::class)->assertSee('0009/AGD/2026')->assertDontSee(RAHASIA_SURAT);
    Livewire::actingAs($admin)->test(ListSuratMasuks::class)->assertSee('0009/AGD/2026')->assertDontSee(RAHASIA_SURAT);
    // Register surat keluar bagi kasubag (bukan penyusun/penandatangan)
    Livewire::actingAs($kasubag)->test(ListRegisterSuratKeluars::class)->assertSee('99/UN58.10/KM.03.02/2026')->assertDontSee(RAHASIA_NASKAH);
    // Arsip
    Livewire::actingAs($admin)->test(Arsip::class)->assertDontSee(RAHASIA_SURAT)->set('kata', 'QQ1')->assertDontSee('0009/AGD/2026');
    Livewire::actingAs($kasubag)->test(Arsip::class)->assertDontSee(RAHASIA_NASKAH)->set('kata', 'QQ2')->assertDontSee('99/UN58.10');
    // Laporan LAP-01
    expect(json_encode(DaftarLaporan::cari('lap-01')->susun(CarbonImmutable::parse('2026-05-01'), CarbonImmutable::parse('2026-05-31'))))->not->toContain(RAHASIA_SURAT)->not->toContain(RAHASIA_NASKAH);

    // Ekspor register
    Livewire::actingAs($kasubag)->test(ListRegisterSuratKeluars::class)->callAction('ekspor');
    $berkas = File::glob(storage_path('app/tmp/register-keluar-*.xlsx'));
    expect($berkas)->toHaveCount(1)->and(json_encode(SimpleExcelReader::create($berkas[0])->getRows()->all()))->not->toContain(RAHASIA_NASKAH);

    // Notifikasi (semua kanal) saat surat rahasia dicatat dan didisposisikan
    Notification::fake();
    $pegawai = rhsPengguna('pegawai');
    app(BuatDisposisi::class)->jalankan($surat, $dekan, [$pegawai->id], ['tindak_lanjuti'], null, now()->addDay());
    $terkirim = Notification::sent($pegawai, NotifikasiTahap::class)->first();
    expect($terkirim)->not->toBeNull()->and($terkirim->ringkas.$terkirim->judul.$terkirim->toWhatsapp($pegawai).json_encode($terkirim->toDatabase($pegawai)).$terkirim->toMail($pegawai)->render())->not->toContain(RAHASIA_SURAT);
});

it('yang berhak (dekan, penerima disposisi) tetap dapat membuka isi surat rahasia', function () {
    $admin = rhsPengguna('admin-persuratan');
    $dekan = rhsPengguna('dekan', 'dekan');
    $pegawai = rhsPengguna('pegawai');
    [$surat] = rhsData($admin);

    expect($dekan->can('view', $surat))->toBeTrue()->and($surat->perihalUntuk($dekan))->toBe(RAHASIA_SURAT)->and($surat->perihalUntuk($pegawai))->toBe(SuratMasuk::TOPENGAN);

    app(BuatDisposisi::class)->jalankan($surat, $dekan, [$pegawai->id], ['tindak_lanjuti'], null, now()->addDay());
    expect($surat->perihalUntuk($pegawai->fresh()))->toBe(RAHASIA_SURAT);
});

it('lembar disposisi dan tautan pindaian surat rahasia tertutup bagi admin; pegawai tak berhak ditolak', function () {
    $admin = rhsPengguna('admin-persuratan');
    $pegawai = rhsPengguna('pegawai');
    [$surat] = rhsData($admin);

    // admin berhak metadata (lembar disposisi tanpa isi), pegawai tanpa disposisi ditolak
    $this->actingAs($pegawai)->get(route('surat-masuk.lembar-disposisi', $surat))->assertForbidden();
    $this->actingAs(rhsPengguna('pengurus-ormawa'))->get(route('surat-masuk.lembar-disposisi', $surat))->assertForbidden();
});
