<template>
  <div class="mx-auto max-w-7xl space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:analysis-text-link" class="size-5 sm:size-6" />
        <h1 class="page-title">Web Analytics</h1>
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

    <!-- Date Range Selector -->
    <div class="border-border bg-card rounded-lg border p-4">
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
    <template v-else-if="analyticsData">
      <!-- Summary Cards -->
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div
          v-for="metric in summaryMetrics"
          :key="metric.key"
          class="border-border bg-card rounded-lg border p-4"
        >
          <div class="flex items-center justify-between">
            <p class="text-muted-foreground text-sm font-medium">
              {{ metric.label }}
            </p>
            <Icon :name="metric.icon" class="text-muted-foreground size-4" />
          </div>
          <div class="mt-2">
            <p class="text-foreground text-2xl font-bold">
              {{ formatNumber(metric.value) }}
            </p>
          </div>
        </div>
      </div>

      <!-- Properties Data -->
      <div class="border-border bg-card rounded-lg border">
        <div class="border-border border-b p-4">
          <h2 class="text-foreground font-semibold">Analytics by Property</h2>
          <p class="text-muted-foreground text-sm">
            Data from {{ analyticsData.length }} active
            {{ analyticsData.length === 1 ? "property" : "properties" }}
          </p>
        </div>

        <div class="divide-border divide-y">
          <div
            v-for="property in analyticsData"
            :key="property.property.id"
            class="p-4"
          >
            <div class="mb-3 flex items-start justify-between">
              <div>
                <h3 class="text-foreground font-medium">
                  {{ property.property.name }}
                </h3>
                <p class="text-muted-foreground text-sm">
                  {{ property.property.account_name }} • Property ID:
                  {{ property.property.property_id }}
                </p>
              </div>
              <span
                v-if="property.property.is_active"
                class="bg-green-500/10 text-green-600 dark:text-green-400 rounded-full px-2 py-0.5 text-xs font-medium"
              >
                Active
              </span>
            </div>

            <!-- Property Metrics -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <div class="bg-muted/50 rounded-md p-3">
                <p class="text-muted-foreground text-xs font-medium">
                  Active Users
                </p>
                <p class="text-foreground mt-1 text-lg font-semibold">
                  {{ formatNumber(property.metrics.activeUsers || 0) }}
                </p>
              </div>
              <div class="bg-muted/50 rounded-md p-3">
                <p class="text-muted-foreground text-xs font-medium">
                  Sessions
                </p>
                <p class="text-foreground mt-1 text-lg font-semibold">
                  {{ formatNumber(property.metrics.sessions || 0) }}
                </p>
              </div>
              <div class="bg-muted/50 rounded-md p-3">
                <p class="text-muted-foreground text-xs font-medium">
                  Page Views
                </p>
                <p class="text-foreground mt-1 text-lg font-semibold">
                  {{ formatNumber(property.metrics.screenPageViews || 0) }}
                </p>
              </div>
              <div class="bg-muted/50 rounded-md p-3">
                <p class="text-muted-foreground text-xs font-medium">
                  Bounce Rate
                </p>
                <p class="text-foreground mt-1 text-lg font-semibold">
                  {{ formatPercent(property.metrics.bounceRate || 0) }}
                </p>
              </div>
            </div>

            <!-- Raw Data (for debugging) -->
            <details class="mt-3">
              <summary
                class="text-muted-foreground hover:text-foreground cursor-pointer text-xs"
              >
                View raw data
              </summary>
              <pre
                class="bg-muted text-muted-foreground mt-2 overflow-x-auto rounded-md p-3 text-xs"
                >{{ JSON.stringify(property, null, 2) }}</pre
              >
            </details>
          </div>
        </div>
      </div>

      <!-- Aggregate Data -->
      <div
        v-if="aggregateData"
        class="border-border bg-card rounded-lg border"
      >
        <div class="border-border border-b p-4">
          <h2 class="text-foreground font-semibold">Aggregate Analytics</h2>
          <p class="text-muted-foreground text-sm">
            Combined data from all properties
          </p>
        </div>
        <div class="p-4">
          <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
              v-for="(value, key) in aggregateData.totals"
              :key="key"
              class="bg-muted/50 rounded-md p-4"
            >
              <p class="text-muted-foreground mb-1 text-sm font-medium capitalize">
                {{ formatMetricLabel(key) }}
              </p>
              <p class="text-foreground text-xl font-bold">
                {{ formatMetricValue(key, value) }}
              </p>
            </div>
          </div>

          <!-- Raw Aggregate Data -->
          <details class="mt-4">
            <summary
              class="text-muted-foreground hover:text-foreground cursor-pointer text-xs"
            >
              View raw aggregate data
            </summary>
            <pre
              class="bg-muted text-muted-foreground mt-2 overflow-x-auto rounded-md p-3 text-xs"
              >{{ JSON.stringify(aggregateData, null, 2) }}</pre
            >
          </details>
        </div>
      </div>
    </template>

    <!-- Empty State -->
    <div
      v-else
      class="border-border bg-card flex items-center justify-center rounded-lg border p-12"
    >
      <div class="flex flex-col items-center gap-3 text-center">
        <Icon name="hugeicons:database-01" class="text-muted-foreground size-8" />
        <div>
          <h3 class="text-foreground mb-1 font-semibold">No data available</h3>
          <p class="text-muted-foreground text-sm">
            Analytics data will appear here once loaded
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const { $dayjs } = useNuxtApp();

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("", {
  title: `Web Analytics`,
  description: "View analytics data from Google Analytics 4",
});

// State
const loading = ref(false);
const error = ref(null);
const analyticsData = ref(null);
const aggregateData = ref(null);
const selectedRange = ref("7");

// Computed dates
const endDate = computed(() => $dayjs());
const startDate = computed(() => $dayjs().subtract(parseInt(selectedRange.value), 'day'));

// Summary metrics
const summaryMetrics = computed(() => {
  if (!aggregateData.value?.totals) return [];

  return [
    {
      key: "activeUsers",
      label: "Total Active Users",
      value: aggregateData.value.totals.activeUsers || 0,
      icon: "hugeicons:user-multiple",
    },
    {
      key: "sessions",
      label: "Total Sessions",
      value: aggregateData.value.totals.sessions || 0,
      icon: "hugeicons:cursor-01",
    },
    {
      key: "screenPageViews",
      label: "Total Page Views",
      value: aggregateData.value.totals.screenPageViews || 0,
      icon: "hugeicons:view",
    },
    {
      key: "bounceRate",
      label: "Avg Bounce Rate",
      value: aggregateData.value.totals.bounceRate || 0,
      icon: "hugeicons:chart-decrease",
    },
  ];
});

// Fetch analytics data
const fetchAnalytics = async () => {
  loading.value = true;
  error.value = null;

  try {
    const client = useSanctumClient();
    const startDateStr = startDate.value.format("YYYY-MM-DD");
    const endDateStr = endDate.value.format("YYYY-MM-DD");

    console.log('Fetching aggregate data...');

    // Fetch aggregate data only for now (faster)
    const { data: aggregate } = await client(
      `/api/google-analytics/aggregate?start_date=${startDateStr}&end_date=${endDateStr}`
    );

    console.log('Aggregate data received:', aggregate);
    aggregateData.value = aggregate;

    // TODO: Temporarily disabled per-property fetching to avoid timeout
    // We'll implement this with better caching/batching later

    // For now, just get the properties list without analytics
    const { data: properties } = await client("/api/google-analytics/ga-properties");

    if (properties?.data?.length > 0) {
      // Just show properties without detailed analytics for now
      analyticsData.value = properties.data.map(property => ({
        property,
        metrics: {
          activeUsers: 0,
          sessions: 0,
          screenPageViews: 0,
          bounceRate: 0
        }
      }));
    }

  } catch (err) {
    console.error("Error fetching analytics:", err);
    error.value = err.data?.message || err.message || "Failed to load analytics data";
  } finally {
    loading.value = false;
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
  return new Intl.NumberFormat().format(value);
};

const formatPercent = (value) => {
  if (value === null || value === undefined) return "0%";
  return `${(value * 100).toFixed(2)}%`;
};

const formatDate = (date) => {
  return date.format("MMM DD, YYYY");
};

const formatMetricLabel = (key) => {
  return key
    .replace(/([A-Z])/g, " $1")
    .replace(/^./, (str) => str.toUpperCase())
    .trim();
};

const formatMetricValue = (key, value) => {
  if (key.toLowerCase().includes("rate")) {
    return formatPercent(value);
  }
  return formatNumber(value);
};

// Load data on mount
onMounted(() => {
  fetchAnalytics();
});
</script>
