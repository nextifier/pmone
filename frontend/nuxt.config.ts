import tailwindcss from "@tailwindcss/vite";
import { createRequire } from "node:module";
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
      // Absolute hrefs: relative ones resolve against the manifest's location,
      // which happens to be correct at the root but breaks under any other scope.
      screenshots: [
        {
          src: `/brands/${brand.id}/screenshots/desktop-1.png`,
          sizes: "1280x833",
          type: "image/png",
          form_factor: "wide" as const,
          label: `Desktop view of ${brand.name}`,
        },
        {
          src: `/brands/${brand.id}/screenshots/mobile-1.png`,
          sizes: "400x842",
          type: "image/png",
          form_factor: "narrow" as const,
          label: `Mobile view of ${brand.name}`,
        },
      ],
      icons: [
        {
          src: `/brands/${brand.id}/icons/icon-192x192.png`,
          sizes: "192x192",
          type: "image/png",
        },
        {
          src: `/brands/${brand.id}/icons/icon-512x512.png`,
          sizes: "512x512",
          type: "image/png",
        },
        // Full-bleed variant for Android adaptive icons. Deliberately DARK
        // (#09090b) with a white mark, and `background_color` below matches it.
        //
        // Declaring a maskable icon is what makes Android treat the artwork as
        // an ADAPTIVE icon: it masks it to the system squircle and draws an
        // elevation shadow around that mask. When the icon and the splash
        // background are both #ffffff, that shadow is the only edge with any
        // contrast, so it reads as a stray grey outline. Measured off device
        // screenshots: splash background 255 -> shadow 215 (obvious), against
        // levenium's 9.7 -> 7.7 (invisible). Same ~16-20% black shadow both
        // times; levenium only gets away with it because its icon and splash
        // share one dark colour. Confirmed on a real device by shipping this
        // entry commented out (4801b7cc) and watching the outline disappear.
        //
        // So the two must stay in sync: change this artwork's colour and you
        // must change `background_color` with it, or the outline returns.
        //
        // Must be square and fully opaque, NOT rounded - Android applies the
        // mask itself, and a pre-rounded icon gets its corners cut twice. The
        // furthest white pixel sits at r = 168.2 px = 0.329 of the width,
        // inside the 0.40 safe-zone radius. (Measure inked PIXELS, not the
        // bounding box corners - the mark is curved, so its bbox corner reads a
        // misleading 0.441.) Without this entry Android letterboxes the icon or
        // crops transparent corners into the mask.
        {
          src: `/brands/${brand.id}/icons/icon-512x512-maskable.png`,
          sizes: "512x512",
          type: "image/png",
          purpose: "maskable" as const,
        },
      ],
    }
  : {};

// The only icon <link> that is neither theme-dependent nor part of the web
// manifest. The favicon pair lives in useDynamicFavicon() (app.vue) because it
// swaps with the browser's prefers-color-scheme; a static rel="icon" here would
// compete with it for the same slot.
const brandHeadLinks = brand.assetsReady
  ? [
      {
        rel: "apple-touch-icon",
        sizes: "180x180",
        href: `/brands/${brand.id}/icons/apple-touch-icon.png`,
      },
    ]
  : [];

/** Single switch for the OG Image module: the module option and the public
 *  runtime flag usePageMeta reads both come from here. */
const OG_IMAGE_ENABLED = false;

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
      // Read by usePageMeta so it can skip defineOgImage entirely while the
      // module is off, instead of calling a mock that warns on every page.
      ogImageEnabled: OG_IMAGE_ENABLED,
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

    // Consumers that never parse the HTML - crawlers, RSS readers, bookmark
    // services - request /favicon.ico directly. Nothing is served there, so the
    // request falls through to Nitro's render handler, whose built-in fallback
    // answers 200 image/x-icon with a 78-byte data-URI STRING as the body: a
    // blank icon that never fails loudly. Point the path at the active brand
    // instead. createRouteRulesHandler is registered on h3App ahead of every
    // route handler, so this rule wins over that fallback.
    //
    // Do NOT "simplify" this by dropping a real file at public/favicon.ico.
    // public/ is shared by every brand, so the file would be one brand's icon
    // on a path all of them serve - and on Cloudflare Workers the asset router
    // answers from public/ BEFORE the Worker runs, so the redirect would become
    // dead code that still works in dev. The asymmetry is silent.
    ...(brand.assetsReady
      ? {
          "/favicon.ico": {
            redirect: { to: `/brands/${brand.id}/favicon.ico`, statusCode: 302 },
          },
        }
      : {}),

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
        // iOS ignores the manifest's `display`, so without these an
        // Add-to-Home-Screen launch opens a plain Safari tab instead of a
        // standalone window. "black" (not "black-translucent") keeps the status
        // bar out of the layout, so no safe-area padding is needed.
        { name: "mobile-web-app-capable", content: "yes" },
        { name: "apple-mobile-web-app-capable", content: "yes" },
        { name: "apple-mobile-web-app-status-bar-style", content: "black" },
        { name: "apple-mobile-web-app-title", content: brand.shortName },
      ],
      htmlAttrs: {
        lang: "en",
      },
      link: brandHeadLinks,
      script: [],
    },
  },

  css: ["~/assets/css/main.css"],

  // Production-only: cssnano menjalankan postcss-calc, yang grammar-nya lahir
  // sebelum CSS relative color syntax ada. Semua style-*.css menskalakan chroma
  // dengan `oklch(from var(--primary) 0.93 calc(c * 0.4) h)` dan main.css
  // memakai `calc(alpha * 0.2)` / `calc(l + 0.4)`. postcss-calc tidak bisa
  // nge-lex keyword channel telanjang sebagai operand, throw, menangkap
  // throw-nya sendiri, lalu warn — 22 "Lexical error on line 1" tiap build,
  // tanpa mengubah output sama sekali. Pass-nya juga tidak berguna di sini:
  // sisa calc() yang Tailwind keluarkan menunjuk ke CSS variable, yang memang
  // tidak bisa dilipat saat build. Ongkos mematikannya diukur di repo
  // pmone-events: 246 B gzip pada stylesheet ~70 KiB.
  //
  // Pakai $production, bukan cek process.env.NODE_ENV: @nuxt/cli men-default
  // envName ke production untuk `nuxt build` MAUPUN `nuxt generate`, sementara
  // script build/generate di sini tidak menyetel NODE_ENV.
  $production: {
    postcss: {
      plugins: {
        cssnano: { preset: ["default", { calc: false }] },
      },
    },
  },

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
        "@lucide/vue",
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
    // Mirrors FIELD_LOCALES in lib/customFieldEditor.js, so every language a
    // custom field can be authored in is also one the public form page can
    // render. `ja`/`ko` currently ship only the `forms` block; everything else
    // falls back to English via i18n.config.ts.
    locales: [
      { code: "en", language: "en-US", name: "English", file: "en.json" },
      { code: "id", language: "id-ID", name: "Indonesian", file: "id.json" },
      { code: "ja", language: "ja-JP", name: "日本語", file: "ja.json" },
      { code: "ko", language: "ko-KR", name: "한국어", file: "ko.json" },
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
    // Off for now — not because it cannot work here. Only `zeroRuntime: true`
    // needs prerendering (which would turn public pages static and break the
    // auth-aware header that reads the session cookie per request). That is NOT
    // the default: v6 renders on demand at /_og/d/ from the running server, so
    // a fully-SSR app generates cards fine — levenium and pmone-events both do.
    //
    // To turn it on: flip this to `true` and add a signing secret, same as
    // pmone-events (`security.secret`, otherwise the module generates a random
    // one per build and every card scraped from an earlier deploy starts
    // 403ing). `@takumi-rs/wasm` must be a dependency here, and the renderer is
    // picked by the `.takumi` filename suffix of app/components/OgImage/. The
    // defineOgImage call in usePageMeta is guarded by the same constant, so
    // flipping OG_IMAGE_ENABLED is the only edit needed.
    enabled: OG_IMAGE_ENABLED,
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
      // Explicit id, so the app identity survives a future start_url change —
      // without it Chrome derives identity from start_url and would treat an
      // updated one as an entirely new app.
      id: "/",
      name: brand.name,
      short_name: brand.shortName,
      start_url: "/",
      display: "standalone",
      // Both match the maskable icon's #09090b on purpose: the splash icon and
      // its background become one surface, so Android's adaptive-icon shadow
      // has nothing to contrast against. See the maskable entry above before
      // changing either of these. Note theme_color here is only the manifest
      // default - while the app runs, useAppearance() owns the reactive
      // <meta name="theme-color"> and swaps it with the colour mode.
      theme_color: "#09090b",
      background_color: "#09090b",
      description: brand.manifestDescription,
      ...brandIcons,
    },
    workbox: {
      cleanupOutdatedCaches: true,
      skipWaiting: true,
      clientsClaim: true,
      navigateFallback: null,
      // NEVER precache html: every route is SSR, so its chunk references stay
      // current only when the HTML comes fresh from the network. A precached
      // page would keep pointing at chunks a later deploy already removed (404).
      // The build emits no .html today, so this is a guard against a future
      // prerender quietly slipping into the precache.
      globPatterns: ["**/*.{js,css,png,svg,ico}"],
      // Raise Workbox's default 2 MiB precache cap so large JS chunks precache
      // cleanly (the default failed the Cloudflare build: "Assets exceeding the
      // limit ... won't be precached").
      maximumFileSizeToCacheInBytes: 5 * 1024 * 1024,
    },
    // No injectManifest block: `strategies` is unset, so vite-plugin-pwa runs
    // generateSW and reads `workbox` above — an injectManifest block would never
    // be read.
    client: {
      // The plugin calls preventDefault() on beforeinstallprompt whenever this
      // is true, which suppresses the browser's own install affordance. Flip it
      // back to true ONLY together with a component that consumes
      // $pwa.showInstallPrompt / $pwa.install(); until then, `false` leaves the
      // mini-infobar (Android) and address-bar install icon (desktop) in place.
      installPrompt: false,
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
    hooks: {
      /**
       * Minify the worker bundle with esbuild instead of terser.
       *
       * Terser is the single slowest, least predictable part of the build.
       * Nitro runs it over the ~490 server chunks (11 MB) as the last step, and
       * that step is the only one that varies between otherwise identical
       * builds: it took 3m44s on 2026-08-06's green build and over 28 minutes on
       * the two after it, both of which Cloudflare killed at its 30-minute
       * timeout. esbuild does the same job in seconds, which puts the whole
       * build far away from that ceiling. Vite already minifies this app's
       * client bundle with esbuild, so this only aligns the server half.
       *
       * `keepNames` matches the `mangle.keep_fnames` / `mangle.keep_classnames`
       * that nitro passes to terser, and es2019 is the target its esbuild
       * transform pass already uses (nitropack/dist/rollup/index.mjs).
       */
      "rollup:before"(_nitro, rollupConfig) {
        const plugins = rollupConfig.plugins as { name?: string }[];
        const index = plugins.findIndex((plugin) => plugin?.name === "terser");

        if (index === -1) {
          return;
        }

        const { transform } = createRequire(import.meta.url)("esbuild");

        const minifier = {
          name: "esbuild-minify",
          async renderChunk(
            code: string,
            _chunk: unknown,
            outputOptions: { sourcemap?: boolean | string }
          ) {
            const result = await transform(code, {
              loader: "js",
              target: "es2019",
              minify: true,
              keepNames: true,
              sourcemap: Boolean(outputOptions.sourcemap),
            });

            return { code: result.code, map: result.map || null };
          },
        };

        plugins[index] = minifier;
      },
    },
  },

  compatibilityDate: "2025-09-16",

  experimental: {
    viewTransition: true,
    emitRouteChunkError: "automatic-immediate",
  },
});
