<?php

use Illuminate\Support\Facades\Http;

it('melaporkan semua layanan sehat', function () {
    config(['pdf.driver' => 'gotenberg', 'pdf.gotenberg_url' => 'http://gotenberg.test:3000', 'app.version' => '9.9.9']);
    Http::fake(['gotenberg.test:3000/health' => Http::response(['status' => 'up'])]);

    $this->getJson('/api/health')
        ->assertOk()
        ->assertExactJson([
            'app' => config('app.name'),
            'versi' => '9.9.9',
            'db' => 'ok',
            'redis' => 'ok',
            'gotenberg' => 'ok',
        ]);
});

it('mengembalikan 503 bila gotenberg tidak sehat', function () {
    config(['pdf.driver' => 'gotenberg', 'pdf.gotenberg_url' => 'http://gotenberg.test:3000']);
    Http::fake(['gotenberg.test:3000/health' => Http::response('', 500)]);

    $this->getJson('/api/health')
        ->assertStatus(503)
        ->assertJsonPath('gotenberg', 'gagal')
        ->assertJsonPath('db', 'ok');
});

it('menandai gotenberg nonaktif saat driver dompdf', function () {
    config(['pdf.driver' => 'dompdf']);

    $this->getJson('/api/health')->assertOk()->assertJsonPath('gotenberg', 'nonaktif');
});

it('menampilkan versi aplikasi di footer panel', function () {
    config(['app.version' => '1.2.3']);

    $this->get('/admin/login')->assertSee('v1.2.3');
});
