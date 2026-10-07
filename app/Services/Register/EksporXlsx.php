<?php

namespace App\Services\Register;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\SimpleExcel\SimpleExcelWriter;

/**
 * Ekspor register ke XLSX di storage/app/tmp (dihapus otomatis ≤ 24 jam). Nama berkas memuat id pengguna
 * agar unduhan terikat pemiliknya. Sel yang berawalan rumus dinetralkan (injeksi rumus).
 */
class EksporXlsx
{
    public const FOLDER = 'app/tmp';

    /**
     * @param  Builder<Model>  $query
     * @param  list<string>  $judul
     * @param  Closure(mixed): list<mixed>  $baris  menerima model baris sesuai query
     * @return string nama berkas (tanpa path)
     */
    public function tulis(Builder $query, array $judul, Closure $baris, User $pemilik, string $awalan): string
    {
        File::ensureDirectoryExists(storage_path(self::FOLDER));

        $nama = "{$awalan}-{$pemilik->getKey()}-".Str::uuid7().'.xlsx';
        $penulis = SimpleExcelWriter::create(storage_path(self::FOLDER.'/'.$nama));
        $penulis->addRow(array_combine($judul, $judul));

        $query->chunk(500, function ($kumpulan) use ($penulis, $judul, $baris) {
            foreach ($kumpulan as $model) {
                $penulis->addRow(array_combine($judul, array_map(self::netralkan(...), $baris($model))));
            }
        });

        $penulis->close();

        return $nama;
    }

    public static function netralkan(mixed $nilai): mixed
    {
        return is_string($nilai) && preg_match('/^[=+\-@\t\r]/', $nilai) ? "'".$nilai : $nilai;
    }

    public static function path(string $nama): string
    {
        return storage_path(self::FOLDER.'/'.basename($nama));
    }
}
