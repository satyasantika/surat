<?php

namespace App\Models;

use App\Models\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['permohonan_id', 'jabatan_id', 'user_id', 'putusan', 'catatan', 'diputus_pada'])]
class PersetujuanWd extends Model
{
    use HasUuids, TercatatAktivitas;

    public const MENUNGGU = 'menunggu';

    public const SETUJU = 'setuju';

    public const TOLAK = 'tolak';

    protected $table = 'persetujuan_wd';

    protected $attributes = ['putusan' => 'menunggu'];

    protected function casts(): array
    {
        return ['diputus_pada' => 'datetime'];
    }

    protected function namaLog(): string
    {
        return 'persetujuan-wd';
    }

    /** @return BelongsTo<Permohonan, $this> */
    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(Permohonan::class);
    }

    /** @return BelongsTo<Jabatan, $this> */
    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pemutus(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
