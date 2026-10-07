<?php

namespace App\Services\Notifikasi;

use App\Enums\KlasifikasiKeamanan;
use App\Enums\StatusNaskah;
use App\Enums\StatusPermohonan;
use App\Filament\Resources\Lpjs\LpjResource;
use App\Filament\Resources\Naskahs\NaskahResource;
use App\Filament\Resources\Permohonans\PermohonanResource;
use App\Models\Disposisi;
use App\Models\DisposisiPenerima;
use App\Models\Kabar;
use App\Models\Lpj;
use App\Models\Naskah;
use App\Models\Ormawa;
use App\Models\Permohonan;
use App\Models\SuratMasuk;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Support\UrlBerkas;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Notifikasi tiap perpindahan tahap (BR-21). Dipanggil dari Action pada titik transisi; kegagalan apa pun hanya
 * dicatat di log. Aturan isi: perihal surat/naskah RAHASIA tidak pernah disertakan di kanal mana pun.
 */
class NotifikasiAlur
{
    public function __construct(private readonly PengirimNotifikasi $kirim) {}

    public function suratMasukBaru(SuratMasuk $surat, User $pelaku): void
    {
        $this->aman(function () use ($surat, $pelaku) {
            $this->kirim->kirim(Penerima::izin('disposisi.buat'), 'surat-masuk', 'Surat masuk menunggu disposisi', $this->ringkasSurat($surat->klasifikasi_keamanan, "{$surat->nomor_agenda}: {$surat->perihal}", "Surat masuk {$surat->klasifikasi_keamanan->value} ({$surat->nomor_agenda})").' menunggu disposisi.', route('disposisi'), $pelaku);
        });
    }

    public function disposisiBaru(Disposisi $disposisi, ?DisposisiPenerima $induk = null): void
    {
        $this->aman(function () use ($disposisi, $induk) {
            $disposisi->loadMissing(['penerima.user', 'suratMasuk', 'dari']);
            $surat = $disposisi->suratMasuk;

            if ($surat === null) {
                return;
            }

            $ringkas = $this->ringkasSurat($surat->klasifikasi_keamanan, "{$surat->nomor_agenda}: {$surat->perihal}", "Surat {$surat->klasifikasi_keamanan->value} ({$surat->nomor_agenda})")
                .' — batas waktu '.$disposisi->batas_waktu->locale('id')->translatedFormat('d M Y H:i').'.';

            $this->kirim->kirim($disposisi->penerima->pluck('user'), 'disposisi', $induk ? 'Disposisi diteruskan kepada Anda' : 'Disposisi diterima', $ringkas, route('disposisi'), $disposisi->dari);

            if ($induk !== null) {
                $asal = User::find($induk->disposisi->dari_user_id);
                $this->kirim->kirim([$asal], 'disposisi', 'Disposisi Anda diteruskan', "Disposisi atas surat {$surat->nomor_agenda} diteruskan oleh {$disposisi->dari->name}.", route('disposisi'), $disposisi->dari);
            }
        });
    }

    public function tindakLanjutDilaporkan(DisposisiPenerima $penerima): void
    {
        $this->aman(function () use ($penerima) {
            $penerima->loadMissing(['disposisi.suratMasuk', 'user']);
            $d = $penerima->disposisi;
            $ref = $d->surat_masuk_id !== null ? $d->suratMasuk->nomor_agenda : 'permohonan';

            $this->kirim->kirim([User::find($d->dari_user_id)], 'disposisi', 'Tindak lanjut disposisi dilaporkan', "{$penerima->user->name} melaporkan tindak lanjut disposisi atas surat {$ref}.", route('disposisi'), $penerima->user);
        });
    }

    public function naskahBerubah(Naskah $naskah, StatusNaskah $ke, User $oleh): void
    {
        $this->aman(function () use ($naskah, $ke, $oleh) {
            $naskah->loadMissing('penandaTanganJabatan');
            $judul = $this->ringkasSurat($naskah->klasifikasi_keamanan, $naskah->perihal, 'Naskah '.$naskah->klasifikasi_keamanan->value);
            $url = NaskahResource::getUrl('view', ['record' => $naskah]);

            match ($ke) {
                StatusNaskah::Paraf => $this->kirim->kirim([$naskah->parafBerjalan()?->user], 'naskah', 'Naskah menunggu paraf Anda', $judul, $url, $oleh),
                StatusNaskah::MenungguTandaTangan => $this->kirim->kirim($this->penandaTangan($naskah), 'naskah', 'Naskah menunggu tanda tangan Anda', $judul, $url, $oleh),
                StatusNaskah::Dikembalikan => $this->kirim->kirim([$naskah->penyusun], 'naskah', 'Naskah dikembalikan', $judul, $url, $oleh),
                StatusNaskah::Terbit => $this->kirim->kirim([$naskah->penyusun], 'naskah', 'Naskah terbit', $judul.($naskah->nomor ? " — nomor {$naskah->nomor}" : ''), $url, $oleh),
                default => null,
            };
        });
    }

    public function permohonanDiajukan(Permohonan $p, User $pelaku): void
    {
        $this->aman(function () use ($p, $pelaku) {
            $p->loadMissing('ormawa');
            $ringkas = "{$p->nomor}: {$p->nama_kegiatan} ({$p->ormawa->nama}).";

            $this->kirim->kirim($p->status === StatusPermohonan::PersetujuanPembina ? [$p->ormawa->pembina] : Penerima::izin('permohonan.validasi'), 'permohonan', $p->status === StatusPermohonan::PersetujuanPembina ? 'Permohonan menunggu persetujuan pembina' : 'Permohonan baru menunggu validasi', $ringkas, PermohonanResource::getUrl('view', ['record' => $p]), $pelaku);
        });
    }

    public function permohonanBerubah(Permohonan $p, StatusPermohonan $ke, User $oleh): void
    {
        $this->aman(function () use ($p, $ke, $oleh) {
            $p->loadMissing('ormawa');
            $ringkas = "{$p->nomor}: {$p->nama_kegiatan} ({$p->ormawa->nama}).";

            // Pihak berikutnya
            [$users, $judul, $url] = $this->penerimaTahap($p, $ke);
            $users !== [] && $ke !== StatusPermohonan::Dikembalikan ? $this->kirim->kirim($users, 'permohonan', $judul, $ringkas, $url, $oleh) : null;

            // Ormawa: setiap perubahan tahap (kecuali yang dilakukannya sendiri)
            $this->kirim->kirim(Penerima::ormawa($p->ormawa, $p), 'permohonan', 'Permohonan: '.$ke->label(), $ringkas, route('ormawa.permohonan', $p->ormawa), $oleh);
        });
    }

    public function lpjDiajukan(Lpj $lpj, User $pelaku): void
    {
        $this->aman(function () use ($lpj, $pelaku) {
            $lpj->loadMissing('permohonan.ormawa');

            $this->kirim->kirim(Penerima::izin('lpj.nilai'), 'lpj', 'LPJ menunggu penilaian', "LPJ {$lpj->permohonan->nama_kegiatan} ({$lpj->permohonan->ormawa->nama}).", LpjResource::getUrl('view', ['record' => $lpj]), $pelaku);
        });
    }

    public function lpjDinilai(Lpj $lpj): void
    {
        $this->aman(function () use ($lpj) {
            $lpj->loadMissing('permohonan.ormawa');
            $p = $lpj->permohonan;

            $this->kirim->kirim(Penerima::ormawa($p->ormawa, $p), 'lpj', 'LPJ telah dinilai', "LPJ {$p->nama_kegiatan}: nilai akhir {$lpj->nilai_akhir}.", route('ormawa.lpj', ['ormawa' => $p->ormawa_id, 'lpj' => $lpj->getKey()]));
        });
    }

    public function kabarDiajukan(Kabar $kabar, User $pelaku): void
    {
        $this->aman(fn () => $this->kirim->kirim(Penerima::izin('kabar.kelola'), 'kabar', 'Kabar menunggu persetujuan', Str::limit($kabar->judul, 120), url('/admin/kabar'), $pelaku));
    }

    public function kabarDiputuskan(Kabar $kabar, User $oleh): void
    {
        $this->aman(function () use ($kabar, $oleh) {
            $terbit = $kabar->status === Kabar::TERBIT;

            $this->kirim->kirim([$kabar->penulis], 'kabar', $terbit ? 'Kabar Anda disetujui dan terbit' : 'Kabar Anda ditolak', Str::limit($kabar->judul, 120).(! $terbit && $kabar->catatan_admin ? " — catatan: {$kabar->catatan_admin}" : ''), $kabar->ormawa_id ? route('ormawa.kabar', $kabar->ormawa_id) : null, $oleh);
        });
    }

    /**
     * Pihak yang berikutnya harus bertindak pada tahap permohonan, beserta judul dan tautannya.
     * Dipakai notifikasi transisi dan pengingat tertahan.
     *
     * @return array{0: list<User>, 1: string, 2: ?string}
     */
    public function penerimaTahap(Permohonan $p, StatusPermohonan $tahap): array
    {
        $p->loadMissing('ormawa.pembina');
        $admin = PermohonanResource::getUrl('view', ['record' => $p]);

        return match ($tahap) {
            StatusPermohonan::PersetujuanPembina => [array_filter([$p->ormawa->pembina]), 'Permohonan menunggu persetujuan pembina', $admin],
            StatusPermohonan::Diajukan, StatusPermohonan::ValidasiAdmin => [Penerima::izin('permohonan.validasi')->all(), 'Permohonan menunggu validasi', $admin],
            StatusPermohonan::DisposisiDekan => [Penerima::jabatan('dekan')->all(), 'Permohonan menunggu disposisi Dekan', route('disposisi')],
            StatusPermohonan::PersetujuanWd => [$this->wdMenunggu($p), 'Permohonan menunggu keputusan Anda', route('disposisi')],
            StatusPermohonan::RekomendasiKasubag => [Penerima::jabatan('kasubag-umum')->all(), 'Permohonan menunggu rekomendasi Kasubag', route('disposisi')],
            StatusPermohonan::Penerbitan => [Penerima::izin('nomor.terbitkan')->all(), 'Permohonan siap diterbitkan suratnya', $admin],
            StatusPermohonan::Dikembalikan => [Penerima::ormawa($p->ormawa, $p)->all(), 'Permohonan menunggu perbaikan Anda', route('ormawa.permohonan', $p->ormawa)],
            default => [[], '', null],
        };
    }

    public function permohonanTertahan(Permohonan $p, int $hari): void
    {
        $this->aman(function () use ($p, $hari) {
            [$users, $judul, $url] = $this->penerimaTahap($p, $p->status);
            $p->loadMissing('ormawa');

            $users !== [] ? $this->kirim->kirim($users, 'pengingat', "Pengingat: {$judul}", "{$p->nomor}: {$p->nama_kegiatan} ({$p->ormawa->nama}) tertahan {$hari} hari pada tahap ini.", $url) : null;
        });
    }

    public function disposisiMendekati(DisposisiPenerima $penerima): void
    {
        $this->aman(function () use ($penerima) {
            $this->kirim->kirim([$penerima->user], 'pengingat', 'Pengingat: disposisi mendekati batas waktu', $this->ringkasDisposisi($penerima).' Batas waktu '.$penerima->disposisi->batas_waktu->locale('id')->translatedFormat('d M Y H:i').'.', route('disposisi'));
        });
    }

    public function disposisiTerlambat(DisposisiPenerima $penerima): void
    {
        $this->aman(function () use ($penerima) {
            $ringkas = $this->ringkasDisposisi($penerima).' Batas waktu '.$penerima->disposisi->batas_waktu->locale('id')->translatedFormat('d M Y H:i').' telah lewat.';

            $this->kirim->kirim([$penerima->user], 'pengingat', 'Disposisi terlambat', $ringkas, route('disposisi'));
            $this->kirim->kirim([User::find($penerima->disposisi->dari_user_id)], 'pengingat', 'Disposisi yang Anda berikan terlambat', "{$penerima->user->name}: {$ringkas}", route('disposisi'));
        });
    }

    /** @param  'h-3'|'h'|'lewat'  $tahap */
    public function lpjPengingat(Lpj $lpj, string $tahap): void
    {
        $this->aman(function () use ($lpj, $tahap) {
            $lpj->loadMissing('permohonan.ormawa');
            $p = $lpj->permohonan;
            $batas = $lpj->batas_waktu->locale('id')->translatedFormat('d M Y');

            [$judul, $teks] = match ($tahap) {
                'h-3' => ['Pengingat: LPJ jatuh tempo 3 hari lagi', "LPJ {$p->nama_kegiatan} jatuh tempo {$batas}."],
                'h' => ['Pengingat: LPJ jatuh tempo hari ini', "LPJ {$p->nama_kegiatan} jatuh tempo hari ini ({$batas})."],
                default => ['LPJ terlambat', "LPJ {$p->nama_kegiatan} melewati batas waktu {$batas}; pengajuan permohonan baru dapat diblokir."],
            };

            $this->kirim->kirim(Penerima::ormawa($p->ormawa, $p), 'pengingat', $judul, $teks, route('ormawa.lpj', ['ormawa' => $p->ormawa_id, 'lpj' => $lpj->getKey()]));
        });
    }

    public function ormawaBlokirBerubah(Ormawa $ormawa, bool $diblokir): void
    {
        $this->aman(function () use ($ormawa, $diblokir) {
            $judul = $diblokir ? 'Pengajuan permohonan diblokir: LPJ terlambat' : 'Blokir pengajuan permohonan dicabut';
            $teks = $diblokir ? "{$ormawa->nama} memiliki LPJ terlambat; lengkapi LPJ untuk dapat mengajukan permohonan baru." : "{$ormawa->nama} dapat kembali mengajukan permohonan.";

            $this->kirim->kirim(Penerima::ormawa($ormawa), 'lpj', $judul, $teks, route('ormawa', ['o' => $ormawa->getKey()]));
            $diblokir ? $this->kirim->kirim(Penerima::izin('ormawa.kelola'), 'lpj', $judul, $teks) : null;
        });
    }

    public function tautanMati(TautanBerkas $tautan): void
    {
        $this->aman(function () use ($tautan) {
            $host = UrlBerkas::urai($tautan->url)['host'] ?? 'tautan';
            $pemilik = class_basename($tautan->pemilik_type);

            $this->kirim->kirim(Penerima::izin('ormawa.kelola'), 'pengingat', 'Tautan berkas tidak dapat diakses', "Tautan {$tautan->jenis} milik {$pemilik} ({$host}) tidak dapat diakses; minta pemilik memperbarui tautan.", url('/admin'));
        });
    }

    private function ringkasDisposisi(DisposisiPenerima $penerima): string
    {
        $penerima->loadMissing(['disposisi.suratMasuk', 'user']);
        $surat = $penerima->disposisi->suratMasuk;

        return $penerima->disposisi->surat_masuk_id !== null
            ? $this->ringkasSurat($surat->klasifikasi_keamanan, "Disposisi atas surat {$surat->nomor_agenda}: {$surat->perihal}.", "Disposisi atas surat {$surat->klasifikasi_keamanan->value} ({$surat->nomor_agenda}).")
            : 'Disposisi atas permohonan ormawa.';
    }

    /** @return list<User> */
    private function wdMenunggu(Permohonan $p): array
    {
        return $p->persetujuanWd()->where('putusan', 'menunggu')->with('jabatan')->get()
            ->flatMap(fn ($ps) => Penerima::jabatan($ps->jabatan->kode))->all();
    }

    /** @return list<User> */
    private function penandaTangan(Naskah $naskah): array
    {
        return $naskah->penanda_tangan_user_id !== null
            ? array_filter([User::find($naskah->penanda_tangan_user_id)])
            : Penerima::jabatan($naskah->penandaTanganJabatan->kode)->all();
    }

    /** Perihal hanya untuk klasifikasi biasa; selain itu teks generik tanpa perihal. */
    private function ringkasSurat(KlasifikasiKeamanan $klasifikasi, string $denganPerihal, string $generik): string
    {
        return $klasifikasi === KlasifikasiKeamanan::Biasa ? Str::limit($denganPerihal, 200) : $generik;
    }

    private function aman(callable $kerja): void
    {
        try {
            $kerja();
        } catch (Throwable $e) {
            Log::error('Notifikasi alur gagal: '.$e->getMessage());
        }
    }
}
