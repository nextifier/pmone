<template>
  <div class="mx-auto max-w-6xl space-y-6 pt-4 pb-16">
    <div class="flex flex-col gap-y-6">
      <div class="flex items-center justify-between gap-2">
        <BackButton destination="/short-links" />
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

    <div v-if="loading" class="flex justify-center py-12">
      <Spinner class="size-8" />
    </div>

    <div v-else-if="error" class="py-12 text-center">
      <p class="text-destructive">{{ error }}</p>
    </div>

    <div v-else-if="analyticsData" class="space-y-6">
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
          <ChartLineDefault
            :data="chartData"
            :config="chartConfig"
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
      <div class="border-border rounded-lg border p-4">
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
      </div>

      <!-- OG Preview Card -->
      <NuxtLink
        v-if="shortLink?.og_image && shortLink?.og_title"
        :to="`${useRuntimeConfig().public.siteUrl}/${shortLink.slug}`"
        target="_blank"
        class="frame flex w-full max-w-sm flex-col"
      >
        <div class="bg-muted aspect-1200/630 shrink-0 overflow-hidden rounded-lg">
          <img
            v-if="shortLink?.og_image"
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
    </div>
  </div>
</template>

<script setup>
import DateRangeSelect from "@/components/analytics/DateRangeSelect.vue";
import ChartLineDefault from "@/components/chart/LineDefault.vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const route = useRoute();
const slug = computed(() => route.params.slug);

const sanctumFetch = useSanctumClient();
const { $dayjs } = useNuxtApp();

const selectedPeriod = ref("7");
const shortLink = ref(null);
const analyticsData = ref(null);
const loading = ref(true);
const error = ref(null);

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
      `/api/short-links/${slug.value}/analytics?period=${selectedPeriod.value}`
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
