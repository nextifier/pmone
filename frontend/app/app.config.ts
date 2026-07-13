import brand from "#brand/meta";

const isProduction = process.env.NODE_ENV === "production";

const app = {
  brandId: brand.id,
  name: brand.name,
  shortName: brand.shortName,
  url: isProduction ? brand.siteUrl : "http://localhost:3000",
  company: brand.company,
};

const settings = {
  blog: {
    showPostCardAuthor: true,
    showPostCardExcerpt: false,
  },
  ogImage: {
    isDarkMode: true,
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
