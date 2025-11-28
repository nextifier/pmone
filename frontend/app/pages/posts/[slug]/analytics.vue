<template>
  <div class="mx-auto max-w-6xl space-y-6 pt-4 pb-16">
    <div class="flex flex-col gap-y-6">
      <div class="flex items-center justify-between gap-2">
        <BackButton :destination="post ? `/posts/${post.slug}` : '/posts'" />
        <DialogViewRaw :data="analyticsData" />
      </div>

      <div class="flex w-full flex-wrap items-center justify-between gap-4">
        <div class="flex flex-col gap-y-1">
          <h1 class="page-title">Post Analytics</h1>
          <p v-if="post" class="text-muted-foreground max-w-2xl text-sm">
            {{ post.title }}
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
      <!-- Post Info Card -->
      <div class="border-border rounded-lg border p-6">
        <div class="flex items-start gap-4">
          <div
            v-if="post?.featured_image"
            class="bg-muted aspect-video w-32 shrink-0 overflow-hidden rounded-lg"
          >
            <img :src="post.featured_image.url" :alt="post.title" class="size-full object-cover" />
          </div>
          <div
            v-else
            class="bg-muted flex aspect-video w-32 shrink-0 items-center justify-center rounded-lg"
          >
            <Icon name="hugeicons:image-01" class="text-muted-foreground size-8" />
          </div>

          <div class="min-w-0 flex-1">
            <h2 class="text-primary text-lg font-semibold tracking-tight">
              {{ post?.title }}
            </h2>
            <p
              v-if="post?.excerpt"
              class="text-muted-foreground mt-1 line-clamp-2 text-sm tracking-tight"
            >
              {{ post.excerpt }}
            </p>
            <div class="text-muted-foreground mt-2 flex flex-wrap items-center gap-2 text-xs">
              <span v-if="post?.published_at">
                Published {{ $dayjs(post.published_at).format("MMM D, YYYY") }}
              </span>
              <span v-if="post?.reading_time">• {{ post.reading_time }} min read</span>
              <span
                v-if="post?.status"
                class="capitalize"
                :class="{
                  'text-green-600 dark:text-green-400': post.status === 'published',
                  'text-yellow-600 dark:text-yellow-400': post.status === 'draft',
                  'text-blue-600 dark:text-blue-400': post.status === 'scheduled',
                }"
              >
                • {{ post.status }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Summary Cards -->
      <div class="grid gap-4 sm:grid-cols-3">
        <div class="border-border rounded-lg border p-6">
          <div class="text-muted-foreground text-sm font-medium">Total Visits</div>
          <div class="text-primary mt-2 text-4xl font-semibold">
            {{ analyticsData.summary.total_visits.toLocaleString() }}
          </div>
        </div>

        <div class="border-border rounded-lg border p-6">
          <div class="text-muted-foreground text-sm font-medium">Authenticated</div>
          <div class="text-primary mt-2 text-4xl font-semibold">
            {{ analyticsData.summary.authenticated_visits.toLocaleString() }}
          </div>
        </div>

        <div class="border-border rounded-lg border p-6">
          <div class="text-muted-foreground text-sm font-medium">Anonymous</div>
          <div class="text-primary mt-2 text-4xl font-semibold">
            {{ analyticsData.summary.anonymous_visits.toLocaleString() }}
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
        <div v-if="analyticsData.top_visitors?.length" class="space-y-2">
          <div
            v-for="(visitorData, index) in analyticsData.top_visitors"
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

            <div class="text-muted-foreground shrink-0 text-sm">{{ referrer.count }} visits</div>
          </div>
        </div>
        <div v-else class="text-muted-foreground py-8 text-center tracking-tight">
          No referrer data available
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

const route = useRoute();
const slug = computed(() => route.params.slug);

const { $dayjs } = useNuxtApp();

const selectedPeriod = ref("30");

// Fetch post details with lazy loading
const { data: postResponse, error: postError } = await useLazySanctumFetch(
  () => `/api/posts/${slug.value}?for=analytics`,
  {
    key: `post-analytics-detail-${slug.value}`,
  }
);

const post = computed(() => postResponse.value?.data || null);

// Fetch analytics with lazy loading
const {
  data: analyticsResponse,
  pending: loading,
  error: analyticsError,
  refresh: loadAnalytics,
} = await useLazySanctumFetch(
  () => `/api/posts/${slug.value}/analytics?period=${selectedPeriod.value}`,
  {
    key: `post-analytics-${slug.value}-${selectedPeriod.value}`,
    watch: [selectedPeriod],
  }
);

const analyticsData = computed(() => analyticsResponse.value?.data || null);

const error = computed(() => {
  if (postError.value) return "Failed to load post";
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
  title: `Analytics - ${slug.value}`,
  description: `Analytics for post ${slug.value}`,
});
</script>
