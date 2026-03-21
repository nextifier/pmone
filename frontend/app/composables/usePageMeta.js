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

  if (meta?.ogImage) {
    useSeoMeta({
      ogImage: meta.ogImage,
    });
  } else {
    defineOgImage("Page", {
      headline: useAppConfig().app.name,
      title: title.value,
      description: description.value,
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
