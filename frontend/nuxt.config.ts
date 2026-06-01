import tailwindcss from "@tailwindcss/vite";
import { fileURLToPath } from "node:url";

const noopMock = fileURLToPath(new URL("./mock/noop.mjs", import.meta.url));

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
      siteUrl: process.env.NODE_ENV === "production" ? "https://pmone.id" : "http://localhost:3000",
      apiUrl:
        process.env.NODE_ENV === "production" ? "https://api.pmone.id" : "http://localhost:8000",
      blogUsernames: "", // Empty string means show all posts (no author filter)
    },
  },

  routeRules: {
    "/docs": { redirect: { to: "/docs/staff-dashboard-overview", statusCode: 302 } },
    "/shaders/docs": { redirect: { to: "/shaders/docs/quickstart", statusCode: 302 } },
    // The visual editor is a fully interactive WebGPU tool — render it client-side
    // only (no SSR benefit, and reka-ui controls don't server-render cleanly).
    "/shaders/editor": { ssr: false },
    // Docs render client-side (markdown via marked/yaml + live shader previews);
    // they are intentionally not indexed, so SSR buys nothing here.
    "/shaders/docs/**": { ssr: false },

    // Admin / auth pages (everything behind sanctum) are excluded from the
    // sitemap and not indexed by search engines. Public, share-worthy routes are
    // re-enabled below — more specific routeRules win over "/**".
    "/**": { sitemap: false, robots: false },

    "/": { sitemap: true, robots: true },
    "/privacy": { sitemap: true, robots: true },
    "/terms": { sitemap: true, robots: true },
    "/news": { sitemap: true, robots: true },
    "/news/**": { sitemap: true, robots: true },
    "/docs/**": { sitemap: true, robots: true },
    "/p/**": { sitemap: true, robots: true },
    "/f/**": { sitemap: true, robots: true },
    "/forms/**": { sitemap: true, robots: true },
    "/hotels": { sitemap: true, robots: true },
    "/hotels/**": { sitemap: true, robots: true },
    "/accommodation": { sitemap: true, robots: true },
    "/accommodation/**": { sitemap: true, robots: true },
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
    // Disable production sourcemaps — they are not used in prod and the
    // @tailwindcss/vite plugin emitted 300+ "Sourcemap is likely to be
    // incorrect" warnings that flooded the build log.
    build: { sourcemap: false },
    css: { devSourcemap: false },
    optimizeDeps: {
      // Bumping this forces Vite to compute a new `?v=` hash on the next
      // dev start, busting any stale browser-cached modules from previous
      // runs (especially useful behind a CDN tunnel where intermediate
      // caches respect Vite's `immutable` cache-control).
      force: true,
      // The `shaders` WebGPU library must NOT be pre-bundled. Bundling its 122
      // components into one giant dep file makes another plugin's code-filter
      // RegExp (run via transform middleware) overflow V8's regex stack
      // ("Maximum call stack size exceeded"). Excluded, it loads as small,
      // per-module ESM files which the filter handles fine.
      exclude: ["shaders", "shaders/vue", "shaders/registry", "shaders/vue/codegen"],
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
    url: process.env.NODE_ENV === "production" ? "https://pmone.id" : "http://localhost:3000",
  },

  ogImage: {
    // Disabled. Build-time OG generation (zeroRuntime) REQUIRES prerendering the
    // public pages, which turns them into static HTML and breaks per-request SSR
    // auth — the header reads the session cookie server-side to show Dashboard vs
    // Log in, so a static prerendered page always renders "Log in". The whole app
    // must stay SSR, so build-time OG is off. (If OG images are wanted later, use
    // a static `ogImage` meta per public page, or runtime satori — neither needs
    // prerendering.)
    enabled: false,
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
    cloudflare: {
      // Generate the Cloudflare deploy config (wrangler.json) at build time so
      // the `nodejs_compat` flag + compatibility date are applied AUTOMATICALLY
      // on every deploy. This removes the "[cloudflare] Node.js compatibility is
      // not enabled" warning and avoids having to toggle the flag by hand in the
      // Cloudflare Pages dashboard for each project.
      deployConfig: true,
      nodeCompat: true,
    },
  },

  compatibilityDate: "2025-09-16",

  experimental: {
    viewTransition: true,
    emitRouteChunkError: "automatic-immediate",
  },
});
