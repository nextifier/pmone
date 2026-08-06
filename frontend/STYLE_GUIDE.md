# Style Guide PM One Frontend

Style guide ini disusun berdasarkan pattern desain yang sudah berjalan di `/frontend/` (Nuxt 4 + Tailwind v4 + shadcn-vue). Tujuannya: setiap kali Claude membuat atau mengubah UI, hasilnya konsisten dengan konvensi yang sudah ada. Bukan menciptakan pattern baru tanpa alasan kuat.

Aturan ini wajib diikuti. Kalau ada kasus yang belum tercakup di sini, cek sibling component dulu sebelum bikin pattern baru.

---

## 1. Typography

### Letter spacing (tracking)

- Semua teks pakai `tracking-tight` sebagai default.
- Untuk teks yang lebih besar (`text-xl` ke atas) atau yang pakai `font-semibold`, pakai `tracking-tighter`. Logikanya: makin besar atau makin tebal, makin rapat.
- Jangan pakai `tracking-wider` atau `tracking-widest`.
- Jangan pakai `uppercase`. Kalau butuh label kecil, tetap pakai case normal dengan `text-muted-foreground`.

### Ukuran teks

- Default body: `text-sm` atau `text-base`.
- Hindari `text-xs`, `text-[11px]`, `text-[10px]` berdiri sendiri di layar besar. Kalau butuh kecil, pakai `text-xs sm:text-sm` supaya tetap nyaman dibaca di desktop.
- Hindari `text-[9px]` kecuali untuk indikator badge yang memang sangat compact (step number, key indicator).

#### Skala button (dimiliki style, jangan di-override di call site)

Semua `style-*.css` memakai skala yang sama. Yang berbeda antar style cuma tinggi, padding, radius, dan ukuran ikon.

| size | font-size |
| --- | --- |
| `xs` | `text-xs sm:text-sm` |
| `sm` | `text-sm` |
| `default`, `lg` | `text-sm sm:text-base` (diwarisi dari `.cn-button`) |
| `xl` | `text-base sm:text-lg` |

Pengecualian: `style-mira.css` memakai `text-xs` flat di size `xs`, karena `h-5` tidak muat menampung line-box 20px.

Konsekuensi yang perlu diingat: tombol `size="sm"` tetap 14px di desktop, sementara field di sebelahnya 16px. Itu memang bentuk yang diinginkan untuk toolbar, bukan bug.

#### Field selalu `text-base`

`.cn-input`, `.cn-textarea`, `.cn-native-select`, dan `.cn-input-otp-slot` memakai `text-base` di semua breakpoint. Jangan menambahkan `md:text-sm` kembali: 16px adalah ambang di mana iOS Safari berhenti melakukan zoom-on-focus.

`.cn-select-trigger` mengikuti skala button (`text-sm sm:text-base`), dengan `data-[size=sm]:text-sm` supaya varian kompaknya sejajar dengan `<Button size="sm">`.

### Hierarchy

- Page title: pakai utility `page-title` (sudah didefinisikan di `main.css:811`, output: `text-primary text-xl font-medium tracking-tighter text-balance`). Jangan bikin styling page title manual.
- Page description: pakai utility `page-description` (`text-muted-foreground text-sm tracking-tight`).
- Section title di dalam frame: `text-sm font-semibold tracking-tighter text-muted-foreground` (sudah otomatis dari class `.frame-title`).
- Card title biasa: `text-lg font-medium tracking-tighter`.
- Body teks panjang: `text-sm tracking-tight` atau `text-base tracking-tight`.
- Helper text di bawah input: `text-muted-foreground text-xs`.
- Label form: pakai component `<Label>`. Stylingnya sudah `text-sm leading-none font-medium tracking-tight` dari base layer (`main.css:526`).

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
- Error / delete: `text-destructive` atau `text-destructive-foreground` (untuk error message di bawah input pakai `text-destructive-foreground` mengikuti pattern `InputErrorMessage`).

---

## 2. Warna (CSS Variables)

Wajib pakai CSS variable, bukan warna Tailwind generik.

- `bg-background`, `bg-card`, `bg-popover` untuk surface utama.
- `bg-muted` untuk surface sekunder, hover state, container icon, badge ringan.
- `bg-muted/50` untuk overlay tipis (lihat `.frame`).
- `bg-primary` + `text-primary-foreground` untuk CTA utama.
- `bg-secondary` + `text-secondary-foreground` untuk button sekunder.
- `bg-destructive` + `text-white` untuk tombol delete. Versi soft: `bg-destructive/10 text-destructive`.
- `bg-success`, `bg-warning`, `bg-info` untuk status badge. Versi soft: `bg-success/10 text-success-foreground` dst.

Jangan pakai:

- `bg-green-*`, `bg-red-*`, `bg-yellow-*`, `bg-blue-*` literal. Ganti ke `success`, `destructive`, `warning`, `info`.
- `bg-white`, `bg-black` literal. Ganti ke `bg-background` atau `bg-foreground`.
- `border-gray-*` literal. Ganti ke `border-border`.
- Warna custom hex (`#fff`, `#000`) kecuali memang ada kebutuhan khusus.

Border:

- Default: `border` (otomatis ambil `border-border` via base layer).
- Input border: `border-input`.

---

## 3. Border Radius

- `rounded-md` untuk input, button kecil, badge.
- `rounded-lg` untuk button standard, popover item, dialog button area.
- `rounded-xl` untuk card, panel, frame.
- `rounded-2xl` untuk container besar / hero.
- `rounded-full` untuk avatar bulat, indikator, chip kecil.
- `squircle` (utility custom di `main.css:771`) untuk logo, app icon. Pakai ini, jangan bikin shape manual.

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
- `<Combobox>` untuk select dengan search.
- `<MultiSelect>` untuk pilih banyak.

### Custom input wajib

- Phone: pakai `<InputPhone>` (`components/ui/input-phone/`). Sudah ada country selector built-in.
- Link / URL / social media: pakai `<InputLink>` (`components/ui/input-link/`). Auto prefix Instagram, Facebook, LinkedIn, dll.
- Password: pakai `<InputPassword>` (`components/ui/input-password/`).
- OTP / PIN: pakai `<InputOtp>` atau `<PinInput>`.
- Date: pakai `<DatePicker>` (single/range, opsional `with-time`, presets). Semua picker keluarga tanggal ada di `components/ui/date-picker/`.
- Time: pakai `<TimePicker>` atau `<TimeRangePicker>`.
- Bulan / Tahun: pakai `<MonthPicker>`, `<MonthRangePicker>`, `<YearPicker>`, atau `<YearRangePicker>`.
- File: pakai `<InputFile>` atau `<InputFileImage>` (untuk gambar dengan preview + delete + undo).
- Tags / labels: pakai pattern yang sudah dipakai untuk Spatie Tags di project / event.
- Rich text: pakai `<TipTapEditor>`.

### Struktur field standar

```vue
<div class="space-y-2">
  <Label for="name">Field Label</Label>
  <Input id="name" v-model="form.name" required />
  <p class="text-muted-foreground text-xs">Helper text opsional.</p>
  <InputErrorMessage :errors="errors.name" />
</div>
```

- Label wajib pakai `for` yang match dengan `id` input.
- Required field: cukup pasang attribute `required`. Asterisk merah otomatis muncul via CSS selector di `main.css:984-991` (di dalam `#layout-app`).
- Error message: pakai `<InputErrorMessage :errors="errors.field" />`. Jangan render manual.
- Helper text pakai `text-muted-foreground text-xs`, posisi di bawah input sebelum error message.

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

Class `frame`, `frame-header`, `frame-title`, `frame-description`, `frame-panel`, `frame-footer` sudah ada di `main.css:960-982`. Jangan bikin styling card alternatif untuk section form.

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

Tinggi setiap size dimiliki style aktif, jadi jangan menghafal angkanya. Di style default (`mono`) urutannya `xs` 24px, `sm` 28px, `default` 32px, `lg` 36px, `xl` 44px, tapi di `sera` semuanya lebih besar dan di `mira` lebih kecil. Pilih berdasarkan peran, bukan piksel.

- `xs`: badge-like action di dalam baris padat.
- `sm`: default untuk toolbar, header halaman, dan aksi di dalam row table. Ini yang paling sering dipakai.
- `default`: form dan panel.
- `lg` / `xl`: CTA besar di hero atau form panjang.
- `icon` / `iconSm` / `iconXs` / `iconLg`: tombol icon-only. Wajib `<Tippy>` atau `aria-label`.

Kalau tombol harus sejajar dengan field di baris yang sama (misal di samping search input), pakai `size="sm"` plus `class="h-(--cn-input-h)"`. Variabel itu dideklarasikan tiap style dan bernilai sama dengan tinggi `.cn-input`, jadi barisnya rata di semua style tanpa hardcode `h-8`.

### Icon + text

- Gap antara icon dan label dimiliki style (`gap-1` di size kecil, `gap-1.5` di size normal).
- Icon size auto `size-4` kalau tidak ditentukan.
- Pattern: `<Button><Icon name="hugeicons:add-01" /> Add</Button>`.

### Tombol toolbar

Jangan menulis ulang tombol toolbar sebagai `<button>` dengan class border sendiri. Yang benar:

```vue
<Button variant="outline" size="sm" to="/posts/trash">
  <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
  <span>Trash</span>
</Button>
```

`<Button>` menerima `to` dan otomatis merender `NuxtLink` (plus `target`/`rel` kalau URL-nya eksternal), jadi tidak perlu `<nuxt-link>` terpisah.

Untuk tombol filter di `<TableData>`, pakai `<TableFilterButton>` — komponennya sudah membawa `PopoverTrigger`, badge counter, dan versi kotak untuk mobile:

```vue
<template #filters>
  <Popover>
    <TableFilterButton :count="totalActiveFilters" />
    <PopoverContent align="end">…</PopoverContent>
  </Popover>
</template>
```

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
- Hover text destructive: `hover:text-destructive`.
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

- Pakai `<Avatar>` untuk semua representasi user, brand, project, event.
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

---

## 19. Skeleton & Loading

- Pakai `<Skeleton>` (`components/ui/skeleton/`) untuk placeholder loading. Jangan bikin div abu manual.
- Animasi sudah otomatis (`animate-skeleton`).
- Untuk loading button: `<Spinner />` di dalam button + `:disabled="loading"`.

---

## 20. Notifikasi (Toast)

- Pakai sonner via `useSonner` / `toast()`. Jangan bikin notifikasi custom di pojok layar.
- Variant: `toast.success()`, `toast.error()`, `toast.warning()`, `toast.info()`.
- Title pendek, description optional. Pesan dalam Bahasa Indonesia kalau di Staff Dashboard, sesuai konvensi.

---

## 21. Tooltip

- Pakai `v-tippy` directive yang sudah terpasang global.
- Pattern: `<Button v-tippy="'Label tooltip'">...</Button>`.
- Untuk konten kompleks, pakai `<Tippy>` component dengan slot `#content`.
- Hindari title attribute native (`title="..."`) karena style tidak konsisten.

---

## 22. Hal yang Wajib Dihindari

- `text-xs` standalone di layar besar. Pakai `text-xs sm:text-sm`.
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
- Menempel `bg-*`, `border-*`, `h-*`, `rounded-*`, `px-*`, `shadow-*` di call site elemen input-like (input, textarea, select trigger, combobox, chips, dropzone). Semua itu milik rule `cn-*` di `assets/css/styles/style-<name>.css`. Meng-hardcode-nya memaku field ke satu tampilan sehingga ia tidak ikut berganti saat user memilih Style lain - dan `dark:bg-background` khususnya membuat field lebur ke latar dialog di dark mode. Kalau butuh nilai yang belum ada, tambahkan rule `cn-*`-nya (ingat: 9 file style x 3 repo), jangan hardcode. Guard: `bash frontend/scripts/check-input-hardcode.sh`.

---

## 23. Checklist Sebelum Commit UI

- Semua input pakai component shadcn-vue, bukan native.
- Tidak ada `text-xs` standalone di larger screen.
- Semua teks pakai `tracking-tight` atau `tracking-tighter`.
- Warna pakai CSS variable, bukan literal Tailwind color.
- Grid form gap-nya `gap-x-2`.
- Form pakai struktur `Label + Input + helper + InputErrorMessage` dalam `space-y-2` wrapper.
- Section form dibungkus `.frame`.
- Tombol delete buka `<ResponsiveDialog>` konfirmasi.
- Empty state pakai component `<Empty>`.
- Skeleton loading pakai component `<Skeleton>`.
- Tidak ada `font-bold`, `uppercase`, `tracking-wider`.
- `bash frontend/scripts/check-theming-sync.sh` hijau (ia sekaligus menjalankan check-input-hardcode.sh).

---

## File Reference

- `frontend/app/assets/css/main.css` - definisi semua CSS variable, utility (`page-title`, `page-description`, `container`, `container-wider`, `squircle`, `min-h-screen-offset`, dll), dan class custom (`.frame`).
- `frontend/app/components/ui/` - semua component shadcn-vue.
- `frontend/app/components/ui/button/index.ts` - daftar nama variant/size button. Nilai visualnya (font-size, tinggi, padding, radius) ada di `assets/css/styles/style-*.css` pada rule `.cn-button*`, bukan di sini.
- `frontend/app/components/FormUser.vue`, `FormProject.vue`, `FormEvent.vue` - referensi pattern form lengkap.
- `frontend/app/components/header/HeaderBreadcrumb.vue` - referensi breadcrumb.

---

## Catatan

Style guide ini belum final. User akan menambah atau mengubah secara manual seiring waktu. Kalau ketemu pattern baru yang konsisten dipakai di banyak tempat, dokumentasikan ke sini juga.
