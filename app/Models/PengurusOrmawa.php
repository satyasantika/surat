<?php

namespace App\Models;

use App\Models\Concerns\TercatatAktivitas;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Validation\ValidationException;

/**
 * Pengurus ormawa per orang (BR-01, BR-02). NIM dan telepon adalah data pribadi (BR-18): telepon
 * dienkripsi, dan keduanya hanya tampil bagi yang berhak (policy lihatDataPribadi).
 *
 * @property CarbonImmutable|null $mulai
 * @property CarbonImmutable|null $selesai
 */
#[Fillable(['ormawa_id', 'user_id', 'sk_kepengurusan_id', 'nama', 'nim', 'prodi', 'jabatan', 'jabatan_teks', 'telepon', 'tampil_publik', 'narahubung', 'mulai', 'selesai'])]
class PengurusOrmawa extends Model
{
    use HasUuids, TercatatAktivitas;

    public const JABATAN = [
        'ketua' => 'Ketua', 'wakil_ketua' => 'Wakil ketua', 'sekretaris' => 'Sekretaris',
        'bendahara' => 'Bendahara', 'anggota' => 'Anggota', 'lainnya' => 'Lainnya',
    ];

    /** Jabatan yang boleh mengelola profil dan pengurus ormawanya. */
    public const PENGELOLA = ['ketua', 'sekretaris'];

    protected $table = 'pengurus_ormawa';

    /** Data pribadi (BR-18): tidak ikut serialisasi maupun jejak audit. */
    protected $hidden = ['telepon', 'nim'];

    protected $attributes = ['tampil_publik' => true, 'narahubung' => false];

    protected function casts(): array
    {
        return [
            'telepon' => 'encrypted',
            'tampil_publik' => 'boolean',
            'narahubung' => 'boolean',
            'mulai' => 'date',
            'selesai' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $p) {
            if (! array_key_exists($p->jabatan ?? '', self::JABATAN)) {
                throw ValidationException::withMessages(['jabatan' => 'Jabatan pengurus tidak dikenal.']);
            }

            if ($p->selesai !== null && $p->mulai !== null && $p->selesai->lt($p->mulai)) {
                throw ValidationException::withMessages(['selesai' => 'Tanggal selesai tidak boleh sebelum mulai.']);
            }

            if ($p->sk_kepengurusan_id !== null && ! SkKepengurusan::where('ormawa_id', $p->ormawa_id)->whereKey($p->sk_kepengurusan_id)->exists()) {
                throw ValidationException::withMessages(['sk_kepengurusan_id' => 'SK harus milik ormawa yang sama.']);
            }
        });
    }

    protected function namaLog(): string
    {
        return 'pengurus-ormawa';
    }

    /**
     * Pengurus aktif (BR-02): masa jabatan mencakup tanggal, ormawa aktif, dan SK kepengurusannya berlaku
     * (SK terpilih bila ada, atau SK berlaku ormawa bila tidak dikaitkan).
     */
    public function aktifPada(?CarbonInterface $tanggal = null): bool
    {
        $tanggal = ($tanggal ?? now())->startOfDay();

        if (($this->mulai !== null && $this->mulai->startOfDay()->gt($tanggal))
            || ($this->selesai !== null && $this->selesai->endOfDay()->lt($tanggal))) {
            return false;
        }

        $this->loadMissing(['ormawa', 'sk']);

        if (! $this->ormawa->aktif) {
            return false;
        }

        return $this->sk !== null ? $this->sk->berlaku($tanggal) : $this->ormawa->skBerlaku($tanggal) !== null;
    }

    public function dapatMengelola(): bool
    {
        return in_array($this->jabatan, self::PENGELOLA, true);
    }

    /** @return BelongsTo<Ormawa, $this> */
    public function ormawa(): BelongsTo
    {
        return $this->belongsTo(Ormawa::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<SkKepengurusan, $this> */
    public function sk(): BelongsTo
    {
        return $this->belongsTo(SkKepengurusan::class, 'sk_kepengurusan_id');
    }

    /** @return MorphMany<TautanBerkas, $this> */
    public function tautan(): MorphMany
    {
        return $this->morphMany(TautanBerkas::class, 'pemilik');
    }
}
