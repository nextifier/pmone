# Style Guide

Style guide ini disusun berdasarkan pattern desain yang sudah berjalan di pmone, pmone-events, dan levenium (Nuxt 4 + Tailwind v4 + shadcn-vue). Tujuannya: setiap kali Claude membuat atau mengubah UI, hasilnya konsisten dengan konvensi yang sudah ada. Bukan menciptakan pattern baru tanpa alasan kuat.

Aturan ini wajib diikuti. Kalau ada kasus yang belum tercakup di sini, cek sibling component dulu sebelum bikin pattern baru.

Section 1-23 di file ini identik di ketiga repo, sama seperti `components/ui`. Yang berbeda hanya bagian File Reference di akhir, karena path tiap repo memang beda.

---

## 1. Typography

### Letter spacing (tracking)

- Semua teks pakai `tracking-tight` sebagai default.
- Untuk teks yang lebih besar (`text-xl` ke atas) atau yang pakai `font-semibold`, pakai `tracking-tighter`. Logikanya: makin besar atau makin tebal, makin rapat.
- Jangan pakai `tracking-wider` atau `tracking-widest`.
- Jangan pakai `uppercase`. Kalau butuh label kecil, tetap pakai case normal dengan `text-muted-foreground`.

### Ukuran teks

Ada tiga kelompok, dan memilih yang salah akan langsung kelihatan.

**Teks statis** (paragraf, deskripsi, judul, isi tabel, tooltip, kbd, shortcut) ukurannya tetap, atau membesar di layar besar.

- Default body: `text-sm` atau `text-base`.
- Lantai teks statis adalah `text-sm`, di semua lebar. `text-xs` (12px) tidak cukup untuk teks yang dibaca orang, termasuk helper text dan deskripsi section.
- `text-xs sm:text-sm` bukan jalan keluar. Tangga itu memperbaiki desktop dan meninggalkan telepon tetap di 12px, padahal telepon justru tempat halaman ini paling banyak dibaca. Tangga baru sah kalau titik awalnya sudah `text-sm`, mis. `text-sm sm:text-base`.
- `text-xs` hanya untuk kontrol dan indikator yang ukurannya memang dikunci: `.cn-button-size-xs` dan `<Badge>`. Bukan untuk teks.
- Kalau teks jadi terpotong setelah dibesarkan, pendekkan teksnya, jangan kecilkan fontnya. Contoh: label bar bayar diganti dari "N tickets selected" jadi "Total", bukan diturunkan ke `text-xs` supaya muat.
- Di bawah `text-xs` (`text-[11px]`, `text-[10px]`, `text-[9px]`) tidak boleh sama sekali untuk teks. Yang tersisa cuma indikator glyph di dalam badge (step number, key indicator) dan compact data tile - keduanya terdaftar di pengecualian §22, jangan menambah kasus baru di luar itu.

**Kontrol interaktif** (button, select, tab, toggle, item menu, label) ukurannya **tetap**: `text-sm`, sama di telepon dan di desktop. Tangga `text-base sm:text-sm` pernah dipakai mengikuti coss.com/ui, lalu dibuang karena 16px terlihat terlalu besar di telepon, paling kentara di sel kalender yang lebarnya cuma 28px. Jangan menambahkan step mobile lagi, dan jangan pula mengembalikan `sm:text-base` ke rule `cn-*` mana pun. Yang tetap responsif cuma field ketik, alasannya di bawah.

#### Skala button (dimiliki style, jangan di-override di call site)

Semua `style-*.css` memakai skala yang sama. Yang berbeda antar style cuma tinggi, padding, radius, dan ukuran ikon.

| size | font-size |
| --- | --- |
| `xs` | `text-xs` |
| `sm` | mewarisi `.cn-button`, tidak punya override sendiri |
| `default`, `lg` | `text-sm` (diwarisi dari `.cn-button`) |
| `xl` | `text-base` |

#### Field: 16px saat disentuh, 14px saat pakai mouse

`.cn-input`, `.cn-textarea`, `.cn-native-select`, `.cn-input-otp-slot`, `.cn-command-input`, dan `.cn-combobox-chips` memakai `text-base pointer-fine:text-sm`.

Kenapa `pointer-fine:` dan bukan `sm:`: iOS Safari melakukan zoom-on-focus setiap kali font field di bawah 16px, dan itu tetap terjadi di iPad maupun iPhone landscape yang lebarnya sudah lewat breakpoint `sm` (640px). `pointer-fine` cuma menyala di perangkat bermouse, jadi 16px bertahan di semua perangkat sentuh. Jangan menggantinya dengan `sm:text-sm`.

`.cn-select-trigger` ikut aturan yang sama persis, walaupun ia bukan field ketik. Bukan karena zoom (ia tombol, tidak pernah memicu zoom), tapi karena ia berdiri sebaris dengan `.cn-input` di form yang sama: kalau nilai Select 14px sementara nilai di sebelahnya 16px, barisnya kelihatan timpang di telepon. Varian `data-[size=sm]` sengaja tidak meng-override font.

#### Tangga tinggi dan ikon

Tiap kontrol punya dua tinggi: mobile satu step (4px) di atas desktop. Nilai desktop milik style dan tidak berubah, jadi layout desktop tidak pernah bergeser.

| style | default (mobile / desktop) |
| --- | --- |
| `mono`, `nova`, `lyra`, `rhea` | 36px / 32px |
| `vega`, `maia`, `luma` | 40px / 36px |
| `mira` | 32px / 28px |
| `sera` | 44px / 40px |

`--cn-input-h` mengikuti tangga yang sama lewat `@media (width >= 40rem)` di dalam blok `.style-X`, jadi `h-(--cn-input-h)` di call site ikut otomatis tanpa perubahan apa pun.

Ikon di dalam kontrol naik setengah step (2px) di mobile: `[&_svg:not([class*=size-])]:size-4.5 sm:[…]:size-4`. Checkbox, radio, dan slider thumb juga setengah step. Badge naik satu step penuh (`h-6 sm:h-5`), tapi fontnya tidak ikut, `text-xs` rata seperti kontrol lain.

### Hierarchy

Judul halaman punya dua tingkat. Pilih berdasarkan jenis halamannya, jangan dicampur dalam satu bagian aplikasi:

- **Halaman kerja** (form, detail, panel, dashboard): utility `page-title` + `page-description`. Jangan bikin styling manual, dan jangan menyalin nilai `@apply`-nya ke dalam class - nilainya sedikit berbeda antar repo, jadi yang mengikat adalah nama utility-nya. Nilai persis di repo ini ada di File Reference.
- **Halaman hero** (landing, marketing, dokumentasi, showcase): judul `text-4xl font-medium tracking-tighter sm:text-5xl` dengan deskripsi `text-muted-foreground max-w-3xl text-base tracking-tight text-pretty sm:text-lg`. Ukuran ini yang membuat halaman publik terasa lapang; memakai `page-title` di sini akan terlihat kekecilan dibanding halaman sebelahnya.

Sisanya:

- Section title di dalam frame: otomatis dari class `.frame-title`. Jangan di-override; nilainya berbeda antar repo.
- Card title biasa: `text-lg font-medium tracking-tighter`.
- Judul section di dalam panel: `text-base font-medium tracking-tight`. Jangan `text-sm`, karena jadi sama besar dengan body di sekitarnya dan hierarkinya hilang.
- Body teks panjang: `text-sm tracking-tight` atau `text-base tracking-tight`.
- Helper text di bawah input: `text-muted-foreground text-sm`.
- Label form: pakai component `<Label>`. Stylingnya sudah `text-sm leading-none font-medium tracking-tight` dari base layer.

### Font weight

- Default body: regular (400).
- Highlight ringan (label, link kecil): `font-medium`.
- Emphasis kuat (section title, card title): `font-semibold`.
- Button: semua variant `font-medium`, sudah dari style aktif. Jangan menaikkan ke `font-semibold` di call site.
- Jangan pakai `font-bold` atau `font-extrabold`. Kalau merasa perlu kontras lebih, naikkan ukuran atau warna, bukan ketebalan.

### Warna teks

- Default: `text-foreground`.
- Teks sekunder, helper, caption, label di samping value: `text-muted-foreground`.
- Link / emphasis: `text-primary`.
- Error / delete: `text-destructive-foreground`. Untuk pesan error di bawah input jangan menulis warnanya sendiri - `<FieldError>` sudah membawa `text-destructive-foreground` lewat rule `cn-field-error` milik style.

---

## 2. Warna (CSS Variables)

Wajib pakai CSS variable, bukan warna Tailwind generik.

- `bg-background`, `bg-card`, `bg-popover` untuk surface utama.
- `bg-muted` untuk surface sekunder, hover state, container icon, badge ringan.
- `bg-muted/50` untuk overlay tipis (lihat `.frame`).
- `bg-primary` + `text-primary-foreground` untuk CTA utama.
- `bg-secondary` + `text-secondary-foreground` untuk button sekunder.
- `bg-destructive` + `text-white` untuk tombol delete. Versi soft: `bg-destructive/10 text-destructive-foreground`.
- **`--destructive` hanya untuk background.** Semua warna teks, ikon, border, dan ring yang bersifat error pakai `--destructive-foreground`: `text-destructive-foreground`, `aria-invalid:border-destructive-foreground`, `aria-invalid:ring-destructive-foreground/20`. Satu-satunya `border-destructive` yang tersisa ada di button destructive solid (style mono), karena border-nya menyatu dengan background.
- `bg-success`, `bg-warning`, `bg-info` untuk status badge. Versi soft: `bg-success/10 text-success-foreground` dst.

Jangan pakai:

- `bg-green-*`, `bg-red-*`, `bg-yellow-*`, `bg-blue-*` literal. Ganti ke `success`, `destructive`, `warning`, `info`.
- `bg-white`, `bg-black` literal. Ganti ke `bg-background` atau `bg-foreground`.
- `border-gray-*` literal. Ganti ke `border-border`.
- Warna custom hex (`#fff`, `#000`) kecuali memang ada kebutuhan khusus.

Border:

- Default: `border` (otomatis ambil `border-border` via base layer).
- Input border: `border-input`.

Kalau menyalin kelas dari coss.com/ui, **jangan bawa `accent` apa adanya**. Di coss `--accent` itu abu tipis (`black/4%`), di pmone isinya `var(--color-primary)` alias hitam pekat. `bg-accent` coss padanannya `bg-muted` di sini. Tokennya juga tidak boleh diubah jadi netral: mira, sera, dan lyra memakai `bg-accent` + `text-accent-foreground` untuk item menu yang di-highlight, dan blok hitam itu memang identitas mereka. `bg-popover` dan `border-input` aman disalin apa adanya, nilainya sama persis dengan `bg-background` dan `border-border` di pmone.

---

## 3. Border Radius

- `rounded-md` untuk input, button kecil, badge.
- `rounded-lg` untuk button standard, popover item, dialog button area.
- `rounded-xl` untuk card, panel, frame.
- `rounded-2xl` untuk container besar / hero.
- `rounded-full` untuk avatar bulat, indikator, chip kecil.
- `squircle` (utility custom di `main.css`) untuk logo, app icon. Pakai ini, jangan bikin shape manual.

Jangan pakai `rounded-sm` kecuali untuk avatar mini atau elemen yang memang harus tegas.

---

## 4. Shadow

Shadow di app ini minimal. Depth dibangun dari border + spacing, bukan shadow tebal.

- `shadow-xs` untuk button default dan input fokus.
- `shadow-sm` untuk card kecil.
- `shadow-lg` khusus tooltip, popover, dialog content.
- Hindari `shadow-2xl` kecuali memang hero image atau elemen yang sengaja diangkat tinggi.

---

## 5. Spacing

### Vertical (`space-y-*`)

- `space-y-2` untuk wrapper Label + Input.
- `space-y-4` untuk grup field dalam satu section.
- `space-y-6` untuk antar section dalam page.
- `space-y-8` untuk gap antar frame di form besar.
- `space-y-16` atau `space-y-24` hanya untuk page-level section di landing / marketing.

### Horizontal & gap

- `gap-x-1` atau `gap-x-1.5` untuk icon + text di dalam button kecil.
- `gap-x-2` standar untuk grid form 2 kolom dan elemen header. Wajib `gap-x-2` di grid form, jangan lebih besar.
- `gap-x-2.5` untuk header dengan icon di sebelah judul.
- `gap-4` untuk grup item di card.
- `gap-6` untuk grid card.

### Padding

- Padding button dimiliki style aktif lewat `.cn-button-size-*`, dan berbeda per style (`px-2.5` di mono, `px-3` di rhea, `px-6` di sera). Jangan hardcode `px-*` / `py-*` di call site button.
- Card / panel: `p-4 sm:p-5` untuk compact, `p-6` standar.
- Empty state: `p-6 md:p-12`.
- Container halaman: pakai utility `container` (sudah `mx-auto px-4`). Untuk halaman lebar pakai `container-wider`.

---

## 6. Grid Layout di Form

- Default form: satu kolom, `grid grid-cols-1 gap-y-6`.
- Saat butuh dua kolom (misal first name + last name, city + country): `grid grid-cols-2 gap-x-2 gap-y-6`. Wajib `gap-x-2`, bukan `gap-x-4` atau lebih besar.
- Untuk responsive dua kolom yang collapse di mobile: `grid grid-cols-1 sm:grid-cols-2 gap-x-2 gap-y-6`.
- Setiap field tetap pakai wrapper `<div class="space-y-2">` untuk jarak Label ke Input.

---

## 7. Form Components

### Wajib pakai component shadcn-vue

Semua input harus pakai component dari `frontend/app/components/ui/`. Jangan pakai elemen native.

- `<Button>` bukan `<button>`.
- `<Input>` bukan `<input>`.
- `<Textarea>` bukan `<textarea>`.
- `<Label>` bukan `<label>`.
- `<Select>` + `<SelectTrigger>` + `<SelectContent>` + `<SelectItem>` bukan `<select>`.
- `<Checkbox>` bukan `<input type="checkbox">`.
- `<RadioGroup>` + `<RadioGroupItem>` bukan `<input type="radio">`.
- `<Switch>` bukan toggle manual.
- `<Combobox>` untuk select dengan search. Untuk pilih banyak, pakai `<Combobox multiple>` dengan chips - `MultiSelect` sudah dihapus.

### Custom input wajib

- Phone: pakai `<InputPhone>` (`components/ui/input-phone/`). Sudah ada country selector built-in.
- Link / URL / social media: pakai `<InputLink>` (`components/ui/input-link/`). Auto prefix Instagram, Facebook, LinkedIn, dll.
- Password: pakai `<InputPassword>` (`components/ui/input-password/`).
- OTP / PIN: pakai `<InputOtp>`.
- Date: pakai `<DatePicker>` (single/range, opsional `with-time`, presets). Semua picker keluarga tanggal ada di `components/ui/date-picker/`.
- Time: pakai `<TimePicker>` atau `<TimeRangePicker>`.
- Bulan / Tahun: pakai `<MonthPicker>`, `<MonthRangePicker>`, `<YearPicker>`, atau `<YearRangePicker>`.
- File: pakai `<InputFile>` atau `<InputFileImage>` (untuk gambar dengan preview + delete + undo).
- Tags / labels: pakai `<TagsInput>`, ikut pattern tag yang sudah ada di repo masing-masing.
- Rich text: pakai `<TipTapEditor>`.

### Struktur field standar

```vue
<div class="space-y-2">
  <Label for="name">Field Label</Label>
  <Input id="name" v-model="form.name" required />
  <p class="text-muted-foreground text-sm">Helper text opsional.</p>
  <FieldError :errors="errors.name" />
</div>
```

- Label wajib pakai `for` yang match dengan `id` input.
- Required field: cukup pasang attribute `required`. Asterisk merah otomatis muncul via CSS selector `label:has(+ input:required)::after` di `main.css`. Jangan pernah menambah `<span class="text-destructive-foreground">*</span>` manual.
- Error message: pakai `<FieldError :errors="errors.field" />` (`components/ui/field/`). Jangan render manual, dan jangan pakai `<p>` biasa - `FieldError` sudah membawa `role="alert"`.
- Helper text pakai `text-muted-foreground text-sm`, posisi di bawah input sebelum error message.

### Form section

Untuk form besar, bungkus tiap section dalam `.frame`:

```vue
<div class="frame">
  <div class="frame-header">
    <div class="frame-title">Section Title</div>
    <div class="frame-description">Deskripsi singkat.</div>
  </div>
  <div class="frame-panel">
    <!-- field di sini -->
  </div>
  <div class="frame-footer">
    <!-- opsional -->
  </div>
</div>
```

Class `frame`, `frame-header`, `frame-title`, `frame-description`, `frame-panel`, `frame-footer` sudah ada di `main.css`. Jangan bikin styling card alternatif untuk section form.

### Submit button

- Posisi: di header page, sejajar dengan back button atau page title. Bukan di bawah form.
- Loading state: tampilkan `<Spinner />` di dalam button + disable button.

```vue
<Button :disabled="loading" @click="handleSubmit">
  <Spinner v-if="loading" />
  <span>Save</span>
</Button>
```

---

## 8. Button

### Variant

- `default` (primary, hitam): CTA utama. Save, Submit, Continue.
- `secondary` (abu muda): aksi pendukung.
- `outline` (border tipis): aksi sekunder yang tetap visible. Tombol toolbar, Cancel di dialog.
- `outline-destructive`: cancel di konteks delete.
- `ghost`: aksi yang tidak boleh menarik perhatian. Toggle sidebar, dropdown trigger di toolbar.
- `destructive`: tombol delete / hapus permanen.
- `link`: text-only, underline on hover. Hindari kecuali memang link di tengah paragraf.

### Size

Tinggi setiap size dimiliki style aktif, jadi jangan menghafal angkanya. Di style default (`mono`) urutan desktopnya `xs` 24px, `sm` 28px, `default` 32px, `lg` 36px, `xl` 44px, dan tiap size 4px lebih tinggi di mobile. Di `sera` semuanya lebih besar, di `mira` lebih kecil. Pilih berdasarkan peran, bukan piksel.

- `xs`: badge-like action di dalam baris padat.
- `sm`: default untuk toolbar, header halaman, dan aksi di dalam row table. Ini yang paling sering dipakai.
- `default`: form dan panel.
- `lg` / `xl`: CTA besar di hero atau form panjang.
- `icon` / `iconSm` / `iconXs` / `iconLg`: tombol icon-only. Wajib `<Tippy>` atau `aria-label`.

Kalau tombol harus sejajar dengan field di baris yang sama (misal di samping search input), pakai `size="sm"` plus `class="h-(--cn-input-h)"`. Variabel itu dideklarasikan tiap style dan bernilai sama dengan tinggi `.cn-input`, jadi barisnya rata di semua style tanpa hardcode `h-8`. Ia sendiri sudah responsif (mobile satu step di atas desktop), jadi jangan menambahkan `sm:h-*` di sebelahnya.

### Icon + text

- Gap antara icon dan label dimiliki style (`gap-1` di size kecil, `gap-1.5` di size normal).
- Icon size auto `size-4` kalau tidak ditentukan.
- Pattern: `<Button><Icon name="hugeicons:add-01" /> Add</Button>`.

### Loading

Pakai prop `loading`, jangan menaruh `<Spinner>` sendiri sebagai anak tombol:

```vue
<Button type="submit" :loading="saving">Save</Button>
```

Tombolnya otomatis disabled, labelnya disembunyikan tanpa mengubah lebar, dan spinner muncul di tengah dengan warna yang mengikuti variant. Prop `loading` tidak bekerja bersama `as-child` - mode itu menyerahkan satu-satunya anak slot ke `Primitive`, jadi pembungkusnya akan jadi tombol itu sendiri.

### Tombol toolbar

Jangan menulis ulang tombol toolbar sebagai `<button>` dengan class border sendiri. Yang benar:

```vue
<Button variant="outline" size="sm" to="/posts/trash">
  <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
  <span>Trash</span>
</Button>
```

`<Button>` menerima `to` dan otomatis merender `NuxtLink` (plus `target`/`rel` kalau URL-nya eksternal), jadi tidak perlu `<nuxt-link>` terpisah.

Untuk tombol filter di `<TableData>`, pakai `<TableFilterButton>` - komponennya sudah membawa `PopoverTrigger`, badge counter, dan versi kotak untuk mobile:

```vue
<template #filters>
  <Popover>
    <TableFilterButton :count="totalActiveFilters" />
    <PopoverContent align="end">…</PopoverContent>
  </Popover>
</template>
```

`TableFilterButton` untuk sekarang baru ada di pmone. Repo lain memakai `PopoverTrigger` biasa sampai komponennya ikut disinkronkan.

---

## 9. Card / Panel

- Untuk konten umum (bukan form section): pakai `<Card>` + `<CardHeader>` + `<CardContent>` + `<CardFooter>`.
- Untuk section di dalam form: pakai `.frame` (lihat bagian Form Section).
- Kalau cuma butuh kotak ringan, bisa pakai `bg-card border rounded-xl shadow-sm p-4 sm:p-5`.
- Padding card default: `p-6`. Compact: `p-4 sm:p-5`.

---

## 10. Dialog / Modal

- Wajib pakai `<ResponsiveDialog>` (`components/ui/responsive-dialog/`). Component ini render Dialog di desktop dan Drawer di mobile.
- Jangan pakai `confirm()` native browser. Untuk konfirmasi delete, pakai `<ResponsiveDialog>` dengan footer dua tombol:
  - Cancel: `variant="outline"`.
  - Delete: `variant="destructive"`.
- Title dialog: `text-lg font-semibold tracking-tighter`.
- Footer alignment: `flex justify-end gap-2`.
- Default max width: 400px (sudah dari component). Untuk dialog form besar, override via prop `dialogMaxWidth`.

### Padding body

`ResponsiveDialog` sengaja **tidak** memberi padding apa pun, supaya body bisa dibuat full-bleed
(gambar menempel ke tepi kiri-kanan viewport). Paddingnya milik wrapper pertama di dalam slot
default, dan nilainya baku:

```
px-4 pt-5 pb-8 md:px-6 md:py-5
```

- Drawer (< 768px): atas 20px, bawah 32px. Jangan `pt-0`. Grabber drawer setinggi 28px, `absolute`,
  dan popup cuma memesan 8px untuknya, jadi tanpa `pt-5` judul akan tertimpa pill.
- Dialog (>= 768px): `md:py-5` menyamakan atas dan bawah jadi 20px, supaya konten terlihat
  vertically centered.
- Breakpoint wajib `md:`, bukan `sm:`. Pergantian Drawer -> Dialog terjadi di `min-width: 768px`
  (`ResponsiveDialog.vue`, `useMediaQuery`), jadi `sm:` akan memakai padding dialog padahal masih
  drawer.
- Safe-area bawah sudah ditangani popup drawer. Jangan tambahkan `env(safe-area-inset-bottom)` lagi.
- Full-bleed: cukup jangan pasang padding di wrapper, atau pasang `p-0`.

Kalau memakai slot `#sticky-header`, string bakunya:

```
border-border sticky top-0 z-10 border-b px-4 pt-5 pb-2 text-center md:px-6 md:py-3.5 md:text-left
```

Jangan pakai `-mt-4` untuk "membetulkan" jarak atas di drawer. Itu justru mendorong header menembus
area grabber.

Jangan pula membungkus isi dialog dalam `<form>` yang men-submit halaman di belakangnya.

---

## 11. Table

- Pakai `<TableData>` (`components/ui/table-data/`) untuk list page yang butuh search + filter + sort + pagination. Jangan rakit table dari nol kalau use case-nya cocok.
- Untuk table statis sederhana: `<Table>` + `<TableHeader>` + `<TableRow>` + `<TableCell>`.
- Row action: pakai `<DropdownMenu>` dengan trigger `<Button variant="ghost" size="iconSm">`.
- Delete action di dropdown wajib buka `<ResponsiveDialog>` konfirmasi, tidak langsung delete.

---

## 12. Icon

- Library utama: `hugeicons:*`. Pakai ini sebagai default.
- `lucide:*` boleh untuk icon yang tidak ada di hugeicons.
- Hindari mix banyak library di satu page.
- Ukuran:
  - `size-3` untuk inline mini.
  - `size-4` default (di button, di list item).
  - `size-5` untuk header section.
  - `size-6` untuk header page atau empty state.
  - `size-8` untuk avatar mini box atau empty state besar.
- Icon dalam box: `bg-muted size-8 flex items-center justify-center rounded-lg`.
- Icon empty state: `bg-muted size-12 flex items-center justify-center rounded-full` dengan icon `size-6`.

Hindari:

- `hugeicons:handshake` (tidak valid). Pakai `hugeicons:agreement-02`.

---

## 13. Hover, Focus, Transition

- Hover background default: `hover:bg-muted`. Pakai ini untuk row, list item, ghost button.
- Hover background primary: `hover:bg-primary/90` (sudah dari Button component).
- Hover text destructive: `hover:text-destructive-foreground`.
- Transition: `transition` atau `transition-colors` untuk perubahan warna. `transition-all` hanya kalau memang banyak property berubah.
- Active state: `active:scale-98` untuk tombol yang ditekan (sudah jadi pattern di app).
- **Jangan menambahkan hover-zoom pada image atau card.** Ini aturan untuk kode yang sedang kamu tulis, bukan untuk mengaudit kode yang sudah ada. Menempelkan `hover:scale-105` / `group-hover:scale-105` plus `transition-transform` ke setiap thumbnail, foto, dan tile adalah refleks yang membuat UI terasa templated, dan itu yang aturan ini cegah. Affordance untuk card yang bisa diklik cukup dari perubahan warna atau border. Kalau kamu menambahkannya karena "biar tidak flat", hapus. Catatan: `active:scale-98` pada tombol yang ditekan tetap boleh - itu feedback klik, bukan dekorasi hover.
- **Motion yang sudah ada di repo bukan temuan.** Kalau sebuah komponen sudah memakai hover-zoom, tilt, atau transform lain, anggap itu keputusan desain yang diambil sadar. Jangan melaporkannya sebagai pelanggaran, jangan "membereskannya" sambil lalu, dan jangan memasukkannya ke daftar audit. Ubah hanya kalau memang diminta. Kalau kamu ragu apakah sesuatu disengaja, tanyakan alih-alih menghapusnya.
- **Transition untuk perubahan yang berarti, bukan untuk hiasan.** Perubahan state (buka/tutup, terpilih, valid/invalid, memuat/selesai) layak dianimasikan. Menganimasikan `transform` semata-mata supaya UI terasa "hidup" tidak. Bedanya ada pada apakah gerakannya menjelaskan sesuatu yang berubah, bukan pada properti CSS mana yang dipakai.
- Focus ring: sudah otomatis via base layer (`focus-visible:ring-ring`). Jangan override kecuali memang perlu.

---

## 14. Empty State

Pakai component family `<Empty>` + `<EmptyMedia>` + `<EmptyHeader>` + `<EmptyContent>`.

```vue
<Empty class="border-dashed">
  <EmptyMedia>
    <Icon name="hugeicons:inbox" class="size-6" />
  </EmptyMedia>
  <EmptyHeader>Belum ada data</EmptyHeader>
  <EmptyContent>Tambahkan item pertama kamu untuk mulai.</EmptyContent>
  <Button>Tambah</Button>
</Empty>
```

Padding default `p-6 md:p-12`. Container icon `bg-muted size-12 rounded-full`. Border `border-dashed` untuk indikasi placeholder.

---

## 15. Avatar

- Pakai `<Avatar>` untuk semua representasi entitas (user, brand, project, event, organisasi).
- Fallback otomatis: inisial dari nama + mesh gradient.
- Ukuran umum: `size-7` (breadcrumb), `size-8` (sidebar collapsed), `size-10` (sidebar normal), `size-12` (card header), `size-20` (profile page).
- Bentuk: `rounded-full` default untuk user, `squircle` untuk brand / project / app icon, `rounded-lg` untuk thumbnail kotak.
- Indicator status: prop `indicator="success|info|warning|destructive|primary"`.

---

## 16. Badge

**WAJIB DIPATUHI: setiap elemen berbentuk badge / pill / chip / tag / status WAJIB memakai komponen `<Badge>`. JANGAN PERNAH membuat `<span>` atau `<div>` dengan class pill manual** (mis. `rounded-full`/`rounded-md` + `px-* py-*` + `bg-*` + `text-xs`). Ini kesalahan yang SANGAT SERING terjadi - sebelum menulis pill manual, selalu cek dulu apakah cukup pakai `<Badge>` (hampir selalu cukup).

Import: `import { Badge } from "@/components/ui/badge";`

Props:

- `variant`: `default` | `info` | `success` | `warning` | `destructive` | `muted` | `outline`. Pilih sesuai makna status; pakai token semantik, jangan warna literal.
- `icon`: nama icon **hugeicons** untuk custom icon, mis. `icon="hugeicons:checkmark-circle-02"`. **Selalu hugeicons** (nama valid; cek dulu kalau ragu).
- `withIcon`: pakai icon default bawaan variant.
- `plain`: hanya teks, tanpa dot/icon.
- Tanpa `icon`/`plain` -> otomatis muncul colored dot sesuai variant.

Contoh benar:

```vue
<Badge variant="success" icon="hugeicons:checkmark-circle-02">Checked in</Badge>
<Badge variant="info">Entry</Badge>            <!-- dot otomatis -->
<Badge variant="muted" plain>Draft</Badge>
<Badge variant="warning" plain>3/100</Badge>   <!-- count chip -->
```

Contoh SALAH (pill manual - jangan):

```vue
<span class="bg-success/10 text-success-foreground rounded-full border px-2 py-0.5 text-xs">Active</span>
```

Ganti menjadi `<Badge variant="success" plain>Active</Badge>` (atau dengan `icon="hugeicons:..."`).

---

## 17. Breadcrumb & Page Header

- Untuk page nested (di dalam project, event, brand): pakai `<HeaderBreadcrumb>`.
- Untuk standalone page: header sederhana dengan icon + judul kiri, tombol aksi kanan.

```vue
<div class="flex flex-col gap-y-4 sm:flex-row sm:items-center sm:justify-between">
  <div class="flex items-center gap-x-2.5">
    <Icon name="hugeicons:..." class="size-5 sm:size-6" />
    <h1 class="page-title">Judul Halaman</h1>
  </div>
  <div class="ml-auto flex gap-2">
    <Button>Aksi</Button>
  </div>
</div>
```

---

## 18. Page Layout

- Layout dasar pakai `app.vue` (Sidebar + Header + main).
- Container halaman:
  - Form / edit page sempit: `mx-auto max-w-xl space-y-6 pt-4 pb-16`.
  - List page lebar: `mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl`.
  - Halaman penuh: pakai utility `container` atau `container-wider`.
- Min height: `min-h-screen-offset` (custom utility, sudah menghitung navbar).
- Sticky header section: `sticky top-0 z-10 bg-background` di dalam page (header utama sudah sticky dari layout).

### 18.1 Edit + preview dua panel (`<PreviewPanel>`)

Halaman yang mengedit sesuatu yang punya hasil terlihat (form builder, brand
editor, link page, announcement) memakai `components/ui/preview-panel`. Jangan
bikin grid + Tabs sendiri lagi.

```vue
<PreviewPanel v-model:tab="activeTab" ratio="3:2" preview-title="Brand page" reloadable>
  <template #edit><div class="mx-auto w-full max-w-2xl space-y-6">…</div></template>
  <template #preview><BrandPreviewPage :preview="previewData" class="p-5 sm:p-6" /></template>
  <template #footer>…save bar…</template>
</PreviewPanel>
```

Aturannya:

- **Split dipicu container query, bukan breakpoint viewport.** Sidebar 18rem vs
  3rem menggeser lebar konten 240px, dan media query tidak melihat itu. Default
  threshold `5xl` (panel ≥1024px).
- **Cuma panel preview yang `sticky`.** Panel editor scroll normal ikut body,
  tanpa border, padding, atau shadow tambahan. Dua pendekatan sebelumnya sudah
  dicoba dan dibuang: mengunci viewport bikin page header nempel permanen dan
  memakan tinggi panel, sementara dua-duanya sticky bikin editor tidak bisa
  di-scroll pakai body.
- **Halaman kirim `offset`, component tidak tahu tinggi navbar.** Misal
  `offset="calc(var(--navbar-height-desktop) + 1rem)"`, tambah `--tabnav-height`
  kalau di bawah TabNav yang sticky. Ini yang bikin component-nya sama di tiga
  repo dengan tiga header berbeda.
- **JANGAN taruh padding bawah di halaman yang memuat panel.** Sticky berhenti
  menempel begitu containing block-nya habis, jadi `pb-16` di halaman + 1rem
  `insetBottom` = preview terdorong 3rem ke atas dan bagian atasnya masuk ke
  balik header saat di-scroll mentok bawah. Padding taruh di **dalam** slot
  `#edit`. Tidak bisa diperbaiki dari dalam component: padding-nya milik
  ancestor yang tidak kelihatan dari sana.
- **`min-h-0` dan `min-w-0` di tiap level flex.** Tanpa `min-h-0` panel tidak
  akan pernah scroll; tanpa `min-w-0` tabel panjang jebol dan terpotong diam-diam
  oleh `overflow-x-clip` milik layout.
- **`container-type: inline-size` TIDAK menangkap `position: fixed`.** Ini
  pernah diasumsikan sebaliknya di sini dan salah — sudah dibuktikan pakai probe:
  element fixed tetap anchor ke viewport dan computed `contain` terbaca `none`.
  Jadi pill Edit/Preview memakai `fixed` biasa, sama seperti pane switcher lain.
  Konsekuensinya: container query TIDAK bisa menyembunyikan element fixed
  langsung (query resolve lewat containing-block chain, bukan DOM tree) —
  bungkus dengan element statis yang bawa query-nya, lalu turunkan lewat
  `visibility` yang sifatnya inherited. JANGAN `transition-[visibility]`, CSS
  menginterpolasi visibility sebagai step function dan nilainya terkunci di
  `visible`.
- **`TabNav :sticky="false"` tidak boleh menambah class `static`.** Nav-nya sudah
  punya `relative` di base class, dan `static` masuk grup position yang sama lalu
  menang di cascade — indicator yang `absolute` kehilangan offsetParent dan
  hilang dari nav. Biarkan branch falsy-nya kosong.
- Jangan pakai `scroll-fade-b` di dalam panel: mask-nya digerakkan
  `animation-timeline: scroll(self y)` dengan `fill-mode: both`, jadi scroller
  yang tidak punya jarak scroll akan terkunci di keyframe 0% dan fade-nya tidak
  pernah hilang. Pakai prop `fade` (gradien absolut) kalau perlu.

---

## 19. Skeleton & Loading

- Pakai `<Skeleton>` (`components/ui/skeleton/`) untuk placeholder loading. Jangan bikin div abu manual.
- Animasi sudah otomatis (`animate-skeleton`).
- Untuk loading button: `<Spinner />` di dalam button + `:disabled="loading"`.

---

## 20. Notifikasi (Toast)

- Pakai sonner via `useSonner` / `toast()`. Jangan bikin notifikasi custom di pojok layar.
- Variant: `toast.success()`, `toast.error()`, `toast.warning()`, `toast.info()`.
- Title pendek, description optional dan faktual.
- Bahasa: ikuti bahasa UI repo tempat kamu bekerja. pmone Staff Dashboard dan levenium memakai literal English; pmone-events, Dashboard Exhibitor, dan `components/ui` memakai i18n. Jangan mencampur dua bahasa dalam satu layar.

---

## 21. Tooltip

- Pakai `v-tippy` directive yang sudah terpasang global.
- Pattern: `<Button v-tippy="'Label tooltip'">...</Button>`.
- Untuk konten kompleks, pakai `<Tippy>` component dengan slot `#content`.
- Hindari title attribute native (`title="..."`) karena style tidak konsisten.

---

## 22. Hal yang Wajib Dihindari

- `text-xs` **pada teks statis**, di lebar mana pun. Lantainya `text-sm` (§1), dan `text-xs sm:text-sm` tidak dihitung sebagai perbaikan karena hasilnya tetap 12px di telepon. Aturan ini tidak berlaku untuk kontrol interaktif: ukurannya dikunci sama di semua lebar (§1), jadi `text-xs` di `.cn-button-size-xs` dan di `<Badge>` memang benar. Kode lama yang masih 12px bukan temuan audit; naikkan saat file itu memang sedang disentuh.
- `sm:text-*` apa pun di rule `cn-*`. Kontrol tidak lagi punya step ukuran mobile; satu-satunya yang masih responsif adalah field ketik, lewat `pointer-fine:`.
- Ukuran di bawah `text-xs` di mana pun. Satu-satunya pengecualian adalah compact data tile (lihat daftar pengecualian di bawah), dan itu pun hanya di belakang prefix breakpoint.
- `text-xs` untuk konten primer: nilai, harga, hasil, isi tabel, pesan error, pesan validasi, tanggal atau sesi yang dipilih user, blok kode. Patokannya bukan panjang teksnya: kalau user harus membacanya untuk memutuskan atau memverifikasi sesuatu, itu konten primer.
- Input / textarea di bawah 16px pada perangkat sentuh - memicu auto-zoom iOS. Pakai `text-base pointer-fine:text-sm`, bukan `sm:text-sm`.
- Gradient text dan nested card.
- `border-left: 3px solid` sebagai accent stripe.
- `font-bold`, `font-extrabold`. Maksimum `font-semibold`.
- `uppercase`, `tracking-wider`, `tracking-widest`.
- `bg-green-*`, `bg-red-*`, `bg-yellow-*`, `bg-blue-*` literal.
- Native element `<button>`, `<input>`, `<select>`, `<textarea>`. Tombol toolbar dengan class border sendiri (`border-border hover:bg-muted ... rounded-md border px-2 py-1 text-sm`) termasuk di sini - pakai `<Button variant="outline" size="sm">`, dan `<TableFilterButton>` untuk tombol filter.
- Memanggil `buttonVariants()` tanpa argumen `size`. Diam-diam jatuh ke `size-default`, sehingga tombol jadi lebih besar dari tetangganya. Selalu sebutkan size-nya.
- `confirm()` browser. Selalu pakai `<ResponsiveDialog>`.
- Pattern card / form dari nol kalau sudah ada `.frame` atau `<Card>`.
- Em-dash (—) di teks UI. Pakai dash biasa (-) atau koma.
- Gap form yang lebih besar dari `gap-x-2` saat grid dua kolom.
- Banyak library icon di satu page.
- Shadow tebal (`shadow-2xl`) di komponen biasa.
- Border radius yang tidak konsisten dengan skala (jangan tiba-tiba `rounded-3xl` di satu card sedang yang lain `rounded-xl`).
- Menambahkan `hover:scale-*` / `group-hover:scale-*` pada image atau card di kode baru. Untuk motion yang sudah ada di repo, lihat §13 - itu bukan temuan audit.
- Menempel `bg-*`, `border-*`, `h-*`, `rounded-*`, `px-*`, `shadow-*` di call site elemen input-like (input, textarea, select trigger, combobox, chips, dropzone). Semua itu milik rule `cn-*` di `assets/css/styles/style-<name>.css`. Meng-hardcode-nya memaku field ke satu tampilan sehingga ia tidak ikut berganti saat user memilih Style lain - dan `dark:bg-background` khususnya membuat field lebur ke latar dialog di dark mode. Kalau butuh nilai yang belum ada, tambahkan rule `cn-*`-nya (ingat: 9 file style x 3 repo), jangan hardcode. Guard di pmone: `bash frontend/scripts/check-input-hardcode.sh`.

### Pengecualian yang diizinkan

Empat kasus di bawah ini melanggar daftar di atas dan tetap boleh, karena aturannya memang tidak dirancang untuk bentuk ini. Di luar keempatnya, tidak ada pengecualian. Tandai di kode dengan komentar `style-guide: <nama kategori>` supaya audit bisa membedakannya dari kelalaian.

1. **Painted surface control** - kontrol yang seluruh permukaannya adalah datanya sendiri: swatch warna, tile preview tema. Boleh `<button type="button">` native, karena `<Button>` membawa background, padding, dan geometri yang justru harus dibuang seluruhnya. Syarat: `type="button"`, `aria-label` yang menyebut seluruh informasi visual di dalamnya, ring `focus-visible` yang terlihat, dan target sentuh minimal 44px. Preseden di dalam design system sendiri: `components/ui/color-picker/ColorPicker.vue`.
2. **Compact data tile** - sel di grid dengan jumlah kolom tetap. Boleh turun di bawah `text-xs`, tapi hanya di belakang prefix breakpoint dan hanya di breakpoint tempat sel itu benar-benar menyempit.
3. **Teks di atas warna arbitrer user** - token semantik tidak berlaku di atas warna yang tidak kita kendalikan. Boleh `text-white/85` atau `text-black/80`, dengan syarat pilihannya dihitung dari rasio kontras, bukan ditebak.
4. **Contoh docs yang di-port dari upstream** - berkas di bawah `ui-docs/examples/` mencerminkan sumber aslinya apa adanya. Jangan "diperbaiki" mengikuti guide ini; itu memutus paritas yang justru jadi tujuan halaman docs.

---

## 23. Checklist Sebelum Commit UI

- Semua input pakai component shadcn-vue, bukan native.
- Tidak ada teks statis di bawah `text-sm`. Jangan diperiksa dengan mata. Buka halamannya di viewport 390px, jalankan ini di console, hasilnya harus kosong:
  ```js
  document.querySelectorAll('main *').forEach((el) => {
    if (el.children.length) return;
    const fs = parseFloat(getComputedStyle(el).fontSize);
    if (fs < 14) console.log(fs, el.textContent.trim().slice(0, 60));
  });
  ```
- Semua input / textarea minimal 16px di mobile.
- Semua teks pakai `tracking-tight` atau `tracking-tighter`.
- Warna pakai CSS variable, bukan literal Tailwind color.
- Grid form gap-nya `gap-x-2`.
- Form pakai struktur `Label + Input + helper + FieldError` dalam `space-y-2` wrapper.
- Section form dibungkus `.frame`.
- Tombol delete buka `<ResponsiveDialog>` konfirmasi.
- Empty state pakai component `<Empty>`.
- Skeleton loading pakai component `<Skeleton>`.
- Tidak ada `font-bold`, `uppercase`, `tracking-wider`.
- Tidak ada `bg-*` / `h-*` / `rounded-*` / `px-*` yang di-hardcode di elemen input-like. Di pmone, `bash frontend/scripts/check-theming-sync.sh` harus hijau (ia sekaligus menjalankan `check-input-hardcode.sh`).

---

## File Reference

pmone adalah aplikasi Nuxt tunggal di `frontend/`, satu repo dengan backend Laravel-nya.

- `frontend/app/components/ui/` - semua component shadcn-vue. Wajib identik dengan pmone-events dan levenium; jangan ubah sepihak.
- `frontend/app/components/ui/button/index.ts` - daftar nama variant/size button. Nilai visualnya (font-size, tinggi, padding, radius) ada di `app/assets/css/styles/style-*.css` pada rule `.cn-button*`, bukan di sini.
- `frontend/app/assets/css/main.css` - CSS variable, utility (`page-title`, `page-description`, `container`, `container-wider`, `squircle`, `min-h-screen-offset`), dan class custom (`.frame`).
- `frontend/app/assets/css/styles/` - satu file per style (`style-mono.css` yang jadi default, `style-nova.css`, dan seterusnya) plus `_base.css` yang di-generate.
- `frontend/app/assets/css/transitions.css` - satu-satunya sumber motion; identik di ketiga repo.
- `frontend/scripts/check-theming-sync.sh` - guard sebelum commit; ia sekaligus menjalankan `check-input-hardcode.sh`.
- `frontend/app/components/FormUser.vue`, `FormProject.vue`, `FormEvent.vue` - referensi pattern form lengkap.
- `frontend/app/components/header/HeaderBreadcrumb.vue` - referensi breadcrumb.

### Nilai utility di repo ini

Disalin dari `main.css`. Kalau salah satunya berubah, perbarui juga di sini - inilah satu-satunya tempat nilai konkret boleh ditulis.

```css
@utility page-title {
  @apply text-foreground text-xl !leading-[1.2] font-medium tracking-tighter text-balance sm:text-xl;
}

@utility page-description {
  @apply text-muted-foreground text-sm tracking-tight;
}
```

Tangga heading untuk halaman publik di luar app chrome (docs, link page, halaman form publik), sesuai yang sudah dipakai:

| Tier | Untuk | Class |
|---|---|---|
| Hero | landing sebuah area | `text-4xl font-medium tracking-tighter sm:text-5xl` |
| Docs | dokumen di dalam area | `text-3xl font-semibold tracking-tighter sm:text-4xl` |
| Tool | halaman satu alat atau satu item | `text-2xl font-semibold tracking-tighter sm:text-3xl` |

Deskripsi di bawah H1 tier mana pun: `text-muted-foreground max-w-3xl text-base tracking-tight text-pretty sm:text-lg`.

---

## Catatan

Style guide ini belum final. User akan menambah atau mengubah secara manual seiring waktu. Kalau ketemu pattern baru yang konsisten dipakai di banyak tempat, dokumentasikan ke sini juga.

Section 1-23 dijaga identik di pmone, pmone-events, dan levenium. Kalau kamu mengubah salah satu, ubah ketiganya - sama seperti aturan sinkronisasi `components/ui`.
