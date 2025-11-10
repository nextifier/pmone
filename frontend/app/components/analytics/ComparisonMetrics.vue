<script setup lang="ts">
const props = defineProps<{
  days: number;
}>();

const { fetchAggregateComparison } = useComparisonData();
const { formatChange, getTrendIcon, getTrendColor, getTrend } = useHistoricalComparison();

const comparison = ref<any>(null);
const loading = ref(true);
const error = ref<string | null>(null);

const loadComparison = async () => {
  loading.value = true;
  error.value = null;

  try {
    const data = await fetchAggregateComparison(props.days);
    comparison.value = data;
  } catch (err: any) {
    error.value = err.message || "Failed to load comparison";
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadComparison();
});

watch(() => props.days, () => {
  loadComparison();
});

const comparisonMetrics = computed(() => {
  if (!comparison.value?.changes) return [];

  const changes = comparison.value.changes;
  const periods = comparison.value.periods;

  return [
    {
      key: "activeUsers",
      label: "Total Visitors",
      current: changes.activeUsers?.current || 0,
      previous: changes.activeUsers?.previous || 0,
      change: changes.activeUsers?.percentage_change || 0,
      trend: changes.activeUsers?.trend || "neutral",
    },
    {
      key: "newUsers",
      label: "New Visitors",
      current: changes.newUsers?.current || 0,
      previous: changes.newUsers?.previous || 0,
      change: changes.newUsers?.percentage_change || 0,
      trend: changes.newUsers?.trend || "neutral",
    },
    {
      key: "sessions",
      label: "Sessions",
      current: changes.sessions?.current || 0,
      previous: changes.sessions?.previous || 0,
      change: changes.sessions?.percentage_change || 0,
      trend: changes.sessions?.trend || "neutral",
    },
    {
      key: "screenPageViews",
      label: "Page Views",
      current: changes.screenPageViews?.current || 0,
      previous: changes.screenPageViews?.previous || 0,
      change: changes.screenPageViews?.percentage_change || 0,
      trend: changes.screenPageViews?.trend || "neutral",
    },
  ];
});

const formatNumber = (value: number) => {
  return new Intl.NumberFormat().format(Math.round(value));
};
</script>

<template>
  <div class="border-border bg-card rounded-lg border p-6">
    <div class="mb-4 flex items-center justify-between">
      <div>
        <h3 class="text-lg font-semibold">Performance Comparison</h3>
        <p class="text-muted-foreground mt-1 text-sm">
          Current period vs previous {{ days }} days
        </p>
      </div>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-8">
      <Spinner class="size-6" />
    </div>

    <div v-else-if="error" class="text-destructive py-4 text-center text-sm">
      {{ error }}
    </div>

    <div v-else-if="comparisonMetrics.length > 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div
        v-for="metric in comparisonMetrics"
        :key="metric.key"
        class="border-border rounded-lg border p-4"
      >
        <div class="text-muted-foreground mb-2 text-xs font-medium uppercase tracking-wide">
          {{ metric.label }}
        </div>
        <div class="text-foreground mb-1 text-2xl font-bold">
          {{ formatNumber(metric.current) }}
        </div>
        <div class="flex items-center gap-1.5">
          <Icon
            :name="getTrendIcon(metric.change)"
            class="size-4"
            :class="getTrendColor(metric.change)"
          />
          <span class="text-sm font-medium" :class="getTrendColor(metric.change)">
            {{ formatChange(metric.change) }}
          </span>
        </div>
        <div class="text-muted-foreground mt-1 text-xs">
          vs {{ formatNumber(metric.previous) }} previous
        </div>
      </div>
    </div>
  </div>
</template>
