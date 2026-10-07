<?php

return [
    // Kanal WhatsApp opsional (BR-21): aktif hanya bila pengaturan `wa_aktif` menyala dan URL gateway diisi.
    'url' => env('WA_GATEWAY_URL'),
    'token' => env('WA_GATEWAY_TOKEN'),
    'timeout' => (int) env('WA_GATEWAY_TIMEOUT', 10),
];
