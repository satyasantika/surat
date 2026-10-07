<?php

use App\Actions\Migrasi\ImporOrmawaHub;
use App\Actions\Migrasi\UndangPengguna;
use App\Actions\Migrasi\VerifikasiMigrasi;
use App\Filament\Resources\ImporLogs\Pages\ListImporLogs;
use App\Models\ImporLog;
use App\Models\Kabar;
use App\Models\Lpj;
use App\Models\Ormawa;
use App\Models\Permohonan;
use App\Models\PermohonanRuangan;
use App\Models\User;
use App\Notifications\UndanganAturSandi;
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
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Fixtures\OrmawaHubFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->withoutVite();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, JenisPermohonanSeeder::class, RubrikLpjSeeder::class]);
    $this->dir = sys_get_temp_dir().'/verif-'.bin2hex(random_bytes(4));
    $this->admin = User::factory()->create(['email' => 'pelaksana@unsil.ac.id'])->assignRole('super-admin');
});

afterEach(fn () => File::deleteDirectory($this->dir));

function imporBersih(bool $bersih = true): void
{
    $fx = OrmawaHubFixture::buat(test()->dir, bersih: $bersih);
    app(ImporOrmawaHub::class)->jalankan($fx['xlsx'], $fx['pemetaan'], false, test()->admin);
}

function hasilCek(string $cek): array
{
    return collect(app(VerifikasiMigrasi::class)->jalankan())->firstWhere('cek', $cek);
}

describe('ormawahub:verifikasi', function () {
    it('lulus semua cek pada migrasi bersih dan berkode keluar 0', function () {
        imporBersih();

        $hasil = app(VerifikasiMigrasi::class)->jalankan();
        expect(collect($hasil)->where('ok', false)->pluck('rincian', 'cek')->all())->toBe([]);

        $this->artisan('ormawahub:verifikasi')->expectsOutputToContain('Semua cek lulus')->assertSuccessful();
    });

    it('menampilkan rekap status, nomor berikutnya, dan sampel LPJ', function () {
        imporBersih();

        expect(hasilCek('Rekap status permohonan')['rincian'])->toContain('selesai: 1')->toContain('ditolak: 1')->toContain('persetujuan_wd: 1')->toContain('validasi_admin: 1')
            ->and(hasilCek('Register nomor')['rincian'])->toContain('naskah-dekan 2026: nomor berikutnya 322+1 = 323')
            ->and(hasilCek('LPJ (sampel acak ≤10)')['rincian'])->toContain('LAP-PR-2026-001: 8 nilai, jumlah 82, akhir 82.00');
    });

    it('gagal bila ada baris galat pada log impor', function () {
        imporBersih(bersih: false);

        $h = hasilCek('Jumlah baris per sheet');

        expect($h['ok'])->toBeFalse()->and($h['rincian'])->toContain('baris galat: 3');
        $this->artisan('ormawahub:verifikasi')->assertFailed();
    });

    it('gagal bila belum ada log impor', function () {
        expect(hasilCek('Jumlah baris per sheet'))->toMatchArray(['ok' => false, 'rincian' => 'Belum ada log impor.']);
    });

    it('gagal bila baris hasil impor dihapus dari tabel tujuan', function () {
        imporBersih();
        Kabar::where('sumber_id_lama', 'B2')->delete();

        expect(hasilCek('Jumlah baris per sheet')['ok'])->toBeFalse();
    });

    it('mendeteksi bentrok ruangan dikonfirmasi dan pemakaian mendatang yang tak ada di jadwal', function () {
        imporBersih();
        $p = Permohonan::where('nomor_lama', 'PR-2026-001')->first();
        $lain = Permohonan::where('nomor_lama', 'PR-2026-002')->first();
        PermohonanRuangan::create(['permohonan_id' => $lain->id, 'kode_ruangan' => 'AULA-UTAMA', 'nama_ruangan' => 'Aula', 'tanggal' => '2026-03-10', 'sesi' => 'seharian', 'status' => 'dikonfirmasi']);

        expect(hasilCek('Ruangan')['ok'])->toBeFalse()->and(hasilCek('Ruangan')['rincian'])->toContain('bentrok antar pemakaian dikonfirmasi: 2');

        PermohonanRuangan::where('permohonan_id', $lain->id)->delete();
        PermohonanRuangan::create(['permohonan_id' => $p->id, 'kode_ruangan' => 'AULA-UTAMA', 'nama_ruangan' => 'Aula', 'tanggal' => now()->addMonth()->toDateString(), 'sesi' => 'pagi', 'status' => 'dikonfirmasi']);

        expect(hasilCek('Ruangan')['ok'])->toBeFalse()->and(hasilCek('Ruangan')['rincian'])->toContain('belum di jadwal: 1');
    });

    it('mendeteksi nilai akhir LPJ yang tidak cocok dengan jumlah nilai', function () {
        imporBersih();
        Lpj::where('sumber_id_lama', 'LAP-PR-2026-001')->update(['nilai_akhir' => 70]);

        expect(hasilCek('LPJ (sampel acak ≤10)')['ok'])->toBeFalse()->and(hasilCek('LPJ (sampel acak ≤10)')['rincian'])->toContain('TIDAK COCOK: LAP-PR-2026-001');
    });

    it('mendeteksi data contoh yang tersisa dan akun migrasi tanpa peran atau berperan pengurus', function () {
        imporBersih();
        $u = User::firstWhere('email', 'dekan.fkip@unsil.ac.id');
        $u->forceFill(['name' => 'Dekan Fakultas Teknik'])->saveQuietly();

        expect(hasilCek('Data contoh tersisa')['ok'])->toBeFalse()->and(hasilCek('Data contoh tersisa')['rincian'])->toContain('users.name: 1');

        $u->forceFill(['name' => 'Dekan'])->saveQuietly();
        expect(hasilCek('Data contoh tersisa')['ok'])->toBeTrue();

        $u->syncRoles([]);
        expect(hasilCek('Pengguna')['ok'])->toBeFalse();
        $u->assignRole('pengurus-ormawa');
        expect(hasilCek('Pengguna')['rincian'])->toContain('akun bersama?): 1');
    });

    it('mendeteksi kebocoran data pribadi pada halaman publik ormawa', function () {
        imporBersih();
        expect(hasilCek('Privasi halaman publik ormawa')['ok'])->toBeTrue();

        Ormawa::firstWhere('sumber_id_lama', 'O1')->update(['visi' => 'Hubungi NIM 2012345678 atau 081234567890']);

        expect(hasilCek('Privasi halaman publik ormawa')['ok'])->toBeFalse()->and(hasilCek('Privasi halaman publik ormawa')['rincian'])->toContain('hima-matematika: memuat data pribadi');
    });
});

describe('ormawahub:undang-pengguna', function () {
    it('mengundang akun migrasi sekali saja lewat notifikasi antrean', function () {
        imporBersih();
        Notification::fake();

        $this->artisan('ormawahub:undang-pengguna')->expectsOutputToContain('6 akun diundang, 0 dilewati')->assertSuccessful();
        Notification::assertSentTimes(UndanganAturSandi::class, 6);
        Notification::assertNotSentTo($this->admin, UndanganAturSandi::class);
        expect(User::whereNotNull('diundang_pada')->count())->toBe(6)->and($this->admin->fresh()->diundang_pada)->toBeNull();

        $this->artisan('ormawahub:undang-pengguna')->expectsOutputToContain('0 akun diundang, 6 dilewati')->assertSuccessful();
        Notification::assertSentTimes(UndanganAturSandi::class, 6);
    });

    it('dry-run tidak mengirim dan tidak menandai; --surel mengundang satu akun; nonaktif dilewati', function () {
        imporBersih();
        Notification::fake();
        User::firstWhere('email', 'wd2.fkip@unsil.ac.id')->forceFill(['aktif' => false])->saveQuietly();

        $this->artisan('ormawahub:undang-pengguna', ['--dry-run' => true])->expectsOutputToContain('[dry-run] 5 akun diundang, 1 dilewati')->assertSuccessful();
        Notification::assertNothingSent();
        expect(User::whereNotNull('diundang_pada')->count())->toBe(0);

        app(UndangPengguna::class)->jalankan(surel: 'dekan.fkip@unsil.ac.id');
        Notification::assertSentTimes(UndanganAturSandi::class, 1);
        Notification::assertSentTo(User::firstWhere('email', 'dekan.fkip@unsil.ac.id'), UndanganAturSandi::class);
    });

    it('surel berisi tautan atur kata sandi dengan token sah dan tanpa kata sandi lama', function () {
        imporBersih();
        $u = User::firstWhere('email', 'dekan.fkip@unsil.ac.id');
        $token = Password::broker()->createToken($u);

        $mail = (new UndanganAturSandi($token))->toMail($u);
        $url = $mail->actionUrl;

        expect($url)->toContain('/atur-ulang-sandi/'.$token)->toContain('email='.urlencode('dekan.fkip@unsil.ac.id'))
            ->and(Password::broker()->tokenExists($u, $token))->toBeTrue()
            ->and(implode(' ', $mail->introLines))->not->toContain('admin123');
    });
});

describe('log migrasi di panel', function () {
    it('hanya super-admin yang melihat log; status dapat difilter', function () {
        imporBersih(bersih: false);
        $this->admin->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        Filament::setCurrentPanel('admin');

        $galat = ImporLog::where('status', 'galat')->get();
        $ok = ImporLog::where('status', 'ok')->first();

        Livewire::actingAs($this->admin)->test(ListImporLogs::class)
            ->filterTable('status', 'galat')->assertCanSeeTableRecords($galat)->assertCanNotSeeTableRecords([$ok]);

        $biasa = User::factory()->create()->assignRole('admin-persuratan');
        $biasa->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $this->actingAs($biasa)->get('/admin/log-migrasi')->assertForbidden();
    });
});
