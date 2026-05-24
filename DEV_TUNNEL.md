# Dev Tunnel (dev.pmone.id + dev-api.pmone.id)

Ini catatan supaya saya, kamu, atau Claude Code di session berikutnya tidak lupa: kenapa setup ini ada, gimana cara kerjanya, dan apa yang harus dijalankan tiap kali mau pakai.

## Kenapa ada

Xendit (Components SDK, Sessions API, webhook callback) butuh URL publik HTTPS. Server Xendit di luar sana tidak bisa nge-ping `localhost`. Tanpa tunnel, mau test flow pembayaran berarti harus push tiap kali ke production, tunggu Cloudflare Pages build, baru lihat hasilnya. Capek dan boros build budget.

Solusinya: Cloudflare named tunnel di akun pribadi yang nge-expose dua port lokal ke dua hostname publik di domain `pmone.id`:

| Hostname publik | Forward ke | Yang jalan di sana |
| --- | --- | --- |
| `dev.pmone.id` | `localhost:3000` | Nuxt (prod-build, bukan dev mode) |
| `dev-api.pmone.id` | `localhost:8000` | Laravel `php artisan serve` |

Kenapa Nuxt-nya prod-build, bukan `pnpm dev` yang biasa? Ini gotcha yang udah lama dipelajari: Vite dev mode load 100+ ESM modules paralel saat first page load. Cloudflare edge multiplex semua request itu lewat HTTP/2 streams ke origin. Stream count-nya hit limit, beberapa di-cancel, dynamic `import()` di Vue gagal di tengah jalan, hydration mismatch, halaman stuck di loading spinner dengan console penuh "Failed to fetch dynamically imported module".

Pakai `nuxi build` + serve `.output/server/index.mjs` di port 3000 bikin bundle-nya ke-minify jadi ~10 file ter-hash. Request count turun drastis, tunnel mulus. Trade-off-nya: tidak ada HMR. Lihat section "Iterasi kode" di bawah soal ini.

## Yang sudah disetup sekali, jangan diulang

Bagian ini udah dikerjain dan tidak perlu disentuh lagi kecuali ada perubahan akun:

- `~/.cloudflared/config.yml` - mapping ingress hostname → port. Tunnel ID `cb80aced-1c1a-4c06-9090-4b3a99ce85e1`.
- `~/.cloudflared/cb80aced-1c1a-4c06-9090-4b3a99ce85e1.json` - credentials. Jangan di-share, jangan di-commit.
- DNS CNAME di Cloudflare dashboard zone `pmone.id` untuk subdomain `dev` dan `dev-api`. Keduanya pointing ke tunnel.
- `frontend/.npmrc` dengan `node-linker=hoisted`. Ini wajib karena pnpm symlink layout default bikin Rollup tulis path import yang salah ke bundle, hasilnya `.output/server/index.mjs` ada `import '/consola@3.4.2/...'` yang dangling dan crash saat node start. Hoisted layout flatten ke top-level node_modules, Rollup baru bisa compute relative path yang benar.
- `frontend/nuxt.config.ts` baca `NUXT_PUBLIC_SITE_URL` + `NUXT_PUBLIC_API_URL` dari env var dengan fallback ke real production URL kalau env tidak diset. Sanctum `baseUrl` + `site.url` ikut chain yang sama.
- `frontend/package.json` punya dua npm script: `build:dev-tunnel` dan `start:dev-tunnel` yang inject env var dev tunnel sebelum jalanin nuxi/node.

## Cara pakai per-session

Tiga terminal jalan barengan:

**Terminal 1, Cloudflared:**

```bash
cloudflared tunnel run
```

Biarin idle. Output-nya nampilin tiap request yang lewat.

**Terminal 2, Laravel:**

```bash
cd ~/Herd/pmone
php artisan serve
```

Listen di `:8000`. Kalau testing flow yang butuh queue (refund, voucher email, dispatch webhook payload), buka tab lain di terminal yang sama dan jalankan:

```bash
php artisan horizon
```

**Terminal 3, Nuxt:**

```bash
cd ~/Herd/pmone/frontend
pnpm build:dev-tunnel
pnpm start:dev-tunnel
```

Build kira-kira 30-60 detik (tergantung berapa file yang berubah dari last build). Setelah build selesai, `start:dev-tunnel` serve `.output/server/index.mjs` di port 3000. Biarin running selama testing.

### Backend .env yang harus disetel

Saat mau pakai tunnel, `.env` Laravel harus include:

```env
APP_URL=https://dev-api.pmone.id
FRONTEND_URL=https://dev.pmone.id
SANCTUM_STATEFUL_DOMAINS=dev.pmone.id,dev-api.pmone.id,localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1,pmone.test
SESSION_DOMAIN=.pmone.id
CORS_ALLOWED_ORIGINS=https://dev.pmone.id,http://localhost:3000,http://pmone.test
```

`SESSION_DOMAIN=.pmone.id` (dengan titik di depan) penting biar cookie shared antara `dev.pmone.id` dan `dev-api.pmone.id` (Sanctum SPA auth butuh ini). Pakai keduanya berdampingan, jangan hapus entry localhost-nya, supaya bisa balik ke local-only mode tanpa edit ulang.

**Wajib restart `php artisan serve` setelah edit `.env`.** Config di-cache saat boot, bukan saat request.

### Xendit dashboard

Sudah disetel sekali dan stabil:

- Webhook URL: `https://dev-api.pmone.id/api/webhooks/xendit`
- Test mode aktif supaya bisa simulate payment tanpa nominal real

URL-nya stabil karena hostname-nya tetap, jadi tidak perlu re-config Xendit dashboard tiap restart tunnel.

## Iterasi kode

Tidak ada HMR di mode ini. Setiap edit code yang mau dilihat lewat dev tunnel:

1. Ctrl-C di terminal 3 (matiin `start:dev-tunnel`)
2. `pnpm build:dev-tunnel`
3. `pnpm start:dev-tunnel`

Sekitar 30 detik per cycle. Cocok untuk testing payment flow yang frekuensi edit-nya rendah. Kalau lagi iterasi UI biasa yang tidak butuh URL publik (form layout, styling, komponen non-payment), tetap pakai `pnpm dev` di plain `localhost:3000` tanpa tunnel. Dua workflow ini coexist baik-baik aja.

## Gotcha yang sering ngegigit

Beberapa hal yang udah pernah bikin saya/Claude Code stuck cukup lama. Catat di sini biar tidak terulang:

**Pakai `pnpm dev` di belakang tunnel = pasti broken.** Halaman bakal stuck di loading spinner, console penuh "Failed to fetch dynamically imported module". Ini bukan bug yang bisa di-config out, ini protocol-level antara Vite dev module fan-out dan CF HTTP/2 multiplex limit. Solusinya prod-build mode, titik.

**`frontend/.npmrc` (`node-linker=hoisted`) dan dev-tunnel-related changes di `nuxt.config.ts` jangan di-sync ke `pmone-events`.** pmone-events build via Cloudflare Pages yang punya pipeline build sendiri dan tidak butuh hoisted layout. Pmone-events juga tidak pakai dev tunnel ini. Cross-repo sync rules lihat memory `hotels-cross-repo-frontend`.

**Cookie tabrakan saat switch antara localhost dan dev.pmone.id.** Kalau habis pakai `pmone.test`/`localhost`, lalu switch ke dev.pmone.id, kadang Sanctum complain karena cookie lama untuk domain berbeda masih nyangkut. Fix: Chrome DevTools → Application → Cookies → clear semua `*.pmone.id` cookies, hard-reload halaman.

**Hard kill node :3000 kalau Ctrl-C tidak bersih:**

```bash
pkill -f "node .output/server"
```

**Cek port yang busy:**

```bash
lsof -iTCP:3000 -sTCP:LISTEN
lsof -iTCP:8000 -sTCP:LISTEN
```

**Cek cloudflared masih jalan:**

```bash
pgrep -fl cloudflared
```

Kalau output kosong, tunnel agent mati, restart `cloudflared tunnel run`.

## Database production VS dev tunnel

Jangan ketuker. Database production PM One diakses lewat MCP server `postgres` yang ngangkat tunnel terpisah ke `127.0.0.1:5433`. Itu khusus untuk query DB production read-only dari Claude Code session. Dev tunnel yang dibahas di file ini cuma untuk traffic Xendit ↔ local Laravel + browser ↔ local Nuxt. Dua hal berbeda, jangan dicampur.

## Status setup ini

Per 2026-05-24, verified working end-to-end:

- Booking dari `dev.pmone.id/hotels` → Nuxt checkout page `/hotels/checkout/[token]`
- Xendit Components SDK render channel picker
- Pilih QRIS atau OVO, submit
- SDK auto-modal tampil dengan tombol "Saya telah melakukan pembayaran ini" (test mode)
- Klik tombol → webhook `payment_session.completed` masuk ke `dev-api.pmone.id`
- Backend mark reservation `status = paid`, `payment_channel = QRIS`/`OVO`
- Redirect ke `/hotels/success` dengan badge Paid

Dua booking test (HTL-20260524-QKSE QRIS Rp754.000 dan HTL-20260524-FSLY OVO Rp2.146.000) sukses tercatat di DB local.

Kalau setup ini suatu hari rusak, mulai cek dari atas: cloudflared running? `.env` benar? port 3000 bener-bener serve `.output` (bukan `nuxt dev`)? webhook URL di Xendit dashboard masih pointing ke `dev-api.pmone.id`? Sembilan dari sepuluh waktu masalahnya ada di empat hal itu.
