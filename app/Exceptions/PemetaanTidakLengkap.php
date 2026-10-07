<?php

namespace App\Exceptions;

use RuntimeException;

/** Pemetaan CSV tidak lengkap/tidak sah; dilempar sebelum ada penulisan ke basis data. */
class PemetaanTidakLengkap extends RuntimeException
{
    /** @param  list<string>  $galat */
    public function __construct(public readonly array $galat)
    {
        parent::__construct('Pemetaan tidak lengkap: '.count($galat).' masalah. '.implode(' | ', array_slice($galat, 0, 5)));
    }
}
