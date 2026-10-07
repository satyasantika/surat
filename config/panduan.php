<?php

return [
    // Akun demo panduan (PanduanSeeder) dikecualikan dari MFA HANYA bila APP_ENV=local dan flag ini menyala.
    'tanpa_mfa' => (bool) env('PANDUAN_TANPA_MFA', false),

    // Kata sandi akun demo PanduanSeeder (hanya lokal; tidak pernah di repo).
    'sandi' => env('PANDUAN_PASSWORD'),
];
