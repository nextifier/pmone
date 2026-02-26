<template>
  <div class="min-h-screen-offset">
    <!-- Mobile sidebar (controlled from Header) -->
    <Sheet v-model:open="mobileOpen">
      <SheetContent side="left" class="w-72 overflow-y-auto p-6">
        <SheetTitle class="sr-only">Documentation Menu</SheetTitle>
        <SheetDescription class="sr-only">Navigate documentation pages</SheetDescription>
        <DocsSidebarNav @navigate="mobileOpen = false" />
      </SheetContent>
    </Sheet>

    <div class="container-wider">
      <div class="relative flex gap-x-2">
        <!-- Left sidebar (desktop) -->
        <aside
          class="sticky top-[var(--navbar-height-desktop)] hidden h-[calc(100vh-var(--navbar-height-desktop))] w-[220px] shrink-0 overflow-y-auto py-6 pr-2 lg:block"
        >
          <div v-if="listPending" class="space-y-5">
            <div v-for="i in 3" :key="i">
              <Skeleton class="mb-2 h-3.5 w-20" />
              <div class="space-y-1">
                <Skeleton v-for="j in i === 1 ? 4 : 3" :key="j" class="h-7 w-full rounded-md" />
              </div>
            </div>
          </div>
          <DocsSidebarNav v-else />
        </aside>

        <!-- Content area -->
        <div class="min-w-0 flex-1">
          <div v-if="docPending" class="relative flex items-start gap-x-4">
            <div
              class="border-border my-6 min-h-screen min-w-0 flex-1 sm:rounded-xl sm:border sm:p-10"
            >
              <Skeleton class="h-10 w-3/4" />
              <Skeleton class="mt-3 h-5 w-full" />
              <Skeleton class="mt-2 h-5 w-2/3" />
              <div class="mt-8 space-y-4">
                <Skeleton class="h-4 w-full" />
                <Skeleton class="h-4 w-full" />
                <Skeleton class="h-4 w-5/6" />
                <Skeleton class="h-4 w-full" />
                <Skeleton class="h-4 w-3/4" />
                <Skeleton class="mt-6 h-7 w-1/2" />
                <Skeleton class="h-4 w-full" />
                <Skeleton class="h-4 w-full" />
                <Skeleton class="h-4 w-4/5" />
                <Skeleton class="h-4 w-full" />
                <Skeleton class="h-4 w-2/3" />
                <Skeleton class="mt-6 h-7 w-2/5" />
                <Skeleton class="h-4 w-full" />
                <Skeleton class="h-4 w-full" />
                <Skeleton class="h-4 w-5/6" />
                <Skeleton class="h-4 w-full" />
                <Skeleton class="h-4 w-3/4" />
                <Skeleton class="h-4 w-full" />
                <Skeleton class="mt-6 h-7 w-1/3" />
                <Skeleton class="h-4 w-full" />
                <Skeleton class="h-4 w-full" />
                <Skeleton class="h-4 w-4/5" />
                <Skeleton class="h-4 w-full" />
                <Skeleton class="h-4 w-2/3" />
              </div>
            </div>
            <div class="hidden w-[220px] shrink-0 py-8 xl:block">
              <Skeleton class="mb-3 h-4 w-24" />
              <div class="space-y-2">
                <Skeleton class="h-3.5 w-32" />
                <Skeleton class="h-3.5 w-28" />
                <Skeleton class="h-3.5 w-36" />
                <Skeleton class="h-3.5 w-24" />
              </div>
            </div>
          </div>

          <div v-else-if="doc" class="relative flex items-start gap-x-4">
            <!-- Main content card -->
            <main class="border-border my-6 min-w-0 flex-1 sm:rounded-xl sm:border sm:p-10">
              <h1
                class="text-primary text-3xl font-semibold tracking-tighter sm:text-4xl lg:text-[2.5rem]"
              >
                {{ doc.title }}
              </h1>

              <p
                v-if="doc.excerpt"
                class="text-muted-foreground mt-3 text-base tracking-tight text-pretty sm:text-lg"
              >
                {{ doc.excerpt }}
              </p>

              <div
                class="format-html prose-headings:scroll-mt-[calc(var(--navbar-height-mobile)+var(--scroll-offset,4rem))] lg:prose-headings:scroll-mt-[calc(var(--navbar-height-desktop)+var(--scroll-offset,2rem))] mt-8"
              >
                <article :id="doc.slug" v-html="processedHtml"></article>
              </div>

              <!-- Prev/Next navigation -->
              <div
                v-if="prevDoc || nextDoc"
                class="border-border mt-12 flex items-stretch gap-4 border-t pt-6"
              >
                <NuxtLink
                  v-if="prevDoc"
                  :to="`/docs/${prevDoc.slug}`"
                  class="border-border hover:bg-muted group flex flex-1 flex-col items-start gap-y-1 rounded-lg border p-4 transition"
                >
                  <span
                    class="text-muted-foreground flex items-center gap-x-1 text-xs tracking-tight"
                  >
                    <Icon
                      name="lucide:arrow-left"
                      class="size-3.5 transition group-hover:-translate-x-0.5"
                    />
                    <span>Previous</span>
                  </span>
                  <span class="text-primary text-sm font-medium tracking-tight">{{
                    prevDoc.title
                  }}</span>
                </NuxtLink>
                <div v-else class="flex-1" />

                <NuxtLink
                  v-if="nextDoc"
                  :to="`/docs/${nextDoc.slug}`"
                  class="border-border hover:bg-muted group flex flex-1 flex-col items-end gap-y-1 rounded-lg border p-4 text-right transition"
                >
                  <span
                    class="text-muted-foreground flex items-center gap-x-1 text-xs tracking-tight"
                  >
                    <span>Next</span>
                    <Icon
                      name="lucide:arrow-right"
                      class="size-3.5 transition group-hover:translate-x-0.5"
                    />
                  </span>
                  <span class="text-primary text-sm font-medium tracking-tight">{{
                    nextDoc.title
                  }}</span>
                </NuxtLink>
                <div v-else class="flex-1" />
              </div>
            </main>

            <!-- Right sidebar: On this page TOC (desktop) -->
            <aside
              class="sticky top-[var(--navbar-height-desktop)] hidden h-[calc(100vh-var(--navbar-height-desktop))] w-[220px] shrink-0 overflow-y-auto py-8 xl:block"
            >
              <ScrollSpy :content-selector="`#${doc.slug}`" />
            </aside>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Sheet, SheetContent, SheetDescription, SheetTitle } from "@/components/ui/sheet";

const route = useRoute();

// Current slug from catch-all route
const currentSlug = computed(() => {
  const segments = route.params.slug;
  return Array.isArray(segments) ? segments[0] || "" : segments || "";
});

// Docs list (lazy - doesn't block page render)
const { data: listData, pending: listPending } = useLazyFetch("/api/docs");

const docs = computed(() => {
  const posts = listData.value?.data || [];
  return posts.map((p) => ({
    slug: p.slug,
    title: p.title,
    tags: p.tags?.map((t) => (typeof t === "string" ? t : t.name)) || [],
  }));
});

// Redirect /docs to first doc
watch(
  [docs, currentSlug],
  ([docsList, slug]) => {
    if (!slug && docsList?.length > 0) {
      navigateTo(`/docs/${docsList[0].slug}`, { replace: true });
    }
  },
  { immediate: true }
);

// Fetch single doc content
const { data: docData, pending: docPending } = useLazyFetch(
  () => (currentSlug.value ? `/api/docs/${currentSlug.value}` : null),
  {
    key: computed(() => `doc-${currentSlug.value}`),
    watch: [currentSlug],
  }
);

const doc = computed(() => docData.value?.data);

// Mobile sidebar state
const mobileOpen = useState("docs-mobile-open", () => false);

// Page meta
usePageMeta(null, {
  title: computed(() => (doc.value?.title ? `${doc.value.title} · Docs` : "Docs")),
});

// Process HTML content for heading IDs
const rawHtml = computed(() => doc.value?.content || "");
const { processedHtml } = useProcessedContent(rawHtml);

// Prev/Next navigation
const currentIndex = computed(() => docs.value.findIndex((d) => d.slug === currentSlug.value));

const prevDoc = computed(() =>
  currentIndex.value > 0 ? docs.value[currentIndex.value - 1] : null
);

const nextDoc = computed(() =>
  currentIndex.value < docs.value.length - 1 ? docs.value[currentIndex.value + 1] : null
);

// --- Sidebar nav grouping logic (inlined from DocsSidebar) ---

const categoryOrder = ["getting-started", "staff-guide", "exhibitor-guide", "advanced"];

const categoryLabels = {
  "getting-started": "Getting Started",
  "staff-guide": "Staff Guide",
  "exhibitor-guide": "Exhibitor Guide",
  advanced: "Advanced",
};

const groupedDocs = computed(() => {
  const groups = {};

  docs.value.forEach((doc) => {
    const categoryTag = doc.tags?.find((t) => t !== "docs") || "uncategorized";

    if (!groups[categoryTag]) {
      groups[categoryTag] = {
        label: categoryLabels[categoryTag] || categoryTag,
        order: categoryOrder.indexOf(categoryTag),
        docs: [],
      };
    }
    groups[categoryTag].docs.push(doc);
  });

  return Object.values(groups).sort((a, b) => {
    const orderA = a.order === -1 ? 999 : a.order;
    const orderB = b.order === -1 ? 999 : b.order;
    return orderA - orderB;
  });
});

// Sidebar nav component (replaces DocsSidebar.vue)
const DocsSidebarNav = defineComponent({
  emits: ["navigate"],
  setup(_, { emit }) {
    return () =>
      h(
        "nav",
        { class: "space-y-5" },
        groupedDocs.value.map((group) =>
          h("div", { key: group.label }, [
            h(
              "h4",
              { class: "text-foreground mb-1.5 text-xs font-medium tracking-tight" },
              group.label
            ),
            h(
              "ul",
              { class: "space-y-0.5" },
              group.docs.map((doc) =>
                h(
                  "li",
                  { key: doc.slug },
                  h(
                    resolveComponent("NuxtLink"),
                    {
                      to: `/docs/${doc.slug}`,
                      class: [
                        "block rounded-md px-2.5 py-1.5 text-[13px] leading-normal tracking-tight transition",
                        currentSlug.value === doc.slug
                          ? "bg-muted text-primary font-medium"
                          : "text-muted-foreground hover:text-foreground hover:bg-muted",
                      ],
                      onClick: () => emit("navigate"),
                    },
                    () => doc.title
                  )
                )
              )
            ),
          ])
        )
      );
  },
});
</script>
