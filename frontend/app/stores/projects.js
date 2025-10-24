export const useProjectsStore = defineStore("projects", {
  state: () => ({
    projects: [],
    isLoading: false,
    lastFetched: null,
  }),

  getters: {
    hasProjects: (state) => state.projects.length > 0,
  },

  actions: {
    async fetchProjects(force = false) {
      // Skip if already loaded and not forcing refresh
      if (!force && this.projects.length > 0) {
        return;
      }

      this.isLoading = true;

      try {
        const sanctumFetch = useSanctumClient();
        const params = new URLSearchParams();
        params.append("client_only", "true");
        params.append("sort", "order_column");

        const response = await sanctumFetch(`/api/projects?${params.toString()}`);
        this.projects = response.data || [];
        this.lastFetched = Date.now();
      } catch (err) {
        console.error("Failed to fetch projects:", err);
        this.projects = [];
      } finally {
        this.isLoading = false;
      }
    },

    clearProjects() {
      this.projects = [];
      this.lastFetched = null;
    },
  },
});
