import tailwindcss from "@tailwindcss/vite";
import { fileURLToPath } from "node:url";
import { brands } from "./brands";

const noopMock = fileURLToPath(new URL("./mock/noop.mjs", import.meta.url));
const unheadStreamIifeMock = fileURLToPath(new URL("./mock/unhead-stream-iife.mjs", import.meta.url));

// Brand selection is BUILD-time: each brand's admin is its own deployment
// (Cloudflare Pages project) building this repo with a different BRAND env.
// See brands/index.ts for the brand-layer rule and how to add a brand.
const brandId = process.env.BRAND || "pmone";
const brand = brands[brandId as keyof typeof brands];

if (!brand) {
  throw new Error(
    `Unknown BRAND "${brandId}". Registered brands: ${Object.keys(brands).join(", ")}`
  );
}

const isProduction = process.env.NODE_ENV === "production";

// While a brand has no real assets yet (assetsReady=false), every icon /
// screenshot reference is omitted so the build never points at missing files.
const brandIcons = brand.assetsReady
  ? {
      screenshots: [
        {
          src: `brands/${brand.id}/screenshots/desktop-1.png`,
          sizes: "1280x833",
          type: "image/png",
          form_factor: "wide" as const,
          label: `Desktop view of ${brand.name}`,
        },
        {
          src: `brands/${brand.id}/screenshots/mobile-1.png`,
          sizes: "400x842",
          type: "image/png",
          form_factor: "narrow" as const,
          label: `Mobile view of ${brand.name}`,
        },
      ],
      icons: [
        {
          src: `brands/${brand.id}/icons/icon-192x192.png`,
          sizes: "192x192",
          type: "image/png",
        },
        {
          src: `brands/${brand.id}/icons/icon-512x512.png`,
          sizes: "512x512",
          type: "image/png",
        },
        {
          src: `brands/${brand.id}/icons/icon-512x512.png`,
          sizes: "512x512",
          type: "image/png",
          purpose: "any" as const,
        },
      ],
    }
  : {};

const brandHeadLinks = brand.assetsReady
  ? [
      {
        rel: "icon",
        href: `/brands/${brand.id}/favicon.ico`,
      },
      {
        rel: "apple-touch-icon",
        sizes: "180x180",
        href: `/brands/${brand.id}/icons/apple-touch-icon.png`,
      },
    ]
  : [];

export default defineNuxtConfig({
  devtools: {
    enabled: false,
    componentInspector: false,
  },

  ignore: ["**/.DS_Store", "**/.DS_Store/**"],

  runtimeConfig: {
    // Private keys that are only available server-side.
    // Dev value comes from frontend/.env; per-brand deployments set their own.
    pmOneApiKey: process.env.NUXT_PM_ONE_API_KEY || "",

    // Public keys that are exposed to the client
    public: {
      siteUrl:
        process.env.NUXT_PUBLIC_SITE_URL ||
        (isProduction ? brand.siteUrl : "http://localhost:3000"),
      apiUrl:
        process.env.NUXT_PUBLIC_API_URL || (isProduction ? brand.apiUrl : "http://localhost:8000"),
      blogUsernames: "", // Empty string means show all posts (no author filter)
    },
  },

  alias: {
    "#brand": fileURLToPath(new URL(`./brands/${brandId}`, import.meta.url)),
  },

  routeRules: {
    // Note: "/" -> "/dashboard" is handled by a global route middleware
    // (app/middleware/root-redirect.global.ts), not a routeRule - a routeRule
    // redirect on "/" is ignored while pages/index.vue exists.
    "/docs": { redirect: { to: "/docs/staff-dashboard-overview", statusCode: 302 } },

    // The old Email Delivery page was renamed to Emails.
    "/email-delivery": { redirect: { to: "/emails", statusCode: 302 } },

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
  },

  app: {
    head: {
      title: brand.name,
      meta: [
        {
          name: "viewport",
          content: "width=device-width, initial-scale=1, interactive-widget=resizes-content",
        },
      ],
      htmlAttrs: {
        lang: "en",
      },
      link: brandHeadLinks,
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
    resolve: {
      // vue-sonner menyimpan state toast di module scope; pnpm bisa membuat
      // beberapa salinan fisik versi yang sama (peer-hash berbeda) sehingga
      // dua importer ter-resolve ke real path berbeda. Di build produksi itu
      // menjadi dua instance state — toast() menulis ke instance yang tidak
      // di-subscribe Toaster dan tidak ada toast yang tampil. dedupe memaksa
      // satu resolusi untuk semua importer.
      dedupe: ["vue-sonner"],
    },
    optimizeDeps: {
      // Bumping this forces Vite to compute a new `?v=` hash on the next
      // dev start, busting any stale browser-cached modules from previous
      // runs (especially useful behind a CDN tunnel where intermediate
      // caches respect Vite's `immutable` cache-control).
      force: true,
      // vue-qrcode-reader loads a zxing wasm decoder at runtime (via
      // barcode-detector). Pre-bundling the wasm-loading deps breaks Vite's
      // optimizer, so exclude them and let them load as native ESM.
      exclude: ["vue-qrcode-reader", "barcode-detector", "zxing-wasm"],
      include: [
        "@internationalized/date",
        "@number-flow/vue",
        "@tanstack/vue-table",
        "@tiptap/extension-code-block",
        "@tiptap/extension-image",
        "@tiptap/extension-link",
        "@tiptap/extension-placeholder",
        "@tiptap/extension-text-align",
        "@tiptap/pm/state",
        "@tiptap/pm/view",
        "@tiptap/starter-kit",
        "@tiptap/vue-3",
        "@unovis/ts",
        "@unovis/vue",
        "@vue/devtools-core",
        "@vue/devtools-kit",
        "@vueuse/integrations/useSortable",
        "base-vue-phone-input",
        "canvas-confetti",
        "class-variance-authority",
        "clsx",
        "dayjs", // CJS
        "dayjs/plugin/customParseFormat", // CJS
        "dayjs/plugin/relativeTime", // CJS
        "dompurify",
        "embla-carousel-autoplay",
        "embla-carousel-vue",
        "embla-carousel-wheel-gestures",
        "filepond-plugin-file-validate-size",
        "filepond-plugin-file-validate-type",
        "filepond-plugin-image-preview",
        "gsap",
        "gsap/Draggable",
        "gsap/InertiaPlugin",
        "lucide-vue-next",
        "nanoid",
        "nuxt > @nuxt/devtools > @vitejs/devtools-kit/client",
        "nuxt > @nuxt/devtools > @vitejs/devtools/client/inject",
        "nuxt > @nuxt/devtools > @vue/devtools-core",
        "nuxt > @nuxt/devtools > @vue/devtools-kit",
        "nuxt > @nuxt/devtools > error-stack-parser-es",
        "nuxt > @nuxt/devtools > vite-plugin-vue-tracer/client/overlay",
        "qrcode", // CJS
        "reka-ui",
        "reka-ui/date",
        "shiki",
        "shiki/core",
        "shiki/engine/oniguruma",
        "shiki/langs/bash.mjs",
        "shiki/langs/css.mjs",
        "shiki/langs/html.mjs",
        "shiki/langs/javascript.mjs",
        "shiki/langs/json.mjs",
        "shiki/langs/jsx.mjs",
        "shiki/langs/markdown.mjs",
        "shiki/langs/php.mjs",
        "shiki/langs/python.mjs",
        "shiki/langs/sql.mjs",
        "shiki/langs/tsx.mjs",
        "shiki/langs/typescript.mjs",
        "shiki/langs/vue.mjs",
        "shiki/langs/xml.mjs",
        "shiki/langs/yaml.mjs",
        "shiki/themes/github-dark.mjs",
        "shiki/themes/github-light.mjs",
        "shiki/wasm",
        "tailwind-merge",
        "v-wave",
        "vaul-vue",
        "vue-filepond",
        "vue-json-pretty",
        "vue-scrollto", // CJS
        "vue-sonner",
        "vue-tippy",
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
      process.env.NUXT_SANCTUM_BASE_URL || (isProduction ? brand.apiUrl : "http://localhost:8000"),
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
    // MinusOne = app default (body). The 10 curated families below power the
    // Appearance "Heading"/"Font" pickers. They MUST be declared explicitly:
    // the families are applied at RUNTIME via injected CSS vars
    // (lib/appearance + lib/fonts), which @nuxt/fonts' static scanner can't see,
    // so auto-discovery would never self-host them.
    families: [
      {
        name: "MinusOne",
        src: "/fonts/MinusOne-VF.woff2",
        weight: "400 1000",
        display: "swap",
      },
      { name: "Geist", provider: "google", weights: [400, 500, 600, 700], display: "swap" },
      { name: "Inter", provider: "google", weights: [400, 500, 600, 700], display: "swap" },
      { name: "DM Sans", provider: "google", weights: [400, 500, 600, 700], display: "swap" },
      { name: "Manrope", provider: "google", weights: [400, 500, 600, 700], display: "swap" },
      { name: "Space Grotesk", provider: "google", weights: [400, 500, 600, 700], display: "swap" },
      { name: "Outfit", provider: "google", weights: [400, 500, 600, 700], display: "swap" },
      { name: "Geist Mono", provider: "google", weights: [400, 500, 600, 700], display: "swap" },
      {
        name: "JetBrains Mono",
        provider: "google",
        weights: [400, 500, 600, 700],
        display: "swap",
      },
      {
        name: "Playfair Display",
        provider: "google",
        weights: [400, 500, 600, 700],
        display: "swap",
      },
      { name: "Lora", provider: "google", weights: [400, 500, 600, 700], display: "swap" },
    ],
  },

  icon: {
    mode: "svg",
    // `clientBundle.scan` parses all ~1.1k components at build time to collect
    // used icons and inlines them into a client chunk - a heavy build-memory
    // step that contributed to the Cloudflare 8 GB OOM. Disabled: with mode
    // "svg" + installed @iconify-json collections, icons are resolved
    // server-side (inlined into SSR HTML) and fetched from the bundled icon API
    // on client navigation, so they keep rendering with no scan/bundle cost.
    // clientBundle: false,
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
    // Cookie storage (not localStorage) so the preference is readable during SSR
    // → the html class + colorMode.value resolve synchronously, no reactive desync
    // / flash. App-scoped key so other apps on the dev localhost:3000 origin can't
    // clobber it. useAppearance is the single gate over this.
    storage: "cookie",
    storageKey: "pmone-color-mode",
  },

  image: {
    provider: "ipx",
    quality: 85,
    format: ["webp"],
    // domains: ["blog.levenium.com"],
  },

  site: {
    name: brand.name,
    url:
      process.env.NUXT_PUBLIC_SITE_URL || (isProduction ? brand.siteUrl : "http://localhost:3000"),
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
      name: brand.name,
      short_name: brand.shortName,
      start_url: "/",
      display: "standalone",
      theme_color: "#ffffff",
      background_color: "#ffffff",
      description: brand.manifestDescription,
      ...brandIcons,
    },
    workbox: {
      cleanupOutdatedCaches: true,
      skipWaiting: true,
      clientsClaim: true,
      navigateFallback: null,
      globPatterns: ["**/*.{js,css,html,png,svg,ico}"],
      // Raise Workbox's default 2 MiB precache cap so large JS chunks precache
      // cleanly (the default failed the Cloudflare build: "Assets exceeding the
      // limit ... won't be precached").
      maximumFileSizeToCacheInBytes: 5 * 1024 * 1024,
    },
    injectManifest: {
      globPatterns: ["**/*.{js,css,html,png,svg,ico}"],
      // Raise Workbox's default 2 MiB precache cap so large JS chunks precache
      // cleanly (the default failed the Cloudflare build: "Assets exceeding the
      // limit ... won't be precached").
      maximumFileSizeToCacheInBytes: 5 * 1024 * 1024,
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
    // Deploy target: Cloudflare Workers (+ Static Assets), migrated from the
    // `cloudflare-pages` preset. With `cloudflare.deployConfig` below, Nitro
    // emits the wrangler.json into .output/server (assets binding + main +
    // nodejs_compat + compat date) plus the .wrangler/deploy/config.json
    // redirect; the Worker name comes from `cloudflare.wrangler` below (no
    // hand-written root wrangler config). Deploy with
    // `npx wrangler --cwd .output deploy`. Only affects `nuxt build`, not dev.
    preset: "cloudflare_module",

    // Nitro defaults `sourceMap: true`, so the Cloudflare worker/server bundle
    // (which SSR-compiles every page + component) still emits source maps even
    // though Vite's client source maps are off. For ~1.3k components this is a
    // major build-memory and bundle-size cost with zero production value — the
    // worker is never debugged via maps. Disabling it cuts the peak heap that
    // was OOM-ing the Cloudflare build (8 GB VM ceiling) and shrinks the worker
    // bundle toward the size limit.
    sourceMap: false,
    alias: {
      "vue-stream-markdown": noopMock,
      // Nuxt 4.5 statically imports unhead's SSR-streaming IIFE (a JS module
      // exporting the whole script as one big string) even when ssrStreaming is
      // off. Nitro's replace plugin rewrites `typeof window` INSIDE that string,
      // breaking its quote escaping and failing the server build with
      // "RollupError: Expected a semicolon". Streaming is disabled here, so the
      // module is dead code — stub it out until nitro/unhead fix this upstream.
      "@unhead/vue/stream/iife": unheadStreamIifeMock,
      "unhead/stream/iife": unheadStreamIifeMock,
    },
    cloudflare: {
      // Generate a complete wrangler.json into .output/server at build time
      // (main + assets binding with auto-computed relative paths + nodejs_compat
      // + compatibility date), plus the .output/.wrangler/deploy/config.json
      // redirect. Deploy with `npx wrangler --cwd .output deploy` — no
      // hand-written root wrangler config, so the asset/entry paths can never
      // drift from Nitro's actual output.
      deployConfig: true,
      nodeCompat: true,
      // Worker name for the generated config. NOTE: a Cloudflare Pages project
      // named "pmone" already exists; if the dashboard/deploy rejects the name
      // during the transition, rename this + the Worker to e.g. "pmone-app".
      wrangler: {
        name: "pmone",
      },
    },
  },

  compatibilityDate: "2025-09-16",

  experimental: {
    viewTransition: true,
    emitRouteChunkError: "automatic-immediate",
  },
});
