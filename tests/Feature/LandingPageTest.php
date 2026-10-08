<?php

it('menampilkan hero, kartu layanan, dan ajakan bertindak di beranda', function () {
    $r = $this->withoutVite()->get('/')->assertOk();

    $r->assertSee('Universitas Siliwangi')
        ->assertSee('Verifikasi keaslian surat')
        ->assertSee('Lihat panduan pengguna')
        ->assertSee('Masuk ke sistem')
        ->assertSee('Kabar kegiatan')
        ->assertSee('Organisasi mahasiswa');
});
