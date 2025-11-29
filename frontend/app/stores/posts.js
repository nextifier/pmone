export const usePostStore = defineStore("posts", {
  state: () => ({
    posts: [],
    pending: false,
    error: null,
    hasFetchedPosts: false,
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 50,
      total: 0,
    },
  }),
  actions: {
    /**
     * Mengambil daftar postingan dengan pagination.
     * @param {Object} [options={}] - Opsi untuk fetch.
     * @param {number} [options.page=1] - Halaman yang akan diambil.
     * @param {boolean} [options.force=false] - Paksa fetch ulang bahkan jika data sudah ada.
     */
    async fetchPosts({ page = 1, force = false } = {}) {
      // Allow refetch if page changes or force is true
      const isPageChange = page !== this.meta.current_page;
      if (this.pending || (this.hasFetchedPosts && !force && !isPageChange)) {
        return;
      }

      this.pending = true;
      this.error = null;

      try {
        // Use $fetch from Nuxt which handles SSR correctly
        const { $fetch } = useNuxtApp();
        const data = await $fetch("/api/blog/posts", {
          query: {
            page,
            per_page: 50,
            sort: "-published_at",
          },
        });

        // PM One returns { data: [...], meta: {...} }
        if (data?.data) {
          this.posts = data.data;
          this.hasFetchedPosts = true;
        }

        if (data?.meta) {
          this.meta = data.meta;
        }
      } catch (err) {
        this.error = err;
        console.error("Fetching posts failed: ", err);
      } finally {
        this.pending = false;
      }
    },

    /**
     * Mengubah halaman dan fetch data.
     * @param {number} page - Halaman yang akan diambil.
     */
    async goToPage(page) {
      await this.fetchPosts({ page, force: true });
    },
  },
});
