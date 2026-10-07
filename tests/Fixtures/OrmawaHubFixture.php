<?php

namespace Tests\Fixtures;

use Spatie\SimpleExcel\SimpleExcelWriter;

/** Membuat XLSX ekspor OrmawaHub dan CSV pemetaan REKAAN (tanpa data asli) untuk uji impor. */
class OrmawaHubFixture
{
    /** @return array{xlsx: string, pemetaan: string} */
    public static function buat(string $direktori, bool $lengkap = true): array
    {
        @mkdir($direktori.'/pemetaan', 0777, true);
        $xlsx = $direktori.'/ormawahub.xlsx';
        @unlink($xlsx);

        $w = SimpleExcelWriter::create($xlsx)->nameCurrentSheet('Users');
        foreach (self::users() as $r) {
            $w->addRow($r);
        }
        self::sheet($w, 'Ormawa_Profiles', self::profil());
        self::sheet($w, 'Pengurus', self::pengurus());
        self::sheet($w, 'Rooms', self::rooms());
        self::sheet($w, 'RektoratRooms', self::rektorat());
        if ($lengkap) {
            self::sheet($w, 'Requests', self::requests());
            self::sheet($w, 'Laporan', self::laporan());
            self::sheet($w, 'Blogs', self::blogs());
            self::sheet($w, 'Galleries', self::galeri());
        }
        $w->close();

        self::csv($direktori.'/pemetaan/pengguna.csv', ['id_lama', 'email', 'peran', 'jabatan'], [
            ['U1', 'admin.fkip@unsil.ac.id', '', ''],
            ['U2', 'dekan.fkip@unsil.ac.id', '', ''],
            ['U3', 'wd1.fkip@unsil.ac.id', '', ''],
            ['U4', 'wd2.fkip@unsil.ac.id', '', ''],
            ['U5', 'kasubag.fkip@unsil.ac.id', '', ''],
            ['U6', 'operator.fkip@unsil.ac.id', '', ''],
        ]);
        self::csv($direktori.'/pemetaan/ormawa.csv', ['id_lama', 'nama', 'slug', 'tingkat', 'buang'], [
            ['O1', 'HIMA Matematika', 'hima-matematika', 'prodi', 'tidak'],
            ['O2', '', '', 'fakultas', 'tidak'],
            ['O4', '', '', 'ukm', 'ya'],
        ]);
        self::csv($direktori.'/pemetaan/wd.csv', ['kode_lama', 'kode_jabatan'], [['wd1', 'wd-akademik'], ['wd2', 'wd-kemahasiswaan']]);
        self::csv($direktori.'/pemetaan/ruangan.csv', ['id_lama', 'kode_ruangan'], [['R1', 'AULA-UTAMA'], ['R3', 'R-SEMINAR']]);

        return ['xlsx' => $xlsx, 'pemetaan' => $direktori.'/pemetaan'];
    }

    /** @param  list<array<string, mixed>>  $baris */
    private static function sheet(SimpleExcelWriter $w, string $nama, array $baris): void
    {
        $w->addNewSheetAndMakeItCurrent($nama);
        foreach ($baris as $r) {
            $w->addRow($r);
        }
    }

    /** @param  list<string>  $kolom @param  list<list<string>>  $baris */
    public static function csv(string $path, array $kolom, array $baris): void
    {
        $h = fopen($path, 'w');
        fputcsv($h, $kolom, ',', '"', '\\');
        foreach ($baris as $b) {
            fputcsv($h, $b, ',', '"', '\\');
        }
        fclose($h);
    }

    /** @return list<array<string, mixed>> */
    public static function users(): array
    {
        $k = fn (array $a) => array_replace(['id' => '', 'name' => '', 'department' => '', 'role' => '', 'phone' => '', 'email' => '', 'password' => 'admin123', 'urlTte' => '', 'permissions' => ''], $a);

        return [
            $k(['id' => 'U1', 'name' => 'Admin Rekaan', 'role' => 'admin', 'email' => 'admin@lama.test', 'phone' => '081200000001']),
            $k(['id' => 'U2', 'name' => 'Dr. Dekan Rekaan', 'department' => '197001011999031001', 'role' => 'dekan', 'email' => 'dekan@lama.test', 'urlTte' => 'https://drive.google.com/file/d/1TtdTtdTtdTtdTtd/view']),
            $k(['id' => 'U3', 'name' => 'Wakil Dekan Satu', 'department' => '197101011999031002', 'role' => 'wd1', 'email' => 'wd1@lama.test']),
            $k(['id' => 'U4', 'name' => 'Wakil Dekan Dua', 'department' => '197201011999031003', 'role' => 'wd2', 'email' => 'wd2@lama.test']),
            $k(['id' => 'U5', 'name' => 'Kasubag Rekaan', 'department' => '198001011999031004', 'role' => 'kasubag', 'email' => 'kasubag@lama.test']),
            $k(['id' => 'U6', 'name' => 'Operator Rekaan', 'role' => 'admin_custom', 'email' => 'op@lama.test', 'permissions' => '["permohonan","admin_galeri","settings","laporan_aneh"]']),
            $k(['id' => 'U7', 'name' => 'HIMA Bersama', 'role' => 'ormawa', 'email' => 'hima@lama.test']),
            $k(['id' => 'U8', 'name' => 'Admin Fakultas Teknik', 'role' => 'admin', 'email' => 'contoh@ormawahub.ac.id']),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function profil(): array
    {
        $k = fn (array $a) => array_replace(['id' => '', 'nama' => '', 'handle' => '', 'email' => '', 'visi' => '', 'misi' => '', 'logoUrl' => '', 'rating' => '5', 'skUrl' => ''], $a);

        return [
            $k(['id' => 'O1', 'nama' => 'HIMA Mat', 'handle' => '@himamat', 'email' => 'hima@unsil.ac.id', 'visi' => 'Visi rekaan', 'misi' => 'Misi rekaan', 'logoUrl' => 'https://drive.google.com/file/d/1LogoLogoLogo123/view', 'skUrl' => 'https://drive.google.com/file/d/1SkSkSkSkSkSk1234/view']),
            $k(['id' => 'O2', 'nama' => 'BEM FKIP', 'handle' => 'bem fkip!', 'logoUrl' => 'https://images.unsplash.com/photo-123']),
            $k(['id' => 'O3', 'nama' => 'BEM FT', 'handle' => '@bemft']),
            $k(['id' => 'O4', 'nama' => 'UKM Lama']),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function pengurus(): array
    {
        $k = fn (array $a) => array_replace(['id' => '', 'ormawaId' => '', 'nama' => '', 'jurusan' => '', 'jabatan' => '', 'hp' => '', 'foto' => '', 'isPic' => ''], $a);

        return [
            $k(['id' => '2012345678', 'ormawaId' => 'O1', 'nama' => 'Ahmad Rekaan', 'jurusan' => 'Pendidikan Matematika', 'jabatan' => 'Ketua', 'hp' => '081234567890', 'isPic' => 'TRUE', 'foto' => 'https://drive.google.com/file/d/1FotoFotoFoto123/view']),
            $k(['id' => '2012345679', 'ormawaId' => 'O1', 'nama' => 'Siti Rekaan', 'jabatan' => 'Sekretaris Umum', 'hp' => 'bukan-nomor']),
            $k(['id' => '2012345680', 'ormawaId' => 'O1', 'nama' => 'Rudi Rekaan', 'jabatan' => 'Koordinator Humas', 'foto' => 'https://images.unsplash.com/photo-9']),
            $k(['id' => '2012345681', 'ormawaId' => 'O2', 'nama' => 'Dewi Rekaan', 'jabatan' => 'Wakil Ketua']),
            $k(['id' => '2012345682', 'ormawaId' => 'O4', 'nama' => 'Anggota UKM Buang', 'jabatan' => 'Anggota']),
            $k(['id' => '2012345683', 'ormawaId' => 'O1', 'nama' => 'Mahasiswa UKM Robotik', 'jabatan' => 'Anggota']),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function rooms(): array
    {
        $k = fn (array $a) => array_replace(['id' => '', 'nama' => '', 'gedung' => '', 'kategori' => '', 'kapasitas' => '', 'fasilitas' => '', 'pic' => 'Pak PIC 0811', 'foto' => '', 'isMaintenance' => 'FALSE', 'tampilKatalog' => 'TRUE'], $a);

        return [
            $k(['id' => 'R1', 'nama' => 'Aula Utama', 'gedung' => 'Gedung A', 'kapasitas' => 300, 'fasilitas' => 'Proyektor, Sound']),
            $k(['id' => 'R2', 'nama' => 'Lab Fakultas Teknik', 'gedung' => 'Gedung X']),
            $k(['id' => 'R3', 'nama' => 'Ruang Seminar', 'gedung' => 'Gedung B', 'kapasitas' => 60, 'isMaintenance' => 'TRUE', 'tampilKatalog' => 'FALSE']),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function rektorat(): array
    {
        return [
            ['id' => 'Q1', 'nama' => 'Gedung Serbaguna', 'kategori' => 'Aula', 'kontak' => 'Bu Kontak 0812'],
            ['id' => 'Q2', 'nama' => 'Sound System', 'kategori' => 'Peralatan', 'kontak' => ''],
        ];
    }

    private static function steps(int $selesaiSampai, string $mulai = '2026-02-01'): string
    {
        $nama = [1 => 'Pengajuan', 2 => 'Validasi Admin', 3 => 'Disposisi Dekan', 4 => 'Persetujuan WD1', 5 => 'Persetujuan WD2', 6 => 'Rekomendasi Kasubag', 7 => 'Penerbitan Surat'];
        $hasil = [];

        foreach ($nama as $n => $label) {
            $hasil[$n] = ['name' => $label, 'status' => $n <= $selesaiSampai ? 'completed' : 'pending', 'date' => $n <= $selesaiSampai ? date('Y-m-d', strtotime("{$mulai} +{$n} days")) : null, 'actor' => "Pelaku {$n}", 'notes' => "Catatan {$n}"];
        }

        return json_encode($hasil, JSON_THROW_ON_ERROR);
    }

    /** @return list<array<string, mixed>> */
    public static function requests(): array
    {
        $k = fn (array $a) => array_replace(['id' => '', 'jenisPermohonan' => 'Permohonan Kegiatan', 'ormawa' => '', 'namaKegiatan' => '', 'perihal' => 'Izin kegiatan', 'deskripsi' => 'Deskripsi rekaan', 'nomorSurat' => '', 'tanggal' => '', 'tanggalSelesai' => '', 'jamMulai' => '08:00', 'jamSelesai' => '12:00', 'tanggalPengajuan' => '', 'ketua' => '{"nama":"Ketua Rekaan","nim":"2012345678","hp":"081234567890"}', 'wakil' => '', 'sekretaris' => 'Sekretaris Rekaan', 'roomId' => '', 'sesiBooking' => '', 'fasilitasRektoratId' => '', 'fasilitas' => '', 'status' => 'pending', 'currentStep' => '', 'steps' => '', 'disposedTo' => '', 'approvedWD' => '', 'suratUrl' => '', 'proposalUrl' => '', 'suratName' => '', 'nomorSuratTerbit' => '', 'nomorSuratTerbit2' => '', 'officialLetterUrl' => '', 'nomorDisposisi' => '', 'draftSurat' => '', 'draftSuratContent' => '', 'dekanNotes' => '', 'wdNotes' => ''], $a);

        return [
            $k(['id' => 'PR-2026-001', 'jenisPermohonan' => 'Permohonan Kegiatan dan ruangan', 'ormawa' => 'HIMA Mat', 'namaKegiatan' => 'Seminar Nasional', 'tanggal' => '10 Mar 2026', 'tanggalSelesai' => '11 Mar 2026', 'tanggalPengajuan' => '1 Feb 2026', 'roomId' => 'R1', 'sesiBooking' => '["pagi","siang"]', 'status' => 'approved', 'currentStep' => 7, 'steps' => self::steps(7), 'disposedTo' => '["wd1","wd2"]', 'approvedWD' => '["wd1","wd2"]', 'suratUrl' => 'https://drive.google.com/file/d/1SuratSuratSurat12/view', 'proposalUrl' => 'https://drive.google.com/file/d/1ProposalProposal1/view', 'nomorSuratTerbit' => '321/UN58.10/KM.03.02/2026', 'nomorSuratTerbit2' => '322/UN58.10/KM.03.02/2026', 'officialLetterUrl' => 'https://drive.google.com/file/d/1ResmiResmiResmi12/view', 'nomorDisposisi' => 'D-001', 'draftSurat' => '<p>draf</p>', 'dekanNotes' => 'Silakan diproses', 'wdNotes' => 'Disetujui', 'fasilitas' => 'Kursi, Meja']),
            $k(['id' => 'PR-2026-002', 'ormawa' => 'BEM FKIP', 'namaKegiatan' => 'Lomba Debat', 'tanggal' => '5 Apr 2026', 'tanggalPengajuan' => '5 Feb 2026', 'status' => 'rejected', 'currentStep' => 5, 'steps' => self::steps(5, '2026-02-05'), 'disposedTo' => '["wd1","wd2"]', 'approvedWD' => '["wd1"]', 'proposalUrl' => 'https://unsplash.com/foto', 'suratName' => 'surat.pdf']),
            $k(['id' => 'PR-2026-003', 'jenisPermohonan' => 'Permohonan Kegiatan dan ruangan', 'ormawa' => 'HIMA Matematika', 'namaKegiatan' => 'Workshop Statistika', 'tanggal' => '20 Des 2026', 'tanggalSelesai' => '20 Des 2026', 'tanggalPengajuan' => '1 Mar 2026', 'roomId' => 'R1', 'sesiBooking' => 'Seharian', 'status' => 'pending', 'currentStep' => 4, 'steps' => self::steps(3, '2026-03-01'), 'disposedTo' => '["wd1","wd2"]', 'approvedWD' => '["wd1"]']),
            $k(['id' => 'PR-2026-003', 'ormawa' => 'BEM FKIP', 'namaKegiatan' => 'Kegiatan Id Ganda', 'tanggal' => '1 Nov 2026', 'tanggalPengajuan' => '1 Apr 2026', 'status' => 'pending', 'currentStep' => 2, 'steps' => self::steps(1, '2026-04-01')]),
            $k(['id' => 'PR-2026-004', 'ormawa' => 'HIMA Mat', 'namaKegiatan' => 'Lomba UKM Robotik', 'tanggal' => '1 Mei 2026', 'tanggalPengajuan' => '1 Mei 2026']),
            $k(['id' => 'PR-2026-005', 'ormawa' => '', 'namaKegiatan' => 'Rapat Dosen', 'deskripsi' => 'Booking manual oleh admin.', 'tanggal' => '2 Apr 2026', 'tanggalSelesai' => '2 Apr 2026', 'tanggalPengajuan' => '1 Apr 2026', 'roomId' => 'R3', 'sesiBooking' => 'siang']),
            $k(['id' => 'PR-2026-006', 'ormawa' => 'Ormawa Hantu', 'namaKegiatan' => 'Tak Dikenal', 'tanggal' => '1 Jun 2026', 'tanggalPengajuan' => '1 Jun 2026']),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function laporan(): array
    {
        $k = fn (array $a) => array_replace(['id' => '', 'requestId' => '', 'tanggalPelaksanaan' => '', 'jumlahPeserta' => 50, 'kendala' => '', 'solusi' => '', 'rekomendasi' => '', 'fileUrl' => '', 'igUrl' => '', 'videoUrl' => '', 'submittedAt' => '', 'penilaian' => ''], $a);

        return [
            $k(['id' => 'LAP-PR-2026-001', 'requestId' => 'PR-2026-001', 'tanggalPelaksanaan' => '10 Mar 2026', 'jumlahPeserta' => 120, 'kendala' => 'Cuaca', 'solusi' => 'Tenda', 'rekomendasi' => 'Ulangi', 'fileUrl' => 'https://drive.google.com/file/d/1LpjLpjLpjLpjLpj12/view', 'igUrl' => 'https://www.instagram.com/p/AbC123/', 'videoUrl' => 'https://evil.example.com/v', 'submittedAt' => '20 Mar 2026', 'penilaian' => '{"dekan":{"ketepatan":18,"kepatuhan":4},"wd1":{"ketepatan":17,"kepatuhan":5},"wd2":{"ketepatan":16,"kepatuhan":3},"kasubag":{"kelengkapan":15,"fakultas":4}}']),
            $k(['id' => 'LAP-PR-2026-003', 'requestId' => 'PR-2026-003', 'tanggalPelaksanaan' => '20 Des 2026', 'submittedAt' => '21 Des 2026', 'penilaian' => '{"dekan":{"ketepatan":18,"kepatuhan":9},"wd1":{"ketepatan":null}}']),
            $k(['id' => 'LAP-PR-HANTU', 'requestId' => 'PR-HANTU', 'tanggalPelaksanaan' => '1 Jan 2026']),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function blogs(): array
    {
        $k = fn (array $a) => array_replace(['id' => '', 'judul' => '', 'subjudul' => '', 'uraian' => '<p>Isi</p>', 'foto' => '', 'tag' => '', 'tanggal' => '', 'ormawa' => '', 'status' => 'approved', 'adminNote' => '', 'komentar_setting' => 'on'], $a);

        return [
            $k(['id' => 'B1', 'judul' => 'Kabar Terbit', 'uraian' => '<p>Isi aman</p><script>alert(1)</script><img src=x onerror=alert(2)>', 'foto' => 'https://drive.google.com/file/d/1FotoKabarKabar12/view', 'tag' => 'seminar, nasional', 'tanggal' => '3 Mar 2026', 'ormawa' => 'HIMA Mat']),
            $k(['id' => 'B2', 'judul' => 'Kabar Menunggu', 'status' => 'pending', 'tanggal' => '4 Mar 2026']),
            $k(['id' => 'B3', 'judul' => 'Kabar Ditolak', 'status' => 'rejected', 'adminNote' => 'Kurang foto', 'ormawa' => 'BEM FKIP']),
            $k(['id' => 'B4', 'judul' => 'Foto Stok', 'foto' => 'https://images.unsplash.com/photo-5', 'tanggal' => '5 Mar 2026']),
            $k(['id' => 'B5', 'judul' => 'Kabar Contoh', 'ormawa' => 'BEM FT']),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function galeri(): array
    {
        return [
            ['id' => 'G1', 'requestId' => 'PR-2026-001', 'title' => 'Foto Seminar', 'ormawa' => 'HIMA Mat', 'type' => 'photo', 'url' => 'https://drive.google.com/file/d/1GaleriGaleri12345/view', 'isActive' => 'TRUE'],
            ['id' => 'G2', 'requestId' => '', 'title' => 'IG Seminar', 'ormawa' => 'HIMA Mat', 'type' => 'photo', 'url' => 'https://www.instagram.com/p/Zz9/', 'isActive' => 'TRUE'],
            ['id' => 'G3', 'requestId' => '', 'title' => 'Video Seminar', 'ormawa' => '', 'type' => 'video', 'url' => 'https://youtu.be/dQw4w9WgXcQ', 'isActive' => 'FALSE'],
            ['id' => 'G4', 'requestId' => '', 'title' => 'Foto Tak Sah', 'ormawa' => '', 'type' => 'photo', 'url' => 'https://evil.example.com/x.jpg', 'isActive' => 'TRUE'],
        ];
    }
}
