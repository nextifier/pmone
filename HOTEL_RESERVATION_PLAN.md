# Hotel Reservation Feature - Implementation Plan

> **Handoff note untuk Claude Code session berikutnya**: File ini berisi rencana implementasi lengkap fitur Hotel Reservation di PM One. Plan ini sudah dibahas secara detail dengan user di sesi sebelumnya, semua keputusan desain sudah dikonfirmasi. Gunakan sebagai blueprint untuk implementasi.
>
> **Cara memulai**: baca plan ini secara penuh, lalu ikuti Phased Delivery (Phase 1 -> 2 -> 3). Jangan skip pembacaan pattern existing yang direferensikan (section "Critical Existing Files to Reference" di bawah).
>
> **Konteks project**:
> - Repository: `/Users/nextifier/Herd/pmone` (Laravel 12 + Nuxt 4 di `/frontend`)
> - Stack: Laravel 12, PHP 8.5, Nuxt 4, PostgreSQL (prod), SQLite in-memory (tests)
> - Payment: Xendit (baru, perlu install `xendit/xendit-php`)
> - Queue: Laravel Horizon aktif
> - Permissions: Spatie Permission
> - Media: Spatie Media Library
> - Frontend admin: shadcn-vue + Tailwind v4 + Sanctum auth
> - Frontend public pages (untuk fitur ini): di Nuxt frontend yang sama, pakai layout `public.vue` tanpa auth
>
> **CRITICAL RULES dari CLAUDE.md yang wajib dipatuhi**:
> - JANGAN PERNAH jalankan perintah destruktif database (`migrate:fresh`, `migrate:reset`, `migrate:rollback`, `db:wipe`, `DROP TABLE`, `TRUNCATE`) tanpa izin eksplisit user. User pernah kehilangan data.
> - JANGAN sentuh database utama (PostgreSQL). Testing pakai `php artisan test --compact` saja (SQLite in-memory)
> - **SELALU GUNAKAN PATTERN YANG SUDAH ADA**, cek sibling files dulu sebelum nulis kode baru (Import/Export, Form Request, Controller, Resource, dll)
> - JANGAN pakai em-dash `—`, gunakan dash biasa `-` atau koma
> - Styling: `tracking-tight` default, `tracking-tighter` untuk text besar (text-xl+), max `font-semibold` (JANGAN bold/extrabold), text kecil `text-xs sm:text-sm`
> - Status colors: CSS variables `bg-success` / `warning` / `destructive` / `info` / `muted`, BUKAN `bg-green-*` / dll
> - Page meta: `usePageMeta(null, { title: "..." })`, divider `·` (middle dot, BUKAN pipe `|`)
> - Dialog: `DialogResponsive`, BUKAN browser `confirm()`
> - User testing wajib isi `email_verified_at` supaya tidak 403 di middleware `verified`
> - JANGAN jalankan `nuxi typecheck`, `npm run build`, `nuxi build` dari terminal Claude (lambat untuk user)
> - Setelah modifikasi PHP: jalankan `vendor/bin/pint --dirty` untuk formatting
> - Tests wajib dibuat/update setiap perubahan, jalankan `php artisan test --compact --filter=<name>`
> - Laravel Boost: pakai `search-docs` tool untuk Laravel docs, pakai `database-schema` tool untuk cek schema
>
> **User preferences dari MEMORY.md relevan**:
> - Admin dashboard English only, TIDAK perlu i18n
> - Currency IDR format `Rp 1.000.000`
> - `shadcn-vue Switch` pakai `v-model` (bukan `:checked`)
> - Validation pattern: FormRequest, cek sibling file array-based vs string-based rules
> - Hydration mismatch fix: untuk nested route children pakai `useSanctumClient()` + `onMounted()` manual fetch, BUKAN `useLazySanctumFetch`
> - Brand system: Brand (global, no project_id) -> BrandEvent (pivot) -> PromotionPost
> - Jangan bikin DIY pattern, pakai pattern existing (Import/Export Excel, formatting, dll)

---

## Context

Tujuan: Tambah fitur akomodasi (Hotel + Transfer In/Out) untuk visitor, dengan pembayaran via Xendit. **Rilis pertama: public booking page di pmone.id** (di Nuxt frontend yang sama dengan admin, tapi pakai layout public tanpa auth). Integrasi ke iicc.askindo.id ditunda ke fase berikutnya.

Kenapa: Butuh channel booking hotel + transfer yang terintegrasi. Saat ini belum ada. PM One bertindak sebagai **aggregator/middleman**: collect booking + payment dari visitor, staff internal yang manual coordinate dengan hotel partner, staff kirim voucher check-in akhir ke visitor.

Outcome: Visitor bisa book + bayar end-to-end dari pmone.id. Staff bisa lihat data reservasi di dashboard, export Excel, upload voucher dari hotel, kirim voucher ke visitor via email manual trigger. Status booking transparan untuk visitor via magic link (view-only).

Out of scope sekarang: penjualan tiket, bundling ticket+hotel, WA notification, role hotel-manager scoped, rating/review, waiting list, integrasi langsung ke PMS hotel, deploy ke iicc.askindo.id.

## Keputusan Desain

- **PM One = middleman**: collect booking + payment, bukan PMS hotel. Staff manual coordinate dengan hotel partner.
- **Scope hybrid**: event-tied atau standalone (`event_id` nullable)
- **Tabel terpisah dari Order**: `hotels`, `room_types`, `hotel_event_allotments`, `hotel_transfer_options`, `reservations`, `reservation_items`, `reservation_transfers`. Orders/order_items khusus exhibitor booth, JANGAN extend.
- **Allotment per event**: quota yang di-commit hotel partner ke PM One, prevent PM One oversell, auto-release unsold pada `release_at`
- **Transfer sebagai add-on** (bukan standalone product)
- **Payment**: full payment + instant confirm via Xendit webhook, window 24 jam
- **Commission tracking**: `commission_rate` per hotel (untuk reporting revenue split)
- **Multi-room per reservation**
- **Permission pattern existing** (no role hotel-manager baru)
- **Rate**: `base_rate` (RoomType) + surcharge opsional dari `hotel_event_allotments`
- **Status flow**: `pending_payment` -> `paid` -> `voucher_sent` (staff action) -> final states `expired` / `cancelled` / `refunded`
- **Email**:
  - **Email #1 otomatis** (setelah Xendit webhook paid): payment receipt + booking summary + magic link + kontak staff PM One
  - **Email #2 manual** (trigger staff dari dashboard): attach voucher file (PDF/image) yang di-upload dari hotel
- **Reminder H-1**: SKIP (hotel yang remind)
- **Voucher auto-generate PDF + QR**: SKIP. Voucher = file dari hotel partner yang di-upload staff
- **Cancellation**: no self-cancel. Visitor kontak staff manual -> staff trigger cancel + refund dari dashboard
- **Magic link page**: view-only (status, detail, staff contact, tombol download Invoice/Receipt PDF). Tidak ada tombol cancel
- **Guest checkout**: no register, access via magic link
- **Admin manual entry**: YES (form di dashboard, opsi skip payment atau mark paid manual)
- **Cancellation policy global**: H-14 100% / H-7..H-13 50% / <H-7 0% (staff punya override di dialog)
- **Voucher file format**: accept PDF / JPG / PNG (single file per reservation via Spatie Media collection `voucher`)
- **Export Excel**: reservations list bisa di-export (ikuti pattern existing export di PM One)
- **Guest fields**: nama lengkap, email, phone, NIK/passport (nomor saja, tanpa upload file), perusahaan, alamat, kewarganegaraan
- **Currency**: IDR only (display `Rp 1.000.000`)
- **Pricing display di public booking**: dual-tier - harga base rate per malam (teks besar) + harga all-in after tax & service (teks kecil di bawah)
- **T&C acknowledgment**: wajib checkbox di form booking sebelum submit, link ke halaman terms
- **Admin dashboard (pmone.id)**: English only, tidak perlu i18n
- **Bulk photo upload**: reuse pattern existing (`InputFile.vue` + `POST /api/media/bulk-upload` + `HasMediaManager` trait). Hotel gallery + RoomType gallery = `single_file: false` collection. Support reorder via `Media::setNewOrder()`.
- **Invoice & Receipt PM One** (selain invoice/receipt Xendit): generate on-demand (tidak save ke storage), streaming PDF response.
  - **Akses**: download dari dashboard admin + download dari magic link page visitor. TIDAK auto-attach di email.
  - **Branding**: per Event override, fallback ke global PM One branding. Stored di `events.branding` JSON column + global settings.
  - **Nomor format**: Invoice `INV/HTL/YYYYMMDD/XXXX`, Receipt `RCP/HTL/YYYYMMDD/XXXX` (derive dari `reservation_number`).
  - **Tool**: DomPDF (`barryvdh/laravel-dompdf`, cek composer - install kalau belum ada)

## Arsitektur Alur

### Public booking (pmone.id/accommodation)
1. Visitor buka `pmone.id/accommodation` (layout public, no auth)
2. Fetch hotel tersedia via server route proxy di Nuxt frontend (`server/api/accommodation/hotels.get.ts` -> Laravel public endpoint dengan `X-API-Key`)
3. Pilih hotel -> room + tanggal + qty -> optional transfer in/out -> isi guest info
4. Submit -> server route proxy -> Laravel `POST /api/public/reservations`
5. Laravel: validate availability (DB lock), hold allotment, create `Reservation` status `pending_payment`, create Xendit invoice (expiry 24h), generate magic link token (hashed di DB, raw ke email)
6. Response: `{ reservation_number, payment_url, magic_link_url }`
7. Client redirect ke Xendit payment page
8. Visitor bayar -> Xendit redirect ke `pmone.id/accommodation/success?ref=<number>`

### Auto-confirm via webhook
1. Xendit POST ke `/api/webhooks/xendit/invoice`
2. `XenditWebhookController` verify header `x-callback-token` vs `config('xendit.webhook_token')`
3. Event `invoice.paid`: find reservation by `xendit_invoice_id` -> update status `paid`, set `paid_at`, commit allotment
4. Dispatch `SendBookingReceivedJob`: kirim **Email #1** ke visitor (payment receipt + booking summary + magic link + staff contact)
5. Event `invoice.expired`: update status `expired`, release allotment

### Staff internal flow (dashboard pmone.id)
1. Buka `/reservations`, filter (event, hotel, status, date range)
2. Export Excel (filter-aware) kalau perlu bulk process
3. Detail reservation: lihat guest info, items, transfer, payment status
4. Manual: staff kontak hotel partner (WA/email/telepon) untuk book atas nama visitor
5. Hotel kirim balik voucher check-in (PDF/image) ke staff
6. Staff upload voucher file ke reservation detail (Spatie Media collection `voucher`)
7. Staff klik "Kirim Voucher ke Visitor" -> dispatch `SendHotelVoucherJob` -> **Email #2** dengan attach voucher file
8. Status update -> `voucher_sent`, `voucher_sent_at` timestamp

### Magic link view (pmone.id/booking/[token])
1. Visitor klik link email -> `pmone.id/booking/{token}`
2. Halaman public (no auth), fetch `GET /api/public/reservations/magic/{token}` via server route proxy
3. Display: status (pending_payment / paid / voucher_sent / cancelled), detail booking, kontak staff PM One, tombol download Invoice PDF + Receipt PDF
4. Tidak ada tombol cancel (visitor harus kontak staff)

### Cancellation (staff-initiated)
1. Visitor kontak staff (WA/email) untuk cancel
2. Staff buka detail reservation di dashboard -> klik "Cancel & Refund"
3. Dialog: reason (text), refund amount (auto-calc H-14/H-7/<H-7 tapi staff bisa override), konfirmasi
4. Backend: status -> `cancelled`, release allotment, trigger `ProcessXenditRefundJob` kalau refund > 0
5. Setelah refund Xendit success -> status -> `refunded`, `refund_amount`, `xendit_refund_id` tersimpan
6. Dispatch `SendCancellationJob` ke visitor

### Admin manual entry
1. Staff buka `/reservations/create`
2. Form: semua field guest + items + transfers + opsi payment:
   - "Skip payment (complimentary)" -> status langsung `paid`
   - "Mark as paid manually" -> status `paid`, payment_method = `manual_bank_transfer`, catat notes
   - "Generate Xendit invoice" -> normal flow dengan payment URL
3. Source = `admin_manual`, `created_by` = staff user ID

## Data Model

### `hotels`
- ulid, slug (unique), name, description (text)
- address, city, country, latitude, longitude (nullable)
- check_in_time (default 14:00), check_out_time (default 12:00)
- contact_email, contact_phone
- commission_rate (decimal 5,2)
- tax_percentage (default 11.00), service_charge_percentage (default 0)
- is_active (bool)
- Spatie Media: `featured`, `gallery`
- created_by / updated_by / deleted_by, soft delete, LogsActivity

### `room_types`
- ulid, hotel_id, slug (unique per hotel), name, description
- max_pax, bed_type, area_sqm (nullable)
- base_rate (decimal 12,2 IDR)
- breakfast_included, amenities (json array)
- is_active
- Spatie Media: `gallery`
- Audit + soft delete

### `hotel_event_allotments`
- ulid, event_id, hotel_id, room_type_id
- quantity (int total block)
- start_date, end_date
- release_at (datetime nullable)
- surcharge_type (enum `fixed`/`percentage`, nullable), surcharge_amount
- is_active
- Audit + soft delete
- Unique: (event_id, hotel_id, room_type_id, start_date, end_date)

### `hotel_transfer_options`
- ulid, hotel_id, label
- direction (enum `in`/`out`/`both`)
- vehicle_type, max_pax, price (decimal)
- is_active, soft delete

### `reservations`
- ulid, reservation_number (unique, `HTL-YYYYMMDD-XXXX`)
- event_id (nullable), hotel_id
- status (enum: `pending_payment`, `paid`, `voucher_sent`, `expired`, `cancelled`, `refunded`)
- payment_expires_at (datetime, default +24h)
- paid_at, voucher_sent_at, cancelled_at, refunded_at (nullable)
- guest_name, guest_email, guest_phone
- guest_identity_type (enum `nik`/`passport`), guest_identity_number
- guest_nationality, guest_company (nullable), guest_address (text nullable)
- special_request (text nullable)
- subtotal_rooms, subtotal_transfer, surcharge_amount, tax_amount, service_charge_amount, discount_amount, total_amount (decimal 14,2)
- xendit_invoice_id (unique nullable), payment_url (text nullable), payment_method (nullable)
- refund_amount (decimal nullable), xendit_refund_id (nullable), refund_reason (text nullable)
- cancellation_reason (text nullable)
- magic_link_token (unique, hashed)
- source (enum `public_website`/`admin_manual`)
- project_username (nullable), ip_address, user_agent (nullable)
- notes (text nullable, internal staff only)
- Spatie Media collection: `voucher` (single file, pdf/jpg/png/jpeg)
- created_by (nullable), updated_by, deleted_by
- Soft delete, LogsActivity

### `reservation_items`
- ulid, reservation_id, room_type_id, allotment_id (nullable)
- check_in_date, check_out_date, nights
- qty (default 1)
- guest_name (nullable), guest_identity (nullable)
- rate_per_night (snapshot), subtotal (decimal)

### `reservation_transfers`
- ulid, reservation_id, transfer_option_id
- direction (enum `in`/`out`)
- transfer_date, transfer_time (nullable)
- pickup_location, dropoff_location
- flight_number, flight_time (nullable)
- pax_count, luggage_count (nullable), note (nullable)
- price (snapshot)

### Schema tambahan untuk Branding

**`events` table** (migration tambahan):
- `branding` (json nullable) - overrides PM One global branding untuk event ini

Struktur `branding` JSON:
```json
{
  "logo_url": "https://...",
  "company_name": "IICC 2026 by Askindo",
  "address": "Jl. ...",
  "city": "Jakarta",
  "country": "Indonesia",
  "phone": "+62 21 ...",
  "email": "info@iicc-event.com",
  "website": "https://iicc.askindo.id",
  "tax_id": "01.234.567.8-901.000",
  "bank_accounts": [
    { "bank_name": "BCA", "account_number": "1234567890", "account_name": "PT Askindo" }
  ],
  "footer_note": "Terima kasih atas kepercayaan Anda",
  "primary_color": "#003366"
}
```

**Global PM One branding**:
- Simpan di existing settings pattern (cek di `config/` atau ada table `settings`/`app_settings`/package `spatie/laravel-settings`)
- Kalau tidak ada: buat `config/branding.php` dengan default + env-driven, atau tabel `app_settings` sederhana (key-value)
- Field sama dengan JSON per-event
- Editable di admin `Settings > Branding`

**Logo**: pakai URL (bisa host di storage public `/uploads/branding/...`) atau Spatie Media collection di model `Event` (kalau per-event) + model `AppSetting` (kalau global). Pilih yang konsisten dengan pattern existing.

## Backend Implementation Steps

### 1. Migrations & Models
`php artisan make:model {Name} -m -f --seed --no-interaction` untuk: Hotel, RoomType, HotelEventAllotment, HotelTransferOption, Reservation, ReservationItem, ReservationTransfer.

Ikuti pattern `app/Models/Event.php` (ulid, HasSlug, Spatie Media, SoftDeletes, LogsActivity, audit).

**Bulk media upload** (untuk Hotel gallery + RoomType gallery):
- Pakai trait `App\Traits\HasMediaManager` (existing)
- Implement `getMediaCollections()` dengan `'single_file' => false` untuk collection `gallery`
- Allowed mime: `image/jpeg`, `image/png`, `image/webp`; max size 20MB per file
- Conversions per collection: `lqip`, `sm`, `md`, `lg`, `xl` (existing pattern)
- Ordering: Media sudah punya `order_column` (Sortable). Pakai `Media::setNewOrder($ids)` untuk reorder.
- **Upload endpoint**: reuse `POST /api/media/bulk-upload` (sudah ada di `app/Http/Controllers/MediaController.php@bulkUpload`). Kirim `files[]`, `model_type`, `model_id`, `collection`.
- **Reorder endpoint**: contoh di `app/Http/Controllers/Api/BrandEventController.php@reorderPromotionPostMedia`. Tiru untuk Hotel: `POST /api/hotels/{hotel}/media/{collection}/reorder` dengan body `{ media_ids: [...] }`.

### 2. Enums
- `app/Enums/ReservationStatus.php` (PendingPayment, Paid, VoucherSent, Expired, Cancelled, Refunded)
- `app/Enums/ReservationSource.php` (PublicWebsite, AdminManual)
- `app/Enums/TransferDirection.php` (In, Out, Both)
- `app/Enums/IdentityType.php` (Nik, Passport)
- `app/Enums/PaymentMethod.php` (Xendit, ManualBankTransfer, Complimentary)

### 3. Form Requests
Admin:
- `Hotel/StoreHotelRequest`, `UpdateHotelRequest`
- `RoomType/Store...`, `Update...`
- `Allotment/Store...`, `Update...`
- `HotelTransferOption/Store...`, `Update...`
- `Reservation/UpdateReservationStatusRequest`
- `Reservation/StoreManualReservationRequest`
- `Reservation/UploadVoucherRequest` (validasi file: mimes pdf/jpg/png, max size)
- `Reservation/CancelReservationRequest` (refund_amount optional override, reason)

Public:
- `PublicReservation/StorePublicReservationRequest`
- `PublicReservation/CheckAvailabilityRequest`

### 4. API Resources
Admin: `HotelResource`, `HotelIndexResource`, `RoomTypeResource`, `AllotmentResource`, `ReservationResource`, `ReservationIndexResource`.

Public: `PublicHotelResource`, `PublicRoomTypeResource`, `PublicReservationResource`, `AvailabilityResource`.

### 5. Services
- `app/Services/Xendit/XenditService.php`
  - `createInvoice(Reservation): array`
  - `refundInvoice(string $invoiceId, float $amount): string`
  - `verifyWebhookToken(Request $request): bool`
- `app/Services/Reservation/ReservationService.php`
  - `checkAvailability(?int $eventId, int $hotelId, int $roomTypeId, string $checkIn, string $checkOut, int $qty): int`
  - `createReservation(array $data): Reservation` (DB transaction, lock allotment)
  - `markAsPaid(Reservation $r, string $xenditInvoiceId): void` (commit allotment, dispatch email #1)
  - `markVoucherSent(Reservation $r): void` (dispatch email #2)
  - `expireReservation(Reservation $r): void` (release allotment)
  - `cancelReservation(Reservation $r, ?float $refundAmountOverride, string $reason): void`
  - `calculateRefund(Reservation $r): float`
  - `generateReservationNumber(): string`
  - `generateMagicLinkToken(): array` (return `[raw, hashed]`)
- `app/Services/Hotel/AllotmentService.php`
  - `getAvailableHotelsForEvent(int $eventId, string $checkIn, string $checkOut): Collection`
  - `releaseExpiredAllotments(): int`
- `app/Services/Reservation/DocumentService.php`
  - `renderInvoicePdf(Reservation $r): \Illuminate\Http\Response` (stream PDF, no storage)
  - `renderReceiptPdf(Reservation $r): \Illuminate\Http\Response` (stream PDF, no storage)
  - `getBranding(?Reservation $r = null): array` (resolve per-event branding kalau ada, fallback ke global settings)
  - `buildInvoiceNumber(Reservation $r): string` (e.g., `INV/HTL/20260417/0001` dari reservation_number)
  - `buildReceiptNumber(Reservation $r): string`

Tidak perlu `VoucherService` (tidak auto-generate voucher PDF; voucher = file dari hotel yang di-upload).

### 6. Controllers

Admin (`auth:sanctum` + `verified`):
- `HotelController` - apiResource
- `RoomTypeController` - nested
- `AllotmentController` - nested
- `HotelTransferOptionController` - nested
- `ReservationController`
  - `index, show, destroy`
  - `storeManual` (admin manual entry)
  - `updateStatus`
  - `uploadVoucher` (multipart file upload -> Spatie Media)
  - `sendVoucher` (trigger manual email #2)
  - `cancel` (cancel + refund)
  - `export` (Excel, filter-aware - ikuti pattern existing)
  - `invoicePdf` (generate on-demand via DocumentService, stream response)
  - `receiptPdf` (generate on-demand, stream response)
- `EventBrandingController` (atau tambah ke existing `EventController` sebagai sub-action)
  - `show` (get event branding setting)
  - `update` (set event branding json)

Public (prefix `public`, middleware `api.key`):
- `Public/PublicHotelController` - index, show, availability
- `Public/PublicReservationController`
  - `store`
  - `showByMagicLink` (tanpa cancel)
  - `invoicePdfByMagicLink` (stream PDF, invoice PM One)
  - `receiptPdfByMagicLink` (stream PDF, receipt PM One - hanya kalau status >= paid)

Webhook (no auth):
- `Webhook/XenditWebhookController@invoice`

### 7. Jobs (queued via Horizon)
- `Reservation/SendBookingReceivedJob` (email #1 otomatis setelah paid)
- `Reservation/SendHotelVoucherJob` (email #2 manual trigger, attach voucher file dari media library)
- `Reservation/SendCancellationJob`
- `Reservation/ProcessXenditRefundJob`
- `Reservation/ExpireUnpaidReservationsJob` (scheduled every 15 min - safety net, utamanya by Xendit webhook expired)
- `Hotel/ReleaseExpiredAllotmentsJob` (scheduled daily 00:30)

Tidak perlu: `SendReminderH1Job`, `SendBookingConfirmationJob` (merge ke SendBookingReceivedJob).

### 8. Mailables
- `Mail/Reservation/BookingReceivedMail` - Email #1 (receipt + summary + magic link + staff contact)
- `Mail/Reservation/HotelVoucherMail` - Email #2 (attach voucher file dari Spatie Media)
- `Mail/Reservation/CancellationMail`

View: `resources/views/emails/reservation/*.blade.php`

Attach voucher di `HotelVoucherMail@attachments()`:
```php
public function attachments(): array
{
    $media = $this->reservation->getFirstMedia('voucher');
    if (!$media) {
        return [];
    }
    return [Attachment::fromPath($media->getPath())->as($media->file_name)];
}
```

### 8b. PDF Invoice & Receipt PM One (on-demand, custom branding)

**Install**: cek `composer.json`, kalau belum ada: `composer require barryvdh/laravel-dompdf`.

**Templates**:
- `resources/views/pdf/reservation/invoice.blade.php`
  - Header: logo + company_name + address + contact (dari branding)
  - Bill to: guest info (name, company, address)
  - Invoice number (INV/HTL/...), issue date, due date (payment_expires_at)
  - Items table: room, check-in/out, nights, qty, rate, subtotal
  - Transfer table (kalau ada): direction, date, pax, price
  - Total breakdown: subtotal rooms, subtotal transfer, surcharge, tax (11% PPN), service charge, total
  - Bank transfer info (dari branding.bank_accounts)
  - Footer note
- `resources/views/pdf/reservation/receipt.blade.php`
  - Header: sama
  - Receipt number (RCP/HTL/...), paid date, payment method (dari Xendit webhook)
  - Items + total (read-only, mirror invoice)
  - "PAID" stamp / watermark
  - Footer note

**Rendering** (`DocumentService`):
```php
public function renderInvoicePdf(Reservation $r): Response
{
    $branding = $this->getBranding($r);
    $pdf = Pdf::loadView('pdf.reservation.invoice', compact('r', 'branding'));
    return response($pdf->output(), 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => "inline; filename=\"{$this->buildInvoiceNumber($r)}.pdf\"",
    ]);
}
```
- TIDAK save ke storage. TIDAK attach ke media library.
- `Content-Disposition: inline` supaya visitor bisa preview di browser tab, bukan langsung force download (bisa diubah `attachment` kalau preferred).

**Branding resolution** (`DocumentService::getBranding`):
```php
public function getBranding(?Reservation $r = null): array
{
    $global = config('branding.pm_one') ?? app(AppSetting::class)->get('branding') ?? [];
    $eventOverride = $r?->event?->branding ?? [];
    return array_merge($global, $eventOverride);
}
```
- Global source: config/app_settings/spatie-settings (ikuti pattern existing di PM One)
- Per-event override: `events.branding` JSON column (diisi via `EventBrandingController`)

**Config DomPDF** (`config/dompdf.php`): enable `ENABLE_REMOTE` kalau logo di-load dari URL external. Kalau logo disimpan lokal (storage public), pakai `public_path()` helper di template.

### 9. Routes

`routes/api.php`:
```php
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::apiResource('hotels', HotelController::class);
    Route::apiResource('hotels.room-types', RoomTypeController::class);
    Route::apiResource('hotels.allotments', AllotmentController::class);
    Route::apiResource('hotels.transfer-options', HotelTransferOptionController::class);
    Route::get('reservations/export', [ReservationController::class, 'export']);
    Route::apiResource('reservations', ReservationController::class)->only(['index','show','destroy']);
    Route::post('reservations/manual', [ReservationController::class, 'storeManual']);
    Route::patch('reservations/{reservation}/status', [ReservationController::class, 'updateStatus']);
    Route::post('reservations/{reservation}/voucher', [ReservationController::class, 'uploadVoucher']);
    Route::delete('reservations/{reservation}/voucher', [ReservationController::class, 'deleteVoucher']);
    Route::post('reservations/{reservation}/send-voucher', [ReservationController::class, 'sendVoucher']);
    Route::post('reservations/{reservation}/cancel', [ReservationController::class, 'cancel']);
    Route::get('reservations/{reservation}/invoice.pdf', [ReservationController::class, 'invoicePdf']);
    Route::get('reservations/{reservation}/receipt.pdf', [ReservationController::class, 'receiptPdf']);

    Route::get('events/{event}/branding', [EventBrandingController::class, 'show']);
    Route::put('events/{event}/branding', [EventBrandingController::class, 'update']);
});

Route::prefix('public')->middleware('api.key')->group(function () {
    Route::get('hotels', [PublicHotelController::class, 'index']);
    Route::get('hotels/{slug}', [PublicHotelController::class, 'show']);
    Route::post('hotels/availability', [PublicHotelController::class, 'availability']);
    Route::post('reservations', [PublicReservationController::class, 'store']);
    Route::get('reservations/magic/{token}', [PublicReservationController::class, 'showByMagicLink']);
    Route::get('reservations/magic/{token}/invoice.pdf', [PublicReservationController::class, 'invoicePdfByMagicLink']);
    Route::get('reservations/magic/{token}/receipt.pdf', [PublicReservationController::class, 'receiptPdfByMagicLink']);
});

Route::post('webhooks/xendit/invoice', [XenditWebhookController::class, 'invoice']);
```

### 10. Permissions
`config/permissions.php`:
```php
'resources' => [..., 'hotels', 'room_types', 'allotments', 'reservations'],
'custom' => [
    ...,
    'reservations.upload_voucher',
    'reservations.send_voucher',
    'reservations.cancel',
    'reservations.refund',
    'reservations.manual_entry',
    'reservations.export',
    'reservations.view_documents',
    'events.update_branding',
],
```
Run: `php artisan permissions:sync`.

### 11. Xendit Config
`composer require xendit/xendit-php`

`config/xendit.php`:
```php
return [
    'secret_key' => env('XENDIT_SECRET_KEY'),
    'webhook_token' => env('XENDIT_WEBHOOK_TOKEN'),
    'is_production' => env('XENDIT_IS_PRODUCTION', false),
    'invoice_duration' => 86400,
    'currency' => 'IDR',
    'success_redirect_url' => env('XENDIT_SUCCESS_REDIRECT_URL'),
    'failure_redirect_url' => env('XENDIT_FAILURE_REDIRECT_URL'),
];
```
Tambah env vars di `.env.example`.

### 12. Scheduler
`routes/console.php`:
```php
Schedule::job(new ExpireUnpaidReservationsJob)->everyFifteenMinutes();
Schedule::job(new ReleaseExpiredAllotmentsJob)->dailyAt('00:30');
```

### 13. Excel Export
Cek existing export pattern di PM One (dari exploration: ada export Excel dynamic filter-aware). Ikuti pattern tersebut - kemungkinan pakai `maatwebsite/excel` atau package sejenis. Jangan bikin pattern baru.

File: `app/Exports/ReservationsExport.php` (FromQuery + WithHeadings + WithMapping).

Kolom export: reservation_number, event name, hotel name, guest name/email/phone, identity, check-in/out, rooms detail, transfers, total, status, paid_at, voucher_sent_at, created_at.

### 14. Tests (Pest)
- `tests/Feature/Hotel/HotelCrudTest.php`
- `tests/Feature/Hotel/RoomTypeCrudTest.php`
- `tests/Feature/Hotel/AllotmentCrudTest.php`
- `tests/Feature/Reservation/PublicReservationTest.php` (availability, create, concurrency)
- `tests/Feature/Reservation/XenditWebhookTest.php`
- `tests/Feature/Reservation/CancellationRefundTest.php` (calc tiers + Xendit refund)
- `tests/Feature/Reservation/MagicLinkAccessTest.php` (view-only)
- `tests/Feature/Reservation/ReservationExpiryTest.php`
- `tests/Feature/Reservation/AdminManualEntryTest.php`
- `tests/Feature/Reservation/UploadVoucherTest.php` (Spatie Media attach)
- `tests/Feature/Reservation/SendVoucherTest.php` (Mailable + attachment)
- `tests/Feature/Reservation/ExportExcelTest.php`
- `tests/Feature/Reservation/InvoicePdfTest.php` (admin + magic link, PDF content assertions)
- `tests/Feature/Reservation/ReceiptPdfTest.php` (access only setelah status >= paid, branding fallback global->event)
- `tests/Feature/Event/EventBrandingTest.php` (set/get branding JSON)

## Frontend Admin (pmone.id)

Lokasi: `/Users/nextifier/Herd/pmone/frontend`

### Pages
- `app/pages/hotels/index.vue` (list, pakai `TableData`)
- `app/pages/hotels/create.vue`, `app/pages/hotels/[slug]/edit.vue`
- `app/pages/hotels/[slug]/show.vue` (tabs: overview, room types, allotments, transfer options, reservations)
- `app/pages/hotels/[slug]/room-types/create.vue`, `[roomSlug]/edit.vue`
- `app/pages/hotels/[slug]/allotments/create.vue`, `[id]/edit.vue`
- `app/pages/hotels/[slug]/transfer-options/index.vue`
- `app/pages/hotels/trash.vue`
- `app/pages/reservations/index.vue` (list + filter + export button)
- `app/pages/reservations/create.vue` (manual entry)
- `app/pages/reservations/[ulid]/show.vue` (detail + actions)

### Components (`app/components/hotel/`)
- `HotelForm.vue` (termasuk gallery bulk uploader - reuse `InputFile.vue` dengan `allowMultiple=true, maxFiles=20`)
- `RoomTypeForm.vue` (sama, gallery uploader multi-image)
- `AllotmentForm.vue`, `TransferOptionForm.vue`
- `AllotmentCalendar.vue` (visual calendar occupancy per date)
- `ReservationTable.vue`, `ReservationDetail.vue`, `ReservationStatusBadge.vue`
- `ManualReservationForm.vue`
- `VoucherUploader.vue` (upload PDF/image untuk voucher dari hotel, single file, reuse `InputFile.vue` dengan `allowMultiple=false`)
- `SendVoucherDialog.vue` (konfirmasi sebelum dispatch email)
- `CancelReservationDialog.vue` (reason + refund amount pre-filled + override)
- `ReservationExportButton.vue` (trigger export dengan filter active)
- `HotelGalleryManager.vue` (grid view existing media + drag-to-reorder pakai `@vueuse/integrations/useSortable`, delete individual). Contoh pattern: cari `useSortable` di `frontend/app/components/` - sudah dipakai di aplikasi.
- `ReservationDocumentsPanel.vue` (di reservation show.vue): tombol "Download Invoice (PDF)" + "Download Receipt (PDF)" (receipt disabled kalau status `pending_payment`). Hit endpoint admin invoice.pdf / receipt.pdf, browser render/download.
- `EventBrandingForm.vue` (di Events module): form edit branding fields (logo upload, company info, bank accounts repeater, footer note, primary color picker). Save via `PUT /api/events/{event}/branding`.
- Admin Settings page (global branding): `app/pages/settings/branding.vue` - form sama dengan EventBrandingForm tapi target global settings.

### Bulk Image Upload Flow (Hotel & RoomType)
1. User klik area upload di form
2. `InputFile.vue` upload file ke `/api/tmp-upload` (temp storage) dengan FilePond, emit array temp folder IDs
3. Saat form submit: kirim `gallery_files: ["tmp-abc", "tmp-def"]` bersama data lain
4. Controller backend: untuk setiap temp ID, attach ke Media Library via existing helper
5. Reorder: user drag di grid -> PATCH media_ids order array -> `Media::setNewOrder()`
6. Delete individual: DELETE `/api/media/{id}` (pakai existing endpoint, permission-protected)

### Detail page actions (reservation show.vue)
- Status badge
- Guest info, items, transfers, payment
- Media library section: Upload Voucher button / preview / delete
- Action buttons per status:
  - `pending_payment` -> (waiting)
  - `paid` -> "Upload Voucher" + "Send Voucher to Visitor" (disabled kalau voucher belum upload) + "Cancel & Refund"
  - `voucher_sent` -> "Re-send Voucher" + "Cancel & Refund"
  - `cancelled` / `refunded` -> read only
- Timeline log (dari LogsActivity)

### Navigation
Update `app/components/AppSidebarNavMain.vue`:
```js
if (hasPermission('hotels.read'))
  items.push({ label: 'Hotels', path: '/hotels', iconName: 'hugeicons:building-01' });
if (hasPermission('reservations.read'))
  items.push({ label: 'Reservations', path: '/reservations', iconName: 'hugeicons:calendar-02' });
```

### Styling rules (dari MEMORY.md)
- `tracking-tight` default, `tracking-tighter` untuk text besar
- Max `font-semibold`
- Text kecil: `text-xs sm:text-sm`
- Status colors: `bg-success`/`warning`/`destructive`/`info`/`muted` (CSS var)
- Page meta: `usePageMeta(null, { title: "..." })` dengan divider `·`
- Dialog: pakai `DialogResponsive`, BUKAN browser `confirm()`

## Frontend Public Pages (pmone.id)

Lokasi: `/Users/nextifier/Herd/pmone/frontend` (satu Nuxt dengan admin, beda layout)

### Setup Layout Public
- Buat `app/layouts/public.vue` - no admin sidebar, no auth middleware. Header: logo PM One + link `/terms`, `/privacy`. Footer: company info.
- Pages accommodation + booking pakai `definePageMeta({ layout: 'public' })`
- **Auth bypass**: pastikan sanctum middleware skip untuk route ini. Pakai `definePageMeta({ auth: false })` atau middleware setting sesuai existing pattern pmone (cek `frontend/app/middleware/` atau `nuxt.config.ts`).

### Pages baru
- `app/pages/accommodation/index.vue` (landing + listing hotels, query param `?event=<slug|ulid>` opsional)
- `app/pages/accommodation/[hotelSlug].vue` (hotel detail + booking wizard, atau handle dalam index dengan state)
- `app/pages/accommodation/success.vue` (landing setelah Xendit redirect)
- `app/pages/booking/[token].vue` (magic link view)
- `app/pages/terms.vue`, `app/pages/privacy.vue` (buat kalau belum ada)

### Components baru (`app/components/accommodation/`)
- `HotelCard.vue` (image, name, location, starting rate dual-tier)
- `HotelDetailPanel.vue` (gallery carousel, amenities, policies)
- `DateRangeSelector.vue` (reuse shadcn calendar existing)
- `RoomTypeSelector.vue` (list room + qty, price dual-tier)
- `TransferSelector.vue`
- `GuestInfoForm.vue` (field wajib, NIK/paspor nomor saja - text input)
- `BookingSummary.vue` (sidebar pricing: subtotal rooms, transfer, tax 11%, service, total)
- `BookingStepsIndicator.vue` (wizard progress)
- `PriceDisplay.vue` (reusable: harga base besar + all-in kecil)
- `TermsCheckbox.vue` (wajib centang, link ke `/terms`)
- `MagicLinkView.vue` (status, detail, staff contact - tombol download Invoice PDF + Receipt PDF. Receipt disabled kalau status `pending_payment`. Hit Laravel public endpoint langsung.)

UI flow: wizard 5 step (hotel -> room -> transfer -> guest -> review).

**Dual-tier pricing display** (`PriceDisplay.vue`):
```
Rp 1.350.000 / malam        <- big (base_rate, text-lg/xl, font-semibold, tracking-tight)
Rp 1.498.500 sudah termasuk  <- small (after tax+service, text-xs sm:text-sm, text-muted-foreground)
pajak & biaya layanan
```

### API access pattern (pmone frontend ke Laravel)
Karena satu project Nuxt-Laravel:
- **Option A (recommended)**: public endpoints tetap pakai middleware `api.key`. Buat ApiConsumer `pmone-frontend`, simpan key di `NUXT_PM_ONE_API_KEY` (private runtime config). Akses dari Nuxt server route proxy (misal `server/api/accommodation/*.ts`) supaya key tidak leak ke client bundle. Konsisten dengan pola event websites nanti.
- Option B: public endpoints tanpa middleware untuk internal pmone frontend, tapi harus hardening (CSRF/rate limit/honeypot). Kurang konsisten.

Go dengan Option A. Buat server routes di `frontend/server/api/accommodation/` yang proxy ke Laravel:
- `server/api/accommodation/hotels.get.ts`
- `server/api/accommodation/availability.post.ts`
- `server/api/accommodation/book.post.ts`
- `server/api/accommodation/booking/[token].get.ts`
- `server/api/accommodation/booking/[token]/invoice.pdf.get.ts` (stream)
- `server/api/accommodation/booking/[token]/receipt.pdf.get.ts` (stream)

### Config pmone frontend
`nuxt.config.ts`:
```ts
runtimeConfig: {
  pmOneApiKey: process.env.NUXT_PM_ONE_API_KEY, // private
  pmOneApiUrl: process.env.NUXT_PM_ONE_API_URL || 'http://pmone.test/api',
  public: {},
}
```

Pastikan `NUXT_PM_ONE_API_KEY` + `NUXT_PM_ONE_API_URL` di `.env`.

### Navigation
Layout public header minimal. Admin sidebar TIDAK perlu link ke public accommodation.

## Verification

### Tests
```bash
php artisan test --compact --filter=Hotel
php artisan test --compact --filter=Reservation
```
Target: semua test di section 14 pass.

### Manual E2E (development)
1. Seed: event IICC, 2 hotel partner, 3 room type, allotment, API consumer `pmone-frontend`
2. Install: `composer require xendit/xendit-php barryvdh/laravel-dompdf`
3. Start Laravel + Horizon (`composer run dev`) - frontend Nuxt pmone harusnya sudah jalan via same process
4. Tunnel: `herd share` (untuk Xendit webhook)
5. Xendit Dashboard (test mode): register webhook URL tunnel + copy verification token
6. Isi `.env` Laravel: `XENDIT_SECRET_KEY`, `XENDIT_WEBHOOK_TOKEN`, `XENDIT_IS_PRODUCTION=false`
7. Isi `.env` pmone frontend: `NUXT_PM_ONE_API_KEY` (dari seeded ApiConsumer), `NUXT_PM_ONE_API_URL` (ke Laravel base URL)
8. Browser: `pmone.test/accommodation` atau `pmone.test/accommodation?event=iicc`
9. Full flow: wizard -> submit -> redirect Xendit -> pay test VA
10. Verify: webhook hit, status `paid`, **Email #1** terkirim
11. Buka magic link `pmone.test/booking/{token}`, verify view only + tombol download PDF
12. Dashboard admin (`/reservations/{ulid}`): upload voucher PDF dummy, klik "Send Voucher"
13. Verify: **Email #2** terkirim dengan attachment, status `voucher_sent`
14. Dashboard cancel: verify Xendit refund + status `refunded`
15. Admin manual entry: skip payment -> verify status langsung `paid`
16. Export Excel: verify file download + kolom sesuai + filter respected
17. **Invoice PDF (admin)**: klik "Download Invoice" -> verify PDF valid, branding global
18. **Invoice PDF (magic link)**: download dari magic link page -> verify PDF valid
19. **Receipt PDF**: setelah status `paid`, verify receipt PDF valid, "PAID" watermark
20. **Event branding override**: set branding IICC event -> regenerate invoice -> verify branding IICC muncul

### Browser smoke test (Claude in Chrome)
- `pmone.id/hotels` - table render, filter & pagination
- `pmone.id/reservations` - status filter, detail view, upload voucher UI
- `pmone.id/accommodation` (public) - wizard render, date picker, T&C checkbox, submit
- `pmone.id/booking/{token}` - magic link view, download PDF buttons

## Xendit Setup Checklist (user action)
- [ ] Login `dashboard.xendit.co`
- [ ] Toggle **Test Mode**
- [ ] Settings > Developers > API Keys > Generate Secret Key (Money-In Invoices read + write) -> copy, kirim ke dev
- [ ] Settings > Developers > Webhooks > Invoices: set Paid + Expired URL ke `{tunnel}/api/webhooks/xendit/invoice`, copy Verification Token
- [ ] Simpan test credentials di password manager
- [ ] Saat go-live: ulangi di Live Mode

## Phased Delivery (rekomendasi)

### Phase 1 (minggu 1-2): Backend + Admin CRUD
- Migrations, models, enums, form requests, resources
- Admin controllers (CRUD hotels, room_types, allotments, transfer_options)
- Permissions + navigation
- Admin UI: Hotels, Room Types, Allotments
- Tests feature CRUD

### Phase 2 (minggu 2-3): Public Booking + Xendit
- Xendit integration + webhook
- Public controllers + ReservationService
- Magic link flow + Email #1 (BookingReceived)
- **pmone frontend public pages**: layout public, accommodation wizard, booking magic link page, server route proxy
- Tests webhook + concurrency + availability

### Phase 3 (minggu 3-4): Staff Workflow + Polish
- Upload voucher UI + SendVoucher flow (Email #2)
- Cancellation + Xendit refund
- Admin manual entry
- Export Excel
- Invoice + Receipt PDF (DomPDF templates, DocumentService, admin + magic link download)
- Event branding form + global branding settings page
- Scheduled jobs (expire, release allotment)
- Final E2E smoke test
- Dokumentasi operator staff

## Critical Existing Files to Reference

### Backend (Laravel)
- `app/Models/Event.php` - ulid, slug, Spatie Media, SoftDeletes, LogsActivity pattern
- `app/Models/Order.php` - status tracking (reference only, JANGAN extend)
- `app/Http/Middleware/ValidateApiKey.php` - public API auth
- `app/Http/Controllers/Api/ContactFormController.php` - public controller pattern
- `app/Http/Controllers/MediaController.php` - existing bulk upload (`bulkUpload()` method)
- `app/Http/Controllers/Api/BrandEventController.php` - media reorder pattern (`reorderPromotionPostMedia`)
- `app/Traits/HasMediaManager.php` - media collection helper trait (dipakai existing models)
- `app/Http/Resources/EventResource.php` - media url conversions (lqip/sm/md/lg/xl)
- `config/permissions.php` - permission pattern
- Cek `composer.json` untuk Excel export package (maatwebsite/excel atau sejenis)

### Frontend admin (Nuxt 4)
- `frontend/app/pages/brands/` - simple CRUD
- `frontend/app/pages/events/` - complex CRUD dengan nested
- `frontend/app/components/TableData.vue` - table pattern
- `frontend/app/components/post/PostEditor.vue` - complex form pattern
- `frontend/app/components/InputFile.vue` - FilePond wrapper untuk upload (single/multi), sudah support temp upload flow
- `frontend/app/components/InputFileImage.vue` - single image dengan preview (untuk featured image)
- `frontend/app/composables/usePermission.ts`
- `frontend/app/components/AppSidebarNavMain.vue`
- Cari existing `useSortable` usage untuk drag-reorder gallery (MEMORY.md catat: ada di aplikasi)
- Cari existing export button component (kalau ada) untuk tiru pattern

### iicc (Nuxt 4.2.2) — future phase (not implemented sekarang)
- Refer ke temuan eksplorasi: stack Nuxt 4.2.2, shadcn-nuxt, Tailwind v4, i18n EN/ID, server route proxy pattern. Akan dipakai saat deploy ke iicc.askindo.id di fase berikutnya.

## Notes (out of scope sekarang)

- **Deploy ke iicc.askindo.id** (akan di phase berikutnya setelah pmone public page stabil). Public endpoint Laravel + ApiConsumer pattern sudah disiapkan, iicc tinggal konsumsi sama seperti ContactForm pattern existing.
- Bundling ticket + hotel (saat ticket feature jadi)
- Role hotel-manager scoped
- WA notification channel
- Auto-generate voucher PDF dengan QR code (PM One-branded)
- Rating/review post-stay
- Waiting list
- Automated commission payout report
- RateCalendar per tanggal (seasonal)
- Self-service cancel di magic link page
- Reminder H-1 otomatis
- Integration langsung ke PMS hotel
