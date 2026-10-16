<?php

it('menampilkan halaman login panel admin tanpa registrasi', function () {
    $this->get('/admin/login')->assertOk();
    $this->get('/admin/register')->assertNotFound();
});

it('menolak tamu di dashboard admin', function () {
    $this->get('/admin')->assertRedirect();
});

it('menyimpan log aktivitas 1825 hari', function () {
    expect(config('activitylog.clean_after_days'))->toBe(1825);
});
