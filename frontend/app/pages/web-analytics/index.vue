<template>
  <div class="min-h-screen-offset mx-auto flex max-w-7xl flex-col gap-y-6 pb-12">
    <!-- <pre v-if="aggregateData">
        {{ aggregateData }}
    </pre> -->
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-6">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:analysis-text-link" class="size-5 sm:size-6" />
        <h1 class="page-title">Web Analytics Dashboard</h1>
      </div>

      <div class="flex shrink-0 gap-1 sm:ml-auto sm:gap-2">
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
      </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-6">
      <div class="flex flex-wrap items-center gap-3">
        <Select v-model="selectedRange" @update:model-value="handleDateRangeChange">
          <SelectTrigger class="w-40">
            <SelectValue placeholder="Select date range" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="today">Today</SelectItem>
            <SelectItem value="yesterday">Yesterday</SelectItem>
            <SelectItem value="this_week">This week</SelectItem>
            <SelectItem value="7">Last 7 days</SelectItem>
            <SelectItem value="last_week">Last week</SelectItem>
            <SelectItem value="30">Last 30 days</SelectItem>
            <SelectItem value="this_month">This month</SelectItem>
            <SelectItem value="last_month">Last month</SelectItem>
            <SelectItem value="90">Last 90 days</SelectItem>
            <SelectItem value="this_year">This year</SelectItem>
            <SelectItem value="365">Last 365 days</SelectItem>
          </SelectContent>
        </Select>

        <div class="text-muted-foreground text-sm font-medium tracking-tighter">
          {{ formatDate(startDate) }} - {{ formatDate(endDate) }}
        </div>
      </div>

      <div class="text-muted-foreground flex items-center gap-x-2 text-sm tracking-tight">
        <div v-if="!cacheInfo || cacheInfo?.is_updating" class="flex items-center gap-x-1">
          <Spinner class="size-3.5 shrink-0" />
          <span>Updating..</span>
        </div>
        <div v-else class="flex items-center gap-x-2">
          <!-- Cache age warning for "today" period -->
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

        <!-- Force Refresh Button -->
        <button
          v-if="cacheInfo && !cacheInfo?.is_updating"
          @click="forceRefresh"
          :disabled="isRefreshing"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-xs font-medium tracking-tight transition-colors active:scale-98 disabled:opacity-50"
          :class="{
            'cursor-not-allowed': isRefreshing,
          }"
        >
          <Icon
            :name="isRefreshing ? 'hugeicons:loading-01' : 'hugeicons:refresh'"
            class="size-3.5 shrink-0"
            :class="{ 'animate-spin': isRefreshing }"
          />
          <span>{{ isRefreshing ? "Refreshing..." : "Refresh" }}</span>
        </button>
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
      <!-- Loading overlay when changing date range -->
      <div
        v-if="loading"
        class="bg-background/90 absolute inset-0 z-10 flex items-start justify-center pt-20 backdrop-blur-md"
      >
        <div
          class="border-border bg-card flex items-center gap-3 rounded-lg border px-6 py-4 shadow-lg"
        >
          <Spinner class="size-5 shrink-0" />
          <span class="text-sm font-medium tracking-tight"
            >Loading
            {{
              selectedRange === "today"
                ? "Today"
                : selectedRange === "yesterday"
                  ? "Yesterday"
                  : selectedRange === "this_week"
                    ? "This Week"
                    : selectedRange === "last_week"
                      ? "Last Week"
                      : selectedRange === "this_month"
                        ? "This Month"
                        : selectedRange === "last_month"
                          ? "Last Month"
                          : selectedRange === "this_year"
                            ? "This Year"
                            : `Last ${selectedRange} days`
            }}...</span
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

      <div>
        <h2 class="text-foreground text-lg font-semibold tracking-tighter">Overall Performance</h2>
        <p class="text-muted-foreground mt-1 text-sm tracking-tight">
          Combined metrics from all properties.
        </p>
        <AnalyticsSummaryCards
          :metrics="summaryMetrics"
          :property-breakdown="propertyBreakdown"
          class="mt-4"
        />
      </div>

      <div v-if="propertyBreakdown.length > 0">
        <h2 class="text-foreground text-lg font-semibold tracking-tighter">
          Analytics by Property
        </h2>
        <p class="text-muted-foreground mt-1 text-sm tracking-tight">
          {{ propertyBreakdown.length }} active
          {{ propertyBreakdown.length === 1 ? "property" : "properties" }}.
        </p>

        <div class="mt-4 grid grid-cols-[repeat(auto-fit,minmax(320px,1fr))] gap-x-4 gap-y-8">
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
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

const selectedRange = ref("30");

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

const summaryMetrics = computed(() => {
  if (!aggregateData.value?.totals) return [];

  const totals = aggregateData.value.totals;

  return [
    {
      key: "onlineUsers",
      label: "Online Now",
      description: "People viewing your site right now",
      value: totals.onlineUsers || 0,
      formattedValue: formatNumber(totals.onlineUsers || 0),
      icon: "hugeicons:wifi-02",
      bgClass: "bg-green-500/10",
      iconClass: "text-green-700 dark:text-green-400",
    },
    {
      key: "activeUsers",
      label: "Active Visitors",
      description: "Visitors who truly engaged with your site",
      value: totals.activeUsers || 0,
      formattedValue: formatNumber(totals.activeUsers || 0),
      icon: "hugeicons:user-multiple-02",
      bgClass: "bg-blue-500/10",
      iconClass: "text-blue-700 dark:text-blue-400",
    },
    {
      key: "totalUsers",
      label: "Total Visitors",
      description: "All unique visitors who ever came",
      value: totals.totalUsers || 0,
      formattedValue: formatNumber(totals.totalUsers || 0),
      icon: "hugeicons:user-group",
      bgClass: "bg-purple-500/10",
      iconClass: "text-purple-700 dark:text-purple-400",
    },
    {
      key: "newUsers",
      label: "New Visitors",
      description: "First-time visitors to your site",
      value: totals.newUsers || 0,
      formattedValue: formatNumber(totals.newUsers || 0),
      icon: "hugeicons:user-add-02",
      bgClass: "bg-sky-500/10",
      iconClass: "text-sky-700 dark:text-sky-400",
    },
    {
      key: "sessions",
      label: "Total Sessions",
      description: "How many times your site was opened",
      value: totals.sessions || 0,
      formattedValue: formatNumber(totals.sessions || 0),
      icon: "hugeicons:cursor-pointer-02",
      bgClass: "bg-indigo-500/10",
      iconClass: "text-indigo-700 dark:text-indigo-400",
    },
    {
      key: "screenPageViews",
      label: "Page Views",
      description: "Total count of pages being viewed",
      value: totals.screenPageViews || 0,
      formattedValue: formatNumber(totals.screenPageViews || 0),
      icon: "hugeicons:view",
      bgClass: "bg-pink-500/10",
      iconClass: "text-pink-700 dark:text-pink-400",
    },
    {
      key: "bounceRate",
      label: "Bounce Rate",
      description: "Visitors who left immediately",
      value: (totals.bounceRate || 0) * 100,
      formattedValue: formatPercent(totals.bounceRate || 0),
      format: "percent",
      icon: "hugeicons:undo-02",
      bgClass: "bg-red-500/10",
      iconClass: "text-red-700 dark:text-red-400",
    },
    {
      key: "averageSessionDuration",
      label: "Average Duration",
      description: "How long visitors stay on your site",
      value: totals.averageSessionDuration || 0,
      formattedValue: formatDuration(totals.averageSessionDuration || 0),
      format: "duration",
      icon: "hugeicons:time-quarter-02",
      bgClass: "bg-yellow-500/10",
      iconClass: "text-yellow-700 dark:text-yellow-400",
    },
  ];
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

const handleDateRangeChange = async (value) => {
  await changeDateRange(value);
};

const refreshData = () => {
  fetchAnalytics(true);
};

const isRefreshing = ref(false);
const forceRefresh = async () => {
  if (isRefreshing.value) return;

  isRefreshing.value = true;

  try {
    // Call the sync-now endpoint to force refresh
    const days =
      selectedRange.value === "today" || selectedRange.value === "yesterday"
        ? 1
        : parseInt(selectedRange.value) || 30;

    await $fetch("/api/google-analytics/aggregate/sync-now", {
      method: "POST",
      body: { days },
    });

    // Refresh the displayed data
    await fetchAnalytics(true);

    // Show success message
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

const formatNumber = (value) => {
  if (value === null || value === undefined) return "0";
  return new Intl.NumberFormat().format(Math.round(value));
};

const formatPercent = (value) => {
  if (value === null || value === undefined) return "0%";
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
