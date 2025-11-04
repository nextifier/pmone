<template>
  <div class="mx-auto max-w-4xl space-y-6">
    <div class="flex flex-col items-start gap-y-6">
      <BackButton destination="/short-links" />

      <div class="flex w-full flex-wrap items-center justify-between gap-4">
        <div class="flex flex-col gap-y-1">
          <h1 class="page-title">Analytics - {{ shortLink?.slug }}</h1>
          <a
            v-if="shortLink"
            :href="shortLink.destination_url"
            target="_blank"
            class="text-muted-foreground hover:text-primary text-sm transition-colors truncate max-w-md"
          >
            {{ shortLink.destination_url }}
          </a>
        </div>

        <select
          v-model="selectedPeriod"
          class="border-border bg-background focus:ring-primary rounded-md border px-3 py-2 text-sm tracking-tight focus:outline-none focus:ring-2"
        >
          <option :value="7">Last 7 days</option>
          <option :value="14">Last 14 days</option>
          <option :value="30">Last 30 days</option>
          <option :value="90">Last 90 days</option>
        </select>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <Spinner class="size-8" />
    </div>

    <div v-else-if="error" class="text-center py-12">
      <p class="text-destructive">{{ error }}</p>
    </div>

    <div v-else-if="analyticsData" class="space-y-6">
      <!-- Summary Card -->
      <div class="border-border rounded-lg border p-6">
        <div class="text-muted-foreground text-sm font-medium">Total Clicks</div>
        <div class="text-primary mt-2 text-4xl font-semibold">
          {{ analyticsData.summary.total_clicks.toLocaleString() }}
        </div>
      </div>

      <!-- Clicks Per Day Chart -->
      <div class="border-border rounded-lg border p-4">
        <h2 class="text-lg font-semibold mb-4">Clicks Over Time</h2>
        <div v-if="analyticsData.clicks_per_day?.length" class="space-y-2">
          <div
            v-for="day in analyticsData.clicks_per_day"
            :key="day.date"
            class="flex items-center gap-3"
          >
            <div class="text-muted-foreground w-24 text-sm">
              {{ $dayjs(day.date).format('MMM DD') }}
            </div>
            <div class="flex-1">
              <div class="bg-primary/20 relative h-6 rounded">
                <div
                  class="bg-primary absolute inset-y-0 left-0 rounded"
                  :style="{
                    width: `${(day.count / maxClicksPerDay) * 100}%`
                  }"
                ></div>
              </div>
            </div>
            <div class="text-primary w-12 text-right text-sm font-medium">
              {{ day.count }}
            </div>
          </div>
        </div>
        <div v-else class="text-muted-foreground py-8 text-center">
          No click data available for this period
        </div>
      </div>

      <!-- Recent Clicks -->
      <div class="border-border rounded-lg border p-4">
        <h2 class="text-lg font-semibold mb-4">Recent Clicks</h2>
        <div v-if="analyticsData.recent_clicks?.length" class="space-y-3">
          <div
            v-for="click in analyticsData.recent_clicks"
            :key="click.id"
            class="border-border flex flex-col gap-2 rounded-lg border p-3"
          >
            <div class="flex items-center justify-between">
              <div class="text-muted-foreground text-sm">
                {{ click.ip_address }}
              </div>
              <div class="text-muted-foreground text-xs">
                {{ $dayjs(click.clicked_at).fromNow() }}
              </div>
            </div>
            <div v-if="click.referer" class="text-muted-foreground text-xs truncate">
              Referer: {{ click.referer }}
            </div>
          </div>
        </div>
        <div v-else class="text-muted-foreground py-8 text-center">
          No clicks yet
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const route = useRoute();
const slug = computed(() => route.params.slug);

const sanctumFetch = useSanctumClient();
const { $dayjs } = useNuxtApp();

const selectedPeriod = ref(7);
const shortLink = ref(null);
const analyticsData = ref(null);
const loading = ref(true);
const error = ref(null);

// Computed max clicks per day for chart scaling
const maxClicksPerDay = computed(() => {
  if (!analyticsData.value?.clicks_per_day?.length) return 0;
  return Math.max(...analyticsData.value.clicks_per_day.map(d => d.count));
});

// Load short link details
async function loadShortLink() {
  try {
    const response = await sanctumFetch(`/api/short-links/${slug.value}`);
    shortLink.value = response.data;
  } catch (err) {
    console.error("Error loading short link:", err);
    error.value = "Failed to load short link";
  }
}

// Load analytics data
async function loadAnalytics() {
  loading.value = true;
  error.value = null;

  try {
    const response = await sanctumFetch(
      `/api/short-links/${slug.value}/analytics?days=${selectedPeriod.value}`
    );
    analyticsData.value = response.data;
  } catch (err) {
    console.error("Error loading analytics:", err);
    error.value = err.response?._data?.message || "Failed to load analytics";
    toast.error(error.value);
  } finally {
    loading.value = false;
  }
}

// Watch for period changes
watch(selectedPeriod, () => {
  loadAnalytics();
});

// Load data on mount
onMounted(async () => {
  await loadShortLink();
  await loadAnalytics();
});

usePageMeta("", {
  title: `Analytics - ${slug.value}`,
  description: `Analytics for short link ${slug.value}`,
});
</script>
