# Plan: Unifikasi Calendar + DatePicker (shadcn pattern) — pmone / pmone-events / levenium

> Plan ini disusun dengan riset source code shadcn (GitHub `shadcn-ui/ui` + `unovue/shadcn-vue` + reka-ui) dan eksplorasi penuh 3 repo. Siap dieksekusi oleh Opus 4.8. Semua fakta di bagian Context sudah diverifikasi — jangan re-derive, langsung eksekusi sesuai urutan §8.

## Context

`components/ui` punya 4 komponen kalender tumpang tindih: `Calendar` (single, dropdown bulan/tahun), `RangeCalendar` (stock, tanpa dropdown), `PricingCalendar` (fork range + harga per hari untuk hotel booking), plus 2 picker paralel (`DatePicker` single, `RangeCalendarPicker` range). Original shadcn hanya punya SATU `Calendar` dengan `mode="single|range|multiple"`; date picker = komposisi Popover + Calendar. Tujuan: ikuti pattern shadcn asli — merge jadi `Calendar` (prop `mode`) + `DatePicker` (prop `mode`), tambah presets (dipakai `/emails`: Today/Yesterday/Last 3/7/15/30 days), mobile-first, migrasi semua call site di 3 repo, hapus `range-calendar/` + `range-calendar-picker/` + `pricing-calendar/`, sync 3 repo, perbarui docs UI levenium dengan semua variant seperti halaman shadcn base calendar.

**Kendala teknis kunci** (verifikasi source GitHub shadcn + reka-ui 2.10.1): React shadcn pakai react-day-picker yang native multi-mode. reka-ui memisah `CalendarRoot` (single/multiple via prop `multiple: boolean`) vs `RangeCalendarRoot` (range, `DateRange {start,end}`) dengan injection context BERBEDA — child primitive `Calendar*` tidak bisa dipakai dalam `RangeCalendarRoot`. Merged `Calendar.vue` wajib branch root internal; sub-wrapper memilih primitive reka dinamis via provide/inject. Kedua root expose slot props identik `{ date, grid, weekDays }` → markup dalam bisa dishare via `createReusableTemplate`. Mapping shadcn→reka: `month`/`onMonthChange` → `placeholder`/`v-model:placeholder`; `defaultMonth` → `defaultPlaceholder`; `fixedWeeks` sama.

**Fakta terverifikasi**:
- 3 lokasi fisik `components/ui` (bukan 4): pmone `frontend/app/components/ui/`, pmone-events `~/Frontend/pmone-events/layers/base/app/components/ui/`, levenium `~/Frontend/levenium/layers/ui/app/components/ui/`. Kelima dir target byte-identical di 3 repo.
- pmone-events: ZERO pemakaian langsung (hanya `BookingStep1Dates.vue` via PricingCalendar).
- `pricing-calendar/` import primitive reka langsung (bukan dari `range-calendar/` lokal); `time-picker`/`time-range-picker` tidak terkait.
- `check-theming-sync.sh` TIDAK bisa deteksi dir yang hanya tersisa di events/levenium → hapus harus dicek manual `ls` di 3 repo.
- `custom-field/CustomFieldRenderer.vue` ada DI DALAM tree synced dan import `../range-calendar-picker` → migrasinya ikut di-copy ke 2 repo lain.
- Semua theme CSS KECUALI `mono` (default aktif) sudah punya `--cell-size`/`--cell-radius` di `.cn-calendar` tapi TIDAK dikonsumsi apapun. Jangan aktifkan (akan mengubah ukuran cell di 8 theme) → pakai var privat baru `--cn-calendar-cell-size`.
- Bug laten: `Calendar.vue` tidak meng-omit `yearRange` sebelum forward → bocor jadi attr DOM. Diperbaiki saat merge.
- Levenium docs auto-glob (`import.meta.glob`) untuk registry/sidebar/illustrations → hapus file = nav bersih otomatis; `/ui/range-calendar` lama jatuh ke DocsNotFound (acceptable). `vite-ui-redirect.ts` regex-based, aman selama tidak ada import tersisa.
- Alias `@/components/ui/*` resolve di KETIGA repo (pmone: app alias; events: layers/base/app; levenium: redirect plugin) → file shared baru aman pakai `@/` dan tetap byte-identical.
- `useMediaQuery` sudah ada preseden dalam ui tree (`DialogResponsive.vue`, `SidebarProvider.vue`).

---

## 1. Merged Calendar (`components/ui/calendar/`)

### 1a. `context.ts` (baru)
```ts
import { computed, inject, provide, type ComputedRef, type InjectionKey } from "vue";

export type CalendarMode = "single" | "multiple" | "range";
export const CALENDAR_MODE_KEY: InjectionKey<ComputedRef<CalendarMode>> = Symbol("cn-calendar-mode");

export function provideCalendarMode(mode: ComputedRef<CalendarMode>) { provide(CALENDAR_MODE_KEY, mode); }
export function useCalendarMode(): ComputedRef<CalendarMode> {
  return inject(CALENDAR_MODE_KEY, computed(() => "single" as CalendarMode));
}
```
Default "single" supaya sub-wrapper tetap bekerja standalone.

### 1b. `Calendar.vue` (rewrite)
```ts
const props = withDefaults(defineProps<
  Omit<CalendarRootProps, "modelValue" | "multiple"> &
  Partial<Pick<RangeCalendarRootProps, "allowNonContiguousRanges" | "maximumDays" | "fixedDate" | "isDateHighlightable">> & {
    mode?: CalendarMode;                                    // "single"
    modelValue?: DateValue | DateValue[] | DateRange | null;
    multiple?: boolean;                                     // legacy alias, dihormati saat mode tidak diisi
    class?: HTMLAttributes["class"];
    layout?: LayoutTypes;                                   // "month-and-year"; null → CalendarHeading polos
    yearRange?: DateValue[];
  }
>(), { mode: "single", layout: "month-and-year", modelValue: undefined });

const emits = defineEmits<{
  "update:modelValue": [value: DateValue | DateValue[] | DateRange | undefined];
  "update:placeholder": [value: DateValue];
  "update:startValue": [value: DateValue | undefined];      // range only
  "update:validModelValue": [value: DateRange];             // range only
}>();
```
- Template: `CalendarRoot v-if="mode !== 'range'"` / `RangeCalendarRoot v-else`; markup dalam (header + nav absolut + month/year Select templates + grid loop — persis markup sekarang) didefinisikan SEKALI via `createReusableTemplate` (pattern yang sudah dipakai file ini) dan direuse di kedua branch.
- `isMultiple = computed(() => props.mode === "multiple" || (props.mode === "single" && !!props.multiple))` → bind eksplisit `:multiple` di `CalendarRoot`.
- Dua delegated props: `delegatedSingle = reactiveOmit(props, "class","layout","yearRange","mode","placeholder","multiple","allowNonContiguousRanges","maximumDays","fixedDate","isDateHighlightable")`; `delegatedRange = reactiveOmit(props, "class","layout","yearRange","mode","placeholder","multiple")` (sekaligus fix bug leak `yearRange`). Dua `useForwardPropsEmits`, satu per branch.
- Pertahankan: `useVModel` passive placeholder, `weekday-format="short"`, `:week-starts-on="1"`, `data-slot="calendar"`, root class `cn-calendar group/calendar bg-background in-data-[slot=card-content]:bg-transparent in-data-[slot=popover-content]:bg-transparent` untuk KEDUA mode + prepend `[--cn-calendar-cell-size:--spacing(8)]` (sebelum `props.class` supaya consumer bisa override via tw-merge).
- `provideCalendarMode(...)` sebelum render.
- Slots dipertahankan: `calendar-heading` `{ date, month, year }`, `calendar-prev-icon`, `calendar-next-icon`. **Slot BARU `#day`** (scoped `{ day: DateValue, month: DateValue }`) — pengganti generik pattern shadcn "custom day cells":
```vue
<CalendarCellTrigger :day="weekDate" :month="month.value">
  <template v-if="$slots.day" #default>
    <slot name="day" :day="weekDate" :month="month.value" />
  </template>
</CalendarCellTrigger>
```
  `v-if="$slots.day"` LOAD-BEARING — slot kosong unconditional akan menekan angka hari.
- TypeScript: union modelValue tidak akan memuaskan vue-tsc strict di `v-bind` — diterima (repo tidak jalankan typecheck), jangan tambah cast yang mengaburkan runtime.
- `layout`/`yearRange`/dropdown bulan-tahun bekerja di SEMUA mode (range akhirnya dapat dropdown — improvement disengaja).
- Week numbers (shadcn `showWeekNumber`) SENGAJA tidak diport — primitive reka tidak support.

### 1c. 11 sub-wrapper di-unify (satu set file, nama tetap)
Setiap file inject mode → `<component :is="isRange ? RekaRangeCalendarX : RekaCalendarX">`. Class:
- `CalendarCell.vue` — branch: single = `[&:has([data-selected])]:rounded-md [&:has([data-selected])]:bg-accent`; range = port VERBATIM class `RangeCalendarCell.vue` sekarang (`[&:has([data-selected])]:bg-primary` + rounded l/r di selection-start/end).
- `CalendarCellTrigger.vue` — base shared (buttonVariants ghost, today-dot, disabled, unavailable, outside-view) + branch: single = set `data-[selected]:bg-primary...` sekarang; range = port VERBATIM set `data-[selection-start]/[selection-end]` dari `RangeCalendarCellTrigger.vue`. Tambah `group` di kedua branch (untuk styling konten slot #day via `group-data-*`), ganti `size-8` → `size-(--cn-calendar-cell-size)`, dan fallback slot eksplisit `<slot>{{ day.day }}</slot>`.
- 9 lainnya (Grid/GridBody/GridHead/GridRow/HeadCell/Header/Heading/Prev/Next): class versi `calendar/` untuk kedua mode (HeadCell `flex-1`, nav non-absolute — posisi dimiliki nav wrapper Calendar.vue, `cn-rtl-flip` dipertahankan).

### 1d. `index.ts`
Ekspor 12 komponen existing + `LayoutTypes` + re-export `CalendarMode`, `CALENDAR_MODE_KEY`, `provideCalendarMode`, `useCalendarMode` dari `./context`.

---

## 2. Merged DatePicker (`components/ui/date-picker/`)

### API
```ts
export type DatePickerMode = "single" | "range";
export type DateRangeValue = { start: Date | null; end: Date | null };
export type DatePickerPresetValue = Date | DateRangeValue;
export interface DatePickerPreset {
  label: string;
  value: DatePickerPresetValue | (() => DatePickerPresetValue); // getter direkomendasikan ("Today" tidak basi)
}

// props (withDefaults):
mode?: DatePickerMode = "single"
modelValue?: Date | null | DateRangeValue = null   // Date|null (single) · {start,end} (range — shape existing)
withTime?: boolean = false                         // single only; diabaikan di range
disabled?: boolean = false
placeholder?: string                               // default per mode: "Pick a date" / "Pick a date range"
defaultHour?: number = 9; defaultMinute?: number = 0
disableFutureDates?; disablePastDates?: boolean = false
minYear?; maxYear?: number
min?: Date | null; max?: Date | null; placeholderDate?: Date | null
numberOfMonths?: number                            // default: range 2 desktop / 1 mobile; single 1
size?: "sm" | "default" | "lg" = "default"         // sm → h-8 · lg → h-10
layout?: LayoutTypes = "month-and-year"            // passthrough ke Calendar
presets?: DatePickerPreset[] = []

// emits: "update:modelValue": [Date | null | DateRangeValue]
// slots: #presets="{ apply }" — custom preset UI menggantikan panel default
```

### Internals
- Logika kedua komponen lama dipindah utuh, switch by mode: single = DatePicker persis (auto emit+close saat pilih; flow withTime Apply/Cancel/Clear; resync saat popover open). Range = RangeCalendarPicker persis (sync-on-open, display text compact dengan elision tahun sama, close saat kedua ujung terpilih, Clear row saat ada value).
- **Default `size="default"`** — 26 call site single existing render tidak berubah. Default lama RangeCalendarPicker "sm" ditangani migrasi (tambah `size="sm"` eksplisit di call site yang mengandalkannya).
- **Presets**: render bila `presets.length || $slots.presets`. Klik → resolve getter → set seleksi internal + emit model + set `placeholderOverride` ref (ikut computed `calendarPlaceholder`, di-reset saat popover open) supaya bulan visible lompat → **auto-close** (kecuali single+withTime: tetap buka untuk Apply). Preset aktif = perbandingan per-hari kalender (`sameDay` helper, bukan ms) → `bg-accent text-accent-foreground`.
- **Mobile**: `const isDesktop = useMediaQuery("(min-width: 640px)")`; bulan efektif `isDesktop ? (numberOfMonths ?? (mode==="range" ? 2 : 1)) : 1` (eksplisit pun di-clamp 1 di mobile; JS media query, bukan CSS hide — hide grid reka merusak keyboard/selection). `PopoverContent class="w-auto max-w-[calc(100vw-0.5rem)] rounded-xl p-0"`.
- Layout panel presets: wrapper `flex flex-col sm:flex-row`. Mobile = chip row horizontal scrollable DI ATAS kalender (`flex gap-1 overflow-x-auto border-b p-2`, button `shrink-0`); `sm+` = sidebar vertikal kiri (`sm:w-32 sm:flex-col sm:gap-0.5 sm:overflow-visible sm:border-b-0 sm:border-r`), button `variant="ghost" size="sm"` + `justify-start text-xs sm:text-sm tracking-tight`.
- Trigger: markup `cn-input` sekarang + `size === "sm" && "h-8"`, `size === "lg" && "h-10"`; empty-state mode-aware.
- Pakai import eksplisit `@/components/ui/*` (gaya RangeCalendarPicker) — resolve di 3 repo, tidak bergantung auto-import.
- `index.ts`: export component + type `DatePickerMode`, `DateRangeValue`, `DatePickerPreset`, `DatePickerPresetValue`.

---

## 3. Pricing merge → domain wrapper `components/hotels/`

### `app/components/hotels/pricing.ts` (baru, byte-identical 3 repo)
Pindahkan VERBATIM dari `ui/pricing-calendar/utils.ts`: `PricingDay`, `PricingMap`, `formatIsoDate`, `formatRupiahShort`. Tambah:
```ts
export function visibleMonthRange(start: DateValue, months: number): { start: string; end: string }
// port emitMonthChange(): hari-1 bulan start → hari terakhir bulan (start + months - 1), "YYYY-MM-DD"
```

### `app/components/hotels/PricingCalendar.vue` (baru, byte-identical 3 repo)
Props/emits SAMA dengan komponen lama (subset RangeCalendarRootProps + `pricingData`, `isLoading`, `numberOfMonths=2`, `goodPriceThreshold`, `class`; emits `update:modelValue`, `update:placeholder`, `monthChange`). Port VERBATIM: `isDateDisabledMerged` (matcher user AND `available===0 || rate==null`), `internalPlaceholder` + watch `props.placeholder`, `monthHasPickableDate` + `effectiveNumberOfMonths` (trim bulan kosong, anchor `props.placeholder` — pertahankan komentar penjelasnya), watch `[internalPlaceholder, effectiveNumberOfMonths]` immediate → `emits("monthChange", visibleMonthRange(...))`. Template:
```vue
<Calendar
  mode="range" :layout="null"
  v-model:placeholder="internalPlaceholder"
  :model-value="modelValue" @update:model-value="(v) => emits('update:modelValue', v)"
  :min-value="minValue" :max-value="maxValue"
  :is-date-disabled="isDateDisabledMerged"
  :number-of-months="effectiveNumberOfMonths"
  :class="cn('rounded-md border p-3 [--cn-calendar-cell-size:--spacing(12)] [&_[data-outside-view]]:invisible', props.class)"
>
  <template #day="{ day }">
    <span class="flex flex-col items-center justify-center gap-1">
      <span class="text-sm leading-none">{{ day.day }}</span>
      <Skeleton v-if="showSkeleton(day)" class="h-2 w-8 rounded-sm" />
      <span v-else-if="priceLabel(day)" class="text-[11px] leading-none font-medium"
        :class="isGoodPrice(day)
          ? 'text-success-foreground group-data-[selected]:text-primary-foreground group-data-[selection-start]:text-primary-foreground group-data-[selection-end]:text-primary-foreground'
          : 'text-muted-foreground group-data-[selected]:text-primary-foreground group-data-[selection-start]:text-primary-foreground group-data-[selection-end]:text-primary-foreground'">
        {{ priceLabel(day) }}
      </span>
    </span>
  </template>
</Calendar>
```
- Helper per-hari jadi fungsi biasa atas `props.pricingData` (`cellOf`, `showSkeleton`, `priceLabel`, `isGoodPrice`) — tidak perlu provide/inject lagi.
- `:layout="null"` mempertahankan heading polos booking calendar sekarang.
- Alasan lokasi: Rupiah/hotel-domain, bukan ui generik; `hotels/` sudah jadi set cross-repo copy-paste-maintained (BookingStep1Dates); nama komponen tetap `PricingCalendar` → `BookingStep1Dates.vue` cukup ganti 1 baris import, template nol perubahan. Tidak inline karena demo page pmone adalah konsumen kedua.
- `data-slot` berubah `pricing-calendar` → `calendar` (tidak ada CSS yang mereferensikan — verified).

---

## 4. Tabel migrasi call site

Ganti import `range-calendar-picker` → `{ DatePicker } from "@/components/ui/date-picker"` (di CustomFieldRenderer: hapus import lama, DatePicker sudah diimport).

| # | File (pmone kecuali dicatat) | Perubahan |
|---|---|---|
| 1 | `app/pages/emails/index.vue` :166, :425 | `<DatePicker mode="range" v-model="dateRange" size="sm" placeholder="Date range" :presets="datePresets" />` + `datePresets` (§5) |
| 2 | `app/pages/emails/analytics.vue` :16, :124 | sama seperti #1 |
| 3 | `project/PaymentGatewayReconciliationDialog.vue` :16, :149 | `<DatePicker mode="range" v-model="dateRange" size="sm" placeholder="Date range" />` — **`size="sm"` eksplisit** (default lama) |
| 4 | `project/PaymentGatewaySettlementDialog.vue` :16, :106 | sama seperti #3 |
| 5 | `project/PaymentGatewayTransactionsDialog.vue` :63, :174 | sama seperti #3 |
| 6 | `guest/FormGuest.vue` :230, :10 | `mode="range"`, props min/max/number-of-months tetap, drop `size="default"` (jadi default) |
| 7 | `hotel/AllotmentsPanel.vue` :54, :214 | `mode="range"`, drop `size="default"` |
| 8 | `ui/custom-field/CustomFieldRenderer.vue` :131, :372 (SYNCED) | `<DatePicker mode="range" :model-value="dateRangeValue" :disabled="disabled" :placeholder="normalized.placeholder || 'Pick a date range'" @update:model-value="handleDateRange" />`; hapus import :372 |
| 9 | `appearance/showcase/preview-02/components/UpcomingPayments.vue` | TANPA perubahan (mode default single, `:layout="null"` tetap jalan) — verifikasi saja |
| 10 | `hotels/BookingStep1Dates.vue` (pmone + events + levenium) | import → `import PricingCalendar from "./PricingCalendar.vue";` — template nol perubahan |
| 11 | `pages/demo/pricing-calendar.vue` (pmone) | import → `@/components/hotels/PricingCalendar.vue` + `type { PricingMap } from "@/components/hotels/pricing"` — template nol perubahan |
| — | 26 call site `<DatePicker>` single | TANPA perubahan (default mempertahankan perilaku) |

Model range tetap `{ start: Date|null, end: Date|null }` → tidak ada konsumen ubah bentuk data.

## 5. Presets /emails (kedua halaman, `$dayjs` sudah in scope)

```js
const lastNDays = (n) => () => ({
  start: $dayjs().subtract(n - 1, "day").toDate(),
  end: $dayjs().toDate(),
});
const datePresets = [
  { label: "Today", value: lastNDays(1) },
  { label: "Yesterday", value: () => ({ start: $dayjs().subtract(1, "day").toDate(), end: $dayjs().subtract(1, "day").toDate() }) },
  { label: "Last 3 days", value: lastNDays(3) },
  { label: "Last 7 days", value: lastNDays(7) },
  { label: "Last 15 days", value: lastNDays(15) },
  { label: "Last 30 days", value: lastNDays(30) },
];
```
Deep `watch(dateRange)` + `toYmd` existing tidak diubah. Default halaman (subtract 29 hari) = "Last 30 days" → langsung ter-highlight aktif.

## 6. Penghapusan + sync 3 repo

1. pmone: hapus `ui/range-calendar/` (13 file), `ui/range-calendar-picker/` (2), `ui/pricing-calendar/` (15).
2. Copy byte-identical ke pmone-events (`layers/base/app/`) dan levenium (ui → `layers/ui/app/components/ui/`; hotels → `layers/base/app/components/hotels/`): `ui/calendar/`, `ui/date-picker/`, `ui/custom-field/CustomFieldRenderer.vue`, `hotels/pricing.ts`, `hotels/PricingCalendar.vue`, `hotels/BookingStep1Dates.vue` (levenium: pertahankan gaya import `@/`-alias file itu; pmone-events byte-identical dengan pmone).
3. Hapus 3 dir yang sama di kedua repo.
4. `bash frontend/scripts/check-theming-sync.sh` → hijau; lalu `ls` manual memastikan 3 dir hilang di SEMUA repo (script tidak deteksi dir sisa di events/levenium).

## 7. Docs levenium (`apps/ui/app/components/ui-docs/`)

Hapus: `registry/{range-calendar,range-calendar-picker,pricing-calendar}.js`, `examples/{range-calendar,range-calendar-picker,pricing-calendar}/`, `illustrations/{range-calendar,range-calendar-picker,pricing-calendar}.vue` (glob-driven, tanpa edit index).

`registry/calendar.js` **rewrite** — parity halaman shadcn base calendar (minus week numbers, dicatat di deskripsi):
| section | example | isi |
|---|---|---|
| `default` | keep | single |
| `range` | rewrite | `<Calendar mode="range">` (sekarang masih cheat import RangeCalendar) |
| `multiple` | edit | `mode="multiple"` |
| `layouts` | baru | month-and-year / month-only / year-only / `:layout="null"` |
| `number-of-months` | baru (port) | range 2 bulan |
| `min-max` | baru (port) | minValue/maxValue |
| `disabled-dates` | baru | `:is-date-disabled` weekend |
| `presets` | baru | komposisi Button + `v-model` + `v-model:placeholder` (ala shadcn #presets) |
| `day-slot` | baru | "Custom day cells" — demo harga self-contained via `#day` + `[--cn-calendar-cell-size:--spacing(12)]` |

apiReference: `mode` (sekarang nyata), `modelValue` union, `layout`, `yearRange`, range-only props, events `update:startValue`/`update:validModelValue`, slot `day` (`{ day, month }`, styling state via `group-data-*`). Anatomy tetap (nama tidak berubah).

`registry/date-picker.js` **rewrite** — sections: `default`, `range` (baru), `presets` (baru: 6 preset /emails), `with-time`, `sizes` (baru: sm/default/lg), `months` (baru: range `:number-of-months="1"` + min/max), `disabled`. apiReference: `mode`, `size`, `presets` (shape + getter + auto-close + jump bulan), `numberOfMonths` responsive, `layout`, modelValue union; slot `#presets`. Buat/edit example SFC sesuai tabel; sisanya keep.

Grep `apps/ui` untuk sisa referensi `range-calendar`/`pricing-calendar` (guides, showcase) dan bersihkan.

## 8. Urutan eksekusi

1. **pmone Calendar merge**: `calendar/context.ts` → 11 sub-wrapper (port class range VERBATIM dari file `range-calendar/` SEBELUM hapus) → `Calendar.vue` → `index.ts`.
2. **pmone DatePicker merge**: `DatePicker.vue` + `index.ts`.
3. **pmone pricing wrapper**: `hotels/pricing.ts` + `hotels/PricingCalendar.vue`; migrasi `BookingStep1Dates.vue` + `pages/demo/pricing-calendar.vue`.
4. **pmone call sites**: tabel §4 baris 1-8 + presets §5.
5. **pmone hapus 3 dir** + grep gate: `grep -rn "ui/range-calendar\|ui/range-calendar-picker\|ui/pricing-calendar\|RangeCalendarPicker" app/` → nol. Sisa yang EXPECTED: `PricingCalendar` di hotels/demo, import `RangeCalendar*` dari `reka-ui` di dalam `ui/calendar/`.
6. **Verifikasi browser pmone** (dev server localhost:3000; JANGAN build/typecheck) — checklist §9.
7. **Sync pmone-events** (copy §6 + delete) → **sync levenium** (idem + hotels).
8. **`check-theming-sync.sh`** hijau + `ls` manual 3 repo.
9. **Docs levenium** §7; jalankan `pnpm dev` di `apps/ui`; cek `/ui/calendar`, `/ui/date-picker`, `/ui/range-calendar` → DocsNotFound, sidebar bersih.
10. **Grep sweep final** 3 repo: `ui/range-calendar`, `ui/range-calendar-picker`, `ui/pricing-calendar`, `RangeCalendarPicker` → nol.
11. **pmone-events smoke**: `pnpm dev` salah satu app boot tanpa error import.

## 9. Checklist verifikasi (browser + grep saja)

- `/emails` + `/emails/analytics`: trigger h-8; presets sidebar desktop / chip row di ~375px (1 bulan); tiap preset set range + jump bulan + close + refetch; "Last 30 days" aktif saat buka; manual range tetap close-on-complete; Clear jalan.
- 1 form single-date (mis. `/logs` atau `/account/profile`) tidak berubah; 1 form with-time: flow Apply/Cancel/Clear.
- FormGuest (min/max, 1 bulan), 1 dialog PaymentGateway (popover di atas dialog, h-8), AllotmentsPanel, custom-field `date_range`.
- `/demo/pricing-calendar`: 4 variant — harga + label hijau threshold, sold-out disabled, trim bulan kosong, skeleton saat loading, `monthChange` fire saat nav, label harga flip primary-foreground saat selected, outside-view invisible, cell size-12.
- Hotel booking Step 1 (bila reachable): pilih range → emit check-in/out, monthChange lazy-load harga.
- Showcase preview-02: heading polos (`layout=null`), single select jalan.
- QA visual range vs sebelum: bar primary kontinu, rounding ujung, today dot, state disabled/unavailable.
- `check-theming-sync.sh` hijau; grep nol.

## 10. Risiko

1. TS: union modelValue vs tipe root yang lebih sempit → tidak lolos vue-tsc strict; diterima (repo tidak typecheck).
2. Perubahan visual range (DISENGAJA): range mewarisi root `cn-calendar` (theming, nav absolut, dropdown layout, HeadCell `flex-1`); popover range DatePicker kehilangan tampilan `p-3` polos lama → wajib eyeball di step 6.
3. Fallback slot CellTrigger `{{ day.day }}` + `v-if="$slots.day"` di Calendar.vue = load-bearing (slot kosong menekan angka hari).
4. Rantai `update:placeholder` (root reka → v-model:placeholder → passive useVModel → emit) menggerakkan `monthChange` pricing → verifikasi di demo page (nav + trimming emit window benar).
5. `--cn-calendar-cell-size` via tw-merge: bila override tidak nempel, fallback descendant override di class wrapper pricing. Var theme `--cell-size`/`--cell-radius` yang sudah ada TETAP tidak dipakai (tugas alignment theming terpisah).
6. Default `size` range "sm"→"default" = satu-satunya breaking API; dimitigasi `size="sm"` eksplisit (re-grep `RangeCalendarPicker` sebelum migrasi untuk call site baru).
7. Hover preview `data-highlighted` (range in-progress) hari ini tidak di-style dan TETAP tidak (preservasi perilaku); follow-up opsional.
8. Blind spot sync script terhadap dir sisa di events/levenium → `ls` manual.
9. Single+withTime+preset: popover tetap buka (butuh jam) — didokumentasikan di docs date-picker.

## Rekomendasi tambahan

1. **Gratis dari merge**: range calendar dapat dropdown bulan/tahun (`layout`) + theming `cn-calendar` konsisten; bug leak `yearRange` ikut terperbaiki.
2. **Follow-up opsional**: styling `data-[highlighted]:bg-accent` untuk preview hover range (reka expose, sekarang polos).
3. **Konsolidasi masa depan**: `analytics/DateRangeSelect.vue` (dropdown preset ber-key string) kandidat diganti `DatePicker :presets` supaya satu pattern (di luar scope ini).
4. **Theming**: var `--cell-size`/`--cell-radius` yang sudah ada di 8 theme saat ini mati; setelah merge stabil bisa dialihkan ke `--cn-calendar-cell-size` supaya ukuran cell ikut theme (tugas terpisah, ada risiko visual).
5. Week numbers tidak diport (limitasi reka-ui); kalau butuh parity penuh shadcn perlu custom grid.
