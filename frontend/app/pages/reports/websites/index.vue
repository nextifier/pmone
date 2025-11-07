<template>
  <div class="mx-auto max-w-7xl space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:analysis-text-link" class="size-5 sm:size-6" />
        <h1 class="page-title">Web Analytics Dashboard</h1>
      </div>

      <div class="ml-auto flex shrink-0 gap-2">
        <button
          @click="refreshData"
          :disabled="loading"
          class="border-border hover:bg-muted flex items-center gap-x-1.5 rounded-md border px-3 py-1.5 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Icon
            name="hugeicons:refresh"
            class="size-4 shrink-0"
            :class="{ 'animate-spin': loading }"
          />
          <span>Refresh</span>
        </button>
      </div>
    </div>

    <!-- Date Range Selector & Cache Info -->
    <div class="border-border bg-card rounded-lg border p-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-4">
          <div class="flex items-center gap-2">
            <label class="text-muted-foreground text-sm font-medium">
              Date Range:
            </label>
            <select
              v-model="selectedRange"
              @change="handleDateRangeChange"
              class="border-border bg-background rounded-md border px-3 py-1.5 text-sm"
            >
              <option value="7">Last 7 days</option>
              <option value="14">Last 14 days</option>
              <option value="30">Last 30 days</option>
              <option value="90">Last 90 days</option>
            </select>
          </div>

          <div class="text-muted-foreground text-sm">
            {{ formatDate(startDate) }} - {{ formatDate(endDate) }}
          </div>
        </div>

        <!-- Cache Info -->
        <div v-if="cacheInfo" class="flex items-center gap-2">
          <div
            v-if="cacheInfo.is_updating"
            class="flex items-center gap-1.5 rounded-full bg-blue-500/10 px-3 py-1"
          >
            <Icon
              name="hugeicons:loading-03"
              class="text-blue-600 dark:text-blue-400 size-3.5 animate-spin"
            />
            <span class="text-blue-600 dark:text-blue-400 text-xs font-medium"
              >Updating...</span
            >
          </div>
          <div class="text-muted-foreground text-xs">
            <span>Last updated {{ formatCacheAge(cacheInfo.cache_age_minutes) }}</span>
            <template v-if="cacheInfo.next_update_in_minutes >= 0">
              <span class="mx-1">•</span>
              <span
                >Next update in {{ Math.ceil(cacheInfo.next_update_in_minutes) }} min</span
              >
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div
      v-if="loading"
      class="border-border bg-card flex items-center justify-center rounded-lg border p-12"
    >
      <div class="flex flex-col items-center gap-3">
        <Icon
          name="hugeicons:loading-03"
          class="text-primary size-8 animate-spin"
        />
        <p class="text-muted-foreground text-sm">Loading analytics data...</p>
      </div>
    </div>

    <!-- Error State -->
    <div
      v-else-if="error"
      class="border-border bg-card rounded-lg border p-6"
    >
      <div class="flex flex-col items-center gap-3 text-center">
        <Icon name="hugeicons:alert-circle" class="text-destructive size-8" />
        <div>
          <h3 class="text-foreground mb-1 font-semibold">
            Failed to load analytics
          </h3>
          <p class="text-muted-foreground text-sm">{{ error }}</p>
        </div>
        <button
          @click="refreshData"
          class="bg-primary text-primary-foreground hover:bg-primary/90 mt-2 rounded-md px-4 py-2 text-sm font-medium"
        >
          Try Again
        </button>
      </div>
    </div>

    <!-- Data Display -->
    <template v-else-if="aggregateData">
      <!-- Overall Summary Cards -->
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div
          v-for="metric in summaryMetrics"
          :key="metric.key"
          class="border-border bg-card rounded-lg border p-5 transition-colors hover:bg-muted/50"
        >
          <div class="flex items-center justify-between">
            <p class="text-muted-foreground text-sm font-medium">
              {{ metric.label }}
            </p>
            <div
              class="flex size-10 items-center justify-center rounded-lg"
              :class="metric.bgClass"
            >
              <Icon :name="metric.icon" class="size-5" :class="metric.iconClass" />
            </div>
          </div>
          <div class="mt-3">
            <p class="text-foreground text-3xl font-bold tracking-tight">
              {{ formatMetricValue(metric.key, metric.value) }}
            </p>
            <p class="text-muted-foreground mt-1 text-xs">
              {{ metric.description }}
            </p>
          </div>
        </div>
      </div>

      <!-- Property Breakdown Cards -->
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-foreground text-lg font-semibold">
              Analytics by Property
            </h2>
            <p class="text-muted-foreground text-sm">
              Click on a property to view detailed analytics
            </p>
          </div>
          <div class="text-muted-foreground text-sm">
            {{ propertyBreakdown.length }} active
            {{ propertyBreakdown.length === 1 ? "property" : "properties" }}
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <NuxtLink
            v-for="property in propertyBreakdown"
            :key="property.property_id"
            :to="`/reports/websites/${property.property_id}`"
            class="border-border bg-card hover:border-primary group relative overflow-hidden rounded-lg border p-5 transition-all hover:shadow-lg"
          >
            <div class="mb-4 flex items-start justify-between">
              <div class="flex-1">
                <h3
                  class="text-foreground mb-1 font-semibold group-hover:text-primary transition-colors"
                >
                  {{ property.property_name }}
                </h3>
                <p class="text-muted-foreground text-xs">
                  Property ID: {{ property.property_id }}
                </p>
              </div>
              <Icon
                name="hugeicons:arrow-right-01"
                class="text-muted-foreground group-hover:text-primary size-5 transition-all group-hover:translate-x-1"
              />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="bg-muted/30 rounded-md p-2.5">
                <p class="text-muted-foreground text-xs">Active Users</p>
                <p class="text-foreground mt-1 text-lg font-semibold">
                  {{ formatNumber(property.metrics.activeUsers || 0) }}
                </p>
              </div>
              <div class="bg-muted/30 rounded-md p-2.5">
                <p class="text-muted-foreground text-xs">Sessions</p>
                <p class="text-foreground mt-1 text-lg font-semibold">
                  {{ formatNumber(property.metrics.sessions || 0) }}
                </p>
              </div>
              <div class="bg-muted/30 rounded-md p-2.5">
                <p class="text-muted-foreground text-xs">Page Views</p>
                <p class="text-foreground mt-1 text-lg font-semibold">
                  {{ formatNumber(property.metrics.screenPageViews || 0) }}
                </p>
              </div>
              <div class="bg-muted/30 rounded-md p-2.5">
                <p class="text-muted-foreground text-xs">Bounce Rate</p>
                <p class="text-foreground mt-1 text-lg font-semibold">
                  {{ formatPercent(property.metrics.bounceRate || 0) }}
                </p>
              </div>
            </div>

            <div class="mt-3 flex items-center gap-2">
              <span
                v-if="property.is_fresh"
                class="bg-green-500/10 text-green-600 dark:text-green-400 rounded-full px-2 py-0.5 text-xs font-medium"
              >
                Fresh Data
              </span>
              <span
                v-if="property.cached_at"
                class="text-muted-foreground text-xs"
              >
                Cached {{ formatRelativeTime(property.cached_at) }}
              </span>
            </div>
          </NuxtLink>
        </div>
      </div>

      <!-- Top Pages -->
      <div
        v-if="aggregateData.top_pages && aggregateData.top_pages.length > 0"
        class="border-border bg-card rounded-lg border"
      >
        <div class="border-border border-b p-4">
          <h2 class="text-foreground flex items-center gap-2 font-semibold">
            <Icon name="hugeicons:file-star" class="size-5" />
            Top Pages
          </h2>
          <p class="text-muted-foreground text-sm">
            Most visited pages across all properties
          </p>
        </div>
        <div class="divide-border divide-y">
          <div
            v-for="(page, index) in aggregateData.top_pages.slice(0, 10)"
            :key="index"
            class="hover:bg-muted/30 p-4 transition-colors"
          >
            <div class="flex items-start justify-between gap-4">
              <div class="flex-1">
                <div class="flex items-center gap-2">
                  <span
                    class="bg-primary/10 text-primary flex size-6 items-center justify-center rounded-full text-xs font-bold"
                  >
                    {{ index + 1 }}
                  </span>
                  <p class="text-foreground font-medium">
                    {{ page.title }}
                  </p>
                </div>
                <p class="text-muted-foreground ml-8 mt-1 text-sm">
                  {{ page.path }}
                </p>
                <p class="text-muted-foreground ml-8 text-xs">
                  {{ page.property_name }}
                </p>
              </div>
              <div class="text-right">
                <p class="text-foreground text-lg font-semibold">
                  {{ formatNumber(page.pageviews) }}
                </p>
                <p class="text-muted-foreground text-xs">views</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Traffic Sources & Devices Grid -->
      <div class="grid gap-4 lg:grid-cols-2">
        <!-- Traffic Sources -->
        <div
          v-if="
            aggregateData.traffic_sources &&
            aggregateData.traffic_sources.length > 0
          "
          class="border-border bg-card rounded-lg border"
        >
          <div class="border-border border-b p-4">
            <h2 class="text-foreground flex items-center gap-2 font-semibold">
              <Icon name="hugeicons:link-square-02" class="size-5" />
              Traffic Sources
            </h2>
            <p class="text-muted-foreground text-sm">
              Where your visitors come from
            </p>
          </div>
          <div class="divide-border divide-y">
            <div
              v-for="(source, index) in aggregateData.traffic_sources.slice(
                0,
                5
              )"
              :key="index"
              class="hover:bg-muted/30 p-4 transition-colors"
            >
              <div class="flex items-center justify-between">
                <div class="flex-1">
                  <p class="text-foreground font-medium">
                    {{ source.source }}
                  </p>
                  <p class="text-muted-foreground text-sm">
                    {{ source.medium }}
                  </p>
                </div>
                <div class="text-right">
                  <p class="text-foreground font-semibold">
                    {{ formatNumber(source.sessions) }}
                  </p>
                  <p class="text-muted-foreground text-xs">sessions</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Device Categories -->
        <div
          v-if="aggregateData.devices && aggregateData.devices.length > 0"
          class="border-border bg-card rounded-lg border"
        >
          <div class="border-border border-b p-4">
            <h2 class="text-foreground flex items-center gap-2 font-semibold">
              <Icon name="hugeicons:monitor-01" class="size-5" />
              Devices
            </h2>
            <p class="text-muted-foreground text-sm">
              Device breakdown of your visitors
            </p>
          </div>
          <div class="p-4">
            <div class="space-y-4">
              <div
                v-for="(device, index) in aggregateData.devices"
                :key="index"
                class="space-y-2"
              >
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <Icon
                      :name="getDeviceIcon(device.device)"
                      class="text-muted-foreground size-4"
                    />
                    <span class="text-foreground capitalize">{{
                      device.device
                    }}</span>
                  </div>
                  <div class="text-right">
                    <p class="text-foreground font-semibold">
                      {{ formatNumber(device.users) }}
                    </p>
                    <p class="text-muted-foreground text-xs">users</p>
                  </div>
                </div>
                <div class="bg-muted h-2 overflow-hidden rounded-full">
                  <div
                    class="bg-primary h-full transition-all duration-500"
                    :style="{
                      width: `${calculatePercentage(device.users, totalDeviceUsers)}%`,
                    }"
                  ></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Period Info -->
      <div class="border-border bg-muted/30 rounded-lg border p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <p class="text-foreground text-sm font-medium">Data Period</p>
            <p class="text-muted-foreground text-xs">
              {{ aggregateData.period?.start_date }} to
              {{ aggregateData.period?.end_date }}
            </p>
          </div>
          <div>
            <p class="text-foreground text-sm font-medium">
              {{ aggregateData.properties_count || 0 }} Properties
            </p>
            <p class="text-muted-foreground text-xs">
              {{ aggregateData.successful_fetches || 0 }} successful fetches
            </p>
          </div>
        </div>
      </div>
    </template>

    <!-- Empty State -->
    <div
      v-else
      class="border-border bg-card flex items-center justify-center rounded-lg border p-12"
    >
      <div class="flex flex-col items-center gap-3 text-center">
        <Icon
          name="hugeicons:database-01"
          class="text-muted-foreground size-12"
        />
        <div>
          <h3 class="text-foreground mb-1 font-semibold">No data available</h3>
          <p class="text-muted-foreground text-sm">
            Analytics data will appear here once properties are configured
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const { $dayjs } = useNuxtApp();
const analyticsStore = useAnalyticsStore();

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("", {
  title: `Web Analytics Dashboard`,
  description: "View aggregated analytics data from all Google Analytics 4 properties",
});

// State
const loading = ref(false);
const error = ref(null);
const aggregateData = ref(null);
const selectedRange = ref("30");

// Computed dates
const endDate = computed(() => $dayjs());
const startDate = computed(() =>
  $dayjs().subtract(parseInt(selectedRange.value), "day")
);

// Summary metrics
const summaryMetrics = computed(() => {
  if (!aggregateData.value?.totals) return [];

  return [
    {
      key: "activeUsers",
      label: "Total Active Users",
      value: aggregateData.value.totals.activeUsers || 0,
      icon: "hugeicons:user-multiple-02",
      bgClass: "bg-blue-500/10",
      iconClass: "text-blue-600 dark:text-blue-400",
      description: "Unique visitors across all sites",
    },
    {
      key: "sessions",
      label: "Total Sessions",
      value: aggregateData.value.totals.sessions || 0,
      icon: "hugeicons:cursor-pointer-02",
      bgClass: "bg-green-500/10",
      iconClass: "text-green-600 dark:text-green-400",
      description: "Total browsing sessions",
    },
    {
      key: "screenPageViews",
      label: "Total Page Views",
      value: aggregateData.value.totals.screenPageViews || 0,
      icon: "hugeicons:view",
      bgClass: "bg-purple-500/10",
      iconClass: "text-purple-600 dark:text-purple-400",
      description: "Total pages viewed",
    },
    {
      key: "bounceRate",
      label: "Avg Bounce Rate",
      value: aggregateData.value.totals.bounceRate || 0,
      icon: "hugeicons:arrow-turn-backward",
      bgClass: "bg-orange-500/10",
      iconClass: "text-orange-600 dark:text-orange-400",
      description: "Average across all properties",
    },
  ];
});

// Property breakdown
const propertyBreakdown = computed(() => {
  return aggregateData.value?.property_breakdown || [];
});

// Cache info
const cacheInfo = computed(() => {
  return aggregateData.value?.cache_info || null;
});

// Total device users for percentage calculation
const totalDeviceUsers = computed(() => {
  if (!aggregateData.value?.devices) return 0;
  return aggregateData.value.devices.reduce(
    (sum, device) => sum + (device.users || 0),
    0
  );
});

// Auto-refresh timer
let autoRefreshTimeout = null;

// Fetch analytics data
const fetchAnalytics = async (silent = false) => {
  // Clear any existing auto-refresh
  if (autoRefreshTimeout) {
    clearTimeout(autoRefreshTimeout);
    autoRefreshTimeout = null;
  }

  // Check Pinia store first for fresh data (but not on silent refresh)
  if (analyticsStore.isAggregateFresh && !silent) {
    console.log("✅ Using Pinia cache for aggregate data");
    aggregateData.value = analyticsStore.aggregateData;
    return;
  }

  // Only show loading indicator if we don't have any data AND not silent refresh
  const showLoading = !aggregateData.value && !silent;
  if (showLoading) {
    loading.value = true;
  }
  error.value = null;

  try {
    const client = useSanctumClient();
    const days = parseInt(selectedRange.value);

    console.log("Fetching aggregate analytics for", days, "days...", silent ? "(silent)" : "");

    const { data } = await client(`/api/google-analytics/aggregate?days=${days}`);

    console.log("Aggregate data received:", data);
    aggregateData.value = data;

    // Save to Pinia store for future use
    analyticsStore.setAggregate(data);
    console.log("💾 Saved aggregate data to Pinia store");

    // Auto-refresh logic based on cache state
    if (data.cache_info?.initial_load || (data.cache_info?.is_updating && data.cache_info?.properties_count === 0)) {
      // Initial load with empty data - refresh quickly
      console.log("🔄 Initial load detected, will auto-refresh in 5 seconds");
      autoRefreshTimeout = setTimeout(() => {
        fetchAnalytics(true); // Silent refresh
      }, 5000);
    } else if (data.cache_info?.is_updating) {
      // Has data but updating in background - refresh slower
      console.log("🔄 Data updating in background, will auto-refresh in 15 seconds");
      autoRefreshTimeout = setTimeout(() => {
        fetchAnalytics(true); // Silent refresh
      }, 15000);
    }
  } catch (err) {
    console.error("Error fetching analytics:", err);
    // Only show error if we don't have cached data to fall back on
    if (!aggregateData.value) {
      error.value =
        err.data?.message || err.message || "Failed to load analytics data";
    }
  } finally {
    if (showLoading) {
      loading.value = false;
    }
  }
};

// Handle date range change
const handleDateRangeChange = () => {
  fetchAnalytics();
};

// Refresh data
const refreshData = () => {
  fetchAnalytics();
};

// Format helpers
const formatNumber = (value) => {
  if (value === null || value === undefined) return "0";
  return new Intl.NumberFormat().format(Math.round(value));
};

const formatPercent = (value) => {
  if (value === null || value === undefined) return "0%";
  return `${(value * 100).toFixed(1)}%`;
};

const formatDate = (date) => {
  return date.format("MMM DD, YYYY");
};

const formatCacheAge = (minutes) => {
  if (minutes === null || minutes === undefined) return "just now";
  if (minutes < 1) return "just now";
  if (minutes === 1) return "1 minute ago";
  if (minutes < 60) return `${Math.floor(minutes)} minutes ago`;
  const hours = Math.floor(minutes / 60);
  if (hours === 1) return "1 hour ago";
  return `${hours} hours ago`;
};

const formatMetricValue = (key, value) => {
  if (key.toLowerCase().includes("rate")) {
    return formatPercent(value);
  }
  if (key.toLowerCase().includes("duration")) {
    return `${formatNumber(value)}s`;
  }
  return formatNumber(value);
};

const formatRelativeTime = (dateString) => {
  return $dayjs(dateString).fromNow();
};

const calculatePercentage = (value, total) => {
  if (!total) return 0;
  return ((value / total) * 100).toFixed(1);
};

const getDeviceIcon = (device) => {
  const deviceLower = device.toLowerCase();
  if (deviceLower.includes("mobile")) return "hugeicons:smart-phone-01";
  if (deviceLower.includes("tablet")) return "hugeicons:tablet-01";
  if (deviceLower.includes("desktop")) return "hugeicons:monitor-01";
  return "hugeicons:device-access";
};

// Load data on mount
onMounted(() => {
  fetchAnalytics();
});

// Cleanup on unmount
onUnmounted(() => {
  if (autoRefreshTimeout) {
    clearTimeout(autoRefreshTimeout);
  }
});
</script>
