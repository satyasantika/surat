<?php

namespace App\Models;

use App\Models\Concerns\TercatatAktivitas;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string|null $app_authentication_secret
 * @property array<string>|null $app_authentication_recovery_codes
 * @property array<string, array{mail?: bool, whatsapp?: bool}>|null $preferensi_notifikasi
 */
#[Fillable(['name', 'email', 'password', 'nip_nim', 'telepon', 'unit_kerja_id', 'aktif', 'sumber_id_lama', 'wajib_ganti_sandi'])]
#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable, TercatatAktivitas;

    /** Peran yang memakai halaman Livewire, bukan panel Filament. */
    public const PERAN_TANPA_PANEL = ['pengurus-ormawa', 'pegawai'];

    protected function namaLog(): string
    {
        return 'pengguna';
    }

    /** @return BelongsTo<UnitKerja, $this> */
    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    /** @return MorphMany<TautanBerkas, $this> */
    public function tautan(): MorphMany
    {
        return $this->morphMany(TautanBerkas::class, 'pemilik');
    }

    /** @return HasMany<PemangkuJabatan, $this> */
    public function pemangkuJabatan(): HasMany
    {
        return $this->hasMany(PemangkuJabatan::class);
    }

    /**
     * Jabatan yang sedang diemban pengguna (tanggal bawaan: hari ini).
     *
     * @return Collection<int, Jabatan>
     */
    public function jabatanAktif(?CarbonInterface $tanggal = null): Collection
    {
        return $this->pemangkuJabatan()
            ->berlakuPada($tanggal ?? now())
            ->with('jabatan')
            ->get()
            ->pluck('jabatan');
    }

    /** @return HasMany<PengurusOrmawa, $this> */
    public function keanggotaanOrmawa(): HasMany
    {
        return $this->hasMany(PengurusOrmawa::class);
    }

    /**
     * Ormawa tempat pengguna menjadi pengurus aktif dengan SK berlaku (BR-02). Hanya ormawa ini yang
     * boleh dipakai bertindak atas nama organisasi.
     *
     * @return Collection<int, Ormawa>
     */
    public function ormawaAktif(?CarbonInterface $tanggal = null): Collection
    {
        return $this->keanggotaanOrmawa()->with(['ormawa', 'sk'])->get()
            ->filter(fn (PengurusOrmawa $p) => $p->aktifPada($tanggal))
            ->map(fn (PengurusOrmawa $p) => $p->ormawa)
            ->unique('id')
            ->values();
    }

    /** Ketua/sekretaris aktif ormawa ini yang berhak mengubah profil dan pengurusnya. */
    public function dapatMengelolaOrmawa(Ormawa $ormawa): bool
    {
        if (! $this->can('ormawa.kelola-sendiri')) {
            return false;
        }

        return $this->keanggotaanOrmawa()->with(['ormawa', 'sk'])->where('ormawa_id', $ormawa->getKey())->get()
            ->contains(fn (PengurusOrmawa $p) => $p->dapatMengelola() && $p->aktifPada());
    }

    /**
     * Preferensi kanal per kategori; bawaan: surel aktif, WhatsApp mati (opt-in).
     *
     * @return array{mail: bool, whatsapp: bool}
     */
    public function preferensiNotifikasi(string $kategori): array
    {
        $pref = $this->preferensi_notifikasi[$kategori] ?? [];

        return ['mail' => (bool) ($pref['mail'] ?? true), 'whatsapp' => (bool) ($pref['whatsapp'] ?? false)];
    }

    public function wajibMfa(): bool
    {
        // Akun demo panduan (contoh.test) bebas MFA HANYA di lokal dengan flag eksplisit; tidak pernah di staging/produksi.
        if (config('panduan.tanpa_mfa') && app()->environment('local') && str_ends_with((string) $this->email, '@contoh.test')) {
            return false;
        }

        return $this->hasAnyRole(config('unsil.peran_wajib_mfa'));
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /** @return ?array<string> */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /** @param  ?array<string>  $codes */
    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }

    protected $attributes = ['aktif' => true];

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->aktif
            && $this->roles->pluck('name')->diff(self::PERAN_TANPA_PANEL)->isNotEmpty();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'aktif' => 'boolean',
            'wajib_ganti_sandi' => 'boolean',
            'diundang_pada' => 'datetime',
            'preferensi_notifikasi' => 'array',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }
}
