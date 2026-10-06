<?php

use App\Contracts\PenyimpananBerkas;
use App\Jobs\PeriksaTautanBerkas;
use App\Models\Aktivitas;
use App\Models\Pengaturan;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Rules\TautanBerkasValid;
use App\Services\Berkas\PemeriksaTautan;
use App\Services\Berkas\TautanEksternal;
use App\Support\UrlBerkas;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

const DRIVE = 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view?usp=sharing';

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    PemeriksaTautan::$penyelesai = null;
});

afterEach(fn () => PemeriksaTautan::$penyelesai = null);

function simpanTautan(?Model $pemilik = null, string $jenis = 'lampiran', string $url = DRIVE): TautanBerkas
{
    return app(PenyimpananBerkas::class)->simpan($pemilik ?? User::factory()->create(), $jenis, $url, 'Berkas uji');
}

it('memvalidasi tautan berkas', function (string $url, bool $lolos) {
    $v = Validator::make(['u' => $url], ['u' => ['required', new TautanBerkasValid]]);

    expect($v->passes())->toBe($lolos);
})->with([
    'drive' => [DRIVE, true],
    'docs' => ['https://docs.google.com/document/d/1AbCdEfGhIjKlMnOpQr/edit', true],
    'subdomain unsil' => ['https://repo.fkip.unsil.ac.id/berkas.pdf', true],
    'onedrive' => ['https://onedrive.live.com/redir?resid=abc', true],
    'sharepoint' => ['https://unsil-my.sharepoint.com/:b:/g/personal/x', true],
    'http polos' => ['http://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view', false],
    'domain asing' => ['https://evil.example.com/a.pdf', false],
    'tiruan domain' => ['https://drive.google.com.evil.com/file/d/1AbCdEfGhIjKlMnOpQr', false],
    'tiruan unsil' => ['https://evilunsil.ac.id/a.pdf', false],
    'domain unsil polos tanpa subdomain' => ['https://unsil.ac.id/a.pdf', false],
    'pemendek bit.ly' => ['https://bit.ly/3abcdef', false],
    'pemendek s.id' => ['https://s.id/abc', false],
    'folder drive' => ['https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOpQr', false],
    'folder drive u/0' => ['https://drive.google.com/drive/u/0/folders/1AbCdEfGhIjKlMnOpQr', false],
    'kredensial di url' => ['https://user:pw@drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr', false],
    'port aneh' => ['https://drive.google.com:8443/file/d/1AbCdEfGhIjKlMnOpQr', false],
    'skema javascript' => ['javascript:alert(1)', false],
    'ip literal' => ['https://127.0.0.1/a.pdf', false],
    'bukan url' => ['bukan-url', false],
    'kosong' => ['', false],
]);

it('mengizinkan tautan folder bila bukan satu berkas', function () {
    $v = Validator::make(['u' => 'https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOpQr'], ['u' => [new TautanBerkasValid(satuBerkas: false)]]);

    expect($v->passes())->toBeTrue();
});

it('mengekstrak id berkas drive dari berbagai pola', function (string $url, ?string $id) {
    expect(UrlBerkas::driveFileId($url))->toBe($id);
})->with([
    [DRIVE, '1AbCdEfGhIjKlMnOpQr'],
    ['https://drive.google.com/open?id=1AbCdEfGhIjKlMnOpQr', '1AbCdEfGhIjKlMnOpQr'],
    ['https://docs.google.com/spreadsheets/d/1AbCdEfGhIjKlMnOpQr/edit#gid=0', '1AbCdEfGhIjKlMnOpQr'],
    ['https://repo.unsil.ac.id/file/d/1AbCdEfGhIjKlMnOpQr', null],
    ['https://drive.google.com/open?id=pendek', null],
]);

it('menyimpan tautan dengan penyedia dan id berkas', function () {
    Queue::fake();
    $pemilik = User::factory()->create();

    $t = simpanTautan($pemilik);

    expect($t->penyedia)->toBe('google_drive')
        ->and($t->drive_file_id)->toBe('1AbCdEfGhIjKlMnOpQr')
        ->and($t->status_cek)->toBe('belum')
        ->and($t->pemilik->is($pemilik))->toBeTrue()
        ->and(app(PenyimpananBerkas::class)->daftar($pemilik, 'lampiran'))->toHaveCount(1)
        ->and(app(PenyimpananBerkas::class)->daftar($pemilik, 'proposal'))->toBeEmpty()
        ->and(app(PenyimpananBerkas::class))->toBeInstanceOf(TautanEksternal::class);
});

it('menolak tautan atau jenis yang melanggar kebijakan di tingkat model', function () {
    Queue::fake();

    expect(fn () => simpanTautan(url: 'https://evil.example.com/x'))->toThrow(ValidationException::class)
        ->and(fn () => simpanTautan(url: 'http://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr'))->toThrow(ValidationException::class)
        ->and(fn () => simpanTautan(jenis: 'sembarang'))->toThrow(ValidationException::class)
        ->and(TautanBerkas::count())->toBe(0);
});

it('membatalkan status cek saat url berubah dan mencatat perubahan', function () {
    Queue::fake();
    $this->actingAs(User::factory()->create());
    $t = simpanTautan();
    $t->forceFill(['status_cek' => 'dapat_diakses'])->saveQuietly();

    $t->update(['url' => 'https://docs.google.com/document/d/1ZyXwVuTsRqPoNmLkJi/edit']);

    expect($t->fresh()->status_cek)->toBe('belum')
        ->and($t->fresh()->penyedia)->toBe('google_docs')
        ->and(Aktivitas::where('log_name', 'tautan-berkas')->where('event', 'updated')->exists())->toBeTrue();
});

it('mengantrekan pemeriksaan di antrean tautan hanya saat dibuat atau url berubah', function () {
    Queue::fake();
    $t = simpanTautan();

    Queue::assertPushedOn('tautan', PeriksaTautanBerkas::class);
    Queue::assertPushed(PeriksaTautanBerkas::class, 1);

    $t->update(['label' => 'Label baru']);
    Queue::assertPushed(PeriksaTautanBerkas::class, 1);

    $t->update(['url' => 'https://docs.google.com/document/d/1ZyXwVuTsRqPoNmLkJi/edit']);
    Queue::assertPushed(PeriksaTautanBerkas::class, 2);
});

describe('pemeriksaan tautan', function () {
    beforeEach(function () {
        Queue::fake();
        PemeriksaTautan::$penyelesai = fn () => ['142.250.4.100'];
    });

    it('menandai dapat diakses untuk respons sukses tanpa mengunduh isi', function () {
        Http::fake(['*' => Http::response('', 200)]);
        $t = simpanTautan();

        expect(app(PemeriksaTautan::class)->periksa($t))->toBe('dapat_diakses')
            ->and($t->fresh()->status_cek)->toBe('dapat_diakses')
            ->and($t->fresh()->dicek_pada)->not->toBeNull();
        Http::assertSent(fn ($r) => $r->method() === 'HEAD');
    });

    it('menandai tidak dapat diakses untuk 404, 5xx, pengalihan, dan galat jaringan', function (int|Throwable $hasil) {
        Http::fake(['*' => $hasil instanceof Throwable ? fn () => throw $hasil : Http::response('', $hasil)]);
        $t = simpanTautan();

        expect(app(PemeriksaTautan::class)->periksa($t))->toBe('tidak_dapat_diakses');
    })->with([404, 500, 302, new ConnectionException('timeout')]);

    it('menolak host yang resolve ke IP pribadi (anti-SSRF) tanpa mengirim permintaan', function (array $ips) {
        Http::fake();
        PemeriksaTautan::$penyelesai = fn () => $ips;
        $t = simpanTautan(url: 'https://repo.unsil.ac.id/berkas.pdf');

        expect(app(PemeriksaTautan::class)->periksa($t))->toBe('tidak_dapat_diakses');
        Http::assertNothingSent();
    })->with([
        'loopback' => [['127.0.0.1']],
        'rfc1918 10' => [['10.0.0.5']],
        'rfc1918 192' => [['192.168.1.10']],
        'metadata awan' => [['169.254.169.254']],
        'campuran publik dan pribadi' => [['142.250.4.100', '10.0.0.5']],
        'dns gagal' => [[]],
    ]);

    it('tidak memeriksa host di luar daftar putih walau tersimpan', function () {
        Http::fake();
        $t = simpanTautan();
        config(['berkas.domain_putih' => ['docs.google.com']]);

        expect(app(PemeriksaTautan::class)->periksa($t))->toBe('tidak_dapat_diakses');
        Http::assertNothingSent();
    });

    it('menjalankan job pemeriksaan', function () {
        Http::fake(['*' => Http::response('', 200)]);
        $t = simpanTautan();

        (new PeriksaTautanBerkas($t))->handle(app(PemeriksaTautan::class));

        expect($t->fresh()->status_cek)->toBe('dapat_diakses');
    });
});

describe('membuka tautan', function () {
    beforeEach(fn () => Queue::fake());

    it('mengalihkan pengguna yang berhak pada pemiliknya', function () {
        $pemilik = User::factory()->create();
        $t = simpanTautan($pemilik);
        $pengelola = User::factory()->create()->assignRole('operator-layanan');
        $pengelola->givePermissionTo('pengguna.kelola');

        $this->actingAs($pengelola)->get(route('berkas.buka', $t))->assertRedirect(DRIVE);
    });

    it('menolak pengguna yang tidak berhak dan tamu', function () {
        $t = simpanTautan();

        $this->actingAs(User::factory()->create()->assignRole('pegawai'))->get(route('berkas.buka', $t))->assertForbidden();
        auth()->logout();
        $this->get(route('berkas.buka', $t))->assertRedirect('/masuk');
    });

    it('menolak bila pemilik tidak punya policy (fail-closed), kecuali super-admin', function () {
        $t = simpanTautan(Pengaturan::create(['kunci' => 'x', 'nilai' => 1]));

        $this->actingAs(User::factory()->create()->assignRole('operator-layanan'))->get(route('berkas.buka', $t))->assertForbidden();
        $this->actingAs(User::factory()->create()->assignRole('super-admin'))->get(route('berkas.buka', $t))->assertRedirect(DRIVE);
    });

    it('tidak pernah menyerahkan url ttd_visual, bahkan kepada super-admin', function () {
        $t = simpanTautan(jenis: 'ttd_visual');
        $super = User::factory()->create()->assignRole('super-admin');

        $this->actingAs($super)->get(route('berkas.buka', $t))->assertNotFound();
        expect(app(PenyimpananBerkas::class)->urlAkses($t))->toBeNull();
    });

    it('menolak tautan yang domainnya sudah dicabut dari daftar putih', function () {
        $t = simpanTautan();
        config(['berkas.domain_putih' => ['docs.google.com']]);
        $super = User::factory()->create()->assignRole('super-admin');

        $this->actingAs($super)->get(route('berkas.buka', $t))->assertNotFound();
    });
});

describe('komponen x-tautan-berkas', function () {
    beforeEach(fn () => Queue::fake());

    it('menampilkan tautan internal aman, bukan url eksternal, untuk yang berhak', function () {
        $t = simpanTautan();
        $this->actingAs(User::factory()->create()->assignRole('super-admin'));

        $html = Blade::render('<x-tautan-berkas :tautan="$t" />', ['t' => $t]);

        expect($html)->toContain(route('berkas.buka', $t))
            ->toContain('target="_blank"')->toContain('rel="noopener noreferrer"')
            ->not->toContain('drive.google.com')->not->toContain('<iframe');
    });

    it('menampilkan pratinjau iframe drive hanya bila diminta', function () {
        $t = simpanTautan();
        $this->actingAs(User::factory()->create()->assignRole('super-admin'));

        $html = Blade::render('<x-tautan-berkas :tautan="$t" :pratinjau="true" />', ['t' => $t]);

        expect($html)->toContain('https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/preview')->toContain('sandbox');
    });

    it('tidak merender apa pun untuk yang tidak berhak, tamu, atau jenis tertutup', function () {
        $biasa = simpanTautan();
        $tertutup = simpanTautan(jenis: 'ttd_visual');

        $this->actingAs(User::factory()->create()->assignRole('pegawai'));
        expect(trim(Blade::render('<x-tautan-berkas :tautan="$t" />', ['t' => $biasa])))->toBe('');

        $this->actingAs(User::factory()->create()->assignRole('super-admin'));
        $html = Blade::render('<x-tautan-berkas :tautan="$t" :pratinjau="true" />', ['t' => $tertutup]);
        expect(trim($html))->toBe('');
    });

    it('memperingatkan tautan yang tidak dapat diakses', function () {
        $t = simpanTautan();
        $t->forceFill(['status_cek' => 'tidak_dapat_diakses'])->saveQuietly();
        $this->actingAs(User::factory()->create()->assignRole('super-admin'));

        expect(Blade::render('<x-tautan-berkas :tautan="$t" />', ['t' => $t->fresh()]))->toContain('tidak dapat diakses');
    });
});
