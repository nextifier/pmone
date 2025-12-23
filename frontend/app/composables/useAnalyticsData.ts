/**
 * Composable for managing analytics data fetching and caching.
 * Provides reactive state and auto-refresh capabilities.
 * Uses Pinia store for cross-page caching.
 *
 * CACHE-FIRST STRATEGY:
 * - Always show cached data immediately if available
 * - Fetch fresh data in background, don't block UI
 * - Never show loading spinner if cached data exists
 * - Never show error if cached data exists (silent background retry)
 *
 * @param initialPeriod - Initial period to fetch (days number or named period)
 * @param withComparison - Whether to include comparison data from previous period
 */
export function useAnalyticsData(initialPeriod: string | number = 30, withComparison: boolean = true) {
  const client = useSanctumClient();
  const analyticsStore = useAnalyticsStore();

  // State - Start with false, only show loading if no cached data
  const loading = ref(false);
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
   * Fetch analytics data with cache-first strategy.
   *
   * BEHAVIOR:
   * 1. If fresh cache exists -> return immediately, no loading
   * 2. If stale cache exists -> show data immediately, fetch in background (silent)
   * 3. If no cache -> show loading, fetch with timeout
   * 4. On error with cache -> ignore error, keep showing cached data
   * 5. On error without cache -> show error message
   */
  async function fetchAnalytics(silent: boolean = false, skipAutoRefresh: boolean = false) {
    const period = String(selectedPeriod.value);
    const hasCachedData = !!analyticsStore.getAggregate(period);

    // Check if we have fresh data in store - return immediately
    if (analyticsStore.isAggregateFresh(period)) {
      loading.value = false;
      error.value = null;
      return analyticsStore.getAggregate(period);
    }

    // If we have stale cached data, return it immediately and refresh in background
    if (hasCachedData && !silent) {
      // Don't show loading spinner - we have data to show
      loading.value = false;
      error.value = null;

      // Fetch fresh data silently in background
      fetchAnalytics(true, skipAutoRefresh).catch(() => {
        // Silently ignore background fetch errors - we have cached data
      });

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

    // Show loading only if no cached data and not silent
    if (!silent && !hasCachedData) {
      loading.value = true;
    }
    error.value = null;

    try {
      const params = {
        ...getPeriodParams(selectedPeriod.value),
        with_comparison: withComparison,
      };

      // Add timeout to prevent hanging requests - 120 seconds (matches backend timeout)
      // First-time aggregation can take 60-90 seconds when processing all GA properties
      const timeoutId = setTimeout(() => {
        if (abortController) {
          abortController.abort();
        }
      }, 120000);

      const response = await client(`/api/google-analytics/aggregate`, {
        params,
        signal: abortController.signal,
      });

      clearTimeout(timeoutId);

      // Handle response - client might return { data } or just the data directly
      const data = response?.data || response;

      // Validate response has expected structure
      if (!data || typeof data !== 'object') {
        throw new Error('Invalid response format from API');
      }

      // Store in Pinia
      analyticsStore.setAggregate(period, data);

      // Clear any previous error since we got fresh data
      error.value = null;

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
      // Ignore abort errors - this is expected when navigating away or timeout
      if (err.name === 'AbortError') {
        // If we have cached data, return it silently
        if (hasCachedData) {
          return analyticsStore.getAggregate(period);
        }
        // Otherwise show timeout error
        if (!silent) {
          error.value = "Request timed out after 2 minutes. Please try again.";
        }
        return null;
      }

      // If we have cached data, ignore the error and return cached data
      if (hasCachedData) {
        console.warn("Background fetch failed, using cached data:", err.message);
        return analyticsStore.getAggregate(period);
      }

      console.error("Error fetching analytics:", err);

      // Only show error if we don't have cached data to fall back on
      if (err.status === 429 || err.statusCode === 429) {
        error.value = "Too many requests. Please wait a moment and try again.";
      } else {
        error.value =
          err.data?.message || err.message || "Failed to load analytics data";
      }

      // Don't throw - just return null and let error state be shown
      return null;
    } finally {
      if (!silent) {
        loading.value = false;
      }
    }
  }

  /**
   * Change date range and refresh data.
   * Shows cached data immediately if available, fetches fresh in background.
   */
  async function changeDateRange(period: string | number) {
    selectedPeriod.value = period;

    // Check if we have any cached data for the new period
    const hasCachedData = !!analyticsStore.getAggregate(String(period));

    if (hasCachedData) {
      // We have cached data - show it immediately, fetch fresh in background
      loading.value = false;
      error.value = null;

      // Fetch fresh data silently in background
      fetchAnalytics(true, true).catch(() => {
        // Silently ignore background fetch errors
      });
    } else {
      // No cached data - need to fetch with loading state
      await fetchAnalytics(false, true);
    }
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
