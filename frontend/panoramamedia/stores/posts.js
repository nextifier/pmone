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
        const config = useAppConfig();

        // PM One API endpoint
        const { data, error: fetchError } = await useFetch(
          `${config.app.pmOneApiUrl}/api/public/blog/posts`,
          {
            headers: {
              Authorization: `Bearer ${config.app.pmOneApiKey}`,
              Accept: "application/json",
            },
            query: {
              per_page: 100, // Get up to 100 posts
              sort: "published_at",
              order: "desc",
            },
            key: "posts",
          },
        );

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
