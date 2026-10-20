<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — FKIP Universitas Siliwangi</title>
    <meta name="description" content="Layanan persuratan digital dan organisasi mahasiswa FKIP Universitas Siliwangi: pengajuan surat, disposisi, naskah bernomor dengan QR verifikasi, pelacakan tanpa akun, dan profil ormawa.">
    <link rel="icon" href="{{ url('/favicon.ico') }}">
    <link rel="stylesheet" href="{{ url('/fonts/filament/filament/inter/index.css') }}">
    <script src="{{ url('/js/tema.js') }}"></script>
    <style>
        :root {
            color-scheme: light;
            --kertas: #f8fafc; --kertas-2: #eff4fb; --tinta: #0f172a; --tinta-2: #475569; --garis: #dbe3ee;
            --amber: #1e3a8a; --amber-tua: #1d4ed8; --amber-muda: #dbeafe; --hijau: #15803d; --merah: #b91c1c;
            --serif: var(--sans);
            --sans: "Inter Variable", "Inter", system-ui, -apple-system, "Segoe UI", sans-serif;
        }
        :root.dark {
            color-scheme: dark;
            --kertas: #0b1220; --kertas-2: #111b2e; --tinta: #e8eef8; --tinta-2: #9fb0c8; --garis: #22304a;
            --amber: #3b82f6; --amber-muda: #172554; --amber-tua: #93c5fd;
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { margin: 0; background: var(--kertas); color: var(--tinta); font: 16px/1.65 var(--sans); -webkit-font-smoothing: antialiased; }
        img { max-width: 100%; height: auto; display: block; }
        a { color: inherit; }
        .wrap { width: min(1160px, 100% - 48px); margin-inline: auto; }

        .sakelar-tema { display: inline-grid; place-items: center; width: 38px; height: 38px; padding: 0; border-radius: 10px; border: 1.5px solid var(--garis); background: transparent; color: var(--tinta); cursor: pointer; transition: background .15s, border-color .15s; }
        .sakelar-tema:hover { background: var(--kertas-2); border-color: var(--amber); }
        .sakelar-tema:focus-visible { outline: 2px solid var(--amber); outline-offset: 2px; }
        .sakelar-tema svg { width: 19px; height: 19px; }
        .sakelar-tema .ikon-matahari { display: none; }
        :root.dark .sakelar-tema .ikon-matahari { display: block; }
        :root.dark .sakelar-tema .ikon-bulan { display: none; }

        header.atas { position: sticky; top: 0; z-index: 20; background: color-mix(in srgb, var(--kertas) 88%, transparent); backdrop-filter: blur(10px); border-bottom: 1px solid var(--garis); }
        header.atas .wrap { display: flex; align-items: center; gap: 28px; height: 64px; }
        .merek { font-weight: 800; letter-spacing: -.02em; font-size: 18px; text-decoration: none; display: flex; align-items: center; gap: 10px; }
        .merek i { width: 28px; height: 28px; border-radius: 50%; background: #1e3a8a; display: grid; place-items: center; }
        .merek svg { width: 16px; height: 16px; }
        nav.menu { display: flex; align-items: center; gap: 22px; margin-left: auto; font-size: 14.5px; font-weight: 500; }
        nav.menu a { text-decoration: none; color: var(--tinta-2); }
        nav.menu a:hover { color: var(--tinta); }

        .tombol { display: inline-flex; align-items: center; gap: 8px; padding: 11px 20px; border-radius: 10px; font-weight: 600; font-size: 15px; text-decoration: none; border: 1.5px solid transparent; transition: transform .15s, box-shadow .15s, background .15s; }
        nav.menu a.tombol.utama { color: #fff; }
        .tombol.utama { background: #1e3a8a; color: #fff; box-shadow: 0 6px 16px -6px rgba(30, 58, 138, .6); }
        .tombol.utama:hover { transform: translateY(-1px); background: #1d4ed8; }
        :root.dark .tombol.utama { background: #3b82f6; }
        .tombol.garis { border-color: var(--tinta); }
        .tombol.garis:hover { background: var(--tinta); color: var(--kertas); }
        header.atas .tombol { padding: 8px 16px; font-size: 14px; }

        .hero { padding: 40px 0 24px; position: relative; }
        .hero .wrap { position: relative; display: grid; grid-template-columns: minmax(0, 1fr); gap: 40px; padding: 56px 40px; border-radius: 24px; color: #fff; background: radial-gradient(ellipse 60% 80% at 100% 0%, rgba(96, 165, 250, .45), transparent 70%), linear-gradient(135deg, #1e3a8a, #1d4ed8); overflow: hidden; }
        .hero .wrap::after { content: ""; position: absolute; inset: 0; background-image: linear-gradient(rgba(255,255,255,.07) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.07) 1px, transparent 1px); background-size: 44px 44px; mask-image: radial-gradient(ellipse 60% 70% at 90% 10%, #000, transparent 75%); pointer-events: none; }
        .hero .wrap > * { position: relative; z-index: 1; }
        .hero .label, .hero h1 em { color: #bfdbfe; }
        .hero .lead { color: #dbeafe; }
        .hero .tombol.utama { background: #fff; color: #1e3a8a; box-shadow: none; }
        .hero .tombol.utama:hover { background: #eff6ff; }
        .hero .tombol.garis { border-color: rgba(255,255,255,.45); color: #fff; }
        .hero .tombol.garis:hover { background: rgba(255,255,255,.12); color: #fff; }
        .hero .fakta { border-color: rgba(255,255,255,.25); color: #dbeafe; }
        .hero .fakta b { color: #fff; }
        .label { display: inline-flex; gap: 8px; align-items: center; font-size: 13px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--amber-tua); }
        .label::before { content: ""; width: 28px; height: 2px; background: currentColor; }
        h1 { font: 700 clamp(34px, 5.4vw, 56px)/1.1 var(--serif); letter-spacing: -.03em; margin: 18px 0 20px; max-width: 16ch; }
        h1 em { font-style: normal; color: var(--amber-tua); }
        .lead { font-size: 19px; color: var(--tinta-2); max-width: 54ch; margin: 0 0 32px; }
        .aksi { display: flex; flex-wrap: wrap; gap: 12px; }
        .fakta { display: flex; flex-wrap: wrap; gap: 10px 28px; margin-top: 36px; padding-top: 24px; border-top: 1px solid var(--garis); font-size: 14px; color: var(--tinta-2); }
        .fakta b { color: var(--tinta); font-weight: 700; }
        .bingkai { border: 1px solid var(--garis); border-radius: 14px; background: var(--kertas-2); box-shadow: 0 30px 60px -30px rgba(2, 6, 23, .6); overflow: hidden; }
        .bingkai .bar { height: 34px; display: flex; align-items: center; gap: 7px; padding: 0 14px; border-bottom: 1px solid var(--garis); background: var(--kertas); }
        .bingkai .bar i { width: 11px; height: 11px; border-radius: 50%; background: var(--garis); }
        .bingkai .bar span { margin-left: 10px; font-size: 12px; color: var(--tinta-2); background: var(--kertas-2); padding: 3px 14px; border-radius: 6px; }
        .bingkai img { width: 100%; }
        @media (min-width: 960px) {
            .hero .wrap { grid-template-columns: minmax(0, 5fr) minmax(0, 7fr); align-items: center; }
        }

        section { padding: 88px 0; }
        section.gelap { background: var(--kertas-2); border-block: 1px solid var(--garis); }
        h2 { font: 700 clamp(28px, 3.6vw, 38px)/1.15 var(--serif); letter-spacing: -.025em; margin: 14px 0 14px; max-width: 22ch; }
        .pengantar { color: var(--tinta-2); max-width: 60ch; font-size: 17px; margin: 0 0 48px; }
        .fitur { display: grid; gap: 1px; background: var(--garis); border: 1px solid var(--garis); border-radius: 16px; overflow: hidden; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); }
        .fitur article { background: var(--kertas); padding: 32px 28px; }
        .fitur .no { font: 700 13px var(--sans); color: var(--amber-tua); letter-spacing: .1em; }
        .fitur h3 { margin: 10px 0 8px; font-size: 19px; letter-spacing: -.01em; }
        .fitur p { margin: 0; color: var(--tinta-2); font-size: 15px; }

        .alur { display: grid; gap: 0; counter-reset: langkah; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); padding: 0; margin: 0; }
        .alur li { list-style: none; position: relative; padding: 0 28px 0 0; }
        .alur li::before { counter-increment: langkah; content: counter(langkah); display: grid; place-items: center; width: 44px; height: 44px; border-radius: 50%; background: var(--amber); color: #fff; font: 700 18px var(--sans); margin-bottom: 18px; position: relative; z-index: 1; }
        .alur li::after { content: ""; position: absolute; left: 44px; right: 0; top: 22px; border-top: 2px dashed var(--garis); }
        .alur li:last-child::after { display: none; }
        .alur h3 { margin: 0 0 6px; font-size: 18px; }
        .alur p { margin: 0; color: var(--tinta-2); font-size: 15px; }

        .lencana { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12.5px; font-weight: 600; border: 1px solid; align-self: flex-start; }
        .lencana.hijau { color: var(--hijau); background: #dcfce7; border-color: #86efac; }
        .lencana.kuning { color: #a16207; background: #fef9c3; border-color: #fde047; }
        .lencana.biru { color: #1d4ed8; background: #dbeafe; border-color: #93c5fd; }
        .peran { display: grid; gap: 20px; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); }
        .peran a { display: flex; flex-direction: column; gap: 10px; text-decoration: none; background: var(--kertas); border: 1px solid var(--garis); border-radius: 16px; padding: 26px 24px; transition: transform .18s, box-shadow .18s, border-color .18s; }
        .peran a:hover { transform: translateY(-4px); border-color: #3b82f6; box-shadow: 0 18px 30px -18px rgba(30, 58, 138, .5); }
        .peran h3 { margin: 6px 0 0; font-size: 20px; }
        .peran p { margin: 0; font-size: 14.5px; color: var(--tinta-2); flex: 1; }
        .peran ul { margin: 0; padding-left: 18px; font-size: 14px; color: var(--tinta-2); }
        .peran .menuju { font-weight: 600; font-size: 14px; color: var(--amber-tua); }

        .dua { display: grid; gap: 48px; align-items: center; }
        @media (min-width: 960px) { .dua { grid-template-columns: 1fr 1fr; } }
        .centang { list-style: none; padding: 0; margin: 24px 0 0; display: grid; gap: 14px; }
        .centang li { display: grid; grid-template-columns: 24px 1fr; gap: 12px; font-size: 16px; }
        .centang svg { width: 22px; height: 22px; color: var(--hijau); margin-top: 2px; }

        .penutup { background: linear-gradient(135deg, #172554, #1e3a8a); color: #fff; text-align: center; }
        .penutup h2 { margin-inline: auto; color: #fff; }
        .penutup p { color: #dbeafe; max-width: 52ch; margin: 0 auto 32px; }
        .penutup .aksi { justify-content: center; }
        .penutup .tombol.utama { background: #fff; color: #1e3a8a; }
        .penutup .tombol.garis { border-color: rgba(255,255,255,.45); color: #fff; }
        .penutup .tombol.garis:hover { background: #fff; color: #1e3a8a; }
        footer { padding: 36px 0; font-size: 14px; color: var(--tinta-2); border-top: 1px solid var(--garis); }
        footer .wrap { display: flex; flex-wrap: wrap; gap: 16px 32px; justify-content: space-between; }
        footer nav { display: flex; flex-wrap: wrap; gap: 8px 22px; }
        footer a { color: var(--tinta-2); }

        @media (max-width: 720px) {
            nav.menu a:not(.tombol) { display: none; }
            section { padding: 64px 0; }
            .hero .wrap { padding: 32px 22px; }
        }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } html { scroll-behavior: auto; } }
    </style>
</head>
<body>

<header class="atas">
    <div class="wrap">
        <a class="merek" href="{{ route('beranda') }}">
            <i aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></i>
            {{ config('app.name') }}
        </a>
        <nav class="menu" aria-label="Menu utama">
            <a href="#layanan">Layanan</a>
            <a href="#alur">Alur</a>
            <a href="#peran">Peran</a>
            <a href="{{ route('organisasi') }}">Organisasi</a>
            <a href="{{ asset('panduan/index.html') }}">Panduan</a>
            <a class="tombol utama" href="{{ route('login') }}">Masuk</a>
            <button type="button" class="sakelar-tema" data-sakelar-tema aria-label="Ganti tema terang/gelap" title="Ganti tema terang/gelap">
                <svg class="ikon-bulan" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                <svg class="ikon-matahari" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            </button>
        </nav>
    </div>
</header>

<main>
    <div class="hero">
        <div class="wrap">
            <div>
                <span class="label">FKIP Universitas Siliwangi</span>
                <h1>Setiap surat, <em>tercatat</em> dan terlacak.</h1>
                <p class="lead">Satu sistem untuk pengajuan surat, disposisi pimpinan, naskah bernomor dengan QR verifikasi, hingga permohonan kegiatan dan profil organisasi mahasiswa FKIP.</p>
                <div class="aksi">
                    <a class="tombol utama" href="{{ route('login') }}">Masuk ke sistem →</a>
                    <a class="tombol garis" href="{{ route('verifikasi.form') }}">Verifikasi keaslian surat</a>
                    <a class="tombol garis" href="{{ asset('panduan/index.html') }}">Lihat panduan pengguna</a>
                </div>
                <div class="fakta">
                    <span><b>QR</b> pada setiap naskah</span>
                    <span>Lacak <b>tanpa akun</b></span>
                    <span><b>Disposisi</b> berjenjang</span>
                </div>
            </div>
            <div class="bingkai">
                <div class="bar"><i></i><i></i><i></i><span>Dasbor · {{ config('app.name') }}</span></div>
                <img src="{{ asset('panduan/img/admin-persuratan/01-dasbor.png') }}" width="1366" height="768" alt="Dasbor sistem persuratan: ringkasan surat masuk, naskah, dan permohonan yang sedang diproses." fetchpriority="high">
            </div>
        </div>
    </div>

    <section id="layanan">
        <div class="wrap">
            <span class="label">Yang dikerjakan sistem</span>
            <h2>Dari surat masuk sampai arsip, tanpa kertas hilang</h2>
            <p class="pengantar">Dirancang mengikuti cara kerja tata naskah dinas fakultas, dengan istilah dan alur yang sama seperti di lapangan.</p>
            <div class="fitur">
                <article>
                    <span class="no">01</span>
                    <h3>Registrasi &amp; disposisi</h3>
                    <p>Surat masuk diregistrasi, diteruskan kepada dekan, lalu didisposisikan kepada pejabat atau pegawai yang menindaklanjuti.</p>
                </article>
                <article>
                    <span class="no">02</span>
                    <h3>Naskah bernomor</h3>
                    <p>Naskah disusun, diparaf, ditandatangani, dan diberi nomor sesuai Permendikbud No. 3 Tahun 2021 tentang Tata Naskah Dinas.</p>
                </article>
                <article>
                    <span class="no">03</span>
                    <h3>Verifikasi keaslian</h3>
                    <p>Setiap naskah terbit membawa QR. Siapa pun dapat memeriksa keasliannya lewat halaman verifikasi, tanpa login.</p>
                </article>
                <article>
                    <span class="no">04</span>
                    <h3>Pelacakan tanpa akun</h3>
                    <p>Pengirim memantau tahap surat atau permohonan dan mengambil hasilnya dengan kode lacak.</p>
                </article>
                <article>
                    <span class="no">05</span>
                    <h3>Permohonan &amp; LPJ ormawa</h3>
                    <p>Pengurus mengajukan kegiatan dan ruangan, memantau progres persetujuan, mengunduh surat izin, dan mengisi LPJ.</p>
                </article>
                <article>
                    <span class="no">06</span>
                    <h3>Akses sesuai peran</h3>
                    <p>Setiap pengguna hanya melihat dan mengubah bagian yang menjadi tugasnya. Pembatasan ditegakkan di sisi server.</p>
                </article>
            </div>
        </div>
    </section>

    <section id="alur" class="gelap">
        <div class="wrap">
            <span class="label">Alur kerja</span>
            <h2>Empat langkah dari pengajuan ke hasil</h2>
            <p class="pengantar">Satu alur yang sama dipakai semua pengirim, sehingga setiap surat punya jejak yang jelas.</p>
            <ol class="alur">
                <li><h3>Ajukan</h3><p>Pengirim mengirim surat atau permohonan, dan mendapat kode lacak.</p></li>
                <li><h3>Disposisi</h3><p>Pimpinan menetapkan siapa yang menindaklanjuti dan batas waktunya.</p></li>
                <li><h3>Proses &amp; terbitkan</h3><p>Naskah disusun, dimintakan paraf dan tanda tangan, lalu diberi nomor.</p></li>
                <li><h3>Ambil &amp; verifikasi</h3><p>Hasil diambil dengan kode lacak; keasliannya dapat dicek lewat QR.</p></li>
            </ol>
            <div class="aksi" style="margin-top:40px">
                <a class="tombol garis" href="{{ asset('panduan/alur-pengirim.html') }}">Bagan alur pengiriman surat</a>
                <a class="tombol garis" href="{{ route('lacak.form') }}">Lacak surat</a>
            </div>
        </div>
    </section>

    <section id="peran">
        <div class="wrap">
            <span class="label">Untuk siapa</span>
            <h2>Satu panduan bergambar untuk tiap peran</h2>
            <p class="pengantar">Pilih peran Anda untuk melihat panduan langkah demi langkah.</p>
            <div class="peran">
                <a href="{{ asset('panduan/pengurus-ormawa.html') }}">
                    <span class="lencana biru">Pengurus ormawa</span>
                    <h3>Mahasiswa pengurus organisasi</h3>
                    <p>Mengajukan kegiatan dan ruangan, memantau progres, serta mengisi LPJ.</p>
                    <ul><li>Ajukan permohonan</li><li>Unduh surat izin</li><li>Kelola profil ormawa</li></ul>
                    <span class="menuju">Buka panduan →</span>
                </a>
                <a href="{{ asset('panduan/dekan.html') }}">
                    <span class="lencana hijau">Pimpinan</span>
                    <h3>Dekan, wakil dekan, kasubag</h3>
                    <p>Mendisposisikan surat, memaraf dan menandatangani naskah, serta menilai LPJ.</p>
                    <ul><li>Kotak masuk disposisi</li><li>Paraf &amp; tanda tangan</li><li>Dasbor kinerja</li></ul>
                    <span class="menuju">Buka panduan →</span>
                </a>
                <a href="{{ asset('panduan/admin-persuratan.html') }}">
                    <span class="lencana kuning">Admin persuratan</span>
                    <h3>Staf persuratan fakultas</h3>
                    <p>Meregistrasi surat, menerbitkan naskah bernomor, dan mengelola ormawa serta arsip.</p>
                    <ul><li>Register &amp; arsip</li><li>Validasi permohonan</li><li>Laporan</li></ul>
                    <span class="menuju">Buka panduan →</span>
                </a>
            </div>
        </div>
    </section>

    <section class="penutup">
        <div class="wrap">
            <h2>Punya surat untuk fakultas?</h2>
            <p>Masuk untuk mengajukan, atau periksa keaslian naskah dinas yang Anda terima tanpa perlu akun.</p>
            <div class="aksi">
                <a class="tombol utama" href="{{ route('login') }}">Masuk ke sistem →</a>
                <a class="tombol garis" href="{{ route('verifikasi.form') }}">Verifikasi surat</a>
                <a class="tombol garis" href="{{ route('organisasi') }}">Organisasi mahasiswa</a>
            </div>
        </div>
    </section>
</main>

<footer>
    <div class="wrap">
        <span>© {{ date('Y') }} Fakultas Keguruan dan Ilmu Pendidikan, Universitas Siliwangi</span>
        <nav aria-label="Tautan kaki">
            <a href="{{ route('verifikasi.form') }}">Verifikasi surat</a>
            <a href="{{ route('lacak.form') }}">Lacak surat</a>
            <a href="{{ asset('panduan/index.html') }}">Panduan pengguna</a>
        </nav>
    </div>
</footer>

</body>
</html>
