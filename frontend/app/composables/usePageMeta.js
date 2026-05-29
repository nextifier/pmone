export const usePageMeta = (pageKey, overrides = {}) => {
  const pageStore = useContentStore();
  const route = useRoute();

  const meta = pageKey ? pageStore.getMetaByKey(pageKey) : null;

  // Support both plain values and computed/ref values
  const title = computed(() => toValue(overrides.title) || meta?.title || "");
  const description = computed(() => toValue(overrides.description) || meta?.description || "");

  useSeoMeta({
    titleTemplate: meta?.withoutTitleTemplate || overrides.withoutTitleTemplate ? "%s" : "%s · %siteName",
    title: title,
    ogTitle: title,
    description: description,
    ogDescription: description,
    ogUrl: useAppConfig().app.url + route.fullPath,
    twitterCard: "summary_large_image",
    twitterTitle: title,
    twitterDescription: description,
  });

  // Only generate OG images for genuinely public, share-worthy pages. Admin /
  // auth pages (everything behind sanctum) must NOT register an OG image: it is
  // wasted build-time Takumi rendering (the source of the build hang) and those
  // pages are never shared publicly. We allowlist by path prefix because auth on
  // nested admin routes is inherited from parent layouts (e.g.
  // projects/[username].vue), so route.meta.middleware is not reliable on the leaf.
  const PUBLIC_OG_PREFIXES = [
    "/news",
    "/docs",
    "/hotels",
    "/accommodation",
    "/p",
    "/f",
    "/forms",
    "/privacy",
    "/terms",
  ];
  const isPublicOgRoute =
    route.path === "/" ||
    PUBLIC_OG_PREFIXES.some(
      (prefix) => route.path === prefix || route.path.startsWith(prefix + "/"),
    );

  if (meta?.ogImage) {
    useSeoMeta({
      ogImage: meta.ogImage,
    });
  } else if (isPublicOgRoute) {
    // Sanitize values for OG image URL to prevent unsafe attribute errors.
    // nuxt-og-image v6 uses comma-separated URL params and doesn't properly
    // encode special characters (?,!,commas) which breaks Vue server renderer.
    const sanitize = (val) => (val || "").replace(/[?,!]/g, "").replace(/,/g, " ");
    const ogTitle = computed(() => sanitize(toValue(title)));
    const ogDescription = computed(() => sanitize(toValue(description)));

    defineOgImage("Page", {
      headline: useAppConfig().app.name,
      pageTitle: ogTitle,
      pageDescription: ogDescription,
      title: ogTitle,
      description: ogDescription,
    });
  }

  const structuredData = {
    "@context": "https://schema.org",
    "@type": "WebSite",
    name: useAppConfig().app.name,
    url: useAppConfig().app.url,
    alternateName: useAppConfig().app.name,
  };

  useHead({
    script: [
      {
        type: "application/ld+json",
        innerHTML: JSON.stringify(structuredData),
      },
    ],
  });

  return { title, description };
};
