// Membangun public/panduan/*.html (statis, CSS di dalam berkas, tautan dan gambar relatif) dari tests/panduan/alur.json.
//   node tests/panduan/bangun.mjs
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const akar = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const alur = JSON.parse(readFileSync(join(akar, 'tests/panduan/alur.json'), 'utf8'));
const versi = (/^APP_VERSION=(.+)$/m.exec(readFileSync(join(akar, '.env.example'), 'utf8')) ?? [, 'dev'])[1].trim();
const tanggal = process.env.PANDUAN_TANGGAL ?? new Date().toISOString().slice(0, 10);
const keluar = join(akar, 'public/panduan');
const dalam = join(akar, 'docs/panduan-internal');
mkdirSync(keluar, { recursive: true });
mkdirSync(dalam, { recursive: true });

const e = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
// Penebalan **x** dan kode `x` sederhana pada teks tepercaya (berkas sumber milik tim).
const md = (s) => e(s).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>').replace(/`(.+?)`/g, '<code>$1</code>');

const css = `
:root{--biru:#1e3a8a;--abu:#475569;--garis:#e2e8f0;--latar:#f8fafc}
*{box-sizing:border-box}body{margin:0;font:16px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#1e293b;background:var(--latar)}
header{background:var(--biru);color:#fff;padding:14px 16px}header a{color:#fff}
main{max-width:860px;margin:0 auto;padding:16px}h1{font-size:1.5rem;color:var(--biru);margin:.4em 0}h2{font-size:1.15rem;margin:1.6em 0 .4em;color:var(--biru)}
.kartu{background:#fff;border:1px solid var(--garis);border-radius:10px;padding:14px 16px;margin:12px 0}
.langkah{display:grid;gap:8px}.langkah figure{margin:0}.langkah img{display:block;max-width:100%;height:auto;border:1px solid var(--garis);border-radius:8px}
.langkah figcaption{font-size:.85rem;color:var(--abu);margin-top:4px}
.daftar{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px;padding:0;list-style:none}
.daftar a{display:block;text-decoration:none;color:inherit}.daftar .kartu{margin:0;height:100%}.daftar strong{color:var(--biru)}
code{background:#eef2ff;padding:1px 5px;border-radius:4px;font-size:.9em}details{margin:6px 0}summary{cursor:pointer;font-weight:600}
footer{max-width:860px;margin:0 auto;padding:16px;color:var(--abu);font-size:.85rem}nav a{margin-right:12px}
@media print{header,nav{display:none}body{background:#fff}.kartu{break-inside:avoid}img{max-height:480px}}
`;

const halaman = (judul, isi) => `<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${e(judul)} — Panduan Persuratan FKIP</title>
<style>${css}</style>
</head>
<body>
<header><strong>Panduan Persuratan &amp; Layanan FKIP Unsil</strong></header>
<main>
${isi}
</main>
<footer>Versi aplikasi ${e(versi)} · dibuat ${e(tanggal)} · Kendala? Hubungi admin persuratan FKIP. Data pada tangkapan layar adalah data rekaan.</footer>
</body>
</html>
`;

const indeks = alur.peran.filter((p) => !p.internal).map((p) => `<li><a href="${p.kode}.html"><div class="kartu"><strong>${e(p.judul)}</strong><br><small>${e(p.tujuan)}</small></div></a></li>`).join('\n');
writeFileSync(join(keluar, 'index.html'), halaman('Beranda panduan', `<h1>Panduan pengguna</h1>
<p>Pilih peran Anda. Setiap panduan berisi langkah bernomor dengan tangkapan layar dan tanya-jawab singkat. Panduan ini siap dicetak.</p>
<ul class="daftar">
${indeks}
</ul>`));

let ada = 0;
for (const p of alur.peran) {
  const tujuan = p.internal ? dalam : keluar;
  const langkah = p.langkah.map((l, i) => {
    const berkas = `img/${p.kode}/${l.gambar}.png`;
    if (!existsSync(join(tujuan, berkas))) console.warn(`PERINGATAN: ${berkas} belum ada (jalankan tangkap.mjs)`);
    else ada++;
    return `<li class="kartu langkah"><div><strong>${e(l.judul)}</strong><br>${md(l.teks)}</div><figure><img src="${berkas}" alt="${e(`${p.judul}: ${l.judul}`)}" loading="lazy"><figcaption>Langkah ${i + 1} — ${e(l.judul)}</figcaption></figure></li>`;
  }).join('\n');
  const faq = (p.faq ?? []).map(([t, j]) => `<details><summary>${e(t)}</summary><p>${md(j)}</p></details>`).join('\n');
  writeFileSync(join(tujuan, `${p.kode}.html`), halaman(p.judul, `${p.internal ? '<nav><strong>Internal — jangan dipublikasikan</strong></nav>' : '<nav><a href="index.html">&larr; Semua panduan</a></nav>'}
<h1>${e(p.judul)}</h1>
<h2>Tujuan</h2><p>${md(p.tujuan)}</p>
<h2>Cara masuk</h2><p>${md(p.masuk)}</p>
<h2>Langkah-langkah</h2>
<ol style="padding-left:1.2em">
${langkah}
</ol>
<h2>Tanya jawab</h2>
${faq}
${p.internal ? '' : '<p><a href="index.html">&larr; Kembali ke daftar panduan</a></p>'}`));
}
console.log(`Panduan dibangun: ${alur.peran.length} halaman, ${ada} gambar ditautkan.`);
