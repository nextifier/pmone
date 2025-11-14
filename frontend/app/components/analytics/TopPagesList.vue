<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-foreground flex items-center gap-2 text-lg font-semibold tracking-tighter">
          <Icon name="hugeicons:file-star" class="size-5" />
          Top Pages
        </h2>
        <p class="text-muted-foreground mt-0.5 text-sm tracking-tight">
          Most visited pages across all properties
        </p>
      </div>
    </div>

    <div v-if="pages && pages.length > 0" class="space-y-3">
      <div
        v-for="(page, index) in displayedPages"
        :key="index"
        class="border-border bg-card hover:border-foreground/20 group relative overflow-hidden rounded-lg border transition-all"
      >
        <div class="flex items-center gap-4 p-4">
          <!-- Rank Badge -->
          <div
            class="bg-primary/10 text-primary flex size-12 shrink-0 items-center justify-center rounded-lg text-lg font-bold"
          >
            {{ index + 1 }}
          </div>

          <!-- Page Info -->
          <div class="min-w-0 flex-1 space-y-1">
            <h3 class="text-foreground truncate font-semibold tracking-tight">
              {{ page.title || page.pageTitle || "Untitled Page" }}
            </h3>
            <p class="text-muted-foreground truncate text-sm">
              {{ page.path || page.pagePath || "/" }}
            </p>
            <div v-if="page.property_name" class="flex items-center gap-1.5">
              <Icon name="hugeicons:analytics-01" class="text-muted-foreground size-3.5" />
              <span class="text-muted-foreground text-xs">{{ page.property_name }}</span>
            </div>
          </div>

          <!-- Metrics -->
          <div class="flex shrink-0 flex-col items-end gap-1">
            <div class="flex items-baseline gap-1.5">
              <span class="text-foreground text-2xl font-bold tabular-nums">
                {{ formatNumber(page.pageviews || page.screenPageViews || 0) }}
              </span>
              <span class="text-muted-foreground text-xs font-medium">views</span>
            </div>
            <div
              v-if="page.activeUsers"
              class="flex items-baseline gap-1.5"
            >
              <Icon name="hugeicons:user-multiple-02" class="text-muted-foreground size-3.5" />
              <span class="text-muted-foreground text-xs tabular-nums">
                {{ formatNumber(page.activeUsers) }} users
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Show More/Less Button -->
      <button
        v-if="pages.length > limit"
        @click="toggleShowAll"
        class="hover:bg-muted border-border mx-auto flex items-center gap-x-1.5 rounded-md border px-4 py-2 text-sm font-medium tracking-tight transition active:scale-98"
      >
        <Icon
          :name="showAll ? 'hugeicons:arrow-up-01' : 'hugeicons:arrow-down-01'"
          class="size-4"
        />
        <span>{{ showAll ? "Show Less" : `Show All (${pages.length})` }}</span>
      </button>
    </div>

    <!-- Empty State -->
    <div
      v-else
      class="border-border bg-muted/30 flex flex-col items-center justify-center rounded-lg border p-8 text-center"
    >
      <Icon name="hugeicons:file-not-found" class="text-muted-foreground size-12" />
      <p class="text-muted-foreground mt-3 text-sm">No top pages data available</p>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  pages: {
    type: Array,
    default: () => [],
  },
  limit: {
    type: Number,
    default: 10,
  },
});

const showAll = ref(false);

const displayedPages = computed(() => {
  if (!props.pages) return [];
  return showAll.value ? props.pages : props.pages.slice(0, props.limit);
});

const toggleShowAll = () => {
  showAll.value = !showAll.value;
};

const formatNumber = (value) => {
  if (value === null || value === undefined) return "0";
  return new Intl.NumberFormat().format(Math.round(value));
};
</script>
