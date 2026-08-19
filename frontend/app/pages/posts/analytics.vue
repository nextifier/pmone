<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex flex-col gap-y-6">
      <div class="flex items-center justify-between gap-2">
        <ButtonBack destination="/posts" force-destination />
        <DialogViewRaw :data="analyticsData" />
      </div>

      <div class="flex w-full flex-wrap items-center justify-between gap-4">
        <div class="flex flex-col gap-y-1">
          <h1 class="page-title">Posts Analytics</h1>
          <p class="text-muted-foreground text-sm">Overall analytics for all published posts</p>
        </div>

        <DatePicker
          v-model="dateRange"
          mode="range"
          size="sm"
          align="end"
          disable-future-dates
          class="w-fit"
          :presets="analyticsRangePresets()"
        />
      </div>
    </div>

    <LoadingState v-if="loading" label="Loading analytics.." />

    <div v-else-if="error" class="py-12 text-center">
      <p class="text-destructive-foreground">{{ error }}</p>
    </div>

    <div v-else-if="analyticsData" class="space-y-6">
      <p v-if="showMethodologyNote" class="text-muted-foreground text-sm tracking-tight">
        Views before {{ $dayjs(meta.browser_counting_since).format("D MMM YYYY") }} come from
        Google Analytics, which measures a few percent lower than this site's own counter.
      </p>

      <!-- Summary Cards -->
      <div
        class="grid gap-4 sm:grid-cols-2"
        :class="showUniqueVisitors ? 'lg:grid-cols-4' : 'lg:grid-cols-3'"
      >
        <div class="border-border rounded-lg border p-6">
          <div class="text-muted-foreground text-sm font-medium">Views</div>
          <div class="text-foreground mt-2 text-4xl font-semibold">
            {{ analyticsData.summary.total_visits.toLocaleString() }}
          </div>
        </div>

        <div v-if="showUniqueVisitors" class="border-border rounded-lg border p-6">
          <div class="text-muted-foreground text-sm font-medium">Unique Visitors</div>
          <div class="text-foreground mt-2 text-4xl font-semibold">
            {{ analyticsData.summary.unique_visitors.toLocaleString() }}
          </div>
          <div class="text-muted-foreground mt-1 text-xs">Estimated from IP and browser</div>
        </div>

        <div class="border-border rounded-lg border p-6">
          <div class="text-muted-foreground text-sm font-medium">Published Posts</div>
          <div class="text-foreground mt-2 text-4xl font-semibold">
            {{ analyticsData.summary.total_posts.toLocaleString() }}
          </div>
        </div>

        <div class="border-border rounded-lg border p-6">
          <div class="text-muted-foreground text-sm font-medium">Avg. Views/Post</div>
          <div class="text-foreground mt-2 text-4xl font-semibold">
            {{
              analyticsData.summary.total_posts > 0
                ? Math.round(analyticsData.summary.total_visits / analyticsData.summary.total_posts)
                : 0
            }}
          </div>
        </div>
      </div>

      <!-- Views Over Time Chart -->
      <div class="border-border rounded-lg border p-4">
        <h2 class="mb-4 text-lg font-semibold tracking-tighter">Views Over Time</h2>
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
          No view data available for this period
        </div>
      </div>

      <!-- Top Posts -->
      <div class="border-border rounded-lg border p-4">
        <h2 class="mb-4 text-lg font-semibold tracking-tighter">Top Posts</h2>
        <div v-if="analyticsData.top_posts?.length" class="space-y-3">
          <NuxtLink
            v-for="post in analyticsData.top_posts"
            :key="post.id"
            :to="`/posts/${post.slug}/analytics`"
            class="hover:bg-muted group flex items-center gap-4 rounded-lg border p-3 transition-colors"
          >
            <div
              v-if="post.featured_image"
              class="bg-muted aspect-video w-24 shrink-0 overflow-hidden rounded"
            >
              <img
                :src="post.featured_image.url"
                :alt="post.title"
                class="size-full object-cover"
              />
            </div>
            <div
              v-else
              class="bg-muted flex aspect-video w-24 shrink-0 items-center justify-center rounded"
            >
              <Icon name="hugeicons:image-01" class="text-muted-foreground size-6" />
            </div>

            <div class="min-w-0 flex-1">
              <h3 class="text-foreground truncate font-medium tracking-tight">{{ post.title }}</h3>
              <p
                v-if="post.excerpt"
                class="text-muted-foreground mt-0.5 line-clamp-1 text-sm tracking-tight"
              >
                {{ post.excerpt }}
              </p>
              <div class="text-muted-foreground mt-1 flex items-center gap-2 text-xs">
                <span>{{ $dayjs(post.published_at).format("MMM D, YYYY") }}</span>
                <span>•</span>
                <span>{{ post.visits_count.toLocaleString() }} views</span>
                <template v-if="showUniqueVisitors">
                  <span>•</span>
                  <span>{{ (post.unique_visitors_count ?? 0).toLocaleString() }} unique</span>
                </template>
              </div>
            </div>

            <Icon
              name="lucide:chevron-right"
              class="text-muted-foreground group-hover:text-primary size-5 shrink-0 transition-colors"
            />
          </NuxtLink>
        </div>
        <div v-else class="text-muted-foreground py-8 text-center tracking-tight">
          No posts data available
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { DatePicker } from "@/components/ui/date-picker";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const { $dayjs } = useNuxtApp();

const dateRange = ref(lastNDaysRange(30)());

const rangeQuery = () =>
  `start_date=${toYmd(dateRange.value.start)}&end_date=${toYmd(dateRange.value.end)}`;

// Fetch analytics with lazy loading
const {
  data: analyticsResponse,
  pending: loading,
  error: analyticsError,
  refresh: loadAnalytics,
} = await useLazySanctumFetch(() => `/api/posts/analytics?${rangeQuery()}`, {
  key: `posts-analytics`,
});

const analyticsData = computed(() => analyticsResponse.value?.data || null);

// Dates come from the API (config/visit-tracking.php) rather than being hardcoded
// here, so the two repos cannot drift and the IP-forwarding date can be filled in
// on deploy day without a frontend release.
const meta = computed(() => analyticsData.value?.meta || {});

// The counter moved from server-side renders to a browser beacon partway through
// the data we hold. Say so whenever the selected range straddles that date,
// otherwise the drop reads as lost traffic.
const showMethodologyNote = computed(() => {
  const cutover = meta.value.browser_counting_since;
  return Boolean(cutover && toYmd(dateRange.value.start) < cutover);
});

// Unique visitors need a real visitor IP. Until the event websites forward one,
// every beacon shares a single Cloudflare Worker address and the figure would
// read as 1 for the whole range, which looks like a bug rather than a gap.
// The API withholds the figure under exactly the same condition, and returns
// null when it does. Checking the payload as well as the date means a response
// that predates a config change hides the card rather than rendering a zero.
const showUniqueVisitors = computed(() => {
  const since = meta.value.unique_visitors_since;
  if (!since || toYmd(dateRange.value.start) < since) return false;
  return analyticsData.value?.summary?.unique_visitors != null;
});

const error = computed(() => {
  if (analyticsError.value)
    return analyticsError.value.response?._data?.message || "Failed to load analytics";
  return null;
});

// Chart data for ChartLineDefault component
const chartData = computed(() => {
  if (!analyticsData.value?.visits_per_day || !Array.isArray(analyticsData.value.visits_per_day)) {
    return [];
  }

  return analyticsData.value.visits_per_day
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
      label: "Views",
      color: "var(--chart-1)",
    },
  };
});

// Watch for range changes and refresh data
watch(dateRange, () => loadAnalytics(), { deep: true });

usePageMeta(null, {
  title: "Posts Analytics",
  description: "Analytics for all published posts",
});
</script>
