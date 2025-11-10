<template>
  <NuxtLink
    :to="`/web-analytics/${property.property_id}`"
    class="border-border bg-card hover:bg-muted/50 group relative flex flex-col gap-4 rounded-lg border p-5 transition-all hover:shadow-md"
  >
    <!-- Header with Avatar, Name, and Property ID -->
    <div class="flex items-start gap-3">
      <Avatar v-if="property.project" :model="property.project" class="size-12" />
      <div class="min-w-0 flex-1">
        <h3
          class="text-foreground group-hover:text-primary truncate text-base font-semibold transition-colors"
        >
          {{ property.property_name }}
        </h3>
        <p class="text-muted-foreground truncate text-sm">ID: {{ property.property_id }}</p>
      </div>
    </div>

    <!-- Metrics Grid -->
    <div class="grid grid-cols-2 gap-3">
      <div class="space-y-1">
        <p class="text-muted-foreground text-xs font-medium">Online Now</p>
        <div class="flex items-baseline gap-2">
          <p class="text-foreground text-2xl font-bold">
            {{ formatNumber(property.metrics.onlineUsers) }}
          </p>

          <div
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
          </div>
        </div>
      </div>

      <!-- Active Users -->
      <div class="space-y-1">
        <p class="text-muted-foreground text-xs font-medium">Total Visitors</p>
        <p class="text-foreground text-2xl font-bold">
          {{ formatNumber(property.metrics?.activeUsers || 0) }}
        </p>
      </div>

      <!-- New Users -->
      <div class="space-y-1">
        <p class="text-muted-foreground text-xs font-medium">New Visitors</p>
        <p class="text-foreground text-2xl font-bold">
          {{ formatNumber(property.metrics?.newUsers || 0) }}
        </p>
      </div>

      <!-- Sessions -->
      <div class="space-y-1">
        <p class="text-muted-foreground text-xs font-medium">Sessions</p>
        <p class="text-foreground text-2xl font-bold">
          {{ formatNumber(property.metrics?.sessions || 0) }}
        </p>
      </div>

      <!-- Page Views -->
      <div class="space-y-1">
        <p class="text-muted-foreground text-xs font-medium">Page Views</p>
        <p class="text-foreground text-2xl font-bold">
          {{ formatNumber(property.metrics?.screenPageViews || 0) }}
        </p>
      </div>

      <!-- Bounce Rate -->
      <div class="space-y-1">
        <p class="text-muted-foreground text-xs font-medium">Bounce Rate</p>
        <p class="text-foreground text-2xl font-bold">
          {{ formatPercent(property.metrics?.bounceRate || 0) }}
        </p>
      </div>

      <!-- Average Duration -->
      <div class="space-y-1">
        <p class="text-muted-foreground text-xs font-medium">Average Duration</p>
        <p class="text-foreground text-2xl font-bold">
          {{ formatDuration(property.metrics?.averageSessionDuration || 0) }}
        </p>
      </div>

      <!-- Last Synced -->
      <div class="space-y-1">
        <p class="text-muted-foreground text-xs font-medium">Last Synced</p>
        <div class="flex items-center gap-2">
          <p v-if="property.cached_at" class="text-muted-foreground text-sm">
            {{ formatRelativeTime(property.cached_at) }}
          </p>
          <p v-else class="text-muted-foreground text-sm">-</p>
        </div>
      </div>
    </div>

    <!-- Arrow Icon -->
    <div class="absolute top-5 right-5 opacity-0 transition-opacity group-hover:opacity-100">
      <Icon name="hugeicons:arrow-right-01" class="text-primary size-5" />
    </div>
  </NuxtLink>
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
