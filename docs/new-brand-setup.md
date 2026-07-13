# Runbook Setup Brand Baru (Monara / whitelabel)

Panduan provisioning satu brand baru dari codebase ini: satu site Forge (API),
satu database PostgreSQL, satu bucket R2, satu project Cloudflare Pages buat
admin frontend. Semuanya dipilih lewat nilai env dan variabel build `BRAND`.
Gak ada fork kode, gak ada migrasi data lama.

Sepanjang dokumen ini, ganti:

- `<brand>` = id brand huruf kecil, misal `monara`, `acme`
- `<domain>` = domain utama brand, misal `monara.id`
- Admin frontend jalan di `https://<domain>`, API di `https://api.<domain>`,
  CDN media di `https://cdn.<domain>`

Kerjakan kurang lebih berurutan: kode -> DNS -> database -> R2 -> Forge ->
email -> OAuth -> CF Pages -> bootstrap -> config per project -> smoke test.
Brand pertama sekitar sehari penuh (yang lama itu propagasi DNS sama
verifikasi email, bukan kerjaannya), brand berikutnya 2-4 jam.

---

## 0. Kode dulu (sekali per brand, di repo ini)

Brand harus ada di registry frontend sebelum apa pun dideploy:

1. Bikin `frontend/brands/<brand>/{meta.ts,Logo.vue,LogoMark.vue,Home.vue}`
   (copy folder `monara` sebagai template), lalu daftarkan di
   `frontend/brands/index.ts`.
2. Isi `meta.ts` pakai nama asli, `siteUrl`, `apiUrl`, identitas perusahaan,
   dan kontak. Biarkan `assetsReady: false` sampai langkah 0.3 beres.
3. Taruh aset asli di `frontend/public/brands/<brand>/`:
   `favicon.ico`, `icons/apple-touch-icon.png` (180x180),
   `icons/icon-192x192.png`, `icons/icon-512x512.png`, plus opsional
   `screenshots/desktop-1.png` (1280x833) dan `screenshots/mobile-1.png`
   (400x842). Setelah lengkap, ubah `assetsReady: true`.
4. Ingat aturannya: kode shared gak boleh nyebut nama brand. Yang boleh
   hardcode cuma `brands/<brand>/**` dan `public/brands/<brand>/**`.
   `BrandLiteralGuardTest` yang jaga sisi backend.

Backend gak butuh perubahan kode sama sekali per brand. Semuanya lewat env
(`config/brand.php`, `APP_NAME`, `FRONTEND_URL`, dst).

## 1. DNS (Cloudflare)

Tambahkan `<domain>` sebagai zone di akun Cloudflare, lalu bikin:

| Record | Target | Catatan |
|---|---|---|
| `api.<domain>` A | IP publik VPS (server "MinusOne": `209.38.58.220`) | Proxy ON (orange cloud); app sudah trust IP edge Cloudflare (`bootstrap/app.php` trustProxies) |
| `<domain>` | custom domain project CF Pages | Kebuat otomatis waktu kamu pasang custom domain di Pages (langkah 7) |
| `cdn.<domain>` | custom domain bucket R2 | Kebuat otomatis oleh R2 (langkah 3) |

Opsional: kalau admin brand ini gak boleh diakses publik, tiru policy
Zero Trust / Cloudflare Access yang dipakai pmone.

## 2. Database (di VPS)

```sql
CREATE DATABASE <brand>;
```

(Sebagai user PostgreSQL `forge`, lewat panel Database di Forge atau psql.
Gak perlu extension apa pun, schema-nya cuma pakai fitur inti Postgres.)

Soal kapasitas: `max_connections` 200 masih cukup buat 2 brand. Mulai pikirkan
PgBouncer sekitar brand keempat (lihat `docs/scale-runbook.md`).

## 3. Cloudflare R2 (media + backup)

Satu bucket per brand. Media di root bucket, dump spatie/backup masuk prefix
`backups/` di bucket yang SAMA (dua disk-nya berbagi `AWS_BUCKET`).

1. R2 -> Create bucket -> namanya `<brand>`.
2. Bucket -> Settings -> Custom Domains -> tambahkan `cdn.<domain>` (zone-nya
   harus di akun CF yang sama). Ini yang bikin object bisa dibaca publik lewat
   host CDN; app nulis URL media terhadap host ini (`AWS_URL`).
3. R2 -> Manage API Tokens -> bikin token yang di-scope ke bucket ini dengan
   permission Object Read & Write. Salin Access Key ID / Secret dan endpoint
   akunnya `https://<account-id>.r2.cloudflarestorage.com`.
4. Nilai-nilai tadi masuk ke `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`,
   `AWS_ENDPOINT`, `AWS_BUCKET=<brand>`, `AWS_URL=https://cdn.<domain>` di
   langkah 4.4. (`AWS_DEFAULT_REGION=auto`, `MEDIA_DISK=r2`.) CORS bucket gak
   perlu disetel: semua upload lewat API Laravel, bukan langsung dari browser.

## 4. Forge (site API di VPS yang sama)

Tiru site `api.pmone.id` persis. Yang beda cuma nilai env.

1. **New Site** di server MinusOne: domain `api.<domain>`, PHP 8.4,
   web directory `/public`. (pmone gak pakai isolated site.)
2. **Repository**: repo GitHub yang sama, branch `main`, zero-downtime deploy
   (layout symlink "current", kayak `/home/forge/api.pmone.id/current`).
3. **Deploy script**: copy punya pmone apa adanya (composer install,
   `php artisan migrate --force`, `php artisan permissions:sync`,
   `npm ci --omit=dev` buat Browsershot kalau ada, `horizon:terminate`, dst).
   Jaga script kedua site tetap IDENTIK huruf per huruf; semua perbedaan
   dibawa env. Saran tambahan di KEDUA site, setelah key barunya masuk ke
   dua-duanya `.env`: panggil `php artisan env:audit` sebelum `migrate`,
   supaya key yang kelupaan bikin deploy gagal, bukan diam-diam jatuh ke
   default.
4. **Environment**: mulai dari `.env` pmone, lalu ubah semua baris di tabel
   delta bawah. Generate key baru pakai `php artisan key:generate --show`
   terus tempel (jangan pernah pakai ulang `APP_KEY` pmone).
5. **SSL**: cert LetsEncrypt buat `api.<domain>` (sama kayak pmone; SSL mode
   zone Cloudflare tetap Full (strict)).
6. **Daemon** (aturan "workflow identik": semua brand jalanin Horizon):
   `php8.4 artisan horizon`, directory `/home/forge/api.<domain>/current`.
7. **Scheduler**: Scheduled Job di Forge, tiap menit:
   `php8.4 /home/forge/api.<domain>/current/artisan schedule:run`.
8. Nyalakan **Quick Deploy** (sekali push, semua site brand ikut deploy) dan
   **notifikasi deploy gagal**. Dengan banyak site, migration bisa sukses di
   satu brand tapi gagal di brand lain; kamu harus tahu saat itu terjadi.
9. Deploy pertama: `migrate --force` bakal bangun schema di database kosong.

### 4.4 Tabel delta `.env` (yang berubah dibanding env pmone)

| Key | Nilai buat `<brand>` | Catatan |
|---|---|---|
| `APP_NAME` | `"<Nama Brand>"` | Jadi nama tampilan, copy email, prefix redis/cache/horizon, nama cookie session |
| `APP_KEY` | `base64:...` baru | `php artisan key:generate --show` |
| `APP_URL` | `https://api.<domain>` | |
| `FRONTEND_URL` | `https://<domain>` | Sumber semua URL reset password / notifikasi / redirect |
| `SESSION_DOMAIN` | `.<domain>` | |
| `SANCTUM_STATEFUL_DOMAINS` | `<domain>` | Tambah host `*.pages.dev` preview CF kalau mau bisa login dari preview |
| `CORS_ALLOWED_ORIGINS` | `https://<domain>` | Pisah koma; origin website event nyusul belakangan |
| `DB_DATABASE` | `<brand>` | Host/user sama dengan pmone |
| `REDIS_DB` / `REDIS_CACHE_DB` | pasangan kosong berikutnya (pmone 0/1 -> monara 2/3, brand berikut 4/5) | Isolasi keyspace di Redis yang dipakai bareng |
| `REDIS_PREFIX` | `<brand>-database-` | Set EKSPLISIT; jangan gantungkan diri ke slug APP_NAME |
| `CACHE_PREFIX` | `<brand>-cache-` | |
| `HORIZON_PREFIX` | `<brand>_horizon:` | |
| `HORIZON_DEFAULT_MAX_PROCESSES` dkk | `1` / `1` / `1` / `1` buat brand baru yang masih sepi | Naikkan setelah upgrade RAM 8 GB / traffic nyata. pmone tetap 10/3/2/10 |
| `MAIL_MAILER` | `resend` (atau `ses-v2` begitu lolos sandbox) | |
| `MAIL_FROM_ADDRESS` | `noreply@<domain>` | Domain pengirim harus diverifikasi dulu di langkah 5 |
| `RESEND_KEY` / `SES_*` | credential milik brand ini sendiri | Lihat langkah 5; `SES_CONFIGURATION_SET=<brand>-transactional`, `SES_SNS_TOPIC_ARN` dari langkah 5.3 |
| `MEDIA_DISK` | `r2` | |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_ENDPOINT` | dari langkah 3.3 | |
| `AWS_BUCKET` | `<brand>` | |
| `AWS_URL` | `https://cdn.<domain>` | Kebawa di setiap URL media yang tersimpan |
| `AWS_DEFAULT_REGION` | `auto` | |
| `PAYMENT_TRUSTED_REDIRECT_HOSTS` | `<domain>` + host tiap website event klien | Allowlist redirect pembayaran (`config/payment.php`) |
| `BRAND_SUPPORT_EMAIL` | `support@<domain>` | Email support fallback di email reservasi |
| `BRAND_ICS_DOMAIN` | `<domain>` | Domain UID file kalender (.ics) |
| `BACKUP_NOTIFICATION_EMAIL` | email ops | Laporan spatie/backup |
| `ANALYTICS_PROPERTY_ID` | (kosongkan, atau GA4 fallback brand ini) | GA4 per project diurus dari dashboard |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` | dari langkah 6 | Tanpa ini tombol Google tetap muncul tapi gagal; pasang sebelum fitur login Google diumumkan |
| `GITHUB_*` / `FACEBOOK_*` | opsional, pola sama dengan Google | |
| `CACHE_STORE` / `RESPONSE_CACHE_DRIVER` / `QUEUE_CONNECTION` | sama dengan pmone (`redis`) | |

Sisanya biarkan sama persis dengan env pmone. Jalankan `php artisan env:audit`
di site baru buat mastiin gak ada yang kelewat.

## 5. Email (identitas pengirim per brand)

Domain FROM beda per brand, jadi tiap brand butuh identitas terverifikasi
sendiri.

1. **Resend (default sekarang, selama SES masih sandbox)**: tambahkan
   `<domain>` di Resend -> Domains, pasang record DKIM/SPF yang dia kasih ke
   zone Cloudflare, bikin API key -> `RESEND_KEY`.
2. **Amazon SES (kalau sudah dipakai)**: SES -> Verified identities -> bikin
   identity buat `<domain>`, pasang 3 CNAME DKIM (+ TXT SPF/DMARC) di DNS;
   bikin **configuration set** bernama `<brand>-transactional`.
3. **Webhook event SES**: bikin SNS topic (misal `<brand>-ses-events`),
   sambungkan ke configuration set (send + bounce + complaint), lalu
   subscribe via HTTPS ke `https://api.<domain>/api/webhooks/ses`. Taruh ARN
   topic-nya di `SES_SNS_TOPIC_ARN`; endpoint-nya nolak payload yang ARN-nya
   gak cocok. Konfirmasi subscription jalan otomatis dari app.
4. Pasang juga DMARC: TXT `_dmarc.<domain>` isi `v=DMARC1; p=none;`.

## 6. Google OAuth ("Sign in with Google"), plus GitHub/Facebook

Tiap brand pakai OAuth client SENDIRI, soalnya consent screen nampilin nama
brand dan redirect URI-nya per domain.

1. Google Cloud Console -> bikin project (atau satu project per brand) ->
   **APIs & Services -> OAuth consent screen**: External, nama app
   `<Nama Brand>`, email support, authorized domain `<domain>`, lalu publish.
   (Selama masih mode "Testing", cuma user yang di-allowlist yang bisa login.)
2. **Credentials -> Create credentials -> OAuth client ID -> Web
   application**:
   - Authorized JavaScript origins: `https://<domain>` dan
     `https://api.<domain>`
   - Authorized redirect URI (PERSIS): `https://api.<domain>/auth/google/callback`
     (redirect Socialite relatif ke `APP_URL`, lihat `config/services.php`;
     habis callback, controller balikin user ke `FRONTEND_URL`.)
3. Salin client ID/secret ke env Forge (`GOOGLE_CLIENT_ID`,
   `GOOGLE_CLIENT_SECRET`), redeploy atau restart FPM.
4. GitHub (`https://api.<domain>/auth/github/callback`) dan Facebook
   (`https://api.<domain>/auth/facebook/callback`) polanya sama di console
   masing-masing. Opsional.

## 7. Cloudflare Pages (admin frontend)

Satu project Pages per brand, build dari repo yang SAMA.

1. Pages -> Create project -> sambungkan repo. Tiru setting build project
   pmone: framework preset **Nuxt**, root directory `frontend`, build command
   `pnpm run build`, output `frontend/dist`.
2. **Environment variable build**:
   - `BRAND=<brand>`  <- ini yang milih brand-nya
   - `NUXT_PUBLIC_SITE_URL=https://<domain>`
   - `NUXT_PUBLIC_API_URL=https://api.<domain>`
   - `NUXT_SANCTUM_BASE_URL=https://api.<domain>`
   - `NUXT_PM_ONE_API_KEY=<key ApiConsumer dari langkah 8.4>` (isi setelah
     langkah 8, terus trigger rebuild)
   - plus var tooling apa pun yang kebetulan ada di project pmone (misal
     `NODE_OPTIONS=--max-old-space-size=6144`, pin versi pnpm). Tiru saja.
3. Custom domain: `<domain>`. Flag `nodejs_compat` kepasang otomatis
   (nitro `cloudflare.deployConfig` men-generate wrangler.json waktu build).
4. Preview deployment belum bisa login sampai host `*.pages.dev`-nya
   ditambahkan ke `SANCTUM_STATEFUL_DOMAINS` + `CORS_ALLOWED_ORIGINS` di env
   API.

## 8. Bootstrap aplikasi (sekali jalan, SSH ke site baru via Forge)

Jalankan dari `/home/forge/api.<domain>/current`:

```bash
# 1. Role + permission (wajib duluan sebelum bikin user pertama)
php8.4 artisan db:seed --class=RoleAndPermissionSeeder --force
php8.4 artisan permissions:sync

# 2. Suara scanner default (butuh R2 sudah kepasang; opsional tapi enak)
php8.4 artisan db:seed --class=ScanSoundsSeeder --force

# 3. Super admin pertama (dapat role `master`)
php8.4 artisan pmone:create-super-admin
```

JANGAN jalankan `db:seed` polos tanpa `--class`. `DatabaseSeeder` default ikut
nyeed user/project demo punya pmone (`UserSeeder`, `ProjectSeeder`,
`GaPropertySeeder`), dan itu gak boleh ada di database brand baru.

Lanjut dari dashboard, login ke `https://<domain>` sebagai super admin:

4. **ApiConsumer** (`/api-consumers`): bikin satu per consumer. Admin frontend
   butuh satu; tiap website event nanti dapat key-nya sendiri. Isi
   `allowed_origins` dengan origin persis yang bakal manggil public API.
   Salin key-nya ke env CF Pages (langkah 7.2), terus rebuild.
5. **App settings**: upload branding PDF global + logo dan suara scanner di
   App Settings. Dua-duanya tersimpan di database brand ini, jadi otomatis
   per brand.
6. **Project + event pertama**: bikin Project klien; begitu website event-nya
   ada, pasang link "Website" di project (ini yang jadi
   `Event::publicBaseUrl()` buat link e-ticket/checkout).
7. **Payment gateway** (per project):
   `/projects/<username>/settings/payment-gateways` -> tambah Xendit/Midtrans
   pakai credential MILIK KLIEN, Test Connection, aktifkan. Buat ngisi
   dashboard provider, buka `/payment-gateways/guide`; URL webhook/redirect
   di sana otomatis nampilin domain API brand ini. Jangan lupa host website
   event masuk ke `PAYMENT_TRUSTED_REDIRECT_HOSTS`.
8. **GA4** (per project, opsional): sambungkan property-nya lewat setting
   analytics project.

## 9. Website event buat klien brand ini

Website event publik ikut pola monorepo pmone-events
(`~/Frontend/pmone-events/`): folder app baru + Nuxt layer, env nunjuk ke
`https://api.<domain>` pakai key ApiConsumer milik site itu, project CF Pages
sendiri. Origin site-nya didaftarkan di tiga tempat: `allowed_origins`
ApiConsumer, `CORS_ALLOWED_ORIGINS`, dan `PAYMENT_TRUSTED_REDIRECT_HOSTS`.
(Catatan: base layer pmone-events belum pernah diaudit brand-nya sendiri.
Sebelum meluncurkan site klien whitelabel dari sana, cek dulu default yang
masih nyangkut ke pmone.)

## 10. Smoke checklist (jalankan per brand sebelum diumumkan)

- [ ] `php artisan env:audit` hijau di site baru (dan masih hijau di pmone)
- [ ] Login di `https://<domain>` jalan; judul tab + wordmark sidebar sudah nama brand
- [ ] Email reset password: link nunjuk `https://<domain>/...`, pengirim `noreply@<domain>`
- [ ] Copy email magic-link nyebut nama brand (bukan PM One)
- [ ] "Sign in with Google" bolak-balik sampai masuk dashboard
- [ ] Upload media -> URL-nya `https://cdn.<domain>/...` dan kebuka
- [ ] PDF receipt/voucher render branding dari AppSetting brand ini
- [ ] Dashboard Horizon kebuka di `https://api.<domain>/horizon` (khusus master/admin)
- [ ] `https://api.<domain>/api/webhooks/ses` nerima event (kirim email tes, cek dashboard email-delivery)
- [ ] Scheduler jalan dalam semenit terakhir (history job di Forge)
- [ ] Backup: paksa sekali `php artisan backup:run --only-db` -> muncul object di `backups/` bucket brand
- [ ] Satu transaksi checkout tes sampai tuntas (webhook gateway ngubah order jadi Paid)

## 11. Setelah go-live

- Forge Monitor: alert memory di 75%. Tiap brand tambahan makan sekitar
  0.5-1.5 GB tergantung sizing Horizon (lihat `docs/scale-runbook.md`).
- Cek swap di VPS (`free -m`); kalau belum ada, pasang swapfile 2 GB.
- Notifikasi deploy gagal harus nyala di SEMUA site brand.
- Kalau backup/analytics antar brand mulai rebutan IO, geser jadwal beratnya.
  Jadwalnya masih hardcode di kode, jadi butuh follow-up kecil supaya bisa
  diatur per env.
- Upgrade VPS ke 8 GB kalau salah satu kejadian: brand baru mulai ramai,
  klien whitelabel berbayar masuk, atau memory steady lewat 75%.
- Klien yang minta isolasi keras bisa dikasih VPS Forge kecil sendiri. Repo
  sama, runbook sama, biayanya masuk kontrak.

---

Latar keputusan arsitektur: `plans/031-multibrand-brand-layer.md` (Appendix A
adalah versi ringkas runbook ini) dan execution log di `plans/README.md`.
