#!/usr/bin/env bash
#
# Surat — skrip rilis produksi (dijalankan DI SERVER, dari akar repositori).
#
# Dipanggil oleh job `deploy` di .github/workflows/ci.yml (setelah CI hijau) atau
# oleh cron (--tarik). Skrip ikut ter-versioning bersama kode, jadi tidak ada lagi
# salinan manual di ~/docker-apps/deploy.sh yang bisa menyimpang dari repo.
#
# Pemakaian:
#   bash scripts/deploy.sh                 # rilis commit yang sedang ter-checkout
#   bash scripts/deploy.sh --tarik         # fetch origin/main; rilis hanya bila ada commit baru (untuk cron)
#   bash scripts/deploy.sh --rollback TAG  # balik ke image lama (tanpa build ulang)
#   bash scripts/deploy.sh --list          # tampilkan tag image yang tersedia
#
# Urutan rilis: kunci → cadangan DB → build → migrasi (image baru, sebelum ganti
# container) → up -d → health check → pangkas image lama.
# Idempoten; keluar non-zero bila ada langkah gagal.
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

COMPOSE=(docker compose -f docker-compose.prod.yml)
CABANG="${SURAT_CABANG:-main}"
KEEP_IMAGE_TAGS="${SURAT_KEEP_IMAGE_TAGS:-5}"
HEALTH_ATTEMPTS=24   # 24 x 5 dtk = 2 menit
HEALTH_INTERVAL=5

log() { printf '\n==> %s\n' "$*"; }

# Satu rilis pada satu waktu (CD dan cron tidak boleh saling menyalip di tengah migrasi).
kunci() {
  # --tarik meneruskan kunci ke proses exec-nya (fd 9 diwariskan); jangan meminta ulang.
  [[ "${SURAT_TERKUNCI:-0}" == "1" ]] && return 0
  exec 9>"${TMPDIR:-/tmp}/surat-deploy.lock"
  if ! flock -n 9; then
    echo "Rilis lain sedang berjalan; keluar." >&2
    exit 75
  fi
}

# Port loopback nginx dibaca dari .env (HTTP_PORT); reverse proxy fakultas memotong awalan /surat.
url_kesehatan() {
  local port
  port="$(sed -n 's/^HTTP_PORT=\([0-9]\{1,5\}\).*/\1/p' .env 2>/dev/null | tail -n1)"
  echo "http://127.0.0.1:${port:-8016}/api/health"
}

tunggu_sehat() {
  local url attempt
  url="$(url_kesehatan)"
  log "health check → $url"

  for ((attempt = 1; attempt <= HEALTH_ATTEMPTS; attempt++)); do
    if curl -fsS --max-time 10 "$url" 2>/dev/null | grep -q '"ok"'; then
      printf 'OK: aplikasi sehat (percobaan %d).\n' "$attempt"
      return 0
    fi
    sleep "$HEALTH_INTERVAL"
  done

  echo "GAGAL: $url tidak sehat setelah $((HEALTH_ATTEMPTS * HEALTH_INTERVAL)) detik." >&2
  echo "--- 50 baris terakhir log php ---" >&2
  "${COMPOSE[@]}" logs --tail=50 php >&2 || true
  return 1
}

# Cadangan DB (mariadb-dump via profil backup) sebelum migrasi. Gagal mencadangkan = rilis
# dibatalkan, kecuali SURAT_LEWATI_CADANGAN=1 (mis. server baru tanpa data).
cadangan() {
  if [[ "${SURAT_LEWATI_CADANGAN:-0}" == "1" ]]; then
    log "cadangan dilewati (SURAT_LEWATI_CADANGAN=1)"
    return 0
  fi
  if [[ -z "$("${COMPOSE[@]}" ps -q php 2>/dev/null)" ]]; then
    log "cadangan dilewati: container php belum berjalan (rilis pertama?)"
    return 0
  fi

  log "cadangan DB pra-rilis"
  "${COMPOSE[@]}" --profile backup run --rm backup
}

daftar_tag() {
  docker image ls surat-app --format '{{.Tag}}\t{{.CreatedSince}}\t{{.Size}}' | sort
}

# Rollback = nyalakan ulang container dari image lama; tidak ada build, hitungan detik.
rollback() {
  local tag="${1:?Pemakaian: deploy.sh --rollback <tag>}"

  for img in surat-app surat-web; do
    if ! docker image inspect "$img:$tag" >/dev/null 2>&1; then
      echo "GAGAL: image $img:$tag tidak ada. Tag tersedia:" >&2
      daftar_tag >&2
      exit 1
    fi
  done

  log "rollback ke $tag"
  SURAT_VERSI="$tag" "${COMPOSE[@]}" up -d --no-build --remove-orphans
  tunggu_sehat

  cat <<EOF

CATATAN: rollback hanya mengembalikan KODE, bukan skema database. Bila rilis yang
gagal sempat bermigrasi, pulihkan cadangan terakhir (lihat docs/DEPLOY.md).
Working tree git masih di commit baru; sinkronkan bila rollback ini permanen.
EOF
}

# Sisakan beberapa tag terakhir supaya rollback tetap mungkin.
pangkas_image() {
  local img tag
  for img in surat-app surat-web; do
    docker image ls "$img" --format '{{.Tag}} {{.CreatedAt}}' \
      | grep -vE '^(latest|<none>) ' \
      | sort -k2 -r \
      | tail -n +"$((KEEP_IMAGE_TAGS + 1))" \
      | awk '{print $1}' \
      | while read -r tag; do
          docker image rm "$img:$tag" >/dev/null 2>&1 || true
        done
  done
  docker image prune -f >/dev/null 2>&1 || true
}

rilis() {
  local sha versi
  sha="$(git rev-parse --short HEAD)"
  versi="$(git describe --tags --always)"
  log "rilis $sha ($versi) — $(git log -1 --pretty=%s)"

  cadangan

  log "build image (php + nginx)"
  SURAT_VERSI="$sha" "${COMPOSE[@]}" build

  # Migrasi memakai image BARU, sebelum container diganti. Migrasi harus kompatibel mundur
  # (rilis lama masih melayani permintaan sampai up -d selesai).
  log "migrasi basis data"
  SURAT_VERSI="$sha" "${COMPOSE[@]}" run --rm --no-deps -T php php artisan migrate --force --no-interaction

  log "up -d"
  SURAT_VERSI="$sha" "${COMPOSE[@]}" up -d --remove-orphans

  # Tag "latest" mengikuti rilis aktif supaya `docker compose up` tanpa variabel tidak mundur versi.
  docker tag "surat-app:$sha" surat-app:latest
  docker tag "surat-web:$sha" surat-web:latest

  tunggu_sehat
  pangkas_image

  log "status"
  "${COMPOSE[@]}" ps

  cat <<EOF

Selesai. Rilis $sha ($versi) aktif.
Rollback cepat:  bash scripts/deploy.sh --rollback <tag>
Tag tersedia:
$(daftar_tag)
EOF
}

# Mode cron: ambil origin/main; keluar diam-diam bila tidak ada commit baru.
tarik() {
  git fetch --prune origin "$CABANG"

  local lokal jauh
  lokal="$(git rev-parse HEAD)"
  jauh="$(git rev-parse "origin/$CABANG")"

  if [[ "$lokal" == "$jauh" ]]; then
    echo "Sudah terbaru ($(git rev-parse --short HEAD))."
    return 0
  fi

  log "commit baru: $(git rev-parse --short HEAD) → $(git rev-parse --short "origin/$CABANG")"
  git reset --hard "origin/$CABANG"
  # Skrip ini mungkin ikut berubah; jalankan versi terbaru untuk rilis.
  SURAT_TERKUNCI=1 exec bash scripts/deploy.sh
}

case "${1:-}" in
  --list)     daftar_tag ;;
  --rollback) kunci; rollback "${2:-}" ;;
  --tarik)    kunci; tarik ;;
  '')         kunci; rilis ;;
  *)          echo "Argumen tidak dikenal: $1" >&2; exit 2 ;;
esac
