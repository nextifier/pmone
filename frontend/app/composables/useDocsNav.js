/**
 * Shared docs navigation: fetches the docs list once (deduped by key across
 * the docs layout and the docs page) and groups it for the sidebar + prev/next.
 */
export function useDocsNav() {
  const route = useRoute();
  const currentSlug = computed(() => route.params.slug || "");

  const { data: listData, pending: listPending } = useLazyFetch("/api/docs", {
    key: "docs-list",
    // Reuse the already-fetched list on client navigation so the sidebar never
    // re-enters its pending/skeleton state (which caused a blink on every page
    // change, since both the docs layout and the page call this composable).
    getCachedData: (key, nuxtApp) => nuxtApp.payload.data[key] ?? nuxtApp.static.data[key],
  });

  const allDocs = computed(() => listData.value?.data || []);

  function mapToCategory(audience, section) {
    if (audience === "staff" && section === "getting-started") return "getting-started";
    if (audience === "staff") return "staff-guide";
    return "exhibitor-guide";
  }

  const categoryOrder = ["getting-started", "staff-guide", "exhibitor-guide"];
  const categoryLabels = {
    "getting-started": "Getting Started",
    "staff-guide": "Staff Guide",
    "exhibitor-guide": "Exhibitor Guide",
  };

  const groupedDocs = computed(() => {
    if (!allDocs.value?.length) return [];

    const groups = {};

    allDocs.value.forEach((post) => {
      const audience = post.settings?.docs_audience || "staff";
      const section = post.settings?.docs_section || "general";
      const order = post.settings?.docs_order ?? 999;
      const category = mapToCategory(audience, section);

      if (!groups[category]) {
        groups[category] = {
          label: categoryLabels[category] || category,
          order: categoryOrder.indexOf(category),
          docs: [],
        };
      }
      groups[category].docs.push({
        title: post.title,
        slug: post.slug,
        docsOrder: order,
      });
    });

    Object.values(groups).forEach((g) => g.docs.sort((a, b) => a.docsOrder - b.docsOrder));

    return Object.values(groups).sort((a, b) => {
      const orderA = a.order === -1 ? 999 : a.order;
      const orderB = b.order === -1 ? 999 : b.order;
      return orderA - orderB;
    });
  });

  const flatDocs = computed(() => groupedDocs.value.flatMap((g) => g.docs));

  return { currentSlug, listPending, groupedDocs, flatDocs };
}
