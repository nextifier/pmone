<template>
  <ul v-if="items.length" class="space-y-3.5">
    <li v-for="(item, i) in rows" :key="item.key ?? i" class="space-y-1.5">
      <div class="flex items-baseline justify-between gap-3">
        <div class="flex min-w-0 items-center gap-x-2">
          <span class="truncate text-sm tracking-tight">{{ item.label }}</span>
          <span
            v-if="item.sublabel"
            class="text-muted-foreground shrink-0 text-xs tracking-tight sm:text-sm"
          >
            {{ item.sublabel }}
          </span>
        </div>
        <div class="flex shrink-0 items-baseline gap-x-1.5">
          <span class="text-foreground text-sm font-medium tabular-nums tracking-tight">
            {{ item.display }}
          </span>
          <span
            v-if="item.secondary"
            class="text-muted-foreground text-xs tabular-nums tracking-tight sm:text-sm"
          >
            {{ item.secondary }}
          </span>
        </div>
      </div>
      <div class="bg-muted h-2 w-full overflow-hidden rounded-full">
        <div
          class="h-full rounded-full transition-[width] duration-700 ease-out"
          :class="toneClass[item.tone] ?? toneClass.primary"
          :style="{ width: `${item.pct}%` }"
        />
      </div>
    </li>
  </ul>
</template>

<script setup>
const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
  // Returns the right-aligned display string for an item's value.
  formatValue: {
    type: Function,
    default: (value) => new Intl.NumberFormat("en-US").format(value ?? 0),
  },
});

const toneClass = {
  primary: "bg-primary",
  success: "bg-success",
  warning: "bg-warning",
  destructive: "bg-destructive",
  info: "bg-info",
  muted: "bg-muted-foreground/40",
};

const max = computed(() => Math.max(1, ...props.items.map((item) => Number(item.value) || 0)));

const rows = computed(() =>
  props.items.map((item) => ({
    ...item,
    display: item.display ?? props.formatValue(item.value),
    pct: Math.max(2, Math.round(((Number(item.value) || 0) / max.value) * 100)),
  }))
);
</script>
