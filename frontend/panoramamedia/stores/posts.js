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
        const { data, error: fetchError } = await useFetch(
          `${useAppConfig().app.blogApiUrl}/posts`,
          {
            query: {
              key: useAppConfig().app.blogApiKey,
              include: "authors,tags",
              filter: `authors.slug:[${useAppConfig().app.blogUsername}]+visibility:public`,
              order: "published_at desc",
              limit: "all",
            },
            key: "posts",
          },
        );

        if (fetchError.value) {
          throw fetchError.value;
        }

        if (data.value?.posts) {
          this.posts = data.value.posts;
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
