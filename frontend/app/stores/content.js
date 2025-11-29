export const useContentStore = defineStore("content", {
  state: () => ({
    pages: {
      home: {
        title: `PM One`,
        description: ``,
        withoutTitleTemplate: true,
      },
      terms: {
        title: `Terms of Service`,
        description: ``,
      },
      privacy: {
        title: `Privacy Policy`,
        description: ``,
      },
      signup: {
        title: `Sign up`,
        description: ``,
      },
      login: {
        title: `Log in`,
        description: ``,
      },
      magicLink: {
        title: `Log in without Password`,
        description: ``,
      },
      forgotPassword: {
        title: `Forgot Password`,
        description: ``,
      },
      resetPassword: {
        title: `Reset Password`,
        description: ``,
      },
      verifiedOnly: {
        title: `Verified Only`,
        description: ``,
      },
      verifyEmail: {
        title: `Verify Email`,
        description: ``,
      },
      dashboard: {
        title: `Dashboard`,
        description: ``,
      },
      editProfile: {
        title: `Edit Profile`,
        description: ``,
      },
      projects: {
        title: `Projects`,
        description: ``,
      },
      projectTrash: {
        title: `Project Trash`,
        description: ``,
      },
      inbox: {
        title: `Inbox`,
        description: ``,
      },
      posts: {
        title: `Posts`,
        description: ``,
      },
      users: {
        title: `Users`,
        description: ``,
      },
      userTrash: {
        title: `User Trash`,
        description: ``,
      },
      logs: {
        title: `Activity Logs`,
        description: ``,
      },
      settings: {
        title: `Settings`,
        description: ``,
      },
      settingsProfile: {
        title: `Edit Profile`,
        description: ``,
      },
      settingsPassword: {
        title: `Change Password`,
        description: ``,
      },
      settingsAppearance: {
        title: `Appearance`,
        description: ``,
      },
      news: {
        title: `News`,
        description: `Latest updates and articles`,
      },
    },

    components: {},
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
