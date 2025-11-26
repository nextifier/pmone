<template>
  <div class="min-h-screen-offset mx-auto flex max-w-6xl flex-col gap-y-4 py-4">
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
        <AnalyticsExportDropdown
          :start-date="startDate"
          :end-date="endDate"
          :disabled="loading"
          :filename-prefix="`property_${route.params.id}_analytics`"
          :on-excel-export="exportToExcel"
        />

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

        <div v-if="propertyChartData?.length >= 2" class="frame">
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

          <ChartLine
            :data="propertyChartData"
            :config="propertyChartConfig"
            :gradient="true"
            :data-key="selectedMetric"
            class="h-auto! overflow-hidden rounded-xl border py-2.5"
          />
        </div>

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

      <AnalyticsTopPagesList
        v-if="propertyData.top_pages?.length > 0"
        :pages="propertyData.top_pages"
        :limit="10"
      />

      <AnalyticsTrafficSourcesList
        v-if="propertyData.traffic_sources?.length > 0"
        :sources="propertyData.traffic_sources"
        :limit="12"
      />

      <AnalyticsDevicesList
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

          <DialogViewRaw :data="propertyData" />
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
import AnalyticsDevicesList from "@/components/analytics/DevicesList.vue";
import AnalyticsTopPagesList from "@/components/analytics/TopPagesList.vue";
import AnalyticsTrafficSourcesList from "@/components/analytics/TrafficSourcesList.vue";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { toast } from "vue-sonner";

const { $dayjs } = useNuxtApp();
const route = useRoute();
const sanctumFetch = useSanctumClient();

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

const getInitialMetric = () => {
  if (typeof window !== "undefined") {
    return localStorage.getItem("analytics_detail_metric") || "activeUsers";
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

// Watch selectedMetric and save to localStorage
watch(selectedMetric, (newValue) => {
  if (typeof window !== "undefined") {
    localStorage.setItem("analytics_detail_metric", newValue);
  }
});

// Realtime data
const { realtimeData, startAutoRefresh } = useRealtimeAnalytics();

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

// Build query params
const buildQueryParams = () => {
  const startDateStr = startDate.value.format("YYYY-MM-DD");
  const endDateStr = endDate.value.format("YYYY-MM-DD");
  return `start_date=${startDateStr}&end_date=${endDateStr}`;
};

// Fetch property analytics using lazy loading
const {
  data: propertyResponse,
  pending: loading,
  error: fetchError,
  refresh: fetchPropertyAnalytics,
} = await useLazySanctumFetch(
  () => `/api/google-analytics/properties/${route.params.id}/analytics?${buildQueryParams()}`,
  {
    key: `property-analytics-${route.params.id}`,
    watch: [selectedRange],
  }
);

const propertyData = computed(() => propertyResponse.value?.data || null);
const error = computed(() => {
  if (!fetchError.value) return null;
  const err = fetchError.value;
  if (err.status === 429 || err.statusCode === 429) {
    return "Too many requests. Please wait a moment and try again.";
  }
  return err.data?.message || err.message || "Failed to load property analytics";
});

// Watch for changes and save to localStorage
watch(selectedRange, (newValue) => {
  if (typeof window !== "undefined") {
    localStorage.setItem("analytics_selected_range", newValue);
  }
});

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

// Chart data for ChartLineDefault - All metrics
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
        totalUsers: item.totalUsers || 0,
        newUsers: item.newUsers || 0,
        sessions: item.sessions || 0,
        screenPageViews: item.screenPageViews || 0,
      };
    })
    .filter((item) => !isNaN(item.date.getTime()))
    .sort((a, b) => a.date - b.date);
});

const propertyChartConfig = computed(() => {
  const metricConfig = metricOptions.find((m) => m.value === selectedMetric.value);
  return {
    [selectedMetric.value]: {
      label: metricConfig?.label || "Metric",
      color: "var(--chart-1)",
    },
  };
});

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

// Export analytics
const isExportingExcel = ref(false);

const exportToExcel = async () => {
  if (isExportingExcel.value) return;

  isExportingExcel.value = true;

  try {
    const propertyId = route.params.id;
    const startDateStr = startDate.value.format("YYYY-MM-DD");
    const endDateStr = endDate.value.format("YYYY-MM-DD");

    // Create download link
    const url = `/api/google-analytics/properties/${propertyId}/analytics/export?start_date=${startDateStr}&end_date=${endDateStr}`;

    // Use sanctumFetch to download the file
    const response = await sanctumFetch(url, {
      method: "GET",
    });

    // Create blob from response
    const blob = new Blob([response], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });

    // Create download link
    const downloadUrl = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = downloadUrl;
    link.download = `property_analytics_${propertyId}_${startDateStr}_to_${endDateStr}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(downloadUrl);

    toast.success("Analytics exported to Excel successfully");
  } catch (error) {
    console.error("Error exporting analytics:", error);
    toast.error("Failed to export analytics", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    isExportingExcel.value = false;
  }
};

// Lifecycle
onMounted(() => {
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

// Watch propertyData to start realtime refresh when data is loaded
watch(
  propertyData,
  (newData) => {
    if (newData?.property?.property_id) {
      const propertyId = String(newData.property.property_id);
      try {
        startAutoRefresh([propertyId]);
      } catch (err) {
        console.error("Error starting realtime refresh:", err);
        // Continue without realtime data
      }
    }
  },
  { immediate: true }
);
</script>
