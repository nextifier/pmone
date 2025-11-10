<template>
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
    <div
      v-for="metric in metrics"
      :key="metric.key"
      class="border-border bg-card hover:bg-muted/50 rounded-lg border p-5 transition-colors"
    >
      <div class="flex items-center justify-between">
        <p class="text-muted-foreground text-sm font-medium">
          {{ metric.label }}
        </p>
        <div class="flex size-10 items-center justify-center rounded-lg" :class="metric.bgClass">
          <Icon :name="metric.icon" class="size-5" :class="metric.iconClass" />
        </div>
      </div>
      <div class="mt-3">
        <p class="text-foreground text-3xl font-bold tracking-tight">
          {{ metric.formattedValue }}
        </p>
        <p class="text-muted-foreground mt-1 text-sm">
          {{ metric.description }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  metrics: {
    type: Array,
    required: true,
  },
});
</script>
