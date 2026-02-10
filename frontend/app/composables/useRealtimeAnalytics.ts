/**
 * Composable for fetching realtime analytics data.
 * Shows active users in the last 30 minutes.
 * Uses Pinia store for caching.
 */
export function useRealtimeAnalytics() {
  const client = useSanctumClient();
  const analyticsStore = useAnalyticsStore();

  // State
  const loading = ref(false);
  const error = ref<string | null>(null);

  // Get realtime data from store (access state directly, not via getter)
  const realtimeData = computed(() => analyticsStore.$state.realtimeData);

  // Auto-refresh interval (every 60 seconds to reduce API calls)
  let refreshInterval: NodeJS.Timeout | null = null;

  // AbortController for cancelling requests
  let abortController: AbortController | null = null;

  /**
   * Fetch realtime active users.
   * Backend will return cached data instantly if available,
   * and refresh in background if stale.
   */
  async function fetchRealtimeUsers(propertyIds?: string[], silent: boolean = false) {
    // Only show loading if not silent refresh and no data yet
    const showLoading = !realtimeData.value && !silent;

    // Abort any pending request
    if (abortController) {
      abortController.abort();
    }

    // Create new AbortController for this request
    abortController = new AbortController();

    try {
      if (showLoading) loading.value = true;
      error.value = null;

      // Build query string manually to ensure array is passed correctly for Laravel
      let url = '/api/google-analytics/realtime';
      if (propertyIds && propertyIds.length > 0) {
        const queryParams = propertyIds.map((id, index) => `property_ids[${index}]=${encodeURIComponent(id)}`).join('&');
        url += `?${queryParams}`;
      }

      const response = await client(url, {
        signal: abortController.signal,
      });

      // Handle response - client might return { data } or just the data directly
      const data = response?.data || response;

      // Store in Pinia
      analyticsStore.setRealtimeData(data);

      return data;
    } catch (err: any) {
      // Ignore abort errors - this is expected when navigating away or when a new request replaces the old one
      if (err.name === 'AbortError' || err.cause?.name === 'AbortError' || String(err.message).includes('aborted')) {
        return null;
      }

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
    // Only run on client-side
    if (typeof window === 'undefined') {
      return;
    }

    // Clear any existing interval
    stopAutoRefresh();

    // Fetch immediately (not silent - will show loading if no data)
    fetchRealtimeUsers(propertyIds, false).catch((err) => {
      console.error('Initial realtime fetch failed:', err);
      // Continue anyway - interval will retry
    });

    // Set up interval - use silent refresh to avoid loading state
    refreshInterval = setInterval(() => {
      fetchRealtimeUsers(propertyIds, true).catch(() => {
        // Silent failure for interval refreshes
      });
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
    realtimeData: readonly(realtimeData),
    loading: readonly(loading),
    error: readonly(error),

    // Actions
    fetchRealtimeUsers,
    startAutoRefresh,
    stopAutoRefresh,
  };
}
