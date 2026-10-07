<?php

namespace Tests\Fixtures;

use Spatie\SimpleExcel\SimpleExcelWriter;

/** Membuat XLSX ekspor OrmawaHub dan CSV pemetaan REKAAN (tanpa data asli) untuk uji impor. */
class OrmawaHubFixture
{
    /** @return array{xlsx: string, pemetaan: string} */
    public static function buat(string $direktori): array
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
}
