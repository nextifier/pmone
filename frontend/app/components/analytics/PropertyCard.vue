<template>
  <div class="frame">
    <div class="frame-header bg-background !px-3">
      <NuxtLink
        :to="`/web-analytics/${property.property_id}`"
        class="flex items-center justify-between gap-2 overflow-hidden"
      >
        <GaPropertyProfile :model="property" />
        <button
          class="bg-muted text-foreground hover:bg-border flex shrink-0 items-center justify-center gap-x-1.5 rounded-full p-2.5 text-sm font-medium tracking-tight transition active:scale-98"
        >
          <!-- <span>View Analytics</span> -->
          <Icon name="hugeicons:arrow-right-02" class="size-4 shrink-0" />
        </button>
      </NuxtLink>
    </div>

    <AnalyticsPropertyChartArea
      v-if="property.rows && property.rows.length > 0"
      :rows="property.rows"
      :property-name="property.property_id"
    />

    <div class="frame-panel !p-3">
      <div
        class="*:bg-muted/50 grid grid-cols-2 gap-2 *:flex *:flex-col *:gap-y-1 *:rounded-lg *:p-3 *:tracking-tight"
      >
        <div class="space-y-1">
          <p class="text-muted-foreground text-xs font-medium">Online Now</p>
          <div class="flex items-baseline gap-2">
            <NumberFlow
              class="text-foreground text-2xl font-bold tracking-tighter"
              :value="property.metrics?.onlineUsers || 0"
              :format="{ notation: 'compact' }"
            />

            <!-- <div
            v-if="property.metrics?.onlineUsers > 0"
            class="inline-flex items-center gap-1.5 rounded-full bg-green-500/20 px-2.5 py-1 text-xs font-medium text-green-700 dark:text-green-300"
          >
            <span class="relative flex size-2">
              <span
                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-500 opacity-75"
              ></span>
              <span class="relative inline-flex size-2 rounded-full bg-green-500"></span>
            </span>
            LIVE
          </div> -->
          </div>
        </div>

        <!-- Active Users -->
        <div class="space-y-1">
          <p class="text-muted-foreground text-xs font-medium">Total Visitors</p>
          <NumberFlow
            class="text-foreground text-2xl font-bold tracking-tighter"
            :value="property.metrics?.activeUsers || 0"
            :format="{ notation: 'compact' }"
          />
        </div>

        <!-- New Users -->
        <div class="space-y-1">
          <p class="text-muted-foreground text-xs font-medium">New Visitors</p>
          <NumberFlow
            class="text-foreground text-2xl font-bold tracking-tighter"
            :value="property.metrics?.newUsers || 0"
            :format="{ notation: 'compact' }"
          />
        </div>

        <!-- Sessions -->
        <div class="space-y-1">
          <p class="text-muted-foreground text-xs font-medium">Sessions</p>
          <NumberFlow
            class="text-foreground text-2xl font-bold tracking-tighter"
            :value="property.metrics?.sessions || 0"
            :format="{ notation: 'compact' }"
          />
        </div>

        <!-- Page Views -->
        <div class="space-y-1">
          <p class="text-muted-foreground text-xs font-medium">Page Views</p>
          <NumberFlow
            class="text-foreground text-2xl font-bold tracking-tighter"
            :value="property.metrics?.screenPageViews || 0"
            :format="{ notation: 'compact' }"
          />
        </div>

        <!-- Bounce Rate -->
        <div class="space-y-1">
          <p class="text-muted-foreground text-xs font-medium">Bounce Rate</p>
          <NumberFlow
            class="text-foreground text-2xl font-bold tracking-tighter"
            :value="(property.metrics?.bounceRate || 0) * 100"
            :format="{ notation: 'standard', minimumFractionDigits: 1, maximumFractionDigits: 1 }"
            suffix="%"
          />
        </div>

        <!-- Average Duration -->
        <div class="space-y-1">
          <p class="text-muted-foreground text-xs font-medium">Average Duration</p>
          <NumberFlow
            class="text-foreground text-2xl font-bold tracking-tighter"
            :value="property.metrics?.averageSessionDuration || 0"
            :format="{ notation: 'standard', minimumFractionDigits: 0, maximumFractionDigits: 0 }"
            suffix="s"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const { $dayjs } = useNuxtApp();

const props = defineProps({
  property: {
    type: Object,
    required: true,
  },
});

const formatNumber = (value) => {
  if (value === null || value === undefined) return "0";
  return new Intl.NumberFormat().format(Math.round(value));
};

const formatPercent = (value) => {
  if (value === null || value === undefined) return "0%";
  return `${(value * 100).toFixed(1)}%`;
};

const formatDuration = (seconds) => {
  if (!seconds) return "0m 0s";
  const minutes = Math.floor(seconds / 60);
  const secs = Math.floor(seconds % 60);
  return `${minutes}m ${secs}s`;
};

const formatRelativeTime = (dateString) => $dayjs(dateString).fromNow();
</script>
