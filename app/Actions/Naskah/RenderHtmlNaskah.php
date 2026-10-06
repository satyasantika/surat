<?php

namespace App\Actions\Naskah;

/** Render HTML naskah dari konteks (draf hidup atau snapshot). Semua nilai ter-escape oleh Blade. */
class RenderHtmlNaskah
{
    /** @param  array<string, mixed>  $konteks */
    public function jalankan(array $konteks): string
    {
        return view($konteks['templat'], ['k' => $konteks])->render();
    }
}
