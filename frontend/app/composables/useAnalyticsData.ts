/**
 * Composable for managing analytics data fetching and caching.
 * Provides reactive state and auto-refresh capabilities.
 */
export function useAnalyticsData(initialDays: number = 30) {
  const client = useSanctumClient();

  // State
  const aggregateData = ref<any>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);
  const selectedDays = ref(initialDays);

  // Auto-refresh management
  let autoRefreshTimeout: NodeJS.Timeout | null = null;

  // Computed
  const cacheInfo = computed(() => aggregateData.value?.cache_info || null);
  const isUpdating = computed(() => cacheInfo.value?.is_updating || false);
  const cacheAge = computed(() => cacheInfo.value?.cache_age_minutes || null);
  const lastUpdated = computed(() => cacheInfo.value?.last_updated || null);

  /**
   * Fetch analytics data.
   */
  async function fetchAnalytics(silent: boolean = false) {
    // Clear any existing auto-refresh
    if (autoRefreshTimeout) {
      clearTimeout(autoRefreshTimeout);
      autoRefreshTimeout = null;
    }

    // Only show loading if not silent refresh and no data
    const showLoading = !aggregateData.value && !silent;
    if (showLoading) loading.value = true;
    error.value = null;

    try {
      const { data } = await client(`/api/google-analytics/aggregate`, {
        params: { days: selectedDays.value },
      });

      aggregateData.value = data;

      // Auto-refresh logic based on cache state
      if (
        data.cache_info?.initial_load ||
        (data.cache_info?.is_updating && data.cache_info?.properties_count === 0)
      ) {
        // Initial load with empty data - refresh quickly
        autoRefreshTimeout = setTimeout(() => fetchAnalytics(true), 5000);
      } else if (data.cache_info?.is_updating) {
        // Has data but updating in background - refresh slower
        autoRefreshTimeout = setTimeout(() => fetchAnalytics(true), 15000);
      }

      return data;
    } catch (err: any) {
      console.error("Error fetching analytics:", err);

      // Only show error if we don't have cached data to fall back on
      if (!aggregateData.value) {
        error.value =
          err.data?.message || err.message || "Failed to load analytics data";
      }

      throw err;
    } finally {
      if (showLoading) loading.value = false;
    }
  }

  /**
   * Change date range and refresh data.
   */
  async function changeDateRange(days: number) {
    selectedDays.value = days;
    await fetchAnalytics();
  }

  /**
   * Force refresh data.
   */
  async function refreshData() {
    await fetchAnalytics();
  }

  /**
   * Stop auto-refresh.
   */
  function stopAutoRefresh() {
    if (autoRefreshTimeout) {
      clearTimeout(autoRefreshTimeout);
      autoRefreshTimeout = null;
    }
  }

  // Cleanup on unmount
  onUnmounted(() => {
    stopAutoRefresh();
  });

  return {
    // State
    aggregateData: readonly(aggregateData),
    loading: readonly(loading),
    error: readonly(error),
    selectedDays: readonly(selectedDays),

    // Computed
    cacheInfo,
    isUpdating,
    cacheAge,
    lastUpdated,

    // Actions
    fetchAnalytics,
    changeDateRange,
    refreshData,
    stopAutoRefresh,
  };
}
