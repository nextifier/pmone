<template>
  <div class="space-y-12 pb-16">
    <!-- Header -->
    <div class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:items-start sm:justify-between">
      <div class="min-w-0 space-y-1.5">
        <div class="flex items-center gap-x-2.5">
          <Icon name="hugeicons:chart-line-data-02" class="size-5 shrink-0 sm:size-6" />
          <h1 class="page-title">Analytics</h1>
        </div>
        <p class="page-description truncate">
          Bookings, revenue and occupancy for {{ event?.title }}
        </p>
        <p
          v-if="updatedAgo"
          class="text-muted-foreground flex items-center gap-x-1.5 text-xs tracking-tight sm:text-sm"
        >
          <span class="relative flex size-2 shrink-0">
            <span
              class="animate-ping-slow bg-success absolute inline-flex size-full rounded-full opacity-75"
            />
            <span class="bg-success relative inline-flex size-2 rounded-full" />
          </span>
          Updated {{ updatedAgo }}
        </p>
      </div>
      <div class="flex shrink-0 flex-wrap items-center gap-2">
        <DatePicker
          v-model="dateRange"
          mode="range"
          size="sm"
          align="end"
          disable-future-dates
          placeholder="All time"
          class="w-fit"
          :presets="analyticsRangePresets()"
        />
        <Button variant="outline" size="sm" as-child class="shrink-0">
          <NuxtLink :to="`${eventBase}/reservations`">
            <Icon name="hugeicons:arrow-left-01" class="size-4 shrink-0" />
            <span>Reservations</span>
          </NuxtLink>
        </Button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="pending" class="space-y-12">
      <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
        <Skeleton v-for="i in 6" :key="`k-${i}`" class="h-28 rounded-xl" />
      </div>
      <Skeleton class="h-80 rounded-xl" />
      <div class="grid gap-4 lg:grid-cols-2">
        <Skeleton class="h-72 rounded-xl" />
        <Skeleton class="h-72 rounded-xl" />
      </div>
    </div>

    <!-- Empty -->
    <div
      v-else-if="!hasData"
      class="flex flex-col items-center justify-center gap-y-4 py-20 text-center"
    >
      <div
        class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
      >
        <div class="translate-y-1.5 -rotate-6"><Icon name="hugeicons:chart-line-data-02" /></div>
        <div><Icon name="hugeicons:hotel-01" /></div>
        <div class="translate-y-1.5 rotate-6"><Icon name="hugeicons:calendar-02" /></div>
      </div>
      <div class="space-y-1">
        <h3 class="font-semibold tracking-tight">No data to analyse yet</h3>
        <p class="text-muted-foreground max-w-sm text-sm tracking-tight">
          Once guests start booking rooms, occupancy and revenue insights will appear here.
        </p>
      </div>
    </div>

    <!-- Content -->
    <template v-else>
      <!-- KPI hero -->
      <GridFill
        :count="6"
        :min-col-width="'200px'"
        rounded="xl"
        class="t-panel-slide"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '0ms' }"
      >
        <div class="flex flex-col items-center justify-center p-4">
          <ChartSemiCircle
            :value="s.rooms_sold"
            :max="Math.max(s.total_allotment, 1)"
            :center-label="`${s.occupancy_rate ?? 0}% booked`"
            show-max
            class="w-full max-w-[200px]"
          />
        </div>
        <div v-for="kpi in kpis" :key="kpi.label" class="flex flex-col items-start gap-y-2 p-4 sm:p-5">
          <Icon :name="kpi.icon" class="size-5" :class="kpi.color" />
          <div class="min-w-0">
            <span class="text-foreground text-sm font-medium tracking-tight">{{ kpi.label }}</span>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">{{ kpi.desc }}</p>
          </div>
          <NumberFlow
            v-if="kpi.type === 'count'"
            class="text-foreground -mb-1 cursor-pointer text-lg leading-tight font-medium tracking-tighter sm:text-xl"
            :value="kpi.value"
            locales="id-ID"
            :format="{ notation: expanded ? 'standard' : 'compact' }"
            :title="expanded ? 'Click to collapse' : 'Click for exact value'"
            @click="expanded = !expanded"
          />
          <NumberFlow
            v-else
            class="text-foreground -mb-1 cursor-pointer text-lg leading-tight font-medium tracking-tighter sm:text-xl"
            :value="expanded ? kpi.value : rupiahCompactParts(kpi.value).value"
            prefix="Rp"
            :suffix="expanded ? '' : rupiahCompactParts(kpi.value).suffix"
            locales="id-ID"
            :format="{ maximumFractionDigits: expanded ? 0 : 1 }"
            :title="expanded ? 'Click to collapse' : 'Click for exact value'"
            @click="expanded = !expanded"
          />
        </div>
      </GridFill>

      <!-- Bookings over time -->
      <section
        class="t-panel-slide"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '70ms' }"
      >
        <Card>
          <CardHeader class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-1.5">
              <CardTitle>Bookings over time</CardTitle>
              <CardDescription>
                How {{ metricMeta[metric].noun }} accumulated as the campaign ran.
              </CardDescription>
            </div>
            <Tabs v-model="metric" variant="segmented" class="w-full sm:w-fit">
              <TabsList class="w-full sm:w-auto">
                <TabsIndicator />
                <TabsTrigger
                  v-for="opt in metricOptions"
                  :key="opt.value"
                  :value="opt.value"
                  class="flex-1 sm:flex-none"
                >
                  {{ opt.label }}
                </TabsTrigger>
              </TabsList>
            </Tabs>
          </CardHeader>
          <CardContent>
            <ChartArea
              v-if="trendData.length > 1 && metric === 'revenue'"
              :data="trendData"
              :config="trendConfig"
              data-key="revenue"
              x-key="date"
              :svg-defs="areaGlowDefs"
              area-fill="url(#res-area-fill)"
              line-filter="url(#res-line-glow)"
              dot-filter="url(#res-dot-glow)"
              dots
              :dot-size="5"
              :stroke-width-by-key="{ revenue: 2 }"
              grid
              :y-tick-formatter="formatRupiahCompact"
              :margin="trendAreaMargin"
              class="h-64! w-full"
            />
            <ChartLine
              v-else-if="trendData.length > 1"
              :key="metric"
              :data="trendData"
              :config="trendConfig"
              :data-key="metric"
              gradient
              class="h-64! w-full"
            />
            <p v-else class="text-muted-foreground py-16 text-center text-sm tracking-tight">
              Not enough activity yet to draw a trend.
            </p>
          </CardContent>
        </Card>
      </section>

      <!-- Stay length + revenue by hotel -->
      <section
        v-if="stayData.length || hotelData.length"
        class="t-panel-slide space-y-4"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '140ms' }"
      >
        <div>
          <h2 class="text-base font-medium tracking-tight">Stay &amp; demand</h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            How long guests stay and which hotels earn the most.
          </p>
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle>Length of stay</CardTitle>
              <CardDescription>Booked rooms by number of nights</CardDescription>
            </CardHeader>
            <CardContent>
              <ChartBar
                v-if="stayData.length"
                :data="stayData"
                :config="{ count: { label: 'Rooms', color: 'var(--chart-1)' } }"
                data-key="count"
                x-key="idx"
                :x-tick-formatter="stayLabel"
                :svg-defs="barGradientDefs"
                bar-fill="url(#res-bar-grad)"
                :bar-max-width="80"
                :margin="{ top: 6, right: 10, bottom: 18, left: 22 }"
                class="h-56! w-full"
              />
              <p v-else class="text-muted-foreground py-14 text-center text-sm tracking-tight">
                No paid bookings yet.
              </p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle>Revenue by hotel</CardTitle>
              <CardDescription>Paid reservations per hotel</CardDescription>
            </CardHeader>
            <CardContent>
              <ChartBar
                v-if="hotelData.length"
                :data="hotelData"
                :config="{ value: { label: 'Revenue', color: 'var(--chart-1)' } }"
                data-key="value"
                x-key="idx"
                horizontal
                :x-tick-formatter="hotelLabel"
                :value-formatter="formatCurrency"
                :margin="hbarMargin"
                :style="{ height: `${Math.max(150, hotelData.length * 44)}px` }"
                class="w-full"
              />
              <p v-else class="text-muted-foreground py-14 text-center text-sm tracking-tight">
                No paid reservations yet.
              </p>
            </CardContent>
          </Card>
        </div>
      </section>

      <!-- Room type performance -->
      <section
        v-if="d.by_room_type.length"
        class="t-panel-slide space-y-4"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '210ms' }"
      >
        <div>
          <h2 class="text-base font-medium tracking-tight">Room type performance</h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            Rooms sold, nights and revenue for each room type.
          </p>
        </div>
        <div class="overflow-hidden rounded-xl border">
          <div
            class="text-muted-foreground bg-muted/40 hidden grid-cols-[2fr_1fr_1.4fr_1.2fr] gap-x-4 px-4 py-2.5 text-xs font-medium tracking-tight sm:grid"
          >
            <span>Room type</span>
            <span class="text-right">Rooms</span>
            <span class="text-right">Nights</span>
            <span class="text-right">Revenue</span>
          </div>
          <div class="divide-border divide-y">
            <div
              v-for="r in d.by_room_type"
              :key="r.room_type_id"
              class="grid grid-cols-2 gap-x-4 gap-y-2 px-4 py-3.5 sm:grid-cols-[2fr_1fr_1.4fr_1.2fr] sm:items-center"
            >
              <div class="col-span-2 space-y-2 sm:col-span-1">
                <span class="truncate text-sm font-medium tracking-tight">{{ r.name }}</span>
                <div class="bg-muted h-1.5 w-full max-w-[240px] overflow-hidden rounded-full">
                  <div
                    class="h-full rounded-full transition-[width] duration-700 ease-out"
                    :style="{ width: `${nightsPct(r)}%`, backgroundColor: 'var(--chart-1)' }"
                  />
                </div>
              </div>
              <div class="tabular-nums sm:text-right">
                <span class="text-muted-foreground text-xs tracking-tight sm:hidden">Rooms </span>
                <span class="text-sm tracking-tight">{{ formatNumber(r.rooms) }}</span>
              </div>
              <div class="tabular-nums sm:text-right">
                <span class="text-muted-foreground text-xs tracking-tight sm:hidden">Nights </span>
                <span class="text-sm tracking-tight">{{ formatNumber(r.nights) }}</span>
              </div>
              <div class="tabular-nums sm:text-right">
                <span class="text-muted-foreground text-xs tracking-tight sm:hidden">Revenue </span>
                <button
                  type="button"
                  class="cursor-pointer text-sm font-medium tracking-tight"
                  :title="expanded ? 'Click to collapse' : 'Click for exact value'"
                  @click="expanded = !expanded"
                >
                  {{ formatCurrency(r.revenue) }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Sales breakdown -->
      <section
        v-if="d.payment_channels.length || d.by_status.length"
        class="t-panel-slide space-y-4"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '280ms' }"
      >
        <div>
          <h2 class="text-base font-medium tracking-tight">Sales breakdown</h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            Where revenue came from and the state of every reservation.
          </p>
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
          <Card v-if="d.payment_channels.length">
            <CardHeader>
              <CardTitle>Payment channels</CardTitle>
              <CardDescription>Revenue by payment method</CardDescription>
            </CardHeader>
            <CardContent>
              <ChartBar
                :data="paymentChartData"
                :config="{ value: { label: 'Revenue', color: 'var(--chart-1)' } }"
                data-key="value"
                x-key="idx"
                horizontal
                :svg-defs="duotoneDefs"
                bar-fill="url(#res-duotone)"
                :x-tick-formatter="paymentChartLabel"
                :value-formatter="formatCurrency"
                :margin="hbarMargin"
                :style="{ height: `${Math.max(150, paymentChartData.length * 44)}px` }"
                class="w-full"
              />
            </CardContent>
          </Card>
          <Card v-if="d.by_status.length">
            <CardHeader>
              <CardTitle>Reservation status</CardTitle>
              <CardDescription>Every reservation by current state</CardDescription>
            </CardHeader>
            <CardContent>
              <ChartPie
                :data="statusData"
                :config="statusConfig"
                value-key="count"
                name-key="status"
                :radius="100"
                :arc-width="28"
                :corner-radius="5"
                :pad-angle="padAngle"
                :segment-fill="statusSegmentFill"
                segment-stroke-color="var(--background)"
                :active-index="statusActiveIndex"
                :active-outer-radius="113"
                :svg-defs="pieStripeDefs"
                :total="s.total_reservations"
                center-sub-label="bookings"
                class="mx-auto"
              />
              <div class="mt-4 flex flex-wrap justify-center gap-x-4 gap-y-1.5">
                <div
                  v-for="(o, i) in d.by_status"
                  :key="o.status"
                  class="flex items-center gap-x-1.5 text-sm tracking-tight"
                >
                  <span
                    class="size-2 shrink-0 rounded-[2px]"
                    :style="{ backgroundColor: statusColor(i) }"
                  />
                  <span class="text-muted-foreground">{{ o.label }}</span>
                  <span class="text-foreground font-medium tabular-nums">{{ formatNumber(o.count) }}</span>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </section>

      <!-- Guests + source -->
      <section
        v-if="d.by_nationality.length || d.top_guests.length || d.by_source.length"
        class="t-panel-slide space-y-4"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '350ms' }"
      >
        <div>
          <h2 class="text-base font-medium tracking-tight">Guests</h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            Where guests come from and who books the most.
          </p>
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
          <Card v-if="d.by_nationality.length">
            <CardHeader>
              <CardTitle>By nationality</CardTitle>
              <CardDescription>Reservations per guest nationality</CardDescription>
            </CardHeader>
            <CardContent>
              <ChartBar
                :data="nationalityData"
                :config="{ value: { label: 'Reservations', color: 'var(--chart-1)' } }"
                data-key="value"
                x-key="idx"
                horizontal
                :svg-defs="dotDefs"
                bar-fill="url(#res-dots)"
                :x-tick-formatter="nationalityLabel"
                :value-formatter="formatNumber"
                :margin="hbarMargin"
                :style="{ height: `${Math.max(150, nationalityData.length * 44)}px` }"
                class="w-full"
              />
            </CardContent>
          </Card>
          <Card v-if="d.top_guests.length">
            <CardHeader>
              <CardTitle>Top guests</CardTitle>
              <CardDescription>Highest spend this event</CardDescription>
            </CardHeader>
            <CardContent>
              <ul class="divide-border divide-y">
                <li
                  v-for="(guest, i) in d.top_guests"
                  :key="i"
                  class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                >
                  <div class="flex min-w-0 items-center gap-x-3">
                    <span
                      class="bg-muted text-muted-foreground squircle flex size-7 shrink-0 items-center justify-center text-xs font-medium tabular-nums"
                    >
                      {{ i + 1 }}
                    </span>
                    <div class="min-w-0">
                      <p class="truncate text-sm font-medium tracking-tight">{{ guest.name }}</p>
                      <p class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm">
                        {{ guest.nights }} nights · {{ guest.reservations }} bookings
                      </p>
                    </div>
                  </div>
                  <span class="shrink-0 text-sm font-medium tabular-nums tracking-tight">
                    {{ formatCurrency(guest.total_spent) }}
                  </span>
                </li>
              </ul>
            </CardContent>
          </Card>
        </div>

        <!-- Booking source -->
        <Card v-if="d.by_source.length">
          <CardHeader>
            <CardTitle>Booking source</CardTitle>
            <CardDescription>Where reservations were created</CardDescription>
          </CardHeader>
          <CardContent>
            <ChartBar
              :data="sourceData"
              :config="{ value: { label: 'Reservations', color: 'var(--chart-1)' } }"
              data-key="value"
              x-key="idx"
              horizontal
              :x-tick-formatter="sourceLabel"
              :value-formatter="formatNumber"
              :margin="hbarMargin"
              :style="{ height: `${Math.max(120, sourceData.length * 44)}px` }"
              class="w-full"
            />
          </CardContent>
        </Card>
      </section>
    </template>
  </div>
</template>

<script setup>
import { useMediaQuery } from "@vueuse/core";
import { useReservationAnalytics } from "@/composables/useReservationAnalytics";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["reservations.read"],
  layout: "app",
});

const props = defineProps({
  event: Object,
  project: Object,
});

const route = useRoute();

const eventBase = computed(
  () => `/projects/${route.params.username}/events/${route.params.eventSlug}`
);

usePageMeta(null, {
  title: computed(() => `Analytics · ${props.event?.title || "Event"}`),
});

// Empty = all-time, the dashboard's historical default; Clear returns to it.
const dateRange = ref({ start: null, end: null });

// Live feed: polls, refetches on focus, reacts to reservation mutations.
const { data: d, pending, lastUpdatedAt } = useReservationAnalytics(props.event?.id, "detail", {
  interval: 20000,
  range: dateRange,
});

// Ticking "Updated Xs ago" affordance so staff know how fresh the live feed is.
const nowTick = ref(Date.now());
let tickTimer = null;
onMounted(() => {
  tickTimer = setInterval(() => (nowTick.value = Date.now()), 10000);
});
onBeforeUnmount(() => tickTimer && clearInterval(tickTimer));

const updatedAgo = computed(() => {
  if (!lastUpdatedAt.value) return null;
  const secs = Math.max(0, Math.round((nowTick.value - lastUpdatedAt.value) / 1000));
  if (secs < 5) return "just now";
  if (secs < 60) return `${secs}s ago`;
  return `${Math.round(secs / 60)}m ago`;
});

const s = computed(() => d.value?.summary ?? {});
const hasData = computed(() => !!d.value && (s.value.total_reservations ?? 0) > 0);

// Staggered panel-reveal: flip open once data is ready so the sections slide +
// blur into place; per-section transition-delay staggers.
const revealed = ref(false);
watch(
  () => !pending.value && hasData.value,
  (ok) => {
    if (ok) {
      requestAnimationFrame(() => requestAnimationFrame(() => (revealed.value = true)));
    }
  },
  { immediate: true }
);

const { formatRupiahFull, formatRupiahCompact, rupiahCompactParts } = useFormatters();

const expanded = ref(false);

const isMobile = useMediaQuery("(max-width: 639px)");
const hbarMargin = computed(() => ({
  top: 6,
  right: 8,
  bottom: 6,
  left: isMobile.value ? 70 : 100,
}));

const formatNumber = (value) => new Intl.NumberFormat("id-ID").format(value ?? 0);
const formatCurrency = (value) =>
  expanded.value ? formatRupiahFull(value) : formatRupiahCompact(value);

const clampLabel = (value) => {
  const str = String(value ?? "");
  const max = isMobile.value ? 12 : 16;
  return str.length > max ? `${str.slice(0, max - 1)}…` : str;
};

// ── Shared monochrome texture defs (grayscale --chart-*) ──────────────────────
const barGradientDefs = `<linearGradient id="res-bar-grad" x1="0" y1="0" x2="0" y2="1">
  <stop offset="0%" stop-color="var(--chart-1)" stop-opacity="0.95" />
  <stop offset="100%" stop-color="var(--chart-1)" stop-opacity="0.45" /></linearGradient>`;

const areaGlowDefs = `
  <linearGradient id="res-area-fill" x1="0" y1="0" x2="0" y2="1">
    <stop offset="5%" stop-color="var(--chart-1)" stop-opacity="0.45" />
    <stop offset="95%" stop-color="var(--chart-1)" stop-opacity="0" />
  </linearGradient>
  <filter id="res-line-glow" x="-10%" y="-20%" width="120%" height="140%">
    <feGaussianBlur stdDeviation="6" result="b" /><feComposite in="SourceGraphic" in2="b" operator="over" />
  </filter>
  <filter id="res-dot-glow" x="-50%" y="-50%" width="200%" height="200%">
    <feGaussianBlur stdDeviation="2.5" result="b" /><feComposite in="SourceGraphic" in2="b" operator="over" />
  </filter>`;

const duotoneDefs = `<linearGradient id="res-duotone" x1="0" y1="0" x2="1" y2="0">
  <stop offset="0%" stop-color="var(--chart-1)" stop-opacity="0.4" />
  <stop offset="100%" stop-color="var(--chart-1)" stop-opacity="1" /></linearGradient>`;

const dotDefs = `<pattern id="res-dots" width="5" height="5" patternUnits="userSpaceOnUse">
  <rect width="5" height="5" fill="var(--chart-1)" opacity="0.1" />
  <circle cx="2.5" cy="2.5" r="1.2" fill="var(--chart-1)" opacity="0.55" /></pattern>`;

const pieStripeDefs = `<pattern id="res-pie-stripe" patternUnits="userSpaceOnUse" width="6" height="6">
  <rect width="6" height="6" fill="var(--chart-2)" opacity="0.25" />
  <path d="M0,6 L6,0" stroke="var(--chart-2)" stroke-width="1" opacity="0.5" /></pattern>`;

const padAngle = (2 * Math.PI) / 180;

const CHART_VARS = [
  "var(--chart-1)",
  "var(--chart-2)",
  "var(--chart-3)",
  "var(--chart-4)",
  "var(--chart-5)",
];

const kpis = computed(() => [
  {
    label: "Reservations",
    desc: `${formatNumber(s.value.paid_reservations ?? 0)} paid`,
    icon: "hugeicons:calendar-02",
    color: "text-violet-500",
    value: s.value.total_reservations ?? 0,
    type: "count",
  },
  {
    label: "Room nights",
    desc: "Across paid bookings",
    icon: "hugeicons:moon-02",
    color: "text-sky-500",
    value: s.value.room_nights ?? 0,
    type: "count",
  },
  {
    label: "Revenue",
    desc: "Paid reservations",
    icon: "hugeicons:money-bag-02",
    color: "text-emerald-500",
    value: s.value.total_revenue ?? 0,
    type: "currency",
  },
  {
    label: "Paid",
    desc: `${formatNumber(s.value.pending_reservations ?? 0)} pending`,
    icon: "hugeicons:checkmark-circle-02",
    color: "text-amber-500",
    value: s.value.paid_reservations ?? 0,
    type: "count",
  },
  {
    label: "Avg booking",
    desc: "Per paid reservation",
    icon: "hugeicons:coins-01",
    color: "text-rose-500",
    value: s.value.avg_booking_value ?? 0,
    type: "currency",
  },
]);

// Trend chart ────────────────────────────────────────────────────────────────
const metric = ref("reservations");
const metricOptions = [
  { value: "reservations", label: "Bookings" },
  { value: "revenue", label: "Revenue" },
  { value: "nights", label: "Nights" },
];
const metricMeta = {
  reservations: { noun: "bookings", label: "Bookings" },
  revenue: { noun: "revenue", label: "Revenue" },
  nights: { noun: "room nights", label: "Room nights" },
};

const trendData = computed(() => {
  let reservations = 0;
  let revenue = 0;
  let nights = 0;
  return (d.value?.bookings_over_time ?? []).map((row) => {
    reservations += row.reservations ?? 0;
    revenue += row.revenue ?? 0;
    nights += row.room_nights ?? 0;
    return { date: new Date(row.date), reservations, revenue, nights };
  });
});
const trendConfig = computed(() => ({
  [metric.value]: { label: metricMeta[metric.value].label, color: "var(--chart-1)" },
}));
const trendAreaMargin = computed(() => ({
  top: 8,
  right: 10,
  bottom: 18,
  left: metric.value === "revenue" ? 46 : 28,
}));

// Length of stay ─────────────────────────────────────────────────────────────
const stayData = computed(() =>
  (d.value?.stay_lengths ?? []).map((x, i) => ({
    idx: i,
    label: x.nights === 1 ? "1 night" : `${x.nights} nights`,
    count: x.count,
  }))
);
const stayLabel = (i) => stayData.value[i]?.label ?? "";

// Revenue by hotel ───────────────────────────────────────────────────────────
const hotelData = computed(() =>
  (d.value?.by_hotel ?? []).map((h, i) => ({ idx: i, value: h.revenue, label: h.name }))
);
const hotelLabel = (i) => clampLabel(hotelData.value[i]?.label);

// Room type table sell bar, scaled to the busiest type by nights.
const maxRoomNights = computed(() =>
  Math.max(1, ...(d.value?.by_room_type ?? []).map((r) => r.nights ?? 0))
);
const nightsPct = (r) => Math.max(2, Math.round(((r.nights ?? 0) / maxRoomNights.value) * 100));

// Payment channels ───────────────────────────────────────────────────────────
const paymentChartData = computed(() =>
  (d.value?.payment_channels ?? []).map((c, i) => ({ idx: i, value: c.revenue, label: c.channel }))
);
const paymentChartLabel = (i) => clampLabel(paymentChartData.value[i]?.label);

// Reservation status donut ───────────────────────────────────────────────────
const statusColor = (i) => CHART_VARS[i % CHART_VARS.length];
const statusData = computed(() =>
  (d.value?.by_status ?? []).map((o, i) => ({
    status: o.status,
    count: o.count,
    label: o.label,
    fill: CHART_VARS[i % CHART_VARS.length],
    patterned: o.status === "pending_payment",
  }))
);
const statusConfig = computed(() => ({
  count: { label: "Reservations" },
  ...Object.fromEntries(
    (d.value?.by_status ?? []).map((o, i) => [o.status, { label: o.label, color: statusColor(i) }])
  ),
}));
const statusActiveIndex = computed(() => {
  const rows = d.value?.by_status ?? [];
  if (!rows.length) return null;
  let best = 0;
  rows.forEach((o, i) => {
    if ((o.count ?? 0) > (rows[best].count ?? 0)) best = i;
  });
  return best;
});
const statusSegmentFill = (row) => (row.patterned ? "url(#res-pie-stripe)" : row.fill);

// Nationality ────────────────────────────────────────────────────────────────
const nationalityData = computed(() =>
  (d.value?.by_nationality ?? []).map((x, i) => ({ idx: i, value: x.count, label: x.nationality }))
);
const nationalityLabel = (i) => clampLabel(nationalityData.value[i]?.label);

// Booking source ─────────────────────────────────────────────────────────────
const sourceData = computed(() =>
  (d.value?.by_source ?? []).map((x, i) => ({ idx: i, value: x.count, label: x.label }))
);
const sourceLabel = (i) => clampLabel(sourceData.value[i]?.label);
</script>
