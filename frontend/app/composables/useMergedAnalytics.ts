/**
 * Composable that merges aggregate analytics data with realtime data.
 * Adds 'onlineUsers' field to totals and property_breakdown from realtime data.
 */
export function useMergedAnalytics(initialPeriod: string | number = 30) {
  const { aggregateData, loading, error, cacheInfo, fetchAnalytics, changeDateRange, refreshData } =
    useAnalyticsData(initialPeriod);
  const { realtimeData, startAutoRefresh: startRealtimeRefresh } = useRealtimeAnalytics();

  const mergedData = computed(() => {
    if (!aggregateData.value) return null;

    const data = { ...aggregateData.value };
    const realtime = realtimeData.value;

    if (data.totals) {
      data.totals = {
        ...data.totals,
        onlineUsers: realtime?.total_active_users || 0,
      };
    }

    if (data.property_breakdown && realtime?.property_breakdown) {
      const realtimeMap = new Map();
      realtime.property_breakdown.forEach((rt: any) => {
        realtimeMap.set(rt.property_id, rt.active_users);
      });

      data.property_breakdown = data.property_breakdown.map((property: any) => ({
        ...property,
        metrics: {
          ...property.metrics,
          onlineUsers: realtimeMap.get(property.property_id) || 0,
        },
      }));
    }

    return data;
  });

  return {
    aggregateData: mergedData,
    realtimeData: readonly(realtimeData),
    loading: readonly(loading),
    error: readonly(error),
    cacheInfo,
    fetchAnalytics,
    changeDateRange,
    refreshData,
    startRealtimeRefresh,
  };
}
