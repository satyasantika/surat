<?php

namespace App\Models;

use App\Models\Concerns\TercatatAktivitas;
use App\Support\Pengaturan;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[Fillable(['slug', 'nama', 'singkatan', 'tingkat', 'prodi', 'akun_media', 'surel_organisasi', 'visi', 'misi', 'pembina_user_id', 'aktif', 'sumber_id_lama'])]
class Ormawa extends Model
{
    use HasUuids, TercatatAktivitas;

    public const TINGKAT = ['fakultas' => 'Fakultas', 'jurusan' => 'Jurusan', 'prodi' => 'Program studi', 'ukm' => 'UKM'];

    protected $table = 'ormawa';

    protected $attributes = ['aktif' => true];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'diblokir_lpj' => 'boolean', 'diblokir_sejak' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $ormawa) {
            if (! array_key_exists($ormawa->tingkat ?? '', self::TINGKAT)) {
                throw ValidationException::withMessages(['tingkat' => 'Tingkat organisasi tidak dikenal.']);
            }

            if (blank($ormawa->slug)) {
                $ormawa->slug = self::slugUnik($ormawa->nama, $ormawa->exists ? $ormawa->getKey() : null);
            }

            if ($ormawa->pembina_user_id !== null && $ormawa->isDirty('pembina_user_id')
                && ! User::find($ormawa->pembina_user_id)?->hasRole('pembina-ormawa')) {
                throw ValidationException::withMessages(['pembina_user_id' => 'Pembina harus pengguna berperan pembina-ormawa.']);
            }
        });
    }

    public static function slugUnik(string $nama, ?string $kecuali = null): string
    {
        $dasar = Str::slug($nama) ?: 'ormawa';
        $slug = $dasar;

        for ($i = 2; self::where('slug', $slug)->when($kecuali, fn ($q) => $q->whereKeyNot($kecuali))->exists(); $i++) {
            $slug = "{$dasar}-{$i}";
        }

        return $slug;
    }

    /** SK kepengurusan yang periodenya mencakup tanggal (bawaan: hari ini); bila ada beberapa, yang terbaru. */
    public function skBerlaku(?CarbonInterface $tanggal = null): ?SkKepengurusan
    {
        $tanggal = ($tanggal ?? now())->toDateString();

        return $this->sk()
            ->whereDate('periode_mulai', '<=', $tanggal)
            ->whereDate('periode_selesai', '>=', $tanggal)
            ->orderByDesc('tanggal_sk')->orderByDesc('id')
            ->first();
    }

    /** @return BelongsTo<User, $this> */
    public function pembina(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pembina_user_id');
    }

    /** @return HasMany<SkKepengurusan, $this> */
    public function sk(): HasMany
    {
        return $this->hasMany(SkKepengurusan::class);
    }

    /** @return HasManyThrough<Lpj, Permohonan, $this> */
    public function lpj(): HasManyThrough
    {
        return $this->hasManyThrough(Lpj::class, Permohonan::class);
    }

    /**
     * LPJ terlambat (BR-16): ada LPJ belum diajukan yang melewati batas waktu + toleransi. Dasar blokir
     * pengajuan permohonan baru bila kebijakan blokir_lpj_terlambat aktif.
     */
    public function lpjTerlambat(): bool
    {
        $toleransi = (int) Pengaturan::get('toleransi_lpj_hari');

        return $this->lpj()->where('lpj.status', Lpj::DRAF)
            ->whereDate('lpj.batas_waktu', '<', now()->startOfDay()->subDays($toleransi)->toDateString())
            ->exists();
    }

    /** @return HasMany<PengurusOrmawa, $this> */
    public function pengurus(): HasMany
    {
        return $this->hasMany(PengurusOrmawa::class);
    }

    /** @return MorphMany<TautanBerkas, $this> */
    public function tautan(): MorphMany
    {
        return $this->morphMany(TautanBerkas::class, 'pemilik');
    }
}
