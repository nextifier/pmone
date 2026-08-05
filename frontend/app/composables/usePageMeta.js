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

  // Same shape as levenium and pmone-events: a static per-page image wins,
  // otherwise the generated Takumi card.
  //
  // `ogImage.enabled` is currently false in nuxt.config, so `defineOgImage`
  // below resolves to the module's no-op mock (it registers mocks for exactly
  // this case) — the call is free and keeps this file aligned with the other
  // two repos. Flipping `enabled: true` is all it takes to turn cards on; the
  // OgImage/Page.takumi.vue component is already in place.
  //
  // The old note here claimed OG generation needs prerendering and would break
  // the auth-aware SSR header. That only applies to `zeroRuntime: true`, which
  // is NOT the default: v6 renders on demand at /_og/d/ from the running
  // server. levenium and pmone-events are both fully SSR with no prerendering
  // and generate cards fine.
  if (meta?.ogImage) {
    useSeoMeta({
      ogImage: meta.ogImage,
    });
  } else {
    if (import.meta.dev) {
      useState(`og-image:ssr-exists:${route.path}`, () => false).value = true;
    }
    defineOgImage("Page", {
      pageTitle: title,
      pageDescription: description,
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
