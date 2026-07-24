# Plan: Optimisasi lanjutan Cloudflare Workers CPU

Ditulis 24 Jul 2026 malam oleh sesi yang mengukur semua angka di bawah langsung dari GraphQL
Cloudflare. Ditujukan untuk sesi eksekutor yang tidak punya konteks. Baca dulu sampai habis
sebelum menyentuh apa pun, terutama §1 (aturan main) dan §7 (yang sudah ditolak).

Konteks panjang ada di dua tempat, jangan diduplikasi ke sini:
- `~/Frontend/pmone-events/docs/cf-cpu-daily-log.md` - log harian, entri terakhir 24 Jul malam
  berisi semua pengukuran yang jadi dasar plan ini.
- `CLOUDFLARE_OPS_DASHBOARD_PLAN.md` (repo ini) - konteks arsitektur dua lapis + fakta akun.

---

## 1. Aturan main (mengikat, dari user)

1. **Dilarang solusi hackish.** Kalau solusinya bikin orang harus mikir dua kali waktu baca
   kodenya enam bulan lagi, jangan. User sudah menolak dua usulan dengan alasan ini
   (R2+cdnURL, internal fetch pakai URL absolut). Lihat memory `feedback-no-hackish-solutions`.
2. **Hitung impact pakai angka sebelum mengerjakan.** Di bawah beberapa persen = tidak usah.
3. **Staleness adalah hard constraint.** Staff yang meng-update konten di dashboard PM One
   harus melihat perubahannya di website event dalam hitungan detik. Kalau tidak, mereka
   mengira fiturnya rusak. Solusi apa pun yang memperlambat propagasi editan = drawback berat,
   dan solusi yang bisa MEMBAKUKAN data basi ke dalam cache ber-TTL panjang = ditolak otomatis.
4. **Tidak ada subsistem baru** (KV, R2, dsb) - sudah diputuskan user.
5. **Payload trim selesai, jangan diulang** - insiden 24 Jul (gambar posts rusak 85 menit).
6. Verifikasi cache selalu pakai **GET** (`curl -sS -o /dev/null -D -`), JANGAN HEAD - HEAD
   mem-bypass edge cache dan selalu terlihat seperti render segar.
7. **Deploy kapan pun adalah hak user - jangan pernah membatasi atau menjadwalkannya.**
   Konsekuensi teknisnya satu: deploy me-reset seluruh cache HTML (validasi build-id),
   jadi kalau ada deploy selama jendela pengukuran 25-27 Jul (hotfix, dsb), cukup catat
   waktunya di daily log dan geser awal jendela bersih ke jam setelah deploy. Angka yang
   digeser, bukan deploy-nya.
8. Aturan lockstep: skema key `buildEdgeCacheKey` (events) ⟷ `EdgeCache::homeVariantUrls()`
   (pmone) harus berubah bersamaan. Plan ini TIDAK menyentuh skema key.

---

## 2. Posisi per 24 Jul malam

Angka lengkap + metodologi di entri "24 Jul malam" `cf-cpu-daily-log.md`. Ringkasnya:

- Rolling 24 jam: **8,36M ms CPU / ~80k invocation / avg 105 ms**. Turun 54% dari puncak
  18,1M, masih 8,6× di atas laju sustainable (0,97M/hari) dan 2,8× di atas gate fase 3.
- Kuota siklus (30M) praktis habis di hari ke-3. Proyeksi invoice 22 Ags bila bertahan
  di 8,4M/hari: ±$9,50.
- **Seluruh penghematan datang dari invocation yang berkurang** (CDN menyerap), bukan dari
  invocation yang jadi murah: avg per invocation cuma 120 → 105 ms.
- 6 worker sehat (p50 4,8-27 ms): icc, iicc, inacon, flei, campx, megabuild.
  12 worker tidak efektif (p50 45-238 ms): hampir tiap request = render penuh 150-350 ms.
- Akar yang tersisa: **Cache API per-colo + Cloudflare meng-evict entri dingin jauh sebelum
  TTL**. Situs bertrafik rendah tidak pernah hangat. TTL 7-30 hari terbukti tidak tercapai
  (entri tertua yang pernah terukur: 20 jam).

Model biaya untuk memilih tuas:

```
CPU = (request yang lolos CDN) × (miss rate edge) × (biaya per render)
```

Fase 1-3 menekan faktor pertama. Faktor kedua (miss rate situs dingin) dan ketiga (biaya
render) belum tersentuh. Plan ini menyerang faktor kedua dengan dua tuas murah, dan hanya
menyerang faktor ketiga kalau data H+3 memaksa.

---

## 3. Jawaban untuk pertanyaan yang sudah terbuka

### 3.1 Apakah TTL website-settings (dan API SSR lain) perlu dinaikkan? **TIDAK.**

Jalur yang dipakai SSR bukan TTL 6 jam, melainkan `defineCachedEventHandler` `maxAge: 15`
(memory Nitro, per-isolate). Menaikkannya sudah dihitung:

- **Ceiling penghematan: 1,3%** dari CPU harian (±56k panggilan upstream/hari × ±2 ms; yang
  mahal itu render halamannya, bukan fetch datanya).
- **Drawback-nya bukan cuma "telat sebesar maxAge", tapi stale-bake:** staff menyimpan
  perubahan → PM One mem-purge URL HTML → visitor berikutnya memicu re-render → render itu
  membaca cache Nitro yang masih menyimpan data LAMA (purge tidak bisa menjangkau memory
  isolate) → **HTML basi tertulis ulang ke cache ber-TTL 7-30 hari, tepat setelah purge**.
  Staff refresh, tetap lihat data lama, mengira fitur rusak. Ini persis skenario yang user
  larang di §1 poin 3.

Keputusan: **`maxAge: 15` dipertahankan di semua handler jalur SSR.** Jangan diutak-atik.
Kalau mau, tambahkan komentar satu kalimat di `useProjectSettingsData.js` / route terkait yang
menjelaskan kenapa 15 detik itu disengaja, supaya sesi berikutnya tidak "mengoptimasi" ini.

### 3.2 Normalisasi cache key `blog/posts`/`banners` di Cache Rule? **Mustahil.**

Zona `pmone.id` plan **Free**; custom cache key = fitur Enterprise. Alternatif Free (Ignore
Query String) menyamakan `?page=2` dengan `?locale=en` = salah konten. Kardinalitas query-nya
juga asli (home `per_page=6`, news `page=N`), bukan bug. Tutup permanen.

### 3.3 Internal `$fetch` SSR tidak pernah menyentuh edge cache. **Diterima sebagai limitasi.**

`localFetch` menghasilkan URL `localhost`, Cache API menolak key beda hostname, `match()`/
`put()` diam-diam no-op. Perbaikan bersihnya tidak ada dalam batasan §1 (tanpa KV, tanpa URL
absolut), dan ceiling-nya tetap 1,3%. Didokumentasikan supaya berhenti ditemukan ulang.

---

## STATUS EKSEKUSI (24 Jul ~23:30 WIB)

- ✅ Baseline pra-perubahan tercatat (7,942M ms / 76.904 inv; zona uji 21,8%).
- ✅ AKSI 2: Smart Tiered Cache ON di **28 zona (SEMUA)**, atas perintah user "pasang
  sekaligus di semua project". Bukan lagi eksperimen 2 zona. Kesehatan situs diverifikasi
  (icc/morefood/cokelatexpo/megabuild/pmone normal; `panoramaevents.id/id` 404 itu
  pre-existing, situsnya English-only).
- ✅ AKSI 3 selesai: AI policy terverifikasi di 28 zona; WAF `block-seo-scrapers` live di
  15 zona. Komponen "junk UA" DIBATALKAN (premisnya salah, lihat §7).
- ❌ AKSI 1 (gate H+1 fase 3) **HANGUS** - rollout tiered cache global mencemari seluruh
  jendela. Diterima: fase 3 tidak akan direvert, jadi nilai diagnostiknya rendah.
- ⏳ **Pembacaan berikutnya 26 Jul.** Pembanding: baseline 7,942M ms / 76.904 invocation
  (23 Jul 16:00 -> 24 Jul 16:00 UTC), diambil sebelum semua perubahan menyala.
  Target gabungan tiered cache + block scraper: < 6M ms/hari.

Detail angka + dua temuan penting ada di `cf-cpu-daily-log.md` entri 24 Jul ~23:30.

## 4. Aksi utama (urut, kerjakan satu-satu)

Ringkasan impact vs effort:

| # | Aksi | Estimasi hemat CPU/hari | Effort | Risiko staleness |
|---|---|---|---|---|
| 1 | Baca jendela bersih H+1 fase 3 | - (prasyarat semua keputusan) | 15 mnt | - |
| 2 | Smart Tiered Cache (eksperimen 2 zona → rollout) | 1,5-3,5M ms (estimasi, diuji dulu) | ±1 jam + 48 jam observasi | **Nol** - purge tetap menghapus semua tier, TTL tak berubah |
| 3 | AI policy Search/Agent=Allow + Training=Block (SUDAH di-apply user) · WAF block junk UA & scraper SEO | 0,5-1,5M ms dari Training block (terukur) + potensi junk UA (21% trafik) | ±1 jam + 48 jam observasi | **Nol** - dan nol interstitial, block hanya match UA bot |

Kedua eksperimen reversible (toggle dimatikan / rule dihapus), dan zonanya sengaja dibuat
disjoint supaya atribusinya bersih. Sebelum mengubah setting apa pun, laporkan satu kalimat
ke user (ini setting produksi, walau reversible).

⚠️ **Constraint keras dari user (24 Jul): TIDAK BOLEH ada halaman verifikasi human
Cloudflare ("Verify you are human" / "Checking your browser") untuk pengunjung, walau satu
detik.** Semua tuas anti-bot di plan ini WAJIB berbasis action Block murni terhadap UA bot,
bukan challenge. Bot Fight Mode dan Managed Challenge dalam bentuk apa pun DILARANG (§7).

### AKSI 1 - Baca jendela bersih H+1 fase 3 (25 Jul, mulai ≥18:00 WIB)

Jendela bersih dimulai 24 Jul 11:00 UTC (jam penuh pertama setelah `edge:purge --all` 17:30
WIB). Baca setelah genap ≥24 jam:

```graphql
{ viewer { accounts(filter: {accountTag: "3797ae01f7dfb6dffb5a1b3f82713c33"}) {
  workersOverviewRequestsAdaptiveGroups(limit: 200,
    filter: {datetimeHour_geq: "2026-07-24T11:00:00Z"}, orderBy: [datetimeHour_ASC]) {
    dimensions { datetimeHour } count sum { cpuTimeUs } } } } }
```

Laju harian = `sum(cpuTimeUs)/1000 / jam × 24`. Gate: **< 3M ms/hari**. Catat hasilnya di
`cf-cpu-daily-log.md` (tabel Log + paragraf singkat), apa pun hasilnya. Lalu lanjut AKSI 2
terlepas dari lolos/tidaknya - tiered cache menyerang masalah yang memang belum disentuh
fase mana pun.

Cara akses GraphQL tanpa token: buka `dash.cloudflare.com` di tab Chrome (user sudah login),
lalu `javascript_tool` → `fetch('/api/v4/graphql', {credentials:'include', ...})`. Pola
persisnya ada di entri 24 Jul daily log.

### AKSI 2 - Smart Tiered Cache: eksperimen di 2 zona, lalu rollout

**Kenapa ini tuas terbesar yang tersisa.** Masalah nomor satu: cache per-colo. Tiap colo
(SIN, HKG, CGK, NRT, ...) membayar MISS-nya sendiri per URL per varian, dan entri di situs
bertrafik rendah di-evict sebelum sempat dipakai ulang. Smart Tiered Cache membuat semua
lower-tier colo mengambil dari SATU upper-tier colo sebelum jatuh ke "origin" (di sini:
worker). Efeknya konsolidasi: satu URL idealnya di-render sekali per TTL secara global,
bukan sekali per colo, dan entri di upper tier dapat frekuensi akses gabungan semua colo
sehingga jauh lebih tahan eviction. Gratis di semua plan, satu toggle per zona, nol kode,
nol perubahan model kesegaran (purge-by-URL Cloudflare otomatis membersihkan upper tier juga).

**Kejujuran soal ketidakpastian:** lapis CDN terbukti berada di depan worker (bukti:
`cf-cache-status: HIT` tanpa invocation; request zone ≫ invocation worker). Yang BELUM
terverifikasi: apakah MISS di lower tier untuk hostname yang di-route ke worker akan
mengecek upper tier dulu sebelum meng-invoke worker. Justru itu yang diuji. Kalau ternyata
tidak ngefek, matikan lagi, catat, selesai - ruginya nol.

**Zona uji** (dua pola berbeda, bukan zona eksperimen BFM):

| Zona | Zone ID | Pola |
|---|---|---|
| franchise-expo.co.id (flei) | `5873a050745d3ab9ebfdcd74aca500dc` | CPU tertinggi (1,23M/hari), p50 sehat 14 ms tapi p75 147 ms - ekornya gendut |
| keramika.co.id | `bc3968bbfec8e468a52377b607a6c235` | Situs dingin klasik, p50 174 ms |

**Cara menyalakan:** dashboard → zona → Caching → Tiered Cache → aktifkan Smart Tiered
Caching. Via API dash-session (`credentials:'include'`):
`PATCH /api/v4/zones/{zone_id}/cache/tiered_cache_smart_topology_enable` body
`{"value":"on"}`. Token purge yang ada TIDAK punya permission ini (cuma Cache Purge + Zone
Read), jadi pakai sesi dashboard atau toggle manual.

**Catat baseline per zona SEBELUM toggle** (CPU/hari per scriptName dari
`workersOverviewRequestsAdaptiveGroups` filter `scriptName`, plus p50 dari
`workersInvocationsAdaptive`), simpan di daily log.

**Gate keberhasilan (48 jam, banding baseline 24-26 Jul zona yang sama):**
- Invocation worker/hari zona uji turun ≥20%, ATAU
- CPU/hari worker zona uji turun ≥30%, DAN
- p50 keramika turun signifikan (target < 60 ms).

Lolos → rollout ke semua 16 zona event (25 Jul+2). Tiga zona menumpang (`pmone.id` untuk
ai, `megabuild.co.id` untuk renex, `askindo.id` untuk iicc) ikut di-toggle juga - efeknya
zona-wide dan tidak merugikan penumpang lain (ini murni topologi cache). Gagal → matikan,
catat di daily log kenapa (sertakan angka), jangan diulang.

**Drawback yang diterima:** MISS pertama dapat satu hop ekstra ke upper tier (belasan ms
latensi). Trafik mayoritas ID/SG dan upper tier kemungkinan SIN, jadi kecil.

### AKSI 3 - Blokir crawler tak bernilai: WAF UA-block + toggle AI crawler

**Kenapa BUKAN Bot Fight Mode.** BFM sempat ada di draft plan ini dan DICORET oleh user
(24 Jul). Mekanisme penegakan BFM adalah halaman challenge Cloudflare, dan itu melanggar
constraint keras di §4: false positive (browser privacy agresif, iOS Lockdown Mode, sebagian
in-app browser Instagram, IP reputasi jelek) akan melihat interstitial, dan di plan Free BFM
tidak bisa dikecualikan sama sekali (tanpa allowlist, tanpa skip rule). Jangan diusulkan
ulang, lihat §7.

**Hipotesis yang diuji tetap sama:** sebagian besar render ekor panjang di situs dingin
adalah crawler tanpa nilai bisnis. Bukti tidak langsung: 21,7k request `/news/*`/hari
tersebar di 1.847 URL unik (11,7 req/URL/hari) - manusia mengumpul di artikel baru, pola
sedatar ini bau crawler.

**Mekanisme yang dipakai: action Block murni berbasis User-Agent.** Bot yang jujur menyebut
namanya di UA dapat 403 di lapis WAF, sebelum worker, CPU nol. Manusia tidak mungkin match
(browser tidak pernah mengirim UA "AhrefsBot"), jadi secara konstruksi nol interstitial,
bukan sekadar "jarang".

**⚠️ Keputusan user soal AI crawler (24 Jul malam): JANGAN blanket-block.** Situs-situs ini
situs marketing event - tujuannya justru ditemukan, dan channel AI terbukti menghasilkan
visibility (AI Overview Google untuk "pameran franchise" menampilkan info flei di posisi
teratas). Pemetaan dampak per kelompok bot:

| Kelompok | Contoh | Kalau diblok | Keputusan |
|---|---|---|---|
| Search crawler terverifikasi | Googlebot, Bingbot | Hilang dari Search + AI Overviews/Copilot | Tak pernah kena AI-block; aman |
| AI search & live fetcher | OAI-SearchBot, ChatGPT-User, PerplexityBot, Claude-User, Applebot | Assistant menjawab pakai data basi/pihak ketiga; fatal karena tanggal/venue ganti tiap edisi | **BIARKAN LEWAT** |
| Training-only / dataset | GPTBot, CCBot, Bytespider | Konten tak dipakai training; jawaban live tak terpengaruh | Boleh diblok, nilai kecil, opsional |
| Scraper SEO/komersial | AhrefsBot, SemrushBot, MJ12bot, dst | Tidak ada dampak user-facing | **Blok** via WAF rule |

Catatan Google: AI Overviews dibangun dari indeks Search (Googlebot); token `Google-Extended`
(kontrol training Gemini) tidak mempengaruhi AI Overviews. Skenario "pameran franchise" aman
apa pun konfigurasinya - yang terancam oleh blanket-block adalah ChatGPT, Perplexity, Claude,
dan Meta AI.

**KEPUTUSAN USER (24 Jul malam) - AI bot policies:** lewat UI baru "Configure AI bot
policies" (Security → Settings → Bot traffic; menggantikan toggle legacy per 15 Sep 2026),
user meng-apply sendiri ke semua domain: **Search = Allow · Agent = Allow · Training =
Block.** Warning mixed-purpose di UI itu aman untuk kita: bot vital (Googlebot, Bingbot,
OAI-SearchBot, ChatGPT-User, Perplexity) murni kategori Search/Agent; yang ikut keblok cuma
bot campuran macam Bytespider/Amazonbot, relakan.

**Angka terukur (23-24 Jul, 12 zona terbesar, 24 jam, GET, sampled):**

| Kategori | Request/hari | Lolos CDN/hari | Catatan |
|---|---:|---:|---|
| Total 12 zona | 2.512.408 | 230.575 | non-hit ≠ invocation (termasuk 403 WAF & asset) |
| AI Training | 14.345 (0,57%) | ~6.900 | estimasi hemat **0,5-1,5M ms/hari (6-18% CPU)** ≈ $0,30-0,90/bln |
| AI Search+Agent (di-allow) | 1.516 | ~1.600 | biaya ~0,1-0,3M ms/hari, sengaja dipertahankan demi visibility |
| **Junk (UA kosong, HeadlessChrome, python dkk)** | **527.603 (21%)** | **126.955** | **12× lebih besar dari AI bot** - target utama WAF di bawah |
| Scraper SEO | 4.558 | 4.836 | target WAF |

Top AI training yang lolos CDN (6 zona sampel): meta-externalagent 1.254 · Bytespider 1.056
· Amazonbot 1.016 · ClaudeBot/anthropic ~800 · GPTBot 347 · CCBot 201.

Langkah per zona:

1. **Verifikasi policy AI konsisten di semua 16 zona event** (user apply manual 24 Jul;
   cek yang kelewat, replikasi bisa via API dash-session). Zona menumpang ikut aturan yang
   sama; `pmone.id` juga aman diberi policy ini (AI policy ≠ bot blocking umum, integrasi
   Google Sheets bukan AI crawler).
2. WAF custom rule, action **Block**, untuk scraper SEO/komersial DAN junk UA
   (HeadlessChrome, python-requests, aiohttp, scrapy, go-http-client, dst). UA kosong:
   cek Security Events dulu sebelum diblok - pastikan tidak ada monitoring/integrasi
   internal yang tidak mengirim UA. Expression awal (sesuaikan setelah lihat Security
   Events):

   ```
   (http.user_agent contains "AhrefsBot") or (http.user_agent contains "SemrushBot") or
   (http.user_agent contains "MJ12bot") or (http.user_agent contains "DotBot") or
   (http.user_agent contains "PetalBot") or (http.user_agent contains "DataForSeoBot") or
   (http.user_agent contains "BLEXBot") or (http.user_agent contains "serpstatbot") or
   (http.user_agent contains "ZoominfoBot")
   ```

   ⚠️ Plan Free maksimal **5 custom rules per zona**, dan zona-zona ini sudah punya rule dari
   pekerjaan WAF 23 Jul. Cek dulu jumlah rule existing; kalau slot habis, TAMBAHKAN kondisi
   ke rule blocklist yang sudah ada, jangan bikin rule baru.

**Zona uji** (disjoint dari AKSI 2):

| Zona | Zone ID | Kenapa |
|---|---|---|
| cokelatexpo.id | `4c66d1e086d20fed9aeddfb4c53d944b` | p50 terburuk (238 ms), trafik manusia kecil |
| indooutingexpo.co.id | `a30a110f263c9c37c24900442bd36cfa` | p50 185 ms, pola sama |

**Gate (48 jam):** CPU/hari worker zona uji turun ≥15% (lebih rendah dari gate BFM lama
karena UA-block hanya menangkap bot jujur). Cek Security Events untuk melihat siapa yang
kena dan berapa banyak. Lolos → rollout.

**Rollout kalau lolos:** semua zona event. `pmone.id` TIDAK usah disentuh (worker pmone
cuma 1,8% CPU, dan zona itu membawa integrasi Google Sheets - jangan ambil risiko apa pun
di sana). `askindo.id` cek dulu penghuni lain di zona itu.

**Limitasi jujur + titik berhenti:** hanya menangkap bot yang jujur ber-UA. Scraper yang
menyamar jadi Chrome lolos. Kalau gate 48 jam tidak tercapai, artinya mayoritas ekor adalah
scraper menyamar, dan **berhenti di sini** - semua tuas anti-bot yang lebih kuat berbasis
challenge dan itu dilarang permanen oleh user.

---

## 5. Kondisional - HANYA kalau gate H+3 (27 Jul, < 2M ms/hari) gagal setelah AKSI 2+3

Urutannya investigasi dulu, jangan langsung koding:

1. **Profiling render satu situs mahal** (keramika atau cokelatexpo): jalankan lokal via
   `wrangler dev`/workerd inspector, profil ke mana 150-350 ms per render pergi (komponen?
   payload parse? plugin?). Hanya tindak lanjuti temuan yang menjanjikan >20% pengurangan
   biaya render. Tanpa profil = tidak ada perubahan kode.
2. **Ukur dulu share varian `__cm=light`** pada MISS manusia (bot sudah di-collapse ke satu
   varian). Varian color-mode menggandakan entri HTML per URL; kalau share light ternyata
   kecil (dugaan: ya, default dark), tuas ini mati dan JANGAN lanjut. Kalau besar, opsi
   client-side color mode (pola standar @nuxtjs/color-mode, bukan hackish) bisa diusulkan ke
   user - dengan drawback jujur: menyentuh 16 situs + risiko flash saat first paint.
3. **Prewarm top-N URL pasca-deploy** - HANYA masuk akal kalau AKSI 2 terbukti bekerja
   (tanpa tiered cache, warming cuma menghangatkan satu colo = sia-sia). Itu pun nilainya
   sebatas mengubah gelombang refill dari user-facing jadi terkontrol.

---

## 6. Strategis - butuh keputusan produk, JANGAN dieksekusi tanpa user

**SSG/prerender untuk situs event yang sudah lewat masa aktifnya.** CPU-nya jadi ~0, tapi
model kesegaran berubah total: editan konten butuh rebuild (±3 menit, via deploy hook),
bukan purge instan. Itu tepat jenis kebingungan staff yang dilarang §1 poin 3, jadi default
plan ini: **tidak**. Baru layak dibahas kalau ada daftar situs yang secara bisnis memang
beku (tidak pernah diedit) - dan itu keputusan user, bukan teknis.

---

## 7. Ditolak permanen - jangan diusulkan ulang

| Usulan | Kenapa ditolak | Sumber |
|---|---|---|
| Cache Response Rules (`strip_set_cookie`) untuk bikin render segar cacheable | DIUJI 24 Jul di 15 zona, NOL efek. Phase `http_response_cache_settings` hanya jalan pada respons dari origin sungguhan; route Worker mengakhiri request sebelum itu. Uji isolasi tanpa kondisi response-header juga nihil. Sudah di-rollback | daily log 24 Jul, TEMUAN 2 |
| Blokir "junk UA" (UA kosong / HeadlessChrome) | Premisnya salah: UA kosong = operasi Cache API worker sendiri (`requestSource: edgeWorkerCacheAPI`), HeadlessChrome mayoritas cache HIT di aset. Tidak ada bot di sana | daily log 24 Jul, TEMUAN 1 |
| Blokir Baidu/Yandex/Sogou | Search engine, punya nilai user-facing. Hanya scraper SEO murni yang diblok | keputusan 24 Jul |
| **Bot Fight Mode / Managed Challenge / challenge apa pun** | Penegakannya = halaman verifikasi human; user melarang interstitial walau 1 detik; Free plan tak bisa allowlist BFM | keputusan user 24 Jul |
| Blanket-block AI crawler (toggle "Block AI Scrapers and Crawlers") | Memutus OAI-SearchBot/ChatGPT-User/PerplexityBot/Claude-User → assistant menjawab pakai data basi pihak ketiga, padahal tanggal/venue event ganti tiap edisi; situs marketing hidup dari ditemukan. Blok selektif training-only tetap boleh (AKSI 3) | keputusan user 24 Jul |
| Naikkan `maxAge` handler Nitro jalur SSR | Ceiling 1,3%; stale-bake pasca purge (§3.1) | dihitung 24 Jul |
| Custom cache key Cache Rule | Enterprise-only; zona Free | dicek 24 Jul |
| Internal fetch pakai URL absolut | Hackish, ditolak user | memory `feedback-no-hackish-solutions` |
| Payload trim allowlist | Insiden gambar posts 24 Jul; sisa tuas bernilai sen | daily log |
| `_nuxt/**` ke R2 + `app.cdnURL` | Hackish, ditolak user; nilai $0-0,60/bln | `CLOUDFLARE_OPS_DASHBOARD_PLAN.md` §7 |
| Subsistem baru (KV/R2) untuk cache | Ditolak user | plan dashboard §status |
| Fetching client-side | Kehilangan 2 lapis cache, menghantam origin | keputusan user |

---

## 8. Ekonomi yang jujur

Tarif kelebihan $0,02 per juta ms. Seluruh sisa masalah ini bernilai **±$4,50/bulan** pada
laju sekarang. AKSI 2-3 layak karena effort-nya jam-jaman dan reversible. Apa pun yang butuh
hari-harian kerja atau menambah risiko produksi TIDAK layak untuk $4,50/bulan - tolak ukur
ini yang dipakai untuk memangkas §5 dan §6.

Ekspektasi invoice: 22 Ags $7-9,50 (hari 1-3 siklus sudah membakar 29,3M, tidak bisa
di-undo); 22 Sep $5,00-6,00 kalau AKSI 2/3 berhasil.

---

## 9. Pelaporan

Setiap aksi selesai → tulis hasilnya (angka sebelum/sesudah, keputusan, tanggal) ke
`cf-cpu-daily-log.md`, dan update memory `cf-workers-cpu-optimization` kalau ada perubahan
status besar. User TIDAK mau commit/push tanpa perintah eksplisit - file boleh ditulis,
git jangan disentuh.
