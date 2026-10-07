<?php

use App\Actions\Galeri\SarankanGaleriDariLpj;
use App\Filament\Resources\Galeris\Pages\ManageGaleris;
use App\Models\Galeri;
use App\Models\JenisPermohonan;
use App\Models\Lpj;
use App\Models\Ormawa;
use App\Models\Permohonan;
use App\Models\User;
use Database\Seeders\JenisPermohonanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed([PeranDanIzinSeeder::class, JenisPermohonanSeeder::class]);
    Filament::setCurrentPanel('admin');
});

function adminGaleri(): User
{
    $u = User::factory()->create()->assignRole('admin-persuratan');
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    return $u;
}

describe('validasi tautan', function () {
    it('menerima tautan yang sesuai tipe', function (string $tipe, string $url) {
        $g = Galeri::create(['judul' => 'X', 'tipe' => $tipe, 'url' => $url]);

        expect($g->fresh()->aktif)->toBeFalse();
    })->with([
        'foto drive' => ['foto', 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view'],
        'foto unsil' => ['foto', 'https://fkip.unsil.ac.id/foto/a.jpg'],
        'video youtube' => ['video', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
        'video youtu.be' => ['video', 'https://youtu.be/dQw4w9WgXcQ'],
        'video drive' => ['video', 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view'],
        'instagram' => ['instagram', 'https://www.instagram.com/p/AbCdEf123/'],
    ]);

    it('menolak tautan yang tak sesuai tipe, tak aman, atau tipe tak dikenal', function (string $tipe, string $url) {
        expect(fn () => Galeri::create(['judul' => 'X', 'tipe' => $tipe, 'url' => $url]))->toThrow(ValidationException::class);
        expect(Galeri::count())->toBe(0);
    })->with([
        'instagram di tipe foto' => ['foto', 'https://www.instagram.com/p/AbC/'],
        'youtube di tipe instagram' => ['instagram', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
        'instagram di tipe video' => ['video', 'https://www.instagram.com/reel/AbC/'],
        'foto folder drive' => ['foto', 'https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOpQr'],
        'foto domain asing' => ['foto', 'https://evil.example.com/a.jpg'],
        'pemendek' => ['foto', 'https://bit.ly/abc'],
        'http' => ['instagram', 'http://www.instagram.com/p/AbC/'],
        'kredensial' => ['instagram', 'https://user:pw@www.instagram.com/p/AbC/'],
        'mirip instagram' => ['instagram', 'https://instagram.com.evil.example/p/AbC/'],
        'tipe asing' => ['audio', 'https://www.instagram.com/p/AbC/'],
        'javascript' => ['video', 'javascript:alert(1)'],
    ]);
});

describe('tampilan', function () {
    it('foto Drive tampil lewat lh3, video YouTube lewat embed tanpa cookie, instagram tidak di-embed', function () {
        $foto = Galeri::create(['judul' => 'F', 'tipe' => 'foto', 'url' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view']);
        $yt = Galeri::create(['judul' => 'Y', 'tipe' => 'video', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);
        $drive = Galeri::create(['judul' => 'D', 'tipe' => 'video', 'url' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view']);
        $ig = Galeri::create(['judul' => 'I', 'tipe' => 'instagram', 'url' => 'https://www.instagram.com/p/AbC/']);

        expect($foto->gambarUrl())->toBe('https://lh3.googleusercontent.com/d/1AbCdEfGhIjKlMnOpQr')->and($foto->embedUrl())->toBeNull()
            ->and($yt->embedUrl())->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
            ->and($drive->embedUrl())->toBe('https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/preview')
            ->and($ig->embedUrl())->toBeNull()->and($ig->gambarUrl())->toBeNull();
    });

    it('hanya item aktif pada scope dan terurut', function () {
        Galeri::create(['judul' => 'Nonaktif', 'tipe' => 'instagram', 'url' => 'https://www.instagram.com/p/A/']);
        Galeri::create(['judul' => 'Kedua', 'tipe' => 'instagram', 'url' => 'https://www.instagram.com/p/B/', 'aktif' => true, 'urutan' => 2]);
        Galeri::create(['judul' => 'Pertama', 'tipe' => 'instagram', 'url' => 'https://www.instagram.com/p/C/', 'aktif' => true, 'urutan' => 1]);

        expect(Galeri::aktif()->pluck('judul')->all())->toBe(['Pertama', 'Kedua']);
    });
});

describe('saran dari LPJ', function () {
    function lpjDenganTautan(string $status, ?string $ig, ?string $video): Lpj
    {
        $o = Ormawa::create(['nama' => 'HIMA '.str()->random(5), 'tingkat' => 'prodi']);
        $p = (new Permohonan)->forceFill([
            'nomor' => 'PMH-2026-'.random_int(1000, 9999).random_int(10, 99), 'ormawa_id' => $o->id,
            'jenis_permohonan_id' => JenisPermohonan::firstOrFail()->id, 'diajukan_oleh' => User::factory()->create()->id,
            'nama_kegiatan' => 'Seminar '.str()->random(4), 'perihal' => 'Izin', 'tanggal_mulai' => '2026-05-10', 'tanggal_selesai' => '2026-05-11',
            'deskripsi' => 'D', 'penanggung_jawab' => ['ketua' => ['nama' => 'B']], 'status' => 'selesai', 'diajukan_pada' => now(),
        ]);
        $p->saveQuietly();
        $lpj = new Lpj;
        $lpj->forceFill(['permohonan_id' => $p->id, 'status' => $status, 'batas_waktu' => '2026-06-30', 'tautan_instagram' => $ig, 'tautan_video' => $video])->save();

        return $lpj;
    }

    it('membuat saran nonaktif hanya dari LPJ dinilai, idempoten, dan melewati tautan tak valid', function () {
        $admin = adminGaleri();
        $a = lpjDenganTautan('dinilai', 'https://www.instagram.com/p/AbC/', 'https://youtu.be/dQw4w9WgXcQ');
        lpjDenganTautan('diajukan', 'https://www.instagram.com/p/Zzz/', null);
        lpjDenganTautan('dinilai', 'https://evil.example.com/x', null);

        $h = app(SarankanGaleriDariLpj::class)->jalankan($admin);
        expect($h)->toBe(['dibuat' => 2, 'dilewati' => 1])
            ->and(Galeri::where('aktif', true)->count())->toBe(0)
            ->and(Galeri::where('permohonan_id', $a->permohonan_id)->pluck('tipe')->sort()->values()->all())->toBe(['instagram', 'video']);

        $ulang = app(SarankanGaleriDariLpj::class)->jalankan($admin);
        expect($ulang['dibuat'])->toBe(0)->and(Galeri::count())->toBe(2);
    });

    it('hanya pemegang izin galeri.kelola', function () {
        expect(fn () => app(SarankanGaleriDariLpj::class)->jalankan(User::factory()->create()->assignRole('pengurus-ormawa')))->toThrow(AuthorizationException::class);
    });
});

describe('panel', function () {
    it('admin membuat item, memvalidasi tautan menurut tipe, dan mengaktifkan saran', function () {
        $admin = adminGaleri();

        Livewire::actingAs($admin)->test(ManageGaleris::class)
            ->callAction(TestAction::make(CreateAction::class), ['judul' => 'Salah', 'tipe' => 'instagram', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'])
            ->assertHasActionErrors(['url']);
        expect(Galeri::count())->toBe(0);

        $t = Livewire::actingAs($admin)->test(ManageGaleris::class);
        $t->callAction(TestAction::make(CreateAction::class), ['judul' => 'Benar', 'tipe' => 'instagram', 'url' => 'https://www.instagram.com/p/AbC/', 'aktif' => true])
            ->assertHasNoActionErrors();
        expect(Galeri::where('judul', 'Benar')->value('aktif'))->toBeTrue();

        $g = Galeri::first();
        $t->callTableAction('edit', $g, ['judul' => 'Benar', 'tipe' => 'instagram', 'url' => 'https://www.instagram.com/p/AbC/', 'aktif' => false])->assertHasNoTableActionErrors();
        expect($g->fresh()->aktif)->toBeFalse();
    });

    it('menolak pengguna tanpa izin', function () {
        $this->actingAs(User::factory()->create()->assignRole('pegawai'))->get('/admin/galeri')->assertForbidden();
    });
});
