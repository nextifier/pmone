<template>
  <div>
    <!-- Search trigger button -->
    <button
      class="border-border bg-muted/50 hover:bg-muted text-muted-foreground flex h-8 w-full max-w-xs items-center gap-x-2 justify-self-end rounded-lg border px-3 text-sm tracking-tight transition"
      @click="open = true"
    >
      <Icon name="lucide:search" class="size-3.5 shrink-0" />
      <span class="truncate">Search docs</span>
      <KbdGroup class="ml-auto hidden shrink-0 sm:inline-flex">
        <Kbd>{{ metaSymbol }} K</Kbd>
      </KbdGroup>
    </button>

    <!-- Command dialog -->
    <CommandDialog v-model:open="open">
      <CommandInput placeholder="Search documentation..." />
      <CommandList>
        <CommandEmpty>No results found.</CommandEmpty>
        <CommandGroup v-for="group in groupedDocs" :key="group.label" :heading="group.label">
          <CommandItem
            v-for="doc in group.docs"
            :key="doc.path"
            :value="doc.title"
            class="tracking-tight"
            @select="navigateToDoc(doc.path)"
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

// Fetch docs list via Nuxt Content
const { data: allDocs } = await useAsyncData("docs-search", () =>
  queryCollection("docs")
    .select("title", "path", "section", "audience", "order", "locale")
    .where("locale", "=", "en")
    .order("order", "ASC")
    .all()
);

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

function navigateToDoc(path) {
  open.value = false;
  router.push(path);
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
