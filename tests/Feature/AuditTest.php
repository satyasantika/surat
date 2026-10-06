<?php

use App\Filament\Resources\Aktivitas\AktivitasResource;
use App\Filament\Resources\Aktivitas\Pages\ListAktivitas;
use App\Models\Aktivitas;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel('admin');
});

function admin(string $peran = 'super-admin'): User
{
    $u = User::factory()->create()->assignRole($peran);
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    return $u;
}

it('mencatat perubahan dengan pelaku dari autentikasi', function () {
    $pelaku = admin();
    $target = User::factory()->create(['name' => 'Lama']);

    $this->actingAs($pelaku);
    $target->update(['name' => 'Baru']);

    $log = Aktivitas::where('subject_id', $target->id)->where('event', 'updated')->latest()->first();

    expect($log->causer_id)->toBe($pelaku->id)
        ->and($log->log_name)->toBe('pengguna')
        ->and($log->attribute_changes->toArray()['attributes'] ?? [])->toHaveKey('name');
});

it('mengabaikan field pelaku atau actor dari input', function () {
    $pelaku = admin();
    $lain = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($pelaku);
    request()->merge(['actor' => $lain->id, 'pelaku' => $lain->id, 'causer_id' => $lain->id]);
    $target->update(['telepon' => '0812']);

    $log = Aktivitas::where('subject_id', $target->id)->where('event', 'updated')->latest()->first();

    expect($log->causer_id)->toBe($pelaku->id)
        ->and(json_encode($log->properties))->not->toContain($lain->id);
});

it('tidak mencatat kata sandi dan rahasia MFA', function () {
    $this->actingAs(admin());
    $target = User::factory()->create();
    $target->forceFill(['password' => Hash::make('rahasia-baru'), 'app_authentication_secret' => 'RAHASIA'])->save();

    $semua = Aktivitas::all()->map(fn ($a) => json_encode([$a->properties, $a->attribute_changes]))->implode('');

    expect($semua)->not->toContain('rahasia-baru')->not->toContain('RAHASIA')->not->toContain('"password"');
});

it('hanya super-admin yang melihat log aktivitas', function () {
    $this->actingAs(admin('super-admin'))->get('/admin/aktivitas')->assertOk();
    $this->actingAs(admin('admin-persuratan'))->get('/admin/aktivitas')->assertForbidden();
});

it('menyaring log menurut modul dan pelaku', function () {
    $pelaku = admin();
    $this->actingAs($pelaku);
    $a = Aktivitas::create(['log_name' => 'modul-a', 'description' => 'A', 'causer_type' => User::class, 'causer_id' => $pelaku->id]);
    $b = Aktivitas::create(['log_name' => 'modul-b', 'description' => 'B']);

    Livewire::test(ListAktivitas::class)
        ->filterTable('log_name', 'modul-a')
        ->assertCanSeeTableRecords([$a])
        ->assertCanNotSeeTableRecords([$b])
        ->resetTableFilters()
        ->filterTable('causer_id', $pelaku->id)
        ->assertCanSeeTableRecords([$a])
        ->assertCanNotSeeTableRecords([$b]);
});

it('tidak menyediakan aksi ubah atau hapus pada log', function () {
    $this->actingAs(admin());

    $l = Aktivitas::create(['log_name' => 'x', 'description' => 'y']);

    $resource = AktivitasResource::class;
    expect($resource::canEdit($l))->toBeFalse()
        ->and($resource::canDelete($l))->toBeFalse()
        ->and($resource::canCreate())->toBeFalse();
});

describe('masuk sebagai', function () {
    it('hanya boleh dilakukan super-admin dan tercatat', function () {
        $super = admin();
        $target = User::factory()->create()->assignRole('pegawai');

        $this->actingAs($super)->post(route('impersonasi.mulai', $target))->assertRedirect(url('admin'));

        $this->assertAuthenticatedAs($target);
        expect(session('impersonator_id'))->toBe($super->id);

        $log = Aktivitas::where('log_name', 'impersonasi')->where('event', 'mulai')->first();
        expect($log->causer_id)->toBe($super->id)->and($log->subject_id)->toBe($target->id);
    });

    it('menampilkan banner merah selama aktif lalu hilang setelah selesai', function () {
        $super = admin();
        $target = User::factory()->create()->assignRole('pegawai');

        $this->actingAs($super)->post(route('impersonasi.mulai', $target));
        $this->get('/profil')->assertSee('Anda sedang masuk sebagai')->assertSee('bg-red-600', false);

        $this->post(route('impersonasi.selesai'))->assertRedirect(url('admin'));
        $this->assertAuthenticatedAs($super);
        $this->get('/profil')->assertDontSee('Anda sedang masuk sebagai');

        $selesai = Aktivitas::where('log_name', 'impersonasi')->where('event', 'selesai')->first();
        expect($selesai->causer_id)->toBe($super->id);
    });

    it('mencatat pelaku asli pada aksi selama menyamar', function () {
        $super = admin();
        $target = User::factory()->create()->assignRole('pegawai');

        $this->actingAs($super)->post(route('impersonasi.mulai', $target));
        $target->update(['telepon' => '0899']);

        $log = Aktivitas::where('subject_id', $target->id)->where('event', 'updated')->latest()->first();
        expect($log->causer_id)->toBe($target->id)
            ->and($log->properties['impersonator_id'])->toBe($super->id);
    });

    it('menolak peran lain, diri sendiri, sesama super-admin, dan akun nonaktif', function () {
        $target = User::factory()->create()->assignRole('pegawai');

        $this->actingAs(admin('admin-persuratan'))->post(route('impersonasi.mulai', $target))->assertForbidden();

        $super = admin();
        $this->actingAs($super)->post(route('impersonasi.mulai', $super))->assertForbidden();
        $this->actingAs($super)->post(route('impersonasi.mulai', admin()))->assertForbidden();
        $this->actingAs($super)->post(route('impersonasi.mulai', User::factory()->create(['aktif' => false])))->assertForbidden();
    });

    it('menolak selesai tanpa sesi menyamar', function () {
        $this->actingAs(admin())->post(route('impersonasi.selesai'))->assertForbidden();
    });
});
