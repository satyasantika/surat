<?php

namespace App\Http\Controllers;

use App\Services\Register\EksporXlsx;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Unduhan ekspor (register dan laporan): URL bertanda tangan + login + berkas milik pengguna + izin terkait; berkas dihapus setelah dikirim. */
class EksporUnduhController extends Controller
{
    public function __invoke(Request $request, string $berkas): BinaryFileResponse
    {
        $pengguna = $request->user();

        abort_unless(preg_match('/^(register-(?:keluar|masuk)|laporan-lap-0[1-5])-([0-9a-f-]{36})-[0-9a-f-]{36}\.xlsx$/', $berkas, $m) === 1, 404);
        abort_unless($m[2] === $pengguna->getKey(), 403);
        abort_unless($pengguna->can(str_starts_with($m[1], 'laporan-') ? 'laporan.lihat' : 'arsip.lihat'), 403);

        $path = EksporXlsx::path($berkas);
        abort_unless(is_file($path), 404);

        return response()->download($path, $berkas)->deleteFileAfterSend();
    }
}
