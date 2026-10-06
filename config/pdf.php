<?php

return [
    // gotenberg (default) | dompdf (cadangan)
    'driver' => env('PDF_DRIVER', 'gotenberg'),

    // Driver naskah resmi: harus deterministik agar hash_pdf dapat diverifikasi ulang (dompdf).
    'driver_naskah' => env('PDF_DRIVER_NASKAH', 'dompdf'),

    'gotenberg_url' => env('GOTENBERG_URL', 'http://surat-gotenberg:3000'),
];
