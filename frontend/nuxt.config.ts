import tailwindcss from "@tailwindcss/vite";

export default defineNuxtConfig({
  devtools: {
    enabled: true,
    componentInspector: false,
  },

  runtimeConfig: {
    public: {
      siteUrl:
        process.env.NODE_ENV === "production"
          ? "https://pmone.id"
          : "http://localhost:3000",
      apiUrl:
        process.env.NODE_ENV === "production"
          ? "https://api.pmone.id"
          : "http://localhost:8000",
    },
  },

  app: {
    head: {
      title: "PM One",
      htmlAttrs: {
        lang: "en",
      },
      link: [
        {
          rel: "apple-touch-icon",
          sizes: "180x180",
          href: "/icons/apple-touch-icon.png",
        },
      ],
      script: [],
    },
  },

  css: ["~/assets/css/main.css"],

  vite: {
    plugins: [tailwindcss()],
  },

  modules: [
    "@nuxt/fonts",
    "@nuxt/icon",
    "@nuxt/image",
    "@nuxtjs/color-mode",
    "shadcn-nuxt",
    "@vueuse/nuxt",
    "@pinia/nuxt",
    "@nuxtjs/seo",
    "nuxt-gtag",
    "@formkit/auto-animate/nuxt",
    "nuxt-auth-sanctum",
  ],

  sanctum: {
    baseUrl:
      process.env.NODE_ENV === "production"
        ? "https://api.pmone.id"
        : "http://localhost:8000",
    mode: "cookie",
    userStateKey: "sanctum.user.identity",
    redirectIfAuthenticated: true,
    redirectIfUnauthenticated: true,
    endpoints: {
      csrf: "/sanctum/csrf-cookie",
      login: "/login",
      logout: "/logout",
      user: "/api/user",
    },
    csrf: {
      cookie: "XSRF-TOKEN",
      header: "X-XSRF-TOKEN",
    },
    client: {
      retry: false,
      initialRequest: true,
    },
    redirect: {
      keepRequestedRoute: true,
      onLogin: "/dashboard",
      onLogout: "/",
      onAuthOnly: "/login",
      onGuestOnly: "/dashboard",
    },
    globalMiddleware: {
      enabled: false,
      allow404WithoutAuth: true,
    },
    logLevel: 3,
    appendPlugin: false,
  },

  fonts: {
    provider: "local",
    families: [
      {
        name: "PlusJakartaSans",
        src: "/fonts/PlusJakartaSans-VariableFont.woff2",
        weight: "200 800",
        display: "swap",
      },
    ],
  },

  icon: {
    mode: "svg",
    clientBundle: {
      scan: true,
    },
  },

  shadcn: {
    /**
     * Prefix for all the imported component
     */
    prefix: "",
    /**
     * Directory that the component lives in.
     * @default "./components/ui"
     */
    componentDir: "./app/components/ui",
  },

  colorMode: {
    preference: "light", //system, light, dark
    fallback: "light",
    classSuffix: "",
    hid: "color-mode-script",
    globalName: "__COLOR_MODE__",
    storageKey: "color-mode",
  },

  image: {
    // provider: process.env.NODE_ENV === "production" ? "ipxStatic" : "ipx",
    provider: process.env.NODE_ENV === "production" ? "cloudflare" : "ipx",
    cloudflare: {
      baseURL: "https://pmone.id",
    },
    quality: 85,
    format: ["webp"],
    // domains: ["blog.levenium.com"],
  },

  site: {
    name: "PM One",
    url:
      process.env.NODE_ENV === "production"
        ? "https://pmone.id"
        : "http://localhost:3000",
  },

  schemaOrg: {
    enabled: false,
  },

  linkChecker: {
    enabled: false,
  },

  // gtag: {
  //   loadingStrategy: "defer",
  //   tags: [
  //     {
  //       id: "G-4ZNWF3G5DM",
  //       enabled: true,
  //     },
  //   ],
  // },

  // gtm: {
  //   defer: true,
  //   id: "GTM-MPMTC993",
  // },

  // disqus: {
  //   shortname: "pmone",
  // },

  compatibilityDate: "2025-09-16",

  experimental: {
    viewTransition: true,
  },
});
