import tailwindcss from "@tailwindcss/vite";
import { fileURLToPath } from "node:url";

const noopMock = fileURLToPath(new URL("./mock/noop.mjs", import.meta.url));

// URL resolution for three environments:
//   - real production       (NODE_ENV=production, no override) → pmone.id / api.pmone.id
//   - dev tunnel prod-build (NUXT_PUBLIC_* env set)            → dev.pmone.id / dev-api.pmone.id
//   - local dev             (NODE_ENV=development)             → localhost
// The dev tunnel scenario builds with NODE_ENV=production for the bundled
// output (avoids the Vite ESM fan-out that breaks the CF named tunnel) while
// pointing at the dev tunnel hostnames so Xendit webhooks reach the local
// Laravel server. See the "Dev Tunnel" section in CLAUDE.md for the full
// workflow.
const isProd = process.env.NODE_ENV === "production";
const defaultSiteUrl = isProd ? "https://pmone.id" : "http://localhost:3000";
const defaultApiUrl = isProd ? "https://api.pmone.id" : "http://localhost:8000";
const siteUrl = process.env.NUXT_PUBLIC_SITE_URL || defaultSiteUrl;
const apiUrl = process.env.NUXT_PUBLIC_API_URL || defaultApiUrl;

export default defineNuxtConfig({
  devtools: {
    enabled: true,
    componentInspector: false,
  },

  ignore: ["**/.DS_Store", "**/.DS_Store/**"],

  runtimeConfig: {
    // Private keys that are only available server-side
    pmOneApiKey: process.env.NUXT_PM_ONE_API_KEY || "pk_apm8WoYS1OuWX2MBzz982DreFm47X05VyZuUc05k",

    // Public keys that are exposed to the client
    public: {
      siteUrl,
      apiUrl,
      blogUsernames: "", // Empty string means show all posts (no author filter)
    },
  },

  routeRules: {
    "/docs": { redirect: { to: "/docs/staff-dashboard-overview", statusCode: 302 } },
  },

  app: {
    head: {
      title: "PM One",
      meta: [
        {
          name: "viewport",
          content: "width=device-width, initial-scale=1, interactive-widget=resizes-content",
        },
      ],
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
    optimizeDeps: {
      // Force-bundle list that the previous working production build
      // depended on. Some of these packages (notably `nanoid` and the
      // dayjs plugin paths) get tree-shaken away in the cloudflare-pages
      // SSR output if Vite is left to auto-detect, which crashes the
      // worker at runtime with "500 undefined" on every route.
      include: [
        "nanoid",
        "embla-carousel-wheel-gestures",
        "vue-json-pretty",
        "vue-scrollto",
        "v-wave",
        "dayjs",
        "dayjs/plugin/relativeTime",
        "dayjs/plugin/customParseFormat",
        "vue-tippy",
        "@number-flow/vue",
        "class-variance-authority",
        "vue-sonner",
        "reka-ui",
        "clsx",
        "tailwind-merge",
        "lucide-vue-next",
        "vaul-vue",
        "shiki",
        "dompurify",
        "@vue/devtools-core",
        "@vue/devtools-kit",
      ],
    },
  },

  modules: [
    "@nuxt/fonts",
    "@nuxt/icon",
    "@nuxt/image",
    "@nuxtjs/color-mode",
    "@nuxtjs/i18n",
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
    // Same resolution chain as runtimeConfig.public.apiUrl so the dev tunnel
    // build can point Sanctum at dev-api.pmone.id without code changes.
    baseUrl: apiUrl,
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

  i18n: {
    locales: [
      { code: "en", language: "en-US", name: "English", file: "en.json" },
      { code: "zh", language: "zh-CN", name: "中文", file: "zh.json" },
    ],
    lazy: true,
    langDir: "../i18n/locales",
    defaultLocale: "en",
    strategy: "no_prefix",
    detectBrowserLanguage: {
      useCookie: true,
      cookieKey: "i18n_locale",
      redirectOn: "root",
      alwaysRedirect: false,
      fallbackLocale: "en",
    },
    vueI18n: "./i18n.config.ts",
  },

  fonts: {
    families: [
      {
        name: "MinusOne",
        src: "/fonts/MinusOne-VF.woff2",
        weight: "400 1000",
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
    preference: "system", //system, light, dark
    fallback: "light",
    classSuffix: "",
    globalName: "__COLOR_MODE__",
    storageKey: "color-mode",
  },

  image: {
    provider: "ipx",
    quality: 85,
    format: ["webp"],
    // domains: ["blog.levenium.com"],
  },

  site: {
    name: "PM One",
    url: siteUrl,
  },

  ogImage: {
    defaults: {
      renderer: "takumi",
    },
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
      theme_color: "#ffffff",
      background_color: "#ffffff",
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
    workbox: {
      cleanupOutdatedCaches: true,
      skipWaiting: true,
      clientsClaim: true,
      navigateFallback: null,
      globPatterns: ["**/*.{js,css,html,png,svg,ico}"],
    },
    injectManifest: {
      globPatterns: ["**/*.{js,css,html,png,svg,ico}"],
    },
    client: {
      installPrompt: true,
    },
    devOptions: {
      enabled: false,
      suppressWarnings: true,
      navigateFallbackAllowlist: [/^\/$/],
      type: "module",
    },
  },

  nitro: {
    alias: {
      "vue-stream-markdown": noopMock,
    },
  },

  compatibilityDate: "2025-09-16",

  experimental: {
    viewTransition: true,
    emitRouteChunkError: "automatic-immediate",
  },
});
