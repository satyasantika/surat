<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['pdf.driver' => 'dompdf', 'app.version' => '9.9.9']);
    cache()->put(config('app.kode').':detak-penjadwal', now()->getTimestamp(), 600);
});

it('melaporkan sehat tanpa Redis dan Gotenberg', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertExactJson([
            'app' => config('app.name'),
            'versi' => '9.9.9',
            'db' => 'ok',
            'job_tertunda' => 0,
            'job_gagal_24j' => 0,
            'detak_penjadwal' => 'ok',
        ]);
});

it('menghitung job tertunda lebih dari 10 menit tanpa mengubah status', function () {
    DB::table('jobs')->insert([
        ['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->subMinutes(11)->getTimestamp(), 'created_at' => now()->subMinutes(11)->getTimestamp()],
        ['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->subMinutes(2)->getTimestamp(), 'created_at' => now()->subMinutes(2)->getTimestamp()],
    ]);

    $this->getJson('/api/health')->assertOk()->assertJsonPath('job_tertunda', 1);
});

it('menghitung job gagal dalam 24 jam terakhir', function () {
    $gagal = fn (string $kapan) => [
        'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
        'payload' => '{}', 'exception' => 'x', 'failed_at' => $kapan,
    ];

    DB::table('failed_jobs')->insert([$gagal(now()->subHours(2)->toDateTimeString()), $gagal(now()->subDays(3)->toDateTimeString())]);

    $this->getJson('/api/health')->assertOk()->assertJsonPath('job_gagal_24j', 1);
});

it('menandai detak penjadwal terlambat, ada, atau belum ada', function () {
    cache()->put(config('app.kode').':detak-penjadwal', now()->subMinutes(6)->getTimestamp(), 600);
    $this->getJson('/api/health')->assertOk()->assertJsonPath('detak_penjadwal', 'terlambat');

    cache()->forget(config('app.kode').':detak-penjadwal');
    $this->getJson('/api/health')->assertOk()->assertJsonPath('detak_penjadwal', 'belum-ada');
});

it('memeriksa gotenberg hanya bila driver gotenberg diaktifkan', function () {
    config(['pdf.driver' => 'gotenberg', 'pdf.gotenberg_url' => 'http://gotenberg.test:3000']);
    Http::fake(['gotenberg.test:3000/health' => Http::response(['status' => 'up'])]);

    $this->getJson('/api/health')->assertOk()->assertJsonPath('gotenberg', 'ok');
});

it('mengembalikan 503 bila gotenberg yang diaktifkan tidak sehat', function () {
    config(['pdf.driver' => 'gotenberg', 'pdf.gotenberg_url' => 'http://gotenberg.test:3000']);
    Http::fake(['gotenberg.test:3000/health' => Http::response('', 500)]);

    $this->getJson('/api/health')
        ->assertStatus(503)
        ->assertJsonPath('gotenberg', 'gagal')
        ->assertJsonPath('db', 'ok');
});

it('tidak memuat kunci redis maupun gotenberg pada driver dompdf', function () {
    $this->getJson('/api/health')->assertJsonMissingPath('redis')->assertJsonMissingPath('gotenberg');
});

it('menampilkan versi aplikasi di footer panel', function () {
    config(['app.version' => '1.2.3']);

    $this->get('/admin/login')->assertSee('v1.2.3');
});
