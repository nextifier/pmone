<template>
  <div
    class="border-border bg-card group hover:bg-muted/50 relative rounded-lg border p-4 transition-all"
  >
    <div class="flex items-start gap-3">
      <div
        class="bg-muted text-foreground flex size-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
      >
        {{ rank }}
      </div>
      <div class="min-w-0 flex-1">
        <h3 class="text-foreground mb-1 truncate font-semibold">
          {{ page.title || "Untitled" }}
        </h3>
        <p class="text-muted-foreground mb-2 truncate text-sm">{{ page.path || "/" }}</p>
        <div class="flex items-center justify-between gap-4">
          <div class="flex items-center gap-2">
            <Icon name="hugeicons:analytics-01" class="text-muted-foreground size-4" />
            <span class="text-muted-foreground text-xs">{{ page.property_name || "Unknown" }}</span>
          </div>
          <div class="text-right">
            <p class="text-foreground text-lg font-semibold">
              {{ formatNumber(page.pageviews || 0) }}
            </p>
            <p class="text-muted-foreground text-xs">views</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  page: {
    type: Object,
    required: true,
  },
  rank: {
    type: Number,
    required: true,
  },
});

const formatNumber = (value) => {
  if (value === null || value === undefined) return "0";
  return new Intl.NumberFormat().format(Math.round(value));
};
</script>
