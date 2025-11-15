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

        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <button
              :disabled="isExporting || loading"
              class="border-border hover:bg-muted flex h-8 items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <Icon
                :name="isExporting ? 'hugeicons:loading-01' : 'hugeicons:file-export'"
                class="size-4 shrink-0"
                :class="{ 'animate-spin': isExporting }"
              />
              <span>{{ isExporting ? "Exporting..." : "Export" }}</span>
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem @click="exportToExcel">
              <Icon name="hugeicons:xls-01" class="size-4 shrink-0" />
              Export to Excel
            </DropdownMenuItem>
            <DropdownMenuItem @click="exportToPDF">
              <Icon name="hugeicons:pdf-01" class="size-4 shrink-0" />
              Export to PDF
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

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

          <ChartLineDefault
            :data="propertyChartData"
            :config="propertyChartConfig"
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
                  class="text-muted-foreground h-full w-full text-left text-xs leading-normal!"
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
import AnalyticsDevicesList from "@/components/analytics/DevicesList.vue";
import AnalyticsTopPagesList from "@/components/analytics/TopPagesList.vue";
import AnalyticsTrafficSourcesList from "@/components/analytics/TrafficSourcesList.vue";
import ChartLineDefault from "@/components/chart/LineDefault.vue";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
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

// State
const loading = ref(true); // Start with true to prevent empty state flash during SSR
const error = ref(null);
const propertyData = ref(null);
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

// Export analytics
const isExporting = ref(false);

const exportToExcel = async () => {
  if (isExporting.value) return;

  isExporting.value = true;

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
    isExporting.value = false;
  }
};

const exportToPDF = async () => {
  if (isExporting.value) return;

  isExporting.value = true;

  try {
    const propertyId = route.params.id;
    const startDateStr = startDate.value.format("YYYY-MM-DD");
    const endDateStr = endDate.value.format("YYYY-MM-DD");
    const propertyName = propertyData.value?.property?.name || "property";

    // Get the page content for PDF
    const element = document.querySelector(".min-h-screen-offset");
    if (!element) {
      throw new Error("Content element not found");
    }

    const clone = element.cloneNode(true);

    // Remove interactive elements and buttons from the clone
    const elementsToRemove = clone.querySelectorAll(
      "button, .border-border.hover\\:bg-muted, [data-slot='dropdown-menu']"
    );
    elementsToRemove.forEach((el) => el.remove());

    // Create a temporary container for the clone
    const container = document.createElement("div");
    container.style.position = "absolute";
    container.style.left = "-9999px";
    container.style.top = "0";
    container.style.width = "800px"; // Optimal width for A4 PDF
    container.style.padding = "20px";
    container.style.backgroundColor = "#ffffff";
    container.appendChild(clone);
    document.body.appendChild(container);

    // Convert profile images and other images to data URLs to avoid CORS issues
    const images = container.querySelectorAll("img");
    const imagePromises = Array.from(images).map(async (img) => {
      if (!img.src || img.src.startsWith("data:")) return;

      try {
        const response = await fetch(img.src, {
          credentials: "same-origin",
          mode: "cors",
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const blob = await response.blob();
        const dataUrl = await new Promise((resolve, reject) => {
          const reader = new FileReader();
          reader.onloadend = () => resolve(reader.result);
          reader.onerror = reject;
          reader.readAsDataURL(blob);
        });

        img.src = dataUrl;

        // Wait for image to load
        if (!img.complete) {
          await new Promise((resolve) => {
            img.onload = resolve;
            img.onerror = resolve;
            setTimeout(resolve, 500);
          });
        }
      } catch (err) {
        console.warn("Failed to convert image:", img.src, err);
        // Keep original image with crossOrigin
        img.crossOrigin = "anonymous";
      }
    });

    await Promise.race([
      Promise.all(imagePromises),
      new Promise((resolve) => setTimeout(resolve, 3000)),
    ]);

    // For SVG charts, we'll inline all computed styles to ensure they render correctly
    const svgElements = container.querySelectorAll("svg");
    svgElements.forEach((svg) => {
      try {
        const bbox = svg.getBoundingClientRect();
        // Only process large SVGs (likely charts)
        if (bbox.width < 100 || bbox.height < 100) {
          return;
        }

        // Inline all computed styles for the SVG and its children
        const elements = svg.querySelectorAll("*");
        elements.forEach((el) => {
          const computedStyle = window.getComputedStyle(el);
          const styleString = Array.from(computedStyle)
            .filter((prop) => {
              // Only include relevant style properties
              return (
                prop.startsWith("fill") ||
                prop.startsWith("stroke") ||
                prop.startsWith("font") ||
                prop === "opacity" ||
                prop === "color"
              );
            })
            .map((prop) => `${prop}:${computedStyle.getPropertyValue(prop)}`)
            .join(";");

          if (styleString) {
            const existingStyle = el.getAttribute("style") || "";
            el.setAttribute("style", existingStyle + ";" + styleString);
          }
        });

        // Ensure SVG has proper namespace
        if (!svg.getAttribute("xmlns")) {
          svg.setAttribute("xmlns", "http://www.w3.org/2000/svg");
        }
      } catch (err) {
        console.warn("Failed to process SVG:", err);
      }
    });

    // Wait for any dynamic content to render
    await new Promise((resolve) => setTimeout(resolve, 500));

    // Dynamic import for client-side only libraries
    const [{ default: html2canvas }, { jsPDF }] = await Promise.all([
      import("html2canvas-pro"),
      import("jspdf"),
    ]);

    // Convert element to canvas using html2canvas-pro (supports oklch)
    const canvas = await html2canvas(clone, {
      scale: 2,
      useCORS: false,
      allowTaint: true,
      logging: false,
      backgroundColor: "#ffffff",
      imageTimeout: 0,
      removeContainer: false,
      foreignObjectRendering: true,
    });

    // Clean up temporary container
    document.body.removeChild(container);

    // PDF configuration
    const pdf = new jsPDF("p", "mm", "a4");
    const pdfWidth = pdf.internal.pageSize.getWidth();
    const pdfHeight = pdf.internal.pageSize.getHeight();

    // Margins (in mm)
    const margin = 15;
    const contentWidth = pdfWidth - margin * 2;
    const contentHeight = pdfHeight - margin * 2;

    // Calculate image dimensions to fit within margins
    const imgWidth = contentWidth;
    const imgHeight = (canvas.height * imgWidth) / canvas.width;

    // Simple multi-page approach: split canvas into pages based on A4 height
    if (imgHeight <= contentHeight) {
      // Single page - content fits
      pdf.addImage(
        canvas.toDataURL("image/jpeg", 0.95),
        "JPEG",
        margin,
        margin,
        imgWidth,
        imgHeight
      );
    } else {
      // Multiple pages needed
      // Calculate how much canvas height fits in one PDF page (accounting for scale=2)
      const pageHeightInCanvas = (contentHeight * canvas.width) / imgWidth;
      let currentY = 0;
      let pageNumber = 0;

      while (currentY < canvas.height) {
        if (pageNumber > 0) {
          pdf.addPage();
        }

        // Calculate slice height for this page
        const remainingHeight = canvas.height - currentY;
        const sliceHeight = Math.min(pageHeightInCanvas, remainingHeight);

        // Create temporary canvas for this page
        const pageCanvas = document.createElement("canvas");
        pageCanvas.width = canvas.width;
        pageCanvas.height = sliceHeight;

        const ctx = pageCanvas.getContext("2d");
        if (ctx) {
          // Draw the slice from the main canvas
          ctx.drawImage(
            canvas,
            0,
            currentY,
            canvas.width,
            sliceHeight,
            0,
            0,
            canvas.width,
            sliceHeight
          );

          // Calculate PDF dimensions for this slice
          const pdfSliceHeight = (sliceHeight * imgWidth) / canvas.width;

          // Add to PDF
          pdf.addImage(
            pageCanvas.toDataURL("image/jpeg", 0.95),
            "JPEG",
            margin,
            margin,
            imgWidth,
            pdfSliceHeight
          );
        }

        currentY += sliceHeight;
        pageNumber++;
      }
    }

    // Download PDF
    pdf.save(`${propertyName}_analytics_${startDateStr}_to_${endDateStr}.pdf`);

    toast.success("Analytics exported to PDF successfully");
  } catch (error) {
    console.error("Error exporting to PDF:", error);
    toast.error("Failed to export to PDF", {
      description: error?.message || "An error occurred",
    });
  } finally {
    isExporting.value = false;
  }
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
