<template>
  <div class="mx-auto max-w-4xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:link-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Short Links</h1>
      </div>

      <div class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <ImportDialog @imported="refresh">
          <template #trigger="{ open }">
            <button
              @click="open()"
              class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
            >
              <Icon name="hugeicons:file-import" class="size-4 shrink-0" />
              <span class="hidden sm:inline">Import</span>
            </button>
          </template>
        </ImportDialog>

        <button
          @click="handleExport"
          :disabled="exportPending"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Spinner v-if="exportPending" class="size-4 shrink-0" />
          <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
          <span class="hidden sm:inline"
            >Export {{ columnFilters?.length ? "selected" : "all" }}</span
          >
        </button>

        <nuxt-link
          to="/short-links/trash"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
          <span class="hidden sm:inline">Trash</span>
        </nuxt-link>

        <nuxt-link
          to="/short-links/create"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:add-circle" class="size-4 shrink-0" />
          <span>Create</span>
        </nuxt-link>
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
      model="short-links"
      search-column="slug"
      search-placeholder="Search slug or destination URL"
      error-title="Error loading short links"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
      @update:pagination="pagination = $event"
      @update:sorting="sorting = $event"
      @update:column-filters="columnFilters = $event"
      @refresh="refresh"
    >
      <template #filters="{ table }">
        <!-- Filter Popover -->
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
          <PopoverContent class="w-auto min-w-48 p-3" align="start">
            <div class="space-y-4">
              <FilterSection
                title="Status"
                :options="[
                  { label: 'Active', value: 'active' },
                  { label: 'Inactive', value: 'inactive' },
                ]"
                :selected="selectedStatuses"
                @change="handleFilterChange('is_active', $event)"
              />
            </div>
          </PopoverContent>
        </Popover>
      </template>

      <template #actions="{ selectedRows }">
        <DialogResponsive
          v-if="selectedRows.length > 0"
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
                {{ selectedRows.length }} selected {{ selectedRows.length === 1 ? "row" : "rows" }}.
              </p>
              <div class="mt-3 flex justify-end gap-2">
                <button
                  class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                  @click="deleteDialogOpen = false"
                >
                  Cancel
                </button>
                <button
                  @click="handleDeleteRows(selectedRows)"
                  class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98"
                >
                  Delete
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
import ImportDialog from "@/components/short-link/ImportDialog.vue";
import LinkTableItem from "@/components/short-link/LinkTableItem.vue";
import TableData from "@/components/TableData.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { PopoverClose } from "reka-ui";
import { resolveDirective, withDirectives } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

defineOptions({
  name: "short-links",
});

const title = "Short Links";
const description = "Shorten your long URLs.";

usePageMeta("", {
  title: title,
  description: description,
});

const { user } = useSanctumAuth();
const { $dayjs } = useNuxtApp();

// Export state
const exportPending = ref(false);

// Table state
const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 10 });
const sorting = ref([{ id: "created_at", desc: true }]);

// Data state
const data = ref([]);
const meta = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0 });
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
    // Server-side mode: add pagination, filters, and sorting
    params.append("page", pagination.value.pageIndex + 1);
    params.append("per_page", pagination.value.pageSize);

    // Filters
    const filters = {
      slug: "filter.search",
      is_active: "filter.status",
    };

    Object.entries(filters).forEach(([columnId, paramKey]) => {
      const filter = columnFilters.value.find((f) => f.id === columnId);
      if (filter?.value) {
        const value = Array.isArray(filter.value) ? filter.value.join(",") : filter.value;
        params.append(paramKey, value);
      }
    });

    // Sorting
    const sortField = sorting.value[0]?.id || "created_at";
    const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
    params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);
  }

  return params.toString();
};

// Fetch short links
const fetchShortLinks = async () => {
  try {
    pending.value = true;
    error.value = null;
    const client = useSanctumClient();
    const response = await client(`/api/short-links?${buildQueryParams()}`);
    data.value = response.data;
    meta.value = response.meta;
  } catch (err) {
    error.value = err;
    console.error("Failed to fetch short links:", err);
  } finally {
    pending.value = false;
  }
};

await fetchShortLinks();

// Watchers for server-side mode only
const debouncedFetch = useDebounceFn(fetchShortLinks, 300);

watch(
  [columnFilters, sorting, pagination],
  () => {
    if (!clientOnly.value) {
      const hasSlugFilter = columnFilters.value.some((f) => f.id === "slug");
      hasSlugFilter ? debouncedFetch() : fetchShortLinks();
    }
  },
  { deep: true }
);

const refresh = fetchShortLinks;

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
    header: "Link",
    accessorKey: "slug",
    cell: ({ row }) =>
      h(LinkTableItem, {
        link: row.original,
      }),
    size: 300,
    enableHiding: false,
    filterFn: (row, columnId, filterValue) => {
      const searchValue = filterValue.toLowerCase();
      const slug = row.original.slug?.toLowerCase() || "";
      const url = row.original.destination_url?.toLowerCase() || "";
      return slug.includes(searchValue) || url.includes(searchValue);
    },
  },
  {
    header: "Clicks",
    accessorKey: "clicks_count",
    cell: ({ row }) => {
      const count = row.getValue("clicks_count") || 0;
      return h("div", { class: "text-sm tracking-tight" }, count.toLocaleString());
    },
    size: 80,
    enableSorting: true,
  },
  {
    header: "Status",
    accessorKey: "is_active",
    cell: ({ row }) => {
      const isActive = row.getValue("is_active");
      const statusColors = {
        true: "bg-success",
        false: "bg-destructive",
      };
      return h("div", { class: "flex items-center gap-x-1.5 capitalize text-sm tracking-tight" }, [
        h("span", { class: ["rounded-full size-2", statusColors[isActive]] }),
        isActive ? "Active" : "Inactive",
      ]);
    },
    size: 80,
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      const isActive = row.getValue(columnId);
      return filterValue.some((value) => {
        if (value === "active") return isActive;
        if (value === "inactive") return !isActive;
        return false;
      });
    },
  },
  {
    header: "Created",
    accessorKey: "created_at",
    cell: ({ row }) => {
      const date = row.getValue("created_at");
      return withDirectives(
        h("div", { class: "text-sm text-muted-foreground tracking-tight" }, $dayjs(date).fromNow()),
        [[resolveDirective("tippy"), $dayjs(date).format("MMMM D, YYYY [at] h:mm A")]]
      );
    },
    size: 100,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) => h(RowActions, { shortLink: row.original }),
    size: 60,
    enableHiding: false,
  },
];

// Table ref
const tableRef = ref();

// Filter helpers - handle both client and server mode
const getFilterValue = (columnId) => {
  if (clientOnly.value && tableRef.value?.table) {
    return tableRef.value.table.getColumn(columnId)?.getFilterValue() ?? [];
  }
  return columnFilters.value.find((f) => f.id === columnId)?.value ?? [];
};

const selectedStatuses = computed(() => getFilterValue("is_active"));
const totalActiveFilters = computed(() => selectedStatuses.value.length);

const handleFilterChange = (columnId, { checked, value }) => {
  if (clientOnly.value && tableRef.value?.table) {
    // Client-side mode: use table instance
    const column = tableRef.value.table.getColumn(columnId);
    if (!column) return;

    const current = column.getFilterValue() ?? [];
    const updated = checked ? [...current, value] : current.filter((item) => item !== value);

    column.setFilterValue(updated.length > 0 ? updated : undefined);
    // Reset to first page when filter changes
    tableRef.value.table.setPageIndex(0);
  } else {
    // Server-side mode: update columnFilters ref
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
    // Reset to first page when filter changes (server-side)
    pagination.value.pageIndex = 0;
  }
};

// Delete handlers
const deleteDialogOpen = ref(false);
const handleDeleteRows = async (selectedRows) => {
  const shortLinkIds = selectedRows.map((row) => row.original.id);
  try {
    const client = useSanctumClient();
    const response = await client("/api/short-links/bulk", {
      method: "DELETE",
      body: { ids: shortLinkIds },
    });
    await refresh();
    deleteDialogOpen.value = false;
    if (tableRef.value?.table) {
      tableRef.value.table.resetRowSelection();
    }

    // Show success toast
    toast.success(response.message || "Short links deleted successfully", {
      description:
        response.errors?.length > 0
          ? `${response.deleted_count} deleted, ${response.errors.length} failed`
          : `${response.deleted_count} short link(s) deleted`,
    });
  } catch (error) {
    console.error("Failed to delete short links:", error);
    toast.error("Failed to delete short links", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  }
};

const handleDeleteSingleRow = async (slug) => {
  try {
    const client = useSanctumClient();
    const response = await client(`/api/short-links/${slug}`, { method: "DELETE" });
    await refresh();

    // Show success toast
    toast.success(response.message || "Short link deleted successfully");
  } catch (error) {
    console.error("Failed to delete short link:", error);
    toast.error("Failed to delete short link", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  }
};

// Export handler
const handleExport = async () => {
  try {
    exportPending.value = true;

    // Build query params
    const params = new URLSearchParams();

    // Add filters
    const searchFilter = columnFilters.value.find((f) => f.id === "slug");
    if (searchFilter?.value) {
      params.append("filter_search", searchFilter.value);
    }

    const statusFilter = columnFilters.value.find((f) => f.id === "is_active");
    if (statusFilter?.value) {
      const statuses = statusFilter.value.map((val) => (val ? "active" : "inactive"));
      params.append("filter_status", statuses.join(","));
    }

    // Add sorting
    const sortField = sorting.value[0]?.id || "created_at";
    const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
    params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);

    const client = useSanctumClient();

    // Fetch the file as blob
    const response = await client(`/api/short-links/export?${params.toString()}`, {
      responseType: "blob",
    });

    // Create a download link and trigger download
    const blob = new Blob([response], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `short_links_${new Date().toISOString().slice(0, 19).replace(/:/g, "-")}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);

    toast.success("Short links exported successfully");
  } catch (error) {
    console.error("Failed to export short links:", error);
    toast.error("Failed to export short links", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    exportPending.value = false;
  }
};

// Row Actions Component
const RowActions = defineComponent({
  props: {
    shortLink: { type: Object, required: true },
  },
  setup(props) {
    const dialogOpen = ref(false);
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
                      h(
                        PopoverClose,
                        { asChild: true },
                        {
                          default: () =>
                            h(
                              resolveComponent("NuxtLink"),
                              {
                                to: `/short-links/${props.shortLink.slug}/edit`,
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

                      h(
                        PopoverClose,
                        { asChild: true },
                        {
                          default: () =>
                            h(
                              resolveComponent("NuxtLink"),
                              {
                                to: `/short-links/${props.shortLink.slug}/analytics`,
                                class:
                                  "hover:bg-muted rounded-md px-3 py-2 text-left text-sm tracking-tight flex items-center gap-x-1.5",
                              },
                              {
                                default: () => [
                                  h(resolveComponent("Icon"), {
                                    name: "lucide:chart-no-axes-combined",
                                    class: "size-4 shrink-0",
                                  }),
                                  h("span", {}, "Analytics"),
                                ],
                              }
                            ),
                        }
                      ),

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
                  "This action can't be undone. This will permanently delete this short link."
                ),
                h("div", { class: "mt-3 flex justify-end gap-2" }, [
                  h(
                    "button",
                    {
                      class:
                        "border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98",
                      onClick: () => (dialogOpen.value = false),
                    },
                    "Cancel"
                  ),
                  h(
                    "button",
                    {
                      class:
                        "bg-destructive text-white hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98",
                      onClick: async () => {
                        await handleDeleteSingleRow(props.shortLink.slug);
                        dialogOpen.value = false;
                      },
                    },
                    "Delete"
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
</script>
