<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex flex-col items-start gap-y-4">
      <ButtonBack destination="/emails" force-destination />

      <div
        class="flex w-full flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
      >
        <div class="flex shrink-0 items-center gap-x-2.5">
          <Icon name="hugeicons:analytics-up" class="size-5 sm:size-6" />
          <h1 class="page-title">Email Analytics</h1>
        </div>

        <div class="ml-auto flex shrink-0 items-center gap-2">
          <ClientOnly>
            <RangeCalendarPicker v-model="dateRange" size="sm" placeholder="Date range" />
          </ClientOnly>
          <Badge v-if="totals" :variant="health.variant" plain>{{ health.label }}</Badge>
        </div>
      </div>
    </div>

    <section class="space-y-3">
      <div class="flex items-center gap-x-2">
        <Icon name="hugeicons:chart-line-data-02" class="text-muted-foreground size-4 shrink-0" />
        <h2 class="text-muted-foreground text-sm font-semibold tracking-tight">Overview</h2>
      </div>

      <GridFill :count="6" min-col-width="210px" rounded="xl">
        <template v-if="overviewPending">
          <div v-for="i in 6" :key="`sk-${i}`" class="flex flex-col gap-y-3 p-4 sm:p-5">
            <Skeleton class="size-5 rounded" />
            <div class="space-y-1.5">
              <Skeleton class="h-3.5 w-20" />
              <Skeleton class="h-3 w-28" />
            </div>
            <Skeleton class="h-6 w-16" />
          </div>
        </template>

        <template v-else>
          <!-- Sending-limit gauges fill the same grid, mirroring the check-in
               gauge in AttendeeAnalyticsSummary. "Today"/"This month" are always
               live, independent of the selected date range. -->
          <div v-for="gauge in usageGauges" :key="gauge.key" class="flex flex-col gap-y-2 p-4 sm:p-5">
            <div class="flex justify-center">
              <span class="text-foreground text-sm font-medium tracking-tight">{{
                gauge.title
              }}</span>
            </div>
            <div class="flex justify-center">
              <ChartSemiCircle
                :value="gauge.used"
                :max="Math.max(gauge.limit, 1)"
                show-max
                :compact="false"
                :center-label="gauge.label"
                class="w-full max-w-[180px]"
              />
            </div>
          </div>

          <div
            v-for="stat in stats"
            :key="stat.key"
            class="flex flex-col items-start gap-y-2 p-4 sm:p-5"
          >
            <Icon :name="stat.icon" class="size-5" :class="stat.color" />
            <div class="min-w-0">
              <span class="text-foreground text-sm font-medium tracking-tight">{{
                stat.label
              }}</span>
              <p class="text-xs tracking-tight sm:text-sm" :class="stat.captionClass">
                {{ stat.caption }}
              </p>
            </div>
            <NumberFlow :class="statValueClass" :value="stat.value" locales="en-US" />
          </div>
        </template>
      </GridFill>

      <div
        v-if="totals && totals.bounced > 0 && bounceBreakdown"
        class="text-muted-foreground flex flex-wrap items-center gap-x-4 gap-y-1 px-1 text-xs tracking-tight sm:text-sm"
      >
        <span class="text-foreground font-medium">Bounce breakdown</span>
        <span><span class="text-destructive">{{ bounceBreakdown.permanent }}</span> permanent</span>
        <span><span class="text-warning-foreground">{{ bounceBreakdown.transient }}</span> transient</span>
        <span v-if="bounceBreakdown.unclassified > 0">
          {{ bounceBreakdown.unclassified }} unclassified (awaiting webhook)
        </span>
      </div>
    </section>

    <!-- Client-only: the chart pulls a Vue useId, and the lazy overview fetch
         resolves into the client payload but not the server render, so letting it
         render during hydration shifts every id after it (the Tabs mismatch). -->
    <ClientOnly>
      <section v-if="hasTrend" class="space-y-3">
        <div class="flex items-center gap-x-2">
          <Icon name="hugeicons:analytics-up" class="text-muted-foreground size-4 shrink-0" />
          <h2 class="text-muted-foreground text-sm font-semibold tracking-tight">Activity</h2>
        </div>

        <div class="rounded-xl border p-4 sm:p-5">
          <ChartArea
            :data="trendData"
            :config="trendConfig"
            :data-keys="trendKeys"
            x-key="date"
            :x-tick-formatter="formatTrendDate"
            legend
            grid
            class="h-64! w-full"
          />
        </div>
      </section>
    </ClientOnly>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { RangeCalendarPicker } from "@/components/ui/range-calendar-picker";
import { Skeleton } from "@/components/ui/skeleton";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["emails.view"],
  layout: "app",
});

defineOptions({ name: "email-analytics" });

usePageMeta(null, { title: "Email Analytics" });

const { $dayjs } = useNuxtApp();

const statValueClass =
  "text-foreground -mb-1 text-lg leading-tight font-medium tracking-tighter sm:text-xl";

/* --------------------------------------------------------------- date range */

const dateRange = ref({
  start: $dayjs().subtract(29, "day").toDate(),
  end: $dayjs().toDate(),
});

const toYmd = (date) => {
  if (!date) return null;
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
};

const buildOverviewQuery = () => {
  const params = new URLSearchParams();
  const from = toYmd(dateRange.value.start);
  const to = toYmd(dateRange.value.end);
  if (from) params.append("date_from", from);
  if (to) params.append("date_to", to);
  return params.toString();
};

const {
  data: overviewResponse,
  pending: overviewPending,
  refresh: refreshOverview,
} = await useLazySanctumFetch(() => `/api/emails/overview?${buildOverviewQuery()}`, {
  key: "emails-analytics-overview",
  watch: false,
});

watch(dateRange, () => refreshOverview(), { deep: true });

const totals = computed(() => overviewResponse.value?.data?.totals ?? null);
const daily = computed(() => overviewResponse.value?.data?.daily ?? []);
const bounceBreakdown = computed(() => overviewResponse.value?.data?.bounce_breakdown ?? null);
const usage = computed(() => overviewResponse.value?.data?.usage ?? null);

// Sending quota gauges, mirroring Resend's own Usage screen (today vs daily
// limit, this month vs monthly limit). Limits are provided by the backend.
const usageGauges = computed(() => [
  {
    key: "daily",
    title: "Daily limit",
    label: "sent today",
    used: usage.value?.daily.used ?? 0,
    limit: usage.value?.daily.limit ?? 100,
  },
  {
    key: "monthly",
    title: "Monthly limit",
    label: "sent this month",
    used: usage.value?.monthly.used ?? 0,
    limit: usage.value?.monthly.limit ?? 3000,
  },
]);

const formatRate = (value) => `${(value ?? 0).toFixed(2)}%`;

const bounceVariant = computed(() => {
  const rate = totals.value?.bounce_rate ?? 0;
  if (rate >= 5) return "destructive";
  if (rate >= 2) return "warning";
  return "success";
});

const complaintVariant = computed(() => {
  const rate = totals.value?.complaint_rate ?? 0;
  if (rate >= 0.1) return "destructive";
  if (rate >= 0.05) return "warning";
  return "success";
});

const health = computed(() => {
  const bounce = totals.value?.bounce_rate ?? 0;
  const complaint = totals.value?.complaint_rate ?? 0;
  if (bounce >= 5 || complaint >= 0.1) return { label: "At risk", variant: "destructive" };
  if (bounce >= 2 || complaint >= 0.05) return { label: "Watch", variant: "warning" };
  return { label: "Healthy", variant: "success" };
});

const stats = computed(() => {
  const s = totals.value ?? {};
  const neutral = "text-muted-foreground";

  return [
    {
      key: "sent",
      label: "Sent",
      icon: "hugeicons:mail-01",
      color: "text-violet-500",
      value: s.sent ?? 0,
      caption: "Total sent",
      captionClass: neutral,
    },
    {
      key: "delivered",
      label: "Delivered",
      icon: "hugeicons:checkmark-circle-02",
      color: "text-emerald-500",
      value: s.delivered ?? 0,
      caption: `${formatRate(s.delivery_rate)} of sent`,
      captionClass: neutral,
    },
    {
      key: "bounced",
      label: "Bounced",
      icon: "hugeicons:cancel-circle",
      color: "text-rose-500",
      value: s.bounced ?? 0,
      caption: `${formatRate(s.bounce_rate)} of sent, limit 5%`,
      captionClass: bounceVariant.value === "success" ? neutral : "text-destructive",
    },
    {
      key: "complained",
      label: "Complaints",
      icon: "hugeicons:alert-02",
      color: "text-amber-500",
      value: s.complained ?? 0,
      caption: `${formatRate(s.complaint_rate)} of sent, limit 0.1%`,
      captionClass: complaintVariant.value === "success" ? neutral : "text-destructive",
    },
  ];
});

/* -------------------------------------------------------------------- trend */

const trendKeys = ["sent", "delivered"];
const trendConfig = {
  sent: { label: "Sent", color: "var(--chart-1)" },
  delivered: { label: "Delivered", color: "var(--chart-2)" },
};
const trendData = computed(() =>
  daily.value.map((d) => ({
    date: d.date,
    sent: d.sent,
    delivered: d.delivered,
  })),
);
const hasTrend = computed(() => daily.value.some((d) => d.sent > 0 || d.delivered > 0));
const formatTrendDate = (value) => $dayjs(value).format("D MMM");
</script>
