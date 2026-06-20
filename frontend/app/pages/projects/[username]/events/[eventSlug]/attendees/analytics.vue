<template>
  <div class="space-y-8 pb-16">
    <!-- Header -->
    <div class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex min-w-0 items-center gap-x-2.5">
        <Icon name="hugeicons:chart-line-data-02" class="size-5 shrink-0 sm:size-6" />
        <div class="min-w-0">
          <h1 class="page-title">Analytics</h1>
          <p class="text-muted-foreground truncate text-sm tracking-tight">
            Attendance, sales and engagement for {{ event?.title }}
          </p>
        </div>
      </div>
      <Button variant="outline" size="sm" as-child>
        <NuxtLink :to="`${eventBase}/attendees`">
          <Icon name="hugeicons:arrow-left-01" class="size-4 shrink-0" />
          <span>Attendees</span>
        </NuxtLink>
      </Button>
    </div>

    <!-- Loading -->
    <div v-if="pending" class="space-y-8">
      <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
        <Skeleton v-for="i in 6" :key="`k-${i}`" class="h-28 rounded-xl" />
      </div>
      <Skeleton class="h-72 rounded-xl" />
      <div class="grid gap-6 lg:grid-cols-2">
        <Skeleton class="h-56 rounded-xl" />
        <Skeleton class="h-56 rounded-xl" />
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
        <div><Icon name="hugeicons:ticket-01" /></div>
        <div class="translate-y-1.5 rotate-6"><Icon name="hugeicons:user-multiple-02" /></div>
      </div>
      <div class="space-y-1">
        <h3 class="font-semibold tracking-tight">No data to analyse yet</h3>
        <p class="text-muted-foreground max-w-sm text-sm tracking-tight">
          Once people start registering, attendance and sales insights will appear here.
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
            :value="s.checked_in"
            :max="Math.max(s.total_attendees, 1)"
            :center-label="`${s.check_in_rate ?? 0}% checked in`"
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
            class="text-foreground -mb-1 text-lg leading-tight font-medium tracking-tighter sm:text-xl"
            :value="kpi.value"
            :format="kpi.format"
          />
        </div>
      </GridFill>

      <!-- Registrations & revenue trend -->
      <section
        class="t-panel-slide space-y-4"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '70ms' }"
      >
        <div class="flex flex-col gap-y-2 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h2 class="text-base font-medium tracking-tight">Registrations over time</h2>
            <p class="text-muted-foreground text-sm tracking-tight">
              How {{ metricMeta[metric].noun }} accumulated as the campaign ran.
            </p>
          </div>
          <div
            ref="metricToggle"
            class="bg-muted inline-flex w-fit gap-x-0.5 rounded-lg p-0.5"
            @mouseleave="onMetricHover(null, 'out')"
          >
            <button
              v-for="(opt, i) in metricOptions"
              :key="opt.value"
              type="button"
              class="t-avatar rounded-md px-2.5 py-1 text-sm font-medium tracking-tight"
              :class="
                metric === opt.value
                  ? 'bg-background text-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground'
              "
              @click="metric = opt.value"
              @mouseenter="onMetricHover(i, 'in')"
            >
              {{ opt.label }}
            </button>
          </div>
        </div>
        <div class="bg-card rounded-xl border p-4 sm:p-5">
          <ChartLine
            v-if="trendData.length > 1"
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
        </div>
      </section>

      <!-- Check-in flow + by day -->
      <section
        v-if="d.check_ins_over_time.length || d.by_event_day.length"
        class="t-panel-slide space-y-4"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '140ms' }"
      >
        <div>
          <h2 class="text-base font-medium tracking-tight">Check-in activity</h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            When attendees arrived and how each event day performed.
          </p>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
          <div class="bg-card rounded-xl border p-4 sm:p-5">
            <p class="text-muted-foreground mb-3 text-sm font-medium tracking-tight">
              Arrivals by hour
            </p>
            <ChartLine
              v-if="checkInFlow.length > 1"
              :data="checkInFlow"
              :config="{ count: { label: 'Check-ins', color: 'var(--chart-2)' } }"
              data-key="count"
              gradient
              class="h-56! w-full"
            />
            <p v-else class="text-muted-foreground py-14 text-center text-sm tracking-tight">
              No check-ins recorded yet.
            </p>
          </div>
          <div class="bg-card rounded-xl border p-4 sm:p-5">
            <p class="text-muted-foreground mb-3 text-sm font-medium tracking-tight">
              Attendance by day
            </p>
            <div v-if="d.by_event_day.length" class="flex flex-wrap justify-center gap-x-4 gap-y-2">
              <div v-for="day in d.by_event_day" :key="day.day_number" class="w-[148px]">
                <ChartSemiCircle
                  :value="day.checked_in"
                  :max="Math.max(s.total_attendees, 1)"
                  :center-label="day.label"
                  class="w-full"
                />
                <p class="text-muted-foreground text-center text-xs tracking-tight sm:text-sm">
                  {{ formatDate(day.date) }}
                </p>
              </div>
            </div>
            <p v-else class="text-muted-foreground py-14 text-center text-sm tracking-tight">
              No event days configured.
            </p>
          </div>
        </div>
      </section>

      <!-- Ticket type performance -->
      <section
        v-if="d.by_ticket_type.length"
        class="t-panel-slide space-y-4"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '210ms' }"
      >
        <div>
          <h2 class="text-base font-medium tracking-tight">Ticket performance</h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            Sales, attendance and revenue for each ticket type.
          </p>
        </div>
        <div class="overflow-hidden rounded-xl border">
          <div
            class="text-muted-foreground bg-muted/40 hidden grid-cols-[2fr_1fr_1.4fr_1.2fr] gap-x-4 px-4 py-2.5 text-xs font-medium tracking-tight sm:grid"
          >
            <span>Ticket</span>
            <span class="text-right">Sold</span>
            <span class="text-right">Checked in</span>
            <span class="text-right">Revenue</span>
          </div>
          <div class="divide-border divide-y">
            <div
              v-for="t in d.by_ticket_type"
              :key="t.ticket_id"
              class="grid grid-cols-2 gap-x-4 gap-y-2 px-4 py-3.5 sm:grid-cols-[2fr_1fr_1.4fr_1.2fr] sm:items-center"
            >
              <div class="col-span-2 flex items-center gap-x-2 sm:col-span-1">
                <span class="truncate text-sm font-medium tracking-tight">{{ t.title }}</span>
                <Badge v-if="t.tier" variant="muted" class="shrink-0">{{ t.tier }}</Badge>
              </div>
              <div class="tabular-nums sm:text-right">
                <span class="text-muted-foreground text-xs tracking-tight sm:hidden">Sold </span>
                <span class="text-sm tracking-tight">{{ formatNumber(t.sold) }}</span>
              </div>
              <div class="flex items-baseline gap-x-1.5 tabular-nums sm:justify-end">
                <span class="text-sm tracking-tight">{{ formatNumber(t.checked_in) }}</span>
                <span class="text-muted-foreground text-xs tracking-tight">{{ t.check_in_rate }}%</span>
              </div>
              <div class="tabular-nums sm:text-right">
                <span class="text-sm font-medium tracking-tight">{{ formatCurrency(t.revenue) }}</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Commercial breakdowns -->
      <section
        v-if="d.payment_channels.length || d.order_status.length"
        class="t-panel-slide space-y-4"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '280ms' }"
      >
        <div>
          <h2 class="text-base font-medium tracking-tight">Sales breakdown</h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            Where revenue came from and the state of every order.
          </p>
        </div>
        <div class="grid gap-x-10 gap-y-8 lg:grid-cols-2">
          <div v-if="d.payment_channels.length" class="space-y-3">
            <p class="text-muted-foreground text-sm font-medium tracking-tight">Payment channels</p>
            <AnalyticsBarList :items="paymentItems" :format-value="formatCurrency" />
          </div>
          <div v-if="d.order_status.length" class="space-y-3">
            <p class="text-muted-foreground text-sm font-medium tracking-tight">Order status</p>
            <AnalyticsBarList :items="statusItems" />
          </div>
        </div>
      </section>

      <!-- Demographics -->
      <section
        v-if="d.demographics.length"
        class="t-panel-slide space-y-4"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '350ms' }"
      >
        <div>
          <h2 class="text-base font-medium tracking-tight">Audience profile</h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            Self-reported answers from the business-matching profile.
          </p>
        </div>
        <div class="grid gap-x-10 gap-y-8 lg:grid-cols-2">
          <div v-for="field in d.demographics" :key="field.field_id" class="space-y-3">
            <div class="flex items-baseline justify-between gap-2">
              <p class="text-muted-foreground text-sm font-medium tracking-tight">{{ field.label }}</p>
              <span class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                {{ formatNumber(field.total_responses) }} responses
              </span>
            </div>
            <AnalyticsBarList
              :items="field.breakdown.map((b) => ({ label: b.value, value: b.count, tone: 'info' }))"
            />
          </div>
        </div>
      </section>

      <!-- Engagement: exhibitor leads + top buyers -->
      <section
        v-if="leads || d.top_buyers.length"
        class="t-panel-slide space-y-4"
        :data-open="revealed"
        :style="{ '--panel-translate-y': '18px', transitionDelay: '420ms' }"
      >
        <div>
          <h2 class="text-base font-medium tracking-tight">Engagement</h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            Exhibitor connections and the most active buyers.
          </p>
        </div>
        <div class="grid gap-x-10 gap-y-8 lg:grid-cols-2">
          <div v-if="leads" class="space-y-3">
            <div class="flex items-baseline justify-between gap-2">
              <p class="text-muted-foreground text-sm font-medium tracking-tight">Exhibitor leads</p>
              <span class="text-foreground text-sm font-medium tabular-nums tracking-tight">
                {{ formatNumber(leads.total) }} captured
              </span>
            </div>
            <AnalyticsBarList
              v-if="leads.by_brand.length"
              :items="leads.by_brand.map((b) => ({ label: b.name, value: b.leads }))"
            />
            <p v-else class="text-muted-foreground text-sm tracking-tight">
              No badge scans recorded yet.
            </p>
          </div>

          <div v-if="d.top_buyers.length" class="space-y-3">
            <p class="text-muted-foreground text-sm font-medium tracking-tight">Top buyers</p>
            <ul class="divide-border divide-y">
              <li
                v-for="(buyer, i) in d.top_buyers"
                :key="i"
                class="flex items-center justify-between gap-3 py-2.5"
              >
                <div class="flex min-w-0 items-center gap-x-3">
                  <span
                    class="bg-muted text-muted-foreground squircle flex size-7 shrink-0 items-center justify-center text-xs font-medium tabular-nums"
                  >
                    {{ i + 1 }}
                  </span>
                  <div class="min-w-0">
                    <p class="truncate text-sm font-medium tracking-tight">{{ buyer.name }}</p>
                    <p class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm">
                      {{ buyer.tickets }} tickets · {{ buyer.orders }} orders
                    </p>
                  </div>
                </div>
                <span class="shrink-0 text-sm font-medium tabular-nums tracking-tight">
                  {{ formatCurrency(buyer.total_spent) }}
                </span>
              </li>
            </ul>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import AnalyticsBarList from "@/components/AnalyticsBarList.vue";
import { useAttendeeAnalytics } from "@/composables/useAttendeeAnalytics";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["attendees.read"],
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

// Live feed: polls, refetches on focus, reacts to attendee mutations - no reload.
const { data: d, pending } = useAttendeeAnalytics(props.event?.id, "detail", {
  interval: 20000,
});

const s = computed(() => d.value?.summary ?? {});
const leads = computed(() => d.value?.exhibitor_leads ?? null);
const hasData = computed(
  () => !!d.value && ((s.value.total_attendees ?? 0) > 0 || (s.value.total_orders ?? 0) > 0)
);

// Staggered panel-reveal (transitions-dev 07): flip open once data is ready so
// the sections slide + blur into place; per-section transition-delay staggers.
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

// Metric toggle hover-spring (avatar group hover, transitions-dev 11). The
// timing-function is written inline before the var writes so the lift uses
// ease-in and the return uses a bouncy ease-out.
const metricToggle = ref(null);
function onMetricHover(activeIdx, phase) {
  const root = metricToggle.value;
  if (!root) {
    return;
  }
  const items = Array.from(root.querySelectorAll(".t-avatar"));
  const cs = getComputedStyle(document.documentElement);
  const num = (name, fallback) => {
    const v = parseFloat(cs.getPropertyValue(name));
    return Number.isFinite(v) ? v : fallback;
  };
  const ease = (name, fallback) => cs.getPropertyValue(name).trim() || fallback;
  const lift = num("--avatar-lift", -4);
  const falloff = num("--avatar-falloff", 0.45);
  const scale = num("--avatar-scale", 1.05);
  const tf =
    phase === "out"
      ? ease("--avatar-ease-out", "cubic-bezier(0.34, 3.85, 0.64, 1)")
      : ease("--avatar-ease-in", "cubic-bezier(0.22, 1, 0.36, 1)");

  items.forEach((el, i) => {
    el.style.transitionTimingFunction = tf;
    if (activeIdx == null) {
      el.style.setProperty("--shift", "0px");
      el.style.setProperty("--scale-active", "1");
      return;
    }
    const dist = Math.abs(i - activeIdx);
    el.style.setProperty("--shift", `${(lift * Math.pow(falloff, dist)).toFixed(3)}px`);
    el.style.setProperty("--scale-active", i === activeIdx ? String(scale) : "1");
  });
}

const formatNumber = (value) => new Intl.NumberFormat("en-US").format(value ?? 0);
const formatCurrency = (value) => {
  const amount = value ?? 0;
  const compact = Math.abs(amount) >= 1_000_000;
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: s.value.currency || "IDR",
    notation: compact ? "compact" : "standard",
    maximumFractionDigits: compact ? 1 : 0,
  }).format(amount);
};
const formatDate = (value) =>
  value
    ? new Date(value).toLocaleDateString("en-US", { day: "numeric", month: "short" })
    : "";

const kpis = computed(() => [
  {
    label: "Attendees",
    desc: "Ticket holders",
    icon: "hugeicons:user-multiple-02",
    color: "text-violet-500",
    value: s.value.total_attendees ?? 0,
    format: { notation: "compact" },
  },
  {
    label: "Tickets sold",
    desc: "Confirmed orders",
    icon: "hugeicons:ticket-01",
    color: "text-sky-500",
    value: s.value.tickets_sold ?? 0,
    format: { notation: "compact" },
  },
  {
    label: "Revenue",
    desc: "Confirmed orders",
    icon: "hugeicons:money-bag-02",
    color: "text-emerald-500",
    value: s.value.total_revenue ?? 0,
    format: {
      style: "currency",
      currency: s.value.currency || "IDR",
      notation: "compact",
      maximumFractionDigits: 1,
    },
  },
  {
    label: "Orders",
    desc: `${formatNumber(s.value.pending_orders ?? 0)} pending`,
    icon: "hugeicons:shopping-cart-02",
    color: "text-amber-500",
    value: s.value.confirmed_orders ?? 0,
    format: { notation: "compact" },
  },
  {
    label: "Avg order",
    desc: "Per confirmed order",
    icon: "hugeicons:coins-01",
    color: "text-rose-500",
    value: s.value.avg_order_value ?? 0,
    format: {
      style: "currency",
      currency: s.value.currency || "IDR",
      notation: "compact",
      maximumFractionDigits: 1,
    },
  },
]);

// Trend chart ────────────────────────────────────────────────────────────────
const metric = ref("tickets");
const metricOptions = [
  { value: "tickets", label: "Tickets" },
  { value: "revenue", label: "Revenue" },
  { value: "orders", label: "Orders" },
];
const metricMeta = {
  tickets: { noun: "ticket sales", color: "var(--chart-1)", label: "Tickets sold" },
  revenue: { noun: "revenue", color: "var(--chart-2)", label: "Revenue" },
  orders: { noun: "orders", color: "var(--chart-4)", label: "Orders" },
};

// Running cumulative so each metric reads as growth over the campaign.
const trendData = computed(() => {
  let tickets = 0;
  let revenue = 0;
  let orders = 0;
  return (d.value?.registrations_over_time ?? []).map((row) => {
    tickets += row.tickets ?? 0;
    revenue += row.revenue ?? 0;
    orders += row.orders ?? 0;
    return { date: row.date, tickets, revenue, orders };
  });
});
const trendConfig = computed(() => ({
  [metric.value]: { label: metricMeta[metric.value].label, color: metricMeta[metric.value].color },
}));

const checkInFlow = computed(() =>
  (d.value?.check_ins_over_time ?? []).map((row) => ({ date: row.slot, count: row.count }))
);

// Bar lists ───────────────────────────────────────────────────────────────────
const paymentItems = computed(() =>
  (d.value?.payment_channels ?? []).map((c) => ({
    label: c.channel,
    value: c.revenue,
    secondary: `${formatNumber(c.orders)} orders`,
  }))
);
const statusItems = computed(() =>
  (d.value?.order_status ?? []).map((o) => ({ label: o.label, value: o.count, tone: o.color }))
);
</script>
