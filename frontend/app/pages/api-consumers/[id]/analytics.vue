<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex flex-col gap-y-6">
      <div class="flex items-center justify-between gap-2">
        <BackButton destination="/api-consumers" />
        <DialogViewRaw :data="analyticsData" />
      </div>

      <div class="flex w-full flex-wrap items-center justify-between gap-4">
        <div class="flex flex-col gap-y-1">
          <h1 class="page-title">API Analytics</h1>
          <p v-if="consumer" class="text-muted-foreground text-sm">
            Usage analytics for {{ consumer.name }}
          </p>
        </div>

        <DateRangeSelect v-model="selectedPeriod" />
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <Spinner class="size-8" />
    </div>

    <div v-else-if="error" class="py-12 text-center">
      <p class="text-destructive">{{ error }}</p>
    </div>

    <div v-else-if="analyticsData" class="space-y-6">
      <!-- Summary Cards -->
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="border-border rounded-lg border p-6">
          <div class="flex items-center gap-2">
            <div class="bg-muted text-primary rounded-lg p-2">
              <Icon name="hugeicons:api" class="size-5" />
            </div>
            <div class="text-muted-foreground text-sm font-medium">Total Requests</div>
          </div>
          <div class="text-primary mt-2 text-4xl font-semibold">
            {{ analyticsData.summary.total_requests.toLocaleString() }}
          </div>
          <div class="text-muted-foreground mt-1 text-xs">
            {{ analyticsData.summary.successful_requests.toLocaleString() }} successful
          </div>
        </div>

        <div class="border-border rounded-lg border p-6">
          <div class="flex items-center gap-2">
            <div class="bg-muted text-primary rounded-lg p-2">
              <Icon name="hugeicons:tick-02" class="size-5" />
            </div>
            <div class="text-muted-foreground text-sm font-medium">Success Rate</div>
          </div>
          <div class="text-primary mt-2 text-4xl font-semibold">
            {{ analyticsData.summary.success_rate }}%
          </div>
          <div class="text-muted-foreground mt-1 text-xs">
            {{ analyticsData.summary.failed_requests.toLocaleString() }} failed
          </div>
        </div>

        <div class="border-border rounded-lg border p-6">
          <div class="flex items-center gap-2">
            <div class="bg-muted text-primary rounded-lg p-2">
              <Icon name="hugeicons:clock-02" class="size-5" />
            </div>
            <div class="text-muted-foreground text-sm font-medium">Avg Response Time</div>
          </div>
          <div class="text-primary mt-2 text-4xl font-semibold">
            {{ analyticsData.summary.avg_response_time }}ms
          </div>
          <div class="text-muted-foreground mt-1 text-xs">
            Max: {{ analyticsData.summary.max_response_time }}ms
          </div>
        </div>

        <div class="border-border rounded-lg border p-6">
          <div class="flex items-center gap-2">
            <div class="bg-muted text-primary rounded-lg p-2">
              <Icon name="hugeicons:calendar-03" class="size-5" />
            </div>
            <div class="text-muted-foreground text-sm font-medium">Period</div>
          </div>
          <div class="text-primary mt-2 text-4xl font-semibold">
            {{ analyticsData.period.days }} days
          </div>
          <div class="text-muted-foreground mt-1 text-xs">
            {{ analyticsData.period.start_date }} - {{ analyticsData.period.end_date }}
          </div>
        </div>
      </div>

      <!-- Requests Over Time Chart -->
      <div class="border-border rounded-lg border p-4">
        <h2 class="mb-4 text-lg font-semibold tracking-tighter">Requests Over Time</h2>
        <div v-if="chartData?.length > 0">
          <ChartLine
            :data="chartData"
            :config="chartConfig"
            :gradient="true"
            data-key="count"
            class="h-auto! overflow-hidden py-2.5"
          />
        </div>
        <div v-else class="text-muted-foreground py-8 text-center tracking-tight">
          No request data available for this period
        </div>
      </div>

      <!-- Status Distribution and Top Endpoints -->
      <div class="grid gap-6 lg:grid-cols-2">
        <!-- Status Distribution -->
        <div class="border-border rounded-lg border p-4">
          <h2 class="mb-4 text-lg font-semibold tracking-tighter">Response Status</h2>
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="size-3 rounded-full bg-green-500"></span>
                <span class="text-sm tracking-tight">2xx Success</span>
              </div>
              <span class="font-mono text-sm font-medium">
                {{ analyticsData.status_distribution['2xx'].toLocaleString() }}
              </span>
            </div>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="size-3 rounded-full bg-blue-500"></span>
                <span class="text-sm tracking-tight">3xx Redirect</span>
              </div>
              <span class="font-mono text-sm font-medium">
                {{ analyticsData.status_distribution['3xx'].toLocaleString() }}
              </span>
            </div>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="size-3 rounded-full bg-yellow-500"></span>
                <span class="text-sm tracking-tight">4xx Client Error</span>
              </div>
              <span class="font-mono text-sm font-medium">
                {{ analyticsData.status_distribution['4xx'].toLocaleString() }}
              </span>
            </div>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="size-3 rounded-full bg-red-500"></span>
                <span class="text-sm tracking-tight">5xx Server Error</span>
              </div>
              <span class="font-mono text-sm font-medium">
                {{ analyticsData.status_distribution['5xx'].toLocaleString() }}
              </span>
            </div>
          </div>

          <!-- Visual bar chart -->
          <div v-if="totalStatusRequests > 0" class="mt-4 flex h-3 overflow-hidden rounded-full">
            <div
              v-if="analyticsData.status_distribution['2xx'] > 0"
              class="bg-green-500"
              :style="{
                width: `${(analyticsData.status_distribution['2xx'] / totalStatusRequests) * 100}%`,
              }"
            ></div>
            <div
              v-if="analyticsData.status_distribution['3xx'] > 0"
              class="bg-blue-500"
              :style="{
                width: `${(analyticsData.status_distribution['3xx'] / totalStatusRequests) * 100}%`,
              }"
            ></div>
            <div
              v-if="analyticsData.status_distribution['4xx'] > 0"
              class="bg-yellow-500"
              :style="{
                width: `${(analyticsData.status_distribution['4xx'] / totalStatusRequests) * 100}%`,
              }"
            ></div>
            <div
              v-if="analyticsData.status_distribution['5xx'] > 0"
              class="bg-red-500"
              :style="{
                width: `${(analyticsData.status_distribution['5xx'] / totalStatusRequests) * 100}%`,
              }"
            ></div>
          </div>
          <div v-else class="bg-muted mt-4 h-3 rounded-full"></div>
        </div>

        <!-- Hourly Distribution -->
        <div class="border-border rounded-lg border p-4">
          <h2 class="mb-4 text-lg font-semibold tracking-tighter">Today's Hourly Activity</h2>
          <div v-if="analyticsData.hourly_distribution?.length > 0" class="flex h-24 items-end gap-1">
            <div
              v-for="hour in 24"
              :key="hour - 1"
              class="bg-muted flex-1 rounded-t transition-all hover:opacity-80"
              :class="{
                'bg-primary!': getHourlyCount(hour - 1) > 0,
              }"
              :style="{
                height: `${getHourlyPercentage(hour - 1)}%`,
                minHeight: getHourlyCount(hour - 1) > 0 ? '8px' : '4px',
              }"
              v-tippy="`${hour - 1}:00 - ${getHourlyCount(hour - 1)} requests`"
            ></div>
          </div>
          <div v-else class="text-muted-foreground py-8 text-center text-sm tracking-tight">
            No activity today
          </div>
          <div
            v-if="analyticsData.hourly_distribution?.length > 0"
            class="text-muted-foreground mt-2 flex justify-between text-xs"
          >
            <span>00:00</span>
            <span>12:00</span>
            <span>23:00</span>
          </div>
        </div>
      </div>

      <!-- Top Endpoints -->
      <div class="border-border rounded-lg border p-4">
        <h2 class="mb-4 text-lg font-semibold tracking-tighter">Top Endpoints</h2>
        <div v-if="analyticsData.top_endpoints?.length > 0" class="space-y-3">
          <div
            v-for="endpoint in analyticsData.top_endpoints"
            :key="`${endpoint.method}-${endpoint.endpoint}`"
            class="flex items-center gap-4"
          >
            <span
              class="w-16 shrink-0 rounded px-2 py-1 text-center text-xs font-medium"
              :class="getMethodClass(endpoint.method)"
            >
              {{ endpoint.method }}
            </span>
            <div class="min-w-0 flex-1">
              <div class="truncate font-mono text-sm tracking-tight">/{{ endpoint.endpoint }}</div>
              <div class="text-muted-foreground text-xs">
                Avg: {{ endpoint.avg_time }}ms
              </div>
            </div>
            <div class="flex items-center gap-4">
              <div class="bg-muted relative h-2 w-24 overflow-hidden rounded-full">
                <div
                  class="bg-primary absolute inset-y-0 left-0 transition-all"
                  :style="{
                    width: `${(endpoint.count / maxEndpointCount) * 100}%`,
                  }"
                ></div>
              </div>
              <span class="w-16 text-right font-mono text-sm font-medium">
                {{ endpoint.count.toLocaleString() }}
              </span>
            </div>
          </div>
        </div>
        <div v-else class="text-muted-foreground py-8 text-center text-sm tracking-tight">
          No endpoint data available
        </div>
      </div>

      <!-- Consumer Info -->
      <div v-if="consumer" class="border-border rounded-lg border p-4">
        <h2 class="mb-4 text-lg font-semibold tracking-tighter">Consumer Details</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div>
            <div class="text-muted-foreground text-xs font-medium">Name</div>
            <div class="mt-1 font-medium tracking-tight">{{ consumer.name }}</div>
          </div>
          <div>
            <div class="text-muted-foreground text-xs font-medium">Website</div>
            <a
              :href="consumer.website_url"
              target="_blank"
              class="text-primary mt-1 block truncate tracking-tight hover:underline"
            >
              {{ consumer.website_url }}
            </a>
          </div>
          <div>
            <div class="text-muted-foreground text-xs font-medium">Rate Limit</div>
            <div class="mt-1 font-medium tracking-tight">
              {{ consumer.rate_limit === 0 ? "Unlimited" : `${consumer.rate_limit}/min` }}
            </div>
          </div>
          <div>
            <div class="text-muted-foreground text-xs font-medium">Status</div>
            <div class="mt-1">
              <span
                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                :class="
                  consumer.is_active
                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                    : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                "
              >
                {{ consumer.is_active ? "Active" : "Inactive" }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import DateRangeSelect from "@/components/analytics/DateRangeSelect.vue";

definePageMeta({
  middleware: ["sanctum:auth", "role"],
  roles: ["admin", "master"],
  layout: "app",
});

const route = useRoute();
const consumerId = computed(() => route.params.id);

const selectedPeriod = ref("7");

// Convert period string to days number
const periodDays = computed(() => {
  const period = selectedPeriod.value;
  if (period === "today") return 1;
  if (period === "yesterday") return 2;
  if (period === "this_week" || period === "last_week") return 7;
  if (period === "this_month" || period === "last_month") return 30;
  if (period === "this_year") return 365;
  return parseInt(period) || 7;
});

// Fetch analytics with lazy loading
const {
  data: analyticsResponse,
  pending: loading,
  error: analyticsError,
  refresh: loadAnalytics,
} = await useLazySanctumFetch(
  () => `/api/api-consumers/${consumerId.value}/analytics?days=${periodDays.value}`,
  {
    key: `api-consumer-analytics-${consumerId.value}-${selectedPeriod.value}`,
    watch: [selectedPeriod],
  }
);

const analyticsData = computed(() => analyticsResponse.value?.data || null);
const consumer = computed(() => analyticsData.value?.consumer || null);

const error = computed(() => {
  if (analyticsError.value)
    return analyticsError.value.response?._data?.message || "Failed to load analytics";
  return null;
});

// Chart data for ChartLine component
const chartData = computed(() => {
  if (!analyticsData.value?.requests_per_day || !Array.isArray(analyticsData.value.requests_per_day)) {
    return [];
  }

  return analyticsData.value.requests_per_day
    .map((item) => ({
      date: new Date(item.date),
      count: item.count || 0,
    }))
    .sort((a, b) => a.date - b.date);
});

// Chart config for ChartLine component
const chartConfig = computed(() => {
  return {
    count: {
      label: "Requests",
      color: "var(--chart-1)",
    },
  };
});

// Status distribution total
const totalStatusRequests = computed(() => {
  if (!analyticsData.value?.status_distribution) return 0;
  const dist = analyticsData.value.status_distribution;
  return dist["2xx"] + dist["3xx"] + dist["4xx"] + dist["5xx"];
});

// Hourly distribution helpers
const maxHourlyCount = computed(() => {
  if (!analyticsData.value?.hourly_distribution?.length) return 0;
  return Math.max(...analyticsData.value.hourly_distribution.map((h) => h.count));
});

const getHourlyCount = (hour) => {
  if (!analyticsData.value?.hourly_distribution) return 0;
  const found = analyticsData.value.hourly_distribution.find((h) => h.hour === hour);
  return found?.count || 0;
};

const getHourlyPercentage = (hour) => {
  const count = getHourlyCount(hour);
  if (maxHourlyCount.value === 0) return 10;
  return Math.max((count / maxHourlyCount.value) * 100, 10);
};

// Top endpoints helper
const maxEndpointCount = computed(() => {
  if (!analyticsData.value?.top_endpoints?.length) return 1;
  return Math.max(...analyticsData.value.top_endpoints.map((e) => e.count));
});

// Method color classes
const getMethodClass = (method) => {
  const classes = {
    GET: "bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200",
    POST: "bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200",
    PUT: "bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200",
    PATCH: "bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200",
    DELETE: "bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200",
  };
  return classes[method] || "bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200";
};

// Watch for period changes and refresh data
watch(selectedPeriod, () => {
  loadAnalytics();
});

usePageMeta("api-consumers", {
  title: consumer.value?.name ? `Analytics - ${consumer.value.name}` : "API Consumer Analytics",
  description: "Usage analytics for API consumer",
});
</script>
