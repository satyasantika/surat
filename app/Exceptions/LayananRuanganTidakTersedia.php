<?php

namespace App\Exceptions;

use RuntimeException;

/** Layanan ruangan (mis. API Aset) tidak dapat dijangkau: tidak pernah diganti dengan data palsu. */
class LayananRuanganTidakTersedia extends RuntimeException {}
