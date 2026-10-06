<?php

return [
    // Domain surel yang diizinkan untuk akun (BR-01). Pola surel mahasiswa perlu diverifikasi.
    'domain_surel' => ['unsil.ac.id', 'student.unsil.ac.id'],

    // Domain khusus mahasiswa (pendaftaran mandiri pengurus ormawa, F2.2).
    'domain_mahasiswa' => ['student.unsil.ac.id'],

    // Peran pejabat/admin yang wajib MFA aplikasi (BR-01, S-02).
    'peran_wajib_mfa' => ['super-admin', 'admin-persuratan', 'dekan', 'wakil-dekan'],
];
