<script setup lang="ts">
interface LinkItem {
  id: number;
  slug: string;
  destination_url: string;
  og_title: string | null;
  clicks_count: number;
}

const props = defineProps<{
  links: LinkItem[];
  loading?: boolean;
}>();

const maxClicks = computed(() => {
  if (!props.links || props.links.length === 0) return 0;
  return Math.max(...props.links.map((l) => l.clicks_count));
});

const getProgressWidth = (clicks: number) => {
  if (maxClicks.value === 0) return 0;
  return (clicks / maxClicks.value) * 100;
};

const truncateUrl = (url: string, maxLength = 40) => {
  try {
    const urlObj = new URL(url);
    const display = urlObj.hostname + urlObj.pathname;
    if (display.length > maxLength) {
      return display.substring(0, maxLength) + "...";
    }
    return display;
  } catch {
    if (url.length > maxLength) {
      return url.substring(0, maxLength) + "...";
    }
    return url;
  }
};
</script>

<template>
  <Card>
    <CardHeader class="pb-3">
      <div class="flex items-center justify-between">
        <div class="flex flex-col gap-1">
          <CardTitle class="text-base font-semibold tracking-tight">Top Links</CardTitle>
          <CardDescription class="text-xs">Most clicked this week</CardDescription>
        </div>
        <NuxtLink
          to="/links"
          class="text-primary hover:text-primary/80 flex items-center gap-1 text-xs font-medium transition-colors"
        >
          View all
          <Icon name="hugeicons:arrow-right-02" class="size-3.5" />
        </NuxtLink>
      </div>
    </CardHeader>
    <CardContent class="px-4 pb-4 pt-0">
      <!-- Loading State -->
      <template v-if="loading">
        <div class="flex flex-col gap-4">
          <div v-for="i in 5" :key="i" class="flex flex-col gap-2">
            <div class="flex items-center justify-between">
              <Skeleton class="h-4 w-32" />
              <Skeleton class="h-4 w-12" />
            </div>
            <Skeleton class="h-2 w-full rounded-full" />
          </div>
        </div>
      </template>

      <!-- Empty State -->
      <template v-else-if="!links || links.length === 0">
        <div class="flex flex-col items-center justify-center gap-3 py-8">
          <div class="bg-muted flex size-12 items-center justify-center rounded-full">
            <Icon name="hugeicons:link-02" class="text-muted-foreground size-6" />
          </div>
          <div class="flex flex-col items-center gap-1">
            <p class="text-muted-foreground text-sm">No links yet</p>
            <NuxtLink to="/links/create" class="text-primary text-xs font-medium hover:underline">
              Create your first link
            </NuxtLink>
          </div>
        </div>
      </template>

      <!-- Links List -->
      <template v-else>
        <div class="flex flex-col gap-4">
          <NuxtLink
            v-for="link in links"
            :key="link.id"
            :to="`/links/${link.slug}`"
            class="group flex flex-col gap-2"
          >
            <div class="flex items-center justify-between gap-2">
              <div class="flex min-w-0 flex-1 items-center gap-2">
                <div
                  class="flex size-6 shrink-0 items-center justify-center rounded-md bg-violet-500/10 transition-colors group-hover:bg-violet-500/20"
                >
                  <Icon name="hugeicons:link-02" class="size-3.5 text-violet-600 dark:text-violet-400" />
                </div>
                <span class="text-foreground group-hover:text-primary truncate text-sm font-medium transition-colors">
                  {{ link.og_title || link.slug || truncateUrl(link.destination_url) }}
                </span>
              </div>
              <span class="text-muted-foreground shrink-0 text-xs font-medium tabular-nums">
                {{ link.clicks_count }} {{ link.clicks_count === 1 ? "click" : "clicks" }}
              </span>
            </div>

            <!-- Progress bar -->
            <div class="bg-muted h-1.5 w-full overflow-hidden rounded-full">
              <div
                class="h-full rounded-full bg-gradient-to-r from-violet-500 to-purple-500 transition-all duration-500"
                :style="{ width: `${getProgressWidth(link.clicks_count)}%` }"
              />
            </div>
          </NuxtLink>
        </div>
      </template>
    </CardContent>
  </Card>
</template>
