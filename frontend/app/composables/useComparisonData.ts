/**
 * Composable for fetching comparison data from the analytics API.
 * Provides methods to compare current period with previous period.
 */
export function useComparisonData() {
  const client = useSanctumClient();
  const loading = ref(false);
  const error = ref<string | null>(null);
  const comparisonData = ref<any>(null);

  /**
   * Fetch aggregate analytics comparison.
   */
  async function fetchAggregateComparison(days: number = 7, propertyIds?: string[]) {
    loading.value = true;
    error.value = null;
    comparisonData.value = null;

    try {
      const params: any = { days };

      if (propertyIds && propertyIds.length > 0) {
        params.property_ids = propertyIds;
      }

      const { data } = await client("/api/google-analytics/comparison", {
        method: "GET",
        params,
      });

      comparisonData.value = data;
      return data;
    } catch (err: any) {
      if (err.status === 429 || err.statusCode === 429) {
        error.value = "Too many requests. Please wait a moment and try again.";
      } else {
        error.value = err.data?.message || err.message || "Failed to load comparison data";
      }
      throw err;
    } finally {
      loading.value = false;
    }
  }

  /**
   * Fetch property analytics comparison.
   */
  async function fetchPropertyComparison(propertyId: string, days: number = 7) {
    loading.value = true;
    error.value = null;
    comparisonData.value = null;

    try {
      const { data } = await client(`/api/google-analytics/properties/${propertyId}/comparison`, {
        method: "GET",
        params: { days },
      });

      comparisonData.value = data;
      return data;
    } catch (err: any) {
      if (err.status === 429 || err.statusCode === 429) {
        error.value = "Too many requests. Please wait a moment and try again.";
      } else {
        error.value = err.data?.message || err.message || "Failed to load comparison data";
      }
      throw err;
    } finally {
      loading.value = false;
    }
  }

  /**
   * Get metrics dashboard data.
   */
  async function fetchMetricsDashboard() {
    loading.value = true;
    error.value = null;

    try {
      const { data } = await client("/api/google-analytics/metrics/dashboard", {
        method: "GET",
      });

      return data;
    } catch (err: any) {
      error.value = err.data?.message || err.message || "Failed to load metrics dashboard";
      throw err;
    } finally {
      loading.value = false;
    }
  }

  /**
   * Get system health metrics.
   */
  async function fetchSystemHealth() {
    loading.value = true;
    error.value = null;

    try {
      const { data } = await client("/api/google-analytics/system-health", {
        method: "GET",
      });

      return data;
    } catch (err: any) {
      error.value = err.data?.message || err.message || "Failed to load system health";
      throw err;
    } finally {
      loading.value = false;
    }
  }

  /**
   * Get quota usage for a property.
   */
  async function fetchQuotaUsage(propertyId: string, days: number = 1) {
    loading.value = true;
    error.value = null;

    try {
      const { data } = await client(`/api/google-analytics/quota/${propertyId}`, {
        method: "GET",
        params: { days },
      });

      return data;
    } catch (err: any) {
      error.value = err.data?.message || err.message || "Failed to load quota usage";
      throw err;
    } finally {
      loading.value = false;
    }
  }

  return {
    loading,
    error,
    comparisonData,
    fetchAggregateComparison,
    fetchPropertyComparison,
    fetchMetricsDashboard,
    fetchSystemHealth,
    fetchQuotaUsage,
  };
}
