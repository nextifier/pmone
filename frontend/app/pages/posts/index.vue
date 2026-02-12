<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:task-edit-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Posts</h1>
      </div>

      <div v-if="!hasSelectedRows" class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <!-- Export Dropdown Menu -->
        <Popover>
          <PopoverTrigger asChild>
            <button
              class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
              :disabled="exportPending || exportImagesPending"
            >
              <Spinner v-if="exportPending || exportImagesPending" class="size-4 shrink-0" />
              <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
              <span>Export</span>
              <Icon name="lucide:chevron-down" class="size-3.5 shrink-0 opacity-60" />
            </button>
          </PopoverTrigger>
          <PopoverContent align="end" class="w-56 p-1">
            <div class="flex flex-col">
              <!-- Export Data (CSV) -->
              <PopoverClose asChild>
                <button
                  @click="handleExport"
                  :disabled="exportPending || exportImagesPending"
                  class="hover:bg-muted flex items-center gap-x-2 rounded-md px-3 py-2 text-left text-sm tracking-tight disabled:opacity-50"
                >
                  <Icon name="hugeicons:csv-02" class="size-4 shrink-0" />
                  <div class="flex flex-col">
                    <span>Export Data</span>
                    <span class="text-muted-foreground text-xs">Download as CSV</span>
                  </div>
                </button>
              </PopoverClose>

              <!-- Export with Images (ZIP) -->
              <PopoverClose asChild>
                <button
                  @click="handleExportImages"
                  :disabled="exportPending || exportImagesPending"
                  class="hover:bg-muted flex items-center gap-x-2 rounded-md px-3 py-2 text-left text-sm tracking-tight disabled:opacity-50"
                >
                  <Icon name="hugeicons:image-download" class="size-4 shrink-0" />
                  <div class="flex flex-col">
                    <span>Export with Images</span>
                    <span class="text-muted-foreground text-xs">Data + images as ZIP</span>
                  </div>
                </button>
              </PopoverClose>
            </div>
          </PopoverContent>
        </Popover>

        <nuxt-link
          to="/posts/analytics"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:analytics-01" class="size-4 shrink-0" />
          <span>Analytics</span>
        </nuxt-link>

        <nuxt-link
          to="/tags"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:tag-01" class="size-4 shrink-0" />
          <span>Tags</span>
        </nuxt-link>

        <nuxt-link
          v-if="canDelete"
          to="/posts/trash"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
          <span>Trash</span>
        </nuxt-link>
      </div>

      <div v-else class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <button
          @click="clearSelection"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="lucide:x" class="size-4 shrink-0" />
          <span>Clear selection</span>
        </button>
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
      model="posts"
      label="Post"
      search-column="title"
      search-placeholder="Search posts..."
      error-title="Error loading posts"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
      :initial-column-visibility="{ status: false, media_count: false }"
      :show-add-button="canCreate"
      @update:pagination="onPaginationUpdate"
      @update:sorting="onSortingUpdate"
      @update:column-filters="onColumnFiltersUpdate"
      @refresh="refresh"
    >
      <template #filters="{ table }">
        <Popover>
          <PopoverTrigger asChild>
            <button
              class="hover:bg-muted relative flex aspect-square h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border text-sm tracking-tight active:scale-98 sm:aspect-auto sm:px-2.5"
            >
              <Icon name="lucide:list-filter" class="size-4 shrink-0" />
              <span class="hidden sm:flex">Filter</span>
              <span
                v-if="totalActiveFilters > 0"
                class="bg-primary text-primary-foreground squircle absolute top-0 right-0 inline-flex size-4 translate-x-1/2 -translate-y-1/2 items-center justify-center text-[11px] font-medium tracking-tight"
              >
                {{ totalActiveFilters }}
              </span>
            </button>
          </PopoverTrigger>
          <PopoverContent class="w-auto min-w-48 p-3 pb-4.5" align="end">
            <div class="space-y-4">
              <FilterSection
                title="Status"
                :options="[
                  { label: 'Draft', value: 'draft' },
                  { label: 'Published', value: 'published' },
                  { label: 'Scheduled', value: 'scheduled' },
                  { label: 'Archived', value: 'archived' },
                ]"
                :selected="selectedStatuses"
                @change="handleFilterChange('status', $event)"
              />
              <FilterSection
                v-if="authorOptions.length > 0"
                title="Author"
                :options="authorOptions"
                :selected="selectedAuthors"
                @change="handleFilterChange('creator', $event)"
              />
            </div>
          </PopoverContent>
        </Popover>
      </template>

      <template #actions="{ selectedRows }">
        <DialogResponsive
          v-if="canDelete && selectedRows.length > 0"
          v-model:open="deleteDialogOpen"
          class="h-full"
        >
          <template #trigger="{ open }">
            <button
              class="hover:bg-muted flex h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border px-2.5 text-sm tracking-tight active:scale-98"
              @click="open()"
            >
              <Icon name="lucide:trash" class="size-4 shrink-0" />
              <span class="text-sm tracking-tight">Delete</span>
              <span
                class="text-muted-foreground/80 -me-1 inline-flex h-5 max-h-full items-center rounded border px-1 font-[inherit] text-[0.625rem] font-medium"
              >
                {{ selectedRows.length }}
              </span>
            </button>
          </template>
          <template #default>
            <div class="px-4 pb-10 md:px-6 md:py-5">
              <div class="text-primary text-lg font-semibold tracking-tight">Are you sure?</div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This action can't be undone. This will permanently delete
                {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "post" : "posts" }}.
              </p>
              <div class="mt-3 flex justify-end gap-2">
                <button
                  class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                  @click="deleteDialogOpen = false"
                  :disabled="deletePending"
                >
                  Cancel
                </button>
                <button
                  @click="handleDeleteRows(selectedRows)"
                  :disabled="deletePending"
                  class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  <Spinner v-if="deletePending" class="size-4 text-white" />
                  <span v-else>Delete</span>
                </button>
              </div>
            </div>
          </template>
        </DialogResponsive>
      </template>
    </TableData>
  </div>
</template>

<script setup>
import DialogResponsive from "@/components/DialogResponsive.vue";
import PostTableItem from "@/components/post/TableItem.vue";
import TableData from "@/components/TableData.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { PopoverClose } from "reka-ui";
import { resolveDirective, withDirectives } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["posts.read"],
  layout: "app",
});

usePageMeta(null, {
  title: "Posts",
});

defineOptions({
  name: "posts",
});

const { $dayjs } = useNuxtApp();
const { formatDate } = useFormatters();
const { getRefreshSignal, clearRefreshSignal } = useDataRefresh();

// Permission checking using composable
const { hasPermission, canEditPost, canDeletePost } = usePermission();

// Permission checks for buttons
const canCreate = computed(() => hasPermission("posts.create"));
const canDelete = computed(() => hasPermission("posts.delete"));

// Table state
const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 15 });
const sorting = ref([{ id: "published_at", desc: true }]);

// Data state
// Client-only mode flag
const clientOnly = ref(false);

// Build query params
const buildQueryParams = () => {
  const params = new URLSearchParams();

  if (clientOnly.value) {
    params.append("client_only", "true");
  } else {
    params.append("page", pagination.value.pageIndex + 1);
    params.append("per_page", pagination.value.pageSize);

    // Filters
    const filters = {
      title: "filter_search",
      status: "filter_status",
      creator: "filter_creator",
    };

    Object.entries(filters).forEach(([columnId, paramKey]) => {
      const filter = columnFilters.value.find((f) => f.id === columnId);
      if (filter?.value) {
        const value = Array.isArray(filter.value) ? filter.value.join(",") : filter.value;
        params.append(paramKey, value);
      }
    });

    // Sorting
    const sortField = sorting.value[0]?.id || "published_at";
    const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
    params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);
  }

  return params.toString();
};

// Fetch posts with lazy loading
const {
  data: postsResponse,
  pending,
  error,
  refresh: fetchPosts,
} = await useLazySanctumFetch(() => `/api/posts?${buildQueryParams()}`, {
  key: "posts-list",
  watch: false,
});

const data = computed(() => postsResponse.value?.data || []);
const meta = computed(
  () => postsResponse.value?.meta || { current_page: 1, last_page: 1, per_page: 15, total: 0 }
);

// Fetch eligible authors for filter
const { data: authorsResponse } = await useLazySanctumFetch("/api/posts/eligible-authors", {
  key: "posts-eligible-authors",
});
const authorOptions = computed(() => {
  const authors = (authorsResponse.value?.data || []).map((author) => ({
    label: `${author.name} (${author.posts_count || 0})`,
    value: author.id,
  }));
  // Add "No Author" option at the end if there are posts without author
  const noAuthorCount = authorsResponse.value?.no_author_count || 0;
  if (noAuthorCount > 0) {
    authors.push({ label: `No Author (${noAuthorCount})`, value: "none" });
  }
  return authors;
});

// Watch for changes and refetch (only in server-side mode)
watch(
  [columnFilters, sorting, pagination],
  () => {
    if (!clientOnly.value) {
      fetchPosts();
    }
  },
  { deep: true }
);

// Update handlers
const onPaginationUpdate = (newValue) => {
  pagination.value.pageIndex = newValue.pageIndex;
  pagination.value.pageSize = newValue.pageSize;
};

const onSortingUpdate = (newValue) => {
  sorting.value = newValue;
};

const onColumnFiltersUpdate = (newValue) => {
  columnFilters.value = newValue;
};

// Handle keepalive reactivation - check if data needs refresh
onActivated(async () => {
  const refreshSignal = getRefreshSignal("posts-list");
  if (refreshSignal > 0) {
    await fetchPosts();
    clearRefreshSignal("posts-list");
  }
});

const refresh = fetchPosts;

// Table columns
const columns = [
  {
    id: "select",
    header: ({ table }) =>
      h(Checkbox, {
        modelValue:
          table.getIsAllPageRowsSelected() ||
          (table.getIsSomePageRowsSelected() && "indeterminate"),
        "onUpdate:modelValue": (value) => table.toggleAllPageRowsSelected(!!value),
        "aria-label": "Select all",
      }),
    cell: ({ row }) =>
      h(Checkbox, {
        modelValue: row.getIsSelected(),
        "onUpdate:modelValue": (value) => row.toggleSelected(!!value),
        "aria-label": "Select row",
      }),
    size: 28,
    enableSorting: false,
    enableHiding: false,
  },
  {
    header: "Post",
    accessorKey: "title",
    cell: ({ row }) =>
      h(PostTableItem, {
        post: row.original,
      }),
    size: 360,
    enableHiding: false,
    filterFn: (row, columnId, filterValue) => {
      const searchValue = filterValue.toLowerCase();
      const title = row.original.title?.toLowerCase() || "";
      const excerpt = row.original.excerpt?.toLowerCase() || "";
      return title.includes(searchValue) || excerpt.includes(searchValue);
    },
  },
  {
    header: "Status",
    accessorKey: "status",
    cell: ({ row }) => {
      const status = row.getValue("status");
      return h(
        "span",
        {
          class: "inline-flex items-center text-sm text-muted-foreground tracking-tight capitalize",
        },
        status
      );
    },
    size: 100,
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      return filterValue.includes(row.getValue(columnId));
    },
  },
  {
    header: "Views",
    accessorKey: "visits_count",
    cell: ({ row }) => {
      const count = row.getValue("visits_count") || 0;
      return h("div", { class: "text-sm tracking-tight" }, count.toLocaleString());
    },
    size: 80,
    enableSorting: true,
  },
  {
    header: "Images",
    accessorKey: "media_count",
    cell: ({ row }) => {
      const count = row.getValue("media_count") || 0;
      return h("div", { class: "text-sm tracking-tight" }, count.toLocaleString());
    },
    size: 80,
    enableSorting: true,
  },
  {
    header: "Published",
    accessorKey: "published_at",
    cell: ({ row }) => {
      const date = row.getValue("published_at");
      if (!date) {
        return h("span", { class: "text-muted-foreground text-sm" }, "-");
      }
      return withDirectives(
        h("div", { class: "text-sm text-muted-foreground tracking-tight" }, $dayjs(date).fromNow()),
        [[resolveDirective("tippy"), $dayjs(date).format("MMMM D, YYYY [at] h:mm A")]]
      );
    },
    size: 100,
  },
  {
    header: "Author",
    accessorKey: "creator",
    cell: ({ row }) => {
      const creator = row.getValue("creator");
      if (!creator) {
        return h("span", { class: "text-muted-foreground text-sm" }, "-");
      }
      //   return h(resolveComponent("UserProfile"), { user: creator });
      return h("div", { class: "flex items-center gap-x-1.5" }, [
        h(resolveComponent("Avatar"), { model: creator, class: "size-7" }),
        h("span", { class: "text-muted-foreground text-sm" }, creator.name),
      ]);
    },
    size: 150,
    enableSorting: true,
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      const creator = row.getValue(columnId);
      if (filterValue.includes("none") && !creator) return true;
      if (creator && filterValue.includes(creator.id)) return true;
      return false;
    },
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) => h(RowActions, { post: row.original }),
    size: 60,
    enableHiding: false,
  },
];

// Table ref
const tableRef = ref();

// Check if there are any selected rows
const hasSelectedRows = computed(() => {
  return tableRef.value?.table?.getSelectedRowModel()?.rows?.length > 0;
});

// Clear selection
const clearSelection = () => {
  if (tableRef.value) {
    tableRef.value.resetRowSelection();
  }
};

// Filter helpers
const getFilterValue = (columnId) => {
  if (clientOnly.value && tableRef.value?.table) {
    return tableRef.value.table.getColumn(columnId)?.getFilterValue() ?? [];
  }
  return columnFilters.value.find((f) => f.id === columnId)?.value ?? [];
};

const selectedStatuses = computed(() => getFilterValue("status"));
const selectedAuthors = computed(() => getFilterValue("creator"));
const totalActiveFilters = computed(
  () => selectedStatuses.value.length + selectedAuthors.value.length
);

const handleFilterChange = (columnId, { checked, value }) => {
  if (clientOnly.value && tableRef.value?.table) {
    const column = tableRef.value.table.getColumn(columnId);
    if (!column) return;

    const current = column.getFilterValue() ?? [];
    const updated = checked ? [...current, value] : current.filter((item) => item !== value);

    column.setFilterValue(updated.length > 0 ? updated : undefined);
    tableRef.value.table.setPageIndex(0);
  } else {
    const current = getFilterValue(columnId);
    const updated = checked ? [...current, value] : current.filter((item) => item !== value);

    const existingIndex = columnFilters.value.findIndex((f) => f.id === columnId);
    if (updated.length) {
      if (existingIndex >= 0) {
        columnFilters.value[existingIndex].value = updated;
      } else {
        columnFilters.value.push({ id: columnId, value: updated });
      }
    } else {
      if (existingIndex >= 0) {
        columnFilters.value.splice(existingIndex, 1);
      }
    }
    pagination.value.pageIndex = 0;
  }
};

// Export handlers
const exportPending = ref(false);
const exportImagesPending = ref(false);

const buildExportParams = () => {
  const params = new URLSearchParams();

  // Get current filters from table instance (for client-only mode) or refs (for server mode)
  let currentFilters = {};
  let currentSorting = [];

  if (clientOnly.value && tableRef.value?.table) {
    // Client-only mode: get filters from table instance
    const titleFilter = tableRef.value.table.getColumn("title")?.getFilterValue();
    const statusFilter = tableRef.value.table.getColumn("status")?.getFilterValue();
    const creatorFilter = tableRef.value.table.getColumn("creator")?.getFilterValue();

    if (titleFilter) currentFilters.title = titleFilter;
    if (statusFilter) currentFilters.status = statusFilter;
    if (creatorFilter) currentFilters.creator = creatorFilter;

    // Get sorting from table state
    currentSorting = tableRef.value.table.getState().sorting;
  } else {
    // Server mode: use refs
    columnFilters.value.forEach((filter) => {
      currentFilters[filter.id] = filter.value;
    });
    currentSorting = sorting.value;
  }

  // Add filters to params
  const filterMapping = {
    title: "filter_search",
    status: "filter_status",
    creator: "filter_creator",
  };

  Object.entries(currentFilters).forEach(([columnId, value]) => {
    const paramKey = filterMapping[columnId];
    if (paramKey && value) {
      const paramValue = Array.isArray(value) ? value.join(",") : value;
      params.append(paramKey, paramValue);
    }
  });

  // Add sorting
  const sortField = currentSorting[0]?.id || "published_at";
  const sortDirection = currentSorting[0]?.desc ? "desc" : "asc";
  params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);

  return params;
};

const downloadBlob = (blob, filename, mimeType) => {
  const url = window.URL.createObjectURL(new Blob([blob], { type: mimeType }));
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  window.URL.revokeObjectURL(url);
};

const handleExport = async () => {
  try {
    exportPending.value = true;
    const params = buildExportParams();
    const client = useSanctumClient();

    const response = await client(`/api/posts/export?${params.toString()}`, {
      responseType: "blob",
    });

    const filename = `posts_${new Date().toISOString().slice(0, 19).replace(/:/g, "-")}.csv`;
    downloadBlob(response, filename, "text/csv");

    toast.success("Posts exported successfully");
  } catch (error) {
    console.error("Failed to export posts:", error);
    toast.error("Failed to export posts", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    exportPending.value = false;
  }
};

const handleExportImages = async () => {
  try {
    exportImagesPending.value = true;
    const params = buildExportParams();
    const client = useSanctumClient();

    const response = await client(`/api/posts/export/with-images?${params.toString()}`, {
      responseType: "blob",
    });

    const filename = `posts_images_${new Date().toISOString().slice(0, 19).replace(/:/g, "-")}.zip`;
    downloadBlob(response, filename, "application/zip");

    toast.success("Posts with images exported successfully");
  } catch (error) {
    console.error("Failed to export posts with images:", error);
    toast.error("Failed to export posts with images", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    exportImagesPending.value = false;
  }
};

// Delete handlers
const deleteDialogOpen = ref(false);
const deletePending = ref(false);

const handleDeleteRows = async (selectedRows) => {
  const postIds = selectedRows.map((row) => row.original.id);
  try {
    deletePending.value = true;
    const client = useSanctumClient();
    await Promise.all(
      postIds.map((id) => {
        const post = data.value.find((p) => p.id === id);
        return client(`/api/posts/${post.slug}`, { method: "DELETE" });
      })
    );
    await refresh();
    deleteDialogOpen.value = false;
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }
    toast.success(`${postIds.length} post(s) deleted successfully`);
  } catch (error) {
    console.error("Failed to delete posts:", error);
    toast.error("Failed to delete posts", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

const handleDeleteSingleRow = async (slug) => {
  try {
    deletePending.value = true;
    const client = useSanctumClient();
    await client(`/api/posts/${slug}`, { method: "DELETE" });
    await refresh();

    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }

    toast.success("Post deleted successfully");
  } catch (error) {
    console.error("Failed to delete post:", error);
    toast.error("Failed to delete post", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

// Row Actions Component
const RowActions = defineComponent({
  props: {
    post: { type: Object, required: true },
  },
  setup(props) {
    const dialogOpen = ref(false);
    const singleDeletePending = ref(false);

    // Check permissions for this specific post - make them computed so they're reactive
    const canEdit = computed(() => canEditPost(props.post));
    const canDelete = computed(() => canDeletePost(props.post));

    return () =>
      h("div", { class: "flex justify-end" }, [
        h(
          Popover,
          {},
          {
            default: () => [
              h(
                PopoverTrigger,
                { asChild: true },
                {
                  default: () =>
                    h(
                      "button",
                      {
                        class:
                          "hover:bg-muted data-[state=open]:bg-muted inline-flex size-8 items-center justify-center rounded-md",
                      },
                      [h(resolveComponent("Icon"), { name: "lucide:ellipsis", class: "size-4" })]
                    ),
                }
              ),
              h(
                PopoverContent,
                { align: "end", class: "w-40 p-1" },
                {
                  default: () =>
                    h("div", { class: "flex flex-col" }, [
                      // View button (only for published posts)
                      ...(props.post.status === "published"
                        ? [
                            h(
                              PopoverClose,
                              { asChild: true },
                              {
                                default: () =>
                                  h(
                                    resolveComponent("NuxtLink"),
                                    {
                                      to: `/news/${props.post.slug}`,
                                      class:
                                        "hover:bg-muted rounded-md px-3 py-2 text-left text-sm tracking-tight flex items-center gap-x-1.5",
                                    },
                                    {
                                      default: () => [
                                        h(resolveComponent("Icon"), {
                                          name: "lucide:eye",
                                          class: "size-4 shrink-0",
                                        }),
                                        h("span", {}, "View"),
                                      ],
                                    }
                                  ),
                              }
                            ),
                          ]
                        : []),
                      // Edit button (only if user has permission)
                      ...(canEdit.value
                        ? [
                            h(
                              PopoverClose,
                              { asChild: true },
                              {
                                default: () =>
                                  h(
                                    resolveComponent("NuxtLink"),
                                    {
                                      to: `/posts/${props.post.slug}/edit`,
                                      class:
                                        "hover:bg-muted rounded-md px-3 py-2 text-left text-sm tracking-tight flex items-center gap-x-1.5",
                                    },
                                    {
                                      default: () => [
                                        h(resolveComponent("Icon"), {
                                          name: "lucide:pencil-line",
                                          class: "size-4 shrink-0",
                                        }),
                                        h("span", {}, "Edit"),
                                      ],
                                    }
                                  ),
                              }
                            ),
                          ]
                        : []),
                      // Analytics button (always visible)
                      h(
                        PopoverClose,
                        { asChild: true },
                        {
                          default: () =>
                            h(
                              resolveComponent("NuxtLink"),
                              {
                                to: `/posts/${props.post.slug}/analytics`,
                                class:
                                  "hover:bg-muted rounded-md px-3 py-2 text-left text-sm tracking-tight flex items-center gap-x-1.5",
                              },
                              {
                                default: () => [
                                  h(resolveComponent("Icon"), {
                                    name: "lucide:bar-chart",
                                    class: "size-4 shrink-0",
                                  }),
                                  h("span", {}, "Analytics"),
                                ],
                              }
                            ),
                        }
                      ),
                      // Delete button (only if user has permission)
                      ...(canDelete.value
                        ? [
                            h(
                              PopoverClose,
                              { asChild: true },
                              {
                                default: () =>
                                  h(
                                    "button",
                                    {
                                      class:
                                        "hover:bg-destructive/10 text-destructive rounded-md px-3 py-2 text-left text-sm tracking-tight flex items-center gap-x-1.5",
                                      onClick: () => (dialogOpen.value = true),
                                    },
                                    [
                                      h(resolveComponent("Icon"), {
                                        name: "lucide:trash",
                                        class: "size-4 shrink-0",
                                      }),
                                      h("span", {}, "Delete"),
                                    ]
                                  ),
                              }
                            ),
                          ]
                        : []),
                    ]),
                }
              ),
            ],
          }
        ),
        h(
          DialogResponsive,
          {
            open: dialogOpen.value,
            "onUpdate:open": (value) => (dialogOpen.value = value),
          },
          {
            default: () =>
              h("div", { class: "px-4 pb-10 md:px-6 md:py-5" }, [
                h(
                  "div",
                  { class: "text-primary text-lg font-semibold tracking-tight" },
                  "Are you sure?"
                ),
                h(
                  "p",
                  { class: "text-body mt-1.5 text-sm tracking-tight" },
                  "This action can't be undone. This will permanently delete this post."
                ),
                h("div", { class: "mt-3 flex justify-end gap-2" }, [
                  h(
                    "button",
                    {
                      class:
                        "border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98",
                      onClick: () => (dialogOpen.value = false),
                      disabled: singleDeletePending.value,
                    },
                    "Cancel"
                  ),
                  h(
                    "button",
                    {
                      class:
                        "bg-destructive text-white hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50",
                      disabled: singleDeletePending.value,
                      onClick: async () => {
                        singleDeletePending.value = true;
                        try {
                          await handleDeleteSingleRow(props.post.slug);
                          dialogOpen.value = false;
                        } finally {
                          singleDeletePending.value = false;
                        }
                      },
                    },
                    singleDeletePending.value
                      ? h(resolveComponent("Spinner"), { class: "size-4 text-white" })
                      : "Delete"
                  ),
                ]),
              ]),
          }
        ),
      ]);
  },
});

// Filter Section Component
const FilterSection = defineComponent({
  props: {
    title: String,
    options: Array,
    selected: Array,
  },
  emits: ["change"],
  setup(props, { emit }) {
    return () =>
      h("div", { class: "space-y-2" }, [
        h("div", { class: "text-muted-foreground text-xs font-medium" }, props.title),
        h(
          "div",
          { class: "space-y-2" },
          props.options.map((option, i) => {
            const value = typeof option === "string" ? option : option.value;
            const label = typeof option === "string" ? option : option.label;
            return h("div", { key: value, class: "flex items-center gap-2" }, [
              h(Checkbox, {
                id: `${props.title}-${i}`,
                modelValue: props.selected.includes(value),
                "onUpdate:modelValue": (checked) => emit("change", { checked: !!checked, value }),
              }),
              h(
                Label,
                {
                  for: `${props.title}-${i}`,
                  class: "grow cursor-pointer font-normal tracking-tight capitalize",
                },
                { default: () => label }
              ),
            ]);
          })
        ),
      ]);
  },
});

const isPageActive = ref(true);
onActivated(() => { isPageActive.value = true; });
onDeactivated(() => { isPageActive.value = false; });

defineShortcuts({
  n: {
    handler: () => {
      if (canCreate.value) {
        navigateTo("/posts/create");
      }
    },
    whenever: [isPageActive],
  },
});
</script>
