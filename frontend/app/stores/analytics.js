export const useAnalyticsStore = defineStore("analytics", {
  state: () => ({
    // Use plain objects instead of Map for better serialization
    propertiesCache: {},
    timestamps: {},
    // Store aggregate data per period to avoid unnecessary refetch
    aggregateCache: {}, // key: period-string, value: { data, timestamp, cacheTTL }
    // Realtime data (separate from aggregate)
    realtimeData: null,
    realtimeTimestamp: null,
  }),

  getters: {
    /**
     * Check if property data is still fresh (less than 5 minutes old)
     */
    isFresh: (state) => (propertyId) => {
      const timestamp = state.timestamps[propertyId];
      if (!timestamp) return false;

      const age = Date.now() - timestamp;
      const maxAge = 5 * 60 * 1000; // 5 minutes

      return age < maxAge;
    },

    /**
     * Get cached property data
     */
    getProperty: (state) => (propertyId) => {
      return state.propertiesCache[propertyId] || null;
    },

    /**
     * Get all cached property IDs
     */
    cachedPropertyIds: (state) => {
      return Object.keys(state.propertiesCache);
    },

    /**
     * Check if aggregate data is fresh for specific period
     * Uses backend's cache_ttl_minutes if available, otherwise defaults to 5 minutes
     */
    isAggregateFresh: (state) => (period = "30") => {
      const cacheKey = `period_${period}`;
      const cached = state.aggregateCache[cacheKey];

      if (!cached || !cached.timestamp) return false;

      const age = (Date.now() - cached.timestamp) / 1000 / 60; // in minutes
      // Use backend's cacheTTL if available, otherwise default to 5 minutes
      const maxAge = cached.data?.cache_info?.cache_ttl_minutes || 5;

      return age < maxAge;
    },

    /**
     * Get aggregate data for specific period
     */
    getAggregate: (state) => (period = "30") => {
      const cacheKey = `period_${period}`;
      return state.aggregateCache[cacheKey]?.data || null;
    },

    /**
     * Check if realtime data is fresh (less than 1 minute old)
     */
    isRealtimeFresh: (state) => {
      if (!state.realtimeTimestamp) return false;
      const age = Date.now() - state.realtimeTimestamp;
      return age < 60 * 1000; // 1 minute
    },

    /**
     * Get cache statistics
     */
    cacheStats: (state) => {
      const total = Object.keys(state.propertiesCache).length;
      const fresh = Object.keys(state.timestamps).filter((id) => {
        const age = Date.now() - state.timestamps[id];
        return age < 5 * 60 * 1000;
      }).length;
      const stale = total - fresh;

      return { total, fresh, stale };
    },
  },

  actions: {
    /**
     * Set property data in cache
     */
    setProperty(propertyId, data) {
      this.propertiesCache[propertyId] = data;
      this.timestamps[propertyId] = Date.now();
    },

    /**
     * Set aggregate data for specific period
     */
    setAggregate(period = "30", data) {
      const cacheKey = `period_${period}`;
      this.aggregateCache[cacheKey] = {
        data,
        timestamp: Date.now(),
      };

      // Also populate individual properties from aggregate breakdown
      if (data?.property_breakdown && Array.isArray(data.property_breakdown)) {
        data.property_breakdown.forEach((property) => {
          // Only cache if it has analytics data
          if (property.analytics) {
            this.setProperty(property.property_id, {
              property: {
                id: property.id,
                name: property.name,
                property_id: property.property_id,
                account_name: property.account_name,
                is_active: property.is_active,
              },
              metrics: property.analytics,
              // Note: aggregate doesn't have rows/top_pages/etc
              // Those will be fetched separately when viewing detail
            });
          }
        });
      }
    },

    /**
     * Set realtime data (online users)
     */
    setRealtimeData(data) {
      this.realtimeData = data;
      this.realtimeTimestamp = Date.now();
    },

    /**
     * Clear specific property from cache
     */
    clearProperty(propertyId) {
      delete this.propertiesCache[propertyId];
      delete this.timestamps[propertyId];
    },

    /**
     * Clear specific period aggregate from cache
     */
    clearAggregate(period = "30") {
      const cacheKey = `period_${period}`;
      delete this.aggregateCache[cacheKey];
    },

    /**
     * Clear stale entries - BUT KEEP AS FALLBACK until fresh data arrives
     *
     * STALE-WHILE-REVALIDATE STRATEGY:
     * - Never delete cached data automatically
     * - Keep stale data as fallback for instant page loads
     * - Only clear when explicitly requested or memory pressure
     * - Backend handles the revalidation, frontend just displays
     */
    clearStale() {
      const now = Date.now();
      const maxAge = 60 * 60 * 1000; // 1 hour for properties (increased from 30 min)

      // Only clear very old properties (>1 hour) to free memory
      Object.keys(this.timestamps).forEach((id) => {
        if (now - this.timestamps[id] > maxAge) {
          delete this.propertiesCache[id];
          delete this.timestamps[id];
        }
      });

      // NEVER clear aggregates automatically - they serve as fallback for instant loads
      // The backend implements stale-while-revalidate, frontend should always show cached data
      // Only clear aggregates older than 24 hours to prevent unbounded memory growth
      const maxAggregateAge = 24 * 60 * 60 * 1000; // 24 hours
      Object.keys(this.aggregateCache).forEach((cacheKey) => {
        const cached = this.aggregateCache[cacheKey];
        if (!cached || !cached.timestamp) {
          return; // Keep even if no timestamp - might still be useful
        }

        const age = now - cached.timestamp;
        if (age >= maxAggregateAge) {
          delete this.aggregateCache[cacheKey];
        }
      });

      // Clear stale realtime data (older than 5 minutes)
      if (this.realtimeTimestamp && now - this.realtimeTimestamp > 5 * 60 * 1000) {
        this.realtimeData = null;
        this.realtimeTimestamp = null;
      }
    },

    /**
     * Clear all cached data
     */
    clearAll() {
      this.propertiesCache = {};
      this.timestamps = {};
      this.aggregateCache = {};
      this.realtimeData = null;
      this.realtimeTimestamp = null;
    },

    /**
     * Check and refresh if data is stale
     */
    async refreshIfStale(propertyId, fetchCallback) {
      if (this.isFresh(propertyId)) {
        return this.getProperty(propertyId);
      }

      // Data is stale or doesn't exist, fetch fresh
      const freshData = await fetchCallback();
      this.setProperty(propertyId, freshData);
      return freshData;
    },
  },
});
