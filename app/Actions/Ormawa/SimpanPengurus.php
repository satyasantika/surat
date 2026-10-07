<?php

namespace App\Actions\Ormawa;

use App\Models\Ormawa;
use App\Models\PengurusOrmawa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Menambah/mengubah/menghapus pengurus oleh yang berhak atas ormawa. Pengurus (bukan admin) tidak boleh
 * membuat ormawanya kehilangan seluruh ketua/sekretaris bertaut akun.
 */
class SimpanPengurus
{
    /** @param  array<string, mixed>  $data */
    public function jalankan(Ormawa $ormawa, ?PengurusOrmawa $pengurus, User $pelaku, array $data): PengurusOrmawa
    {
        $pengurus === null
            ? Gate::forUser($pelaku)->authorize('update', $ormawa)
            : Gate::forUser($pelaku)->authorize('update', $pengurus);

        if ($pengurus !== null && $pengurus->ormawa_id !== $ormawa->getKey()) {
            abort(404);
        }

        $valid = Validator::make($data, [
            'nama' => ['required', 'string', 'max:150'],
            'jabatan' => ['required', Rule::in(array_keys(PengurusOrmawa::JABATAN))],
            'jabatan_teks' => ['nullable', 'string', 'max:100'],
            'nim' => ['nullable', 'string', 'max:20', 'regex:/^[0-9A-Za-z.-]+$/'],
            'prodi' => ['nullable', 'string', 'max:100'],
            'telepon' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'tampil_publik' => ['boolean'],
            'narahubung' => ['boolean'],
            'mulai' => ['nullable', 'date'],
            'selesai' => ['nullable', 'date', 'after_or_equal:mulai'],
        ])->validate();

        return DB::transaction(function () use ($ormawa, $pengurus, $pelaku, $valid) {
            if ($pengurus === null) {
                $pengurus = new PengurusOrmawa($valid);
                $pengurus->ormawa_id = $ormawa->getKey();
                $pengurus->save();
            } else {
                $pengurus->update($valid);
            }

            $this->jagaPengelola($ormawa, $pelaku);

            return $pengurus;
        });
    }

    public function hapus(Ormawa $ormawa, PengurusOrmawa $pengurus, User $pelaku): void
    {
        Gate::forUser($pelaku)->authorize('delete', $pengurus);
        abort_unless($pengurus->ormawa_id === $ormawa->getKey(), 404);

        DB::transaction(function () use ($ormawa, $pengurus, $pelaku) {
            $pengurus->delete();
            $this->jagaPengelola($ormawa, $pelaku);
        });
    }

    private function jagaPengelola(Ormawa $ormawa, User $pelaku): void
    {
        if ($pelaku->can('ormawa.kelola') || $pelaku->can('ormawa.kelola-binaan')) {
            return;
        }

        $masihAda = $ormawa->pengurus()->with(['ormawa', 'sk'])->whereNotNull('user_id')
            ->get()->contains(fn (PengurusOrmawa $p) => $p->dapatMengelola() && $p->aktifPada());

        if (! $masihAda) {
            throw ValidationException::withMessages(['jabatan' => 'Ormawa harus tetap memiliki ketua/sekretaris aktif yang bertaut akun.']);
        }
    }
}
