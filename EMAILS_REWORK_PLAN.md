# EMAILS_REWORK_PLAN.md - Rombak /email-delivery menjadi /emails (Resend-only)

## Context

Halaman admin `/email-delivery` awalnya dibangun untuk Amazon SES. SES batal dipakai (production access tak kunjung disetujui, respons AWS lambat), produksi tetap memakai **Resend**. Konsekuensi:

1. Semua kode SES kini dead code dan harus dihapus.
2. Halaman di-rename `/email-delivery` -> `/emails` dan dirombak meniru **resend.com/emails**: list semua email (paginated, sort terbaru, filter range tanggal via `RangeCalendarPicker`, search, filter status), section analytics ringkas di atas dengan tombol ke halaman full analytics, dan halaman detail per email dengan preview body (seperti `resend.com/emails/{id}`).
3. Animasi entrance yang tidak pernah diminta dihapus.
4. Target verifikasi: data `localhost:3000/emails` == data `resend.com/emails` (jumlah, status, metrics per range tanggal, isi preview).

**Keputusan user yang sudah final (jangan tanya ulang):**
- Animasi: hapus **entrance saja** (`t-panel-slide` + trik reveal double-rAF). `NumberFlow`, `TabsIndicator`, `Skeleton` TETAP (pattern standar semua halaman admin).
- Sync Resend: **ya**, command `emails:sync-resend` + scheduler tiap 15 menit (backfill + update status meski webhook belum aktif).
- Arsitektur list: **DB lokal** (`email_messages`) sebagai sumber list/analytics (Resend API tidak punya filter tanggal & paginationnya cursor-only). Resend API dipakai untuk: sync/backfill + fetch body email di halaman detail.

## Referensi Resend API (hasil riset, jangan riset ulang)

- SDK sudah terpasang: `resend/resend-laravel ^1.3` (bundle `resend/resend-php v1.1.1`). API key dibaca dari vendor config `resend.api_key` = `env('RESEND_API_KEY')` (config TIDAK dipublish ke `config/resend.php`, biarkan begitu).
- `Resend::emails()->list(['limit' => 100, 'after' => $id])` -> `GET /emails`. Cursor pagination `after`/`before` (mutually exclusive), `limit` max 100. **Tidak ada filter tanggal/search.** Response: `{ object: "list", has_more: bool, data: [{ id, message_id (RFC2822), to[], from, created_at, subject, bcc, cc, reply_to, last_event, scheduled_at }] }`, urut terbaru dulu.
- `Resend::emails()->get($id)` -> `GET /emails/{id}`. Field tambahan: `html`, `text`, `tags[{name,value}]`.
- Rate limit: **5 request/detik per team**. Beri jeda antar page saat sync (mis. `usleep(250_000)`).
- Nilai `last_event`: `queued, scheduled, sent, delivered, delivery_delayed, bounced, complained, opened, clicked, failed, canceled, suppressed`.
- Retensi Resend terbatas per plan: email lama bisa hilang dari API (list & body). DB lokal adalah sumber sejarah; endpoint content harus graceful saat Resend 404/error.

**Tampilan resend.com yang ditiru (sudah diinspeksi via Chrome):**
- List: kolom `To | Status (badge) | Subject | Sent (relative time)`, search box, filter tanggal, filter status, sort terbaru.
- Detail: header recipient, grid meta `FROM / SUBJECT / TO / ID (copyable)`, section `EMAIL EVENTS` (timeline: Sent, Delivered, ... + timestamp), card tabs `Preview | Plain Text | HTML | Raw | Insights`. Kita implement **Preview, Plain Text, HTML** saja (Raw MIME & Insights tidak tersedia via API, skip).

---

## FASE A - Backend: hapus dead code SES

**Hapus file:**
- `app/Http/Controllers/Api/Webhook/SesNotificationController.php`
- `app/Services/Ses/SesEventRecorder.php`
- `app/Services/Ses/SesAccountService.php` (sudah orphan, zero caller) -> folder `app/Services/Ses/` ikut hilang
- `tests/Feature/Mail/SesNotificationWebhookTest.php`, `SesEventRecorderTest.php`, `SesMailerConfigTest.php`

**Edit surgical (bagian SES saja):**
- `routes/api.php`: hapus route `POST /api/webhooks/ses` + import `SesNotificationController`.
- `app/Enums/EmailEventType.php`: hapus `fromSes()`.
- `app/Listeners/RecordSentMessage.php`: hapus fallback header `X-SES-Message-ID` dan pembacaan `config('mail.mailers.ses-v2.options.ConfigurationSetName')`; hanya `X-Resend-Email-ID`, `mailer` di-set eksplisit `'resend'`, `configuration_set` null.
- `app/Models/EmailSuppression.php`: default param `source` di `suppress()` jadi `'resend'`; bersihkan docblock SES.
- `app/Http/Resources/Email/EmailSuppressionResource.php`: buang fallback diagnostic bentuk SES (`bouncedRecipients[0].diagnosticCode`), sisakan bentuk Resend (`payload.bounce.message`).
- `config/mail.php`: hapus mailer `ses`, `ses-v2`, dan `roundrobin` (roundrobin mereferensikan ses); rapikan komentar supported drivers.
- `config/services.php`: hapus blok `ses` dan `ses_sns` (beserta komentarnya); ubah `resend.key` jadi `env('RESEND_KEY', env('RESEND_API_KEY'))` (unifikasi: .env prod hanya punya `RESEND_API_KEY`).
- `.env.example`: hapus baris `SES_KEY/SES_SECRET/SES_REGION/SES_CONFIGURATION_SET/SES_SNS_TOPIC_ARN`; tambah `# RESEND_WEBHOOK_SECRET=` di bawah `# RESEND_KEY=`.
- `composer remove aws/aws-php-sns-message-validator --no-interaction`. **KEEP `aws/aws-sdk-php` dan `league/flysystem-aws-s3-v3`** (dipakai disk s3/r2/r2-backup, SQS, DynamoDB). GOTCHA: composer memicu ide-helper menulis ulang PHPDoc ~64 model -> jalankan `git checkout -- app/Models/` sesudahnya.
- Migration lama JANGAN diedit (sudah jalan di prod). Tambah 1 migration baru kecil: ubah default kolom `email_messages.mailer` `'ses-v2'` -> `'resend'` dan `email_suppressions.source` `'ses'` -> `'resend'` (pakai `->change()`, sertakan seluruh atribut kolom sebelumnya sesuai aturan Laravel 12).
- JANGAN sentuh `.env` (aturan repo). Pembersihan env SES di local/prod dilakukan user (lihat "Langkah operator").

## FASE B - Backend: rework endpoint /api/emails

**Rename:** controller `app/Http/Controllers/Api/Email/EmailDeliveryController.php` -> `EmailController.php` (class `EmailController`). Route group di `routes/api.php` (skrg line ~795): prefix `email-delivery` -> `emails`, nama route `email-delivery.*` -> `emails.*`, middleware `can:emails.view` tetap. Perbaiki komentar stale "SES quota". Permission keys TIDAK berubah (`emails.view`, `emails.manage_suppressions`) -> **tidak perlu** `permissions:sync`.

Routes final:
```
GET    /api/emails/overview                                  emails.overview
GET    /api/emails/messages                                  emails.messages
GET    /api/emails/messages/{emailMessage:message_id}         emails.messages.show
GET    /api/emails/messages/{emailMessage:message_id}/content emails.messages.content   (BARU)
GET    /api/emails/suppressions                              emails.suppressions
DELETE /api/emails/suppressions/{emailSuppression}           emails.suppressions.destroy (can:emails.manage_suppressions)
```
Binding `{emailMessage:message_id}` (UUID Resend) supaya URL frontend `/emails/{uuid}` mirror resend.com.

**`overview(Request)`**: tambah param `date_from`/`date_to` (format `Y-m-d`, validasi, default 30 hari terakhir). Semua agregat (sent/delivered/bounced/complained/opened/clicked + rates + `daily` trend zero-filled) dihitung dalam range itu. `suppressed_total` tetap global. Struktur response sama, ganti key `last_30_days` jadi `totals` + sertakan `range: {from,to}`.

**`messages(Request)`**: filter existing dipertahankan (`status` comma-separated, `search` pada subject/from_address/message_id + `whereJsonContains` recipients, sort whitelist `sent_at|status|subject` default `-sent_at`). Tambah `date_from`/`date_to` pada `sent_at`. GOTCHA: search recipients WAJIB `whereJsonContains`, `LIKE` di kolom json ditolak Postgres (SQLite test lolos diam-diam).

**`show(EmailMessage)`**: tetap, eager load `events` urut `occurred_at`.

**`content(EmailMessage)` (BARU)**: proxy body dari Resend.
- Buat service `app/Services/Resend/ResendEmailApi.php` (wrapper tipis, supaya mockable di test): `get(string $id): array` dan `list(?string $after = null, int $limit = 100): array`, delegasi ke `Resend::emails()` (facade `Resend\Laravel\Facades\Resend`), konversi response SDK ke array.
- Controller: `Cache::remember("resend:email-content:{$emailMessage->message_id}", now()->addMinutes(10), ...)`; try/catch -> sukses: `{ available: true, html, text, cc, bcc, reply_to, tags, last_event, scheduled_at }`; gagal/expired/mailer non-resend: `{ available: false }` dengan HTTP 200.

**Sync command (BARU)**: `app/Console/Commands/SyncResendEmails.php`, signature `emails:sync-resend {--full}`.
- Loop `ResendEmailApi::list(after: cursorTerakhir, limit: 100)`; per item upsert `email_messages` keyed `message_id = item.id`: `from_address`, `subject`, `recipients = to[]`, `sent_at = created_at`, `mailer = 'resend'`, `last_event_at`.
- Status: map `last_event` -> `EmailEventType` (perhatikan: `fromResend()` existing menerima bentuk `email.delivered`; buat mapper baru mis. `EmailEventType::fromResendLastEvent(string)` untuk nilai polos `delivered|bounced|...`, abaikan `queued/scheduled/canceled/failed` yang tak ada padanannya atau map seadanya). Status hanya boleh MAJU: bandingkan `rank()` sebelum menimpa (pattern `EmailMessage::applyEvent()`), supaya sync tidak menurunkan status hasil webhook.
- Mode incremental (default): berhenti paging saat `created_at` item tertua di page < (max `sent_at` di DB minus 2 hari overlap). `--full` = jalan sampai `has_more = false` (backfill perdana).
- `usleep(250000)` antar page (rate limit). Output ringkasan count created/updated.
- `routes/console.php`: `Schedule::command('emails:sync-resend')->everyFifteenMinutes()->withoutOverlapping();` (ikuti gaya schedule existing di file itu).

**Pint:** `vendor/bin/pint --dirty` setelah semua edit PHP.

## FASE C - Tests backend (Pest, aktifkan skill `pest-testing`)

- `tests/Feature/Mail/EmailDeliveryEndpointsTest.php` -> rename `EmailsEndpointsTest.php`: update semua URL ke `/api/emails/*`, binding show pakai `message_id`, tambah kasus filter `date_from`/`date_to` (overview + messages), tambah kasus endpoint `content` (mock `ResendEmailApi` via `$this->mock(...)`: available true/false), permission gating tetap.
- `tests/Feature/Mail/RecordSentMessageTest.php`: buang kasus header `X-SES-Message-ID`.
- BARU `tests/Feature/Mail/SyncResendEmailsTest.php`: mock `ResendEmailApi::list` multi-page; assert upsert benar, status forward-only (tidak menimpa status rank lebih tinggi), incremental stop, `--full` menghabiskan page.
- Jalankan per-direktori: `php artisan test --compact tests/Feature/Mail` (JANGAN full suite single-process, fatal 120s; JANGAN `--parallel`, rusak di repo ini). User test factory WAJIB `email_verified_at` terisi.
- JANGAN hapus test lain tanpa approval. DILARANG `migrate:fresh/reset/rollback`, `db:wipe`, `TRUNCATE`, `DROP TABLE`.

## FASE D - Frontend (Nuxt, `/frontend`, pnpm BUKAN npm)

**Prasyarat: baca `frontend/STYLE_GUIDE.md` dulu.** UI copy WAJIB English (pengecualian existing: copy dialog penjelasan suppression boleh tetap Indonesia). Styling rules: teks kecil `text-xs sm:text-sm`, selalu `tracking-tight` (text `text-xl`+ pakai `tracking-tighter`), font weight maks `font-semibold`.

**Hapus:** `app/pages/email-delivery/` (index.vue). Komponen `app/components/email/SuppressionRowActions.vue` DIPERTAHANKAN (dipakai ulang).

**Nav + redirect:**
- `app/components/AppSidebarNavMain.vue` (~line 441-448): `{ label: "Emails", path: "/emails", iconName: "hugeicons:mail-01" }`.
- `frontend/nuxt.config.ts` `routeRules` (~line 104): tambah `"/email-delivery": { redirect: "/emails" }`.

**Animasi (keputusan final):** di ketiga halaman baru JANGAN pakai `t-panel-slide` maupun trik `revealed` double-rAF (`onMounted(requestAnimationFrame(...))`). Class `t-panel-slide` di `main.css` JANGAN dihapus (dipakai halaman lain). `NumberFlow`, `TabsIndicator`, `Skeleton`, transitions bawaan komponen ui TETAP.

### D1. `app/pages/emails/index.vue` (list, meniru resend.com/emails)

- `definePageMeta({ middleware: ["sanctum:auth", "permission"], permissions: ["emails.view"], layout: "app" })`, `usePageMeta(null, { title: "Emails" })`, `defineOptions({ name: "emails" })`. Container `space-y-6 pt-4 pb-16`.
- Header: `Icon hugeicons:mail-01` + `<h1 class="page-title">Emails</h1>`.
- **Section Overview** (pattern `AttendeeAnalyticsSummary.vue` MINUS animasi): header "Overview" (icon `hugeicons:chart-line-data-02` + h2 muted) + kanan `<Button variant="outline" size="sm" as-child><NuxtLink to="/emails/analytics">View full analytics</NuxtLink></Button>`; `<GridFill :count="6" min-col-width="210px" rounded="xl">` sel Sent/Delivered/Opened/Clicked/Bounced/Complaints (`Icon` size-5 berwarna + label + `NumberFlow`), skeleton saat pending. Data: `GET /api/emails/overview?date_from&date_to` (`useLazySanctumFetch`, key `emails-overview`, `watch:false` + `watch(dateRange) -> refresh`). Angka MENGIKUTI range tanggal terpilih.
- **State tanggal**: `const dateRange = ref({ start: <30 hari lalu>, end: <hari ini> })` (`$dayjs`). Helper `toYmd()` pattern `app/components/project/PaymentGatewayTransactionsDialog.vue` (line ~215).
- **Tabs segmented** `Emails | Suppressions` + `<TabsIndicator />` di dalam `TabsList` (wajib, kalau tidak indikator tidak muncul).
- **Tab Emails**: `<TableData>` server-side, ikut wiring halaman lama + contoh `app/pages/promo-codes/index.vue`:
  - Fetch `GET /api/emails/messages?page&per_page&sort&search&status&date_from&date_to` (key `emails-messages`, `watch:false`, `watch([...]) -> refresh`).
  - Props: `:client-only="false"`, `model="emails"`, `:show-add-button="false"`, `search-column` sesuai wiring search existing, `initial-sorting` `[{ id: "sent_at", desc: true }]`.
  - Slot `#filters`: `<RangeCalendarPicker v-model="dateRange" size="sm" placeholder="Date range" />` + Popover+Checkbox multi-select Status (reuse markup halaman lama; daftar status dari label `EmailEventType` yang dipakai backend). Filter menulis ke `columnFilters` supaya tombol Clear filters TableData bekerja.
  - Kolom (semua WAJIB `size` eksplisit, gotcha `table-fixed`): `To` (recipient pertama + badge `+N` bila multiple; `h(NuxtLink, { to: \`/emails/${row.original.message_id}\` })`, sel `flex min-w-0 flex-col` + `truncate`, size ~240), `Status` (`Badge` warna per status, mapping reuse dari halaman lama, size ~120), `Subject` (NuxtLink ke detail, truncate, size ~360), `Sent` (relative `$dayjs().fromNow()` + `title` tanggal lengkap, size ~110). Klik row = klik sel link (tidak ada row-click API di TableData).
- **Tab Suppressions**: pindahkan apa adanya dari halaman lama (TableData suppressions + filter reason + `SuppressionRowActions` + dialog konfirmasi remove + dialog explainer "About suppressions"), endpoint jadi `/api/emails/suppressions`.

### D2. `app/pages/emails/[id].vue` (detail, meniru resend.com/emails/{id})

- Param = `message_id` UUID. Layout detail pattern `app/pages/promo-codes/[ulid]/show.vue`: container `mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl`, `<ButtonBack destination="/emails" force-destination />`.
- Fetch `GET /api/emails/messages/{id}` (`useLazySanctumFetch`, key computed). `usePageMeta(null, { title: computed(subject atau recipient) })`.
- Header: `<h1 class="page-title">` = recipient utama + `Badge` status.
- Grid meta (label kecil uppercase muted, pattern caption `<p class="text-muted-foreground text-xs tracking-tight sm:text-sm">`, BUKAN `<Label>`): `From`, `Subject`, `To`, `ID` (message_id + tombol copy-to-clipboard, reuse pattern copy existing di repo). Saat content termuat, tampilkan juga `CC/BCC/Reply-To` bila terisi.
- **Email events**: timeline dari `data.events` (reuse markup timeline dialog detail halaman lama: label `EmailEventType` + icon + `occurred_at` diformat), urut kronologis. Empty state `<Empty>` bila belum ada event (webhook belum aktif).
- **Body card**: Tabs segmented `Preview | Plain Text | HTML` + `TabsIndicator`. Konten lazy: `onMounted` -> `useSanctumClient()` `GET /api/emails/messages/{id}/content` (GOTCHA: jangan `useLazySanctumFetch` di nested content yang resolve client-only, hydration mismatch).
  - Preview: `<iframe sandbox="" :srcdoc="content.html" class="h-[600px] w-full rounded-lg border bg-white" />` (bg putih selalu, HTML email mengasumsikan light; sandbox kosong = tanpa script).
  - Plain Text: `<pre class="whitespace-pre-wrap ...">` `content.text` (fallback "No plain text version").
  - HTML: `<pre class="overflow-x-auto font-mono text-xs ...">` source `content.html`.
  - `available: false` -> `<Empty>` "Content is no longer available from Resend" (retensi).
- Tidak ada tab Raw/Insights (tidak tersedia via API).

### D3. `app/pages/emails/analytics.vue` (full analytics)

- Pindahan konten analytics halaman lama, MINUS animasi entrance: 6 stat card + health `Badge` (Healthy/Watch/At risk) + rates (delivery/bounce/complaint/open/click) + section "Activity" `<ChartArea :data-keys="['sent','delivered','opened']">` **WAJIB dibungkus `<ClientOnly>`** (gotcha hydration Tabs/useId).
- Tambah `RangeCalendarPicker` (default 30 hari) yang mendrive `GET /api/emails/overview?date_from&date_to`.
- `<ButtonBack destination="/emails" force-destination />`, `usePageMeta(null, { title: "Email Analytics" })`. Chart mengikuti aturan monokrom + tekstur (JANGAN radar/radial).

## FASE E - Verifikasi (wajib sebelum selesai)

1. Backend: `php artisan test --compact tests/Feature/Mail` hijau; `vendor/bin/pint --dirty`.
2. Backfill local: `php artisan emails:sync-resend --full` (butuh `RESEND_API_KEY` di `.env` local; kalau kosong minta user mengisi, JANGAN baca/tulis .env sendiri).
3. Browser (dev server Nuxt dikelola Claude via nvm; JANGAN `npm run build`/`nuxi typecheck`):
   - `localhost:3000/emails`: overview + list muncul, pagination, sort default terbaru, search, filter status, RangeCalendarPicker mengubah list DAN angka overview; tab Suppressions berfungsi; klik row -> detail.
   - `localhost:3000/emails/{id}`: meta lengkap, timeline events, ketiga tab body, preview iframe render benar.
   - `/email-delivery` redirect ke `/emails`. Cek light + dark mode.
4. **Parity check dengan resend.com** (buka Chrome, user sudah login):
   - Samakan range tanggal di kedua sisi -> jumlah email, urutan, status badge, subject, waktu kirim harus sama.
   - Buka 1 email yang sama di kedua sisi (contoh `resend.com/emails/b9e580b3-e645-41ee-b6ee-ca1a250186da` vs `localhost:3000/emails/b9e580b3-...`) -> FROM/SUBJECT/TO/ID identik, preview body tampil sama.
   - Bila count beda: cek retensi Resend (email sangat lama tidak dikembalikan API list; DB lokal justru bisa lebih lengkap ke belakang, itu expected dan bukan bug).

## Langkah operator (user, manual, JANGAN dikerjakan Claude)

1. Hapus env SES dari `.env` local + Forge production: `SES_KEY, SES_SECRET, SES_REGION, SES_CONFIGURATION_SET, SES_SNS_TOPIC_ARN`.
2. AWS (opsional, kapan saja): hapus SNS subscription `https://api.pmone.id/api/webhooks/ses` (biar tidak retry ke endpoint 404), topic `pmone-ses-events`, config set, IAM user `pmone-ses-mailer`; DNS DKIM/MAIL-FROM SES di Cloudflare boleh dicabut.
3. Masih pending dari sebelumnya: buat webhook Resend -> `https://api.pmone.id/api/webhooks/resend`, isi `RESEND_WEBHOOK_SECRET`, aktifkan open/click tracking di domain Resend (tanpa ini timeline events kosong; status tetap terisi via sync 15 menit).
4. Deploy prod: `php artisan migrate` (1 migration default kolom). `permissions:sync` TIDAK perlu.

## Catatan konteks untuk eksekutor

- Peta lengkap kode existing: controller `EmailDeliveryController` 5 method (overview/messages/show/suppressions/destroySuppression), model `EmailMessage`/`EmailEvent`/`EmailSuppression`, enum `EmailEventType` (rank forward-only) / `EmailSuppressionReason`, resources di `app/Http/Resources/Email/`, listener `RecordSentMessage` + `BlockSuppressedRecipients` (EventServiceProvider), webhook Resend `ResendWebhookController` + `ResendEventRecorder` (JANGAN diubah, sudah benar).
- Tabel: `email_messages` (message_id unique, recipients json, status + status_rank, sent_at), `email_events` (tanpa FK by design, dedupe unique index), `email_suppressions` (email unique). Semua sudah ada di production.
- Halaman lama `app/pages/email-delivery/index.vue` (~1000 baris) adalah sumber copy-paste terbaik untuk: kolom tabel, badge status, filter popover, dialog suppression, timeline events. Rombak strukturnya, jangan tulis ulang dari nol.
- Pattern rujukan: `promo-codes/index.vue` (TableData server-side + link detail), `promo-codes/[ulid]/show.vue` (detail + ButtonBack), `AttendeeAnalyticsSummary.vue` (overview + tombol full analytics), `PaymentGatewayTransactionsDialog.vue` (RangeCalendarPicker -> date_from/date_to).
- Tidak ada komponen preview email/iframe srcdoc existing di repo; bagian iframe di D2 memang baru.
- Frontend package manager **pnpm**. Tidak ada dependency baru yang dibutuhkan.
