<template>
  <div>
    <!-- Search trigger button. `hover:bg-border` rather than upstream's
         `dark:bg-card` pair: on this palette `--card` sits only 0.03 above
         `--background`, so the button would vanish into the header. -->
    <button
      class="bg-muted hover:bg-border text-muted-foreground flex h-8 w-full max-w-xs items-center gap-x-2 justify-self-end rounded-lg border-none px-2.5 text-sm tracking-tight transition-colors"
      @click="open = true"
    >
      <Icon name="hugeicons:search-01" class="size-4 shrink-0" />
      <span class="truncate">Search docs</span>
      <KbdGroup class="ml-auto hidden shrink-0 sm:inline-flex">
        <Kbd>{{ metaSymbol }} K</Kbd>
      </KbdGroup>
    </button>

    <!-- Command dialog -->
    <CommandDialog v-model:open="open">
      <Command>
        <CommandInput placeholder="Search documentation" />
        <CommandList class="h-[50vh]! max-h-[50vh]">
          <CommandEmpty>No results found.</CommandEmpty>
          <CommandGroup v-for="group in groupedDocs" :key="group.label" :heading="group.label">
            <CommandItem
              v-for="doc in group.docs"
              :key="doc.slug"
              :value="doc.title"
              class="tracking-tight"
              @select="navigateToDoc(doc.slug)"
            >
              <Icon name="hugeicons:arrow-right-02" class="mr-2 size-4" />
              <span>{{ doc.title }}</span>
            </CommandItem>
          </CommandGroup>
        </CommandList>
      </Command>
    </CommandDialog>
  </div>
</template>

<script setup>
const open = ref(false);
const router = useRouter();
const { metaSymbol } = useShortcuts();
// Fetch docs list via server API
const { data: listData } = useLazyFetch("/api/docs", { key: "docs-search" });

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
  const posts = listData.value?.data || [];
  if (!posts.length) return [];

  const groups = {};

  posts.forEach((post) => {
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

async function navigateToDoc(slug) {
  await router.push(`/docs/${slug}`);
  open.value = false;
}

// Keyboard shortcut: Cmd+K / Ctrl+K
// `usingInput: true` because the palette focuses its input as soon as it opens;
// without it the shortcut is suppressed and ⌘K could only ever open the dialog.
defineShortcuts({
  meta_k: {
    usingInput: true,
    handler: () => {
      open.value = !open.value;
    },
  },
});
</script>
