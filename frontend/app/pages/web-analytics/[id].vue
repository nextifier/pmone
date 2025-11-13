<template>
  <div class="min-h-screen-offset mx-auto flex max-w-7xl flex-col gap-y-4 py-4">
    <div class="flex">
      <BackButton destination="/web-analytics" />
    </div>
    <div class="my-2 flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex items-center gap-x-2.5">
        <Avatar
          v-if="propertyData?.property?.project?.profile_image"
          :model="propertyData.property.project"
          class="size-12 shrink-0"
        />
        <div class="flex flex-col gap-y-1">
          <h1 class="page-title">
            {{ propertyData?.property?.name || "Property Analytics" }}
          </h1>
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
      </div>

      <div class="ml-auto flex shrink-0 items-center gap-2">
        <!-- <button
          @click="refreshData"
          :disabled="loading"
          class="border-border hover:bg-muted flex h-8 items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Icon
            name="hugeicons:refresh"
            class="size-4 shrink-0"
            :class="{ 'animate-spin': loading }"
          />
          <span>Refresh</span>
        </button> -->

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
      v-if="loading && !propertyData"
      class="border-border bg-pattern-diagonal flex grow items-center justify-center overflow-hidden rounded-xl border p-6"
    >
      <div class="flex items-center gap-2">
        <Spinner class="size-5 shrink-0" />
        <span class="text-sm tracking-tight">Loading property analytics..</span>
      </div>
    </div>

    <div v-else-if="error && !propertyData" class="flex items-center justify-center p-6">
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

    <div v-else-if="propertyData" class="relative grid grid-cols-1 gap-y-10">
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

      <div class="flex flex-col gap-y-4">
        <div class="flex flex-col gap-y-1">
          <h2 class="text-foreground text-lg font-semibold tracking-tighter">
            Property Performance
          </h2>
          <p class="text-muted-foreground text-sm tracking-tight">
            Analytics metrics for {{ propertyData.property.name }}.
          </p>
        </div>

        <ChartLineDefault
          v-if="propertyChartData?.length >= 2"
          :data="propertyChartData"
          :config="propertyChartConfig"
          data-key="activeUsers"
          class="h-auto! overflow-hidden rounded-xl border py-2.5"
        />

        <div
          v-else-if="propertyChartData?.length === 1"
          class="border-border bg-muted/30 flex items-center justify-center rounded-xl border p-8"
        >
          <p class="text-muted-foreground text-sm">
            Chart requires at least 2 days of data. Select a longer period to view the trend.
          </p>
        </div>

        <AnalyticsSummaryCards :metrics="summaryMetrics" :property-breakdown="[]" />
      </div>

      <div v-if="propertyData.top_pages?.length > 0" class="space-y-4">
        <div>
          <h2 class="text-foreground flex items-center gap-2 font-semibold">
            <Icon name="hugeicons:file-star" class="size-5" />
            Top Pages
          </h2>
          <p class="text-muted-foreground text-sm">Most visited pages for this property</p>
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
          v-if="propertyData.top_pages.length > topPagesLimit"
          @click="toggleTopPages"
          class="hover:bg-muted mx-auto flex items-center gap-x-1.5 rounded-md border px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
        >
          <Icon
            :name="showAllTopPages ? 'hugeicons:arrow-up-01' : 'hugeicons:arrow-down-01'"
            class="size-4"
          />
          <span>{{
            showAllTopPages ? "Show Less" : `Show All (${propertyData.top_pages.length})`
          }}</span>
        </button>
      </div>

      <div v-if="propertyData.traffic_sources?.length > 0" class="space-y-4">
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
          v-if="propertyData.traffic_sources.length > trafficSourcesLimit"
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
              : `Show All (${propertyData.traffic_sources.length})`
          }}</span>
        </button>
      </div>

      <AnalyticsDevicesBreakdown
        v-if="propertyData.devices?.length > 0"
        :devices="propertyData.devices"
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
                  >{{ propertyData }}</pre
                >
              </div>
            </template>
          </DialogResponsive>
        </div>

        <div
          v-if="propertyData.period"
          class="text-muted-foreground flex items-center justify-center gap-2 text-center text-sm tracking-tight"
        >
          <span
            >Data period: {{ propertyData.period.start_date }} to
            {{ propertyData.period.end_date }}</span
          >
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
          Property analytics data will appear here once loaded.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import DateRangeSelect from "@/components/analytics/DateRangeSelect.vue";
import ChartLineDefault from "@/components/chart/LineDefault.vue";

const { $dayjs } = useNuxtApp();
const route = useRoute();

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

defineOptions({
  name: "web-analytics-id",
});

// Load selected range from localStorage immediately (client-side only)
// Use same key as index page for consistency
const getInitialRange = () => {
  if (typeof window !== "undefined") {
    return localStorage.getItem("analytics_selected_range") || "30";
  }
  return "30";
};

// State
const loading = ref(true); // Start with true to prevent empty state flash during SSR
const error = ref(null);
const propertyData = ref(null);
const selectedRange = ref(getInitialRange());

// Realtime data
const { realtimeData, startAutoRefresh } = useRealtimeAnalytics();

// Watch for changes and save to localStorage
watch(selectedRange, (newValue) => {
  if (typeof window !== "undefined") {
    localStorage.setItem("analytics_selected_range", newValue);
  }
  fetchPropertyAnalytics();
});

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

// Dynamic page title
const pageTitle = computed(() => {
  if (propertyData.value?.property?.name) {
    return `${propertyData.value.property.name} - Analytics`;
  }
  return "Property Analytics";
});

useSeoMeta({
  title: pageTitle,
  description: "Detailed analytics for Google Analytics property",
});

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

// Format helpers (defined early for use in computed properties)
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

const summaryMetrics = computed(() => {
  if (!propertyData.value?.metrics) return [];

  const metrics = propertyData.value.metrics;
  const propertyId = String(route.params.id);

  return METRIC_CONFIGS.map((config) => {
    let value = metrics[config.key] || 0;

    // Get onlineUsers from realtime data
    if (config.key === "onlineUsers" && realtimeData.value?.property_breakdown) {
      const realtimeProperty = realtimeData.value.property_breakdown.find(
        (p) => String(p.property_id) === propertyId
      );
      value = realtimeProperty?.active_users || 0;
    }

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

// Chart data for ChartLineDefault
const propertyChartData = computed(() => {
  if (!propertyData.value?.rows || !Array.isArray(propertyData.value.rows)) {
    return [];
  }

  return propertyData.value.rows
    .map((item) => {
      // Backend returns date in YYYY-MM-DD format already
      return {
        date: new Date(item.date),
        activeUsers: item.activeUsers || 0,
      };
    })
    .filter((item) => !isNaN(item.date.getTime()))
    .sort((a, b) => a.date - b.date);
});

const propertyChartConfig = {
  activeUsers: {
    label: "Active Visitors",
    color: "var(--chart-1)",
  },
};

// Fetch property analytics
const fetchPropertyAnalytics = async () => {
  loading.value = true;
  error.value = null;

  try {
    const client = useSanctumClient();
    const propertyId = route.params.id;
    const startDateStr = startDate.value.format("YYYY-MM-DD");
    const endDateStr = endDate.value.format("YYYY-MM-DD");

    const { data } = await client(
      `/api/google-analytics/properties/${propertyId}/analytics?start_date=${startDateStr}&end_date=${endDateStr}`
    );

    propertyData.value = data;
  } catch (err) {
    console.error("Error fetching property analytics:", err);

    if (err.status === 429 || err.statusCode === 429) {
      error.value = "Too many requests. Please wait a moment and try again.";
    } else {
      error.value = err.data?.message || err.message || "Failed to load property analytics";
    }
  } finally {
    loading.value = false;
  }
};

// Handlers
const refreshData = () => fetchPropertyAnalytics();

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

const showAllTopPages = ref(false);
const topPagesLimit = 9;
const displayedTopPages = computed(() => {
  if (!propertyData.value?.top_pages) return [];
  const pages = propertyData.value.top_pages;
  return showAllTopPages.value ? pages : pages.slice(0, topPagesLimit);
});

const toggleTopPages = () => {
  showAllTopPages.value = !showAllTopPages.value;
};

const showAllTrafficSources = ref(false);
const trafficSourcesLimit = 8;
const displayedTrafficSources = computed(() => {
  if (!propertyData.value?.traffic_sources) return [];
  const sources = propertyData.value.traffic_sources;
  return showAllTrafficSources.value ? sources : sources.slice(0, trafficSourcesLimit);
});

const toggleTrafficSources = () => {
  showAllTrafficSources.value = !showAllTrafficSources.value;
};

// Lifecycle
onMounted(async () => {
  // Fetch property analytics first
  await fetchPropertyAnalytics();

  // Start realtime refresh for this property after we have property data
  // Use the actual property_id from the response, not the route param
  if (propertyData.value?.property?.property_id) {
    const propertyId = String(propertyData.value.property.property_id);
    try {
      startAutoRefresh([propertyId]);
    } catch (err) {
      console.error("Error starting realtime refresh:", err);
      // Continue without realtime data
    }
  }
});
</script>
