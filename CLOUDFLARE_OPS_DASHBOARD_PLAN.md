# Rencana: Halaman Cloudflare Ops Dashboard di PM One

Status: **draft konteks** — sengaja ringan di sisi teknis/arsitektur. Dokumen ini ditulis oleh
sesi Claude Code lain (23 Jul 2026) yang mengerjakan seluruh perbaikan Cloudflare di bawah, dan
ditujukan untuk **sesi berikutnya yang tidak punya konteks apa pun**. Silakan timpa/perdalam
bagian "Lingkup" dan seterusnya; **jangan buang bagian "Konteks" dan "Fakta terverifikasi"** —
itu hasil investigasi berhari-hari yang mahal untuk diulang.

---

## 1. Konteks: kenapa halaman ini dibutuhkan

### Masalah yang memicu semuanya

Tagihan Cloudflare akun `Nextifier@gmail.com` melonjak dari **$5,00 → $12,52** (invoice 22 Jul
2026). Penyebabnya Workers CPU: **373.604.916 ms** terpakai dari kuota gratis 30.000.000 ms.

Akar masalahnya (ini penting dipahami, karena menjelaskan seluruh desain sekarang):

> Di preset Nitro `cloudflare_module`, **Worker berjalan SEBELUM cache Cloudflare** — Cache
> Rule zone tak bisa meng-cache render segar Worker (selalu membawa `set-cookie`), sehingga
> awalnya **setiap request halaman = 1 render SSR penuh** (±150–350 ms CPU).
>
> **KOREKSI PENTING (24 Jul):** setelah edge-cache in-worker hidup, arsitekturnya menjadi
> **DUA lapis**: salinan cache kita bebas `set-cookie` → **memenuhi syarat CDN** → Cache Rule
> ikut meng-cache-nya. Rantai: `CDN (cf-cache-status) → Worker → Cache API (x-edge-cache) →
> render`. Konsekuensi analisa: request ZONE ≠ invocation worker (zone bisa 31k req API
> sementara worker hanya 9k invocation) — **analisa CPU wajib dari dataset
> `workersInvocationsAdaptive`, JANGAN dari `httpRequestsAdaptiveGroups`.**

Ini tidak terlihat sebelumnya karena dulu memakai preset `cloudflare-pages` (project Pages
diperlakukan sebagai origin, sehingga Cache Rule bekerja). Migrasi ke Workers pada 21 Jul 2026
mematikannya secara diam-diam.

### Solusinya, dan kenapa itu melahirkan kebutuhan halaman ini

Perbaikannya: **cache di dalam worker** memakai Cloudflare Cache API (`caches.default`),
di-deploy ke 16 website event. Hasilnya p50 CPU turun dari ±115–145 ms ke **4–17 ms**.

Konsekuensi arsitektural yang melahirkan kebutuhan dashboard:

- HTML di-cache **7 hari (list & home) sampai 30 hari (detail)**; API **6 jam** (tier STABLE ber-?locale) atau **120 dtk** (tier VARIED ?page/?search/?placement).
- Karena TTL sepanjang itu, **purge adalah mekanisme utama kesegaran konten** — bukan TTL.
  Saat editor mem-publish artikel/brand/rundown, backend ini mem-purge URL persisnya dalam
  hitungan detik.
- Artinya: **kalau purge bermasalah, konten bisa basi berhari-hari sampai sebulan.** Sudah
  pernah terjadi sekali (23 Jul, purge jadi no-op diam-diam selama beberapa jam karena cache key
  memuat build-id yang tak mungkin ditebak backend).

Jadi halaman ini bukan sekadar "nice to have": ia adalah **katup kontrol dan jendela observasi**
untuk sistem yang kesegarannya bergantung pada purge yang berjalan benar.

### Kenapa juga butuh visibilitas/analytics

Sepanjang investigasi, satu-satunya cara membaca CPU harian, hit-rate cache, dan status build
adalah lewat **sesi browser** (Claude membuka dashboard Cloudflare dan menembak GraphQL API dari
konteks halaman). Itu tidak berkelanjutan — user tidak bisa memantau sendiri, dan sesi Claude
mana pun harus mengulang ritual yang sama. Angka-angka ini perlu berada di dashboard sendiri.

### Repo mana yang mana (mudah tertukar)

| Repo | Isi | Peran di cerita ini |
|---|---|---|
| `~/Herd/pmone` (**repo ini**) | Laravel 13 + admin Nuxt 4 di `frontend/` | Sumber data & **pemilik logika purge**. Halaman dashboard dibuat DI SINI. |
| `~/Frontend/pmone-events` | Monorepo Nuxt, 16 website event | Yang di-cache. Kode edge-cache-nya ada di `layers/base/server/`. |

16 worker event + 1 worker admin (`pmone`) + 3 worker dari repo `levenium` = **20 worker** total
di akun ini.

---

### Status terkini (ditulis 24 Jul 2026 malam — baca ini dulu)

**Kronologi fase:** Fase 1–2 (23 Jul): WAF 28 zone, edge cache in-worker + `x-edge-build`,
TTL HTML 7–30 hari, purge varian, home purgeable, 404 di-cache, watch-paths dipersempit di 20
worker. Gate H+1 fase-2 **GAGAL 7,8×** → investigasi 24 Jul menemukan model dua-lapis (di atas)
dan sumber CPU riil = **TTL API kita sendiri (60–120 dtk)** yang memaksa re-render tiap ≤2 mnt
per endpoint per colo. **Fase 3 (24 Jul sore, `154661e`+`29bbcaa` di pmone-events):** API tier
STABLE 6 jam (11 endpoint ?locale-only, purge-covered), 302 i18n "/" di-cache, 20 ikon runtime
→ client bundle, `getCachedData` di profile/event.

**INSIDEN 24 Jul (resolved, pelajaran wajib):** trim payload posts membuang field
`featured_image.md/.sm/.original` yang ternyata dirender PostCard → semua gambar posts rusak
±85 menit → di-REVERT total + `php artisan edge:purge --all` (pemakaian perdana). Pelajaran:
perubahan bentuk data API wajib audit konsumen exhaustive (layers+apps); anomali visual saat
verifikasi = temuan, bukan noise.

**⚠️ Batasan `purge_everything` yang HARUS dipahami dashboard ini:** tiga situs menumpang zone
pihak lain — `ai.pmone.id` di zone pmone.id (purge site-nya ikut mengosongkan **cdn.pmone.id
+ api.pmone.id + admin**), `renex.megabuild.co.id` di zone megabuild.co.id (saling nuke dgn
megabuild), `iicc.askindo.id` di zone askindo.id. Tombol "purge all"/"purge project" di
dashboard WAJIB menampilkan peringatan dampak ini.

**Yang sedang ditunggu (tak ada pekerjaan kode):** gate H+1 fase-3 (25 Jul) < 3M ms/hari;
gate H+3 (27 Jul) < 2M ms/hari — metodologi lengkap + query GraphQL ada di
`~/Frontend/pmone-events/docs/cf-cpu-daily-log.md`. Invoice 22 Ags (perkiraan $5,30–6,50;
20,2M terbakar pra-fix) lalu 22 Sep (target $5,00). Sesi lama punya cron harian 09:23 WIB
yang MATI bersama sesinya — sesi baru yang diminta "jalankan laporan gate Cloudflare" cukup
ikuti metodologi di daily-log tsb.

**Keputusan user yang mengikat:** fetching tetap server-side (client-side ditolak — kehilangan
2 lapis cache & menghantam origin); tak ada subsistem baru (KV/R2 ditolak); mikro-optimasi
payload SELESAI — sisa tuas bernilai sen dengan risiko nyata (terbukti lewat insiden).

## 2. Yang SUDAH ada di repo ini (jangan bangun ulang)

Seluruh mesin purge sudah jadi dan terverifikasi live. Halaman dashboard idealnya **membungkus**
ini, bukan menulis ulang logikanya.

| File | Isi |
|---|---|
| `app/Support/EdgeCache.php` | `purgeTags()`, `purgeUrls()`, `purgeSite()`, `sitesFor()`, `homeVariantUrls()`, `zoneForHost()`. Tidak pernah melempar exception — kegagalan hanya di-log. |
| `app/Jobs/PurgeEdgeCache.php` | Job antrean, unik + debounce 5 detik. |
| `app/Support/TagAwareResponseCache.php` | Decorator container `responsecache` — menangkap ±109 call-site `ResponseCache::clear($tags)` tanpa mengedit satu pun. |
| `app/Console/Commands/EdgePurge.php` | `php artisan edge:purge {--project=|--all}` — katup darurat. **Ini template perilaku untuk tombol di UI.** |
| `config/edge-sites.php` | Registry 16 situs (project ↔ domain ↔ locale) + peta tag→path. |
| `app/Traits/ClearsResponseCache.php` | Dipakai 31 model; model bisa deklarasi `edgeCachePaths()` untuk purge URL presisi (Post, Brand, Guest, Form sudah). |
| `docs/cloudflare-edge-cache-token.md` | Cara membuat/roll token purge. |

**Token yang sudah ada:** `CLOUDFLARE_EDGE_PURGE_TOKEN` di `.env` — account-owned, scope
**All zones**, permission `Zone → Cache Purge` + `Zone → Read`. Sudah terverifikasi menjangkau
28 zone.

⚠️ **Aturan lockstep antar-repo** (jangan sampai dilanggar): skema cache key di
`pmone-events/layers/base/server/utils/edgeCache.ts` (`buildEdgeCacheKey`) dan
`EdgeCache::homeVariantUrls()` di repo ini **harus berubah bersamaan**. Purge Cloudflare adalah
exact-match termasuk query string; kalau skema key berubah sepihak, purge diam-diam tidak
mencocokkan apa pun.

---

## 3. Lingkup halaman yang diminta

Permintaan user (silakan pertajam):

- **Daftar semua project CF Workers** beserta status.
- **Purge**: per project · selected (checkbox di TableData) · all.
- **Rebuild**: per project · selected · all.
- **Status build** dan informasi lain.
- **Analytics komprehensif** dari Cloudflare.

### Rekomendasi & peringatan dari sesi sebelumnya

1. **Rebuild sebaiknya alat ops, BUKAN jalur utama kode masuk produksi.** Build watch paths di
   20 worker sudah dipersempit (lihat §4), jadi deploy otomatis dari git sudah presisi: edit satu
   app → hanya app itu rebuild; edit `layers/base` → semua app event rebuild; edit `docs/` → nol
   rebuild. Mengganti otomatisasi ini dengan tombol manual akan membuat **git tidak lagi sama
   dengan produksi** — perbaikan bisa tidak pernah ter-deploy karena lupa diklik.
2. **Semua tombol di sini destruktif dan berbiaya.** "Rebuild all" = 16 build **dan** mereset
   seluruh cache HTML (validasi `x-edge-build` memperlakukan HTML dari build lain sebagai
   basi) → gelombang render ulang. Perlu: konfirmasi eksplisit, audit log siapa menekan apa,
   rate-limit, dan idealnya **estimasi dampak ditampilkan sebelum eksekusi**.
3. **Purge adalah bagian paling berguna dan paling aman** — prioritaskan ini kalau harus memilih.
4. **Jangan panggil API Cloudflare tiap halaman dibuka.** 20 worker × beberapa query akan lambat
   dan kena rate limit. Cache hasilnya server-side (±5 menit).
5. **Status build real-time adalah bagian paling rapuh** (lihat §4) — rancang agar boleh gagal
   tanpa merusak halaman.
6. Token tetap di `.env`, semua panggilan dari sisi Laravel. Jangan pernah ke browser.

---

## 4. Fakta Cloudflare yang sudah diverifikasi (23 Jul 2026)

Semua angka di bawah **hasil pengecekan langsung**, bukan dari dokumentasi.

**Account ID:** `3797ae01f7dfb6dffb5a1b3f82713c33` · 20 worker · 28 zone.

### Izin token yang perlu ditambahkan

| Kebutuhan | Permission | Status |
|---|---|---|
| Purge | `Zone → Cache Purge` | sudah ada |
| Domain → zone | `Zone → Read` | sudah ada |
| Daftar worker + `modified_on` | `Account → Workers Scripts → Read` | **perlu ditambah** |
| Analytics (GraphQL) | `Account → Account Analytics → Read` | **perlu ditambah** |

Tombol rebuild **tidak butuh token**: pakai **Deploy Hooks** (URL ber-secret, cukup di-POST).
Endpoint-nya sudah dicek hidup; saat ini **belum ada hook yang dibuat** (`[]`).

### Biaya

**Nol tambahan.** GraphQL Analytics, Cache Purge, dan Deploy Hooks semuanya included. Tidak ada
layanan Cloudflare baru yang perlu diaktifkan.

Satu-satunya yang terkonsumsi adalah **build minutes**:
- Kuota **6.000 menit/bulan**; terpakai **782 menit** di siklus berjalan (2 hari, mayoritas dari
  deploy intensif saat perbaikan).
- Satu build ≈ **3 menit** → **"rebuild all" ≈ 48 menit** → plafon ±125 klik/bulan.

### Limitasi yang ditemui langsung

| Limit | Angka | Implikasi |
|---|---|---|
| GraphQL: zone per query | **maks 4** | 28 zone harus di-chunk (±7 query) |
| GraphQL: rentang waktu | **maks 4 minggu 4 hari** | grafik >1 bulan harus disambung |
| Data analytics | ter-sampling + lag beberapa menit | jangan janjikan real-time |
| Purge by URL | **30 URL / request** | sudah ditangani batching di `EdgeCache` |

### ⚠️ Risiko teknis utama

Endpoint status build (`/api/v4/accounts/{acct}/builds/*` — triggers, builds/latest,
deploy_hooks) adalah **API internal dashboard, TIDAK terdokumentasi**. Sesi sebelumnya
mengaksesnya lewat cookie sesi browser; **apakah ia menerima Bearer token belum terverifikasi**,
dan Cloudflare bisa mengubahnya sewaktu-waktu.

Mitigasi: `modified_on` dari endpoint resmi `workers/scripts` sudah memberi "terakhir deploy
kapan" dan cukup untuk sebagian besar kebutuhan. Perlakukan status build live (queued/running)
sebagai **lapisan opsional yang boleh gagal**.

### Dataset GraphQL yang relevan

- `workersOverviewRequestsAdaptiveGroups` — CPU + request, bisa di-group per `date`/`datetimeHour`/`scriptName`.
- `workersInvocationsAdaptive` — per worker, punya `quantiles { cpuTimeP50 … }`.
- `httpRequestsAdaptiveGroups` (level zone) — `cacheStatus`, path, status code.

Header diagnostik yang dipancarkan worker event dan berguna ditampilkan: **`x-edge-cache`**
(`HIT` / `MISS` / `SKIP` / `STALE-BUILD`) dan **`x-edge-build`**.

---

## 5. Stack halaman ini

Admin frontend = **Nuxt 4** di `frontend/`, halaman di `frontend/app/pages/`. Sudah ada
komponen `ui/table` dan `ui/table-data` yang bisa dipakai untuk daftar + checkbox seleksi.
Backend = Laravel 13; endpoint API admin mengikuti pola yang sudah ada di `routes/api.php` +
`app/Http/Controllers/Api/`.

Detail arsitektur (struktur controller, resource, komponen, state) **sengaja tidak ditentukan di
sini** — itu bagian yang akan diperdalam sesi berikutnya.

---

## 6. Bacaan lanjutan (di repo pmone-events)

Kalau perlu memahami sisi worker-nya:

- `docs/cf-cpu-investigation-2026-07.md` — investigasi lengkap + tabel billing 12 siklus.
- `docs/cf-cpu-plan-2026-07.md` — rencana fase 1.
- `docs/cf-cpu-daily-log.md` — log harian, gate keputusan, dan matematika tagihan yang jujur.
- `layers/base/server/utils/edgeCache.ts` — skema cache key (**lockstep** dengan repo ini).
- `layers/base/shared/cf-cache-rules.ts` — tabel TTL, sumber kebenaran apa yang boleh di-cache.

---

## 7. Catatan penutup dari sesi sebelumnya

Sebuah usulan yang lebih "canggih" (memindahkan `_nuxt/**` ke R2 + `app.cdnURL` supaya deploy
tidak pernah meng-invalidate cache) **sudah ditolak user** dengan alasan yang tepat: terlalu
hackish dan menyulitkan pemahaman aplikasi sendiri di masa depan. Sisa penghematan yang
diperdebatkan hanya **$0–0,60/bulan** (tarif $0,02 per juta ms di atas kuota 30M) — tidak
sepadan dengan biaya kompleksitas.

Pegang prinsip itu saat mendesain halaman ini: **utamakan yang mudah dipahami enam bulan lagi**
di atas yang paling optimal.
