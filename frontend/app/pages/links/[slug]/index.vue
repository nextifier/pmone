<template>
  <div class="mx-auto max-w-6xl space-y-6 pt-4 pb-16">
    <div class="flex flex-col gap-y-6">
      <div class="flex items-center justify-between gap-2">
        <BackButton destination="/links" :forceDestination="true" />
        <DialogViewRaw :data="analyticsData" />
      </div>

      <div class="flex w-full flex-wrap items-center justify-between gap-4">
        <div class="flex flex-col gap-y-1">
          <h1 class="page-title">Short Link Analytics</h1>
          <a
            v-if="shortLink"
            :href="shortLink.destination_url"
            target="_blank"
            class="text-muted-foreground hover:text-primary max-w-2xl truncate text-sm transition-colors"
          >
            {{ shortLink.destination_url }}
          </a>
        </div>

        <DateRangeSelect v-model="selectedPeriod" />
      </div>
    </div>

    <LoadingState v-if="loading" label="Loading analytics.." />

    <div v-else-if="error" class="py-12 text-center">
      <p class="text-destructive">{{ error }}</p>
    </div>

    <div v-else-if="analyticsData" class="space-y-6">
      <!-- QR Code Section -->
      <Card v-if="shortLink">
        <CardHeader>
          <CardTitle>QR Code</CardTitle>
          <CardDescription>Share your short link with a QR code</CardDescription>
        </CardHeader>
        <CardContent class="flex flex-col items-center gap-6">
          <!-- Short Link URL with Copy Button -->
          <div class="w-full max-w-md">
            <div class="flex items-center gap-2">
              <Input :model-value="shortLinkUrl" readonly class="flex-1 font-mono text-sm" />
              <Button @click="copyToClipboard" variant="outline" size="icon">
                <Icon :name="copied ? 'lucide:check' : 'lucide:copy'" class="size-4" />
              </Button>
            </div>
          </div>

          <!-- QR Code Preview -->
          <div class="flex items-center justify-center">
            <div
              v-if="qrDataUrl"
              class="xs:max-w-[280px] aspect-square w-full overflow-hidden rounded-lg bg-white shadow-lg"
            >
              <img :src="qrDataUrl" alt="QR Code" class="size-full object-contain" />
            </div>
          </div>

          <!-- Download Buttons -->
          <div class="flex flex-wrap justify-center gap-x-2 gap-y-4">
            <button
              @click="downloadJPG"
              class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center justify-center gap-x-1.5 rounded-lg px-4 py-2 font-medium tracking-tight transition active:scale-98"
            >
              <Icon name="hugeicons:jpg-01" class="size-5 shrink-0" />
              <span>Download JPG</span>
            </button>
            <button
              @click="downloadSVG"
              class="bg-muted text-foreground hover:bg-border flex items-center justify-center gap-x-1.5 rounded-lg px-4 py-2 font-medium tracking-tight transition active:scale-98"
            >
              <Icon name="hugeicons:svg-01" class="size-5 shrink-0" />
              <span>Download SVG</span>
            </button>
          </div>
        </CardContent>
      </Card>

      <!-- Summary Cards -->
      <div class="grid gap-4 sm:grid-cols-3">
        <div class="border-border rounded-lg border p-6">
          <div class="text-muted-foreground text-sm font-medium">Total Clicks</div>
          <div class="text-primary mt-2 text-4xl font-semibold">
            {{ analyticsData.summary.total_clicks.toLocaleString() }}
          </div>
        </div>

        <div class="border-border rounded-lg border p-6">
          <div class="text-muted-foreground text-sm font-medium">Authenticated</div>
          <div class="text-primary mt-2 text-4xl font-semibold">
            {{ analyticsData.summary.authenticated_clicks.toLocaleString() }}
          </div>
        </div>

        <div class="border-border rounded-lg border p-6">
          <div class="text-muted-foreground text-sm font-medium">Anonymous</div>
          <div class="text-primary mt-2 text-4xl font-semibold">
            {{ analyticsData.summary.anonymous_clicks.toLocaleString() }}
          </div>
        </div>
      </div>

      <!-- Clicks Over Time Chart -->
      <div class="border-border rounded-lg border p-4">
        <h2 class="mb-4 text-lg font-semibold tracking-tighter">Clicks Over Time</h2>
        <div v-if="chartData?.length > 2">
          <ChartLine
            :data="chartData"
            :config="chartConfig"
            :gradient="true"
            data-key="count"
            class="h-auto! overflow-hidden py-2.5"
          />
        </div>
        <div v-else class="text-muted-foreground py-8 text-center tracking-tight">
          No click data available for this period
        </div>
      </div>

      <!-- Top Clickers -->
      <div class="border-border rounded-lg border p-4">
        <h2 class="mb-4 text-lg font-semibold tracking-tighter">Top Clickers</h2>
        <div v-if="analyticsData.top_clickers?.length" class="space-y-2">
          <div
            v-for="(clickerData, index) in analyticsData.top_clickers"
            :key="index"
            class="hover:bg-muted flex items-center gap-3 rounded-lg p-2 transition-colors"
          >
            <div class="flex flex-1 items-center gap-3">
              <Avatar v-if="clickerData.clicker" :model="clickerData.clicker" class="size-10" />
              <div
                v-else
                class="bg-muted flex size-10 shrink-0 items-center justify-center rounded-full"
              >
                <Icon name="lucide:user" class="text-muted-foreground size-5" />
              </div>

              <div class="min-w-0 flex-1">
                <div v-if="clickerData.clicker" class="text-primary truncate text-sm font-medium">
                  {{ clickerData.clicker.name }}
                </div>
                <div v-else class="text-muted-foreground truncate text-sm italic">Anonymous</div>
                <div
                  v-if="clickerData.clicker?.username"
                  class="text-muted-foreground truncate text-xs"
                >
                  @{{ clickerData.clicker.username }}
                </div>
              </div>
            </div>

            <div class="text-muted-foreground shrink-0 text-sm">
              {{ clickerData.click_count }} clicks
            </div>
          </div>
        </div>
        <div v-else class="text-muted-foreground py-8 text-center tracking-tight">
          No authenticated clickers yet
        </div>
      </div>

      <!-- Top Referrers -->
      <!-- <div class="border-border rounded-lg border p-4">
        <h2 class="mb-4 text-lg font-semibold tracking-tighter">Top Referrers</h2>
        <div v-if="analyticsData.top_referrers?.length" class="space-y-2">
          <div
            v-for="(referrer, index) in analyticsData.top_referrers"
            :key="index"
            class="hover:bg-muted flex items-center gap-3 rounded-lg p-2 transition-colors"
          >
            <div class="flex flex-1 items-center gap-3">
              <div class="bg-muted flex size-10 shrink-0 items-center justify-center rounded-full">
                <Icon name="lucide:link" class="text-muted-foreground size-5" />
              </div>

              <div class="min-w-0 flex-1">
                <a
                  :href="referrer.referer"
                  target="_blank"
                  class="text-primary block truncate text-sm font-medium hover:underline"
                >
                  {{ referrer.referer }}
                </a>
              </div>
            </div>

            <div class="text-muted-foreground shrink-0 text-sm">{{ referrer.count }} clicks</div>
          </div>
        </div>
        <div v-else class="text-muted-foreground py-8 text-center tracking-tight">
          No referrer data available
        </div>
      </div> -->

      <!-- OG Preview Card -->
      <ClientOnly>
        <NuxtLink
          v-if="shortLink?.og_image && shortLink?.og_title"
          :to="`${useRuntimeConfig().public.siteUrl}/${shortLink.slug}`"
          target="_blank"
          class="frame flex w-full max-w-sm flex-col"
        >
          <div class="bg-muted aspect-1200/630 shrink-0 overflow-hidden rounded-lg">
            <img
              :src="shortLink?.og_image"
              :alt="shortLink?.og_title"
              class="size-full object-cover"
              @error="$event.target.closest('.frame').style.display = 'none'"
            />
          </div>

          <div class="bg-background flex flex-col p-4">
            <h6
              v-if="shortLink?.og_title"
              class="text-foreground text-base font-semibold tracking-tighter"
            >
              {{ shortLink?.og_title }}
            </h6>
            <p
              v-if="shortLink?.og_description"
              class="text-muted-foreground mt-1 text-xs tracking-tight"
            >
              {{ shortLink?.og_description }}
            </p>
          </div>
        </NuxtLink>
      </ClientOnly>
    </div>
  </div>
</template>

<script setup>
import DateRangeSelect from "@/components/analytics/DateRangeSelect.vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const route = useRoute();
const slug = computed(() => route.params.slug);

const { $dayjs } = useNuxtApp();
const config = useRuntimeConfig();

// QR Code - dynamic import for client-side only
const QRCode = ref(null);

// QR Code state
const qrDataUrl = ref("");
const copied = ref(false);
const previewSize = 300;
const downloadSize = 1080;
const errorCorrectionLevel = "H";
const qrMargin = 2;

// Load QRCode library on client-side
onMounted(async () => {
  const qrcodeModule = await import("qrcode");
  QRCode.value = qrcodeModule.default;
});

const selectedPeriod = ref("7");

// Fetch short link details with lazy loading
const { data: shortLinkResponse, error: shortLinkError } = await useLazySanctumFetch(
  () => `/api/short-links/${slug.value}`,
  {
    key: `short-link-${slug.value}`,
  }
);

const shortLink = computed(() => shortLinkResponse.value?.data || null);

// Fetch analytics with lazy loading
const {
  data: analyticsResponse,
  pending: loading,
  error: analyticsError,
  refresh: loadAnalytics,
} = await useLazySanctumFetch(
  () => `/api/short-links/${slug.value}/analytics?period=${selectedPeriod.value}`,
  {
    key: `short-link-analytics-${slug.value}-${selectedPeriod.value}`,
    watch: [selectedPeriod],
  }
);

const analyticsData = computed(() => analyticsResponse.value?.data || null);

const error = computed(() => {
  if (shortLinkError.value) return "Failed to load short link";
  if (analyticsError.value)
    return analyticsError.value.response?._data?.message || "Failed to load analytics";
  return null;
});

// Short link URL
const shortLinkUrl = computed(() => {
  if (!shortLink.value) return "";
  return `${config.public.siteUrl}/${shortLink.value.slug}`;
});

// Generate QR code whenever short link is available
watch(
  [shortLink, QRCode],
  async ([link, qrLib]) => {
    if (!link || !qrLib) {
      qrDataUrl.value = "";
      return;
    }

    try {
      qrDataUrl.value = await qrLib.toDataURL(shortLinkUrl.value, {
        width: previewSize,
        margin: qrMargin,
        errorCorrectionLevel: errorCorrectionLevel,
        color: {
          dark: "#000000",
          light: "#FFFFFF",
        },
      });
    } catch (err) {
      console.error("Error generating QR code:", err);
      qrDataUrl.value = "";
    }
  },
  { immediate: true }
);

// Copy to clipboard
const copyToClipboard = async () => {
  try {
    await navigator.clipboard.writeText(shortLinkUrl.value);
    copied.value = true;
    toast.success("Link copied to clipboard!");
    setTimeout(() => {
      copied.value = false;
    }, 2000);
  } catch (err) {
    toast.error("Failed to copy link");
    console.error("Error copying to clipboard:", err);
  }
};

// Download as JPG
const downloadJPG = async () => {
  if (!shortLinkUrl.value || !QRCode.value) return;

  try {
    const dataUrl = await QRCode.value.toDataURL(shortLinkUrl.value, {
      width: downloadSize,
      margin: qrMargin,
      errorCorrectionLevel: errorCorrectionLevel,
      color: {
        dark: "#000000",
        light: "#FFFFFF",
      },
    });

    const link = document.createElement("a");
    link.download = `QR-${shortLink.value.slug}.png`;
    link.href = dataUrl;
    link.click();
    toast.success("QR code downloaded!");
  } catch (err) {
    toast.error("Failed to download QR code");
    console.error("Error generating QR code for download:", err);
  }
};

// Download as SVG
const downloadSVG = async () => {
  if (!shortLinkUrl.value || !QRCode.value) return;

  try {
    const svgString = await QRCode.value.toString(shortLinkUrl.value, {
      type: "svg",
      width: 512,
      margin: qrMargin,
      errorCorrectionLevel: errorCorrectionLevel,
      color: {
        dark: "#000000",
        light: "#FFFFFF",
      },
    });

    const blob = new Blob([svgString], { type: "image/svg+xml" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.download = `QR-${shortLink.value.slug}.svg`;
    link.href = url;
    link.click();
    URL.revokeObjectURL(url);
    toast.success("QR code downloaded!");
  } catch (err) {
    toast.error("Failed to download QR code");
    console.error("Error generating SVG:", err);
  }
};

// Chart data for ChartLineDefault component
const chartData = computed(() => {
  if (!analyticsData.value?.clicks_per_day || !Array.isArray(analyticsData.value.clicks_per_day)) {
    return [];
  }

  return analyticsData.value.clicks_per_day
    .map((item) => ({
      date: new Date(item.date),
      count: item.count || 0,
    }))
    .sort((a, b) => a.date - b.date);
});

// Chart config for ChartLineDefault component
const chartConfig = computed(() => {
  return {
    count: {
      label: "Clicks",
      color: "var(--chart-1)",
    },
  };
});

usePageMeta("", {
  title: `Analytics - ${slug.value}`,
  description: `Analytics for short link ${slug.value}`,
});
</script>
