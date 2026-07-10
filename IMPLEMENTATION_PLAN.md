# Implementation Plan: 5 Briefs

> Dokumen ini disusun dengan eksplorasi menyeluruh pada repo `pmone` dan `~/Frontend/pmone-events` per **2026-07-10** (working tree dengan perubahan uncommitted). Semua file path dan line number sudah diverifikasi langsung di kode. Line number bisa bergeser, verifikasi dengan grep sebelum edit.
>
> Implementer: kerjakan **per brief secara berurutan**, jalankan test setelah tiap brief, lalu verifikasi manual di browser. Jangan mengerjakan dua brief sekaligus dalam satu pass.

---

## Bagian 0 - Aturan global untuk implementer

- **Urutan pengerjaan: Brief 1 → 3 → 2 → 4 → 5.** Brief 4 menyentuh output Brief 1/2/3 (kolom sheets); Brief 5 butuh `profile_image` dari Brief 1.
- `vendor/bin/pint --dirty` setelah setiap perubahan PHP.
- Test: Pest, `php artisan test --compact --filter=NamaTest` (SQLite in-memory). JANGAN sentuh PostgreSQL utama. User testing WAJIB diisi `email_verified_at` (mis. `now()`), tanpa itu kena 403 middleware `verified`.
- `php artisan permissions:sync` setelah mengubah `config/permissions.php`.
- Frontend `/frontend` pakai **pnpm** (BUKAN npm). JANGAN jalankan `nuxi typecheck` / `npm run build` / proses berat dari terminal; verifikasi via browser `localhost:3000`.
- UI copy **English**. Chat/penjelasan boleh Indonesia, tapi semua teks yang tampil di UI harus English.
- Styling wajib ikut `frontend/STYLE_GUIDE.md`: `tracking-tight` (text `text-xl`+ pakai `tracking-tighter`), font weight maksimal `font-semibold`, teks kecil pakai `text-xs sm:text-sm` (khusus Brief 5: minimal `text-sm`, tanpa `text-xs` sama sekali), warna via CSS variables (dilarang `bg-green-500` dkk), Badge wajib `<Badge>`, dialog konfirmasi `<DialogResponsive>`, table admin `<TableData>`, icon `hugeicons:*`.
- JANGAN tambah dependency baru (npm/composer) tanpa approval.
- JANGAN pakai em-dash di teks apa pun; pakai dash biasa atau koma.
- DILARANG KERAS: `migrate:fresh/reset/rollback`, `db:wipe`, `TRUNCATE`, `DROP TABLE`.
- JANGAN menawarkan/melakukan commit kecuali diminta eksplisit.

### Peta arsitektur singkat (hasil eksplorasi)

- **Brand media**: `Brand` pakai trait `HasMediaManager` (collection didefinisikan di `getMediaCollections()`, `single_file` → Spatie `singleFile()`). `getMediaUrls($collection)` (app/Traits/HasMediaManager.php:33-58) menghasilkan `{url, original, caption, alt, width, height, lqip, sm, md, lg, xl}`; key conversion fallback ke original URL jika belum tergenerate.
- **Upload flow**: FilePond → `POST /api/tmp-upload` (`TemporaryUploadController::upload`, validasi hanya `file|max:20480`, SELALU `ImageOptimizer::compressInPlace`) → form submit mengirim `tmp_{field}` (folder id) → controller `handleTemporaryUpload` membaca `tmp/uploads/{folder}/metadata.json`, `clearMediaCollection`, `addMedia(...)->toMediaCollection(...)`.
- **`ImageOptimizer::compressInPlace`** (app/Support/ImageOptimizer.php): skip non jpeg/png/webp; cap `config('images.original_max_dimension')` default 2560, quality 90, skip file < 500KB.
- **Order engine**: `ExhibitorDashboardController::submitOrder` (:732-856) → `Order::create` (subtotal, tax 0 dulu) → `items()->createMany` → `PenaltyService::evaluateAndApply` (penalty onsite jadi `AppliedAdjustment` terpisah) → `PromoCodeService::applyByCode` (opsional) → `PricingService::recalculateAndPersist` (hitung final discount/penalty/tax/total).
- **Operational documents**: `EventDocument` (definisi per event, filter `booth_types`, mini-form via `CustomField` context `document`) → `EventDocumentSubmission` (SATU row per doc+booth+event, unique constraint; file di collection `submission_file` non-singleFile, tiap media punya `custom_properties.field_ulid`).
- **SheetsController**: 4 endpoint GET token-auth (`?token=` vs `config('services.sheets.api_token')`), response `{title|event, headings[], rows[][], updated_at}` untuk Google Sheets. Routes di `routes/api.php:906-917`.
- **Custom fields**: `CustomField` polymorphic (context `brand` milik Project via `fieldable`); nilai Brand tersimpan inline di `brands.custom_fields` jsonb keyed by field `key`; nilai BrandEvent di `brand_event.custom_fields` jsonb TANPA katalog definisi. Formatter siap pakai: `FormFieldTypes::formatValueForType($type, $value, $options)` (app/Support/FormFieldTypes.php:278-324).
- **brands.address** jsonb keys: `{street, city, province, country}` (migration `2026_07_10_170000_convert_brands_company_address_to_jsonb.php`).

---

## Brief 1 - Split `profile_image` (avatar) vs `brand_logo` (raw file)

### Latar belakang

Saat ini collection `brand_logo` dipakai sebagai avatar di pmone dan pmone-events. Kebutuhan baru:
- **Profile Image** (baru): avatar square 1:1, image only, min 1000x1000px, dikompresi sesuai pattern. Dipakai di SEMUA tempat avatar sekarang.
- **Brand Logo** (repurpose): file mentah untuk staff/designer (banner, social media, print). Bebas ratio, boleh image/PDF/AI/ZIP, disimpan **original tanpa kompresi**, TANPA conversions. Tidak tampil publik.
- Data existing `brand_logo` di-duplicate (copy, bukan move) ke `profile_image`.

### 1.1 Backend: `app/Models/Brand.php`

- `getMediaCollections()` (:284-297): tambah `profile_image` dan ubah mimes `brand_logo`:

```php
'profile_image' => [
    'single_file' => true,
    'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
],
'brand_logo' => [
    'single_file' => true,
    'mime_types' => [
        'image/jpeg', 'image/png', 'image/webp', 'image/svg+xml',
        'application/pdf', 'application/postscript', 'application/illustrator',
        'application/zip', 'application/x-zip-compressed', 'application/octet-stream',
    ],
],
// description_images tidak berubah
```

- `registerMediaConversions()` (:217-282): semua conversion square lqip/sm(200)/md(400)/lg(800)/xl(1080) pindah dari `performOnCollections('brand_logo')` → `performOnCollections('profile_image')`. Collection `brand_logo` TIDAK punya conversion sama sekali (pattern: `EventDocumentSubmission` tidak mendaftarkan conversion untuk `submission_file`).
- Accessor baru:

```php
public function getProfileImageAttribute(): ?array
{
    return $this->getMediaUrls('profile_image');
}
```

- Ubah `getBrandLogoAttribute()` (:302-305) jadi shape raw, via method baru reusable di `app/Traits/HasMediaManager.php`:

```php
public function getMediaFileInfo(string $collection): ?array
{
    $media = $this->getFirstMedia($collection);
    if (! $media) {
        return null;
    }

    return [
        'url' => $media->getUrl(),
        'name' => $media->file_name,
        'size' => $media->size,
        'mime_type' => $media->mime_type,
        'extension' => pathinfo($media->file_name, PATHINFO_EXTENSION),
        'uploaded_at' => $media->created_at?->toIso8601String(),
    ];
}
```

### 1.2 Backend: tmp-upload `skip_optimize`

`app/Http/Controllers/Api/TemporaryUploadController.php::upload` (:16-51): tambah param opsional `skip_optimize` (`sometimes|boolean`); jika true, lewati `ImageOptimizer::compressInPlace($absolutePath)` (:37). Aman karena route tetap `auth:sanctum` + `verified`, cap `max:20480` (20MB) tetap berlaku, dan mime enforcement tetap terjadi saat attach ke collection (Spatie `acceptsMimeTypes`).

### 1.3 Backend: validasi min 1000x1000 (net-new, belum ada precedent `dimensions` di repo)

Helper baru `app/Support/ImageDimensions.php`:

```php
public static function meetsMinimum(string $absolutePath, string $mimeType, int $min = 1000): bool
// SVG (image/svg+xml) SELALU lolos (pattern existing: SVG di-exclude dari cek dimensi, lihat BackfillMediaDimensions).
// Selain itu: getimagesize($absolutePath), false → gagal; cek width >= $min && height >= $min.
```

Panggil tepat sebelum `addMedia(...)` KHUSUS collection `profile_image` di dua tempat:
- `BrandController::handleTemporaryUpload` (:829-880)
- `ExhibitorDashboardController::handleTemporaryUpload` (:1353-1414)

Gagal → `ValidationException::withMessages(['tmp_profile_image' => 'Profile image must be at least 1000x1000 pixels.'])`. Catatan: kompresi tmp-upload cap 2560 > 1000, jadi tidak pernah membuat file valid jadi invalid.

### 1.4 Backend: controllers

- `BrandController::update` (:164-278): tambah rules `tmp_profile_image => nullable|string`, `delete_profile_image => nullable|boolean` (sejajar dengan `tmp_brand_logo` di :182-183), unset sebelum `$brand->update`, lalu `handleTemporaryUpload($request, $brand, 'tmp_profile_image', 'profile_image')` di samping call existing untuk `brand_logo` (:214).
- Payload inline `BrandController` (TIDAK ada BrandResource): `show` :125, `update` response :271, `search` :465, `transformBrandForTable` :808 → tambah `'profile_image' => $brand->profile_image`; key `brand_logo` tetap ada tapi kini shape raw.
- `ExhibitorDashboardController::brandUpdate` (:349-394): sama (rules + handle kedua field). `ResponseCache::clear(['brands'])` yang sudah ada di `handleTemporaryUpload` dipertahankan untuk kedua collection.
- `ExhibitorDashboardController::dashboard`: payload brand tambah `'profile_image' => $brand->profile_image` (dipakai Brief 5). Pastikan `media` tetap eager-loaded.

### 1.5 Backend: resources dan backward compat public API

- Admin resources, tambah `profile_image` (dengan guard `relationLoaded('media')`, pola existing): `app/Http/Resources/BrandEventResource.php:46`, `BrandEventIndexResource.php:31`, `OrderResource.php:76-78`. Key `brand_logo` tetap (raw shape); admin frontend deploy atomic dengan API jadi aman.
- **Public API (KRITIS - 11 situs pmone-events deploy TIDAK atomic dengan API):** `app/Http/Resources/PublicBrandIndexResource.php:23`:

```php
$profileImage = $brand?->relationLoaded('media')
    ? ($brand->profile_image ?? $brand->getMediaUrls('brand_logo')) // fallback selama window migrasi (sebelum command copy jalan)
    : null;
// ...
'profile_image' => $profileImage,
'brand_logo' => $profileImage, // DEPRECATED alias untuk 11 situs; hapus di cleanup PR setelah semua situs deploy
```

`PublicBrandDetailResource` extends index resource, otomatis ikut. Fallback `getMediaUrls('brand_logo')` aman: media lama masih punya `generated_conversions` di DB sehingga URL sm/md/lg/xl tetap resolve.

### 1.6 Backend: score service

`app/Services/Brand/BrandProfileScoreService.php` - `hasLogo()` (:98-107) transisi: cek `profile_image` ATAU `brand_logo`. Rubric key `logo` (weight 18) JANGAN di-rename (dipakai sort/breakdown pmone-events). Cleanup jadi `profile_image`-only dilakukan di cleanup PR setelah command copy jalan di production.

### 1.7 Backend: exports, imports

- `app/Exports/BrandsExport.php` (~:40 heading, :50 value): kolom `Brand Logo` tetap = `getFirstMediaUrl('brand_logo', 'original')` (raw); tambah kolom `Profile Image` = `getFirstMediaUrl('profile_image', 'md')`.
- `app/Exports/BrandEventsExport.php:96`: sama (Brand Logo raw + kolom Profile Image baru).
- `app/Imports/BrandEventsImport.php` (:113-119 validasi URL, :184-186 guard, ~:338 `importBrandLogo`): arahkan `addMediaFromUrl` ke collection `profile_image` (sumber import selalu image URL); rename method `importBrandLogo` → `importProfileImage`; kolom input Excel `brand_logo` tetap diterima sebagai alias.
- `app/Imports/BrandsImport.php`: tidak menyentuh logo, tidak berubah.
- Email/PDF: TIDAK ada yang memakai brand logo exhibitor (sudah diverifikasi grep), tidak ada perubahan.

### 1.8 Backend: command copy data

File baru `app/Console/Commands/CopyBrandLogoToProfileImage.php`. Pattern ikut `CompressMediaOriginals.php` + `BackfillMediaDimensions.php` (chunkById, progress bar, dry-run, konfirmasi production tanpa `--force`).

- Signature: `brands:copy-logo-to-profile-image {--dry-run} {--chunk=100} {--force}`
- Logic: `Media::query()->where('model_type', Brand::class)->where('collection_name', 'brand_logo')->chunkById(...)`; per media: skip jika brand sudah punya media `profile_image`; skip mime non-image (hanya copy jpeg/png/webp/svg); `$media->copy($brand, 'profile_image')` (Spatie native; net-new di repo ini tapi first-party API). Copy men-trigger generate conversions (md/lg/xl queued, pastikan queue worker jalan).
- Setelah selesai: `ResponseCache::clear(['brands'])` dan print ringkasan (copied/skipped).
- TIDAK ada migration DB (collection hanya string di tabel media).

### 1.9 Frontend admin: input components

- `frontend/app/components/InputFile.vue`: prop baru `skipOptimize: Boolean`; saat true, FilePond `server.process` (:164) meng-append `skip_optimize=1` ke FormData.
- `frontend/app/components/InputFileImage.vue`:
  - Prop baru `minDimension: Number` (default null): client-side check saat file ditambahkan, TANPA dependency baru - `URL.createObjectURL(file.file)` → `new Image()` → `onload` cek `naturalWidth/naturalHeight >= minDimension`; skip jika mime `image/svg+xml`; gagal → tolak file + tampilkan pesan error "Image must be at least 1000x1000 pixels."
  - Emit baru `preview-url`: emit objectURL saat file dipilih, `null` saat dihapus (dipakai Brief 5). Revoke objectURL lama saat ganti file dan saat `onBeforeUnmount`.
- Komponen baru `frontend/app/components/InputFileDownloadCard.vue` (untuk raw Brand Logo):
  - Props: `modelValue` (array tmp ids), `initialFile` (shape raw 1.1), `deleteFlag`, `acceptedFileTypes`, `skipOptimize`.
  - Ada file: card `rounded-lg border` berisi icon per mime (`hugeicons:pdf-01` / `hugeicons:zip-01` / `hugeicons:image-01` / `hugeicons:file-01`), nama file `font-medium tracking-tight truncate`, size (format human) + uploaded_at `text-muted-foreground text-xs sm:text-sm`, tombol **Download** (link `initialFile.url`, `target="_blank"`), **Replace**, **Delete** (set deleteFlag, konfirmasi ringan).
  - Kosong / replace mode: render `<InputFile :skip-optimize="skipOptimize" :accepted-file-types="acceptedFileTypes">`.

### 1.10 Frontend admin: form brand

`frontend/app/components/brand/FormBrandProfile.vue` (gated prop `showLogo` existing) - ganti field logo tunggal jadi group **"Brand Assets"** berisi 2 field. Gunakan elemen visual yang jelas membedakan keduanya (dua blok field dengan hint list beriKon di bawah masing-masing; hint pakai `text-muted-foreground text-xs sm:text-sm` + icon `size-3.5`):

**Field 1 - Profile Image** (`InputFileImage :initial-image="brand?.profile_image" :min-dimension="1000" allow-svg`, submit `body.tmp_profile_image` / `body.delete_profile_image`):
- Helper: `Shown as your brand's avatar on the exhibitors list and your brand page.`
- Hints (list item terpisah dengan icon):
  - `Square, at least 1000x1000px`
  - `JPG, PNG, WebP, or SVG`
  - `Max 20MB`
  - `Use a solid background - a transparent logo can disappear against light or dark pages.`
- Preview image di-render di atas background checkerboard (CSS gradient pattern) supaya logo transparan langsung ketahuan.

**Field 2 - Brand Logo** (`InputFileDownloadCard :initial-file="brand?.brand_logo" skip-optimize`, accepted types image + `application/pdf, application/postscript, application/illustrator, application/zip, application/x-zip-compressed`, submit `body.tmp_brand_logo` / `body.delete_brand_logo`, mapping existing di :370-375):
- Helper: `The master file our design team uses for banners, social media, and print. Upload the highest resolution you have - we store it exactly as uploaded, without compression.`
- Hints:
  - `Any shape - no ratio requirement`
  - `JPG, PNG, WebP, SVG, PDF, AI, or ZIP`
  - `Max 20MB`
  - `Not shown anywhere on the website.`

Form kedua (inline di `frontend/app/pages/projects/[username]/events/[eventSlug]/brands/[brandSlug]/index.vue`, upload field :142, refs :612, submit mapping :793-797): treatment sama persis (2 field + copy sama).

### 1.11 Frontend admin: semua display avatar pindah ke `profile_image`

Semua berikut mengoper `brand.brand_logo` ke `<Avatar :model="{ profile_image: ... }">` atau `<img>`; ganti sumbernya jadi `brand.profile_image`:

- `components/brand/TableItem.vue:3`
- `components/brand/FormAddBrandToEvent.vue:37` (catatan: existing baca `.thumbnail` yang tidak pernah ada, betulkan jadi `.sm`)
- `pages/brands/trash.vue:300`
- `pages/brands/[slug]/index.vue:82`
- `pages/projects/[username]/events/[eventSlug]/brands/[brandSlug]/marketing.vue:72, 482`
- `pages/projects/[username]/events/[eventSlug]/operational/orders/[ulid].vue:81`
- `components/dashboard/DashboardExhibitorSections.vue:152`
- `components/exhibitor/ExhibitorMyEvents.vue:104`
- `components/header/HeaderBreadcrumb.vue:70`

Grep final `brand_logo` di `/frontend/app` untuk memastikan sisa referensi hanya di konteks raw-file field.

### 1.12 pmone-events (`~/Frontend/pmone-events/layers/base/app`)

Ganti konsumsi avatar jadi `brand.profile_image ?? brand.brand_logo` (fallback dua arah, aman sebelum/sesudah API deploy):

- `composables/useBrandPreview.ts:21` (interface) dan `:49-59` (filter `brandsWithLogo`)
- `components/BrandPreview.vue:50`
- `components/BrandGridItem.vue:15`
- `components/BrandResultsView.vue:206`
- `pages/brands/[slug].vue:60, 76, 203, 556` (termasuk Lightbox `fullKey="xl"`)
- `pages/[edition]/brands/[slug].vue` (file near-identical, posisi setara, Lightbox :499)

Grep `brand_logo` di `layers/base` dan `apps/*` untuk memastikan tidak ada yang terlewat (beberapa apps punya gambar hardcoded lokal, itu bukan konsumsi API, biarkan).

### 1.13 Urutan deploy production

1. Deploy API + admin pmone (dengan alias + fallback public API).
2. `php artisan brands:copy-logo-to-profile-image --force` (pastikan queue worker aktif untuk conversions).
3. Deploy 11 situs pmone-events kapan pun setelahnya (alias `brand_logo` tetap berisi data avatar).
4. Cleanup PR terpisah setelah semua situs live: hapus alias `brand_logo` dari public resources, hapus fallback, `hasLogo()` jadi cek `profile_image` saja.

PERINGATAN: JANGAN jalankan `media-library:regenerate` untuk model Brand selama window transisi (conversions `brand_logo` sudah tidak terdaftar; regenerate akan menghapus file conversions lama yang masih dipakai fallback).

### 1.14 Tests

`tests/Feature/Brand/BrandProfileImageTest.php` (buat via `php artisan make:test --pest`):
- Update brand dengan `tmp_profile_image` valid (fake image 1200x1200) → media masuk collection `profile_image`, response punya shape conversions.
- Image 500x500 → 422 dengan error key `tmp_profile_image`.
- SVG kecil → lolos (tidak dicek dimensi).
- Upload PDF/ZIP via `tmp_brand_logo` → tersimpan, accessor `brand_logo` shape `{url,name,size,mime_type,extension,uploaded_at}`, TIDAK ada conversions tergenerate.
- tmp-upload dengan `skip_optimize=1` → ukuran file tidak berubah.
- `PublicBrandIndexResource` expose `profile_image` dan alias `brand_logo` identik; skenario brand hanya punya media `brand_logo` lama → fallback jalan.
- Command copy: jalankan 2x → media `profile_image` tetap 1 (idempotent); `--dry-run` tidak menulis; media non-image tidak di-copy.

---

## Brief 3 - Version history file Operational Documents (retention 5)

> Dikerjakan SEBELUM Brief 2 dan 4 (Brief 4 membaca version history).

### Latar belakang

Saat exhibitor re-upload file dokumen, file lama di-hard-delete (`ExhibitorDashboardController::storeSubmissionFieldFiles` :1159-1162). Ubah jadi version history: file lama disimpan, retention **5 versi per dokumen per field**, staff bisa lihat seluruh history, exhibitor tetap hanya melihat file current. Tujuan: hindari dispute, cegah perubahan sepihak menjelang event, audit trail permanen. Activity log TIDAK bisa jadi audit (retention 30 hari, `config/activitylog.php:14`).

### Desain

Tetap satu collection `submission_file`; versi direpresentasikan lewat **custom_properties media** (tanpa tabel baru):

| property | isi |
|---|---|
| `field_ulid` | sudah ada |
| `version` | int, mulai 1 |
| `superseded_at` | ISO string; `null`/absen = current |
| `uploaded_by` | user id |
| `uploaded_by_name` | snapshot nama (hindari join saat render) |

Media lama tanpa properties = lazy semantics: `superseded_at` absen → current, `version` absen → 1. **Tidak perlu backfill.** Field dengan `settings.multiple` = akumulatif seperti sekarang, TANPA versioning.

### 3.1 `ExhibitorDashboardController::storeSubmissionFieldFiles` (:1143-1180)

Branch non-multiple:
- HAPUS `->each(fn (Media $media) => $media->delete())` (:1159-1162). Ganti: untuk tiap media lama field tersebut → `$media->setCustomProperty('superseded_at', now()->toIso8601String()); $media->save();`. Untuk media legacy (`field_ulid === null` pada field `system_key === 'legacy_file'`), sekalian set `field_ulid` supaya history rapi.
- `addSubmissionFileFromTmp` (:1182-1204): tambah custom properties `version` (max version existing field itu + 1), `uploaded_by` (auth id), `uploaded_by_name`.
- Setelah attach: **prune** - kumpulkan semua media field tsb, sort `version` desc, sisakan 5 teratas (1 current + 4 superseded), sisanya `->delete()` (hapus fisik, retention policy).
- `submissionHasFileForField` (:1121-1136): hitung file **current-only** (`superseded_at === null`).
- Cek juga jalur legacy `handleTemporaryUpload` di sekitar :1100 di dalam `submitDocument` (:968-1112): jika menarget collection `submission_file`, terapkan semantik versioning yang sama; jika menarget collection lain (template staff), biarkan.

### 3.2 Helper di `app/Models/EventDocumentSubmission.php`

```php
public function currentSubmissionFiles(): MediaCollection;              // filter superseded_at === null
public function currentFilesForField(string $fieldUlid): MediaCollection;
public function fileHistoryForField(string $fieldUlid): MediaCollection; // semua versi, sort version desc
```

### 3.3 Exhibitor TIDAK boleh melihat versi lama

`app/Http/Resources/EventDocumentSubmissionResource.php`:
- `files` (:43-52): saat ini map SEMUA media → filter jadi current-only (kalau tidak, versi lama BOCOR ke exhibitor).
- `submission_file` legacy (:39-42, `getMediaUrls`): bangun dari first current media.

### 3.4 Staff: history di endpoint existing

`BrandEventController::documentSubmissions` (:324-365), per item tambah:

```php
'file_history' => $submission?->getMedia('submission_file')
    ->groupBy(fn ($m) => $m->getCustomProperty('field_ulid') ?? 'legacy')
    ->map(fn ($group, $fieldUlid) => [
        'field_ulid' => $fieldUlid,
        'versions' => $group
            ->sortByDesc(fn ($m) => (int) $m->getCustomProperty('version', 1))
            ->values()
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->file_name,
                'url' => $m->getUrl(),
                'size' => $m->size,
                'mime_type' => $m->mime_type,
                'version' => (int) $m->getCustomProperty('version', 1),
                'is_current' => $m->getCustomProperty('superseded_at') === null,
                'uploaded_at' => $m->created_at?->toIso8601String(),
                'uploaded_by_name' => $m->getCustomProperty('uploaded_by_name'),
                'superseded_at' => $m->getCustomProperty('superseded_at'),
            ]),
    ])->values(),
```

Tanpa route baru. Endpoint exhibitor dashboard TIDAK menyertakan `file_history`.

### 3.5 `EventDocument::submissionSummary` (:330-358)

Semua pembacaan media per-field / `getFirstMediaUrl('submission_file')` diarahkan ke current-only helper (dipakai Sheets Brief 4 dan `BrandEventsExport`).

### 3.6 Frontend staff

Komponen baru `frontend/app/components/brandEvent/DocumentFileHistory.vue`, dipakai di `frontend/app/pages/brands/[slug]/documents/[brandEventId].vue` (480 baris, tampilan "Last submitted" :131, "View uploaded file" :174):
- Collapsible `File history (N versions)` per field: tiap versi menampilkan Badge `Current` (variant success) untuk `is_current`, label `v{n}`, nama file sebagai link download, size, `uploaded_by_name`, timestamp. Versi lama pakai `text-muted-foreground`.
- Copy English, `tracking-tight`, hint `text-xs sm:text-sm`.

Exhibitor UI (`components/dashboard/DashboardExhibitorDocItem.vue`) tidak berubah fungsional (tetap current-only; verifikasi ia membaca `submission.files`).

### 3.7 Tests

`tests/Feature/EventDocuments/SubmissionFileVersioningTest.php`:
- Re-upload file field non-multiple → media lama masih ada dengan `superseded_at` terisi, media baru `version = 2` current.
- Exhibitor resource `files` hanya berisi current.
- Staff endpoint `file_history` berisi 2 versi urut desc dengan `is_current` benar.
- Upload ke-6 → total media field = 5, versi tertua terhapus.
- Field `settings.multiple` → tidak ada supersede.
- `submissionSummary` mengembalikan URL file current.

---

## Brief 2 - Manual Order oleh staff

### Latar belakang

Exhibitor bisa order dari dashboard (`POST /api/exhibitor/brands/{brandSlug}/events/{brandEventId}/orders`), tapi endpoint itu memaksa brand milik user (`$request->user()->brands()->...->firstOrFail()`, :734). Admin `OrderController` TIDAK punya `store`. Staff perlu bisa membuat order atas nama exhibitor mana pun di suatu event.

Keputusan user: checkbox "Send confirmation email" default ON; staff bebas periode (boleh order kapan pun) dengan penalty onsite otomatis mengikuti periode aktif (bisa di-void via fitur adjustment existing).

### 2.1 Permission

`config/permissions.php` (:161-166) group `orders`: actions `['read', 'update']` → `['create', 'read', 'update']`. Jalankan `php artisan permissions:sync` (local; production saat deploy).

### 2.2 Migration

`php artisan make:migration add_source_to_orders_table --no-interaction`:

```php
$table->string('source', 20)->default('exhibitor')->after('order_period'); // 'exhibitor' | 'staff'
```

Tambah `source` ke `$fillable` `app/Models/Order.php` (:102-136). Alasan kolom eksplisit (bukan hanya `created_by`): role user bisa berubah seiring waktu; `source` langsung bisa jadi filter TableData + kolom Sheets. Default `exhibitor` otomatis benar untuk data historis.

### 2.3 Service extraction (refactor dulu, KEMUDIAN endpoint baru; 2 commit terpisah secara logika)

File baru `app/Services/Order/OrderSubmissionService.php`:

```php
public function determinePeriod(Event $event, CarbonInterface $now): string;
// pindahan submitOrder :767-778 (normal_order default; onsite_order jika dalam window onsite)

/**
 * @param array $options ['notes','internal_notes','promo_code','source','user'=>User]
 */
public function create(BrandEvent $brandEvent, array $items, array $options): Order;
// pindahan transaksi :780-839: validasi EventProduct aktif milik event, build itemsData
// (unit_price = base price), Order::create (+source, order_period), items()->createMany,
// PenaltyService::evaluateAndApply, PromoCodeService::applyByCode (opsional, throw = rollback),
// PricingService::recalculateAndPersist. Return $order->fresh(['adjustments']).

public function sendEmails(Order $order, Event $event, Brand $brand, User $actor,
    bool $notifyInternal = true, bool $confirmationToBrand = true): void;
// pindahan sendOrderEmails :1320-1351 (OrderSubmittedMail → settings notification_emails jika $notifyInternal;
// OrderConfirmationMail → brand->recipientEmails() + actor email dedup, jika $confirmationToBrand)
```

Refactor `ExhibitorDashboardController::submitOrder` (:732-856) memakai service ini dengan perilaku identik (source `exhibitor`). Jalankan seluruh test order existing, harus hijau, sebelum lanjut.

### 2.4 Routes

`routes/api.php` dalam group `projects/{username}/events/{eventSlug}/orders` (:477-496):

```php
Route::get('/create-info', [OrderController::class, 'createInfo'])->name('orders.create-info'); // SEBELUM route /{ulid}
Route::post('/', [OrderController::class, 'store'])->name('orders.store');
```

### 2.5 Form Request

`app/Http/Requests/Order/StoreManualOrderRequest.php`:

```php
public function authorize(): bool
{
    return $this->user()?->hasRole(['master', 'admin'])
        || $this->user()?->can('orders.create');
}

public function rules(): array
{
    return [
        'brand_event_id' => ['required', 'integer', 'exists:brand_event,id'],
        'items' => ['required', 'array', 'min:1'],
        'items.*.event_product_id' => ['required', 'integer', 'exists:event_products,id'],
        'items.*.quantity' => ['required', 'integer', 'min:1'],
        'items.*.notes' => ['nullable', 'string', 'max:500'],
        'notes' => ['nullable', 'string', 'max:2000'],
        'internal_notes' => ['nullable', 'string', 'max:5000'],
        'promo_code' => ['nullable', 'string', 'max:60'],
        'send_confirmation_email' => ['sometimes', 'boolean'],
    ];
}
```

(Ikuti konvensi authorize/permission sibling di `StoreAdjustmentRequest`.)

### 2.6 `OrderController` (app/Http/Controllers/Api/OrderController.php)

- **`store(StoreManualOrderRequest $request, string $username, string $eventSlug)`**: resolve project + event via helper existing controller ini; load `BrandEvent` + guard `$brandEvent->event_id === $event->id` (404 jika bukan participant event ini); TANPA cek `order_form_deadline` (staff bebas periode); periode + penalty otomatis via `determinePeriod(now())`; `OrderSubmissionService::create(..., ['source' => 'staff', 'internal_notes' => ..., 'user' => $request->user()])`.
  - Email: `sendEmails(..., notifyInternal: false, confirmationToBrand: $request->boolean('send_confirmation_email', true))` - `OrderSubmittedMail` internal DILEWATI (staff sendiri yang membuat), `OrderConfirmationMail` ke exhibitor default ON.
  - In-app `OrderSubmittedNotification` ke project notifiable users tetap dikirim.
  - Return `201` + `OrderResource` (eager `items.productCategory`, `brandEvent.brand`, `creator`).
- **`createInfo(Request $request, string $username, string $eventSlug)`** `?brand_event_id=`: reuse logika `ExhibitorDashboardController::orderFormProducts` (:618-670, filter booth_type + group per category) dan `orderFormInfo` (:672-730, tax_rate default 11, current period, penalty_rate, T&C) - ekstrak ke helper/service agar tidak duplikat. Response: `{products_by_category, tax_rate, current_period, penalty_rate, penalty_note, order_form_content, brand_event: {id, brand{name, profile_image}, booth_number, booth_type}}`.

### 2.7 Frontend admin

Halaman baru `frontend/app/pages/projects/[username]/events/[eventSlug]/operational/orders/create.vue`:
- Layout pattern order-form exhibitor (`frontend/app/pages/brands/[slug]/order-form/[brandEventId].vue`): `grid grid-cols-1 gap-8 lg:grid-cols-3`, panel kanan `sticky top-[var(--navbar-height-desktop)]`.
- **Step 1**: Combobox (shadcn-vue) pilih brand participant - fetch daftar brand-events event ini (endpoint index existing); item = Avatar `profile_image` + nama brand + booth number (pola `FormAddBrandToEvent.vue`).
- **Step 2** (setelah brand dipilih): fetch `GET .../orders/create-info?brand_event_id=` → render katalog produk per kategori. Komponen baru `frontend/app/components/order/OrderProductPicker.vue` (props `productsByCategory`, `v-model:items`; qty stepper + notes per item).
- **Panel kanan**: komponen baru `frontend/app/components/order/OrderSummaryPanel.vue` (props `items, taxRate, penaltyRate, currentPeriod, promoCode`): subtotal, baris estimasi penalty saat onsite period dengan info `Onsite period penalty is applied automatically and can be voided after creation.`, tax, total.
- Field tambahan: Order notes, Internal notes, Promo code, `<Checkbox>` **`Send confirmation email to exhibitor`** default checked.
- Submit → `POST /api/projects/{u}/events/{e}/orders` → toast sukses → redirect ke detail `[ulid]`.
- `operational/orders/index.vue`: tombol **Create Order** di header (tampil hanya jika punya `orders.create`, pakai pola permission check existing halaman itu); tambah kolom `Source` (Badge: `Staff` variant info / `Exhibitor` variant muted) + filter. Halaman detail `[ulid].vue` tampilkan source.
- Copy English semua.

### 2.8 Tests

`tests/Feature/Orders/StoreManualOrderTest.php`:
- Staff + permission `orders.create` → 201, subtotal/tax/total benar via PricingService.
- Event dalam onsite period → penalty `AppliedAdjustment` terpasang otomatis.
- `promo_code` invalid → 422 dan tidak ada order tersisa (transaksi rollback).
- `Mail::fake()`: default → `OrderConfirmationMail` sent, `OrderSubmittedMail` NOT sent; `send_confirmation_email=false` → keduanya not sent.
- User tanpa permission → 403. `brand_event_id` milik event lain → 404.
- Order tersimpan `source='staff'`, `created_by` = staff id.
- Regresi: test submitOrder exhibitor existing tetap hijau setelah refactor service.

---

## Brief 4 - SheetsController rework

### Latar belakang

`app/Http/Controllers/Api/SheetsController.php` (555 baris) menyajikan 4 sheet untuk Google Sheets. Masalah: kolom `Custom Fields` berupa `json_encode` blob (brands :289-291, brandEvents :467-473), kolom `Doc: {title}` bercampur di sheet BrandEvents (:382-394, :419-421, :529-543), dan sheet Orders kekurangan kolom yang diminta. `contacts()` sudah bersih (emails/phones implode, address di-split) dan jadi referensi pattern.

Kerjakan SETELAH Brief 1, 2, 3 (kolom Profile Image, Source, dan File History bergantung pada ketiganya). **Deploy semua perubahan sheets dalam SATU release** supaya pemilik Google Sheets cukup remap kolom sekali.

### 4.0 Util baru `app/Support/Sheets/SheetFormatting.php`

```php
public static function address(?array $address): array;   // [country, province, city, street], '-' jika kosong
public static function customFieldColumns(Collection $fields, ?array $values): array;
// per field: FormFieldTypes::formatValueForType($field->type, $values[$field->key] ?? null, $field->options ?? [])
public static function freeJsonColumns(array $keys, ?array $values): array;
// untuk brand_event.custom_fields tanpa katalog: array → join ', ', bool → Yes/No, scalar apa adanya, null → '-'
public static function dateTime(?CarbonInterface $dt): string; // 'Y-m-d H:i:s' atau '-'
```

- **Definisi brand custom fields**: `CustomField::query()->where('context', 'brand')->whereNull('deleted_at')->get()` union seluruh project, dedupe by `key`, sort by label; header = `$field->label` (locale en, field translatable). Referensi katalog per-project: `BrandController::brandFieldDefinitionsFor` (:772-780).
- **BrandEvent custom fields** (tanpa katalog definisi): union keys dari result set, sort alfabetis (deterministik antar-refresh), header = `Str::headline($key)`; bila bentrok dengan header brand field → suffix `' (Booth)'`.
- Konvensi: header Title Case, datetime `Y-m-d H:i:s`, null `'-'`, enum pakai `->label()`.

### 4.1 `orders($eventId)` (:19-113)

Eager load tambah `brandEvent.event` dan `adjustments`. Urutan kolom FINAL (grain tetap 1 row per order item; kolom order-level berulang per item):

```
ID | Order Number | Event ID | Event Title
| Brand Name | Company Name | Country
| Booth Type | Booth Number | Booth Size (sqm) | Booth Price | Fascia Name | Badge Name | Sales PIC
| Order Period | Source
| Product Name | Product Category | Qty | Unit Price | Item Total | Item Notes | Item Internal Notes
| Subtotal | Discount Amount | Penalty Amount | Promo Code | Adjustment Reason
| Tax Rate (%) | Tax Amount | Total
| Operational Status | Payment Status | Cancellation Reason
| Order Notes | Order Internal Notes
| Submitted At | Confirmed At | Created By
```

- `Event ID` / `Event Title` = `$order->brandEvent?->event` (diminta untuk lookup, meski endpoint per-event).
- `Country` = `data_get($brand->address, 'country') ?? '-'`.
- `Source` = `Str::title($order->source)` (Brief 2).
- `Item Internal Notes` = `order_items.internal_notes`; `Order Internal Notes` = `orders.internal_notes`.
- `Adjustment Reason` = `$order->adjustments->map(...)`: `rule_snapshot['reason'] ?? label`; yang voided → `"{label} (voided: {void_reason})"`; join `'; '`; kosong → `'-'`.

### 4.2 `brands()` (:189-335)

- HAPUS kolom blob `Custom Fields` (heading :238, builder :289-291, row :325).
- Heading `Logo URL` → ganti jadi dua kolom: `Profile Image URL` (= `getFirstMediaUrl('profile_image', 'md')`) lalu `Logo URL` (= `getFirstMediaUrl('brand_logo')` original raw), menggantikan :242 dan :307.
- Append **dynamic brand custom field columns** PALING AKHIR (setelah `Updated At`), pakai `SheetFormatting::customFieldColumns` dengan values `$brand->custom_fields`.
- Urutan lain tidak berubah (pattern kolom dinamis per-link :212-222/:276-282 dipertahankan).

### 4.3 `brandEvents()` (:337-555)

- HAPUS: heading+value `Brand Custom Fields` dan `BrandEvent Custom Fields` (:467-473, values di :523-524 area), seluruh blok `Doc:` (query `$operationalDocs`/`$documentSubmissions` :382-394, headings :419-421, row builder :529-543), dan import `EventDocument`/`EventDocumentSubmission` (:10-11) yang jadi tak terpakai di method ini.
- `Brand Logo URL` (:428): jadi `Profile Image URL` + `Brand Logo URL` (sama seperti 4.2).
- Append dynamic PALING AKHIR: grup brand custom fields (typed), lalu grup brandEvent custom fields (untyped via `freeJsonColumns`).

### 4.4 Sheet BARU: `operationalDocuments()`

Route (routes/api.php, tempatkan setelah :915-917):

```php
Route::get('/sheets/operational-documents', [SheetsController::class, 'operationalDocuments'])
    ->middleware('throttle:60,1')
    ->name('sheets.operational-documents');
```

Token guard sama (`?token=` vs `config('services.sheets.api_token')`). **Global** (semua event aktif, seperti sheet brand-events yang selama ini memuat kolom Doc).

**Grain: 1 row per (brand_event × applicable document)**, termasuk event rules (`EventDocument::isEventRule()` :280-299) DAN operational docs; filter `appliesToBoothType($brandEvent->booth_type)`. Eager: brandEvents (brand, event), eventDocuments (fields), submissions (media, submitter) - batch per event, hindari N+1.

Urutan kolom FINAL:

```
Brand Event ID | Brand ID | Brand Name | Company Name | Event ID | Event Title | Booth Number | Booth Type
| Document ID | Document Title | Document Kind | Required | Blocks Next Step | Submission Deadline
| Status | Agreed At | Agreed Version | Current Content Version
| Submitted By | Submitted At | IP Address
| Current Files | File Versions Count | Last Upload At | Last Upload By | File History
| {dynamic: union mini-form field labels}
```

- `Document Kind` = `Event Rule` / `Operational` (via `isEventRule()`).
- `Status` = `Not Submitted` / `Completed` / `Needs Re-agreement` (dari `isSubmissionComplete()` + `needsReagreement()`).
- Lookup submission pakai key **sama dengan builder lama** :529-543: `"{event_id}|{booth_identifier}"` dengan `booth_identifier = $brandEvent->booth_number ?: 'be-'.$brandEvent->id`.
- `Current Files` = URL file current (Brief 3) join `', '`; `File Versions Count` = total media semua versi; `Last Upload At/By` dari media terbaru.
- `File History` (audit tracking, dari custom_properties versi Brief 3, BUKAN activity log): format kompak per field, contoh `Upload file: v3 booth-layout.pdf - Jane Doe, 2026-07-01 10:00 (current); v2 old.pdf - John, 2026-06-20 09:12 (superseded 2026-07-01)`, antar field join `' | '`.
- Dynamic columns: union `label` semua mini-form fields (context `document`) dari dokumen yang tampil, sort alfabetis; value dari `submission->field_values[field->ulid]` diformat `FormFieldTypes::formatValueForType`; kosong bila field bukan milik dokumen row tsb. (Checkbox agreement → Yes/No, dst.)

### 4.5 `contacts()` (:115-187)

Tidak berubah (sudah bersih). Refactor internal opsional ke `SheetFormatting::address`.

### 4.6 Out of scope

`app/Exports/BrandEventsExport.php` (Excel download admin) TIDAK diubah di brief ini, kecuali penyesuaian minimal akibat `submissionSummary` current-only (Brief 3) yang memang diinginkan.

### 4.7 Tests

`tests/Feature/Sheets/SheetsReworkTest.php` (atau pecah per sheet):
- Token salah → 401 di semua endpoint termasuk yang baru.
- `brands`: tidak ada header `Custom Fields`; field select → header = label field, value = label option (bukan raw); header `Profile Image URL` ada.
- `brandEvents`: tidak ada header mengandung `Custom Fields` maupun `Doc:`.
- `orders`: urutan header PERSIS seperti 4.1; `Adjustment Reason` terisi dari `rule_snapshot.reason`; `Country` dari address jsonb; `Source` benar.
- `operational-documents`: row per dokumen applicable (booth_types filter jalan); event rule row punya `Agreed At`; `File History` memuat 2 versi setelah re-upload (integrasi Brief 3).

---

## Brief 5 - Redesign DashboardExhibitor + live brand preview

### Latar belakang

Masalah sekarang: info event (poster, nama, tanggal, venue) duplikat per brand-event card; poster dipaksa square/thumbnail kecil (`size-9`/`size-10 object-cover`); tidak ada avatar brand; nama brand dan booth number kurang menonjol; tampilan keseluruhan kurang rapi. Selain itu form brand details butuh **real-time preview** yang meniru tampilan situs publik pmone-events (card `/brands` + halaman `/brands/[slug]`).

Keputusan user: preview ditempatkan di `pages/brands/[slug]/edit.vue` (dipakai exhibitor DAN staff); desktop side-by-side, mobile Tabs dengan trigger pill sticky/fixed (pattern editor Posts).

Referensi pattern yang SUDAH ada:
- Tabs force-mount + pill trigger: `frontend/app/components/post/PostEditor.vue` (TabsRoot reka-ui, trigger fixed `bottom-8 left-1/2 -translate-x-1/2` :21-26), `post/PostEditorContent.vue` (dua `TabsContent force-mount` :4 dan :86), `post/TabsTrigger.vue` (pill `bg-muted rounded-full border p-0.5` + TabsIndicator).
- Side-by-side sticky: `pages/brands/[slug]/order-form/[brandEventId].vue` (`grid lg:grid-cols-3`, panel kanan `sticky top-[var(--navbar-height-desktop)]`).
- Target replika (pmone-events `layers/base/app`): `components/BrandGridItem.vue` (card subgrid 4 rows: Avatar `size-16 rounded-full`, `brand_name` line-clamp-2, categories join ', ', blok booth # + first promotion thumb) dan `pages/brands/[slug].vue` (left rail sticky: Avatar `size-28→36`, `brand_name` `text-4xl→5xl font-semibold tracking-tighter`, company_name, SocialLink per `links[]` dengan icon map website/instagram/facebook/tiktok/x/linkedin/youtube/threads, facts grid booth/categories/custom fields; right: `brand_description` v-html + promotions feed).

### 5A. Dashboard: group by event

**Aturan keras Brief 5: TIDAK ADA `text-xs` (minimal `text-sm`), warna teks kontras (`text-muted-foreground` hanya untuk label sekunder, nilai pakai `text-foreground`).**

1. **`frontend/app/components/dashboard/DashboardExhibitor.vue`** (187 baris): ganti loop per brand_event (:33-104) dengan grouping per event:

```js
const eventGroups = computed(() => {
  const map = new Map();
  for (const be of dashboard.value?.brand_events ?? []) {
    if (!map.has(be.event.id)) map.set(be.event.id, { event: be.event, brandEvents: [] });
    map.get(be.event.id).brandEvents.push(be);
  }
  return [...map.values()];
});
```

Hapus pola Collapsible per brand_event lama (:48-103) berikut poster thumbnail `size-9`.

2. **Komponen baru `components/dashboard/DashboardExhibitorEventGroup.vue`** (props `event`, `brandEvents`): header event render SEKALI:
   - Poster **aspect ratio asli, tidak terpotong**: `<img :src="event.poster_image.md || event.poster_image.url" :width="event.poster_image.width" :height="event.poster_image.height" class="w-full max-w-xs rounded-xl border" />` (payload `getMediaUrls` sudah menyertakan `width`/`height`; JANGAN `object-cover` + ukuran fix).
   - Title `text-xl sm:text-2xl font-semibold tracking-tighter`, tanggal + venue `text-muted-foreground text-sm tracking-tight`, deadlines row dipertahankan dari EventCard lama.
3. **Refactor `DashboardExhibitorEventCard.vue` → `DashboardExhibitorBrandCard.vue`** (props `be`): identitas brand yang jelas:
   - `<Avatar :model="{ name: be.brand.name, profile_image: be.brand.profile_image }" class="size-14 sm:size-16" rounded="rounded-full" />` (butuh `profile_image` di payload dashboard, Brief 1 langkah 1.4).
   - Nama brand `text-lg sm:text-xl font-semibold tracking-tight`.
   - Booth number menonjol: blok `bg-muted rounded-lg px-3 py-1.5` label `Booth` + nomor `font-semibold` (pola blok booth di BrandGridItem).
   - Bila >1 brand dalam satu event → tiap brand card Collapsible (state per `brand_event_id`; ringkasan progress `eventProgress` dipertahankan di trigger). Bila 1 brand → flat tanpa collapsible.
   - `DashboardExhibitorStepper` + `DashboardExhibitorSections` tetap dirender di dalam brand card; props dan `@refresh` tidak berubah; helper `jumpTo`/`setSectionsRef`/`handleHeroAction` disesuaikan dengan struktur baru.
4. **Sapu visual seluruh keluarga komponen** `DashboardExhibitorHero / Stepper / Sections / Section / DocItem / OrderInfo`: hapus semua `text-xs` (naikkan ke `text-sm`), periksa kontras, spacing `space-y-*` konsisten STYLE_GUIDE, collapsible section rapikan (icon box `bg-muted size-8 rounded-lg`, title `font-medium tracking-tight`). Jangan ubah logika/endpoint.

### 5B. Live preview di `pages/brands/[slug]/edit.vue`

5. **Komponen preview baru** (replika manual dari pmone-events; tulis komentar di tiap file: sumber kebenaran = `~/Frontend/pmone-events/layers/base/app/components/BrandGridItem.vue` dan `pages/brands/[slug].vue`):
   - `components/brand/preview/BrandPreviewCard.vue`: replika BrandGridItem (Avatar `size-16 rounded-full`, nama `font-medium tracking-tight line-clamp-2 text-center`, categories `text-muted-foreground text-sm`, grid 2 kolom blok Booth # + thumbnail promo pertama / placeholder). Non-interaktif (`<div>`, bukan link).
   - `components/brand/preview/BrandPreviewPage.vue`: replika ringkas halaman detail (Avatar `size-24 sm:size-28 rounded-full`, `brand_name` `text-3xl sm:text-4xl font-semibold tracking-tighter`, `company_name` `text-muted-foreground`, social links icon dari `links[]` dengan icon map sama, facts grid booth/categories/custom fields, `description_html` via `v-html` - konten TipTap internal, konsisten dengan situs publik yang juga v-html, promotions section = empty state "Promotion posts will appear here").
   - `components/brand/preview/BrandLivePreview.vue`: wrapper dengan toggle kecil `Card` / `Page` (Tabs kecil) + frame `rounded-xl border bg-background p-4 overflow-hidden`.
   - Kontrak prop `preview`:

```ts
{
  brand_name: string,
  company_name: string,
  profile_image_url: string | null, // satu URL, bukan object conversions
  business_categories: string[],
  links: { label: string, url: string }[],
  booth_number: string | null,
  custom_fields: Record<string, unknown>,
  description_html: string,
  promotions: unknown[], // kosong untuk sekarang
}
```

6. **Wiring `components/brand/FormBrandProfile.vue`**: prop baru `livePreview: Boolean`; saat true, `watch` (deep) atas state form + links + custom field values + preview image URL → emit `update:previewData` dengan shape di atas (`brand_name: form.name`, `description_html: form.description`, dst).
   - **Image real-time tanpa save**: konsumsi emit `preview-url` dari `InputFileImage` (dibuat di Brief 1 langkah 1.9) - objectURL file yang baru dipilih langsung dipakai sebagai `profile_image_url`. Fallback chain: `objectUrl || brand.profile_image?.lg || brand.profile_image?.url || null`. Revoke objectURL saat ganti file / unmount.
7. **Layout `pages/brands/[slug]/edit.vue`** (saat ini `max-w-2xl`, :2):
   - Container → `mx-auto max-w-2xl lg:max-w-6xl`.
   - Bungkus konten dengan `TabsRoot` (reka-ui) `v-model="activeTab"` default `edit`; di dalamnya `div class="grid gap-6 lg:grid-cols-5"`:
     - `TabsContent value="edit" force-mount class="max-lg:data-[state=inactive]:hidden lg:col-span-3"` → `FormBrandProfile live-preview @update:preview-data="previewData = $event"` + frame Members + frame Metadata (tetap di kolom form).
     - `TabsContent value="preview" force-mount class="max-lg:data-[state=inactive]:hidden lg:col-span-2"` → `<BrandLivePreview :preview="previewData" class="lg:sticky lg:top-[var(--navbar-height-desktop)] lg:self-start" />`.
   - Desktop: kedua panel tampil side-by-side (class `max-lg:` hanya menyembunyikan di mobile), preview sticky.
   - Mobile: pill trigger baru `components/brand/preview/BrandPreviewTabsTrigger.vue` (adaptasi `components/post/TabsTrigger.vue`: pill `bg-muted rounded-full border p-0.5` + TabsIndicator, label `Edit` / `Preview`), dirender `class="lg:hidden fixed bottom-8 left-1/2 z-50 -translate-x-1/2"`. `force-mount` menjamin switch tab TIDAK me-reset isian form. (Pendekatan CSS-breakpoint ini menghindari dependensi `useSidebar().isMobile` yang dipakai PostEditor.)
   - `booth_number` untuk preview: ambil dari data brand events brand tsb bila tersedia di payload `GET /api/brands/{slug}`; jika tidak ada, tampilkan placeholder `-`.
   - Form inline admin event (`pages/projects/.../brands/[brandSlug]/index.vue`) TANPA preview (prop `livePreview` default false).

### 5C. Verifikasi manual (browser localhost:3000, JANGAN build dari terminal)

- Dashboard exhibitor skenario: 1 brand/1 event, 2 brand/1 event, 1 brand/2 event → header event muncul SEKALI per event, poster tampil utuh (aspect asli), avatar + nama + booth jelas per brand.
- Edit brand desktop: ketik nama / ubah kategori / edit description → preview berubah real-time; upload profile image → langsung tampil di preview SEBELUM save.
- Mobile (responsive mode): pill fixed bottom, switch Edit ↔ Preview tidak me-reset form.
- Dark mode: kontras cukup di dashboard dan preview.
- Grep `text-xs` di `components/dashboard/DashboardExhibitor*` harus kosong.
- Test Pest existing exhibitor dashboard tetap hijau + assert payload `brand.profile_image`.

---

## Bagian 6 - Checklist deploy production (setelah semua brief selesai)

```bash
php artisan migrate                                    # Brief 2: orders.source
php artisan permissions:sync                           # Brief 2: orders.create
php artisan brands:copy-logo-to-profile-image --force  # Brief 1 (queue worker WAJIB aktif untuk conversions)
```

- Deploy 11 situs pmone-events menyusul kapan saja (alias `brand_logo` di public API tetap berisi data avatar).
- Assign permission `orders.create` ke role staff terkait via UI roles.
- Update mapping kolom Google Sheets SEKALI setelah release sheets (urutan kolom baru per Brief 4) + tambah koneksi sheet baru `/api/sheets/operational-documents?token=...`.
- Cleanup PR terpisah setelah semua situs live: hapus alias `brand_logo` + fallback di public resources, `hasLogo()` jadi `profile_image`-only.
- JANGAN jalankan `media-library:regenerate` untuk Brand selama window transisi.
