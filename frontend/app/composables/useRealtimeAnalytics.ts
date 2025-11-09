/**
 * Composable for fetching realtime analytics data.
 * Shows active users in the last 30 minutes.
 */
export function useRealtimeAnalytics() {
  const client = useSanctumClient();

  // State
  const realtimeData = ref<any>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);

  // Auto-refresh interval (every 30 seconds)
  let refreshInterval: NodeJS.Timeout | null = null;

  /**
   * Fetch realtime active users.
   */
  async function fetchRealtimeUsers(propertyIds?: string[]) {
    try {
      loading.value = true;
      error.value = null;

      const params = propertyIds ? { property_ids: propertyIds } : {};

      const response = await client('/api/google-analytics/realtime', {
        params,
      });

      // Handle response - client might return { data } or just the data directly
      const data = response?.data || response;

      realtimeData.value = data;

      return data;
    } catch (err: any) {
      console.error('Error fetching realtime analytics:', err);
      error.value =
        err.data?.message || err.message || 'Failed to load realtime data';
      throw err;
    } finally {
      loading.value = false;
    }
  }

  /**
   * Start auto-refresh (every 30 seconds).
   */
  function startAutoRefresh(propertyIds?: string[]) {
    // Clear any existing interval
    stopAutoRefresh();

    // Fetch immediately
    fetchRealtimeUsers(propertyIds);

    // Set up interval
    refreshInterval = setInterval(() => {
      fetchRealtimeUsers(propertyIds);
    }, 30000); // 30 seconds
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
