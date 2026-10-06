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
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string|null $app_authentication_secret
 * @property array<string>|null $app_authentication_recovery_codes
 */
#[Fillable(['name', 'email', 'password', 'nip_nim', 'telepon', 'unit_kerja_id', 'aktif', 'sumber_id_lama'])]
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

    public function wajibMfa(): bool
    {
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
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }
}
