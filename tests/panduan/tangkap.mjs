// Menangkap layar panduan per peran (Playwright/Chromium) berdasarkan tests/panduan/alur.json.
// Dijalankan di container Playwright (lihat README "Memperbarui panduan"): memerlukan PANDUAN_BASE_URL dan PANDUAN_PASSWORD.
// Hanya data rekaan dari PanduanSeeder (APP_ENV=local). Gagal bila halaman memberi galat atau CSP melanggar skrip di halaman aplikasi.
import { chromium } from 'playwright-core';
import { mkdirSync, readFileSync, rmSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const akar = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const dasar = (process.env.PANDUAN_BASE_URL ?? 'http://localhost:8028').replace(/\/$/, '');
const sandi = process.env.PANDUAN_PASSWORD;
if (!sandi) { console.error('PANDUAN_PASSWORD wajib diisi (kata sandi akun demo dari PanduanSeeder).'); process.exit(2); }

const alur = JSON.parse(readFileSync(join(akar, 'tests/panduan/alur.json'), 'utf8'));
const tampilan = { desktop: { width: 1366, height: 768 }, ponsel: { width: 390, height: 844 } };
const galat = [];
const peranDipilih = process.env.PANDUAN_PERAN ? process.env.PANDUAN_PERAN.split(',') : null;

const browser = await chromium.launch({ args: ['--no-sandbox'] });
const konteks = new Map();

async function halaman(akun, jenis) {
  const kunci = `${akun ?? 'anon'}|${jenis}`;
  if (konteks.has(kunci)) return konteks.get(kunci);
  const ctx = await browser.newContext({ viewport: tampilan[jenis], locale: 'id-ID', timezoneId: 'Asia/Jakarta', deviceScaleFactor: 1, isMobile: jenis === 'ponsel' });
  const page = await ctx.newPage();
  page.on('console', (m) => { if (/content security policy/i.test(m.text())) galat.push(`CSP: ${m.text().slice(0, 200)} @ ${page.url()}`); });
  page.on('pageerror', (e) => galat.push(`JS: ${e.message.slice(0, 200)} @ ${page.url()}`));
  if (akun) {
    // Pengurus ormawa dan pegawai masuk lewat /masuk; akun panel (pejabat, admin) lewat /admin/login (MFA).
    const jalur = /panduan\.(pegawai|pengurus)/.test(akun) ? '/masuk' : '/admin/login';
    await page.goto(`${dasar}${jalur}`, { waitUntil: 'networkidle' });
    await page.fill('input[type=email], input[name=email]', akun);
    await page.fill('input[type=password]', sandi);
    await page.click('button[type=submit]');
    await page.waitForURL((u) => !/\/(masuk|login)$/.test(u.pathname), { timeout: 15000 }).catch(() => {});
    await page.waitForLoadState('networkidle');
    if (/\/(masuk|login)$/.test(new URL(page.url()).pathname)) throw new Error(`Gagal masuk sebagai ${akun} (${page.url()})`);
  }
  konteks.set(kunci, page);
  return page;
}

for (const peran of alur.peran) {
  if (peranDipilih && !peranDipilih.includes(peran.kode)) continue;
  const dir = join(akar, 'public/panduan/img', peran.kode);
  rmSync(dir, { recursive: true, force: true });
  mkdirSync(dir, { recursive: true });

  for (const l of peran.langkah) {
    const akun = 'akun' in l ? l.akun : peran.akun;
    const page = await halaman(akun, l.viewport ?? 'desktop');
    const resp = await page.goto(`${dasar}${l.url}`, { waitUntil: 'networkidle' });
    if (resp && resp.status() >= 400) galat.push(`HTTP ${resp.status()} ${l.url} (${peran.kode}/${l.gambar})`);
    if (l.klik) {
      const tautan = page.getByRole('link', { name: l.klik }).first();
      if (await tautan.count() === 0) { galat.push(`Tautan "${l.klik}" tidak ditemukan di ${l.url} (${peran.kode}/${l.gambar})`); continue; }
      await Promise.all([page.waitForLoadState('networkidle'), tautan.click()]);
    }
    await page.waitForTimeout(l.tunggu ?? 400);
    await page.screenshot({ path: join(dir, `${l.gambar}.png`), fullPage: false });
    console.log(`✓ ${peran.kode}/${l.gambar}`);
  }
}

await browser.close();
if (galat.length) { console.error(`\n${galat.length} masalah:\n- ${galat.join('\n- ')}`); process.exit(1); }
