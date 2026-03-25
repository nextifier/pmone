<template>
  <div>
    <!-- Search trigger button -->
    <button
      class="border-border bg-muted/50 hover:bg-muted text-muted-foreground flex h-8 items-center gap-x-2 rounded-lg border px-3 text-sm tracking-tight transition"
      @click="open = true"
    >
      <Icon name="lucide:search" class="size-3.5" />
      <span class="hidden sm:inline">Search docs...</span>
      <KbdGroup class="hidden sm:inline-flex">
        <Kbd>{{ metaSymbol }} K</Kbd>
      </KbdGroup>
    </button>

    <!-- Command dialog -->
    <CommandDialog v-model:open="open">
      <CommandInput placeholder="Search documentation..." />
      <CommandList>
        <CommandEmpty>No results found.</CommandEmpty>
        <CommandGroup
          v-for="group in groupedDocs"
          :key="group.label"
          :heading="group.label"
        >
          <CommandItem
            v-for="doc in group.docs"
            :key="doc.slug"
            :value="doc.title"
            class="tracking-tight"
            @select="navigateToDoc(doc.slug)"
          >
            <Icon name="lucide:arrow-right" class="mr-2 size-4" />
            <span>{{ doc.title }}</span>
          </CommandItem>
        </CommandGroup>
      </CommandList>
    </CommandDialog>
  </div>
</template>

<script setup>
const open = ref(false);
const router = useRouter();
const { metaSymbol } = useShortcuts();

// Fetch docs list independently (cached on server)
const { data: listData } = useLazyFetch("/api/docs");

const docs = computed(() => {
  const posts = listData.value?.data || [];
  return posts.map((p) => ({
    slug: p.slug,
    title: p.title,
    tags: p.tags?.map((t) => (typeof t === "string" ? t : t.name)) || [],
  }));
});

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
    const categoryTag =
      doc.tags?.find((t) => t !== "docs" && t !== "en" && t !== "zh") || "uncategorized";

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

function navigateToDoc(slug) {
  open.value = false;
  router.push(`/docs/${slug}`);
}

// Keyboard shortcut: Cmd+K / Ctrl+K
defineShortcuts({
  meta_k: {
    handler: () => {
      open.value = !open.value;
    },
  },
});
</script>
