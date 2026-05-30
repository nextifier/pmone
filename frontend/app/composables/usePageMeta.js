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

  // A static OG image can still be set per page via `meta.ogImage`. We do NOT
  // call `defineOgImage` (nuxt-og-image) because build-time OG generation
  // requires prerendering, which is disabled — the whole app is SSR so the
  // auth-aware header renders correctly per request.
  if (meta?.ogImage) {
    useSeoMeta({
      ogImage: meta.ogImage,
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
