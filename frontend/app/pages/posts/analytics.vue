<template>
  <div class="mx-auto max-w-6xl space-y-6 pt-4 pb-16">
    <div class="flex flex-col items-start gap-y-6">
      <BackButton destination="/posts" />

      <div class="flex w-full flex-wrap items-center justify-between gap-4">
        <div class="flex flex-col gap-y-1">
          <h1 class="page-title">Posts Analytics</h1>
          <p class="text-muted-foreground text-sm">Overall analytics for all published posts</p>
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
        <div v-if="analyticsData.visits_per_day?.length" class="space-y-2">
          <div
            v-for="day in analyticsData.visits_per_day"
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
                    width: `${(day.count / maxVisitsPerDay) * 100}%`,
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
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const sanctumFetch = useSanctumClient();
const { $dayjs } = useNuxtApp();

const selectedPeriod = ref(30);
const analyticsData = ref(null);
const loading = ref(true);
const error = ref(null);

// Computed max visits per day for chart scaling
const maxVisitsPerDay = computed(() => {
  if (!analyticsData.value?.visits_per_day?.length) {
    return 0;
  }
  return Math.max(...analyticsData.value.visits_per_day.map((d) => d.count));
});

// Load analytics data
async function loadAnalytics() {
  loading.value = true;
  error.value = null;

  try {
    const response = await sanctumFetch(`/api/posts/analytics?days=${selectedPeriod.value}`);
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
  await loadAnalytics();
});

usePageMeta("", {
  title: "Posts Analytics",
  description: "Analytics for all published posts",
});
</script>
