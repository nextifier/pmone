/**
 * Composable for managing analytics data fetching and caching.
 * Provides reactive state and auto-refresh capabilities.
 * Uses Pinia store for cross-page caching.
 *
 * @param initialPeriod - Initial period to fetch (days number or named period)
 * @param withComparison - Whether to include comparison data from previous period
 */
export function useAnalyticsData(initialPeriod: string | number = 30, withComparison: boolean = true) {
  const client = useSanctumClient();
  const analyticsStore = useAnalyticsStore();

  // State
  const loading = ref(true); // Start with true for SSR hydration
  const error = ref<string | null>(null);
  const selectedPeriod = ref(initialPeriod);

  // Auto-refresh management
  let autoRefreshTimeout: NodeJS.Timeout | null = null;

  // AbortController for cancelling requests
  let abortController: AbortController | null = null;

  // Get aggregate data from store
  const aggregateData = computed(() => {
    return analyticsStore.getAggregate(String(selectedPeriod.value));
  });

  // Computed
  const cacheInfo = computed(() => {
    if (!aggregateData.value) return null;
    return aggregateData.value.cache_info || null;
  });
  const isUpdating = computed(() => {
    if (!aggregateData.value || !cacheInfo.value) return false;
    return cacheInfo.value.is_updating || false;
  });
  const cacheAge = computed(() => {
    if (!aggregateData.value || !cacheInfo.value) return null;
    return cacheInfo.value.cache_age_minutes || null;
  });
  const lastUpdated = computed(() => {
    if (!aggregateData.value || !cacheInfo.value) return null;
    return cacheInfo.value.last_updated || null;
  });

  /**
   * Convert period to API parameters.
   *
   * For named periods (today, yesterday, etc.), we pass the period name to backend
   * so backend can calculate dates using server timezone (Asia/Jakarta).
   * This prevents timezone mismatch between client and server.
   */
  function getPeriodParams(period: string | number) {
    // If numeric, use days parameter
    if (typeof period === 'number' || !isNaN(Number(period))) {
      return { days: Number(period) };
    }

    // For named periods, pass period name to backend
    // Backend will calculate dates using server timezone (Asia/Jakarta)
    return { period: period };
  }

  /**
   * Fetch analytics data.
   * First checks store cache, then fetches if needed.
   */
  async function fetchAnalytics(silent: boolean = false, skipAutoRefresh: boolean = false) {
    const period = String(selectedPeriod.value);

    // Check if we have fresh data in store
    if (analyticsStore.isAggregateFresh(period)) {
      loading.value = false;
      return analyticsStore.getAggregate(period);
    }

    // Abort any pending request
    if (abortController) {
      abortController.abort();
    }

    // Create new AbortController for this request
    abortController = new AbortController();

    // Clear any existing auto-refresh
    if (autoRefreshTimeout) {
      clearTimeout(autoRefreshTimeout);
      autoRefreshTimeout = null;
    }

    // Show loading unless it's a silent background refresh
    if (!silent) {
      loading.value = true;
    }
    error.value = null;

    try {
      const params = {
        ...getPeriodParams(selectedPeriod.value),
        with_comparison: withComparison,
      };
      const response = await client(`/api/google-analytics/aggregate`, {
        params,
        signal: abortController.signal,
      });

      // Handle response - client might return { data } or just the data directly
      const data = response?.data || response;

      // Validate response has expected structure
      if (!data || typeof data !== 'object') {
        throw new Error('Invalid response format from API');
      }

      // Store in Pinia
      analyticsStore.setAggregate(period, data);

      // Only set up auto-refresh if not skipping (i.e., not a manual period change)
      // and only on client-side
      if (!skipAutoRefresh && typeof window !== 'undefined') {
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
      }

      return data;
    } catch (err: any) {
      // Ignore abort errors - this is expected when navigating away
      if (err.name === 'AbortError') {
        // Silent return - don't log or throw for aborted requests
        return null;
      }

      console.error("Error fetching analytics:", err);

      // Handle rate limit errors specifically
      if (err.status === 429 || err.statusCode === 429) {
        error.value = "Too many requests. Please wait a moment and try again.";
      } else if (!aggregateData.value) {
        // Only show error if we don't have cached data to fall back on
        error.value =
          err.data?.message || err.message || "Failed to load analytics data";
      }

      throw err;
    } finally {
      if (!silent) {
        loading.value = false;
      }
    }
  }

  /**
   * Change date range and refresh data.
   */
  async function changeDateRange(period: string | number) {
    selectedPeriod.value = period;
    // Fetch new data without auto-refresh (user initiated action)
    // Don't clear aggregateData to null - let loading state handle UX
    await fetchAnalytics(false, true);
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

  /**
   * Cancel any pending requests.
   */
  function cancelPendingRequests() {
    if (abortController) {
      abortController.abort();
      abortController = null;
    }
  }

  // Cleanup on unmount
  onUnmounted(() => {
    stopAutoRefresh();
    cancelPendingRequests();
  });

  return {
    // State
    aggregateData: readonly(aggregateData),
    loading: readonly(loading),
    error: readonly(error),
    selectedPeriod: readonly(selectedPeriod),

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
    cancelPendingRequests,
  };
}
