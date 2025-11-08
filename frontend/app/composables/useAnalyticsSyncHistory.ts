/**
 * Composable for managing analytics sync history.
 * Handles fetching and auto-refreshing sync logs and stats.
 */
export function useAnalyticsSyncHistory(hoursFilter: Ref<number> = ref(24)) {
  const client = useSanctumClient();

  // State
  const syncLogs = ref<any[]>([]);
  const syncStats = ref<any>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);

  // Auto-refresh management
  let refreshTimeout: NodeJS.Timeout | null = null;

  /**
   * Fetch sync history logs and stats.
   */
  async function fetchSyncHistory() {
    loading.value = true;
    error.value = null;

    // Clear any existing timeout
    if (refreshTimeout) {
      clearTimeout(refreshTimeout);
      refreshTimeout = null;
    }

    try {
      // Fetch logs and stats in parallel
      const [logsResponse, statsResponse] = await Promise.all([
        client(`/api/google-analytics/sync-logs`, {
          params: {
            hours: hoursFilter.value,
            limit: 50,
          },
        }),
        client(`/api/google-analytics/sync-logs/stats`, {
          params: {
            hours: hoursFilter.value,
          },
        }),
      ]);

      syncLogs.value = logsResponse.data.logs || [];
      syncStats.value = statsResponse.data;

      // Auto-refresh if there are in-progress syncs
      const hasInProgress = syncLogs.value.some((log) => log.status === "started");
      if (hasInProgress) {
        refreshTimeout = setTimeout(() => fetchSyncHistory(), 10000); // 10s
      }
    } catch (err: any) {
      error.value = err.data?.message || err.message || "Failed to load sync history";
      console.error("Error fetching sync history:", err);
    } finally {
      loading.value = false;
    }
  }

  /**
   * Start auto-refresh polling.
   */
  function startAutoRefresh(intervalSeconds: number = 5) {
    const pollInterval = setInterval(() => {
      fetchSyncHistory();
    }, intervalSeconds * 1000);

    // Stop after 30 seconds
    setTimeout(() => {
      clearInterval(pollInterval);
    }, 30000);
  }

  /**
   * Stop auto-refresh.
   */
  function stopAutoRefresh() {
    if (refreshTimeout) {
      clearTimeout(refreshTimeout);
      refreshTimeout = null;
    }
  }

  // Cleanup on unmount
  onUnmounted(() => {
    stopAutoRefresh();
  });

  return {
    // State
    syncLogs: readonly(syncLogs),
    syncStats: readonly(syncStats),
    loading: readonly(loading),
    error: readonly(error),

    // Actions
    fetchSyncHistory,
    startAutoRefresh,
    stopAutoRefresh,
  };
}
