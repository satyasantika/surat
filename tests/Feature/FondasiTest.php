<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

it('menampilkan halaman login panel admin tanpa registrasi', function () {
    $this->get('/admin/login')->assertOk();
    $this->get('/admin/register')->assertNotFound();
});

it('menolak tamu di dashboard admin', function () {
    $this->get('/admin')->assertRedirect();
});

it('membatasi horizon sesuai HORIZON_EMAILS', function () {
    config(['horizon.emails' => 'admin@unsil.ac.id']);

    expect(Gate::forUser(null)->allows('viewHorizon'))->toBeFalse()
        ->and(Gate::forUser(new User(['email' => 'admin@unsil.ac.id']))->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser(new User(['email' => 'lain@unsil.ac.id']))->allows('viewHorizon'))->toBeFalse();
});

it('menyimpan log aktivitas 1825 hari', function () {
    expect(config('activitylog.clean_after_days'))->toBe(1825);
});

it('mendefinisikan antrean horizon sesuai rancangan', function () {
    expect(config('horizon.defaults.supervisor-1.queue'))
        ->toBe(['notifikasi', 'pdf', 'integrasi', 'tautan', 'impor', 'default'])
        ->and(Str::startsWith(config('horizon.prefix'), 'surat_horizon'))->toBeTrue();
});
