export const usePostStore = defineStore("posts", {
  state: () => ({
    posts: [],
    pending: false,
    error: null,
    hasFetchedPosts: false,
  }),
  actions: {
    /**
     * Mengambil daftar postingan jika belum pernah diambil.
     * @param {boolean} [force=false] - Paksa fetch ulang bahkan jika data sudah ada.
     */
    async fetchPosts(force = false) {
      if (this.pending || (this.hasFetchedPosts && !force)) {
        return;
      }

      this.pending = true;
      this.error = null;

      try {
        // Call local Nuxt server API (which proxies to PM One API)
        // API key is kept secure on the server, not exposed to browser
        const { data, error: fetchError } = await useFetch("/api/blog/posts", {
          query: {
            per_page: 100, // Get up to 100 posts
            sort: "-published_at", // Sort by published_at descending (newest first)
          },
          key: "posts",
        });

        if (fetchError.value) {
          throw fetchError.value;
        }

        // PM One returns { data: [...], meta: {...} }
        if (data.value?.data) {
          this.posts = data.value.data;
          this.hasFetchedPosts = true;
        }
      } catch (err) {
        this.error = err;
        console.error("Fetching posts failed: ", err);
      } finally {
        this.pending = false;
      }
    },
  },
});
