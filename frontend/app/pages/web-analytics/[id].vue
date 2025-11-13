<template>
  <div class="mx-auto max-w-7xl space-y-6 pt-4 pb-16">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-6">
      <div class="flex flex-col items-start gap-y-4">
        <BackButton destination="/web-analytics" />
        <div>
          <h1 class="page-title">
            {{ propertyData?.property?.name || "Property Analytics" }}
          </h1>
          <p class="text-muted-foreground text-sm">Property ID: {{ route.params.id }}</p>
        </div>
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
          <label class="text-muted-foreground text-sm font-medium"> Date Range: </label>
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
        <Icon name="hugeicons:loading-03" class="text-primary size-8 animate-spin" />
        <p class="text-muted-foreground text-sm">Loading property analytics...</p>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="border-border bg-card rounded-lg border p-6">
      <div class="flex flex-col items-center gap-3 text-center">
        <Icon name="hugeicons:alert-circle" class="text-destructive size-8" />
        <div>
          <h3 class="text-foreground mb-1 font-semibold">Failed to load property analytics</h3>
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
    <template v-else-if="propertyData">
      <!-- Property Info Card -->
      <div class="border-border bg-card rounded-lg border p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="flex-1">
            <h2 class="text-foreground mb-1 text-lg font-semibold">
              {{ propertyData.property.name }}
            </h2>
            <p class="text-muted-foreground text-sm">
              {{ propertyData.property.account_name }}
            </p>
            <div class="mt-2 flex flex-wrap items-center gap-3">
              <div class="flex items-center gap-1.5 text-sm">
                <Icon name="hugeicons:checkmark-badge-01" class="size-4" />
                <span class="text-muted-foreground">
                  Property ID: {{ propertyData.property.property_id }}
                </span>
              </div>
              <div
                v-if="propertyData.property.last_synced_at"
                class="flex items-center gap-1.5 text-sm"
              >
                <Icon name="hugeicons:clock-03" class="size-4" />
                <span class="text-muted-foreground">
                  Last synced: {{ formatRelativeTime(propertyData.property.last_synced_at) }}
                </span>
              </div>
              <span
                v-if="propertyData.property.is_active"
                class="rounded-full bg-green-500/10 px-2 py-0.5 text-xs font-medium text-green-600 dark:text-green-400"
              >
                Active
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Metrics Cards -->
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div
          v-for="metric in mainMetrics"
          :key="metric.key"
          class="border-border bg-card hover:bg-muted/50 rounded-lg border p-5 transition-colors"
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

      <!-- Additional Metrics Grid -->
      <div class="grid gap-4 sm:grid-cols-2">
        <div
          v-for="metric in additionalMetrics"
          :key="metric.key"
          class="border-border bg-card rounded-lg border p-4"
        >
          <div class="flex items-center gap-2">
            <Icon :name="metric.icon" class="text-muted-foreground size-5" />
            <p class="text-muted-foreground text-sm font-medium">
              {{ metric.label }}
            </p>
          </div>
          <p class="text-foreground mt-2 text-2xl font-bold">
            {{ formatMetricValue(metric.key, metric.value) }}
          </p>
        </div>
      </div>

      <!-- Daily Growth Chart -->
      <div v-if="chartData.length > 0" class="border-border bg-card rounded-lg border">
        <div class="border-border border-b p-4">
          <h2 class="text-foreground flex items-center gap-2 font-semibold">
            <Icon name="hugeicons:chart-line-data-03" class="size-5" />
            Daily Growth
          </h2>
          <p class="text-muted-foreground text-sm">Page views and users over time</p>
        </div>

        <!-- Legend -->
        <div class="border-border flex flex-wrap items-center gap-4 border-b px-4 py-3">
          <div class="flex items-center gap-2">
            <div class="size-3 rounded bg-purple-500"></div>
            <span class="text-muted-foreground text-sm">Page Views</span>
          </div>
          <div class="flex items-center gap-2">
            <div class="size-3 rounded bg-blue-500"></div>
            <span class="text-muted-foreground text-sm">Active Users</span>
          </div>
        </div>

        <!-- Chart -->
        <div class="p-4">
          <div class="space-y-3">
            <div v-for="(row, index) in chartData" :key="index" class="space-y-2">
              <!-- Date Label -->
              <div class="flex items-center justify-between">
                <span class="text-muted-foreground text-xs font-medium">
                  {{ row.formattedDate }}
                </span>
                <div class="flex items-center gap-3 text-xs">
                  <span class="text-purple-600 dark:text-purple-400">
                    {{ formatNumber(row.pageViews) }} views
                  </span>
                  <span class="text-blue-600 dark:text-blue-400">
                    {{ formatNumber(row.users) }} users
                  </span>
                </div>
              </div>

              <!-- Page Views Bar -->
              <div class="space-y-1">
                <div class="bg-muted h-2 overflow-hidden rounded-full">
                  <div
                    class="h-full bg-purple-500 transition-all duration-500"
                    :style="{ width: `${row.pageViewsPercent}%` }"
                  ></div>
                </div>
              </div>

              <!-- Users Bar -->
              <div class="space-y-1">
                <div class="bg-muted h-2 overflow-hidden rounded-full">
                  <div
                    class="h-full bg-blue-500 transition-all duration-500"
                    :style="{ width: `${row.usersPercent}%` }"
                  ></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Top Pages -->
      <div
        v-if="propertyData.top_pages?.length > 0"
        class="border-border bg-card rounded-lg border"
      >
        <div class="border-border border-b p-4">
          <h2 class="text-foreground flex items-center gap-2 font-semibold">
            <Icon name="hugeicons:file-star" class="size-5" />
            Top Pages
          </h2>
          <p class="text-muted-foreground text-sm">Most visited pages for this property</p>
        </div>
        <div class="divide-border divide-y">
          <div
            v-for="(page, index) in propertyData.top_pages"
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
                  <p class="text-foreground font-medium">{{ page.title }}</p>
                </div>
                <p class="text-muted-foreground mt-1 ml-8 text-sm">
                  {{ page.path }}
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
          v-if="propertyData.traffic_sources?.length > 0"
          class="border-border bg-card rounded-lg border"
        >
          <div class="border-border border-b p-4">
            <h2 class="text-foreground flex items-center gap-2 font-semibold">
              <Icon name="hugeicons:link-square-02" class="size-5" />
              Traffic Sources
            </h2>
            <p class="text-muted-foreground text-sm">Where your visitors come from</p>
          </div>
          <div class="divide-border divide-y">
            <div
              v-for="(source, index) in propertyData.traffic_sources"
              :key="index"
              class="hover:bg-muted/30 p-4 transition-colors"
            >
              <div class="flex items-center justify-between">
                <div class="flex-1">
                  <p class="text-foreground font-medium">{{ source.source }}</p>
                  <p class="text-muted-foreground text-sm">{{ source.medium }}</p>
                </div>
                <div class="text-right">
                  <p class="text-foreground font-semibold">
                    {{ formatNumber(source.sessions) }}
                  </p>
                  <p class="text-muted-foreground text-xs">sessions</p>
                  <p class="text-muted-foreground text-xs">
                    {{ formatNumber(source.users) }} users
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Device Categories -->
        <div
          v-if="propertyData.devices?.length > 0"
          class="border-border bg-card rounded-lg border"
        >
          <div class="border-border border-b p-4">
            <h2 class="text-foreground flex items-center gap-2 font-semibold">
              <Icon name="hugeicons:monitor-01" class="size-5" />
              Devices
            </h2>
            <p class="text-muted-foreground text-sm">Device breakdown of your visitors</p>
          </div>
          <div class="p-4">
            <div class="space-y-4">
              <div v-for="(device, index) in propertyData.devices" :key="index" class="space-y-2">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <Icon
                      :name="getDeviceIcon(device.device)"
                      class="text-muted-foreground size-4"
                    />
                    <span class="text-foreground capitalize">{{ device.device }}</span>
                  </div>
                  <div class="text-right">
                    <p class="text-foreground font-semibold">
                      {{ formatNumber(device.users) }}
                    </p>
                    <p class="text-muted-foreground text-xs">
                      {{ formatNumber(device.sessions) }} sessions
                    </p>
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

      <!-- Daily Metrics Table -->
      <div v-if="propertyData.rows?.length > 0" class="border-border bg-card rounded-lg border">
        <div class="border-border border-b p-4">
          <h2 class="text-foreground flex items-center gap-2 font-semibold">
            <Icon name="hugeicons:chart-line-data-03" class="size-5" />
            Daily Metrics
          </h2>
          <p class="text-muted-foreground text-sm">Day-by-day breakdown of metrics</p>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-muted/30">
              <tr class="border-border border-b">
                <th class="text-muted-foreground px-4 py-3 text-left font-medium">Date</th>
                <th class="text-muted-foreground px-4 py-3 text-right font-medium">Active Users</th>
                <th class="text-muted-foreground px-4 py-3 text-right font-medium">Sessions</th>
                <th class="text-muted-foreground px-4 py-3 text-right font-medium">Page Views</th>
                <th class="text-muted-foreground px-4 py-3 text-right font-medium">Bounce Rate</th>
              </tr>
            </thead>
            <tbody class="divide-border divide-y">
              <tr
                v-for="(row, index) in propertyData.rows.slice().reverse()"
                :key="index"
                class="hover:bg-muted/30 transition-colors"
              >
                <td class="text-foreground px-4 py-3">
                  {{ formatRowDate(row.date) }}
                </td>
                <td class="text-foreground px-4 py-3 text-right font-medium">
                  {{ formatNumber(row.activeUsers || 0) }}
                </td>
                <td class="text-foreground px-4 py-3 text-right font-medium">
                  {{ formatNumber(row.sessions || 0) }}
                </td>
                <td class="text-foreground px-4 py-3 text-right font-medium">
                  {{ formatNumber(row.screenPageViews || 0) }}
                </td>
                <td class="text-foreground px-4 py-3 text-right font-medium">
                  {{ formatPercent(row.bounceRate || 0) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Period Info -->
      <div class="border-border bg-muted/30 rounded-lg border p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div>
            <p class="text-foreground text-sm font-medium">Data Period</p>
            <p class="text-muted-foreground text-xs">
              {{ propertyData.period?.start_date }} to {{ propertyData.period?.end_date }}
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
        <Icon name="hugeicons:database-01" class="text-muted-foreground size-12" />
        <div>
          <h3 class="text-foreground mb-1 font-semibold">No data available</h3>
          <p class="text-muted-foreground text-sm">
            Property analytics data will appear here once loaded
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const { $dayjs } = useNuxtApp();
const route = useRoute();

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

defineOptions({
  name: "web-analytics-id",
});

// State
const loading = ref(false);
const error = ref(null);
const propertyData = ref(null);
const selectedRange = ref("30");

// Computed
const endDate = computed(() => $dayjs());
const startDate = computed(() => $dayjs().subtract(parseInt(selectedRange.value), "day"));

// Dynamic page title
const pageTitle = computed(() => {
  if (propertyData.value?.property?.name) {
    return `${propertyData.value.property.name} - Analytics`;
  }
  return "Property Analytics";
});

// Set initial meta and watch for changes
useSeoMeta({
  title: pageTitle,
  description: "Detailed analytics for Google Analytics property",
});

const mainMetrics = computed(() => {
  if (!propertyData.value?.metrics) return [];

  return [
    {
      key: "activeUsers",
      label: "Active Users",
      value: propertyData.value.metrics.activeUsers || 0,
      icon: "hugeicons:user-multiple-02",
      bgClass: "bg-blue-500/10",
      iconClass: "text-blue-600 dark:text-blue-400",
      description: "Unique visitors",
    },
    {
      key: "sessions",
      label: "Sessions",
      value: propertyData.value.metrics.sessions || 0,
      icon: "hugeicons:cursor-pointer-02",
      bgClass: "bg-green-500/10",
      iconClass: "text-green-600 dark:text-green-400",
      description: "Total browsing sessions",
    },
    {
      key: "screenPageViews",
      label: "Page Views",
      value: propertyData.value.metrics.screenPageViews || 0,
      icon: "hugeicons:view",
      bgClass: "bg-purple-500/10",
      iconClass: "text-purple-600 dark:text-purple-400",
      description: "Total pages viewed",
    },
    {
      key: "bounceRate",
      label: "Bounce Rate",
      value: propertyData.value.metrics.bounceRate || 0,
      icon: "hugeicons:arrow-turn-backward",
      bgClass: "bg-orange-500/10",
      iconClass: "text-orange-600 dark:text-orange-400",
      description: "Single-page sessions",
    },
  ];
});

const additionalMetrics = computed(() => {
  if (!propertyData.value?.metrics) return [];

  return [
    {
      key: "newUsers",
      label: "New Users",
      value: propertyData.value.metrics.newUsers || 0,
      icon: "hugeicons:user-add-01",
    },
    {
      key: "averageSessionDuration",
      label: "Avg Session Duration",
      value: propertyData.value.metrics.averageSessionDuration || 0,
      icon: "hugeicons:time-03",
    },
  ];
});

const chartData = computed(() => {
  if (!propertyData.value?.rows?.length) return [];

  const rows = [...propertyData.value.rows].sort((a, b) => a.date - b.date);
  const maxPageViews = Math.max(...rows.map((r) => r.screenPageViews || 0));
  const maxUsers = Math.max(...rows.map((r) => r.activeUsers || 0));

  return rows.map((row) => {
    const pageViews = row.screenPageViews || 0;
    const users = row.activeUsers || 0;

    return {
      date: row.date,
      formattedDate: formatRowDate(row.date),
      pageViews,
      users,
      pageViewsPercent: maxPageViews > 0 ? (pageViews / maxPageViews) * 100 : 0,
      usersPercent: maxUsers > 0 ? (users / maxUsers) * 100 : 0,
    };
  });
});

const totalDeviceUsers = computed(() => {
  if (!propertyData.value?.devices) return 0;
  return propertyData.value.devices.reduce((sum, device) => sum + (device.users || 0), 0);
});

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

    // Handle rate limit errors specifically
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
const handleDateRangeChange = () => fetchPropertyAnalytics();
const refreshData = () => fetchPropertyAnalytics();

// Format helpers
const formatNumber = (value) => {
  if (value === null || value === undefined) return "0";
  return new Intl.NumberFormat().format(Math.round(value));
};

const formatPercent = (value) => {
  if (value === null || value === undefined) return "0%";
  return `${(value * 100).toFixed(1)}%`;
};

const formatDate = (date) => date.format("MMM DD, YYYY");

const formatRowDate = (dateString) => {
  const year = dateString.substring(0, 4);
  const month = dateString.substring(4, 6);
  const day = dateString.substring(6, 8);
  return $dayjs(`${year}-${month}-${day}`).format("MMM DD, YYYY");
};

const formatMetricValue = (key, value) => {
  if (key.toLowerCase().includes("rate")) return formatPercent(value);
  if (key.toLowerCase().includes("duration")) return `${formatNumber(value)}s`;
  return formatNumber(value);
};

const formatRelativeTime = (dateString) => $dayjs(dateString).fromNow();

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

// Lifecycle
onMounted(() => fetchPropertyAnalytics());
</script>
