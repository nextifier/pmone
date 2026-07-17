<script setup lang="ts">
import { CalendarDate, getLocalTimeZone, today as todayFn } from "@internationalized/date";
import PricingCalendar from "@/components/hotels/PricingCalendar.vue";
import type { PricingMap } from "@/components/hotels/pricing";
import { computed, ref } from "vue";

definePageMeta({ layout: "default" });
usePageMeta(null, { title: "Pricing Calendar" });

const today = todayFn(getLocalTimeZone());

type DateRange = { start: CalendarDate | undefined; end: CalendarDate | undefined };

const variant1 = ref<DateRange>({ start: undefined, end: undefined });
const variant2 = ref<DateRange>({ start: undefined, end: undefined });
const variant3 = ref<DateRange>({ start: undefined, end: undefined });

function buildMonth(
  year: number,
  month: number,
  opts: { soldOut?: number[]; baseRate?: number; weekendRate?: number; skipPast?: boolean } = {}
): PricingMap {
  const map: PricingMap = {};
  const baseRate = opts.baseRate ?? 1_500_000;
  const weekendRate = opts.weekendRate ?? 1_850_000;
  const lastDay = new Date(year, month, 0).getDate();
  const pad = (n: number) => String(n).padStart(2, "0");
  const todayDate = new Date(today.year, today.month - 1, today.day);

  for (let d = 1; d <= lastDay; d++) {
    const dateObj = new Date(year, month - 1, d);
    if (opts.skipPast !== false && dateObj < todayDate) {
      continue;
    }
    const key = `${year}-${pad(month)}-${pad(d)}`;
    const dow = dateObj.getDay();
    const isWeekend = dow === 0 || dow === 6;
    const soldOut = opts.soldOut?.includes(d) ?? false;
    map[key] = {
      rate: isWeekend ? weekendRate : baseRate,
      available: soldOut ? 0 : isWeekend ? 3 : 8,
    };
  }
  return map;
}

const startPlaceholder = computed(() => new CalendarDate(today.year, today.month, 1));

const pricingV1 = computed<PricingMap>(() => {
  const next = new CalendarDate(today.year, today.month, 1).add({ months: 1 });
  return {
    ...buildMonth(today.year, today.month),
    ...buildMonth(next.year, next.month),
  };
});

const pricingV2 = computed<PricingMap>(() => {
  const next = new CalendarDate(today.year, today.month, 1).add({ months: 1 });
  return {
    ...buildMonth(today.year, today.month, { soldOut: [5, 6, 7, 15, 16, 22] }),
    ...buildMonth(next.year, next.month, { soldOut: [3, 4, 18, 19, 20] }),
  };
});

const pricingV3 = computed<PricingMap>(() => buildMonth(today.year, today.month));

const variant4Loading = ref(true);
let firedDemoLoad = false;
function startVariant4Demo() {
  if (firedDemoLoad) return;
  firedDemoLoad = true;
  setTimeout(() => {
    variant4Loading.value = false;
  }, 1800);
}
onMounted(startVariant4Demo);

const variant4Pricing = computed<PricingMap>(() =>
  variant4Loading.value ? {} : buildMonth(today.year, today.month)
);

const formatRange = (r: DateRange) => {
  if (!r.start || !r.end) return "-";
  return `${r.start.toString()} → ${r.end.toString()}`;
};
</script>

<template>
  <div class="container overflow-hidden pt-4 pb-24">
    <div class="mb-10 flex flex-col gap-y-2.5 lg:items-center lg:text-center">
      <h1 class="text-4xl font-medium tracking-tighter sm:text-5xl">Pricing Calendar</h1>
      <p class="text-muted-foreground max-w-3xl text-base tracking-tight text-pretty sm:text-lg">
        Range calendar yang menampilkan harga per malam langsung di tiap tanggal. Cell otomatis
        di-disable saat allotment habis. Built on reka-ui dengan format harga Rupiah singkat
        (Rp1,5jt / Rp850rb).
      </p>
    </div>

    <section class="mb-10">
      <h2 class="mb-2 text-xl font-medium tracking-tighter">Default (2 bulan)</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight">
        Default menampilkan 2 bulan side-by-side di desktop, stack vertical di mobile. Weekday
        Rp1,5jt, weekend Rp1,85jt. Harga di bawah Rp1,6jt tampil hijau (good price).
      </p>
      <div class="bg-background flex justify-center rounded-2xl border p-4 sm:p-8">
        <PricingCalendar
          v-model="variant1"
          :placeholder="startPlaceholder"
          :pricing-data="pricingV1"
          :good-price-threshold="1_600_000"
          :min-value="today"
        />
      </div>
      <p class="text-muted-foreground mt-3 text-sm tracking-tight">
        Range:
        <code class="bg-muted rounded px-1 py-0.5 text-xs">{{ formatRange(variant1) }}</code>
      </p>
    </section>

    <section class="mb-10">
      <h2 class="mb-2 text-xl font-medium tracking-tighter">Dengan tanggal sold out</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight">
        Tanggal dengan
        <code class="bg-muted rounded px-1 py-0.5 text-xs">available === 0</code>
        otomatis disabled dan tidak bisa dipilih.
      </p>
      <div class="bg-background flex justify-center rounded-2xl border p-4 sm:p-8">
        <PricingCalendar
          v-model="variant2"
          :placeholder="startPlaceholder"
          :pricing-data="pricingV2"
          :min-value="today"
        />
      </div>
      <p class="text-muted-foreground mt-3 text-sm tracking-tight">
        Range:
        <code class="bg-muted rounded px-1 py-0.5 text-xs">{{ formatRange(variant2) }}</code>
      </p>
    </section>

    <section class="mb-10">
      <h2 class="mb-2 text-xl font-medium tracking-tighter">Single month</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight">
        Override prop
        <code class="bg-muted rounded px-1 py-0.5 text-xs">numberOfMonths</code>
        ke 1 untuk layout yang lebih kompak.
      </p>
      <div class="bg-background flex justify-center rounded-2xl border p-4 sm:p-8">
        <PricingCalendar
          v-model="variant3"
          :placeholder="startPlaceholder"
          :pricing-data="pricingV3"
          :number-of-months="1"
          :min-value="today"
        />
      </div>
      <p class="text-muted-foreground mt-3 text-sm tracking-tight">
        Range:
        <code class="bg-muted rounded px-1 py-0.5 text-xs">{{ formatRange(variant3) }}</code>
      </p>
    </section>

    <section class="mb-10">
      <h2 class="mb-2 text-xl font-medium tracking-tighter">Loading state</h2>
      <p class="text-muted-foreground mb-4 text-sm tracking-tight">
        Saat
        <code class="bg-muted rounded px-1 py-0.5 text-xs">isLoading</code>
        true dan
        <code class="bg-muted rounded px-1 py-0.5 text-xs">pricingData</code>
        masih kosong, tiap cell menampilkan Skeleton di tempat harga. Demo ini akan otomatis selesai
        loading dalam 2 detik.
      </p>
      <div class="bg-background flex justify-center rounded-2xl border p-4 sm:p-8">
        <PricingCalendar
          :placeholder="startPlaceholder"
          :pricing-data="variant4Pricing"
          :is-loading="variant4Loading"
          :number-of-months="1"
          :min-value="today"
        />
      </div>
    </section>
  </div>
</template>
