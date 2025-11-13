<template>
  <div class="flex flex-col overflow-hidden rounded-xl border">
    <div class="p-3">
      <NuxtLink
        :to="`/web-analytics/${property.property_id}`"
        class="flex items-center justify-between gap-2 overflow-hidden"
      >
        <GaPropertyProfile :model="property" />
        <button
          class="bg-muted text-foreground hover:bg-border flex shrink-0 items-center justify-center gap-x-1.5 rounded-full p-2.5 text-sm font-medium tracking-tight transition active:scale-98"
        >
          <Icon name="hugeicons:arrow-right-02" class="size-4 shrink-0" />
        </button>
      </NuxtLink>
    </div>

    <ChartLineDefault
      v-if="chartData && chartData.length > 0"
      :data="chartData"
      :config="chartConfig"
      data-key="activeUsers"
      class="h-auto! pb-2.5"
    />

    <div class="bg-background -m-px grid grid-cols-2 gap-px gap-y-5 rounded-xl border px-4 py-5">
      <div v-for="metric in metrics" :key="metric.key" class="flex flex-col gap-y-1">
        <div class="flex items-center justify-between gap-x-2">
          <div class="inline-flex shrink-0 items-center gap-x-1.5">
            <p class="text-foreground/70 text-xs font-medium tracking-tight">
              {{ metric.label }}
            </p>

            <Icon
              :name="metric.icon"
              class="size-3.5 shrink-0"
              :class="[
                metric.key === 'onlineUsers' && metric.value
                  ? 'text-green-500 dark:text-green-500'
                  : 'text-muted-foreground',
                {
                  'size-4': metric.key === 'onlineUsers',
                },
              ]"
            />

            <!-- <div
              v-if="metric.key === 'onlineUsers'"
              class="text-success-foreground inline-flex shrink-0 items-center gap-1 text-[11px] font-medium"
            >
              <span class="relative flex size-1.5">
                <span
                  class="animate-ping-slow bg-success absolute inline-flex size-full rounded-full opacity-75"
                ></span>
                <span class="bg-success relative inline-flex size-full rounded-full"></span>
              </span>
            </div> -->
          </div>
        </div>

        <div class="flex items-center justify-between select-none">
          <div class="text-foreground shrink-0 font-semibold tracking-tighter">
            <span v-if="metric.format === 'percent'">
              {{ formatPercent(metric.value) }}
            </span>
            <span v-else-if="metric.format === 'duration'">
              {{ formatDuration(metric.value) }}
            </span>
            <span v-else @click="isExpanded = !isExpanded" class="cursor-pointer">
              {{
                new Intl.NumberFormat("en-US", {
                  notation: isExpanded ? "standard" : "compact",
                  maximumFractionDigits: 1,
                }).format(metric.value)
              }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- <div class="bg-border border-t">
      <div class="grid grid-cols-2 gap-px">
        <div
          v-for="metric in metrics"
          :key="metric.key"
          class="bg-background flex flex-col gap-y-1 p-3"
        >
          <div class="flex items-center justify-between gap-x-2">
            <div class="inline-flex shrink-0 items-center gap-x-1.5">
              <p class="text-foreground/70 text-xs font-medium tracking-tight">
                {{ metric.label }}
              </p>

              <div
                v-if="metric.key === 'onlineUsers'"
                class="text-success-foreground inline-flex shrink-0 items-center gap-1 text-[11px] font-medium"
              >
                <span class="relative flex size-1.5">
                  <span
                    class="animate-ping-slow bg-success absolute inline-flex size-full rounded-full opacity-75"
                  ></span>
                  <span class="bg-success relative inline-flex size-full rounded-full"></span>
                </span>
              </div>
            </div>

            <Icon
              :name="metric.icon"
              class="text-muted-foreground! size-4.5 shrink-0"
              :class="metric.iconClass"
            />
          </div>

          <div class="flex items-center justify-between select-none">
            <div class="text-foreground shrink-0 font-semibold tracking-tighter">
              <span v-if="metric.format === 'percent'">
                {{ formatPercent(metric.value) }}
              </span>
              <span v-else-if="metric.format === 'duration'">
                {{ formatDuration(metric.value) }}
              </span>
              <span v-else @click="isExpanded = !isExpanded" class="cursor-pointer">
                {{
                  new Intl.NumberFormat("en-US", {
                    notation: isExpanded ? "standard" : "compact",
                    maximumFractionDigits: 1,
                  }).format(metric.value)
                }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div> -->
  </div>
</template>

<script setup>
import ChartLineDefault from "@/components/chart/LineDefault.vue";

const props = defineProps({
  property: {
    type: Object,
    required: true,
  },
});

const isExpanded = ref(false);

const metrics = computed(() => [
  {
    key: "onlineUsers",
    label: "Online Now",
    value: props.property.metrics?.onlineUsers || 0,
    icon: "hugeicons:wifi-02",
    iconClass: "text-green-700 dark:text-green-400",
  },
  {
    key: "activeUsers",
    label: "Active Visitors",
    value: props.property.metrics?.activeUsers || 0,
    icon: "hugeicons:user-multiple-02",
    iconClass: "text-blue-700 dark:text-blue-400",
  },
  {
    key: "newUsers",
    label: "New Visitors",
    value: props.property.metrics?.newUsers || 0,
    icon: "hugeicons:user-add-02",
    iconClass: "text-sky-700 dark:text-sky-400",
  },
  {
    key: "totalUsers",
    label: "Total Visitors",
    value: props.property.metrics?.totalUsers || 0,
    icon: "hugeicons:user-group",
    iconClass: "text-purple-700 dark:text-purple-400",
  },
  {
    key: "sessions",
    label: "Total Sessions",
    value: props.property.metrics?.sessions || 0,
    icon: "hugeicons:cursor-pointer-02",
    iconClass: "text-indigo-700 dark:text-indigo-400",
  },
  {
    key: "screenPageViews",
    label: "Page Views",
    value: props.property.metrics?.screenPageViews || 0,
    icon: "hugeicons:view",
    iconClass: "text-pink-700 dark:text-pink-400",
  },
  {
    key: "bounceRate",
    label: "Bounce Rate",
    value: props.property.metrics?.bounceRate || 0,
    format: "percent",
    icon: "hugeicons:undo-02",
    iconClass: "text-red-700 dark:text-red-400",
  },
  {
    key: "averageSessionDuration",
    label: "Average Duration",
    value: props.property.metrics?.averageSessionDuration || 0,
    format: "duration",
    icon: "hugeicons:time-quarter-02",
    iconClass: "text-yellow-700 dark:text-yellow-400",
  },
]);

const formatPercent = (value) => {
  if (value == null) return "0%";
  return `${(value * 100).toFixed(1)}%`;
};

const formatDuration = (seconds) => {
  if (!seconds) return "0s";
  const minutes = Math.floor(seconds / 60);
  const secs = Math.floor(seconds % 60);
  return minutes === 0 ? `${secs}s` : `${minutes}m ${secs}s`;
};

// Chart data: Daily activeUsers for the current period
const chartData = computed(() => {
  if (!props.property.rows || !Array.isArray(props.property.rows)) {
    return [];
  }

  return props.property.rows
    .map((item) => ({
      date: new Date(item.date),
      activeUsers: item.activeUsers || 0,
    }))
    .sort((a, b) => a.date - b.date);
});

const chartConfig = {
  activeUsers: {
    label: "Active Visitors",
    color: "var(--chart-1)",
  },
};
</script>
