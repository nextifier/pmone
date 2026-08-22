import brand from "#brand/meta";

const isProduction = process.env.NODE_ENV === "production";

const app = {
  brandId: brand.id,
  name: brand.name,
  shortName: brand.shortName,
  url: isProduction ? brand.siteUrl : "http://localhost:3000",
  company: brand.company,
  // Mirrors BrandMeta.assetsReady so SHARED runtime code can apply the same
  // guard nuxt.config applies at build time (brandIcons / brandHeadLinks):
  // while a brand has no real icons, nothing may reference public/brands/<id>/.
  // Read by useDynamicFavicon(). Keep the name identical to the source field —
  // one concept, one word, so the two guards cannot drift apart conceptually.
  assetsReady: brand.assetsReady,
};

const settings = {
  blog: {
    showPostCardAuthor: true,
    showPostCardExcerpt: false,
  },
  ogImage: {
    isDarkMode: true,
    // Multi-brand: each brand serves its own icon. levenium and pmone-events
    // are single-brand and leave this unset, which falls back to
    // /icons/icon-192x192.png inside OgImage/Page.takumi.vue — that fallback is
    // what keeps the component byte-identical across the three repos.
    icon: `/brands/${brand.id}/icons/icon-192x192.png`,
  },
  terms: {
    lastUpdate: "August 21, 2025",
  },
};

const contact = brand.contact;

const routes = {
  docs: {
    label: "Docs",
    path: "/docs",
  },
};

export default defineAppConfig({
  app: app,
  settings: settings,
  contact: contact,
  organizationOptions: brand.organizationOptions,
  buildDate: new Date().toISOString(),

  routes: {
    header: [routes.docs],
  },
});
