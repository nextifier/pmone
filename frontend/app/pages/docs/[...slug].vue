<template>
  <DocsSidebar :grouped-docs="groupedDocs" :current-slug="currentPath" :pending="!allDocs" />
  <SidebarInset>
    <DocsHeader />
    <div class="min-h-screen-offset">
      <div class="container-wider">
        <!-- Content area -->
        <div class="min-w-0 flex-1">
          <!-- Not found -->
          <div v-if="!doc && !isDocsRoot && docStatus === 'success'" class="my-6 min-w-0 flex-1 sm:p-10">
            <div class="mx-auto max-w-2xl text-center">
              <h1 class="text-primary text-3xl font-semibold tracking-tighter">Page not found</h1>
              <p class="text-muted-foreground mt-3 tracking-tight">
                The documentation page you're looking for doesn't exist.
              </p>
              <Button
                v-if="groupedDocs?.[0]?.docs?.[0]?.path"
                :to="groupedDocs[0].docs[0].path"
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
                <!-- Title -->
                <Skeleton class="h-10 w-3/4" />
                <!-- Description -->
                <Skeleton class="mt-3 h-5 w-full" />
                <Skeleton class="mt-2 h-5 w-2/3" />

                <!-- Content paragraphs -->
                <div class="mt-8 space-y-4">
                  <Skeleton class="h-4 w-full" />
                  <Skeleton class="h-4 w-full" />
                  <Skeleton class="h-4 w-5/6" />
                  <Skeleton class="h-4 w-full" />
                  <Skeleton class="h-4 w-3/4" />
                </div>

                <!-- Heading -->
                <Skeleton class="mt-10 h-7 w-1/2" />
                <div class="mt-4 space-y-4">
                  <Skeleton class="h-4 w-full" />
                  <Skeleton class="h-4 w-full" />
                  <Skeleton class="h-4 w-4/5" />
                  <Skeleton class="h-4 w-full" />
                  <Skeleton class="h-4 w-2/3" />
                </div>

                <!-- Heading -->
                <Skeleton class="mt-10 h-7 w-2/5" />
                <div class="mt-4 space-y-4">
                  <Skeleton class="h-4 w-full" />
                  <Skeleton class="h-4 w-full" />
                  <Skeleton class="h-4 w-5/6" />
                  <Skeleton class="h-4 w-3/4" />
                </div>

                <!-- Heading -->
                <Skeleton class="mt-10 h-7 w-1/3" />
                <div class="mt-4 space-y-4">
                  <Skeleton class="h-4 w-full" />
                  <Skeleton class="h-4 w-full" />
                  <Skeleton class="h-4 w-4/5" />
                  <Skeleton class="h-4 w-full" />
                  <Skeleton class="h-4 w-2/3" />
                  <Skeleton class="h-4 w-full" />
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
                v-if="doc.description"
                class="text-muted-foreground mx-auto mt-3 max-w-2xl text-base tracking-tight text-pretty sm:text-lg"
              >
                {{ doc.description }}
              </p>

              <div
                :id="contentId"
                class="format-html prose-headings:scroll-mt-[calc(var(--navbar-height-mobile)+var(--scroll-offset,4rem))] lg:prose-headings:scroll-mt-[calc(var(--navbar-height-desktop)+var(--scroll-offset,2rem))] mx-auto mt-8"
                @click="onHeadingLinkClick"
              >
                <ContentRenderer :value="doc" />
              </div>

              <!-- Prev/Next navigation -->
              <div
                v-if="prevDoc || nextDoc"
                class="mx-auto mt-12 flex max-w-2xl items-center justify-between"
              >
                <NuxtLink
                  v-if="prevDoc"
                  :to="prevDoc.path"
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
                  :to="nextDoc.path"
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
  middleware: (to) => {
    if (to.path === "/docs" || to.path === "/docs/") {
      return navigateTo("/docs/staff/getting-started/dashboard-overview", { replace: true, redirectCode: 302 });
    }
  },
});

const route = useRoute();

const currentPath = computed(() => route.path);
const contentId = computed(() => currentPath.value.replace(/\//g, "-").replace(/^-/, ""));

// Prevent heading anchor clicks from going through Vue Router (causes scrollBehavior warning)
function onHeadingLinkClick(e) {
  const link = e.target.closest(":is(h1, h2, h3, h4, h5, h6) a[href^='#']");
  if (link) {
    e.preventDefault();
  }
}


// Fetch all docs for sidebar
const { data: allDocs } = await useAsyncData("docs-list", () =>
  queryCollection("docs")
    .select("title", "path", "section", "audience", "order", "locale")
    .where("locale", "=", "en")
    .order("order", "ASC")
    .all(),
);

// Fetch current doc
const { data: doc, status: docStatus } = await useAsyncData(`doc-${currentPath.value}`, () =>
  queryCollection("docs").path(currentPath.value).first(),
  { watch: [currentPath] },
);

// Page meta
usePageMeta(null, {
  title: computed(() => (doc.value?.title ? `${doc.value.title} · Docs` : "Docs")),
});

// --- Sidebar nav grouping logic ---

function mapToCategory(audience, section) {
  if (audience === "staff" && section === "getting-started") return "getting-started";
  if (audience === "staff") return "staff-guide";
  return "exhibitor-guide";
}

const categoryOrder = ["getting-started", "staff-guide", "exhibitor-guide", "advanced"];

const categoryLabels = {
  "getting-started": "Getting Started",
  "staff-guide": "Staff Guide",
  "exhibitor-guide": "Exhibitor Guide",
  advanced: "Advanced",
};

const groupedDocs = computed(() => {
  if (!allDocs.value) return [];

  const groups = {};

  allDocs.value.forEach((doc) => {
    const category = mapToCategory(doc.audience, doc.section);

    if (!groups[category]) {
      groups[category] = {
        label: categoryLabels[category] || category,
        order: categoryOrder.indexOf(category),
        docs: [],
      };
    }
    groups[category].docs.push({
      title: doc.title,
      path: doc.path,
    });
  });

  return Object.values(groups).sort((a, b) => {
    const orderA = a.order === -1 ? 999 : a.order;
    const orderB = b.order === -1 ? 999 : b.order;
    return orderA - orderB;
  });
});

// Prev/Next based on grouped order
const flatDocs = computed(() => groupedDocs.value.flatMap((g) => g.docs));
const currentFlatIndex = computed(() => flatDocs.value.findIndex((d) => d.path === currentPath.value));
const prevDoc = computed(() => currentFlatIndex.value > 0 ? flatDocs.value[currentFlatIndex.value - 1] : null);
const nextDoc = computed(() => currentFlatIndex.value < flatDocs.value.length - 1 ? flatDocs.value[currentFlatIndex.value + 1] : null);

const isDocsRoot = computed(() => route.path === "/docs" || route.path === "/docs/");
</script>
