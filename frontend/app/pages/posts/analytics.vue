<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex flex-col gap-y-6">
      <div class="flex items-center justify-between gap-2">
        <BackButton destination="/posts" />
        <DialogViewRaw :data="analyticsData" />
      </div>

      <div class="flex w-full flex-wrap items-center justify-between gap-4">
        <div class="flex flex-col gap-y-1">
          <h1 class="page-title">Posts Analytics</h1>
          <p class="text-muted-foreground text-sm">Overall analytics for all published posts</p>
        </div>

        <DateRangeSelect v-model="selectedPeriod" />
      </div>
    </div>

    <LoadingState v-if="loading" label="Loading analytics.." />

    <div v-else-if="error" class="py-12 text-center">
      <p class="text-destructive">{{ error }}</p>
    </div>

    <div v-else-if="analyticsData" class="space-y-6">
      <!-- Summary Cards -->
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="border-border rounded-lg border p-6">
          <div class="text-muted-foreground text-sm font-medium">Total Visits</div>
          <div class="text-primary mt-2 text-4xl font-semibold">
            {{ analyticsData.summary.total_visits.toLocaleString() }}
          </div>
          <div class="text-muted-foreground mt-1 text-xs">
            {{ analyticsData.summary.authenticated_visits.toLocaleString() }} authenticated •
            {{ analyticsData.summary.anonymous_visits.toLocaleString() }} anonymous
          </div>
        </div>

        <div class="border-border rounded-lg border p-6">
          <div class="text-muted-foreground text-sm font-medium">Published Posts</div>
          <div class="text-primary mt-2 text-4xl font-semibold">
            {{ analyticsData.summary.total_posts.toLocaleString() }}
          </div>
          <div class="text-muted-foreground mt-1 text-xs">
            {{ analyticsData.summary.total_drafts.toLocaleString() }} drafts •
            {{ analyticsData.summary.total_scheduled.toLocaleString() }} scheduled
          </div>
        </div>

        <div class="border-border rounded-lg border p-6">
          <div class="text-muted-foreground text-sm font-medium">Avg. Visits/Post</div>
          <div class="text-primary mt-2 text-4xl font-semibold">
            {{
              analyticsData.summary.total_posts > 0
                ? Math.round(analyticsData.summary.total_visits / analyticsData.summary.total_posts)
                : 0
            }}
          </div>
          <div class="text-muted-foreground mt-1 text-xs">Per published post</div>
        </div>
      </div>

      <!-- Visits Over Time Chart -->
      <div class="border-border rounded-lg border p-4">
        <h2 class="mb-4 text-lg font-semibold tracking-tighter">Visits Over Time</h2>
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
          No visit data available for this period
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
              <h3 class="text-primary truncate font-medium tracking-tight">{{ post.title }}</h3>
              <p
                v-if="post.excerpt"
                class="text-muted-foreground mt-0.5 line-clamp-1 text-sm tracking-tight"
              >
                {{ post.excerpt }}
              </p>
              <div class="text-muted-foreground mt-1 flex items-center gap-2 text-xs">
                <span>{{ $dayjs(post.published_at).format("MMM D, YYYY") }}</span>
                <span>•</span>
                <span>{{ post.visits_count.toLocaleString() }} visits</span>
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
import DateRangeSelect from "@/components/analytics/DateRangeSelect.vue";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const { $dayjs } = useNuxtApp();

const selectedPeriod = ref("30");

// Fetch analytics with lazy loading
const {
  data: analyticsResponse,
  pending: loading,
  error: analyticsError,
  refresh: loadAnalytics,
} = await useLazySanctumFetch(() => `/api/posts/analytics?period=${selectedPeriod.value}`, {
  key: `posts-analytics-${selectedPeriod.value}`,
  watch: [selectedPeriod],
});

const analyticsData = computed(() => analyticsResponse.value?.data || null);

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
      label: "Visits",
      color: "var(--chart-1)",
    },
  };
});

// Watch for period changes and refresh data
watch(selectedPeriod, () => {
  loadAnalytics();
});

usePageMeta("", {
  title: "Posts Analytics",
  description: "Analytics for all published posts",
});
</script>
