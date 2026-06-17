# PM One — Brief Fitur Tickets

> Status: draft (v0.9). Brief lengkap fitur Tickets untuk PM One. Poin yang ditandai `(saran)` adalah rekomendasi/penambahan di luar brief asli.

## 1. Tujuan & Konteks
- Menambahkan fitur **Tickets** di PM One (Laravel 12 backend + Nuxt 4 frontend).
- Menggantikan data ticket statis di repo `pmone-events` (`composables/tickets.js`) dengan data dinamis dari PM One.
- Pola implementasi mengikuti fitur **Hotel Reservation** yang sudah ada: payment gateway aktif, invoice, receipt, promotion rules, promo codes, PDF render di browser, serta entry point di `pmone-events` dan `pmone.id`.

## 2. Data Model & Entitas

### 2.1 Entitas utama
- **Event** — punya banyak **EventDay**; `timezone` (mis. WIB/WITA/WIT) dipakai untuk **semua perbandingan waktu** (phase aktif, `valid_days`, sesi add-on); punya konfigurasi **payment gateway sendiri** (akun Xendit per event).
- **EventDay** — `day_number`, `date`, `label`. Dipakai scanner untuk resolve "hari ini = Day berapa".
- **EventGroup** — relasi antar event in-conjunction; flag **allow cross-scan** (mengatur cross-event redeem **dan** exhibitor scan, lihat 10 & 11).
- **Ticket** (produk) — `kind` (`entry` | `add_on`), `title` (free-text), `tier` (free-text), `poster`, `benefits[]`, `currency`, `purchase_type` (`external` | `first_party`), `external_url`, `more_details`, `valid_days` (relasi EventDay; untuk **entry**), `print_on_redeem` (bool; untuk **add-on**; default false), `stock` (nullable; null = unlimited), `sold_count`, `min_quantity`/`max_quantity`, `settings`, `status`. Harga via **PricePhase**; jadwal add-on via **Session**.
- **PricePhase** (milik Ticket) — `label` (Pre-registration / Pre-sale / Normal / dll), `price` (0 = gratis), `starts_at`, `ends_at`, opsional `quota`. Tiket flat = satu phase (UI bisa default 1 phase agar authoring simpel).
- **Session** (milik Ticket add-on) — `label` (mis. "4 Okt 12:00–12:15"), `starts_at`, `ends_at`, `location`?, `host`?, `capacity`/`stock`. Add-on bisa punya **0, 1, atau banyak** Session; jika >1 buyer memilih saat beli.
- **Order** — `user_id` (pembeli), `event_id` (satu event), `status` (`pending_payment` | `confirmed` | `cancelled` | `expired`), `total`, promo, `payment_ref`. Punya banyak **OrderItem**. Gratis vs berbayar **diturunkan dari `total`** (tidak perlu status terpisah).
- **OrderItem** — `ticket_id`, `quantity`, `unit_price` (snapshot harga phase), `phase_label`, `session_id` (nullable; sesi terpilih untuk add-on). Menghasilkan N Attendee.
- **Attendee** (tiket fisik / pemegang QR) — `order_item_id`, `ticket_id`, `name` (placeholder "Tamu #n" atau asli), `email`?, `phone`?, `qr_token` (signed/opaque; **satu token** untuk e-ticket & label), `claimed_by_user_id`?, `personalized_at`?, dan **state check-in**: `checked_in_at`?, `checked_in_by`?, `checkin_event_id`?, `reprint_count`. `valid_days`/sesi diturunkan dari Ticket/OrderItem.
- **ScanLog** (append-only) `(saran)` — `attendee_id`, `action` (`check_in` | `reprint` | `reissue`), `event_id`/pos, `staff_id`, `scanned_at`, `idempotency_key`. Untuk audit + rekonsiliasi offline. (Menggantikan entitas CheckIn 1:1; state terkini hidup di Attendee.)
- **ExhibitorLead** — `exhibitor_id`, `attendee_id`, `scanned_at`, + snapshot data attendee. Sumber data dashboard exhibitor.
- **User (role Visitor)** — akun per orang. Memuat **profil opsional** (gender, birth_year, country, city, company_name, profession, position) + `profile_completeness`, **opt-in business matching**, dan jawaban **business matching** (FieldResponse). Dibuat lazy (2.4). **Inilah anchor data personal & matching** (lihat 15 & 16).
- **CustomField** `(saran)` — field business matching dinamis dikelola admin (staff dashboard): `event_id`, `label`, `type` (select/multiselect/text/number/range/...), `options`, `required`, `order`, `active`.
- **FieldResponse** `(saran)` — `user_id`, `custom_field_id`, `value`. Disimpan **per User**, bukan per attendee/buyer (lihat 16).

### 2.2 Status Order `(saran)`
`pending_payment` | `confirmed` | `cancelled` | `expired`. Order gratis langsung `confirmed`. Status **check-in terpisah** (state di Attendee, bukan di Order).
- **Validitas tiket:** QR/tiket **valid & bisa di-redeem hanya setelah Order `confirmed`**. Order `pending_payment`/`expired`/`cancelled` → tiketnya tidak berlaku.
- **Tidak ada refund.** Tiket non-refundable; tidak ada flow refund/pembatalan oleh pembeli. `cancelled`/`expired` hanya dari sistem (pembayaran gagal/timeout) atau admin.

### 2.3 Stok & inventory hold
- `stock` per tiket (untuk add-on bersesi, kapasitas ada di **Session**); **kosong/null = unlimited**.
- Saat menunggu pembayaran lakukan inventory hold supaya tidak oversell. Lepas hold bila pembayaran timeout (webhook Xendit/Midtrans, pola hotel reservation).

### 2.4 Group order & personalisasi attendee
Prinsip: pisahkan **aksi beli** dari **aksi isi identitas** (issue now, identify later). Checkout cepat, tapi data asli tetap dijamin masuk sebelum check-in, cetak badge, dan scan exhibitor.

- **Buyer isi data sekali** (email, name, phone) + pilih qty. Checkbox **"Saya juga hadir"**: jika dicentang, Attendee #1 memakai data buyer.
- Sistem menerbitkan **N Attendee** sesuai qty (lintas semua line item), masing-masing `qr_token` + **halaman e-ticket sendiri**.
- **Placeholder nama**: **"Tamu #n"**. `personalized_at` null selama placeholder. `email`/`phone` attendee **opsional**.
- Halaman e-ticket bisa **di-share** (copy link / WhatsApp), dibuka tanpa login (token = akses).
- **Akun (lazy):** buyer selalu dapat 1 akun (User). Attendee dibuatkan/di-link ke User hanya saat ia mengisi email sendiri (set `claimed_by_user_id`). Email sudah ada akun → attach. **Jangan** buat akun email palsu/auto.
- **Hak edit identitas:** sebelum di-claim → buyer edit semua; pemegang link bisa personalisasi. Setelah di-claim → milik penerima + staff. Setelah check-in → **dikunci**, staff-only.
- **Lead exhibitor** = snapshot data Attendee saat di-scan (asli/placeholder).
- Catatan: buyer mengatur **tiket** (nama on-ticket via Manage Attendees); **profil & business matching tetap milik masing-masing User** (privasi), bukan diisi buyer untuk orang lain.

### 2.5 Model hari: EventDay & valid_days
Dua dimensi **independen**: **tier** (free-text) dan **cakupan hari** (`valid_days`). Tiap baris jualan = **produk Ticket flat**. Contoh: FLEI Regular Day 1/2/3 → [1]/[2]/[3]; Three-Day → [1,2,3]; Comic Con General Two-Day → [1,2]; VIP → semua hari. Logika check-in/badge baca dari `tier` + `valid_days`, **bukan parsing judul**. Validasi: `valid_days` hanya boleh merujuk EventDay yang ada.

### 2.6 Pricing phases (Pre-registration / Pre-sale / Normal)
Satu Ticket punya 1+ **PricePhase** (`label`, `price`, `starts_at`, `ends_at`). Contoh Regular FLEI: 1–10 Apr → Pre-registration Rp0; 11–20 Apr → Pre-sale Rp30.000; 21 Apr–selesai → Normal Rp60.000. **Phase aktif** = "now" ∈ [starts_at, ends_at]; di luar semua phase → tiket tidak on sale. Promo berlaku di atas harga phase aktif. **Gratis vs berbayar = hasil phase aktif → diputuskan di level order** (2.7).

### 2.7 Mixed cart & order items
Satu transaksi boleh campur beberapa jenis tiket (entry + add-on), **dalam satu event**. Order punya banyak **OrderItem**. Attendee dibuat per qty.
- **Free vs paid di level order:** `total` = Σ(`unit_price` × qty) − promo. `total == 0` → **Claim** (skip gateway). `total > 0` → **Confirm & Pay** (Invoice + Receipt).
- Tiket `external` **tidak masuk cart**; hanya `first_party`.
- **Scope cart = satu event.** Tiap event jual tiket di **domain sendiri**, jadi satu Order = satu event = satu akun Xendit. **Tidak ada checkout lintas event.**

### 2.8 Tiket Add-on
`kind = add_on`. Contoh: Business Conference (Day 1, FLEI); Workshop (Cafe & Brasserie); Meet & Greet guest A (Comic Con).
- **Sesi:** add-on bisa **tanpa sesi**, **satu sesi**, atau **banyak sesi** (entitas **Session**). Jika >1 sesi, buyer memilih saat beli (mis. Meet & Greet "4 Okt 12:00–12:15"). Sesi terpilih disimpan di `OrderItem.session_id`; **kapasitas per Session**.
- **Pembelian:** barengan entry (satu cart/Order) atau **standalone**. **Tanpa dependency keras ke entry** — add-on di-scan **di dalam** event, dan siapa pun yang sudah masuk pasti sudah punya tiket masuk; jadi tidak perlu verifikasi entry secara sistem.
- **Attendee & QR:** tiap add-on = **Attendee + QR sendiri** (dipersonalisasi seperti entry; "Tamu #n", shareable). My Tickets menampilkan entry + add-on.
- **Redeem:** di **checkpoint add-on**, divalidasi terhadap **sesi/jam**-nya (atau tanpa batas bila tanpa sesi). **Print opsional** via `print_on_redeem` (default cukup scan tanpa print; aktifkan bila ada case yang butuh).
- **E-ticket add-on** dikirim ke pemegangnya (buyer, atau attendee saat ia claim), satu per Attendee. Validasi: waktu **Session** harus dalam rentang tanggal event.

## 3. Tipe Pembelian Tiket
### 3.1 External Ticketing Platform
- `purchase_type = external`. Input `external_url` (contoh: `https://panorama.undangin.com/ticket/26299`).
- Klik beli → **redirect di tab yang sama** ke link luar. **Tidak masuk cart PM One.**
### 3.2 First-party (PM One)
- Harga dari **PricePhase aktif** (bisa 0/gratis tergantung tanggal).
- **Free vs paid di level order** dari `total`: total 0 → Claim/skip gateway; total > 0 → **Payment Gateway milik event** (akun **Xendit per event**) menerbitkan Invoice + Receipt (pola hotel), payment timeout + lepas inventory hold bila gagal bayar.
- **Promotion Rules & Promo Codes** berlaku (pola hotel reservation).

## 4. Entry Points & Purchase Flow (cart)
Entry point: website event di `pmone-events` (contoh `franchise-expo.co.id/ticket`) dan `pmone.id`.
1. **Halaman tiket** (per event): daftar produk Ticket (**entry & add-on**). `external` → redirect ke link luar. `first_party` → kontrol qty (hormati `min/max` & `stock`), harga = phase aktif; add-on bersesi → pilih sesi. Add-on bisa ditambah bersama entry atau dibeli sendiri.
2. Buyer pilih qty untuk satu/beberapa jenis (**mixed cart, satu event**). Shortcut: 1 jenis & `max_quantity == 1` → **Get Ticket**.
3. **Cart / Ticket Summary**: line items (jenis, qty, sesi bila ada, harga phase, subtotal), promo code, total.
4. **Checkout — data buyer (form pendek):**
   - **Email paling atas** → onBlur cek backend; jika terdaftar tawarkan login untuk autofill (**jangan langsung tampilkan PII**, lihat 5).
   - **Name, Phone** (wajib). Field profil opsional **tidak** di sini (lihat 15).
   - **Business Matching Yes/No** (untuk buyer). Jika **Yes** → tampilkan **field dinamis** (Position, Company Type, Interests, Budget Range, dll), tersimpan di **profil User buyer** (lihat 16).
   - Centang **Terms & Conditions** (teks T&C **sudah mencakup** persetujuan berbagi data ke exhibitor saat di-scan).
   - Checkbox **"Saya juga hadir"**.
5. **Resolusi flow dari total:** total == 0 → **Claim Ticket** → submit. total > 0 → **Confirm & Pay** → payment link (Xendit event terkait) → setelah bayar.
6. Redirect ke halaman hasil (domain sama) menampilkan **QR** (semua tiket bila > 1).
7. **E-ticket via email**; tiap Attendee punya halaman + QR sendiri (placeholder "Tamu #n"), bisa di-share & dipersonalisasi (2.4).

Tombol di halaman hasil (bawah QR): **Download E-Ticket**, **Download Receipt** (jika berbayar), **Login**.

## 5. Auto-registrasi Visitor
- Saat beli via PM One, data buyer otomatis mendaftarkan **User** role **Visitor** (lazy, 2.4).
- **Keamanan login:** jangan kirim password plaintext. Gunakan **magic link + link "set password" sekali pakai (expiring)** atau OTP.
- **Email sudah punya akun:** attach tiket ke akun yang ada, jangan duplikat.
- **Email-first + autofill aman** `(saran)`: onBlur cek backend (boolean terdaftar/tidak). Jika terdaftar, **jangan langsung prefill Name/Phone** — tawarkan login dulu; setelah terautentikasi baru autofill. (Mencegah panen PII orang lain lewat ketik email.)
- Tombol di email: **Download E-Ticket**, **Download Receipt**, **Login** (magic link).

## 6. Visitor Dashboard (baru)
- **My Tickets**: **Tiket saya** (entry + add-on yang di-pegang akun ini, lintas event) dan **Order saya / Manage Attendees** (order yang dibeli: edit nama per baris, copy link share, status, import CSV/paste).
- **Profil** opsional + **progress meter** kelengkapan (lihat 15).
- **Business Matching** intake (lihat 16); hasil matching menyusul.

## 7. E-Ticket, Receipt, Invoice & QR
- Semua PDF **tidak disimpan**, **di-render di browser** (pola hotel).
- **Satu token** untuk e-ticket & label badge: exhibitor cukup scan salah satunya. Token signed/opaque di Attendee, **ikut pola token-based frontend QR (qr-code-styling)**; validasi di server.
- Token bersifat **contextless**: aksi saat di-scan ditentukan oleh **role/endpoint yang men-scan** (scanner/staff → redeem check-in; exhibitor → lead capture), bukan oleh tokennya.

## 8. Redeem / Check-in (Staff & Scanner)
**Akses (role):** redeem check-in bisa dilakukan oleh role **scanner, staff, admin, master**. Role **`scanner`** (baru) khusus untuk freelancer/daily worker yang hanya memegang device scan — untuk membatasi akses, dashboard scanner **hanya berisi: scan + cari visitor untuk manual check-in**. Role lebih tinggi (staff/admin/master) bisa scan **tanpa** perlu role scanner tambahan. **Exhibitor** juga bisa scan, tapi untuk **lead capture** (lihat 11), bukan redeem.

**Aturan akses:**
- Entry: valid pada `valid_days`. Add-on: valid pada **sesi/jam**-nya (atau tanpa batas bila tanpa sesi).
- **Cross-day** (entry): warning bila hari ini di luar `valid_days`; setting `allow_cross_day` (per event) bisa override.
- **Cross-event** (entry): tiket Event A bisa di-redeem di gate B/C bila EventGroup **allow cross-scan** (lihat 10).

**Metode:** scan QR kamera HP; hardware barcode scanner; search nama/email/phone + check-in manual.
**Implementasi scan (Android + Chrome):** camera → **BarcodeDetector API native** via **`vue-qrcode-reader`** (fallback ZXing/WASM); hardware barcode scanner = **keyboard-wedge** (ketik nilai + Enter ke input fokus).

**Tampilan saat scan:** entry → Nama, tier + cakupan hari, hari ini, event. Add-on → Nama, nama add-on + sesi/jam + lokasi, event.

**Behavior (check-in sekali per attendee):**
- **Scan pertama** (valid; tiket dari Order `confirmed`) → tampil data → tandai checked-in (DB lock/idempotent) → entry **auto-print badge** (9); add-on print sesuai `print_on_redeem`.
- **Scan ulang** (sudah check-in: badge hilang / datang lagi) → **warning "sudah check-in"**, tanpa auto-print → tombol **Reprint/Re-issue**.
- **Re-entry multi-day** (entry): **tanpa scan**, cukup tunjukkan badge/wristband (cek visual; badge mencetak tier + cakupan hari).
- **Audit** `(saran)`: tiap scan/reprint/re-issue tercatat di **ScanLog** (siapa, kapan, aksi) — sekaligus dipakai rekonsiliasi offline (12).
- **Concurrency** `(saran)`: DB transaction/lock saat menandai checked-in.
- **Jaring pengaman data** `(saran)`: jika attendee masih "Tamu #n", prompt cepat isi nama (+ email/phone) sebelum print.

## 9. Label Printing (Web Bluetooth API)
- Target: **Android + Chrome** (Web Bluetooth didukung). Printer: **Clabel CT221B**, label **50×50mm**.
- **Pakai ulang implementasi print yang sudah teruji** di `https://pmone.id/tools/print-test`.
- Entry: auto-print badge (**QR + nama + tier + cakupan hari**, mis. "VIP · Day 1-3"). Add-on (bila `print_on_redeem`): label berisi QR + nama + nama add-on + sesi. **Reprint/Re-issue** untuk cetak ulang (cacat/hilang).

## 10. In-conjunction Events (cross-event redeem)
- Event A, B, C in-conjunction (mis. Franchise & License Expo, Cafe & Brasserie Expo, More Food Expo).
- Tiket **Event A** bisa & boleh di-scan di **gate redemption Event B & C**. Diatur lewat **EventGroup** + flag **allow cross-scan** (14).
- Cross-scan = soal **akses/redeem**. Pembelian & settlement tetap **per event** (akun Xendit masing-masing).

## 11. Exhibitor Scan & Dashboard (cross-event)
- **Dashboard & role exhibitor sudah ada** (lengkapi profil Brand + order item operasional). Tambahkan:
  - **Menu Scan QR badge visitor** → tangkap **lead** (scan: `vue-qrcode-reader` / BarcodeDetector; Android Chrome).
  - **Halaman Data Visitor (leads)** + **export ke Excel (.xlsx)**.
  - **Laporan analytics** (jumlah lead, per hari, tren).
- **Cross-event exhibitor scan:** karena hall di-explore bersama, **exhibitor Event B & C boleh scan badge visitor pemegang tiket Event A** (dan sebaliknya). Aturan sama: **EventGroup + allow cross-scan**. Lead masuk ke dashboard exhibitor yang men-scan.
- Lead = snapshot data Attendee saat di-scan (asli/placeholder); satu exhibitor men-scan visitor yang sama berkali-kali tetap **satu lead** per (exhibitor, attendee).
- **Isolasi data:** tiap exhibitor **hanya melihat lead & analytics miliknya sendiri** — tidak ada akses ke data exhibitor lain atau analytics event. (Analytics sisi penyelenggara/EO di luar scope brief ini.)
- **Privasi (UU PDP):** persetujuan berbagi data ke exhibitor **termasuk dalam Terms & Conditions** saat beli (bukan checkbox terpisah). Simpan timestamp T&C (= consent). **Teks T&C menyebut eksplisit** poin berbagi data ini agar informed.

## 12. Check-in Offline & Sync Multi-Device `(saran)`
**Prinsip.** Banyak device offline → tidak ada jaminan 100% tanpa double check-in (CAP). Pilih **availability**: scanner tetap jalan saat internet mati, rekonsiliasi di server. Pola: **optimistic di lapangan, reconcile di server**.
1. **Pre-cache manifest** ke **IndexedDB**: token + nama, tier, `valid_days`/sesi, event, **status check-in**, event group. Scanner = **PWA** (service worker). Puluhan ribu attendee = beberapa MB.
2. **Scan offline = validasi lokal** (token + hari/sesi valid), tandai checked-in lokal, tulis ke **ScanLog outbox**. Print via Web Bluetooth tetap jalan. Search + manual check-in jalan offline.
3. **Deteksi duplikat antar device.** Warning "sudah check-in" seakurat sync terakhir. Server jadi wasit: **first-wins by timestamp** per attendee; entri ScanLog berikutnya di-log dan muncul di report.
4. **Sync = ScanLog + idempotency** (UUID dari client). Saat online: push batch (server dedupe by UUID), pull check-in device lain sejak cursor. Sync **oportunis**.
5. **(Opsional) Local hub di venue.** Laptop/Raspberry Pi sync lokal via WiFi/LAN; sumber kebenaran on-site; push ke cloud saat ada internet. Tradeoff: tambah hardware.
6. **Clock skew.** Sinkronkan jam saat online atau terima skew kecil.

**Keputusan praktis.** Expo umum: poin 1–4 cukup. Gerbang ketat: koneksi prioritas atau local hub.

## 13. Settings (konsolidasi)
- Periode & harga via **PricePhase** per tiket.
- `min_quantity`/`max_quantity` per pembelian per tiket.
- `stock` per tiket (null = unlimited); kapasitas per **Session** (add-on bersesi); opsional `quota` per phase.
- `print_on_redeem` per add-on (default off).
- `allow_cross_day` (per event).
- **Allow cross-scan** (per **EventGroup**) — cross-event redeem **dan** exhibitor scan.
- **Payment gateway: per event** (akun Xendit berbeda per event).
- **Timezone event** — dasar perbandingan phase aktif, `valid_days`, dan sesi.
- **Business matching custom fields** dikelola admin (16).

## 14. Pola Implementasi & Referensi `(saran)`
- Ikuti **Hotel Reservation** sebagai reference implementation: model + migration, store Pinia, halaman & komponen Nuxt, service render PDF (browser), handler webhook payment (Xendit/Midtrans). Pastikan resolusi gateway **per event**.
- **Print:** pakai ulang implementasi di `https://pmone.id/tools/print-test` (Clabel CT221B, 50×50mm).
- **QR scan:** `vue-qrcode-reader` (BarcodeDetector native Android Chrome, fallback ZXing); hardware scanner = keyboard-wedge.
- Stack: Laravel 12 + Nuxt 4, PostgreSQL, Cloudflare R2 (poster bila perlu), DigitalOcean via Forge.

## 15. Data Visitor: field wajib, optional profile & progress meter
- **Form pembelian minimal:** Name, Email, Phone (wajib). Tidak ada form panjang di alur beli.
- **Field profil opsional** (di **dashboard**, bukan alur beli, semua di **User**): gender, birth year, country, city, company name, profession, position.
- **Progress meter** kelengkapan profil untuk mendorong pengisian (efek "tanggung, sekalian dilengkapi"). (Mekanik reward seperti undian/Spin the Wheel tidak dipakai dulu — bisa ditambah belakangan.)
- Field profil ini **dibagi pakai** dengan business matching: field yang sama (mis. **Position**) diisi sekali, tersimpan di User (16).

## 16. Business Matching: conditional fields & form builder
- Di alur beli, setelah Name/Email/Phone: **Yes/No** ikut business matching (untuk buyer). **Yes** → field dinamis (Position, Company Type, Interests, Budget Range, dll).
- **Form builder admin:** field business matching **create/edit/delete/manage** oleh admin di **staff dashboard** (**CustomField**: `label`, `type`, `options`, `required`, `order`, `active`), per event.
- **Disimpan di User (bukan buyer-vs-attendee).** Alasan: business matching adalah soal **orang** yang akan hadir & ketemu exhibitor, bukan soal "buyer". Menyimpan per buyer akan rusak untuk pembelian rombongan (mis. HR beli 20 tiket — profil HR tidak relevan untuk 20 staf yang ketemu exhibitor). Menyimpan per **User** lebih sederhana daripada split buyer/attendee + sync, dan benar untuk semua kasus:
  - Buyer mengisi miliknya saat checkout → tersimpan di **User buyer**.
  - Attendee lain mengisi **saat claim/personalisasi atau di dashboard** → tersimpan di **User masing-masing**.
  - Attendee anonim ("Tamu #n") yang belum punya akun: belum punya profil BM — wajar, orang anonim memang tidak bisa di-match.
  - Matching beroperasi atas **User yang opt-in & memegang tiket**.
- **Overlap** dengan profil: field yang sama (mis. **Position**) = satu field di User; diisi di form BM **atau** profil, dua-duanya terisi (tidak dobel).
- **Algoritma matching** (mempertemukan visitor ↔ exhibitor) = fitur tersendiri, menyusul. Bagian ini fokus **intake data**.
