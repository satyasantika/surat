<?php

return [
    // tautan = hanya URL + metadata (STANDAR-TEKNIS §1a); tidak ada unggahan berkas ke server.
    'mode' => env('BERKAS_MODE', 'tautan'),

    // Host yang diizinkan; awalan "*." berarti seluruh subdomain.
    'domain_putih' => [
        'drive.google.com',
        'docs.google.com',
        '*.unsil.ac.id',
        'onedrive.live.com',
        '*.sharepoint.com',
    ],

    // Tautan media publik (LPJ/galeri): hanya domain berikut.
    'domain_media' => ['instagram.com', '*.instagram.com', 'youtube.com', '*.youtube.com', 'youtu.be'],

    // Pemendek URL yang ditolak.
    'pemendek' => ['bit.ly', 's.id', 'tinyurl.com', 't.co', 'goo.gl', 'cutt.ly', 'is.gd', 'rb.gy', 'ow.ly', 'shorturl.at'],

    // Nilai jenis tautan sistem ini (03-SKEMA §10).
    'jenis' => ['pindaian', 'lampiran', 'surat_permohonan', 'proposal', 'lpj', 'sk', 'logo', 'foto', 'naskah_basah', 'ttd_visual'],

    // Jenis yang tidak pernah dirender sebagai URL ke klien.
    'jenis_tertutup' => ['ttd_visual'],

    'timeout_cek' => 10,
];
