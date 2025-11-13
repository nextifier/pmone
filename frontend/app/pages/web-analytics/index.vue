<template>
  <div class="min-h-screen-offset mx-auto flex max-w-7xl flex-col gap-y-4 py-4">
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:analysis-text-link" class="size-5 sm:size-6" />
        <h1 class="page-title">
          Web Analytics
          <ClientOnly>
            <span
              v-if="formatDate(startDate) && formatDate(endDate)"
              class="text-foreground/70 ml-1 text-sm font-medium tracking-tighter"
            >
              {{ formatDate(startDate) }}
              <span v-if="formatDate(startDate) !== formatDate(endDate)">
                - {{ formatDate(endDate) }}</span
              >
            </span>
          </ClientOnly>
        </h1>
      </div>

      <div class="ml-auto flex shrink-0 items-center gap-2">
        <button
          class="border-border hover:bg-muted flex h-8 items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Icon name="hugeicons:file-export" class="size-4 shrink-0" />
          <span>Export</span>
        </button>

        <ClientOnly>
          <DateRangeSelect v-model="selectedRange" />
        </ClientOnly>
      </div>
    </div>

    <div
      v-if="loading && !aggregateData"
      class="border-border bg-pattern-diagonal flex grow items-center justify-center overflow-hidden rounded-xl border p-6"
    >
      <div class="flex items-center gap-2">
        <Spinner class="size-5 shrink-0" />
        <span class="text-sm tracking-tight">Loading analytics data..</span>
      </div>
    </div>

    <div v-else-if="error && !aggregateData" class="flex items-center justify-center p-6">
      <div class="flex flex-col items-center gap-3 text-center">
        <Icon name="hugeicons:alert-circle" class="text-destructive size-6" />
        <div>
          <h3 class="text-foreground font-semibold tracking-tighter">Failed to load analytics</h3>
          <p class="text-muted-foreground mt-1 text-sm tracking-tight">{{ error }}</p>
        </div>
        <button
          @click="refreshData"
          class="bg-primary text-primary-foreground hover:bg-primary/90 mt-2 rounded-md px-4 py-2 text-sm font-medium"
        >
          Try Again
        </button>
      </div>
    </div>

    <div v-else-if="aggregateData" class="relative grid grid-cols-1 gap-y-10">
      <div
        v-if="loading"
        class="bg-background/90 absolute inset-0 z-10 flex items-start justify-center pt-20 backdrop-blur-md"
      >
        <div
          class="border-border bg-card flex items-center gap-3 rounded-lg border px-6 py-4 shadow-lg"
        >
          <Spinner class="size-5 shrink-0" />
          <span class="text-sm font-medium tracking-tight"
            >Loading {{ getDateRangeLabel() }}...</span
          >
        </div>
      </div>

      <div
        v-if="aggregateData.errors && aggregateData.errors.length > 0"
        class="bg-destructive/5 border-destructive/20 flex flex-col gap-3 rounded-lg border p-4"
      >
        <div class="flex items-start gap-2">
          <Icon name="hugeicons:alert-circle" class="text-destructive mt-0.5 size-5 shrink-0" />
          <div class="flex-1">
            <h3 class="text-foreground font-semibold tracking-tight">
              Some properties failed to load
            </h3>
            <p class="text-muted-foreground mt-1 text-sm tracking-tight">
              {{ aggregateData.errors.length }} of {{ aggregateData.properties_count }} properties
              encountered errors. Data shown below may be incomplete.
            </p>
          </div>
          <button
            v-if="hasRateLimitErrors"
            @click="clearRateLimit"
            :disabled="clearingRateLimit"
            class="bg-destructive hover:bg-destructive/90 flex shrink-0 items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium text-white transition disabled:opacity-50"
          >
            <Spinner v-if="clearingRateLimit" class="size-3.5" />
            <Icon v-else name="hugeicons:refresh" class="size-4" />
            <span>{{ clearingRateLimit ? "Clearing..." : "Clear Rate Limit" }}</span>
          </button>
        </div>
        <div class="ml-7 space-y-2">
          <div
            v-for="(err, index) in aggregateData.errors"
            :key="index"
            class="bg-background/50 border-destructive/10 flex items-start gap-2 rounded-md border p-2.5 text-sm"
          >
            <div class="flex-1">
              <span class="font-medium">{{ err.property_name }}</span>
              <span class="text-muted-foreground ml-1 text-xs">({{ err.property_id }})</span>
              <p class="text-destructive mt-0.5 text-xs">{{ err.error }}</p>
            </div>
          </div>
        </div>
      </div>

      <div class="flex flex-col gap-y-4">
        <div class="flex flex-col gap-y-1">
          <h2 class="text-foreground text-lg font-semibold tracking-tighter">
            Overall Performance
          </h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            Combined metrics from all properties.
          </p>
        </div>

        <ChartLineDefault
          v-if="aggregatedChartData?.length >= 2"
          :data="aggregatedChartData"
          :config="aggregatedChartConfig"
          data-key="activeUsers"
          class="h-auto! overflow-hidden rounded-xl border py-2.5"
        />

        <AnalyticsSummaryCards :metrics="summaryMetrics" :property-breakdown="propertyBreakdown" />
      </div>

      <div v-if="propertyBreakdown.length > 0">
        <h2 class="text-foreground text-lg font-semibold tracking-tighter">
          Analytics by Property
        </h2>
        <p class="text-muted-foreground mt-1 text-sm tracking-tight">
          {{ propertyBreakdown.length }} active
          {{ propertyBreakdown.length === 1 ? "property" : "properties" }}.
        </p>

        <div class="mt-4 grid grid-cols-[repeat(auto-fit,minmax(300px,1fr))] gap-x-2.5 gap-y-4">
          <AnalyticsPropertyCard
            v-for="property in propertyBreakdown"
            :key="property.property_id"
            :property="property"
          />
        </div>
      </div>

      <div v-if="aggregateData.top_pages?.length > 0" class="space-y-4">
        <div>
          <h2 class="text-foreground flex items-center gap-2 font-semibold">
            <Icon name="hugeicons:file-star" class="size-5" />
            Top Pages
          </h2>
          <p class="text-muted-foreground text-sm">Most visited pages across all properties</p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <AnalyticsTopPagesCard
            v-for="(page, index) in displayedTopPages"
            :key="index"
            :page="page"
            :rank="index + 1"
          />
        </div>
        <button
          v-if="aggregateData.top_pages.length > topPagesLimit"
          @click="toggleTopPages"
          class="hover:bg-muted mx-auto flex items-center gap-x-1.5 rounded-md border px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
        >
          <Icon
            :name="showAllTopPages ? 'hugeicons:arrow-up-01' : 'hugeicons:arrow-down-01'"
            class="size-4"
          />
          <span>{{
            showAllTopPages ? "Show Less" : `Show All (${aggregateData.top_pages.length})`
          }}</span>
        </button>
      </div>

      <div v-if="aggregateData.traffic_sources?.length > 0" class="space-y-4">
        <div>
          <h2 class="text-foreground flex items-center gap-2 font-semibold">
            <Icon name="hugeicons:link-square-02" class="size-5" />
            Traffic Sources
          </h2>
          <p class="text-muted-foreground text-sm">Where your visitors come from</p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          <AnalyticsTrafficSourceCard
            v-for="(source, index) in displayedTrafficSources"
            :key="index"
            :source="source"
          />
        </div>
        <button
          v-if="aggregateData.traffic_sources.length > trafficSourcesLimit"
          @click="toggleTrafficSources"
          class="hover:bg-muted mx-auto flex items-center gap-x-1.5 rounded-md border px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
        >
          <Icon
            :name="showAllTrafficSources ? 'hugeicons:arrow-up-01' : 'hugeicons:arrow-down-01'"
            class="size-4"
          />
          <span>{{
            showAllTrafficSources
              ? "Show Less"
              : `Show All (${aggregateData.traffic_sources.length})`
          }}</span>
        </button>
      </div>

      <AnalyticsDevicesBreakdown
        v-if="aggregateData.devices?.length > 0"
        :devices="aggregateData.devices"
      />

      <div class="flex flex-col items-center justify-center gap-y-6 pb-6">
        <div class="flex flex-wrap items-center justify-center gap-2">
          <NuxtLink
            to="/web-analytics/docs"
            class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
          >
            <Icon name="hugeicons:book-02" class="size-4 shrink-0" />
            <span>Documentation</span>
          </NuxtLink>
          <NuxtLink
            to="/web-analytics/sync-history"
            class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
          >
            <Icon name="hugeicons:clock-03" class="size-4 shrink-0" />
            <span>Sync History</span>
          </NuxtLink>

          <DialogResponsive dialog-max-width="500px" :overflow-content="true">
            <template #trigger="{ open }">
              <button
                class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
                @click="open()"
              >
                <Icon name="hugeicons:raw-01" class="size-4 shrink-0" />
                <span>View Raw</span>
              </button>
            </template>

            <template #default>
              <div class="px-4 pb-10 md:px-6 md:py-5">
                <pre
                  class="text-muted-foreground h-full w-full overflow-auto text-left text-xs leading-normal!"
                  >{{ aggregateData }}</pre
                >
              </div>
            </template>
          </DialogResponsive>
        </div>

        <div
          class="text-muted-foreground flex flex-wrap items-center justify-center gap-2 text-center text-sm tracking-tight"
        >
          <div v-if="!cacheInfo || cacheInfo?.is_updating" class="flex items-center gap-x-1">
            <Spinner class="size-3.5 shrink-0" />
            <span>Updating..</span>
          </div>
          <div v-else class="flex items-center gap-x-2">
            <div
              v-if="selectedRange === 'today' && cacheInfo?.cache_age_minutes > 60"
              class="bg-warning/10 text-warning-foreground border-warning/20 flex items-center gap-x-1.5 rounded-md border px-2 py-1"
            >
              <Icon name="hugeicons:alert-01" class="size-3.5 shrink-0" />
              <span class="text-xs font-medium">Data may be outdated</span>
            </div>

            <span
              :class="{
                'text-warning': selectedRange === 'today' && cacheInfo?.cache_age_minutes > 60,
              }"
              >Last updated {{ formatCacheAge(cacheInfo?.cache_age_minutes) }}.
              <span v-if="cacheInfo?.next_update_in_minutes"
                >Next update in {{ Math.ceil(cacheInfo?.next_update_in_minutes) }} min<span
                  v-if="Math.ceil(cacheInfo?.next_update_in_minutes) > 1"
                  >s</span
                >.
              </span></span
            >
          </div>

          <button
            v-if="cacheInfo && !cacheInfo?.is_updating"
            @click="forceRefresh"
            :disabled="isRefreshing"
            class="text-foreground border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
            :class="{
              'cursor-not-allowed': isRefreshing,
            }"
          >
            <Icon
              :name="isRefreshing ? 'hugeicons:loading-01' : 'hugeicons:refresh'"
              class="size-3.5 shrink-0"
              :class="{ 'animate-spin': isRefreshing }"
            />
            <span>{{ isRefreshing ? "Refreshing..." : "Force Refresh" }}</span>
          </button>
        </div>
      </div>
    </div>

    <div
      v-else
      class="border-border bg-pattern-diagonal flex grow items-center justify-center overflow-hidden rounded-xl border p-6"
    >
      <div class="flex flex-col items-center gap-2 text-center">
        <h3 class="text-foreground text-lg font-semibold tracking-tighter">No data available</h3>
        <p class="text-muted-foreground text-sm tracking-tight">
          Analytics data will appear here once properties are configured.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import DateRangeSelect from "@/components/analytics/DateRangeSelect.vue";
import ChartLineDefault from "@/components/chart/LineDefault.vue";
import { toast } from "vue-sonner";

const { $dayjs } = useNuxtApp();
const sanctumFetch = useSanctumClient();

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

defineOptions({
  name: "web-analytics",
});

usePageMeta("", {
  title: "Web Analytics Dashboard",
  description: "View aggregated analytics data from all Google Analytics 4 properties",
});

// Load selected range from localStorage immediately (client-side only)
const getInitialRange = () => {
  if (typeof window !== "undefined") {
    return localStorage.getItem("analytics_selected_range") || "30";
  }
  return "30";
};

const selectedRange = ref(getInitialRange());

// Watch for changes and save to localStorage
watch(selectedRange, async (newValue) => {
  if (typeof window !== "undefined") {
    localStorage.setItem("analytics_selected_range", newValue);
  }
  await changeDateRange(newValue);
});

const {
  aggregateData,
  loading,
  error,
  cacheInfo,
  fetchAnalytics,
  changeDateRange,
  startRealtimeRefresh,
} = useMergedAnalytics(selectedRange.value);

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
  // Sort by activeUsers in descending order (highest first)
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

  // Collect all daily data from all properties
  const dailyDataMap = new Map();

  propertyBreakdown.value.forEach((property) => {
    if (property.rows && Array.isArray(property.rows)) {
      property.rows.forEach((row) => {
        const date = row.date;
        const activeUsers = row.activeUsers || 0;

        if (dailyDataMap.has(date)) {
          dailyDataMap.set(date, dailyDataMap.get(date) + activeUsers);
        } else {
          dailyDataMap.set(date, activeUsers);
        }
      });
    }
  });

  // Convert map to array and sort by date
  return Array.from(dailyDataMap.entries())
    .map(([date, activeUsers]) => ({
      date: new Date(date),
      activeUsers,
    }))
    .sort((a, b) => a.date - b.date);
});

const aggregatedChartConfig = {
  activeUsers: {
    label: "Active Visitors",
    color: "var(--chart-1)",
  },
};

const refreshData = () => {
  fetchAnalytics(true);
};

const isRefreshing = ref(false);

const getDaysFromRange = (range) => {
  return range === "today" || range === "yesterday" ? 1 : parseInt(range) || 30;
};

const forceRefresh = async () => {
  if (isRefreshing.value) return;

  isRefreshing.value = true;

  try {
    await $fetch("/api/google-analytics/aggregate/sync-now", {
      method: "POST",
      body: { days: getDaysFromRange(selectedRange.value) },
    });

    await fetchAnalytics(true);

    useToast().add({
      title: "Success",
      description: "Analytics data refreshed successfully",
      color: "green",
    });
  } catch (error) {
    console.error("Force refresh failed:", error);
    useToast().add({
      title: "Error",
      description: error.data?.message || "Failed to refresh analytics data",
      color: "red",
    });
  } finally {
    isRefreshing.value = false;
  }
};

const DATE_RANGE_LABELS = {
  today: "Today",
  yesterday: "Yesterday",
  this_week: "This Week",
  last_week: "Last Week",
  this_month: "This Month",
  last_month: "Last Month",
  this_year: "This Year",
};

const getDateRangeLabel = () => {
  return DATE_RANGE_LABELS[selectedRange.value] || `Last ${selectedRange.value} days`;
};

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

const formatDate = (date) => date.format("MMM D");

const formatCacheAge = (minutes) => {
  if (!minutes || minutes < 1) return "just now";
  if (minutes === 1) return "1 minute ago";
  if (minutes < 60) return `${Math.floor(minutes)} minutes ago`;
  const hours = Math.floor(minutes / 60);
  return hours === 1 ? "1 hour ago" : `${hours} hours ago`;
};

const showAllTopPages = ref(false);
const topPagesLimit = 9;
const displayedTopPages = computed(() => {
  if (!aggregateData.value?.top_pages) return [];
  const pages = aggregateData.value.top_pages;
  return showAllTopPages.value ? pages : pages.slice(0, topPagesLimit);
});

const toggleTopPages = () => {
  showAllTopPages.value = !showAllTopPages.value;
};

const showAllTrafficSources = ref(false);
const trafficSourcesLimit = 8;
const displayedTrafficSources = computed(() => {
  if (!aggregateData.value?.traffic_sources) return [];
  const sources = aggregateData.value.traffic_sources;
  return showAllTrafficSources.value ? sources : sources.slice(0, trafficSourcesLimit);
});

const toggleTrafficSources = () => {
  showAllTrafficSources.value = !showAllTrafficSources.value;
};

// Rate limit handling
const clearingRateLimit = ref(false);

const hasRateLimitErrors = computed(() => {
  if (!aggregateData.value?.errors) return false;
  return aggregateData.value.errors.some((err) => err.error.toLowerCase().includes("rate limit"));
});

const clearRateLimit = async () => {
  clearingRateLimit.value = true;

  try {
    await sanctumFetch("/api/google-analytics/rate-limit", {
      method: "DELETE",
    });

    toast.success("Rate limit cleared successfully");

    // Refresh analytics data
    await fetchAnalytics(true);
  } catch (error) {
    console.error("Error clearing rate limit:", error);
    toast.error("Failed to clear rate limit", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    clearingRateLimit.value = false;
  }
};

onMounted(async () => {
  // Start both fetches in parallel for faster initial load
  // Realtime data will update independently via auto-refresh
  startRealtimeRefresh();

  // Fetch aggregate data (will use cache if available)
  // Backend now uses daily aggregation: fetches 365 days once, aggregates on-demand
  // No need to preload multiple periods - all periods are instant!
  await fetchAnalytics();
});
</script>
