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

const truncateUrl = (url: string, maxLength = 35) => {
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
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h3 class="page-title text-lg!">Top Links</h3>
      <NuxtLink
        to="/links"
        class="text-muted-foreground hover:text-foreground flex items-center gap-x-1 text-sm tracking-tight"
      >
        <span>View all</span>
        <Icon name="hugeicons:arrow-right-02" class="size-4 shrink-0" />
      </NuxtLink>
    </div>

    <!-- Loading -->
    <template v-if="loading">
      <div class="space-y-3">
        <div v-for="i in 3" :key="i" class="space-y-1.5">
          <div class="flex items-center justify-between">
            <Skeleton class="h-3.5 w-28" />
            <Skeleton class="h-3 w-10" />
          </div>
          <Skeleton class="h-1 w-full rounded-full" />
        </div>
      </div>
    </template>

    <!-- Empty -->
    <template v-else-if="!links || links.length === 0">
      <div class="flex items-center gap-2 py-4">
        <Icon name="hugeicons:link-02" class="text-muted-foreground size-4" />
        <div class="flex items-center gap-1.5">
          <p class="text-muted-foreground text-sm tracking-tight">No links yet</p>
          <NuxtLink to="/links/create" class="text-primary text-xs font-medium tracking-tight hover:underline">
            Create one
          </NuxtLink>
        </div>
      </div>
    </template>

    <!-- Links List -->
    <div v-else class="space-y-3">
      <NuxtLink
        v-for="link in links"
        :key="link.id"
        :to="`/links/${link.slug}`"
        class="flex flex-col gap-1.5 transition-opacity hover:opacity-80"
      >
        <div class="flex items-center justify-between gap-2">
          <div class="flex min-w-0 flex-1 items-center gap-1.5">
            <Icon name="hugeicons:link-02" class="size-3 shrink-0 text-violet-600 dark:text-violet-400" />
            <span class="truncate text-sm tracking-tight">
              {{ link.og_title || link.slug || truncateUrl(link.destination_url) }}
            </span>
          </div>
          <span class="text-muted-foreground shrink-0 text-xs tabular-nums tracking-tight">
            {{ link.clicks_count }}
          </span>
        </div>
        <div class="bg-muted h-1 w-full overflow-hidden rounded-full">
          <div
            class="h-full rounded-full bg-gradient-to-r from-violet-500 to-purple-500 transition-[width] duration-500"
            :style="{ width: `${getProgressWidth(link.clicks_count)}%` }"
          />
        </div>
      </NuxtLink>
    </div>
  </div>
</template>
