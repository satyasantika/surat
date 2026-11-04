<?php

namespace App\Http\Livewire;

use Livewire\Mechanisms\HandleRequests\HandleRequests;

/**
 * Override satu method: Livewire::getUpdateUri() memakai toRoute(..., absolute: false),
 * yang pada APP_URL bersubpath (/surat) menghasilkan path RELATIF yang SUDAH memuat
 * /surat (hasil strip scheme+host saja, bukan subpath). FrontendAssets.php lalu membungkus
 * nilai itu lagi dengan url(), yang MENAMBAHKAN root (termasuk /surat) SEKALI LAGI ->
 * dobel jadi /surat/surat/livewire-xxx/update.
 *
 * Fix: kembalikan URL absolut langsung (absolute: true). url() di FrontendAssets.php lalu
 * mendeteksinya sebagai URL valid (isValidUrl()) dan mengembalikannya tanpa diubah -> prefix tunggal.
 */
class HandleRequestsSubpath extends HandleRequests
{
    public function getUpdateUri()
    {
        $route = $this->updateRoute ?? $this->findUpdateRoute();

        return (string) app('url')->toRoute($route, [], true);
    }
}
