<template>
  <DocsSidebar :grouped-docs="groupedDocs" :current-slug="currentSlug" :pending="listPending" />
  <SidebarInset>
    <DocsHeader />
    <div class="min-h-screen-offset">
      <div class="container-wider">
        <!-- Content area -->
        <div class="min-w-0 flex-1">
          <!-- Not found -->
          <div v-if="!doc && docStatus === 'success'" class="my-6 min-w-0 flex-1 sm:p-10">
            <div class="mx-auto max-w-2xl text-center">
              <h1 class="text-primary text-3xl font-semibold tracking-tighter">Page not found</h1>
              <p class="text-muted-foreground mt-3 tracking-tight">
                The documentation page you're looking for doesn't exist.
              </p>
              <Button
                v-if="groupedDocs?.[0]?.docs?.[0]?.slug"
                :to="`/docs/${groupedDocs[0].docs[0].slug}`"
                variant="outline"
                class="mt-6 tracking-tight"
              >
                Go to docs home
              </Button>
            </div>
          </div>

          <!-- Loading skeleton -->
          <div v-else-if="!doc" class="relative flex items-start gap-x-4">
            <div class="my-6 min-w-0 flex-1 sm:p-10">
              <div class="mx-auto max-w-2xl">
                <Skeleton class="h-10 w-3/4" />
                <Skeleton class="mt-3 h-5 w-full" />
                <Skeleton class="mt-2 h-5 w-2/3" />
                <div class="mt-8 space-y-4">
                  <Skeleton class="h-4 w-full" />
                  <Skeleton class="h-4 w-full" />
                  <Skeleton class="h-4 w-5/6" />
                  <Skeleton class="h-4 w-full" />
                  <Skeleton class="h-4 w-3/4" />
                </div>
                <Skeleton class="mt-10 h-7 w-1/2" />
                <div class="mt-4 space-y-4">
                  <Skeleton class="h-4 w-full" />
                  <Skeleton class="h-4 w-full" />
                  <Skeleton class="h-4 w-4/5" />
                  <Skeleton class="h-4 w-full" />
                  <Skeleton class="h-4 w-2/3" />
                </div>
                <Skeleton class="mt-10 h-7 w-2/5" />
                <div class="mt-4 space-y-4">
                  <Skeleton class="h-4 w-full" />
                  <Skeleton class="h-4 w-full" />
                  <Skeleton class="h-4 w-5/6" />
                  <Skeleton class="h-4 w-3/4" />
                </div>
              </div>
            </div>
            <div class="hidden w-[220px] shrink-0 py-8 xl:block">
              <Skeleton class="mb-3 h-4 w-24" />
              <div class="space-y-2">
                <Skeleton class="h-3.5 w-32" />
                <Skeleton class="h-3.5 w-28" />
                <Skeleton class="h-3.5 w-36" />
                <Skeleton class="h-3.5 w-24" />
                <Skeleton class="h-3.5 w-30" />
                <Skeleton class="h-3.5 w-20" />
              </div>
            </div>
          </div>

          <div v-else class="relative flex items-start gap-x-4">
            <!-- Main content card -->
            <main class="my-6 min-w-0 flex-1 sm:p-10">
              <h1
                class="text-primary mx-auto max-w-2xl text-3xl font-semibold tracking-tighter sm:text-4xl lg:text-[2.5rem]"
              >
                {{ doc.title }}
              </h1>

              <p
                v-if="doc.excerpt"
                class="text-muted-foreground mx-auto mt-3 max-w-2xl text-base tracking-tight text-pretty sm:text-lg"
              >
                {{ doc.excerpt }}
              </p>

              <div
                :id="contentId"
                class="format-html prose-headings:scroll-mt-[calc(var(--navbar-height-mobile)+var(--scroll-offset,4rem))] lg:prose-headings:scroll-mt-[calc(var(--navbar-height-desktop)+var(--scroll-offset,2rem))] mx-auto mt-8"
                v-html="doc.content"
              />

              <!-- Prev/Next navigation -->
              <div
                v-if="prevDoc || nextDoc"
                class="mx-auto mt-12 flex max-w-2xl items-center justify-between"
              >
                <NuxtLink
                  v-if="prevDoc"
                  :to="`/docs/${prevDoc.slug}`"
                  class="text-muted-foreground hover:text-foreground group inline-flex items-center gap-x-1 text-sm tracking-tight transition"
                >
                  <Icon
                    name="lucide:chevron-left"
                    class="size-4 transition group-hover:-translate-x-0.5"
                  />
                  <span>{{ prevDoc.title }}</span>
                </NuxtLink>
                <div v-else />

                <NuxtLink
                  v-if="nextDoc"
                  :to="`/docs/${nextDoc.slug}`"
                  class="text-muted-foreground hover:text-foreground group ml-auto inline-flex items-center gap-x-1 text-sm tracking-tight transition"
                >
                  <span>{{ nextDoc.title }}</span>
                  <Icon
                    name="lucide:chevron-right"
                    class="size-4 transition group-hover:translate-x-0.5"
                  />
                </NuxtLink>
              </div>
            </main>

            <!-- Right sidebar: On this page TOC (desktop) -->
            <aside
              class="sticky top-[var(--navbar-height-desktop)] hidden h-[calc(100vh-var(--navbar-height-desktop))] w-[220px] shrink-0 overflow-y-auto py-8 xl:block"
            >
              <ScrollSpy :content-selector="`#${contentId}`" />
            </aside>
          </div>
        </div>
      </div>
    </div>
  </SidebarInset>
</template>

<script setup>
definePageMeta({
  layout: "docs",
});

const route = useRoute();
const currentSlug = computed(() => route.params.slug || "");
const contentId = computed(() => `doc-${currentSlug.value}`);

// Fetch all docs for sidebar
const { data: listData, pending: listPending } = useLazyFetch("/api/docs");

const allDocs = computed(() => listData.value?.data || []);

// Redirect to first doc if no slug
watch(
  [allDocs, currentSlug],
  ([docs, slug]) => {
    if (!slug && docs?.length > 0) {
      const firstDoc = groupedDocs.value?.[0]?.docs?.[0];
      if (firstDoc) {
        navigateTo(`/docs/${firstDoc.slug}`, { replace: true });
      }
    }
  },
  { immediate: true },
);

// Fetch current doc (await for SSR so OG image meta is available)
const { data: docData, status: docStatus } = await useFetch(
  () => (currentSlug.value ? `/api/docs/${currentSlug.value}` : null),
  { key: computed(() => `doc-${currentSlug.value}`), watch: [currentSlug] },
);

const doc = computed(() => docData.value?.data);

// Page meta
usePageMeta(null, {
  title: computed(() => (doc.value?.title ? `${doc.value.title} · Docs` : "Docs")),
  description: computed(() => doc.value?.excerpt || ""),
});

// --- Sidebar nav grouping logic ---

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

  // Sort docs within each group by order
  Object.values(groups).forEach((g) => g.docs.sort((a, b) => a.docsOrder - b.docsOrder));

  return Object.values(groups).sort((a, b) => {
    const orderA = a.order === -1 ? 999 : a.order;
    const orderB = b.order === -1 ? 999 : b.order;
    return orderA - orderB;
  });
});

// Prev/Next based on grouped order
const flatDocs = computed(() => groupedDocs.value.flatMap((g) => g.docs));
const currentFlatIndex = computed(() => flatDocs.value.findIndex((d) => d.slug === currentSlug.value));
const prevDoc = computed(() => (currentFlatIndex.value > 0 ? flatDocs.value[currentFlatIndex.value - 1] : null));
const nextDoc = computed(() =>
  currentFlatIndex.value < flatDocs.value.length - 1 ? flatDocs.value[currentFlatIndex.value + 1] : null,
);
</script>
