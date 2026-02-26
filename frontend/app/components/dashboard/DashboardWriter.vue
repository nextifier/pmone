<template>
  <div
    class="mx-auto flex flex-col gap-y-3 pt-2 pt-4 pb-16 pb-20 lg:max-w-4xl lg:pt-4 xl:max-w-6xl"
  >
    <DashboardGreetingTips :tip-definitions="tipDefinitions" :tips="tips" :loading="loading" />

    <!-- Stats Cards -->
    <div class="mt-2 grid grid-cols-2 gap-2 sm:mt-4 lg:grid-cols-4">
      <DashboardStatsCard
        title="Total Posts"
        description="All posts you've created"
        :value="stats?.total_posts ?? 0"
        icon="hugeicons:task-edit-01"
        icon-color="text-blue-600 dark:text-blue-400"
        href="/posts"
        :loading="loading"
      />
      <DashboardStatsCard
        title="Published"
        description="Live and visible to readers"
        :value="stats?.published_posts ?? 0"
        icon="hugeicons:checkmark-circle-02"
        icon-color="text-emerald-600 dark:text-emerald-400"
        href="/posts"
        :loading="loading"
      />
      <DashboardStatsCard
        title="Drafts"
        description="Unpublished, still in progress"
        :value="stats?.draft_posts ?? 0"
        icon="hugeicons:file-edit"
        icon-color="text-amber-600 dark:text-amber-400"
        href="/posts"
        :loading="loading"
      />
      <DashboardStatsCard
        title="Views (30 days)"
        description="Readers in the past month"
        :value="stats?.total_views_30d ?? 0"
        icon="hugeicons:eye"
        icon-color="text-violet-600 dark:text-violet-400"
        :loading="loading"
      />
    </div>

    <!-- Visits Over Time Chart -->
    <div class="mt-5 sm:mt-8">
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="page-title text-lg!">Visits Over Time</h3>
          <NuxtLink
            to="/posts/analytics"
            class="text-muted-foreground hover:text-foreground flex items-center gap-x-1 text-sm tracking-tight"
          >
            <span>Details</span>
            <Icon name="hugeicons:arrow-right-02" class="size-4 shrink-0" />
          </NuxtLink>
        </div>
        <template v-if="loading">
          <Skeleton class="h-48 w-full rounded-lg" />
        </template>
        <div v-else-if="chartData?.length > 0">
          <ChartLine
            :data="chartData"
            :config="chartConfig"
            :gradient="true"
            data-key="count"
            class="h-auto! overflow-hidden py-2.5"
          />
        </div>
        <div v-else class="text-muted-foreground py-8 text-center text-sm tracking-tight">
          No visit data available
        </div>
      </div>
    </div>

    <!-- Two Column: Recent Posts + Top Performing -->
    <div class="mt-5 grid gap-10 sm:mt-8 lg:grid-cols-2">
      <!-- Recent Posts -->
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="page-title text-lg!">Recent Posts</h3>
          <NuxtLink
            to="/posts"
            class="text-muted-foreground hover:text-foreground flex items-center gap-x-1 text-sm tracking-tight"
          >
            <span>View all</span>
            <Icon name="hugeicons:arrow-right-02" class="size-4 shrink-0" />
          </NuxtLink>
        </div>
        <template v-if="loading">
          <div class="space-y-3">
            <div v-for="i in 5" :key="i" class="flex items-center gap-x-2">
              <Skeleton class="size-12 shrink-0 rounded-lg" />
              <div class="flex-1 space-y-1">
                <Skeleton class="h-3 w-16" />
                <Skeleton class="h-4 w-3/4" />
              </div>
            </div>
          </div>
        </template>
        <div
          v-else-if="!recentPosts.length"
          class="border-border flex flex-col items-center gap-2 rounded-xl border px-4 py-8"
        >
          <div class="bg-muted flex size-10 items-center justify-center rounded-full">
            <Icon name="hugeicons:task-edit-01" class="text-muted-foreground size-5" />
          </div>
          <p class="text-muted-foreground text-sm tracking-tight">No posts yet</p>
          <NuxtLink
            to="/posts/create"
            class="text-primary text-xs font-medium tracking-tight hover:underline"
          >
            Write your first post
          </NuxtLink>
        </div>
        <div v-else class="space-y-2">
          <NuxtLink
            v-for="post in recentPosts"
            :key="post.id"
            :to="`/posts/${post.slug}/edit`"
            class="flex items-center gap-x-2 transition-opacity hover:opacity-80"
          >
            <div class="bg-muted border-border size-12 shrink-0 overflow-hidden rounded-sm border">
              <img
                v-if="post.featured_image?.sm"
                :src="post.featured_image.sm"
                :alt="post.title"
                class="size-full object-cover select-none"
                loading="lazy"
              />
            </div>
            <div class="flex flex-col items-start gap-y-0.5 overflow-hidden">
              <div class="flex items-center gap-x-2">
                <span
                  class="text-xs font-medium tracking-tight capitalize"
                  :class="{
                    'text-success-foreground': post.status === 'published',
                    'text-warning-foreground': post.status === 'draft',
                    'text-muted-foreground': post.status === 'scheduled',
                  }"
                >
                  {{ post.status }}
                </span>
                <span class="text-muted-foreground text-xs tracking-tight"
                  >{{ post.visits_count }} views</span
                >
              </div>
              <p class="line-clamp-1 text-sm tracking-tight">{{ post.title }}</p>
            </div>
          </NuxtLink>
        </div>
      </div>

      <!-- Top Performing Posts -->
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="page-title text-lg!">Top Performing Posts</h3>
          <NuxtLink
            to="/posts/analytics"
            class="text-muted-foreground hover:text-foreground flex items-center gap-x-1 text-sm tracking-tight"
          >
            <span>Analytics</span>
            <Icon name="hugeicons:arrow-right-02" class="size-4 shrink-0" />
          </NuxtLink>
        </div>
        <template v-if="loading">
          <div class="space-y-3">
            <div v-for="i in 5" :key="i" class="flex items-center gap-x-2">
              <Skeleton class="size-12 shrink-0 rounded-lg" />
              <div class="flex-1 space-y-1">
                <Skeleton class="h-3 w-16" />
                <Skeleton class="h-4 w-3/4" />
              </div>
            </div>
          </div>
        </template>
        <div
          v-else-if="!topPosts.length"
          class="border-border flex flex-col items-center gap-2 rounded-xl border px-4 py-8"
        >
          <div class="bg-muted flex size-10 items-center justify-center rounded-full">
            <Icon name="hugeicons:analytics-01" class="text-muted-foreground size-5" />
          </div>
          <p class="text-muted-foreground text-sm tracking-tight">
            No published posts with views yet
          </p>
        </div>
        <div v-else class="space-y-2">
          <NuxtLink
            v-for="(post, index) in topPosts"
            :key="post.id"
            :to="`/posts/${post.slug}/analytics`"
            class="flex items-center gap-x-2 transition-opacity hover:opacity-80"
          >
            <div class="bg-muted border-border size-12 shrink-0 overflow-hidden rounded-sm border">
              <img
                v-if="post.featured_image?.sm"
                :src="post.featured_image.sm"
                :alt="post.title"
                class="size-full object-cover select-none"
                loading="lazy"
              />
            </div>
            <div class="flex flex-col items-start gap-y-0.5 overflow-hidden">
              <div class="flex items-center gap-x-2">
                <span class="text-muted-foreground text-xs font-medium tracking-tight">
                  #{{ index + 1 }}
                </span>
                <span
                  class="text-muted-foreground inline-flex items-center gap-0.5 text-xs tracking-tight"
                >
                  <Icon name="hugeicons:eye" class="size-3" />
                  {{ post.visits_count }}
                </span>
                <span
                  v-if="post.recent_visits_count > 0"
                  class="text-xs tracking-tight text-emerald-600 dark:text-emerald-400"
                >
                  +{{ post.recent_visits_count }} (Last 30 days)
                </span>
              </div>
              <p class="line-clamp-1 text-sm tracking-tight">{{ post.title }}</p>
            </div>
          </NuxtLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  tipDefinitions: { type: Array, required: true },
});

const client = useSanctumClient();

const loading = ref(true);
const tips = ref(null);
const stats = ref(null);
const visitsPerDay = ref([]);
const recentPosts = ref([]);
const topPosts = ref([]);

const chartData = computed(() => {
  if (!visitsPerDay.value?.length) return [];
  return visitsPerDay.value
    .map((item) => ({
      date: new Date(item.date),
      count: item.count || 0,
    }))
    .sort((a, b) => a.date - b.date);
});

const chartConfig = {
  count: {
    label: "Visits",
    color: "var(--chart-1)",
  },
};

const fetchData = async () => {
  try {
    loading.value = true;
    const response = await client("/api/dashboard/writer-stats");

    if (response?.data) {
      tips.value = response.data.tips || null;
      stats.value = response.data.stats;
      visitsPerDay.value = response.data.visits_per_day || [];
      recentPosts.value = response.data.recent_posts || [];
      topPosts.value = response.data.top_posts || [];
    }
  } catch (error) {
    console.error("Failed to fetch writer dashboard stats:", error);
  } finally {
    loading.value = false;
  }
};

onMounted(fetchData);
</script>
