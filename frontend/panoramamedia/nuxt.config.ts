import tailwindcss from "@tailwindcss/vite";

export default defineNuxtConfig({
  devtools: {
    enabled: true,
    componentInspector: false,
  },

  nitro: {
    prerender: {
      crawlLinks: true,
      ignore: ["/news", "/news/**"],
    },
  },

  routeRules: {
    "/blog/**": { redirect: "/news/**", statusCode: 301 },
  },

  app: {
    head: {
      title: "Panorama Media",
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
    // "@zadigetvoltaire/nuxt-gtm",
    // "nuxt-disqus",
  ],

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
    componentDir: "./components/ui",
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
    provider: process.env.NODE_ENV === "production" ? "ipxStatic" : "ipx",
    // provider: process.env.NODE_ENV === "production" ? "cloudflare" : "ipx",
    // cloudflare: {
    //   baseURL: "https://morefoodexpo.com",
    // },
    quality: 85,
    format: ["webp"],
    // domains: ["blog.levenium.com"],
  },

  site: {
    name: "Panorama Media",
    url:
      process.env.NODE_ENV === "production"
        ? "https://panoramaevents.id"
        : "http://localhost:3000",
  },

  robots: {
    disallow: ["/terms", "/privacy", "/winner"],
  },

  sitemap: {
    sources: ["/api/sitemap-urls"],
  },

  schemaOrg: {
    enabled: false,
  },

  linkChecker: {
    enabled: false,
  },

  gtag: {
    loadingStrategy: "defer",
    tags: [
      {
        id: "G-4ZNWF3G5DM",
        enabled: true,
      },
    ],
  },

  // gtm: {
  //   defer: true,
  //   id: "GTM-MPMTC993",
  // },

  // disqus: {
  //   shortname: "pmone",
  // },

  compatibilityDate: "2025-05-01",

  experimental: {
    viewTransition: true,
  },
});
