<template>
  <div class="grid grid-cols-[repeat(auto-fit,minmax(280px,1fr))] gap-x-2.5 gap-y-4">
    <div
      v-for="metric in metrics"
      :key="metric.key"
      class="border-border bg-card flex flex-col rounded-xl border"
    >
      <div class="flex flex-col p-5">
        <div class="flex items-center justify-between">
          <div class="inline-flex items-center gap-x-2">
            <p class="text-foreground text-sm font-medium tracking-tight">
              {{ metric.label }}
            </p>

            <div
              v-if="metric.key === 'onlineUsers'"
              class="text-success-foreground bg-success/10 inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium"
            >
              <span class="relative flex size-2">
                <span
                  class="animate-ping-slow bg-success absolute inline-flex size-full rounded-full opacity-75"
                ></span>
                <span class="bg-success relative inline-flex size-full rounded-full"></span>
              </span>

              <span>LIVE</span>
            </div>
          </div>
          <div class="flex size-8 items-center justify-center rounded-lg" :class="metric.bgClass">
            <Icon :name="metric.icon" class="size-5 shrink-0" :class="metric.iconClass" />
          </div>
        </div>

        <div>
          <NumberFlow
            class="text-foreground text-3xl leading-none! font-bold tracking-tighter"
            :class="{
              'cursor-pointer': !['percent', 'duration'].includes(metric.format),
            }"
            :value="metric.value"
            :format="{
              notation:
                ['percent', 'duration'].includes(metric.format) || isExpanded
                  ? 'standard'
                  : 'compact',
              ...(metric.format === 'percent'
                ? { minimumFractionDigits: 1, maximumFractionDigits: 1 }
                : {}),
              ...(metric.format === 'duration'
                ? { minimumFractionDigits: 0, maximumFractionDigits: 0 }
                : {}),
            }"
            :suffix="{ percent: '%', duration: 's' }[metric.format]"
            @click="!['percent', 'duration'].includes(metric.format) && (isExpanded = !isExpanded)"
          />
        </div>

        <p class="text-muted-foreground mt-1.5 text-xs tracking-tight">
          {{ metric.description }}
        </p>
      </div>

      <div
        v-if="propertyBreakdown?.length"
        class="bg-background -m-px flex grow flex-col gap-y-2 rounded-xl border px-3 py-3"
      >
        <div
          v-for="property in getPropertyBreakdownForMetric(metric.key)"
          :key="property.property_id"
          class="text-muted-foreground flex items-center justify-between gap-2 text-xs tracking-tight"
        >
          <div class="flex items-center gap-1.5">
            <Avatar
              v-if="property.project?.profile_image"
              :model="property.project"
              class="size-5 shrink-0"
            />
            <span class="line-clamp-1">
              {{ property.property_name }}
            </span>
          </div>
          <div class="text-foreground shrink-0 font-medium">
            <span v-if="metric.format === 'percent'">
              {{ formatPercent(property.value) }}
            </span>
            <span v-else-if="metric.format === 'duration'">
              {{ formatDuration(property.value) }}
            </span>
            <span v-else>
              {{ formatNumber(property.value) }}
            </span>
          </div>
        </div>
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
  propertyBreakdown: {
    type: Array,
    default: () => [],
  },
});

const isExpanded = ref(false);

const getPropertyBreakdownForMetric = (metricKey) => {
  if (!props.propertyBreakdown || props.propertyBreakdown.length === 0) return [];

  // Map metric keys to their property names
  const metricMap = {
    onlineUsers: "onlineUsers",
    activeUsers: "activeUsers",
    totalUsers: "totalUsers",
    newUsers: "newUsers",
    sessions: "sessions",
    screenPageViews: "screenPageViews",
    bounceRate: "bounceRate",
    averageSessionDuration: "averageSessionDuration",
  };

  const metricName = metricMap[metricKey];
  if (!metricName) return [];

  // Extract and sort property values for this metric
  return props.propertyBreakdown
    .map((property) => ({
      property_id: property.property_id,
      property_name: property.property_name,
      project: property.project,
      value: property.metrics?.[metricName] || 0,
    }))
    .filter((property) => property.value > 0)
    .sort((a, b) => b.value - a.value)
    .slice(0, 5); // Show top 5 properties
};

const formatNumber = (value) => {
  if (value === null || value === undefined) return "0";
  return new Intl.NumberFormat("en-US", { notation: "compact", maximumFractionDigits: 1 }).format(
    value
  );
};

const formatPercent = (value) => {
  if (value === null || value === undefined) return "0%";
  return `${(value * 100).toFixed(1)}%`;
};

const formatDuration = (seconds) => {
  if (!seconds) return "0s";
  const minutes = Math.floor(seconds / 60);
  const secs = Math.floor(seconds % 60);
  if (minutes === 0) return `${secs}s`;
  return `${minutes}m ${secs}s`;
};
</script>
