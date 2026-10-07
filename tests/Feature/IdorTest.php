<?php

use App\Livewire\Ormawa\Kabar as KomponenKabar;
use App\Models\Jabatan;
use App\Models\JenisNaskah;
use App\Models\JenisPermohonan;
use App\Models\Lpj;
use App\Models\Naskah;
use App\Models\Ormawa;
use App\Models\PengurusOrmawa;
use App\Models\Permohonan;
use App\Models\TautanBerkas;
use App\Models\User;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\JenisPermohonanSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->withoutVite();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, JenisPermohonanSeeder::class]);
});

function idorOrmawa(string $nama): array
{
    $o = Ormawa::create(['nama' => $nama, 'tingkat' => 'prodi']);
    $o->sk()->create(['nomor_sk' => 'SK/1', 'tanggal_sk' => '2026-01-01', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2099-12-31']);
    $u = User::factory()->create(['nip_nim' => (string) random_int(1000000, 9999999)])->assignRole('pengurus-ormawa');
    PengurusOrmawa::create(['ormawa_id' => $o->id, 'user_id' => $u->id, 'nama' => $u->name, 'jabatan' => 'ketua']);

    return [$o, $u];
}

it('pengurus tidak dapat membuka halaman ormawa lain (profil, permohonan, kabar, LPJ)', function () {
    [$a, $ua] = idorOrmawa('HIMA A');
    [$b] = idorOrmawa('HIMA B');
    $p = (new Permohonan)->forceFill([
        'nomor' => 'PMH-2026-7777', 'ormawa_id' => $b->id, 'jenis_permohonan_id' => JenisPermohonan::firstWhere('kode', 'kegiatan')->id, 'diajukan_oleh' => User::factory()->create()->id,
        'nama_kegiatan' => 'Rahasia B', 'perihal' => 'x', 'tanggal_mulai' => '2026-05-10', 'tanggal_selesai' => '2026-05-11', 'deskripsi' => 'D', 'penanggung_jawab' => ['ketua' => ['nama' => 'Z']], 'status' => 'selesai', 'diajukan_pada' => now(),
    ]);
    $p->saveQuietly();
    $lpj = new Lpj;
    $lpj->forceFill(['permohonan_id' => $p->id, 'batas_waktu' => '2026-05-25'])->save();

    foreach ([route('ormawa.profil', $b), route('ormawa.permohonan', $b), route('ormawa.permohonan.baru', $b), route('ormawa.kabar', $b), route('ormawa.lpj', ['ormawa' => $b->id, 'lpj' => $lpj->id]), route('ormawa.lpj', ['ormawa' => $a->id, 'lpj' => $lpj->id])] as $url) {
        $status = $this->actingAs($ua)->get($url)->status();
        expect($status)->toBeIn([403, 404], "{$url} seharusnya ditolak, status {$status}");
    }

    $this->actingAs($ua)->get(route('ormawa.permohonan', $a))->assertOk()->assertDontSee('Rahasia B');
});

it('properti terkunci Livewire tidak dapat diganti ke ormawa lain dari klien', function () {
    [$a, $ua] = idorOrmawa('HIMA A');
    [$b] = idorOrmawa('HIMA B');

    expect(fn () => Livewire::actingAs($ua)->test(KomponenKabar::class, ['ormawa' => $a->id])->set('ormawaId', $b->id))->toThrow(Exception::class, 'Cannot update locked property');
});

it('tautan berkas milik ormawa lain tidak dapat dibuka; tautan tanda tangan tidak pernah dapat dibuka', function () {
    [$a, $ua] = idorOrmawa('HIMA A');
    [$b, $ub] = idorOrmawa('HIMA B');
    $tautan = $b->tautan()->create(['jenis' => 'sk', 'url' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view', 'label' => 'SK']);

    expect($this->actingAs($ua)->get(route('berkas.buka', $tautan))->status())->toBeIn([403, 404]);
    $this->actingAs($ub)->get(route('berkas.buka', $tautan))->assertRedirect('https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view');

    $ttd = User::factory()->create()->tautan()->create(['jenis' => 'ttd_visual', 'url' => 'https://drive.google.com/file/d/1TtdTtdTtdTtdTtd/view']);
    $this->actingAs(User::factory()->create()->assignRole('super-admin'))->get(route('berkas.buka', $ttd))->assertNotFound();
    expect(TautanBerkas::find($ttd->id)->tertutup())->toBeTrue();
});

it('naskah dan PDF-nya tidak dapat diakses pengguna yang tidak berkaitan; id bukan UUID memberi 404', function () {
    [, $ua] = idorOrmawa('HIMA A');
    $penyusun = User::factory()->create()->assignRole('admin-persuratan');
    $n = new Naskah(['jenis_naskah_id' => JenisNaskah::firstWhere('kode', 'surat-dinas')->id, 'perihal' => 'N', 'data' => [], 'status' => 'terbit', 'penyusun_id' => $penyusun->id,
        'penanda_tangan_jabatan_id' => Jabatan::firstWhere('kode', 'dekan')->id, 'mode_tanda_tangan' => 'basah']);
    $n->forceFill(['nomor' => '1/X/2026', 'tanggal_naskah' => '2026-05-01', 'snapshot' => ['migrasi' => true]])->save();

    $this->actingAs($ua)->get(route('naskah.pdf', $n))->assertForbidden();
    $this->actingAs($ua)->get(route('naskah.pratinjau', $n))->assertForbidden();

    foreach (['/naskah/1/pdf', '/naskah/abc/pratinjau', '/ormawa/xyz/profil', '/berkas/1/buka', '/surat-masuk/1/lembar-disposisi', '/notifikasi/1/buka', '/ekspor/..%2F..%2F.env'] as $url) {
        expect($this->actingAs($ua)->get($url)->status())->toBeIn([404, 403], $url);
    }
});

it('rute tanpa login mengalihkan ke masuk dan tidak membocorkan keberadaan data', function () {
    [$o] = idorOrmawa('HIMA A');

    foreach ([route('ormawa.profil', $o), route('naskah.pdf', Str::uuid()->toString()), route('berkas.buka', Str::uuid()->toString()), route('notifikasi')] as $url) {
        $this->get($url)->assertRedirect(route('login'));
    }
});
