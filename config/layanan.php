<?php

return [
    // Integrasi sistem Aset (API v1, BR-12). Kosong = layanan lokal.
    'aset' => [
        'url' => env('ASET_API_URL'),
        'token' => env('ASET_API_TOKEN'),
        'timeout' => (int) env('ASET_API_TIMEOUT', 5),
        'cache_detik' => 60,
    ],
];
