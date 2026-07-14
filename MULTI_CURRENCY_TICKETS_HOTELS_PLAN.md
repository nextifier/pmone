# Plan: Multi-Currency (IDR / USD) untuk Pembelian Tiket & Reservasi Hotel

Dokumen ini adalah plan implementasi yang sudah disepakati dan self-contained — session yang mengerjakannya tidak punya konteks sebelumnya, jadi SEMUA fakta arsitektur yang dibutuhkan ada di sini. Kerjakan fase secara berurutan. Path backend relatif ke root repo `pmone`; path admin frontend relatif ke `frontend/`; fase terakhir menyentuh monorepo terpisah `~/Frontend/pmone-events/`.

Nomor baris di dokumen ini adalah anchor per 2026-07-14 dan bisa bergeser sedikit — selalu verifikasi dengan membaca file sebelum edit.

---

## Konteks: apa yang SUDAH ada di codebase (jangan dibangun ulang)

Multi-currency untuk **exhibitor orders** sudah diimplementasikan penuh (lihat `MULTI_CURRENCY_ORDERS_PLAN.md` sebagai referensi pola). Bagian yang shared/reusable sudah diekstrak menjadi "currency kernel":

1. **`app/Services/Currency/CurrencyResolver.php`** — service resolusi currency:
   - `resolve(?string $override, ?string $country): string` — generik: override menang (guard `! empty`, string kosong = tanpa override), selain itu country menentukan.
   - `currencyForCountry(?string): string` — Indonesia aliases → `IDR`, kosong/null → `IDR`, selain itu → `USD`.
   - `exchangeRateToIdr(string $currency): float` — `IDR` → `1.0`; currency asing → `ExchangeRate::getLatest($currency)?->getRate('IDR')`; **melempar `RuntimeException('Exchange rate unavailable, please try again later.')`** bila tidak ada rate — controller WAJIB mengubahnya jadi respons 422.
   - `resolveForBrandEvent(BrandEvent)` — khusus exhibitor, jangan dipakai untuk tiket/hotel.

2. **`app/Traits/HasBillingCurrency.php`** — trait model:
   - `formatMoney(float|int|string|null $amount): string` (public) — USD → `'$'.number_format($v, 2)`; selainnya → `'Rp '.number_format($v, 0, ',', '.')`. Dipakai dari Blade email/PDF.
   - `convertToIdr(float $amount): float` (protected) — `round($amount * (float) $this->exchange_rate_to_idr, 2, PHP_ROUND_HALF_UP)`.
   - **By design trait TIDAK me-merge fillable/casts** — setiap model adopter mendeklarasikan `currency`/`exchange_rate_to_idr`/`total_idr` sendiri di `$fillable` + `casts()` (mencegah kolom mass-assignable sebelum migrasinya ada).

3. **`app/Models/ExchangeRate.php`** + job `app/Jobs/FetchExchangeRates.php` — kurs USD-base di-fetch tiap jam, dijadwalkan di `bootstrap/app.php` (~baris 119-121, BUKAN `routes/console.php`), butuh queue worker jalan. Menyimpan 10 baris terakhir per base currency.

4. **Guard promo per-currency SUDAH generik** — `app/Services/Promotion/PromoCodeService.php::validate()` membaca `$entity->getPurchaseContext()['currency'] ?? 'IDR'` dan menolak dengan `PromoCodeValidation::ERROR_CURRENCY_MISMATCH` bila: (a) `promotion_rules.currency` terisi dan beda dengan order, atau (b) rule legacy `currency=null` tapi bersemantik nominal (`fixed_amount`/`tiered_fixed_amount`/`bundle_price`, atau `min_purchase_amount`/`max_discount_amount` terisi) diterapkan ke order non-IDR. **Adopter cukup menambahkan key `'currency'` ke `getPurchaseContext()` — nol perubahan di PromoCodeService.**

5. **Referensi implementasi lengkap: `app/Models/Order.php`** (exhibitor) — kolom `orders.currency` (string(3) default `'IDR'`), `exchange_rate_to_idr` (decimal 18,6 default 1), `total_idr` (decimal 18,2 default 0, ber-index); `persistTotals()` menghitung `total_idr = $this->convertToIdr($total)` di SATU tempat; `getPurchaseContext()` mengembalikan `'currency'`. Migrasi referensi: `database/migrations/2026_07_14_093408_add_currency_columns_to_orders_table.php` (termasuk pola backfill via query builder).

6. **Frontend admin (`frontend/`)**: `app/composables/useFormatters.js` → `formatPrice(amount, currency = "IDR")` sudah currency-aware (USD → Intl en-US 2dp; selainnya → `Rp` id-ID 0dp). Pakai ini di semua tampilan uang; JANGAN buat formatter lokal baru.

7. **Test referensi pola**: `tests/Unit/Currency/CurrencyResolverTest.php`, `tests/Unit/Currency/HasBillingCurrencyTest.php`, `tests/Feature/Orders/MultiCurrencyOrderTest.php`, `tests/Feature/Orders/MultiCurrencyReadSurfacesTest.php`, `tests/Feature/Promotion/MultiCurrencyPromoTest.php`.

---

## Keputusan desain (FINAL — sudah dikonfirmasi user, jangan ditanyakan ulang)

1. **Model penagihan: "priced in USD, charged in IDR".** Akun Xendit hanya settle IDR (Midtrans juga IDR-only). Order/reservasi USD menampilkan & menyimpan nominal USD, tapi gateway SELALU menerima jumlah IDR hasil konversi kurs snapshot yang dikunci saat order dibuat. Tidak ada perubahan payload gateway.
2. **Currency tiket: per-tiket** via kolom `tickets.currency` yang SUDAH ada (saat ini kosmetik, jadikan semantik). Harga `ticket_price_phases.price` DIDENOMINASI dalam currency tiketnya — **tidak ada kolom `price_usd`** untuk tiket. Satu event boleh punya tiket IDR dan tiket USD berdampingan.
3. **Satu order tiket = satu currency.** Cart berisi tiket campur currency → tolak 422.
4. **Currency hotel: per-hotel** via kolom baru `hotels.currency` (default `'IDR'`). SEMUA rate hotel itu (room `base_rate`, `hotel_event_allotments.base_rate_override`, dynamic period rates, harga transfer option, surcharge) didenominasi dalam currency hotel. Reservasi mewarisi currency hotel. Tidak ada dual-rate.
5. **`checkoutAmount(): float` tetap bermakna "jumlah IDR yang dikirim ke gateway".** Order/reservasi USD mengembalikan `(float) $this->total_idr` dari method ini. Dengan begitu `XenditService`, `MidtransService`, dan `PaymentProviderFactory` **tidak diubah sama sekali**.
6. **Currency TIDAK PERNAH diterima dari request payload** — selalu resolved server-side (dari tiket / hotel).
7. **Reporting currency = IDR** — semua agregasi/analytics revenue lintas-currency berjalan di `total_idr`.
8. **Scope**: backend + admin frontend (`/frontend`) + fase akhir monorepo `pmone-events`.

## Prinsip arsitektur

- Transaction currency (`currency` + kolom nominal) vs reporting currency (`total_idr`, snapshot `exchange_rate_to_idr` — dihitung sekali saat tulis, tidak pernah di-refresh; data historis beku terhadap perubahan kurs).
- `total_idr` HANYA dihitung di `persistTotals()` masing-masing model via `convertToIdr()` — jangan hitung di tempat lain.
- Money math `decimal:2` + `round(..., 2, PHP_ROUND_HALF_UP)` mengikuti `PricingService` yang ada. `PricingService` sendiri currency-agnostic — tidak perlu diubah.

---

## BAGIAN A — TIKET

### Fase A1 — Database (via `php artisan make:migration`)

1. **`ticket_orders`**: tambah `currency` string(3) not null default `'IDR'`; `exchange_rate_to_idr` decimal(18,6) not null default 1; `total_idr` decimal(18,2) not null default 0 **dengan index**. Backfill dalam migration yang sama: `DB::table('ticket_orders')->update(['total_idr' => DB::raw('total')])` (currency & rate sudah benar via default). Ikuti persis pola `2026_07_14_093408_add_currency_columns_to_orders_table.php`.
2. Tidak ada migrasi untuk `tickets` (kolom `currency` sudah ada di `2026_06_17_223002_create_tickets_table.php` baris 20, default `'IDR'`).

### Fase A2 — Model TicketOrder (`app/Models/TicketOrder.php`)

- `use HasBillingCurrency;`
- `$fillable` + `currency`, `exchange_rate_to_idr`, `total_idr`; `casts()` + `'exchange_rate_to_idr' => 'decimal:6'`, `'total_idr' => 'decimal:2'`; update PHPDoc `@property`.
- `persistTotals()` (~baris 297-303; saat ini hanya menulis `discount_amount` + `total`): tambah `'total_idr' => $this->convertToIdr((float) ($totals['total_amount'] ?? 0))`.
- `getPurchaseContext()` (~baris 308-319): tambah `'currency' => $this->currency` — ini yang mengaktifkan guard promo.
- `checkoutAmount()` (~baris 254-257; saat ini `return (float) $this->total;`): ubah jadi `return $this->currency === 'IDR' ? (float) $this->total : (float) $this->total_idr;` Tambahkan PHPDoc yang menegaskan semantik "IDR amount sent to the gateway".
- `isFree()` (total <= 0) tidak perlu diubah — bekerja untuk USD juga.
- Update `TicketOrderFactory` (`database/factories/`): default `currency => 'IDR'`, rate 1, `total_idr = total`; tambah state `usd(float $rate = 16000)` (contoh di `OrderFactory::usd()`).

### Fase A3 — Validasi tiket & order creation

**Ticket requests** — `app/Http/Requests/StoreTicketRequest.php` (rule `currency` saat ini `['sometimes','string','size:3']`) dan Update-nya: perketat jadi `['sometimes', 'string', 'in:IDR,USD']`.

**Blokir ganti currency tiket yang sudah punya order**: di controller/request update tiket, bila `currency` berubah DAN tiket sudah direferensikan order manapun → 422 dengan pesan jelas (harga phase akan tereinterpretasi; larang saja).

**`app/Services/Ticket/TicketPurchaseService.php`** (file besar ~1639 baris, baca dulu):

- `createOrder()` (~baris 285-637):
  1. Setelah resolve tiket-tiket di cart: kumpulkan currency unik dari `$ticket->currency ?? 'IDR'`. Bila > 1 → tolak 422 ("Tickets in one order must share the same currency."). Set `$currency` = currency tunggal itu.
  2. `$rate = app(CurrencyResolver::class)->exchangeRateToIdr($currency);` — `RuntimeException` harus bubble ke controller sebagai 422 (cek existing catch di `PublicTicketOrderController@store`; tambahkan catch `\RuntimeException` → 422 bila belum ada, ikuti pola `OrderController::store` exhibitor).
  3. Sertakan `currency` + `exchange_rate_to_idr` di array `TicketOrder::create([...])` (~baris 520-536). `total_idr` TIDAK di-set manual — `recalculateAndPersist` (~baris 623) → `persistTotals` yang menghitungnya.
- `previewCart()` (~baris 120-252): tambah `'currency'` di array hasil; terapkan validasi cart homogen yang sama (mixed → exception/422). Untuk USD, tambahkan juga `'charge_amount_idr'` + `'exchange_rate_to_idr'` (dipakai pmone-events untuk menampilkan "You will be charged Rp X").

### Fase A4 — Webhook & rekonsiliasi (WAJIB, jangan dilewati)

Pembanding jumlah bayar saat ini memakai `$order->total` — untuk order USD itu salah (gateway menagih IDR). Ganti pembandingnya ke `(float) $order->checkoutAmount()`:

- `app/Http/Controllers/Api/Webhook/XenditWebhookController.php` → `ticketPaymentAmountSufficient()` (~baris 325-360; epsilon 1.0, dipanggil dari jalur invoice ~baris 501 dan session ~baris 1060).
- `app/Http/Controllers/Api/Webhook/MidtransWebhookController.php` → `ticketPaymentAmountSufficient()` (~baris 192-225, dipanggil ~baris 271).
- `app/Services/Payment/TicketReconciliationService.php` (~baris 102): `abs((float) $order->total - $txn->amount)` → pakai `checkoutAmount()`.

Epsilon 1.0 tetap — menutup delta pembulatan `(int) round()` di Xendit session vs `total_idr` 2dp.

**Audit refund**: cari call site `refundInvoice`/`refundQrPayment` (`app/Services/Xendit/XenditService.php` ~baris 705/740) yang jumlah refund-nya diturunkan dari `total`/`subtotal` ticket order — untuk order USD wajib berbasis jumlah IDR yang ditagih (`checkoutAmount()` / proporsinya). Audit juga alur mark-as-paid manual (fitur `marked_paid_manually`) — pastikan tidak ada perbandingan nominal yang mengasumsikan IDR.

**Yang TIDAK diubah**: `XenditService`, `MidtransService`, `CheckoutPayable`/`CreatesCheckout` contract, verifikasi signature Midtrans (hash `gross_amount` dari payload sendiri).

### Fase A5 — Read surfaces, PDF, email

- **Resources**: resource ticket-order manapun yang mengekspos uang (cari di `app/Http/Resources/` mis. `TicketOrderResource`/varian publik) → tambah `currency`, `exchange_rate_to_idr`, `total_idr`. `PublicTicketResource` sudah mengekspos `currency` (~baris 46) — verifikasi konsisten.
- **Analytics/aggregasi**: grep `SUM` / `->sum(` atas `ticket_orders.total` (dashboard tiket, attendee analytics — `app/Services/AttendeeAnalyticsService.php` sudah membaca `Ticket.currency` ~baris 574) → pindahkan agregasi revenue lintas-currency ke `total_idr`, label frontend "Revenue (IDR)". Ikuti pola exhibitor (`DashboardController`/`EventController` memakai `SUM(orders.total_idr)`).
- **PDF & email** (semua hardcode Rupiah saat ini):
  - `resources/views/pdf/ticket/invoice.blade.php` — closure `$rupiah` di baris ~4, dipakai ~baris 120/139/144/149 → ganti `$order->formatMoney(...)`; untuk USD tambah baris "Charged as {Rp X} (rate Rp Y / USD)" memakai `total_idr` + `exchange_rate_to_idr`. Cek juga receipt PDF tiket bila ada.
  - `resources/views/emails/tickets/order-confirmation.blade.php` (~baris 23/32/36/41 `Rp{{ number_format(...) }}`) → `$order->formatMoney(...)` + baris charged-in-IDR untuk USD.
- **Ekspor** ticket orders (bila ada `app/Exports/*TicketOrder*`): tambah kolom `Currency`, `Exchange Rate (to IDR)`, `Total (IDR)` di POSISI PALING AKHIR (kolom Excel positional — jangan geser letter map; lihat pola `app/Exports/OrdersExport.php`).

### Fase A6 — Admin frontend (`frontend/` — WAJIB baca `frontend/STYLE_GUIDE.md` dulu)

1. `app/components/ticket/TicketForm.vue` — input currency free-text (~baris 132-143) → ganti `<Select>` opsi `IDR` / `USD` (pola select currency di `app/components/FormPromotionRule.vue`). Bila backend menolak perubahan currency (tiket sudah punya order), tampilkan error 422-nya.
2. `app/components/ticket/PricePhasesPanel.vue` — hapus `formatRupiah` lokal (~baris 343) dan render `Rp{{ ... }}` (~baris 54) → `formatPrice(phase.price, ticket.currency)` dari `useFormatters` (auto-import). Label input harga menyesuaikan currency tiket (addon `Rp`/`$`).
3. Tampilan uang ticket-order di admin manapun (list/detail/dialog mark-as-paid) → `formatPrice(x, order.currency)`; untuk USD tampilkan juga `Total (IDR)` + kurs snapshot (pola: `frontend/app/pages/projects/[username]/events/[eventSlug]/operational/orders/[ulid].vue` baris FX footer).
4. Kartu revenue tiket/attendee analytics → pastikan nilai dari `total_idr` dan label "Revenue (IDR)".

---

## BAGIAN B — HOTEL

### Fase B1 — Database

1. **`hotels`**: tambah `currency` string(3) not null default `'IDR'`. Semantik: seluruh rate hotel didenominasi currency ini.
2. **`reservations`**: tambah `currency` string(3) default `'IDR'`, `exchange_rate_to_idr` decimal(18,6) default 1, `total_idr` decimal(18,2) default 0 + index; backfill `total_idr = total_amount`.

### Fase B2 — Model & service

**`app/Models/Hotel.php`**: `currency` ke fillable + PHPDoc; expose di resource admin & publik. **Guard ganti currency**: bila hotel punya reservasi aktif berstatus future, PATCH yang mengubah `currency` → 409 (ikuti pola `ACTIVE_RESERVATIONS_EXIST` yang sudah ada di fitur hotel-reservation toggle project-level; cari `ACTIVE_RESERVATIONS_EXIST` di codebase).

**`app/Models/Reservation.php`**:
- `use HasBillingCurrency;` + fillable/casts 3 kolom + PHPDoc.
- `persistTotals()` (~baris 485-494): tambah `'total_idr' => $this->convertToIdr((float) ($totals['total_amount'] ?? 0))`.
- `getPurchaseContext()` (~baris 496-513): tambah `'currency' => $this->currency` (guard promo hotel otomatis aktif; rule legacy nominal ber-`currency=null` otomatis tertolak di reservasi USD — perilaku benar).
- `checkoutAmount()` (~baris 459): `return $this->currency === 'IDR' ? (float) $this->total_amount : (float) $this->total_idr;`

**`app/Services/Reservation/ReservationService.php`** — `createReservation()` (~baris 129): set `currency = $hotel->currency ?? 'IDR'` + `exchange_rate_to_idr = app(CurrencyResolver::class)->exchangeRateToIdr($currency)` pada snapshot create (~baris 299-306). `RuntimeException` → 422 di `PublicReservationController@store` & `previewPricing` (tambah catch bila belum ada). `previewPricing` menyertakan `currency` (+ `charge_amount_idr` untuk USD).

**Interplay fitur estimasi harga**: `Project::getHotelEstimatedPriceConfig()` (`app/Models/Project.php` ~baris 751-776) mengonversi IDR→foreign untuk DISPLAY. Untuk hotel non-IDR fitur ini tidak bermakna — `PublicHotelController::attachEstimatedPrice()` (~baris 148-156) harus skip (null) bila `hotel->currency !== 'IDR'`.

### Fase B3 — Read surfaces & tampilan

- Resource publik & admin reservasi/hotel/availability (`PublicHotelResource`, `AvailabilityResource`, `PublicReservationResource`, resource admin reservasi): tambah `currency` (dan `total_idr` + rate di resource reservasi). `availability()` (`PublicHotelController` ~baris 198-275) → `all_in_per_night`/`estimated_total` kini bermakna "dalam currency hotel"; sertakan `currency` di respons.
- Blade PDF/email reservasi (receipt/voucher magic-link — cari di `resources/views/` yang me-render nominal reservasi dengan `Rp` hardcode) → `$reservation->formatMoney(...)` + baris charged-in-IDR untuk USD.
- Ekspor reservasi (bila ada) → 3 kolom currency di akhir.
- **Admin frontend**: form hotel (cari halaman create/edit hotel) + select Currency IDR/USD; `app/pages/projects/[username]/events/[eventSlug]/reservations/[ulid]/index.vue` (formatRupiah lokal ~baris 865, baris uang ~304-369) & `.../reservations/index.vue` (~baris 674-679) → `formatPrice(x, reservation.currency)` + blok FX utk USD; analytics reservasi (`.../reservations/analytics.vue`, `app/components/ReservationAnalyticsSummary.vue`) → agregasi revenue dari `total_idr`, label "Revenue (IDR)".

---

## BAGIAN C — pmone-events (monorepo `~/Frontend/pmone-events/`)

Baca `~/Frontend/pmone-events/CLAUDE.md` dulu. 11 situs event, pnpm workspace + Nuxt Layers. JANGAN build dari terminal (aturan repo); verifikasi via dev server. Catatan: folder app ≠ username PM One — pakai `dataSourceUsername || projectUsername`.

1. **Formatter**: cari util/composable format harga di layer shared — jadikan currency-aware dengan signature sama seperti `formatPrice(amount, currency='IDR')` di pmone.
2. **Tiket**: listing/checkout membaca `currency` dari API tiket (sudah diekspos `PublicTicketResource`) → format per-currency; cart: BLOKIR pencampuran tiket beda currency (pesan error jelas); halaman checkout USD: tampilkan total USD + catatan "You will be charged Rp {charge_amount_idr}" dari respons preview (Fase A3); halaman status order/e-ticket → format per `order.currency`.
3. **Hotel**: booking flow (komponen copy-paste-maintained antara pmone ↔ pmone-events — sengaja 2 adapter, lihat memory project) → format rate/total per `hotel.currency`/`reservation.currency`; fitur estimated-price hanya muncul utk hotel IDR (backend sudah meng-null-kan); langkah bayar reservasi USD tampilkan catatan charged-in-IDR.
4. Setelah selesai: 11 situs perlu rebuild/deploy (di luar scope session — cukup catat).

---

## Fase Tests (Pest, feature-first; aktifkan skill `pest-testing`)

Buat via `php artisan make:test --pest`. Pola setup lengkap ada di `tests/Feature/Orders/MultiCurrencyOrderTest.php` (seed `ExchangeRate` USD, factory states, permission+role boilerplate). PENTING: user testing wajib `email_verified_at` terisi.

- **`tests/Feature/Tickets/MultiCurrencyTicketOrderTest.php`**:
  - Order tiket USD: `currency=USD`, rate snapshot, `total` dalam USD, `total_idr = round(total*rate,2)`, `checkoutAmount() == total_idr`.
  - Cart campur tiket IDR + USD → 422, tidak ada order terbuat.
  - Tabel `exchange_rates` kosong + order USD → 422.
  - Order tiket IDR: perilaku lama utuh (`total_idr == total`, rate 1, `checkoutAmount == total`).
  - Order USD gratis (total 0) → skip gateway seperti biasa.
  - Promo fixed-amount IDR di order tiket USD → ditolak (`CURRENCY_MISMATCH`); promo percentage currency-match → jalan.
  - Ganti currency tiket yang sudah punya order → 422.
  - Webhook sufficient-amount: paid = `total_idr` (±1) → confirm; paid jauh di bawah `total_idr` → tidak confirm (uji langsung method/endpoint webhook dgn payload minimal, lihat test webhook existing di `tests/Feature/Tickets/`).
- **`tests/Feature/Hotels/MultiCurrencyReservationTest.php`** (atau folder test hotel existing):
  - Reservasi di hotel USD: currency diwarisi, snapshot benar, `checkoutAmount == total_idr`; hotel IDR: perilaku lama utuh.
  - `estimated_price` null untuk hotel USD, tetap ada untuk hotel IDR (bila setting aktif).
  - PATCH hotel ganti currency saat ada reservasi future aktif → 409.
- **Read surfaces**: agregasi revenue tiket/reservasi memakai `total_idr` (order USD Rp-equivalent besar mengungguli order IDR kecil).
- **Backfill migration**: order/reservasi lama → `currency=IDR`, rate 1, `total_idr = total`.

Jalankan test TERARAH per file (`php artisan test --compact tests/Feature/Tickets/... `). JANGAN full suite sekali jalan (fatal 120s pre-existing di DocumentService); 2 fail QaMatrix (tanggal basi) = pre-existing, abaikan. `vendor/bin/pint --dirty` setelah semua perubahan PHP.

---

## Aturan repo KRITIS untuk session ini (tidak ada konteks lain — patuhi)

1. **DILARANG KERAS** `migrate:fresh/reset/rollback`, `db:wipe`, `DROP TABLE`, `TRUNCATE`, atau query modifikasi ke database production. Migrate DB lokal dev (127.0.0.1:5432) hanya dengan izin eksplisit user. Testing = `php artisan test` saja (SQLite in-memory).
2. JANGAN jalankan `npm run build` / `nuxi typecheck` / proses berat dari terminal (laptop lambat). Verifikasi frontend via browser `localhost:3000` (Nuxt dev dikelola Claude; jangan ganggu artisan `:8000`). Frontend pmone pakai **pnpm**.
3. UI copy WAJIB English. JANGAN em-dash di copy. Styling: `text-xs sm:text-sm`, selalu `tracking-tight` (`tracking-tighter` utk text-xl+), font weight maks `font-semibold`. Komponen shadcn-vue (`<Select>`, `<Badge>`, `<InputGroup>`+`<InputNumber>`) — jangan native input/select. JANGAN edit `components/ui/**`.
4. Ikuti pattern existing (Form Request array-based rules, Eloquent API Resources, sibling files dulu). PHPDoc > komentar inline. `vendor/bin/pint --dirty` sebelum selesai.
5. Commit hanya bila user meminta eksplisit.

## Catatan & edge cases

- Sebelum deploy: cek data prod `SELECT DISTINCT currency FROM tickets` — pastikan tidak ada nilai selain IDR/USD sebelum memperketat validasi (kolom lama free-text 3 char).
- Kurs snapshot hanya untuk konversi charge + reporting; nilai USD yang ditampilkan/di-invoice tidak pernah berubah setelah order dibuat.
- Xendit session membulatkan amount ke integer (`(int) round`) — delta vs `total_idr` 2dp tertutup epsilon 1.0 di webhook.
- `allowed_payment_channels` tiket tidak terpengaruh (tagihan tetap IDR).
- Deployment prod fitur ini bergantung pada migrasi exhibitor multi-currency + baris `exchange_rates` USD sudah ada di prod (batch sebelumnya).
- Checkout lokal WAJIB payment gateway mode Legacy (Xendit Sessions gagal di localhost — catatan project).
- Setelah selesai: update memory file `multi-currency-orders.md` di direktori memory Claude dengan status implementasi.

## Out of scope

- Settlement USD sungguhan di gateway (butuh akun Xendit multi-currency; Midtrans tidak bisa).
- Currency selain IDR/USD (desain string(3) memungkinkan perluasan).
- Dual-rate per room type / per phase (ditolak — keputusan final per-hotel & per-tiket).
- Revaluasi FX / laporan akuntansi lanjutan.
- Rebuild + deploy 11 situs pmone-events (catat sebagai langkah user).
