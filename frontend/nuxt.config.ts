import tailwindcss from "@tailwindcss/vite";

export default defineNuxtConfig({
  devtools: {
    enabled: true,
    componentInspector: false,
  },

  runtimeConfig: {
    public: {
      siteUrl: process.env.NODE_ENV === "production" ? "https://pmone.id" : "http://localhost:3000",
      apiUrl:
        process.env.NODE_ENV === "production" ? "https://api.pmone.id" : "http://localhost:8000",
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
    "@vite-pwa/nuxt",
  ],

  sanctum: {
    baseUrl:
      process.env.NODE_ENV === "production" ? "https://api.pmone.id" : "http://localhost:8000",
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
    preference: "dark", //system, light, dark
    fallback: "dark",
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
    url: process.env.NODE_ENV === "production" ? "https://pmone.id" : "http://localhost:3000",
  },

  schemaOrg: {
    enabled: false,
  },

  linkChecker: {
    enabled: false,
  },

  pwa: {
    registerType: "autoUpdate",
    registerWebManifestInRouteRules: true,
    manifest: {
      name: "PM One",
      short_name: "PM One",
      start_url: "/",
      display: "standalone",
      theme_color: "#09090b",
      background_color: "#09090b",
      description:
        "Streamline your project management with PM One - a powerful, intuitive dashboard that helps you organize tasks, track progress, and collaborate seamlessly. Access your projects anywhere, anytime with our fast and reliable PWA experience.",

      screenshots: [
        {
          src: "screenshots/desktop-1.png",
          sizes: "1280x833",
          type: "image/png",
          form_factor: "wide",
          label: "Desktop view of PM One",
        },
        {
          src: "screenshots/mobile-1.png",
          sizes: "400x842",
          type: "image/png",
          form_factor: "narrow",
          label: "Mobile view of PM One",
        },
      ],
      icons: [
        {
          src: "icons/icon-192x192.png",
          sizes: "192x192",
          type: "image/png",
        },
        {
          src: "icons/icon-512x512.png",
          sizes: "512x512",
          type: "image/png",
        },
        {
          src: "icons/icon-512x512.png",
          sizes: "512x512",
          type: "image/png",
          purpose: "any",
        },
      ],
    },
    // Disable all caching - PWA for install capability only
    disable: false,
    strategies: "injectManifest",
    srcDir: "public",
    filename: "sw.js",
    injectManifest: {
      globPatterns: [], // No files to cache
      globIgnores: ["**/*"], // Ignore all files
    },
    client: {
      installPrompt: true,
    },
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

  compatibilityDate: "2025-09-16",

  experimental: {
    viewTransition: true,
  },
});
