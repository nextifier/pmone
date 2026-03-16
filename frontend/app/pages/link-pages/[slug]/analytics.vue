<template>
  <div class="mx-auto max-w-6xl space-y-6 pt-4 pb-16">
    <div class="flex flex-col gap-y-6">
      <div class="flex items-center justify-between gap-2">
        <BackButton :destination="`/link-pages/${slug}`" :forceDestination="true" />
        <DialogViewRaw :data="analytics" />
      </div>

      <div class="flex w-full flex-wrap items-center justify-between gap-4">
        <div class="flex flex-col gap-y-1">
          <h1 class="page-title">Link Page Analytics</h1>
          <p
            v-if="linkPage"
            class="text-muted-foreground max-w-2xl truncate text-sm tracking-tight transition-colors"
          >
            {{ linkPage.title }}
          </p>
        </div>

        <DateRangeSelect v-model="selectedPeriod" />
      </div>
    </div>

    <LoadingState v-if="loading" label="Loading analytics.." />

    <div v-else-if="error" class="py-12 text-center">
      <p class="text-destructive">{{ error }}</p>
    </div>

    <div v-else-if="analytics" class="space-y-6">
      <!-- QR Code Section -->
      <Card v-if="linkPage">
        <CardHeader>
          <CardTitle>QR Code</CardTitle>
          <CardDescription>Share your link page with a QR code</CardDescription>
        </CardHeader>
        <CardContent class="flex flex-col items-center gap-6">
          <!-- Link Page URL with Copy Button -->
          <div class="w-full max-w-md">
            <div class="flex items-center gap-2">
              <Input :model-value="publicUrl" readonly class="flex-1 font-mono text-sm" />
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
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="border-border rounded-lg border p-6">
          <div class="text-muted-foreground text-sm font-medium">Total Visits</div>
          <div class="text-primary mt-2 text-4xl font-semibold">
            {{ analytics.summary.total_visits?.toLocaleString() }}
          </div>
        </div>

        <div class="border-border rounded-lg border p-6">
          <div class="text-muted-foreground text-sm font-medium">Authenticated</div>
          <div class="text-primary mt-2 text-4xl font-semibold">
            {{ analytics.summary.authenticated_visits?.toLocaleString() }}
          </div>
        </div>

        <div class="border-border rounded-lg border p-6">
          <div class="text-muted-foreground text-sm font-medium">Anonymous</div>
          <div class="text-primary mt-2 text-4xl font-semibold">
            {{ analytics.summary.anonymous_visits?.toLocaleString() }}
          </div>
        </div>

        <div class="border-border rounded-lg border p-6">
          <div class="text-muted-foreground text-sm font-medium">Total Items</div>
          <div class="text-primary mt-2 text-4xl font-semibold">
            {{ analytics.summary.total_items?.toLocaleString() }}
          </div>
        </div>
      </div>

      <!-- Visits Over Time Chart -->
      <div class="border-border rounded-lg border p-4">
        <h2 class="mb-4 text-lg font-semibold tracking-tighter">Visits Over Time</h2>
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
          No visit data available for this period
        </div>
      </div>

      <!-- Top Visitors -->
      <div class="border-border rounded-lg border p-4">
        <h2 class="mb-4 text-lg font-semibold tracking-tighter">Top Visitors</h2>
        <div v-if="analytics.top_visitors?.length" class="space-y-2">
          <div
            v-for="(visitorData, index) in analytics.top_visitors"
            :key="index"
            class="hover:bg-muted flex items-center gap-3 rounded-lg p-2 transition-colors"
          >
            <div class="flex flex-1 items-center gap-3">
              <Avatar v-if="visitorData.visitor" :model="visitorData.visitor" class="size-10" />
              <div
                v-else
                class="bg-muted flex size-10 shrink-0 items-center justify-center rounded-full"
              >
                <Icon name="lucide:user" class="text-muted-foreground size-5" />
              </div>

              <div class="min-w-0 flex-1">
                <div v-if="visitorData.visitor" class="text-primary truncate text-sm font-medium">
                  {{ visitorData.visitor.name }}
                </div>
                <div v-else class="text-muted-foreground truncate text-sm italic">Anonymous</div>
                <div
                  v-if="visitorData.visitor?.username"
                  class="text-muted-foreground truncate text-xs"
                >
                  @{{ visitorData.visitor.username }}
                </div>
              </div>
            </div>

            <div class="text-muted-foreground shrink-0 text-sm">
              {{ visitorData.visit_count }} visits
            </div>
          </div>
        </div>
        <div v-else class="text-muted-foreground py-8 text-center tracking-tight">
          No authenticated visitors yet
        </div>
      </div>

      <!-- Clicks by Item -->
      <div class="border-border rounded-lg border p-4">
        <h2 class="mb-4 text-lg font-semibold tracking-tighter">Clicks by Item</h2>
        <div v-if="analytics.item_clicks?.length" class="space-y-2">
          <div
            v-for="item in analytics.item_clicks"
            :key="item.id"
            class="hover:bg-muted flex items-center gap-3 rounded-lg p-2 transition-colors"
          >
            <div v-if="item.poster" class="w-16 shrink-0">
              <img
                :src="item.poster.sm || item.poster.url"
                :alt="item.label"
                class="w-full rounded-md"
              />
            </div>

            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-medium tracking-tight">{{ item.label }}</p>
              <p class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm">
                {{ item.url }}
              </p>
            </div>
            <div class="text-muted-foreground shrink-0 text-sm tracking-tight tabular-nums">
              {{ item.clicks_count?.toLocaleString() }} clicks
            </div>
          </div>
        </div>
        <div v-else class="text-muted-foreground py-8 text-center tracking-tight">
          No click data available
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import DateRangeSelect from "@/components/analytics/DateRangeSelect.vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["link_pages.read"],
  layout: "app",
});

const route = useRoute();
const slug = computed(() => route.params.slug);
const config = useRuntimeConfig();

const client = useSanctumClient();
const loading = ref(true);
const error = ref(null);
const linkPage = ref(null);
const analytics = ref(null);
const selectedPeriod = ref("7");

usePageMeta(null, {
  title: computed(() => (linkPage.value ? `Analytics · ${linkPage.value.title}` : "Analytics")),
});

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

const publicUrl = computed(() => {
  if (!linkPage.value) return "";
  return `${config.public.siteUrl}/${linkPage.value.slug}`;
});

// Chart data for ChartLine component
const chartData = computed(() => {
  if (!analytics.value?.visits_per_day || !Array.isArray(analytics.value.visits_per_day)) {
    return [];
  }

  return analytics.value.visits_per_day
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
      label: "Visits",
      color: "var(--chart-1)",
    },
  };
});

const fetchData = async () => {
  loading.value = true;
  error.value = null;

  try {
    const [linkPageRes, analyticsRes] = await Promise.all([
      client(`/api/link-pages/${slug.value}`),
      client(`/api/link-pages/${slug.value}/analytics?period=${selectedPeriod.value}`),
    ]);

    linkPage.value = linkPageRes.data;
    analytics.value = analyticsRes.data;
  } catch (err) {
    console.error("Failed to fetch analytics:", err);
    error.value = err.response?._data?.message || "Failed to load analytics";
    toast.error("Failed to load analytics");
  } finally {
    loading.value = false;
  }
};

onMounted(fetchData);
watch(selectedPeriod, fetchData);

// Generate QR code whenever link page is available
watch(
  [() => linkPage.value, QRCode],
  async ([link, qrLib]) => {
    if (!link || !qrLib) {
      qrDataUrl.value = "";
      return;
    }

    try {
      qrDataUrl.value = await qrLib.toDataURL(publicUrl.value, {
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
  { immediate: true },
);

// Copy to clipboard
const copyToClipboard = async () => {
  try {
    await navigator.clipboard.writeText(publicUrl.value);
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
  if (!publicUrl.value || !QRCode.value) return;

  try {
    const dataUrl = await QRCode.value.toDataURL(publicUrl.value, {
      width: downloadSize,
      margin: qrMargin,
      errorCorrectionLevel: errorCorrectionLevel,
      color: {
        dark: "#000000",
        light: "#FFFFFF",
      },
    });

    const link = document.createElement("a");
    link.download = `QR-${linkPage.value.slug}.png`;
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
  if (!publicUrl.value || !QRCode.value) return;

  try {
    const svgString = await QRCode.value.toString(publicUrl.value, {
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
    link.download = `QR-${linkPage.value.slug}.svg`;
    link.href = url;
    link.click();
    URL.revokeObjectURL(url);
    toast.success("QR code downloaded!");
  } catch (err) {
    toast.error("Failed to download QR code");
    console.error("Error generating SVG:", err);
  }
};
</script>
