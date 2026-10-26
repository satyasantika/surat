<?php

namespace App\Services\Notifikasi;

use App\Enums\KlasifikasiKeamanan;
use App\Enums\StatusNaskah;
use App\Enums\StatusPermohonan;
use App\Enums\StatusUsulan;
use App\Filament\Resources\Lpjs\LpjResource;
use App\Filament\Resources\Naskahs\NaskahResource;
use App\Filament\Resources\Permohonans\PermohonanResource;
use App\Filament\Resources\SuratMasuks\SuratMasukResource;
use App\Jobs\KirimHasilKePengirim;
use App\Models\Disposisi;
use App\Models\DisposisiPenerima;
use App\Models\Lpj;
use App\Models\Naskah;
use App\Models\Ormawa;
use App\Models\Permohonan;
use App\Models\SuratMasuk;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Models\UsulanMahasiswa;
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

    /** Surat baru: staf khusus bila menunggu diteruskan, dekan bila langsung (jalur admin). */
    public function suratMasukBaru(SuratMasuk $surat, User $pelaku): void
    {
        $this->aman(function () use ($surat, $pelaku) {
            $ringkas = $this->ringkasSurat($surat->klasifikasi_keamanan, "{$surat->nomor_agenda}: {$surat->perihal}", "Surat masuk {$surat->klasifikasi_keamanan->value} ({$surat->nomor_agenda})");

            if ($surat->sudahDiteruskan()) {
                $this->kirim->kirim(Penerima::izin('disposisi.buat'), 'surat-masuk', 'Surat masuk menunggu disposisi', $ringkas.' menunggu disposisi.', route('disposisi'), $pelaku);

                return;
            }

            $this->kirim->kirim(Penerima::izin('masuk.teruskan'), 'surat-masuk', 'Surat masuk menunggu diteruskan ke Dekan', $ringkas.' menunggu diteruskan ke Dekan.', SuratMasukResource::getUrl('edit', ['record' => $surat]), $pelaku);
        });
    }

    /**
     * Kiriman pengirim eksternal baru (belum bernomor agenda): pemegang izin registrasi diminta menerimanya.
     * Kontak pengirim tidak pernah disertakan; label "pihak luar" membedakannya dari surat yang diregistrasi petugas.
     */
    public function kirimanEksternalBaru(SuratMasuk $surat, User $pengirim): void
    {
        $this->aman(function () use ($surat, $pengirim) {
            $dari = $surat->pengirim_instansi ?? $surat->pengirim_nama ?? $surat->asal;
            $ringkas = $this->ringkasSurat($surat->klasifikasi_keamanan, "Kiriman dari pihak luar ({$dari}): {$surat->perihal}", 'Kiriman dari pihak luar')
                .' menunggu diterima dan diberi nomor agenda.';

            $this->kirim->kirim(Penerima::izin('masuk.registrasi'), 'surat-masuk', 'Kiriman pihak luar menunggu diterima', $ringkas, SuratMasukResource::getUrl('edit', ['record' => $surat]), $pengirim);
        });
    }

    public function suratDiteruskan(SuratMasuk $surat, User $staf, ?string $catatan = null): void
    {
        $this->aman(function () use ($surat, $staf, $catatan) {
            $ringkas = $this->ringkasSurat($surat->klasifikasi_keamanan, "{$surat->nomor_agenda}: {$surat->perihal}", "Surat masuk {$surat->klasifikasi_keamanan->value} ({$surat->nomor_agenda})")
                .' diteruskan oleh '.$staf->name.' dan menunggu disposisi.'.($catatan ? " Catatan: {$catatan}" : '');

            $this->kirim->kirim(Penerima::izin('disposisi.buat'), 'surat-masuk', 'Surat masuk diteruskan kepada Dekan', $ringkas, route('disposisi'), $staf);
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

    /**
     * Perantara (pengurus ormawa atau pengaju unit) diberi tahu ada usulan mahasiswa baru, atau kiriman pihak luar
     * (berlabel "pihak luar" agar dibedakan; kontak pengirim tidak disertakan).
     */
    public function usulanDiajukan(UsulanMahasiswa $usulan, User $mahasiswa): void
    {
        $this->aman(function () use ($usulan, $mahasiswa) {
            $usulan->loadMissing(['ormawa', 'unitKerja']);
            $penerima = $usulan->ormawa_id !== null ? Penerima::ormawa($usulan->ormawa) : Penerima::pengajuUnit((string) $usulan->unit_kerja_id);
            $url = $usulan->ormawa_id !== null ? route('ormawa.usulan', $usulan->ormawa_id) : route('unit.usulan', $usulan->unit_kerja_id);
            $judul = $usulan->dariPihakLuar() ? 'Kiriman pihak luar baru' : 'Usulan mahasiswa baru';

            $this->kirim->kirim($penerima, 'permohonan', $judul, Str::limit($usulan->perihal, 120).' ('.$usulan->namaTujuan().').', $url, $mahasiswa);
        });
    }

    /** Pengusul (mahasiswa atau pihak luar) diberi tahu usulannya dikembalikan atau ditolak perantara (beserta catatan). */
    public function usulanDiputus(UsulanMahasiswa $usulan, User $oleh): void
    {
        $this->aman(function () use ($usulan, $oleh) {
            $usulan->loadMissing(['mahasiswa', 'ormawa', 'unitKerja']);
            $ditolak = $usulan->status === StatusUsulan::Ditolak;

            $this->kirim->kirim([$usulan->mahasiswa], 'permohonan', $ditolak ? 'Usulan Anda ditolak' : 'Usulan Anda dikembalikan', Str::limit($usulan->perihal, 100).' ('.$usulan->namaTujuan().') — catatan: '.Str::limit((string) $usulan->catatan, 200), $this->urlPengusul($usulan), $oleh);
        });
    }

    /** Pengirim pihak luar diberi tahu kirimannya diarsipkan perantara (tanpa tindak lanjut). */
    public function usulanDiarsipkan(UsulanMahasiswa $usulan, User $oleh): void
    {
        $this->aman(function () use ($usulan, $oleh) {
            $usulan->loadMissing(['mahasiswa', 'ormawa', 'unitKerja']);

            $this->kirim->kirim([$usulan->mahasiswa], 'permohonan', 'Kiriman Anda diarsipkan', Str::limit($usulan->perihal, 100).' ('.$usulan->namaTujuan().') diterima dan diarsipkan tanpa tindak lanjut.', $this->urlPengusul($usulan), $oleh);
        });
    }

    /** Halaman milik pengusul: ruang mahasiswa atau ruang pengirim pihak luar. */
    private function urlPengusul(UsulanMahasiswa $usulan): string
    {
        return $usulan->dariPihakLuar() ? route('pengirim') : route('mahasiswa');
    }

    /** Pengusul (mahasiswa atau pihak luar) dasar permohonan mengikuti tiap perubahan tahap permohonan itu. */
    private function kabarkanPengusulDasar(Permohonan $p, StatusPermohonan $ke, User $oleh): void
    {
        $dasar = UsulanMahasiswa::with('mahasiswa')->where('permohonan_id', $p->getKey())->get();

        foreach ($dasar->groupBy(fn (UsulanMahasiswa $u) => $this->urlPengusul($u)) as $url => $kelompok) {
            $pengusul = $kelompok->pluck('mahasiswa')->filter()->unique('id')->values()->all();

            if ($pengusul !== []) {
                $this->kirim->kirim($pengusul, 'permohonan', 'Permohonan dasar usulan Anda: '.$ke->labelPublik(), "{$p->nomor}: {$p->nama_kegiatan} ({$p->namaPengaju()}).", (string) $url, $oleh);
            }
        }
    }

    public function permohonanDiajukan(Permohonan $p, User $pelaku): void
    {
        $this->aman(function () use ($p, $pelaku) {
            $p->loadMissing(['ormawa', 'unitKerja']);
            $ringkas = "{$p->nomor}: {$p->nama_kegiatan} ({$p->namaPengaju()}).";

            [$penerima, $judul] = match ($p->status) {
                StatusPermohonan::PersetujuanPembina => [[$p->ormawa?->pembina], 'Permohonan menunggu persetujuan pembina'],
                StatusPermohonan::PersetujuanUnit => [Penerima::penyetujuUnit((string) $p->unit_kerja_id), 'Permohonan menunggu persetujuan kepala unit'],
                default => [Penerima::izin('permohonan.validasi'), 'Permohonan baru menunggu validasi'],
            };

            // Kepala unit tidak memakai panel admin: persetujuannya lewat halaman unit.
            $url = $p->status === StatusPermohonan::PersetujuanUnit ? route('unit.persetujuan') : PermohonanResource::getUrl('view', ['record' => $p]);
            $this->kirim->kirim($penerima, 'permohonan', $judul, $ringkas, $url, $pelaku);

            // Staf khusus dekanat mendapat tembusan (informasi) setiap permohonan baru masuk.
            $this->kirim->kirim(Penerima::peran('staf-khusus'), 'permohonan', 'Tembusan: permohonan baru dari '.$p->jenis_pengaju->label(), $ringkas, PermohonanResource::getUrl('view', ['record' => $p]), $pelaku);
        });
    }

    /** Halaman progres bagi pengirim: ruang ormawa atau ruang unit (prodi/laboratorium). */
    private function urlProgresPengaju(Permohonan $p): ?string
    {
        return match (true) {
            $p->ormawa_id !== null => route('ormawa.permohonan', $p->ormawa),
            $p->unit_kerja_id !== null => route('unit.permohonan', ['unit' => $p->unit_kerja_id]),
            default => null,
        };
    }

    public function permohonanBerubah(Permohonan $p, StatusPermohonan $ke, User $oleh): void
    {
        $this->aman(function () use ($p, $ke, $oleh) {
            $p->loadMissing(['ormawa', 'unitKerja']);
            $ringkas = "{$p->nomor}: {$p->nama_kegiatan} ({$p->namaPengaju()}).";

            // Pihak berikutnya
            [$users, $judul, $url] = $this->penerimaTahap($p, $ke);
            $users !== [] && $ke !== StatusPermohonan::Dikembalikan ? $this->kirim->kirim($users, 'permohonan', $judul, $ringkas, $url, $oleh) : null;

            $this->kabarkanPengusulDasar($p, $ke, $oleh);

            // Ormawa: setiap perubahan tahap (kecuali yang dilakukannya sendiri). Selesai = hasil sampai ke pengirim,
            // disertai tautan lacak publik (BR-27, BR-28).
            $selesai = $ke === StatusPermohonan::Selesai;
            $this->kirim->kirim(
                Penerima::pengaju($p),
                'permohonan',
                $selesai ? 'Permohonan selesai: hasil tersedia' : 'Permohonan: '.$ke->label(),
                $selesai ? $ringkas.' Hasil dapat dibuka dari halaman progres permohonan atau tautan lacak: '.$p->tautanLacak() : $ringkas,
                $this->urlProgresPengaju($p),
                $oleh,
            );
        });
    }

    /**
     * Hasil surat masuk dikirim petugas unit (BR-28): pemberitahuan internal (staf khusus penerus/registrator, pimpinan
     * prodi pengirim) dan, bila ada kontak terenkripsi, surel/WhatsApp ke pengirim lewat job yang mendekripsi sendiri.
     */
    public function hasilSuratMasukDikirim(SuratMasuk $surat, User $petugas): void
    {
        $this->aman(function () use ($surat, $petugas) {
            $ringkas = $this->ringkasSurat($surat->klasifikasi_keamanan, "{$surat->nomor_agenda}: {$surat->perihal}", "Surat masuk {$surat->klasifikasi_keamanan->value} ({$surat->nomor_agenda})")
                .' selesai diproses; hasil dikirim kepada pengirim. Tautan lacak: '.$surat->tautanLacak();

            $internal = Penerima::izin('masuk.teruskan')->merge(array_filter([$surat->diregistrasi_oleh ? User::find($surat->diregistrasi_oleh) : null]));
            $this->kirim->kirim($internal, 'surat-masuk', 'Hasil surat masuk dikirim kepada pengirim', $ringkas, SuratMasukResource::getUrl('edit', ['record' => $surat]), $petugas);

            if ($surat->jenis_pengirim->berunit() && $surat->pengirim_unit_kerja_id !== null) {
                $this->kirim->kirim(Penerima::pimpinanUnit($surat->pengirim_unit_kerja_id), 'surat-masuk', 'Surat Anda telah selesai diproses', 'Surat dari unit Anda kepada Dekan telah selesai diproses. Lihat hasilnya: '.$surat->tautanLacak(), $surat->tautanLacak(), $petugas);
            }

            if (filled($surat->pengirim_surel) || filled($surat->pengirim_wa)) {
                KirimHasilKePengirim::dispatch($surat->getKey())->afterCommit();
            }
        });
    }

    /**
     * Mode WD salah_satu: WD yang disposisinya diabaikan karena WD lain sudah menyetujui.
     *
     * @param  list<User>  $wd
     */
    public function disposisiWdDiabaikan(Permohonan $p, array $wd, User $pemutus): void
    {
        $this->aman(function () use ($p, $wd, $pemutus) {
            $p->loadMissing('ormawa');

            $this->kirim->kirim($wd, 'permohonan', 'Disposisi permohonan tidak perlu diputus', "{$p->nomor}: {$p->nama_kegiatan} sudah disetujui {$pemutus->name}; keputusan Anda tidak diperlukan lagi.", route('disposisi'), $pemutus);
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

    /**
     * Pihak yang berikutnya harus bertindak pada tahap permohonan, beserta judul dan tautannya.
     * Dipakai notifikasi transisi dan pengingat tertahan.
     *
     * @return array{0: list<User>, 1: string, 2: ?string}
     */
    public function penerimaTahap(Permohonan $p, StatusPermohonan $tahap): array
    {
        $p->loadMissing(['ormawa.pembina', 'unitKerja']);
        $admin = PermohonanResource::getUrl('view', ['record' => $p]);

        return match ($tahap) {
            StatusPermohonan::PersetujuanPembina => [array_filter([$p->ormawa?->pembina]), 'Permohonan menunggu persetujuan pembina', $admin],
            StatusPermohonan::PersetujuanUnit => [Penerima::penyetujuUnit((string) $p->unit_kerja_id)->all(), 'Permohonan menunggu persetujuan kepala unit', route('unit.persetujuan')],
            StatusPermohonan::Diajukan, StatusPermohonan::ValidasiAdmin => [Penerima::izin('permohonan.validasi')->all(), 'Permohonan menunggu validasi', $admin],
            StatusPermohonan::DisposisiDekan => [Penerima::jabatan('dekan')->all(), 'Permohonan menunggu disposisi Dekan', route('disposisi')],
            StatusPermohonan::PersetujuanWd => [$this->wdMenunggu($p), 'Permohonan menunggu keputusan Anda', route('disposisi')],
            StatusPermohonan::RekomendasiKasubag => [Penerima::jabatan('kasubag-umum')->all(), 'Permohonan menunggu rekomendasi Kasubag', route('disposisi')],
            StatusPermohonan::Pembuatan => [$p->unitPembuat?->petugasAktif()->get()->all() ?? [], 'Permohonan ditugaskan kepada unit Anda untuk dibuatkan suratnya', $admin],
            StatusPermohonan::Penerbitan => [Penerima::izin('nomor.terbitkan')->all(), 'Permohonan siap diterbitkan suratnya', $admin],
            StatusPermohonan::Dikembalikan => [Penerima::pengaju($p)->all(), 'Permohonan menunggu perbaikan Anda', $this->urlProgresPengaju($p)],
            default => [[], '', null],
        };
    }

    public function permohonanTertahan(Permohonan $p, int $hari): void
    {
        $this->aman(function () use ($p, $hari) {
            [$users, $judul, $url] = $this->penerimaTahap($p, $p->status);
            $p->loadMissing(['ormawa', 'unitKerja']);

            $users !== [] ? $this->kirim->kirim($users, 'pengingat', "Pengingat: {$judul}", "{$p->nomor}: {$p->nama_kegiatan} ({$p->namaPengaju()}) tertahan {$hari} hari pada tahap ini.", $url) : null;
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

    public function retensiArsip(int $total, int $masaInaktif, int $melewatiInaktif): void
    {
        $this->aman(fn () => $this->kirim->kirim(Penerima::izin('masuk.registrasi'), 'pengingat', 'Arsip melewati retensi', "{$total} arsip melewati retensi: {$masaInaktif} masuk masa inaktif, {$melewatiInaktif} melewati retensi inaktif dan perlu penentuan nasib akhir. Pemusnahan hanya melalui prosedur resmi.", url('/admin/arsip')));
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
