<template>
  <div class="mx-auto max-w-4xl space-y-6 pt-4 pb-16">
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:tag-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Tags</h1>
      </div>
    </div>

    <TableData
      :clientOnly="clientOnly"
      ref="tableRef"
      :data="data"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="tags"
      label="Tag"
      search-column="name"
      search-placeholder="Search tags..."
      error-title="Error loading tags"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      @update:pagination="pagination = $event"
      @update:sorting="sorting = $event"
      @refresh="refresh"
    />
  </div>
</template>

<script setup>
import TableData from "@/components/TableData.vue";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

defineOptions({
  name: "tags",
});

usePageMeta(null, {
  title: "Tags",
});
const { $dayjs } = useNuxtApp();

// Table state
const pagination = ref({ pageIndex: 0, pageSize: 15 });
const sorting = ref([{ id: "posts_count", desc: true }]);

// Data state
const data = ref([]);
const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
const pending = ref(false);
const error = ref(null);

// Client-only mode flag (true = client-side pagination, false = server-side)
const clientOnly = ref(true);

// Build query params
const buildQueryParams = () => {
  const params = new URLSearchParams();

  if (clientOnly.value) {
    params.append("client_only", "true");
  } else {
    // Server-side mode: add pagination and sorting
    params.append("page", pagination.value.pageIndex + 1);
    params.append("per_page", pagination.value.pageSize);

    // Sorting
    const sortField = sorting.value[0]?.id || "posts_count";
    const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
    params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);
  }

  return params.toString();
};

// Fetch tags
const fetchTags = async () => {
  try {
    pending.value = true;
    error.value = null;
    const client = useSanctumClient();
    const response = await client(`/api/tags?${buildQueryParams()}`);
    data.value = response.data;
    meta.value = response.meta;
  } catch (err) {
    error.value = err;
    console.error("Failed to fetch tags:", err);
  } finally {
    pending.value = false;
  }
};

await fetchTags();

// Watchers for server-side mode only
watch(
  [sorting, pagination],
  () => {
    if (!clientOnly.value) {
      fetchTags();
    }
  },
  { deep: true }
);

const refresh = fetchTags;

// Table columns
const columns = [
  {
    header: "Tag",
    accessorKey: "name",
    cell: ({ row }) => {
      const tag = row.original;
      const tagName = typeof tag.name === "object" ? tag.name.en : tag.name;
      const tagSlug = typeof tag.slug === "object" ? tag.slug.en : tag.slug;

      return h(
        resolveComponent("NuxtLink"),
        {
          to: `/tags/${tagSlug}`,
          class:
            "hover:bg-muted/50 -mx-2 -my-1 flex items-center gap-x-2 rounded-md px-2 py-1 transition-colors",
        },
        {
          default: () => [
            h(resolveComponent("Icon"), {
              name: "hugeicons:tag-01",
              class: "text-muted-foreground size-4 shrink-0",
            }),
            h("span", { class: "text-sm font-medium tracking-tight" }, tagName),
          ],
        }
      );
    },
    size: 280,
    enableHiding: false,
    filterFn: (row, columnId, filterValue) => {
      const searchValue = filterValue.toLowerCase();
      const name = row.original.name;
      const tagName = typeof name === "object" ? name.en : name;
      return tagName?.toLowerCase().includes(searchValue);
    },
  },
  {
    header: "Posts",
    accessorKey: "posts_count",
    cell: ({ row }) => {
      const count = row.getValue("posts_count") || 0;
      return h(
        "div",
        { class: "flex items-center gap-x-1.5 text-sm tracking-tight text-muted-foreground" },
        [h("span", {}, count.toLocaleString())]
      );
    },
    size: 100,
    enableSorting: true,
  },
  //   {
  //     header: "Created",
  //     accessorKey: "created_at",
  //     cell: ({ row }) => {
  //       const date = row.getValue("created_at");
  //       return withDirectives(
  //         h(
  //           "div",
  //           { class: "text-sm text-muted-foreground tracking-tight" },
  //           $dayjs(date).fromNow()
  //         ),
  //         [[resolveDirective("tippy"), $dayjs(date).format("MMMM D, YYYY [at] h:mm A")]]
  //       );
  //     },
  //     size: 120,
  //     enableSorting: true,
  //   },
];

// Table ref
const tableRef = ref();
</script>
