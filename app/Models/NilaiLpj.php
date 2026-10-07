<?php

namespace App\Models;

use App\Models\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lpj_id', 'rubrik_lpj_id', 'penilai_jabatan_id', 'penilai_user_id', 'nilai', 'catatan'])]
class NilaiLpj extends Model
{
    use HasUuids, TercatatAktivitas;

    protected $table = 'nilai_lpj';

    protected function casts(): array
    {
        return ['nilai' => 'decimal:2'];
    }

    protected function namaLog(): string
    {
        return 'nilai-lpj';
    }

    /** @return BelongsTo<Lpj, $this> */
    public function lpj(): BelongsTo
    {
        return $this->belongsTo(Lpj::class);
    }

    /** @return BelongsTo<RubrikLpj, $this> */
    public function rubrik(): BelongsTo
    {
        return $this->belongsTo(RubrikLpj::class, 'rubrik_lpj_id');
    }

    /** @return BelongsTo<Jabatan, $this> */
    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class, 'penilai_jabatan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function penilai(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penilai_user_id');
    }
}
