<template>
  <div class="mx-auto max-w-4xl space-y-6 pt-4 pb-16">
    <div class="flex flex-col items-start gap-y-6">
      <BackButton destination="/short-links" />

      <div class="flex w-full flex-wrap items-center justify-between gap-4">
        <div class="flex flex-col gap-y-1">
          <h1 class="page-title">Analytics - {{ shortLink?.slug }}</h1>
          <a
            v-if="shortLink"
            :href="shortLink.destination_url"
            target="_blank"
            class="text-muted-foreground hover:text-primary max-w-md truncate text-sm transition-colors"
          >
            {{ shortLink.destination_url }}
          </a>
        </div>

        <select
          v-model="selectedPeriod"
          class="border-border bg-background focus:ring-primary rounded-md border px-3 py-2 text-sm tracking-tight focus:ring-2 focus:outline-none"
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

    <div v-else-if="error" class="py-12 text-center">
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
        <h2 class="mb-4 text-lg font-semibold tracking-tighter">Clicks Over Time</h2>
        <div v-if="analyticsData.clicks_per_day?.length" class="space-y-2">
          <div
            v-for="day in analyticsData.clicks_per_day"
            :key="day.date"
            class="flex items-center gap-3"
          >
            <div class="text-muted-foreground w-24 text-sm">
              {{ $dayjs(day.date).format("MMM D") }}
            </div>
            <div class="flex-1">
              <div class="bg-muted relative h-6 rounded">
                <div
                  class="bg-primary absolute inset-y-0 left-0 rounded"
                  :style="{
                    width: `${(day.count / maxClicksPerDay) * 100}%`,
                  }"
                ></div>
              </div>
            </div>
            <div class="text-primary w-12 text-right text-sm font-medium">
              {{ day.count }}
            </div>
          </div>
        </div>
        <div v-else class="text-muted-foreground py-8 text-center tracking-tight">
          No click data available for this period
        </div>
      </div>
    </div>

    <NuxtLink
      v-if="shortLink?.og_image && shortLink?.og_title"
      :to="`${useRuntimeConfig().public.siteUrl}/${shortLink.slug}`"
      target="_blank"
      class="frame mt-10 flex w-full max-w-sm flex-col"
    >
      <div class="bg-muted aspect-[1200/630] shrink-0 overflow-hidden rounded-lg">
        <img
          v-if="shortLink?.og_image"
          :src="shortLink?.og_image"
          :alt="shortLink?.og_title"
          class="size-full object-cover"
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
  return Math.max(...analyticsData.value.clicks_per_day.map((d) => d.count));
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
