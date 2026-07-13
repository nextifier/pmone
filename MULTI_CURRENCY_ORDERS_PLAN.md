# Plan: Multi-Currency (IDR / USD) untuk Exhibitor Orders

Dokumen ini adalah plan implementasi yang sudah disepakati. Kerjakan fase secara berurutan. Semua path backend relatif ke root repo; path frontend relatif ke `frontend/`.

## Konteks

Exhibitor (Brand yang terhubung ke Event via `BrandEvent`) melakukan order item (`EventProduct`) melalui dua jalur:

1. **Exhibitor portal**: `ExhibitorDashboardController::submitOrder` (katalog via `orderFormProducts`, info via `orderFormInfo`) — halaman `frontend/app/pages/brands/[slug]/order-form/[brandEventId].vue`.
2. **Manual order oleh admin**: `OrderController::store` (katalog/context via `createInfo`) — halaman `frontend/app/pages/projects/[username]/events/[eventSlug]/operational/orders/create.vue`.

Keduanya bermuara di `app/Services/Order/OrderSubmissionService::create()`, lalu `app/Services/Pricing/PricingService::recalculateAndPersist()` menghitung discount/penalty/tax/total dan memanggil `Order::persistTotals()`. Saat ini semua harga implisit IDR.

Fitur: exhibitor dengan country **Indonesia** ditagih dalam **IDR**, selain itu dalam **USD**.

## Keputusan desain (FINAL — sudah dikonfirmasi user, jangan ditanyakan ulang)

1. **Dual price manual per produk**: `event_products` mendapat kolom `price_usd` yang di-set manual oleh admin di samping `price` (IDR). **Tidak ada auto-convert** untuk harga yang ditagihkan.
2. **Currency resolution**: otomatis dari country Brand (Indonesia → IDR, selain itu → USD) **plus** field override manual per `BrandEvent` (`currency_override`).
3. **Produk tanpa `price_usd`**: disembunyikan dari katalog exhibitor ber-currency USD; backend tetap memvalidasi dan menolak item tanpa harga USD pada order USD.
4. **Tax**: configurable per event — setting baru `tax_rate_usd` di `events.settings` (JSON), fallback ke `tax_rate` yang ada jika tidak di-set.
5. **Scope**: backend (repo ini) + admin frontend (`/frontend`). Monorepo event websites (`pmone-events`) TIDAK termasuk.
6. **Reporting currency = IDR**: setiap order menyimpan snapshot kurs dan `total_idr`; semua analytics, sort, dan filter lintas-currency berjalan di kolom `total_idr`.

## Prinsip arsitektur

- **Transaction currency vs reporting currency.** `orders.currency` + kolom nominal (`subtotal`, `tax_amount`, `total`, `order_items.unit_price`, dst.) berdenominasi transaction currency. `orders.total_idr` adalah ekuivalen reporting currency (IDR), dihitung **sekali saat tulis** dari snapshot `exchange_rate_to_idr`, dan **tidak pernah di-refresh** — order historis beku terhadap perubahan kurs/country.
- **Currency TIDAK PERNAH diterima dari request payload.** Selalu di-resolve server-side dari BrandEvent.
- **Satu order = satu currency.** Tidak ada pencampuran currency antar item.
- Money math tetap `decimal:2` + `round(..., 2, PHP_ROUND_HALF_UP)` mengikuti konvensi `PricingService` yang ada.

---

## Fase 1 — Database (migrations via `php artisan make:migration`)

1. **`event_products`**: tambah `price_usd` `decimal(15,2)` nullable (setelah `price`).
2. **`orders`**: tambah
   - `currency` `string(3)` not null default `'IDR'`
   - `exchange_rate_to_idr` `decimal(18,6)` not null default `1`
   - `total_idr` `decimal(18,2)` not null default `0`, **beri index**
   - Backfill dalam migration yang sama: `UPDATE orders SET total_idr = total` (currency & rate sudah benar via default). Gunakan query builder, bukan raw string concat.
3. **`brand_events`**: tambah `currency_override` `string(3)` nullable (nilai valid: `IDR`, `USD`).
4. **`promotion_rules`**: tambah `currency` `string(3)` nullable. Semantik: `NULL` = currency-agnostic (hanya sahih untuk rule murni persentase tanpa nominal), selain itu rule hanya berlaku untuk order dengan currency yang sama.

Update `$fillable`, `casts()`, dan PHPDoc `@property` di model terkait (`Order`, `OrderItem` tidak berubah struktur, `EventProduct`, `BrandEvent`, `PromotionRule`). Update factories (`OrderFactory`, `EventProductFactory`, dst.) seperlunya.

## Fase 2 — Currency resolution

Buat `app/Services/Currency/CurrencyResolver.php` (via `php artisan make:class`):

- `resolveForBrandEvent(BrandEvent $brandEvent): string`
  1. Jika `currency_override` terisi → pakai itu.
  2. Ambil country dari `Brand->address['country']` (JSON, free text — lihat pola `Hotel::getCountryAttribute` di `app/Models/Hotel.php:198`).
  3. Normalisasi: `trim` + lowercase, cocokkan terhadap set: `indonesia`, `id`, `idn`, `republik indonesia`, `republic of indonesia` → `IDR`.
  4. Country **kosong/null → `IDR`** (fallback aman, mayoritas exhibitor domestik). Country terisi tapi bukan Indonesia → `USD`.
- `exchangeRateToIdr(string $currency): float` — `1.0` untuk IDR; untuk USD ambil `ExchangeRate::getLatest('USD')->getRate('IDR')` (`app/Models/ExchangeRate.php`). Pakai rate terakhir yang ada meskipun stale; jika tabel kosong sama sekali, lempar exception yang oleh controller diubah jadi 422 dengan pesan jelas ("Exchange rate unavailable, please try again later") — dan pastikan `FetchExchangeRates` job/command terjadwal (cek `routes/console.php`).

Ekspos hasil resolve:
- `BrandEvent` mendapat method tipis `resolveCurrency(): string` yang mendelegasikan ke resolver.
- Resource/endpoint yang menampilkan BrandEvent di admin (form edit brand-event) mengembalikan `currency` (resolved) + `currency_override`.
- Request update BrandEvent (cari Form Request update brand-event yang ada): tambah rule `currency_override => nullable|in:IDR,USD`.

## Fase 3 — Order creation & pricing

**`app/Services/Order/OrderSubmissionService::create()`** (`app/Services/Order/OrderSubmissionService.php:55`):

- Resolve `$currency` sekali di awal via `CurrencyResolver`.
- Tax rate: IDR → `settings['tax_rate'] ?? 11` (tetap); USD → `settings['tax_rate_usd'] ?? settings['tax_rate'] ?? 11`.
- Harga item: IDR → `$product->price`; USD → `$product->price_usd`. Jika ada item USD dengan `price_usd === null` → tolak seluruh order (exception → 422, pesan menyebut nama produknya). Ini guard kedua di belakang penyembunyian katalog.
- Simpan di `Order::create([...])`: `currency`, `exchange_rate_to_idr` (snapshot saat itu).

**`app/Models/Order.php`**:

- `persistTotals()` (`app/Models/Order.php:288`) ikut menghitung dan menyimpan `total_idr = round(total * exchange_rate_to_idr, 2)`. Dengan begitu SEMUA jalur recalculate (submit, adjustment manual, promo, void) otomatis menjaga `total_idr` konsisten — jangan hitung `total_idr` di tempat lain.

**Katalog & context endpoints** (filter + expose currency):

- `ExhibitorDashboardController::orderFormProducts` (`app/Http/Controllers/Api/ExhibitorDashboardController.php:622`): resolve currency; jika USD tambah `whereNotNull('price_usd')`; field `price` pada response diisi harga sesuai currency; tambah `currency` di response (level data root, bukan per item).
- `ExhibitorDashboardController::orderFormInfo` (`:676`): kembalikan `currency` + `tax_rate` yang sudah currency-aware.
- `OrderController::createInfo` (`app/Http/Controllers/Api/OrderController.php:162`): sama — filter produk & sertakan `currency` + tax rate untuk brand_event terpilih.

`SubmitOrderRequest` / `StoreManualOrderRequest`: **tidak** menambah field currency (server-side only).

## Fase 4 — Promo & adjustments

- **`app/Services/Promotion/PromoCodeService::applyByCode`**: sebelum apply, validasi currency rule vs order:
  - `rule->currency !== null && rule->currency !== $order->currency` → ValidationException ("Promo code is not valid for this order's currency").
  - `rule->currency === null` tapi rule punya semantik nominal (`value_type` fixed_amount / tiered_fixed_amount / bundle_price, atau `min_purchase_amount`/`max_discount_amount` terisi) → perlakukan sebagai IDR (legacy) dan tolak untuk order USD.
- **`app/Services/Promotion/PenaltyService`**: verifikasi penalty seeded dari `Event.onsite_penalty_rate` murni persentase (seharusnya ya) → berlaku aman untuk semua currency, tidak perlu perubahan. Tambahkan test yang membuktikannya.
- **`PricingService`** (`app/Services/Pricing/PricingService.php`): tidak perlu perubahan — engine currency-agnostic selama semua input satu denominasi.
- Form/CRUD promotion rule di admin: tambah field `currency` (select IDR/USD, default IDR) di Form Request + resource + UI.
- Manual adjustment (`ManualAdjustmentDialog.vue` + endpoint-nya): nominal dientri dalam currency order — cukup tampilkan label currency order di dialog, tidak perlu perubahan backend.

## Fase 5 — API read surfaces (sort, filter, export, analytics)

- **`OrderController::index`** (`app/Http/Controllers/Api/OrderController.php:109`) dan `::all` (`:40`):
  - Tambahkan **whitelist** field sort (saat ini `orderBy($field)` tanpa validasi — perbaiki sekalian). Map sort `total` → kolom `total_idr` agar sort lintas-currency bermakna.
  - Filter baru: `filter.currency` (`IDR`/`USD`), dan opsional `filter.total_min` / `filter.total_max` (nilai IDR, query ke `total_idr`).
- **Resources**: `OrderIndexResource` + `OrderResource` tambah `currency`, `exchange_rate_to_idr`, `total_idr`.
- **`app/Exports/OrdersExport.php`**: tambah kolom `Currency`, `Exchange Rate (to IDR)`, `Total (IDR)`. Kolom nominal yang ada tetap dalam currency order.
- **Revenue aggregation → pindah ke `total_idr`**:
  - `app/Http/Controllers/Api/DashboardController.php:82` — `SUM(orders.total)` → `SUM(orders.total_idr)`.
  - `app/Http/Controllers/Api/EventController.php:106` dan occurrence kedua (~`:190`-an, method list lain dengan query serupa) — sama.
  - `total_revenue` di `EventResource`/`PublicEventResource` otomatis ikut karena membaca hasil agregasi tersebut; nilainya kini bermakna "IDR equivalent".
- Angka per-currency breakdown (mis. "Rp X + $Y") adalah nice-to-have; kalau mudah, tambahkan di response stats (`GROUP BY currency`), kalau tidak, lewati.

## Fase 6 — Admin frontend (`/frontend`, Nuxt 4)

**WAJIB baca `frontend/STYLE_GUIDE.md` sebelum mengubah komponen.**

1. **Formatter terpusat — `app/composables/useFormatters.js`**: jadikan `formatPrice(amount, currency = "IDR")` currency-aware: IDR → `Rp` + `id-ID`, 0 desimal; USD → `Intl.NumberFormat("en-US", { style: "currency", currency: "USD" })`, 2 desimal. Lalu hapus formatter lokal duplikat dan pakai composable ini di:
   - `app/pages/projects/[username]/events/[eventSlug]/operational/orders/index.vue` (local `formatPrice` ~line 565)
   - `app/pages/projects/[username]/events/[eventSlug]/operational/orders/[ulid].vue` (~line 969)
   - `app/pages/projects/[username]/events/[eventSlug]/operational/products.vue` (~line 251)
   - `app/components/order/OrderSummaryPanel.vue` (local Intl IDR)
   - `app/components/order/OrderProductPicker.vue` (~line 137)
2. **Form produk — `app/components/event/FormEventProduct.vue`**: tambah input "Price (USD)" opsional (addon `$`) di bawah input harga IDR (addon `Rp` yang sudah ada). Backend: `StoreEventProductRequest`/`UpdateEventProductRequest` tambah `price_usd => nullable|numeric|min:0`. List produk di `products.vue`: tampilkan USD sebagai baris/teks sekunder jika terisi. Cek juga `EventProductImportDialog.vue` (import Excel) — tambah kolom `price_usd` opsional jika formatnya memungkinkan dengan mudah.
3. **Order list (`orders/index.vue`)**: kolom/badge `currency`; kolom total diformat per `order.currency`; sort total tetap dikirim sebagai `sort=total` (server memetakan ke `total_idr`); filter currency opsional.
4. **Order detail (`orders/[ulid].vue`)**: semua nominal diformat per `order.currency`; untuk order USD tampilkan baris info kurs snapshot + ekuivalen IDR (`total_idr`).
5. **Admin create order (`orders/create.vue` + `OrderSummaryPanel.vue` + `OrderProductPicker.vue`)**: `createInfo` kini mengembalikan `currency` + `tax_rate` — thread sebagai prop ke kedua komponen; picker menampilkan harga sesuai currency; summary menghitung dengan tax rate dari server (sudah begitu, pastikan nilai USD-nya benar).
6. **Exhibitor portal**: `app/pages/brands/[slug]/order-form/[brandEventId].vue`, `orders/[brandEventId]/index.vue`, `orders/[brandEventId]/[ulid].vue`, dan `app/composables/useOrderCart.js` — pakai `currency` dari `order-form-info` / payload order untuk formatting; logika cart tidak berubah (harga yang diterima sudah dalam currency yang benar).
7. **BrandEvent edit form** (cari form tempat `booth_number`/`booth_type` diedit): tambah select "Order Currency" dengan opsi Auto (default, tampilkan hasil resolve), IDR, USD → `currency_override`.
8. **Stats**: `app/components/event/EventStatsGrid.vue` — kartu Revenue kini berisi IDR-equivalent; ubah label menjadi "Revenue (IDR)" atau sejenis.

## Fase 7 — Tests (Pest, feature-first; aktifkan skill `pest-testing`)

- **CurrencyResolver** (unit): variasi country ("Indonesia", "indonesia", "ID", " Republik Indonesia "), country kosong → IDR, country asing → USD, override menang atas country.
- **Order submission** (feature, kedua jalur exhibitor + manual admin):
  - Exhibitor USD: item pakai `price_usd`, order tersimpan `currency=USD`, `tax_rate` dari `tax_rate_usd`, `exchange_rate_to_idr` snapshot, `total_idr = total × rate`.
  - Exhibitor USD + produk tanpa `price_usd` → 422, order tidak terbuat.
  - Exhibitor IDR: perilaku lama utuh, `total_idr == total`, rate 1.
  - Katalog `orderFormProducts` USD menyembunyikan produk tanpa `price_usd`.
  - Promo fixed-amount IDR di order USD → ditolak; promo percentage ber-currency cocok → jalan; penalty onsite persentase → jalan di order USD.
  - Adjustment manual setelah submit → `total_idr` ikut ter-update (via `persistTotals`).
  - Tabel `exchange_rates` kosong + order USD → 422.
- **Order index**: sort `total` mengurutkan lintas-currency via `total_idr` (order Rp 5jt vs $2.000 — USD di atas dengan rate wajar); `filter.currency` bekerja; field sort di luar whitelist ditolak/di-default-kan.
- **Export**: kolom baru muncul dengan nilai benar.
- **Migration backfill**: order lama mendapat `currency=IDR`, rate 1, `total_idr=total`.

Jalankan `vendor/bin/pint --dirty` setelah perubahan PHP, dan test terarah via `php artisan test --compact --filter=...`.

## Catatan & edge cases

- Kurs snapshot hanya untuk **reporting** — tidak pernah memengaruhi nilai yang ditagihkan.
- Order historis tidak boleh berubah ketika country brand / kurs / `price_usd` berubah (semua nilai sudah di-snapshot di `orders`/`order_items`).
- Email order (`OrderSubmittedMail`, `OrderConfirmationMail`) dan template invoice/receipt: cek hardcoded "Rp" — format per `order->currency`.
- `EventProduct::logOnly` (activity log) tambahkan `price_usd`.
- Jangan tambah dependency baru; jangan buat folder base baru selain `app/Services/Currency/`.
- Database: JANGAN menjalankan query ke database production; gunakan migration + test database saja.

## Out of scope

- Event websites monorepo (`pmone-events`) — order form publik di sana menyusul terpisah.
- Payment gateway untuk order exhibitor (alur saat ini invoice manual — tidak berubah). Catatan: Midtrans hanya support IDR jika kelak dibutuhkan.
- Currency selain IDR/USD (desain kolom `string(3)` sudah memungkinkan perluasan).
- Revaluasi FX (unrealized gain/loss) — kalau finance butuh, itu layer reporting terpisah, bukan perubahan data order.
