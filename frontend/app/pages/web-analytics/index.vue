<template>
  <div class="min-h-screen-offset mx-auto flex max-w-7xl flex-col space-y-6 pb-12">
    <!-- <pre v-if="aggregateData">
        {{ aggregateData }}
    </pre> -->
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:analysis-text-link" class="size-5 sm:size-6" />
        <h1 class="page-title">Web Analytics Dashboard</h1>
      </div>

      <div class="ml-auto flex shrink-0 gap-1 sm:gap-2">
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

    <div class="flex flex-wrap items-center justify-between gap-4">
      <div class="flex flex-wrap items-center gap-4">
        <div class="flex items-center gap-2">
          <!-- <label class="text-muted-foreground text-sm font-medium"> Date Range: </label> -->
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

        <div class="text-sm font-medium tracking-tight">
          {{ formatDate(startDate) }} - {{ formatDate(endDate) }}
        </div>
      </div>

      <div class="text-muted-foreground flex items-center gap-x-1.5 text-sm tracking-tight">
        <div v-if="!cacheInfo || cacheInfo?.is_updating" class="flex items-center gap-x-1.5">
          <Spinner class="size-3.5 shrink-0" />
          <span>Updating...</span>
        </div>
        <div v-else>
          <span
            >Last updated {{ formatCacheAge(cacheInfo?.cache_age_minutes) }}.
            <span v-if="cacheInfo?.next_update_in_minutes"
              >Next update in {{ Math.ceil(cacheInfo?.next_update_in_minutes) }} min<span
                v-if="Math.ceil(cacheInfo?.next_update_in_minutes) > 1"
                >s</span
              >.
            </span></span
          >
        </div>
      </div>
    </div>

    <div
      v-if="loading"
      class="border-border bg-pattern-diagonal flex grow items-center justify-center overflow-hidden rounded-xl border p-6"
    >
      <div class="flex items-center gap-2">
        <Spinner class="size-5 shrink-0" />
        <span class="text-sm tracking-tight">Loading analytics data..</span>
      </div>
    </div>

    <div v-else-if="error" class="flex items-center justify-center p-6">
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

    <template v-else-if="aggregateData">
      <AnalyticsSummaryCards :metrics="summaryMetrics" />

      <div v-if="propertyBreakdown.length > 0" class="space-y-4">
        <div>
          <h2 class="text-foreground flex items-center gap-2 font-semibold">
            <Icon name="hugeicons:analytics-01" class="size-5" />
            Analytics by Property
          </h2>
          <p class="text-muted-foreground text-sm">
            {{ propertyBreakdown.length }} active
            {{ propertyBreakdown.length === 1 ? "property" : "properties" }}
          </p>
        </div>
        <div class="grid gap-4">
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
    </template>

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
const { $dayjs } = useNuxtApp();

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
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
} = useMergedAnalytics(parseInt(selectedRange.value));

const endDate = computed(() => $dayjs());
const startDate = computed(() => $dayjs().subtract(parseInt(selectedRange.value), "day"));

const summaryMetrics = computed(() => {
  if (!aggregateData.value?.totals) return [];

  const totals = aggregateData.value.totals;

  return [
    {
      key: "onlineUsers",
      label: "Online Visitors",
      description: "People browsing your site right now.",
      value: totals.onlineUsers || 0,
      formattedValue: formatNumber(totals.onlineUsers || 0),
      icon: "hugeicons:wifi-02",
      bgClass: "bg-green-500/10",
      iconClass: "text-green-700 dark:text-green-400",
    },
    {
      key: "activeUsers",
      label: "Total Visitors",
      description: "Everyone who visited your site.",
      value: totals.activeUsers || 0,
      formattedValue: formatNumber(totals.activeUsers || 0),
      icon: "hugeicons:user-multiple-02",
      bgClass: "bg-blue-500/10",
      iconClass: "text-blue-700 dark:text-blue-400",
    },
    {
      key: "newUsers",
      label: "New Visitors",
      description: "First-time visitors to your site.",
      value: totals.newUsers || 0,
      formattedValue: formatNumber(totals.newUsers || 0),
      icon: "hugeicons:user-add-02",
      bgClass: "bg-sky-500/10",
      iconClass: "text-sky-700 dark:text-sky-400",
    },
    {
      key: "sessions",
      label: "Total Sessions",
      description: "Number of times people opened your site.",
      value: totals.sessions || 0,
      formattedValue: formatNumber(totals.sessions || 0),
      icon: "hugeicons:cursor-pointer-02",
      bgClass: "bg-indigo-500/10",
      iconClass: "text-indigo-700 dark:text-indigo-400",
    },
    {
      key: "screenPageViews",
      label: "Page Views",
      description: "Total pages viewed by visitors.",
      value: totals.screenPageViews || 0,
      formattedValue: formatNumber(totals.screenPageViews || 0),
      icon: "hugeicons:view",
      bgClass: "bg-pink-500/10",
      iconClass: "text-pink-700 dark:text-pink-400",
    },
    {
      key: "bounceRate",
      label: "Bounce Rate",
      description: "Visitors who left immediately.",
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
      description: "How long visitors stay on your site.",
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

const handleDateRangeChange = async () => {
  await changeDateRange(parseInt(selectedRange.value));
};

const refreshData = () => {
  fetchAnalytics(true);
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

const formatDate = (date) => date.format("MMM DD, YYYY");

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

onMounted(() => {
  fetchAnalytics();
  startRealtimeRefresh();
});
</script>
