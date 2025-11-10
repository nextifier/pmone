<script setup lang="ts">
const { fetchMetricsDashboard, fetchSystemHealth } = useComparisonData();

const metrics = ref<any>(null);
const systemHealth = ref<any>(null);
const loading = ref(true);
const error = ref<string | null>(null);

const loadMetrics = async () => {
  loading.value = true;
  error.value = null;

  try {
    const [metricsData, healthData] = await Promise.all([
      fetchMetricsDashboard(),
      fetchSystemHealth(),
    ]);

    metrics.value = metricsData;
    systemHealth.value = healthData;
  } catch (err: any) {
    error.value = err.message || "Failed to load metrics";
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadMetrics();
});

const getStatusColor = (status: string) => {
  if (status === "healthy") return "bg-green-500";
  if (status === "degraded") return "bg-yellow-500";
  return "bg-red-500";
};

const getStatusText = (status: string) => {
  if (status === "healthy") return "Healthy";
  if (status === "degraded") return "Degraded";
  return "Unhealthy";
};
</script>

<template>
  <div class="flex flex-col gap-6">
    <!-- System Health Card -->
    <div class="border-border bg-card rounded-lg border p-6">
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-semibold">System Health</h3>
        <button
          @click="loadMetrics"
          class="hover:bg-muted rounded-md p-2 transition-colors"
          :disabled="loading"
        >
          <Icon
            name="hugeicons:refresh"
            class="size-4"
            :class="{ 'animate-spin': loading }"
          />
        </button>
      </div>

      <div v-if="loading && !systemHealth" class="flex items-center justify-center py-8">
        <Spinner class="size-6" />
      </div>

      <div v-else-if="error" class="text-destructive py-4 text-center text-sm">
        {{ error }}
      </div>

      <div v-else-if="systemHealth" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <!-- System Status -->
        <div class="flex items-center gap-3">
          <div
            class="size-3 shrink-0 rounded-full"
            :class="getStatusColor(systemHealth.status)"
          />
          <div>
            <div class="text-muted-foreground text-xs">Status</div>
            <div class="font-semibold">{{ getStatusText(systemHealth.status) }}</div>
          </div>
        </div>

        <!-- API Success Rate -->
        <div>
          <div class="text-muted-foreground text-xs">API Success Rate</div>
          <div class="font-semibold">
            {{ systemHealth.api_health?.success_rate?.toFixed(1) }}%
          </div>
        </div>

        <!-- Cache Hit Rate -->
        <div>
          <div class="text-muted-foreground text-xs">Cache Hit Rate</div>
          <div class="font-semibold">
            {{ systemHealth.cache_health?.hit_rate?.toFixed(1) }}%
          </div>
        </div>

        <!-- Error Count -->
        <div>
          <div class="text-muted-foreground text-xs">Errors (24h)</div>
          <div
            class="font-semibold"
            :class="
              systemHealth.error_count_24h > 0
                ? 'text-red-600 dark:text-red-400'
                : 'text-green-600 dark:text-green-400'
            "
          >
            {{ systemHealth.error_count_24h }}
          </div>
        </div>
      </div>
    </div>

    <!-- Performance Metrics -->
    <div v-if="metrics" class="grid gap-6 sm:grid-cols-2">
      <!-- API Stats -->
      <div class="border-border bg-card rounded-lg border p-6">
        <h4 class="mb-4 font-semibold">API Performance (24h)</h4>
        <div class="grid gap-3">
          <div class="flex items-center justify-between">
            <span class="text-muted-foreground text-sm">Total Calls</span>
            <span class="font-medium">{{ metrics.api_stats?.total_calls || 0 }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-muted-foreground text-sm">Success Rate</span>
            <span class="font-medium">{{ metrics.api_stats?.success_rate?.toFixed(1) }}%</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-muted-foreground text-sm">Avg Duration</span>
            <span class="font-medium">{{ metrics.api_stats?.avg_duration_ms?.toFixed(0) }}ms</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-muted-foreground text-sm">Max Duration</span>
            <span class="font-medium">{{ metrics.api_stats?.max_duration_ms || 0 }}ms</span>
          </div>
        </div>
      </div>

      <!-- Cache Stats -->
      <div class="border-border bg-card rounded-lg border p-6">
        <h4 class="mb-4 font-semibold">Cache Performance (24h)</h4>
        <div class="grid gap-3">
          <div class="flex items-center justify-between">
            <span class="text-muted-foreground text-sm">Total Requests</span>
            <span class="font-medium">{{ metrics.cache_stats?.total_requests || 0 }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-muted-foreground text-sm">Cache Hits</span>
            <span class="font-medium text-green-600 dark:text-green-400">{{
              metrics.cache_stats?.cache_hits || 0
            }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-muted-foreground text-sm">Cache Misses</span>
            <span class="font-medium text-red-600 dark:text-red-400">{{
              metrics.cache_stats?.cache_misses || 0
            }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-muted-foreground text-sm">Hit Rate</span>
            <span class="font-medium">{{ metrics.cache_stats?.hit_rate?.toFixed(1) }}%</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
