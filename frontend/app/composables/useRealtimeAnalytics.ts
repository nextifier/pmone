/**
 * Composable for fetching realtime analytics data.
 * Shows active users in the last 30 minutes.
 * Uses cache fallback for instant display with stale data.
 */
export function useRealtimeAnalytics() {
  const client = useSanctumClient();

  // State
  const realtimeData = ref<any>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);

  // Auto-refresh interval (every 60 seconds to reduce API calls)
  let refreshInterval: NodeJS.Timeout | null = null;

  /**
   * Fetch realtime active users.
   * Backend will return cached data instantly if available,
   * and refresh in background if stale.
   */
  async function fetchRealtimeUsers(propertyIds?: string[], silent: boolean = false) {
    // Only show loading if not silent refresh and no data yet
    const showLoading = !realtimeData.value && !silent;

    try {
      if (showLoading) loading.value = true;
      error.value = null;

      const params = propertyIds ? { property_ids: propertyIds } : {};

      const response = await client('/api/google-analytics/realtime', {
        params,
      });

      // Handle response - client might return { data } or just the data directly
      const data = response?.data || response;

      // Always update with the response (could be fresh or from cache)
      realtimeData.value = data;

      return data;
    } catch (err: any) {
      console.error('Error fetching realtime analytics:', err);

      // Only show error if we don't have fallback data
      if (!realtimeData.value) {
        error.value =
          err.data?.message || err.message || 'Failed to load realtime data';
      }

      throw err;
    } finally {
      if (showLoading) loading.value = false;
    }
  }

  /**
   * Start auto-refresh (every 60 seconds).
   */
  function startAutoRefresh(propertyIds?: string[]) {
    // Clear any existing interval
    stopAutoRefresh();

    // Fetch immediately (not silent - will show loading if no data)
    fetchRealtimeUsers(propertyIds, false);

    // Set up interval - use silent refresh to avoid loading state
    refreshInterval = setInterval(() => {
      fetchRealtimeUsers(propertyIds, true);
    }, 60000); // 60 seconds (reduced from 30s to save API quota)
  }

  /**
   * Stop auto-refresh.
   */
  function stopAutoRefresh() {
    if (refreshInterval) {
      clearInterval(refreshInterval);
      refreshInterval = null;
    }
  }

  // Cleanup on unmount
  onUnmounted(() => {
    stopAutoRefresh();
  });

  return {
    // State
    realtimeData: readonly(realtimeData),
    loading: readonly(loading),
    error: readonly(error),

    // Actions
    fetchRealtimeUsers,
    startAutoRefresh,
    stopAutoRefresh,
  };
}
