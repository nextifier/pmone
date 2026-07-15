# Exhibitor & Brand Fixes Plan

Plan perbaikan 9 issue area exhibitor/brand. Disusun 2026-07-15 setelah riset codebase + verifikasi production DB (read-only). Semua keputusan desain sudah dikonfirmasi user. Eksekusi: kerjakan sesuai urutan di bagian "Urutan eksekusi".

## Context

- Issue #1: fix di exhibitor dashboard, filter ke event `is_active = true` saja. Halaman My Events TIDAK diubah (riwayat tetap tampil).
- Issue #2: brand penanggung jawab booth = brand-event pertama (`MIN(id)`) per grup booth, otomatis, tanpa migration.
- Issue #5: fix kode dedupe kolom by label + data cleanup production (hapus set duplikat custom_fields id 127-129, pertahankan set lama 103-105).

## Aturan eksekusi (wajib)

- JANGAN commit/push kecuali diminta eksplisit.
- Setiap perubahan PHP: `vendor/bin/pint --dirty`, lalu test terkait `php artisan test --compact --filter=...` (SQLite in-memory, JANGAN sentuh PostgreSQL).
- Semua copy UI English; i18n update 3 file sekaligus: `frontend/i18n/locales/en.json`, `id.json`, `zh.json`.
- Styling: `text-xs sm:text-sm` untuk teks kecil, selalu `tracking-tight` (`tracking-tighter` untuk `text-xl`+), font weight maks `font-semibold`. Ikuti `frontend/STYLE_GUIDE.md`.
- Verifikasi frontend via browser `localhost:3000` (dev server dikelola Claude, pnpm bukan npm). JANGAN `npm run build`/`nuxi typecheck`.
- Testing user wajib `email_verified_at` terisi (kalau tidak 403 middleware `verified`).
- Query modifikasi data production: konfirmasi dulu ke user sebelum eksekusi.

---

## Issue 1 - Exhibitor dashboard menampilkan semua brand dari event lama

**Problem:** Company dengan >1 brand yang re-exhibit melihat semua brand dari event sebelumnya di dashboard, bukan hanya brand yang ditambahkan ke event aktif.

**Root cause:** `app/Http/Controllers/Api/ExhibitorDashboardController.php:44-52` memuat SEMUA `brandEvents` tanpa filter event aktif, lalu flat-map semuanya (`:67-191`). API publik event website SUDAH benar (scoped `event_id` + `status=active` di `PublicProjectController`), jadi fix hanya di dashboard.

**Fix (backend):** di closure eager-load `brandEvents`, tambah `$q->whereHas('event', fn ($e) => $e->where('is_active', true))`. `myEvents()` (`:212-274`) TIDAK diubah (riwayat tetap ada di halaman My Events).

**Catatan:** `database/factories/EventFactory.php:128` sudah default `is_active => true`, jadi test existing aman. Pastikan empty state dashboard (exhibitor tanpa event aktif) tetap tampil wajar.

**Test:** tambah Pest test: brand dengan brand-event di event `is_active=false` tidak muncul di `/api/exhibitor/dashboard`; yang di event aktif muncul.

---

## Issue 2 - Booth sama dipakai beberapa brand: hanya 1 brand yang isi Operational Documents + Order Form

**Kondisi sekarang:** submissions dokumen sudah shared per booth via `booth_identifier = booth_number ?: "be-{id}"` (`ExhibitorDashboardController.php:56,87`; `EventDocumentSubmission` tidak punya brand_id). Tapi semua brand di booth sama bisa mengisi, dan Order tetap per `brand_event_id` sehingga tiap brand bisa order terpisah.

**Desain (dikonfirmasi user):** brand-event dengan `MIN(id)` di antara baris `brand_event` yang `event_id` sama + `booth_number` sama (non-empty, exact match, sudah dinormalisasi mutator `BrandEvent::boothNumber()`) = "booth primary". Stabil terhadap reorder staff (jangan pakai kolom `order`). Brand tanpa `booth_number` selalu primary. Tanpa migration.

**Fix backend (`ExhibitorDashboardController.php`):**
1. Helper private, mis. `boothPrimaryId(BrandEvent $be): int` + versi batch untuk dashboard: satu query `BrandEvent::whereIn('event_id', $eventIds)->whereNotNull('booth_number')->groupBy('event_id','booth_number')->selectRaw('event_id, booth_number, MIN(id) as primary_id')`.
2. Payload `dashboard()` per brand-event tambah: `is_booth_primary` (bool) dan `booth_primary_brand_name` (nullable, nama brand primary; query kedua hanya untuk booth yang non-primary).
3. Guard tulis: method private `assertBoothPrimary(BrandEvent $be)` yang abort 403 + `error_code: BOOTH_NOT_PRIMARY`, dipanggil di `submitDocument()` (`:903`), `updateBoothFields()` (area `:1273`), dan `submitOrder()` (`:747`). Event-rules agreement TIDAK digate (submission-nya memang shared per booth; semua brand boleh agree).
4. Endpoint info yang dipakai halaman penuh (documents index exhibitor + `order-form-info`) tambah field `is_booth_primary` + `booth_primary_brand_name` supaya halaman bisa menampilkan notice.

**Fix frontend:**
1. `frontend/app/utils/exhibitorDashboard.js` `getExhibitorSteps()` (`:27-85`): skip step `docs` dan `order` bila `be.is_booth_primary === false` (progress/stepper otomatis menyesuaikan; verifikasi hero "What's Next" di `DashboardExhibitorHero.vue` ikut benar karena derive dari steps).
2. `frontend/app/components/dashboard/DashboardExhibitorSections.vue`: bila non-primary, ganti Section 5 (Operational Documents, `:225-293`) dan Section 6 (Order Form, `:295-308`) dengan satu note muted, copy English, i18n key baru mis. `ed.booth.sharedNote`: "Operational documents and the order form for booth {booth} are managed under {brand}."
3. Halaman penuh `frontend/app/pages/brands/[slug]/documents/[brandEventId].vue` dan `frontend/app/pages/brands/[slug]/order-form/[brandEventId].vue`: bila info payload bilang non-primary, tampilkan notice yang sama dan sembunyikan form (defensif, direct URL).

**Test:** Pest: event + 2 brand-event booth sama (set `booth_number` eksplisit; `BrandEventFactory` booth-nya `optional(0.7)` acak): primary bisa submit doc + order; non-primary dapat 403 `BOOTH_NOT_PRIMARY`; payload dashboard berisi flag benar; brand tanpa booth number selalu primary.

---

## Issue 3 - Fascia Name: limit 24 hanya exhibitor, staff bebas; + wording contact WhatsApp

**Fix backend:**
- `app/Http/Controllers/Api/BrandEventController.php:194` (store) dan `:403` (update): `max:24` -> `max:255`. Perhatikan `update()` tidak meng-uppercase (hanya store dan endpoint exhibitor); biarkan konsisten seperti sekarang.
- `app/Imports/BrandEventsImport.php`: cek apakah ada validasi/limit fascia 24, samakan ke 255 bila ada.
- Endpoint exhibitor `ExhibitorDashboardController::updateBoothFields()` (`:1273`) TETAP `max:24`.

**Fix frontend:**
- Staff: `frontend/app/pages/projects/[username]/events/[eventSlug]/brands/[brandSlug]/index.vue:30-38`: hapus `maxlength="24"`, placeholder jadi "Fascia name".
- Exhibitor: `frontend/app/components/dashboard/DashboardExhibitorSections.vue:259-270`: pertahankan `maxlength="24"`; di bawah input tambah hint (pattern `<p class="text-muted-foreground text-xs tracking-tight sm:text-sm">`): teks existing `ed.docs.fasciaHint` + kalimat baru mis. `ed.docs.fasciaContactHint`: "Need more than 24 characters? Contact us on WhatsApp." dengan link `https://wa.me/{digits}`.
- Nomor WhatsApp: per project. Backend `dashboard()` tambah di blok `event`: `project_contact` diambil dari `$event->project->phone` (array `{label, number}`): cari label case-insensitive `whatsapp pc`, fallback `whatsapp sales`, kalau dua-duanya tidak ada -> null (frontend sembunyikan kalimat contact, tampilkan hint max-24 saja). Eager-load `event.project` di query dashboard. Normalisasi nomor ke digit (strip non-digit, `0` awal -> `62`); cek dulu apakah sudah ada util wa-link di frontend sebelum bikin baru.
- i18n en/id/zh untuk key baru.

**Test:** staff update fascia 30 chars -> 200; exhibitor update fascia 30 chars -> 422; payload dashboard berisi `project_contact` dengan prioritas WhatsApp PC > WhatsApp Sales > null.

---

## Issue 4 - Predefined option "WhatsApp PC" di Phones project settings

**Fix:** `frontend/app/components/FormProject.vue:485`: `PREDEFINED_PHONE_LABELS = ["WhatsApp Sales", "WhatsApp Marketing", "WhatsApp PC"]`. Konstanta lokal file ini saja; mapping load (`:700`) sudah pakai `includes()` jadi otomatis benar. Backend label free-form (`ProjectController` validasi `phones.*.label` string bebas), tidak perlu perubahan.

---

## Issue 5 - Spreadsheet custom fields: mapping kolom berdasarkan nama field (tanpa duplikat antar project)

**Root cause (diverifikasi di production DB):**
- `SheetsController::brandCustomFieldDefinitions()` (`app/Http/Controllers/Api/SheetsController.php:552-561`) dedupe `->unique('key')`. Key di-generate per-project dari label, jadi label sama antar project biasanya key sama (aman), TAPI: globalaiexpo punya set duplikat dibuat 2026-07-14 (id 127-129) termasuk key `buyer_target#1_2` ber-label "Buyer Target #2" -> muncul 2 kolom "Buyer Target #2".
- Duplikat production: id 127 (`buyer_target#1`), 128 (`buyer_target#1_2`), 129 (`buyer_target#3`) menduplikasi id 103-105 (set lama options asli "Investors/Distributor/...", set baru options test "Investor/A/B" + `settings.public=false`). Hanya 1 brand punya nilai di key `buyer_target#1_2`.

**Fix kode (`SheetsController` + `app/Support/Sheets/SheetFormatting.php:36-48`):**
- `brandCustomFieldDefinitions()`: group by label ternormalisasi (lowercase + trim, label English) -> hasilkan koleksi "column def" `{label, type, options, keys: [...]}` (urut kemunculan pertama, `ordered()`).
- `SheetFormatting::customFieldColumns()`: terima keys jamak per kolom; nilai = first non-empty di antara keys, diformat via `FormFieldTypes::formatValueForType`.
- Terapkan konsisten di brands sheet (`:254`, `:339`) dan brand-events sheet (`:395`, `:431`, `:532`); pastikan guard suffix "(Booth)" (`:404-409`) masih bekerja dengan struktur baru.

**Data cleanup production (konfirmasi user dulu; via tinker di server prod, MCP postgres read-only tidak bisa write):**
1. Brand yang punya key `buyer_target#1_2` di `brands.custom_fields`: salin nilainya ke key `buyer_target#2` bila kosong, hapus key `buyer_target#1_2`.
2. Set `settings.public = false` pada custom_fields id 103, 104, 105 (memindahkan flag internal dari set baru).
3. Hapus rows `custom_fields` id 127, 128, 129.

**Test:** unit/feature test SheetFormatting: dua definisi label sama beda key -> 1 kolom, nilai coalesce dari key manapun.

---

## Issue 6 - Booth number di card dashboard exhibitor: lebih besar, tanpa bg-muted, pindah ke kanan menggantikan "3/6"

**Fix:** `frontend/app/components/dashboard/DashboardExhibitorBrandCard.vue`:
- Hapus chip booth ber-`bg-muted` (`:19-30`, bagian booth saja; `booth_type_label` tetap di posisi sekarang).
- Sisi kanan header (`:33-39`): buang `progressLabel` (dan computed-nya `:80-83` bila tak terpakai lagi), ganti dengan booth number yang selalu tampil bila ada (jangan digate `collapsible`), mis. label "Booth" kecil muted + nomor `text-lg sm:text-xl font-semibold tracking-tighter`. Chevron tetap hanya saat `collapsible`.
- Progress detail tetap tersedia lewat stepper (`DashboardExhibitorStepper`, `:45`), tidak perlu pengganti "3/6".
- Verifikasi light + dark mode di browser.

---

## Issue 7 - Placeholder Business Category: hilangkan "..."

**Fix:** i18n key `brandsForm.addCategory` ("Add category..." -> "Add category") di `frontend/i18n/locales/en.json:113` + padanan `id.json`/`zh.json`. Dipakai `FormBrandProfile.vue:93,101` (MultiSelect + fallback TagsInput). Halaman staff brand-event sudah tanpa ellipsis.

---

## Issue 8 - Custom fields di brand edit hanya dari project yang event-nya aktif

**Problem:** `/brands/bakso-kampungqu/edit` menampilkan custom fields redundant dan dari project lain.

**Root cause:** `app/Http/Controllers/Api/BrandController.php:91-110` (show) dan `:224-248` (update) mengambil project IDs dari SEMUA `brandEvents` brand (semua event yang pernah diikuti), lalu `brandFieldDefinitionsFor()` (`:780-788`) union semua definisi tanpa dedupe. Sama di `ExhibitorDashboardController::brandShow()` (`:301-344`, agregasi `:309`). Pembanding yang sudah benar: `BrandEventController::show()` (`:317`) pakai project event saat itu saja.

**Fix backend:**
- Di ketiga titik: filter `brandEvents` ke `event.is_active === true` sebelum pluck project IDs. Fallback bila brand tidak punya event aktif: project dari event terbarunya (berdasar `start_date` terbaru) supaya field tetap tampil/editable.
- `brandFieldDefinitionsFor()`: tambah dedupe defensif by label ternormalisasi (guard sisa duplikat dalam satu project).
- `update()`: pastikan penyimpanan `custom_fields` JSON MERGE dengan nilai existing, bukan replace total. Nilai key dari project lain (event tidak aktif) TIDAK boleh terhapus saat exhibitor menyimpan form yang hanya berisi field project aktif. Periksa alur persist di `BrandController::update()` / `CustomFieldService` sebelum mengubah.
- Halaman lain sudah dicek: staff brand-event page sudah benar; exhibitor documents page pakai custom fields context `document` (sistem berbeda, tidak disentuh). Frontend tidak perlu perubahan (definisi datang dari API).

**Test:** brand ikut event aktif project A + event non-aktif project B: `GET /api/brands/{slug}` hanya kembalikan definisi project A tanpa duplikat; update nilai field A tidak menghapus nilai field B yang tersimpan.

---

## Issue 9 - Links section: semua predefined link langsung terbuka tanpa klik "Add Link"

**Pola fix (sama di semua form):** seeding rows di-merge dari daftar predefined: map setiap label predefined -> row `{label, url: <dari link tersimpan yang label-nya match, case-insensitive>, isCustomLabel: false}`, lalu append link custom/non-predefined yang tersimpan. Tombol "Add Link" tetap ada untuk link custom tambahan. Filter save yang sudah drop row ber-url kosong dipertahankan (verifikasi tiap form; tambahkan bila belum ada).

**Lokasi (terapkan semuanya):**
1. `frontend/app/components/brand/FormBrandProfile.vue`: seeding `:343-349` + watch resync `:456-465` (save filter `:519-523` sudah ada). `PREDEFINED_LINK_LABELS` di `:341`.
2. `frontend/app/components/FormUser.vue` (links `:139-189`, labels `:319`) - dipakai settings/profile, [username]/edit, users/create, exhibitors/create.
3. `frontend/app/components/FormProject.vue` (links `:156-210`, labels `:476`; JANGAN sentuh phones kecuali issue #4).
4. `frontend/app/components/guest/FormGuest.vue` (links `:276-335`, labels `:91`).
5. `frontend/app/pages/projects/[username]/events/[eventSlug]/brands/[brandSlug]/index.vue` (duplikasi blok links staff, render `:334-360`, labels `:745`, `addLink` `:755`).

**Verifikasi:** buka `/brands/bakso-kampungqu/edit` (dan create forms): semua field predefined (Website, Instagram, dst) langsung tampil kosong siap isi; link tersimpan ter-prefill; simpan tanpa mengisi tidak membuat link kosong.

---

## Urutan eksekusi yang disarankan

1. Issue 4, 7 (trivial, frontend-only)
2. Issue 6, 9 (frontend-only)
3. Issue 1 (backend kecil + test)
4. Issue 3 (backend + frontend + i18n)
5. Issue 8 (backend, hati-hati merge custom_fields)
6. Issue 2 (paling besar: backend guard + payload + frontend)
7. Issue 5 (kode export + data cleanup production terakhir, dengan konfirmasi)

## Verifikasi end-to-end

- `php artisan test --compact --filter=ExhibitorDashboard`; `--filter=BrandController`; `--filter=BrandEvent`; test baru issue 2/3/5/8. Jalankan per-chunk, JANGAN full suite single-process (fatal 120s karena `set_time_limit` di DocumentService).
- Browser `localhost:3000`: dashboard exhibitor (booth number kanan besar, event lama hilang, booth sharing note), `/brands/bakso-kampungqu/edit` (custom fields hanya project aktif, links terbuka semua, placeholder tanpa "..."), `/projects/globalaiexpo/settings` (option WhatsApp PC), halaman staff brand-event (fascia > 24 chars bisa), light + dark mode.
- Setelah semua: `vendor/bin/pint --dirty`.
- PROD setelah deploy: jalankan data cleanup issue #5 (dengan konfirmasi). Tidak ada migration baru di plan ini.
