<template>
  <div
    class="min-h-screen-offset mx-auto flex flex-col space-y-6 pt-4 pb-4 lg:max-w-4xl xl:max-w-6xl"
  >
    <div class="flex flex-col gap-y-1">
      <h2 class="page-title">
        <DashboardGreeting />
      </h2>
      <p class="page-description">What do you want to do today?</p>
    </div>

    <div v-if="!loading && aggregateData" class="flex flex-col gap-y-2.5">
      <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
        <div class="flex flex-col">
          <h2 class="text-foreground text-lg font-semibold tracking-tighter">Web Analytics</h2>
          <ClientOnly>
            <span
              v-if="formatDate(startDate) && formatDate(endDate)"
              class="text-foreground/70 text-sm font-medium tracking-tighter"
            >
              {{ formatDate(startDate) }}
              <span v-if="formatDate(startDate) !== formatDate(endDate)">
                - {{ formatDate(endDate) }}</span
              >
            </span>
          </ClientOnly>
        </div>

        <div class="ml-auto flex shrink-0 items-center gap-2">
          <ClientOnly>
            <DateRangeSelect v-model="selectedRange" />
          </ClientOnly>
        </div>
      </div>

      <div class="space-y-6 pb-10">
        <div v-if="aggregatedChartData?.length >= 2" class="frame">
          <div class="frame-header">
            <div class="flex items-center justify-between gap-2">
              <div class="flex flex-col gap-y-1">
                <h6 class="text-foreground text-base font-medium tracking-tighter">
                  {{ selectedMetricInfo?.label }}
                </h6>
                <p class="text-muted-foreground xs:block hidden text-xs tracking-tight">
                  {{ selectedMetricInfo?.description }}
                </p>
              </div>
              <Select v-model="selectedMetric" class="absolute top-2 right-2">
                <SelectTrigger data-size="sm" class="w-36">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="option in metricOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <ChartLineDefault
            :data="aggregatedChartData"
            :config="aggregatedChartConfig"
            :data-key="selectedMetric"
            class="bg-background h-auto! overflow-hidden rounded-xl border py-2.5"
          />
        </div>

        <AnalyticsSummaryCards :metrics="summaryMetrics" :property-breakdown="propertyBreakdown" />

        <div class="flex items-center justify-center">
          <NuxtLink
            to="/web-analytics"
            class="bg-muted text-foreground hover:bg-border flex items-center gap-x-1.5 rounded-lg px-3 py-2 text-sm font-medium tracking-tight transition active:scale-98"
          >
            <span>Full Report</span>
            <Icon name="hugeicons:arrow-right-02" class="size-4 shrink-0" />
          </NuxtLink>
        </div>
      </div>
    </div>

    <div
      v-else-if="loading"
      class="border-border bg-pattern-diagonal flex grow items-center justify-center overflow-hidden rounded-xl border p-6"
    >
      <div class="flex items-center gap-2">
        <Spinner class="size-5 shrink-0" />
        <span class="text-sm tracking-tight">Loading analytics...</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import DateRangeSelect from "@/components/analytics/DateRangeSelect.vue";

const { $dayjs } = useNuxtApp();

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("dashboard");

// Load selected range from localStorage immediately (client-side only)
const getInitialRange = () => {
  if (typeof window !== "undefined") {
    return localStorage.getItem("dashboard_selected_range") || "30";
  }
  return "30";
};

const getInitialMetric = () => {
  if (typeof window !== "undefined") {
    return localStorage.getItem("dashboard_selected_metric") || "activeUsers";
  }
  return "activeUsers";
};

const selectedRange = ref(getInitialRange());

// Selected metric for chart
const selectedMetric = ref(getInitialMetric());

// Metric options for chart
const metricOptions = [
  {
    value: "activeUsers",
    label: "Active Visitors",
    description: "Visitors who truly engaged with your site",
  },
  {
    value: "totalUsers",
    label: "Total Visitors",
    description: "All unique visitors who ever came",
  },
  {
    value: "newUsers",
    label: "New Visitors",
    description: "First-time visitors to your site",
  },
  {
    value: "sessions",
    label: "Sessions",
    description: "How many times your site was opened",
  },
  {
    value: "screenPageViews",
    label: "Page Views",
    description: "Total count of pages being viewed",
  },
];

// Get selected metric info
const selectedMetricInfo = computed(() => {
  return metricOptions.find((m) => m.value === selectedMetric.value);
});

// Watch for changes and save to localStorage
watch(selectedRange, async (newValue) => {
  if (typeof window !== "undefined") {
    localStorage.setItem("dashboard_selected_range", newValue);
  }
  await changeDateRange(newValue);
});

// Watch selectedMetric and save to localStorage
watch(selectedMetric, (newValue) => {
  if (typeof window !== "undefined") {
    localStorage.setItem("dashboard_selected_metric", newValue);
  }
});

// Fetch analytics data
const { aggregateData, loading, fetchAnalytics, changeDateRange, startRealtimeRefresh } =
  useMergedAnalytics(selectedRange.value);

/**
 * Calculate start and end dates based on the selected range
 */
const getDateRange = (range) => {
  const today = $dayjs();

  switch (range) {
    case "today":
      return { start: today.startOf("day"), end: today.endOf("day") };
    case "yesterday":
      return {
        start: today.subtract(1, "day").startOf("day"),
        end: today.subtract(1, "day").endOf("day"),
      };
    case "this_week":
      return { start: today.startOf("week"), end: today.endOf("day") };
    case "last_week":
      return {
        start: today.subtract(1, "week").startOf("week"),
        end: today.subtract(1, "week").endOf("week"),
      };
    case "this_month":
      return { start: today.startOf("month"), end: today.endOf("day") };
    case "last_month":
      return {
        start: today.subtract(1, "month").startOf("month"),
        end: today.subtract(1, "month").endOf("month"),
      };
    case "this_year":
      return { start: today.startOf("year"), end: today.endOf("day") };
    default:
      // Numeric values like 7, 30, 90, 365 - "last N days"
      const days = parseInt(range);
      return { start: today.subtract(days - 1, "day").startOf("day"), end: today.endOf("day") };
  }
};

const dateRange = computed(() => getDateRange(selectedRange.value));
const startDate = computed(() => dateRange.value.start);
const endDate = computed(() => dateRange.value.end);

const formatDate = (date) => date.format("MMM D");

// Fetch analytics on client-side mount
onMounted(async () => {
  // Start realtime refresh for online users
  startRealtimeRefresh();

  // Fetch aggregate data (will use cache if available)
  // Composable handles cache checking internally
  await fetchAnalytics();
});

// Metric configurations
const METRIC_CONFIGS = [
  {
    key: "onlineUsers",
    label: "Online Now",
    description: "People viewing your site right now",
    icon: "hugeicons:wifi-02",
    bgClass: "bg-green-500/10",
    iconClass: "text-green-700 dark:text-green-400",
  },
  {
    key: "activeUsers",
    label: "Active Visitors",
    description: "Visitors who truly engaged with your site",
    icon: "hugeicons:user-multiple-02",
    bgClass: "bg-blue-500/10",
    iconClass: "text-blue-700 dark:text-blue-400",
  },
  {
    key: "newUsers",
    label: "New Visitors",
    description: "First-time visitors to your site",
    icon: "hugeicons:user-add-02",
    bgClass: "bg-sky-500/10",
    iconClass: "text-sky-700 dark:text-sky-400",
  },
  {
    key: "totalUsers",
    label: "Total Visitors",
    description: "All unique visitors who ever came",
    icon: "hugeicons:user-group",
    bgClass: "bg-purple-500/10",
    iconClass: "text-purple-700 dark:text-purple-400",
  },
  {
    key: "sessions",
    label: "Total Sessions",
    description: "How many times your site was opened",
    icon: "hugeicons:cursor-pointer-02",
    bgClass: "bg-indigo-500/10",
    iconClass: "text-indigo-700 dark:text-indigo-400",
  },
  {
    key: "screenPageViews",
    label: "Page Views",
    description: "Total count of pages being viewed",
    icon: "hugeicons:view",
    bgClass: "bg-pink-500/10",
    iconClass: "text-pink-700 dark:text-pink-400",
  },
  {
    key: "bounceRate",
    label: "Bounce Rate",
    description: "Visitors who left immediately",
    format: "percent",
    icon: "hugeicons:undo-02",
    bgClass: "bg-red-500/10",
    iconClass: "text-red-700 dark:text-red-400",
  },
  {
    key: "averageSessionDuration",
    label: "Average Duration",
    description: "How long visitors stay on your site",
    format: "duration",
    icon: "hugeicons:time-quarter-02",
    bgClass: "bg-yellow-500/10",
    iconClass: "text-yellow-700 dark:text-yellow-400",
  },
];

const formatNumber = (value) => {
  if (value == null) return "0";
  return new Intl.NumberFormat().format(Math.round(value));
};

const formatPercent = (value) => {
  if (value == null) return "0%";
  return `${(value * 100).toFixed(1)}%`;
};

const formatDuration = (seconds) => {
  if (!seconds) return "0m 0s";
  const minutes = Math.floor(seconds / 60);
  const secs = Math.floor(seconds % 60);
  return `${minutes}m ${secs}s`;
};

const summaryMetrics = computed(() => {
  if (!aggregateData.value?.totals) return [];

  const totals = aggregateData.value.totals;

  return METRIC_CONFIGS.map((config) => {
    const value = totals[config.key] || 0;
    const computedValue = config.format === "percent" ? value * 100 : value;
    const formattedValue =
      config.format === "percent"
        ? formatPercent(value)
        : config.format === "duration"
          ? formatDuration(value)
          : formatNumber(value);

    return {
      ...config,
      value: computedValue,
      formattedValue,
    };
  });
});

const propertyBreakdown = computed(() => {
  const breakdown = aggregateData.value?.property_breakdown || [];
  return [...breakdown].sort((a, b) => {
    const aUsers = a.metrics?.activeUsers || 0;
    const bUsers = b.metrics?.activeUsers || 0;
    return bUsers - aUsers;
  });
});

// Aggregate daily chart data from all properties
const aggregatedChartData = computed(() => {
  if (!propertyBreakdown.value || propertyBreakdown.value.length === 0) {
    return [];
  }

  const dailyDataMap = new Map();

  propertyBreakdown.value.forEach((property) => {
    if (property.rows && Array.isArray(property.rows)) {
      property.rows.forEach((row) => {
        const date = row.date;

        if (!dailyDataMap.has(date)) {
          dailyDataMap.set(date, {
            activeUsers: 0,
            totalUsers: 0,
            newUsers: 0,
            sessions: 0,
            screenPageViews: 0,
          });
        }

        const existing = dailyDataMap.get(date);
        existing.activeUsers += row.activeUsers || 0;
        existing.totalUsers += row.totalUsers || 0;
        existing.newUsers += row.newUsers || 0;
        existing.sessions += row.sessions || 0;
        existing.screenPageViews += row.screenPageViews || 0;
      });
    }
  });

  return Array.from(dailyDataMap.entries())
    .map(([date, metrics]) => ({
      date: new Date(date),
      ...metrics,
    }))
    .sort((a, b) => a.date - b.date);
});

const aggregatedChartConfig = computed(() => {
  const metricConfig = metricOptions.find((m) => m.value === selectedMetric.value);
  return {
    [selectedMetric.value]: {
      label: metricConfig?.label || "Metric",
      color: "var(--chart-1)",
    },
  };
});
</script>
