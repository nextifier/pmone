/**
 * Composable for managing analytics sync operations.
 * Provides reactive state and methods for triggering sync and monitoring progress.
 */
export function useAnalyticsSync() {
  const { $dayjs } = useNuxtApp();
  const client = useSanctumClient();

  // State
  const syncingNow = ref(false);
  const syncError = ref<string | null>(null);
  const lastSyncResult = ref<any>(null);

  // AbortController for cancelling requests
  let abortController: AbortController | null = null;

  /**
   * Trigger manual sync now.
   */
  async function triggerSync(days: number = 30) {
    // Abort any pending request
    if (abortController) {
      abortController.abort();
    }

    // Create new AbortController for this request
    abortController = new AbortController();

    syncingNow.value = true;
    syncError.value = null;

    try {
      const response = await client(`/api/google-analytics/aggregate/sync-now`, {
        method: "POST",
        body: { days },
        signal: abortController.signal,
      });

      // Handle response - client might return { data } or just data directly
      const data = response?.data || response;

      lastSyncResult.value = data;
      return data;
    } catch (err: any) {
      // Ignore abort errors - this is expected when navigating away
      if (err.name === 'AbortError') {
        return null;
      }

      const errorMessage =
        err.data?.message || err.message || "Failed to trigger sync";
      syncError.value = errorMessage;

      // Handle rate limiting
      if (err.status === 429) {
        const retryAfterMinutes = err.data?.retry_after_minutes || 60;
        syncError.value = `Too many sync attempts. Please try again in ${retryAfterMinutes} minutes.`;
      }

      throw err;
    } finally {
      syncingNow.value = false;
    }
  }

  /**
   * Reset error state.
   */
  function resetError() {
    syncError.value = null;
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
    cancelPendingRequests();
  });

  return {
    // State
    syncingNow: readonly(syncingNow),
    syncError: readonly(syncError),
    lastSyncResult: readonly(lastSyncResult),

    // Actions
    triggerSync,
    resetError,
    cancelPendingRequests,
  };
}
