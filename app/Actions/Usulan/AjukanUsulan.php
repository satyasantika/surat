<?php

namespace App\Actions\Usulan;

use App\Contracts\PenyimpananBerkas;
use App\Enums\TipeUnitKerja;
use App\Models\Ormawa;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\UsulanMahasiswa;
use App\Rules\TautanBerkasValid;
use App\Services\Notifikasi\NotifikasiAlur;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Mahasiswa mengusulkan kepada satu perantara: ormawa aktif atau program studi/laboratorium aktif. Tujuan ditulis
 * "ormawa:{id}" atau "unit:{id}". Usulan tidak langsung ke fakultas; perantara yang meneruskannya sebagai dasar permohonan.
 */
class AjukanUsulan
{
    public const BATAS_PER_JAM = 10;

    public function __construct(private readonly PenyimpananBerkas $berkas) {}

    /** @param  array<string, mixed>  $data  tujuan, perihal, uraian, lampiran (opsional, tautan) */
    public function jalankan(User $mahasiswa, array $data): UsulanMahasiswa
    {
        if (! $mahasiswa->can('usulan.ajukan')) {
            throw new AuthorizationException('Hanya mahasiswa yang dapat mengajukan usulan.');
        }

        $kunci = 'usulan-mahasiswa:'.$mahasiswa->getKey();

        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_PER_JAM)) {
            throw new TooManyRequestsHttpException(RateLimiter::availableIn($kunci), 'Terlalu banyak usulan. Coba lagi nanti.');
        }

        RateLimiter::hit($kunci, 3600);

        $valid = Validator::make($data, [
            'tujuan' => ['required', 'string', 'regex:/^(ormawa|unit):[0-9a-f-]{36}$/'],
            'perihal' => ['required', 'string', 'max:255'],
            'uraian' => ['required', 'string', 'max:5000'],
            'lampiran' => ['nullable', 'string', new TautanBerkasValid],
        ], ['tujuan.required' => 'Pilih ormawa atau program studi tujuan.', 'tujuan.regex' => 'Tujuan usulan tidak valid.'])->validate();

        [$jenis, $id] = explode(':', $valid['tujuan'], 2);
        $tujuan = $this->tujuan($jenis, $id);

        return DB::transaction(function () use ($mahasiswa, $valid, $tujuan) {
            $usulan = new UsulanMahasiswa(['perihal' => $valid['perihal'], 'uraian' => $valid['uraian']]);
            $usulan->id = $usulan->newUniqueId();
            $usulan->mahasiswa_id = $mahasiswa->getKey();
            $usulan->ormawa_id = $tujuan instanceof Ormawa ? $tujuan->getKey() : null;
            $usulan->unit_kerja_id = $tujuan instanceof UnitKerja ? $tujuan->getKey() : null;
            $usulan->save();

            if (filled($valid['lampiran'] ?? null)) {
                $this->berkas->simpan($usulan, 'lampiran', $valid['lampiran'], null, $mahasiswa);
            }

            app(NotifikasiAlur::class)->usulanDiajukan($usulan, $mahasiswa);

            return $usulan;
        });
    }

    private function tujuan(string $jenis, string $id): Ormawa|UnitKerja
    {
        if ($jenis === 'ormawa') {
            return Ormawa::where('aktif', true)->find($id)
                ?? throw ValidationException::withMessages(['tujuan' => 'Ormawa tujuan tidak ditemukan atau tidak aktif.']);
        }

        $unit = UnitKerja::where('aktif', true)->whereIn('tipe', [TipeUnitKerja::Prodi->value, TipeUnitKerja::Laboratorium->value])->find($id)
            ?? throw ValidationException::withMessages(['tujuan' => 'Program studi atau laboratorium tujuan tidak ditemukan atau tidak aktif.']);

        if (! $unit->pengaju()->where('users.aktif', true)->exists()) {
            throw ValidationException::withMessages(['tujuan' => 'Unit tujuan belum memiliki pengelola usulan. Hubungi admin fakultas.']);
        }

        return $unit;
    }
}
