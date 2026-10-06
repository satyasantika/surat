<?php

namespace App\Models;

use App\Models\Concerns\TercatatAktivitas;
use App\Support\Pengaturan as PengaturanSistem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['kunci', 'nilai', 'grup'])]
class Pengaturan extends Model
{
    use HasUuids, TercatatAktivitas;

    protected $table = 'pengaturan';

    protected function casts(): array
    {
        return ['nilai' => 'json'];
    }

    protected static function booted(): void
    {
        $lupakan = fn () => PengaturanSistem::lupakan();

        static::saved($lupakan);
        static::deleted($lupakan);
    }
}
