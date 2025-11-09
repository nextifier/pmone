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

  /**
   * Trigger manual sync now.
   */
  async function triggerSync(days: number = 30) {
    syncingNow.value = true;
    syncError.value = null;

    try {
      const response = await client(`/api/google-analytics/aggregate/sync-now`, {
        method: "POST",
        body: { days },
      });

      // Handle response - client might return { data } or just data directly
      const data = response?.data || response;

      lastSyncResult.value = data;
      return data;
    } catch (err: any) {
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

  return {
    // State
    syncingNow: readonly(syncingNow),
    syncError: readonly(syncError),
    lastSyncResult: readonly(lastSyncResult),

    // Actions
    triggerSync,
    resetError,
  };
}
