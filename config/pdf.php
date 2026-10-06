<?php

return [
    // gotenberg (default) | dompdf (cadangan)
    'driver' => env('PDF_DRIVER', 'gotenberg'),

    'gotenberg_url' => env('GOTENBERG_URL', 'http://surat-gotenberg:3000'),
];
