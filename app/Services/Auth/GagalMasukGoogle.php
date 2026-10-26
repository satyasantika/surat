<?php

namespace App\Services\Auth;

use RuntimeException;

/** Masuk lewat Google ditolak; pesan siap ditampilkan kepada pengguna. */
class GagalMasukGoogle extends RuntimeException {}
