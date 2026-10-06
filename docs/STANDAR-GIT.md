# Standar Git & Commit — Support System FKIP Unsil

> Berkas ini identik di setiap folder sistem. Setiap langkah di `04-PROMPT-BERTAHAP.md` diakhiri satu (atau beberapa) commit mengikuti aturan di bawah.

## 1. Prinsip

1. **Satu langkah vibecoding = satu commit yang lolos uji.** Jangan menumpuk beberapa langkah dalam satu commit, dan jangan meng-commit kode yang gagal `pint`/`pest`.
   **Commit hanya bila test lulus**: blok Commit ditulis sebagai satu rantai `&&` (`<x> ./vendor/bin/pint && <x> php artisan test && git add -A && git commit …`), sehingga `git commit` tidak pernah berjalan bila test gagal; hook `pre-commit` (§6) menjalankan test lagi sebagai pengaman kedua. Jangan memakai `--no-verify`.
2. **Satu fase = satu branch fitur → Pull Request → squash/merge ke `main` → tag versi.**
3. `main` selalu bisa di-deploy. Tidak ada commit langsung ke `main` setelah fondasi (Fase 0–1) selesai.
4. Agen AI **boleh** menulis kode, tetapi **manusia** yang meninjau `git diff` dan menjalankan commit.

## 2. Format pesan commit — Conventional Commits 1.0.0

```
<tipe>(<cakupan>): <ringkasan imperatif, huruf kecil, ≤ 72 karakter>

[badan opsional: apa & mengapa, bukan bagaimana]

[footer opsional: Refs: #12 | BREAKING CHANGE: ...]
```

| Tipe | Kapan dipakai | Contoh |
|---|---|---|
| `feat` | Fitur baru untuk pengguna | `feat(peminjaman): tambah pengajuan peminjaman ruangan` |
| `fix` | Perbaikan bug | `fix(nomor-surat): cegah nomor ganda saat terbit bersamaan` |
| `docs` | Dokumentasi saja | `docs(readme): tambah cara menjalankan horizon` |
| `style` | Format kode tanpa ubah logika | `style: jalankan laravel pint` |
| `refactor` | Ubah struktur tanpa ubah perilaku | `refactor(mutasi): pindahkan logika ke action SetujuiMutasi` |
| `perf` | Peningkatan kinerja | `perf(inventaris): tambah indeks lokasi dan kondisi` |
| `test` | Menambah/memperbaiki uji | `test(disposisi): uji akses pimpinan dan admin` |
| `build` | Dependensi, Docker, Vite | `build: pasang filament 5 dan spatie permission` |
| `ci` | Pipeline CI | `ci: tambah workflow pest dan larastan` |
| `chore` | Pemeliharaan lain | `chore: inisialisasi repositori` |
| `revert` | Membatalkan commit | `revert: feat(laporan): ekspor pdf` |

Migrasi basis data memakai tipe biasa dengan cakupan `db`: `feat(db): tambah tabel mitra dan dokumen kerja sama`.

**Cakupan** (`scope`) = nama modul dalam kebab-case, sesuai daftar modul di PRD (`auth`, `master`, `inventaris`, `disposisi`, `laporan`, `db`, `ui`, dst.).

**Breaking change**: tambahkan `!` setelah cakupan (`feat(api)!: ...`) dan footer `BREAKING CHANGE: ...`.

## 3. Penamaan branch

```
main                         # produksi, terlindungi
feat/<fase>-<ringkas>        # feat/f3-master-data
fix/<ringkas>                # fix/nomor-surat-ganda
chore/<ringkas>              # chore/upgrade-laravel-13-x
```

## 4. Versi & tag (SemVer)

- Selesai Fase 1 (fondasi) → `v0.1.0`; setiap fase berikutnya menaikkan *minor* (`v0.2.0`, `v0.3.0`, …).
- Rilis produksi pertama → `v1.0.0`. Perbaikan setelah rilis → *patch* (`v1.0.1`).
```bash
git tag -a v0.2.0 -m "Fase 2: autentikasi & peran"
git push origin main --tags
```

## 5. Siklus kerja tiap langkah

```bash
# 0) awal fase
git switch main && git pull
git switch -c feat/f3-master-data

# 1) tempel prompt langkah ke agen AI, tinjau hasilnya

# 2) gerbang kualitas (wajib lolos) — di dalam container; <x> = alias shell aplikasi (STANDAR-TEKNIS §2.1)
<x> ./vendor/bin/pint
<x> ./vendor/bin/phpstan analyse --memory-limit=1G
<x> php artisan test        # atau <x> ./vendor/bin/pest

# 3) tinjau perubahan
git status
git diff

# 4) commit
git add -A
git commit -m "feat(master): tambah resource program studi"

# 5) akhir fase
git push -u origin feat/f3-master-data
# buka Pull Request → review → merge → tag
```

## 6. Pagar otomatis (dipasang di Fase 0)

**`.githooks/commit-msg`** — menolak pesan commit yang tidak sesuai standar:
```bash
#!/usr/bin/env bash
pattern='^(feat|fix|docs|style|refactor|perf|test|build|ci|chore|revert)(\([a-z0-9-]+\))?!?: .{1,72}$'
first_line=$(head -n1 "$1")
if ! [[ "$first_line" =~ $pattern ]] && ! [[ "$first_line" =~ ^Merge ]]; then
  echo "✖ Pesan commit tidak sesuai Conventional Commits."
  echo "  Contoh: feat(inventaris): tambah impor excel"
  exit 1
fi
```

**`.githooks/pre-commit`** — memastikan format & uji cepat. PHP berjalan di container (STANDAR-TEKNIS §2), jadi hook memanggil `docker compose exec`; nama service diturunkan dari nama folder repo (`~/code/<app>` → `<app>-php`):
```bash
#!/usr/bin/env bash
set -e
root="$(git rev-parse --show-toplevel)"
svc="${APP_PHP_SERVICE:-$(basename "$root")-php}"
cd "$root"
if ! docker compose ps --status running --services | grep -qx "$svc"; then
  echo "✖ Container $svc belum berjalan. Jalankan: docker compose up -d"
  exit 1
fi
docker compose exec -T "$svc" ./vendor/bin/pint --test
docker compose exec -T "$svc" php artisan test --parallel --stop-on-failure
```
Git dijalankan di host (WSL); hanya pint & test yang masuk container.

Aktifkan (sekali per klon):
```bash
chmod +x .githooks/*
git config core.hooksPath .githooks
```

## 7. Yang TIDAK boleh masuk repositori

`.env`, `/vendor`, `/node_modules`, `/storage/*.key`, `storage/app/tmp/*` (berkas sementara hasil ekspor), dump database, berkas `.zip` cadangan, kredensial gateway (token Fonnte, SMTP), `auth.json`. Gunakan `.env.example` berisi kunci tanpa nilai rahasia.

## 8. CI minimal (GitHub Actions / GitLab CI)

Jalankan pada setiap PR: `composer install` → `pint --test` → `phpstan` → `pest` dengan service MySQL 8.4 dan Redis 7. PR tidak boleh di-merge bila CI merah.
