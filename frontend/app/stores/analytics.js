export const useAnalyticsStore = defineStore("analytics", {
  state: () => ({
    // Use plain objects instead of Map for better serialization
    propertiesCache: {},
    timestamps: {},
    aggregateData: null,
    aggregateTimestamp: null,
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
     * Check if aggregate data is fresh
     */
    isAggregateFresh: (state) => {
      if (!state.aggregateTimestamp) return false;

      const age = Date.now() - state.aggregateTimestamp;
      const maxAge = 5 * 60 * 1000; // 5 minutes

      return age < maxAge;
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
     * Set aggregate data
     */
    setAggregate(data) {
      this.aggregateData = data;
      this.aggregateTimestamp = Date.now();

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
     * Clear specific property from cache
     */
    clearProperty(propertyId) {
      delete this.propertiesCache[propertyId];
      delete this.timestamps[propertyId];
    },

    /**
     * Clear stale entries (older than 30 minutes)
     */
    clearStale() {
      const now = Date.now();
      const maxAge = 30 * 60 * 1000; // 30 minutes

      Object.keys(this.timestamps).forEach((id) => {
        if (now - this.timestamps[id] > maxAge) {
          delete this.propertiesCache[id];
          delete this.timestamps[id];
        }
      });

      // Clear stale aggregate
      if (this.aggregateTimestamp && now - this.aggregateTimestamp > maxAge) {
        this.aggregateData = null;
        this.aggregateTimestamp = null;
      }
    },

    /**
     * Clear all cached data
     */
    clearAll() {
      this.propertiesCache = {};
      this.timestamps = {};
      this.aggregateData = null;
      this.aggregateTimestamp = null;
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
