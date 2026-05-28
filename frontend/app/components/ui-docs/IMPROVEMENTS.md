# UI Documentation Improvements

Daftar prioritas untuk meningkatkan kualitas `/ui` docs. Setiap item bisa dikerjakan terpisah di sesi Claude Code baru.

Konteks wajib untuk setiap sesi: baca dulu `app/components/ui-docs/README.md` sebagai authoring guide. Stack: Nuxt 4 + Vue 3 + Tailwind v4 + reka-ui. 97 komponen sudah terdaftar di `registry/`, illustrations di `illustrations/`, examples di `examples/{name}/*.vue`.

Aturan global: copy English, tidak pakai em-dash, `tracking-tight` untuk teks normal dan `tracking-tighter` untuk heading, `font-semibold` maksimal, CSS variable colors (`bg-muted`, `text-foreground`), no `text-xs` standalone, jangan sentuh `npm run build` atau `nuxi typecheck`.

---

## 1. Audit + expand example coverage

**Status:** Banyak komponen hanya punya 1 contoh `default`. Target shadcn-vue: 3-7 contoh per komponen (default + variants + sizes + states + composition).

**Acceptance:**
- Setiap komponen punya minimal 3 sections kecuali yang memang trivial (`Spinner`, `Separator`, `Kbd`, `AspectRatio`)
- Komponen interaktif punya: `default`, `variants` atau `sizes` (jika ada CVA), `disabled`, `controlled` (v-model)
- Form-style components (`Input`, `Textarea`, `Checkbox`, `Switch`, `Select`, `RadioGroup`) tambahan: `with-label`, `with-error`, `with-description`
- File baru di `examples/{name}/{section}.vue`, append section ke `registry/{name}.js`
- Verifikasi di browser, tidak boleh ada console warning baru

**Prompt:**
```
Baca app/components/ui-docs/README.md dulu. Audit example coverage di /ui docs. List 97 komponen di app/components/ui-docs/registry/, identifikasi yang masih punya kurang dari 3 sections (kecuali trivial: Spinner, Separator, Kbd, AspectRatio). 

Untuk setiap komponen yang kurang, tambahkan sections berikut sesuai relevansi:
- default (jika belum ada)
- variants (jika komponen punya CVA variant prop)
- sizes (jika komponen punya size prop)
- disabled (komponen interaktif)
- controlled (komponen dengan v-model)
- with-label / with-error / with-description (form-style)
- with-icon (komponen yang sering dikombinasi dengan icon)

Pattern: tambah file Vue di examples/{name}/{section-id}.vue, append section object ke registry/{name}.js sections array dengan { id, title, description, examples: [section-id], align }. Cek sibling registry untuk format yang konsisten.

Selalu verifikasi setiap halaman di browser (localhost:3000/ui/{name}) via Claude in Chrome setelah selesai per komponen. Tidak boleh ada console error/warning baru. 

Kerjakan dalam batches per huruf alphabet, A-Z, satu batch per turn supaya context tidak meledak. Report progress per batch.
```

---

## 2. API reference completeness audit

**Status:** Beberapa registry hanya document main component, padahal banyak yang punya sub-components (`CardHeader`, `DialogContent`, `FormField`, `SidebarMenuItem`, dst). Beberapa juga miss events/slots.

**Acceptance:**
- Setiap registry punya entry `apiReference` untuk SEMUA sub-component yang punya props/events/slots non-trivial
- Setiap entry punya: props (lengkap), events (jika emit), slots (jika ada `<slot>` atau named slot)
- Cross-check dengan source `app/components/ui/{name}/*.vue` dan `index.ts`
- Untuk reka-ui forwarded props, link ke reka-ui docs di description daripada salin-tempel

**Prompt:**
```
Baca app/components/ui-docs/README.md dulu. Audit API reference di registry/{name}.js untuk semua 97 komponen. 

Pattern audit per komponen:
1. Buka app/components/ui/{name}/index.ts, list semua sub-components yang diekspor
2. Buka setiap sub-component .vue file, identifikasi defineProps, defineEmits, slot tags
3. Bandingkan dengan apiReference[] di registry/{name}.js
4. Tambahkan entry apiReference yang missing, atau update props/events/slots yang incomplete

Aturan:
- Hanya document sub-component yang punya props/events/slots non-trivial (skip yang cuma render <slot />)  
- Untuk reka-ui forwarded props, sebut "Forwards to reka-ui ComponentRoot" di description; jangan salin semua prop
- Setiap prop: { name, type (TypeScript syntax), default, description (1-2 sentences) }
- Events: { name, description } (gunakan kebab-case nama event seperti yang di-emit)
- Slots: { name, description } ("default" untuk default slot)

Verifikasi di browser per komponen yang diubah, scroll ke API Reference section, pastikan tabel render benar.

Kerjakan per huruf alphabet (1 batch per turn), report progress dengan list komponen yang ditambahkan/diupdate.
```

---

## 3. Anatomy diagrams untuk composite components

**Status:** Composite components (`Card`, `Dialog`, `Form`, `Sidebar`, `Sheet`, `Drawer`, `Command`, `Calendar`, `Stepper`, `TableData`) butuh anatomy diagram seperti shadcn-vue, supaya user paham hierarki sub-component sebelum baca kode.

**Acceptance:**
- Component baru `app/components/ui-docs/AnatomyDiagram.vue` yang render tree text/blocks
- Setiap composite component dapat section `anatomy` di registry (id: "anatomy", title: "Anatomy", description: optional)
- Registry support field baru `anatomy: { tree: [...] }` yang di-render via AnatomyDiagram
- Pattern visual: nested boxes dengan border + label monospace, indent untuk hierarki
- Render di antara `whenToUse` dan sections yang biasa

**Prompt:**
```
Baca app/components/ui-docs/README.md dulu. Buat fitur Anatomy diagram untuk composite components di /ui docs.

Implementation:
1. Buat app/components/ui-docs/AnatomyDiagram.vue, props { tree: AnatomyNode[] } dimana AnatomyNode = { component: string, children?: AnatomyNode[] }. Render sebagai nested boxes pakai border + bg-card + monospace text. Tampilkan tree indent dengan border-l ascii-like atau pure tailwind.

2. Update app/components/ui-docs/registry/define.js JSDoc supaya `anatomy` field dikenali: optional { tree: AnatomyNode[] }.

3. Update app/pages/ui/[name].vue: render <AnatomyDiagram :tree="entry.anatomy.tree" /> di section terpisah dengan id "anatomy", title "Anatomy", letak setelah whenToUse sebelum sections lain. Skip kalau entry.anatomy tidak ada.

4. Tambahkan anatomy: { tree: [...] } ke registries berikut sesuai struktur sub-component asli (cek index.ts):
- card (Card > CardHeader, CardTitle, CardDescription, CardContent, CardFooter)
- dialog (Dialog > DialogTrigger, DialogContent > DialogHeader > DialogTitle, DialogDescription, DialogFooter, DialogClose)
- dialog-responsive
- drawer
- sheet
- alert-dialog
- form (Form > FormField > FormItem > FormLabel, FormControl, FormDescription, FormMessage)
- sidebar
- command
- calendar
- stepper
- table-data
- carousel
- table

5. Tambahkan "Anatomy" entry ke ScrollSpy "On This Page" sidebar (kemungkinan otomatis karena sudah ada h2 baru).

Verifikasi di browser: buka /ui/card dan /ui/dialog, anatomy diagram tampil benar, scrollspy highlight aktif.
```

---

## 4. Accessibility notes section

**Status:** Tidak ada dokumentasi keyboard shortcut, ARIA, atau focus behavior. Untuk komponen seperti `Dialog`, `Combobox`, `Calendar`, `Tabs`, ini krusial.

**Acceptance:**
- Field baru `accessibility` di registry: `{ keyboard: KeyBinding[], notes?: string[] }`
- `KeyBinding = { keys: string[], description: string }`, contoh `{ keys: ["Esc"], description: "Close the dialog" }`
- Component baru `AccessibilityTable.vue` render keyboard shortcuts dengan `<Kbd>` component yang sudah ada
- Section "Accessibility" muncul setelah API Reference, hanya kalau entry punya field accessibility
- Mulai dengan komponen interaktif: dialog, dialog-responsive, alert-dialog, combobox, command, calendar, range-calendar, date-picker, range-calendar-picker, popover, tooltip, tabs, accordion, dropdown-menu, context-menu, menubar, navigation-menu, hover-card, select, multi-select, sidebar, sheet, drawer

**Prompt:**
```
Baca app/components/ui-docs/README.md dulu. Tambahkan Accessibility section di /ui docs.

Implementation:
1. Buat app/components/ui-docs/AccessibilityTable.vue. Props: { keyboard: { keys: string[], description: string }[], notes?: string[] }. Render keyboard table pakai Kbd component (sudah ada di @/components/ui/kbd). Format mirip API table tapi 2 columns: Shortcut (Kbd badges joined oleh "+") dan Description. notes? rendered sebagai bullet list di bawahnya.

2. Update define.js JSDoc untuk accept field accessibility?: { keyboard, notes? }.

3. Update app/pages/ui/[name].vue: render Accessibility section dengan id "accessibility" setelah API Reference, hanya kalau entry.accessibility ada. Title "Accessibility", description "Keyboard shortcuts and ARIA behavior."

4. Isi accessibility data untuk komponen interaktif berikut (cari real shortcut dari reka-ui docs context7 atau test manual): dialog, dialog-responsive, alert-dialog, combobox, command, calendar, range-calendar, date-picker, range-calendar-picker, popover, tooltip, tabs, accordion, dropdown-menu, context-menu, menubar, navigation-menu, hover-card, select, multi-select, sidebar, sheet, drawer, slider, switch, checkbox, radio-group, toggle, toggle-group, tags-input.

Verifikasi di browser per komponen. ScrollSpy harus include Accessibility item.
```

---

## 5. Expand whenToUse untuk sibling components

**Status:** Beberapa komponen punya sibling yang melakukan hal mirip. User butuh kriteria decision yang jelas.

**Sibling pairs:**
- Dialog vs DialogResponsive vs AlertDialog
- Drawer vs Sheet vs SlideDrawer
- Sonner vs Notifications
- Pagination vs PaginationCustom
- Table vs TableData
- Tabs vs TabNav
- Calendar vs DatePicker vs RangeCalendar vs RangeCalendarPicker
- Combobox vs Select vs MultiSelect vs LocationCombobox
- Switch vs TableSwitch
- Tooltip vs HoverCard
- Input vs InputGroup vs Field

**Acceptance:**
- Setiap komponen di pair di atas punya `whenToUse` yang jelas membedakan dirinya dari sibling
- Format: 1 paragraf, mention nama sibling, kasih kriteria konkret (use X when..., use Y when...)
- Sudah ada beberapa, tinggal review + tambah yang missing

**Prompt:**
```
Baca app/components/ui-docs/README.md dulu. Improve whenToUse sections di registry untuk komponen yang punya sibling.

Sibling groups yang harus punya whenToUse jelas:
- dialog vs dialog-responsive vs alert-dialog (kapan masing-masing)
- drawer vs sheet vs slide-drawer
- sonner vs notifications
- pagination vs pagination-custom
- table vs table-data
- tabs vs tab-nav
- calendar vs date-picker vs range-calendar vs range-calendar-picker
- combobox vs select vs multi-select vs location-combobox
- switch vs table-switch
- tooltip vs hover-card
- input vs input-group vs field

Untuk setiap komponen di atas:
1. Baca whenToUse yang sudah ada di registry/{name}.js
2. Pastikan: (a) menyebut nama sibling, (b) kasih kriteria konkret kapan pakai ini vs kapan pakai sibling
3. Jika belum jelas, tulis ulang dengan format: 1 paragraf 2-3 kalimat, English natural

Verifikasi di browser per komponen, whenToUse section harus muncul di atas dan scrollspy include "When to use X vs Y".
```

---

## 6. Dark mode preview toggle per example

**Status:** Saat ini ganti theme global pakai ColorModeToggle di header. Lebih baik tiap example bisa di-toggle independen biar mudah compare.

**Acceptance:**
- ComponentPreview tambah toggle sun/moon di pojok preview frame
- Toggle hanya affect div pembungkus preview, tidak global
- Implementasi: add/remove `dark` class di scoped wrapper, atau pakai `data-theme="dark"` selector
- Default sesuai theme global aktif
- State per-preview, tidak persistent

**Prompt:**
```
Baca app/components/ui-docs/README.md dulu. Tambahkan dark mode toggle per example preview di /ui docs.

Implementation:
1. Edit app/components/ui-docs/ComponentPreview.vue. Tambah toggle button (Icon sun/moon dari lucide) di pojok kanan atas preview frame, di dalam TabsContent value="preview". 
2. State lokal: const isDark = ref(undefined). Pas undefined, ikutin global theme. Pas true/false override per-preview.
3. Wrap preview content dengan div :class="isDark === true ? 'dark' : isDark === false ? '' : ''" dan terapkan style isolation supaya `dark` class hanya affect children, bukan parent. Pakai CSS isolation via :where(.dark) atau wrapping yang explicit.
4. Toggle button kecil, tampil saat hover preview frame (opacity-0 group-hover:opacity-100), supaya tidak distract.

Verifikasi di browser: buka /ui/button, /ui/card, /ui/dialog. Toggle theme per preview, pastikan hanya preview tersebut yang berubah, sidebar/header tetap di global theme.
```

---

## 7. Section anchor copy-link

**Status:** Heading section tidak bisa di-click untuk dapat deep link. shadcn-vue dan tailwindcss.com punya fitur ini (hash icon muncul on hover).

**Acceptance:**
- Setiap h2 di docs page punya icon link kecil yang muncul on hover
- Click icon copy URL absolut (e.g. `localhost:3000/ui/button#variants`) ke clipboard
- Toast "Link copied" pakai sonner setelah copy
- Tidak override behavior browser scroll-to-anchor

**Prompt:**
```
Baca app/components/ui-docs/README.md dulu. Tambahkan section anchor copy-link di /ui docs.

Implementation:
1. Edit app/pages/ui/[name].vue. Untuk setiap h2 di sections (whenToUse + sections + api-reference + accessibility), wrap heading text + tambah <button> dengan Icon lucide:link kecil ml-2. Tampil opacity-0 group-hover:opacity-100.
2. Click handler: copy `${window.location.origin}${window.location.pathname}#${section.id}` ke clipboard via navigator.clipboard.writeText. Lalu trigger toast lewat sonner: toast.success("Link copied").
3. Pastikan h2 parent punya class "group" supaya hover state propagate.
4. Pakai ButtonCopy component yang sudah ada kalau ada API yang cocok, atau plain button.

Verifikasi di browser: hover h2 di /ui/button, icon link muncul, click copy link, toast muncul, paste URL di tab baru lalu scroll ke section yang benar.
```

---

## 8. Browser smoke test untuk semua /ui pages

**Status:** Tidak ada automated test. Bug regresi bisa terjadi tanpa terdeteksi sampai user manual buka tiap page.

**Acceptance:**
- Pest 4 browser test di `tests/Browser/UiDocsSmokeTest.php`
- Loop semua 97 komponen + introduction, visit `/ui/{name}`, assert no console errors
- Test cepat (target di bawah 60 detik total)
- Run via `php artisan test --filter=UiDocsSmokeTest`
- Activate skill pest-testing sebelum start

**Prompt:**
```
Activate pest-testing skill dulu. Lalu baca app/components/ui-docs/README.md untuk konteks docs system.

Implementation:
1. Buat tests/Browser/UiDocsSmokeTest.php pakai Pest 4 browser testing.
2. Test loop semua 97 komponen + introduction page.
3. Untuk setiap route /ui/{name}: visit, assertNoJavascriptErrors, assertSee judul komponen.
4. Generate list 97 nama dari membaca app/components/ui-docs/registry/*.js (kecuali index.js + define.js) di setup test.
5. Pakai Pest dataset supaya tiap komponen jadi separate test case, output jelas mana yang fail.
6. Target runtime di bawah 60 detik total. Pakai shared browser instance kalau bisa.

Verifikasi: `php artisan test --filter=UiDocsSmokeTest --compact` harus pass untuk semua 97 komponen + introduction. Sertakan output di response.

Catatan: ini test frontend Nuxt yang served di localhost:3000 (frontend), bukan Laravel backend. Pastikan dev server Nuxt jalan sebelum run test, atau guard test dengan check ke localhost:3000 ready.
```

---

## 9. Real-world composition examples

**Status:** Examples sekarang isolated (1 komponen per file). User butuh lihat composition realistic seperti "form sign-up", "settings card", "data table dengan filter + pagination".

**Acceptance:**
- Tambah section baru "Composition" atau "Recipes" di docs untuk komponen yang sering jadi building block
- Target komponen: form, dialog, card, sidebar, table-data, tip-tap-editor, calendar
- Setiap composition example mendemonstrasikan komponen dipakai bareng 3+ komponen lain
- File di `examples/{name}/composition.vue` atau `examples/{name}/recipe-{slug}.vue`

**Prompt:**
```
Baca app/components/ui-docs/README.md dulu. Tambahkan real-world composition examples untuk komponen building-block utama.

Target komponen + composition idea:
- form: complete sign-up form (Input, FormField, Label, Description, Error, Checkbox terms, Submit Button)
- dialog: edit profile dialog dengan Form di dalamnya
- card: settings card dengan Header, Description, Switch toggle, Footer dengan Save button
- sidebar: full app shell (Sidebar + SidebarInset + Header + content)
- table-data: dengan toolbar filters, search, bulk actions, pagination
- tip-tap-editor: blog post composer dengan title Input + TipTap body + Save/Draft buttons
- calendar: date picker dengan input trigger (sebenarnya ini DatePicker, kasih example terpisah yang menunjukkan Calendar dipakai inside Popover)

Pattern:
1. File examples/{name}/composition.vue (atau multiple jika perlu: composition-form, composition-with-popover, dst)
2. Append section ke registry/{name}.js: { id: "composition", title: "Composition", description: "...real-world pattern description...", examples: ["composition"] }
3. Content harus realistic tapi generic (jangan sebut "PM One", pakai contoh seperti "user@example.com", "Acme Inc", dst)

Verifikasi di browser per komponen, composition section render dengan benar.
```

---

## 10. Illustration distinctness audit

**Status:** Beberapa illustration mungkin terlalu generic atau mirip satu sama lain karena copy-paste dari template. Perlu pass untuk pastikan tiap card di /ui index unik.

**Acceptance:**
- Buka /ui index, scroll seluruh 97 cards di light + dark mode
- Identifikasi illustration yang: terlalu generic (cuma frame kosong), duplikat dari komponen lain, overflow, atau tidak mewakili komponen
- Redesign illustration tersebut supaya distinctly menampilkan ciri visual komponen
- Tidak boleh ada literal palette colors (no `bg-red-500`, dst), pakai `bg-muted-foreground/{20,40,88}` atau CSS variable

**Prompt:**
```
Baca app/components/ui-docs/README.md dulu, perhatikan section "Illustrations" untuk pattern + primitive shapes yang available.

Audit illustration quality:
1. Buka localhost:3000/ui via Claude in Chrome, screenshot full page light mode + dark mode
2. Scroll dan capture semua 97 cards (split jadi beberapa screenshot per row)
3. Identifikasi yang bermasalah: (a) terlalu generic, (b) duplikat visual dengan komponen lain, (c) overflow keluar frame, (d) tidak relate ke komponen, (e) pakai literal palette color

Untuk setiap masalah, redesign file di app/components/ui-docs/illustrations/{name}.vue:
- Pakai IllustrationFrame variants yang tersedia
- Primitive shapes: text bars (h-1.5 bg-muted-foreground/{20,40,88} rounded-full), paragraph blocks (h-4 bg-muted-foreground/8 rounded-sm), dots (size-2 rounded-full bg-muted-foreground/88), inline lucide SVG untuk affordances
- Width safelist: 24, 36, 50, 72 only
- No literal palette colors, pakai muted-foreground variants atau CSS vars

Verifikasi di browser, semua cards harus distinctive dan render bersih di light + dark.

Kerjakan dalam batches per huruf alphabet (A-D, E-I, J-N, O-S, T-Z) supaya context manageable.
```

---

## Cara pakai

1. Pilih item TODO yang mau dikerjakan
2. Buka sesi Claude Code baru di direktori `/Users/nextifier/Herd/pmone/frontend`
3. Copy-paste prompt di item tersebut
4. Claude akan baca authoring guide dulu, lalu kerjakan
5. Verifikasi hasil di browser
6. Centang item kalau sudah selesai

Order saran (priority): 2 (API audit) > 1 (example coverage) > 4 (accessibility) > 5 (whenToUse) > 3 (anatomy) > 8 (smoke tests) > 9 (composition) > 10 (illustration) > 6 (dark toggle) > 7 (anchor link)
