/**
 * Composable for managing data refresh signals across keepalive pages.
 *
 * This allows pages that are kept alive to know when they need to refresh
 * their data after mutations (create/edit/delete) happen on other pages.
 *
 * Usage:
 * - On list page: watch for refresh signals and call refresh() when signaled
 * - On create/edit/delete: call signalRefresh() after successful mutation
 */
export function useDataRefresh() {
  // Create a reactive state to track which data keys need refresh
  const refreshSignals = useState<Record<string, number>>("data-refresh-signals", () => ({}));

  /**
   * Signal that data for a specific key needs to be refreshed.
   * Call this after successful create/edit/delete operations.
   */
  function signalRefresh(key: string) {
    refreshSignals.value[key] = Date.now();
  }

  /**
   * Check if data needs refresh and get the last signal timestamp.
   * Returns the timestamp of the last refresh signal for this key.
   */
  function getRefreshSignal(key: string): number {
    return refreshSignals.value[key] || 0;
  }

  /**
   * Clear the refresh signal for a key after refreshing.
   * Call this after you've successfully refreshed the data.
   */
  function clearRefreshSignal(key: string) {
    if (refreshSignals.value[key]) {
      delete refreshSignals.value[key];
    }
  }

  return {
    signalRefresh,
    getRefreshSignal,
    clearRefreshSignal,
  };
}
