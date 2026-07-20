# Migrasi pmone frontend: Cloudflare Pages → Cloudflare Workers

Status: **Fase 1 in progress** (branch `chore/migrate-workers`, worktree `~/Herd/pmone-workers`).

Runbook migrasi deployment admin frontend Nuxt (`frontend/`) dari CF Pages ke CF
Workers + Static Assets. Setiap fase reversibel sampai cutover domain (Fase 4).

---

## Konteks pmone (hasil audit)

- **Preset sekarang**: auto-`cloudflare-pages` (dari env `CF_PAGES` saat build Pages). Tidak ada `preset:` eksplisit.
- **Sudah ada** `nitro.cloudflare.deployConfig: true` + `nodeCompat: true` → Nitro auto-generate wrangler config saat build (penyederhana besar).
- **Env var**: `NUXT_PM_ONE_API_KEY` (RAHASIA server). `NUXT_PUBLIC_API_URL` / `NUXT_PUBLIC_SITE_URL` / `NUXT_SANCTUM_BASE_URL` (publik; di prod default ke `brand.apiUrl`/`brand.siteUrl` → kemungkinan tak perlu di-set eksplisit). `BRAND=pmone` (build-time).
- **PWA aktif** (`@vite-pwa/nuxt` → `sw.js`) — butuh header no-cache + flow update SW.
- **Domain `pmone.id` di belakang Cloudflare Access (Zero Trust)** — policy Access harus dipastikan tetap match hostname saat cutover. **Langkah paling kritis.**
- **CF Cache Rules** level-zona sudah live → persist lintas Pages→Workers (match by hostname/path).
- Build butuh heap 8 GB (`NODE_OPTIONS=--max-old-space-size=8192`).
- Worker terakhir ~12.5 MiB raw ≈ ~4–5 MB gzip → **aman** di bawah limit Workers 10 MB gzipped (paid).

---

## Fase 0 — Pra-terbang (nol risiko)

1. Pastikan `main` bersih & deploy Pages terakhir hijau (titik rollback).
2. Screenshot setting Pages project pmone: build command, env vars, compatibility flags (`nodejs_compat` di Production + Preview), custom domain, dan **konfigurasi Cloudflare Access** yang menempel di `pmone.id`.
3. Branch kerja sudah dibuat: `chore/migrate-workers` (via worktree `~/Herd/pmone-workers`, agar tidak mengganggu working tree `main`).

## Fase 1 — Perubahan kode (di branch, reversibel)

Dikerjakan di worktree `~/Herd/pmone-workers/frontend`:

**PENTING (best practice, tanpa wrangler.jsonc hand-written):** preset `cloudflare-module` + `deployConfig:true` sudah meng-generate `.output/server/wrangler.json` LENGKAP (main + `assets` binding dengan path relatif dihitung otomatis + `nodejs_compat` + compat date) PLUS redirect `.output/.wrangler/deploy/config.json`. Deploy pakai `npx wrangler --cwd .output deploy` yang membaca config generated itu — jadi path entry/assets **tak mungkin melenceng** dari output Nitro. Nitro juga meng-handle routing SSR asset-vs-worker sendiri (tak perlu `not_found_handling`/`run_worker_first` manual).

4. **Preset**: `nitro.preset = "cloudflare_module"` di `nuxt.config.ts`.
5. **`cloudflare.deployConfig: true` + `nodeCompat: true` DIPERTAHANKAN.**
6. **Nama Worker** via `nitro.cloudflare.wrangler = { name: "pmone" }`. (Catatan: Pages project bernama "pmone" sudah ada; jika deploy/dashboard menolak nama saat transisi, ganti jadi mis. `pmone-app` di sini + di Worker.) **TIDAK ada root `wrangler.jsonc`** — sengaja, agar tak konflik dengan config generated.
7. `_headers`/`_redirects` didukung native Workers Static Assets — routeRules pmone tetap ter-generate. **Verifikasi `sw.js` tetap `Cache-Control: no-cache`** (kritis untuk PWA).

## Fase 2 — Buat Worker + deploy via Workers Builds (TANPA build lokal) — TESTED

Best practice tanpa build lokal = **Workers Builds** (CF build dari git, seperti Pages tapi 6 paralel). Alur dashboard yang SUDAH TERUJI (pmone + levenium-ui):

1. Dashboard → **Compute (Workers) → Create → "Continue with GitHub"** (GitHub sudah tersambung dari Pages — nol OAuth baru). *Catatan: kartu "Continue with GitHub" kadang perlu di-klik 2x + scroll; pakai kotak Search repo untuk pilih repo secara stabil.*
2. Pilih repo → **Next** → halaman "Set up your application":
   - **Project name** = nama Worker. Samakan dengan `nitro.cloudflare.wrangler.name`. (Kalau bentrok dengan Pages project senama, pakai suffix mis. `-ui`/`-app`.)
   - **Build command** (default `npm run build` — GANTI):
     - Single-repo (pmone): `pnpm build`
     - **Monorepo (levenium-ui / pmone-events):** `pnpm --filter @events/ui build` (root `/`, pnpm install workspace jalan sendiri).
   - **Deploy command** (default `npx wrangler deploy` — WAJIB GANTI, config generated ada di `.output`):
     - Single-repo: `npx wrangler --cwd .output deploy`
     - **Monorepo:** `npx wrangler --cwd apps/ui/.output deploy`
   - **Advanced settings → Path (root directory):** single-repo `/frontend`; **monorepo `/`** (JANGAN `apps/ui` — pnpm workspace install harus di root).
   - **Variable name/value** (env): tambah di sini kalau perlu (klik **Encrypt** untuk secret). levenium-ui = kosong. pmone = `NUXT_PM_ONE_API_KEY`.
   - **Production branch: default `main`** (form import TIDAK punya selektor branch). Kalau config migrasi ada di `main` → langsung benar. Kalau di branch lain → lihat GOTCHA di bawah.
3. **Deploy** → Worker dibuat + build pertama jalan (~6 mnt).
4. **Enable URL test:** Worker → **Domains** → toggle ON `Production <name>.<subdomain>.workers.dev` (Public untuk test). *pmone.id nanti dilindungi Access, jadi URL test Public OK.*
5. **Validasi di `*.workers.dev`** SEBELUM sentuh domain produksi.

**GOTCHA production-branch (kalau config migrasi di branch, BUKAN main):** build pertama pakai `main` (salah). Fix: **Cancel build** → Worker **Settings → Build → Branch control** → set production branch ke branch migrasi (+ non-prod branch builds Disabled biar main tak trigger build gagal) → trigger build benar (push commit / empty commit ke branch itu).

**GOTCHA placeholder:** saat Worker baru dibuat, "Hello world" (11 byte) di-deploy sebagai placeholder. Meng-enable workers.dev saat build BELUM selesai → URL balas 200 "Hello world" (false-positive). Tunggu versi build asli ter-deploy (cek Deployments → Active deployment berubah).

## Fase 3 — Env vars & secrets

- **pmone**: `NUXT_PM_ONE_API_KEY`. Bisa di build var (ke-bake ke bundle, FUNGSIONAL) TAPI untuk prod lebih aman **runtime Secret**: Worker → Settings → **Variables and secrets** (runtime) → Add → Encrypt.
- **levenium-ui / coming-soon**: tidak perlu env.

## Fase 4 — Cutover domain (TESTED di levenium-ui; pmone.id + Access = hati-hati)

1. **Cek Cloudflare Access dulu** (KHUSUS pmone.id): Zero Trust → Access → Applications → app pelindung `pmone.id`. Access berbasis hostname → biasanya tetap berlaku, TAPI konfirmasi tak putus saat pindah Pages→Worker. (levenium-ui: TIDAK ada Access.)
2. **Lepas domain dari Pages project lama** (Pages project → Custom domains → hapus domain) supaya bebas dipasang ke Worker.
3. **Pasang ke Worker:** Worker → **Domains → Add Domain** → ketik domain (mis. `ui.levenium.com`) → Add. DNS otomatis (nameserver sudah di CF). CNAME/route dibuat CF.
4. Tunggu propagasi; verifikasi domain serve dari Worker (+ Access prompt untuk pmone.id).

## Fase 5 — Matikan Pages lama

- **Matikan auto-deploy Pages project** (jangan hapus dulu — rollback beberapa hari). Setelah yakin, hapus Pages project.

## Fase 6 — Verifikasi & rollback

- Smoke test domain (halaman kunci + console bersih). Enable **Workers Logs** (Settings → Observability) untuk runtime debugging — keuntungan besar vs Pages.
- **Rollback**: lepas domain dari Worker + pasang balik ke Pages project + re-enable auto-deploy Pages.

---

## Titik rawan

- **Cloudflare Access (pmone.id)** — risiko #1. Salah = admin terbuka/terkunci. Uji segera pasca-cutover.
- **Service Worker PWA (pmone)** — SW lama bisa serve konten basi; pastikan `sw.js` no-cache. (levenium-ui/coming-soon tak ada PWA.)
- **Limit `_routes.json` (100 rule)** yang diakali di Pages — **hilang** di Workers → keuntungan.
- **Bundle limit** — Workers 10 MB **gzipped** vs Pages 25 MiB raw. pmone ~12.5 MiB, levenium-ui ~10 MiB raw ≈ jauh di bawah 10 MB gzip → aman.
- **Working tree bersama sesi lain** — kalau ada sesi Claude paralel di repo yang sama, JANGAN `git add -A` / `git commit` tanpa path; index di-share → menyapu kerja mereka. Pakai `git commit <path>` spesifik.

---

## Status eksekusi

- **pmone**: LIVE di **pmone.id + www.pmone.id** dari Worker `pmone` (config di `main`, commit `84f95944`, production branch `main`). Cutover 2026-07-20 TERVERIFIKASI: `pmone.id/`→302 `/login?redirect=/dashboard`, `/login`→200 `<title>Log in · PM One</title>`, asset `/_nuxt/*.js`→200, `www.pmone.id/`→301 `https://pmone.id/`. workers.dev `pmone.nextifier.workers.dev` tetap ON. Kedua domain dilepas dulu dari Pages project `pmone` sebelum di-Add ke Worker. Secret masih build-var (pindah ke runtime Secret disarankan). Pages project lama belum dihapus (rollback).
- **levenium-ui**: Worker `levenium-ui`, config di `main` (commit `6ffdaec`), build `pnpm --filter @events/ui build` + deploy `npx wrangler --cwd apps/ui/.output deploy`. Domain `ui.levenium.com` DIPINDAH ke Worker (referensi cutover TESTED).
- **Sisa (user):** pmone-events 16 app (monorepo, pola levenium-ui) + levenium/monara. Ganti preset per app → Worker per app → domain.
