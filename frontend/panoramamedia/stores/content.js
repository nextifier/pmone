export const useContentStore = defineStore("content", {
  state: () => ({
    pages: {
      home: {
        title: `Panorama Media`,
        description: ``,
        withoutTitleTemplate: true,
      },
      about: {
        title: `About`,
        description: ``,
      },
      products: {
        title: `Products`,
        description: ``,
      },
      events: {
        title: `Events`,
        description: ``,
      },
      contact: {
        title: `Contact`,
        description: ``,
      },
      faq: {
        title: `FAQ`,
        description: ``,
      },
      news: {
        title: `News`,
        description: ``,
      },
      terms: {
        title: `Terms of Service`,
        description: ``,
      },
      privacy: {
        title: `Privacy Policy`,
        description: ``,
      },
    },

    components: {
      postSlider: {
        title: {
          default: "Latest updates",
          morePosts: "You might also like",
        },
      },

      contact: {
        title: "Contact us",
        description: "",
      },

      faq: {
        title: "Frequently Asked Questions",
        emptyStateDescription:
          "We are gathering commonly asked questions. Please come back later 😉",
        contactTitle: "Have any questions? Just send it to us!",
      },
    },
  }),

  getters: {
    /**
     * Mengambil metadata untuk halaman tertentu berdasarkan kuncinya.
     * @param {object} state - State store.
     * @returns {function(string): object | null}
     */
    getMetaByKey: (state) => (key) => {
      return state.pages[key] || null;
    },
  },
});
