<template>
  <div class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-center justify-between gap-x-2.5">
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:delete-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Short Links Trash</h1>
      </div>

      <nuxt-link
        to="/short-links"
        class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
      >
        <Icon name="hugeicons:link-01" class="size-4 shrink-0" />
        <span>All Short Links</span>
      </nuxt-link>
    </div>

    <TableData
      :clientOnly="clientOnly"
      ref="tableRef"
      :data="data"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="short-links-trash"
      search-column="slug"
      :show-add-button="false"
      search-placeholder="Search slug or destination URL"
      error-title="Error loading trashed short links"
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
          v-model:open="restoreDialogOpen"
          class="h-full"
        >
          <template #trigger="{ open }">
            <button
              class="hover:bg-muted flex h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border px-2.5 text-sm tracking-tight active:scale-98"
              @click="open()"
            >
              <Icon name="hugeicons:undo-02" class="size-4 shrink-0" />
              <span class="text-sm tracking-tight">Restore</span>
              <span
                class="text-muted-foreground/80 -me-1 inline-flex h-5 max-h-full items-center rounded border px-1 font-[inherit] text-[0.625rem] font-medium"
              >
                {{ selectedRows.length }}
              </span>
            </button>
          </template>
          <template #default>
            <div class="px-4 pb-10 md:px-6 md:py-5">
              <div class="text-primary text-lg font-semibold tracking-tight">Restore short links?</div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This will restore {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "short link" : "short links" }}.
              </p>
              <div class="mt-3 flex justify-end gap-2">
                <button
                  class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                  @click="restoreDialogOpen = false"
                  :disabled="restorePending"
                >
                  Cancel
                </button>
                <button
                  @click="handleRestoreRows(selectedRows)"
                  :disabled="restorePending"
                  class="bg-primary text-primary-foreground hover:bg-primary/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  <Spinner v-if="restorePending" class="size-4 text-white" />
                  <span v-else>Restore</span>
                </button>
              </div>
            </div>
          </template>
        </DialogResponsive>

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
              <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
              <span class="text-sm tracking-tight">Delete Permanently</span>
              <span
                class="text-muted-foreground/80 -me-1 inline-flex h-5 max-h-full items-center rounded border px-1 font-[inherit] text-[0.625rem] font-medium"
              >
                {{ selectedRows.length }}
              </span>
            </button>
          </template>
          <template #default>
            <div class="px-4 pb-10 md:px-6 md:py-5">
              <div class="text-primary text-lg font-semibold tracking-tight">
                Are you absolutely sure?
              </div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This action can't be undone. This will permanently delete
                {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "short link" : "short links" }}.
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
                  <span v-else>Delete Permanently</span>
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
import LinkTableItem from "@/components/short-link/LinkTableItem.vue";
import TableData from "@/components/TableData.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Switch } from "@/components/ui/switch";
import { PopoverClose } from "reka-ui";
import { resolveDirective, withDirectives } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

defineOptions({
  name: "short-links-trash",
});

usePageMeta("short-links-trash");

const { $dayjs } = useNuxtApp();

// Table state
const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 10 });
const sorting = ref([{ id: "deleted_at", desc: true }]);

// Data state
const data = ref([]);
const meta = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0 });
const pending = ref(false);
const error = ref(null);

// Client-only mode flag
const clientOnly = ref(true);

// Build query params
const buildQueryParams = () => {
  const params = new URLSearchParams();

  if (clientOnly.value) {
    params.append("client_only", "true");
  } else {
    params.append("page", pagination.value.pageIndex + 1);
    params.append("per_page", pagination.value.pageSize);

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

    const sortField = sorting.value[0]?.id || "deleted_at";
    const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
    params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);
  }

  return params.toString();
};

// Fetch trashed short links
const fetchShortLinks = async () => {
  try {
    pending.value = true;
    error.value = null;
    const client = useSanctumClient();
    const response = await client(`/api/short-links/trash?${buildQueryParams()}`);
    data.value = response.data;
    meta.value = response.meta;
  } catch (err) {
    error.value = err;
    console.error("Failed to fetch trashed short links:", err);
  } finally {
    pending.value = false;
  }
};

await fetchShortLinks();

// Watchers
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

// Toggle status handler
const handleToggleStatus = async (shortLink) => {
  const newStatus = !shortLink.is_active;
  const originalStatus = shortLink.is_active;

  // Optimistic update
  shortLink.is_active = newStatus;

  try {
    const client = useSanctumClient();
    const response = await client(`/api/short-links/trash/${shortLink.id}/restore`, {
      method: "POST",
    });

    // Then update the status
    await client(`/api/short-links/${shortLink.slug}`, {
      method: "PUT",
      body: {
        is_active: newStatus,
      },
    });

    // Update with server response
    if (response.data) {
      const updatedLink = data.value.find((link) => link.id === shortLink.id);
      if (updatedLink) {
        updatedLink.is_active = newStatus;
      }
    }

    toast.success(`Short link ${newStatus ? "activated" : "deactivated"} successfully`);
  } catch (error) {
    // Revert on error
    shortLink.is_active = originalStatus;

    console.error("Failed to update short link status:", error);
    toast.error("Failed to update status", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  }
};

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
      const shortLink = row.original;
      return h("div", { class: "flex items-center gap-x-2" }, [
        h(Switch, {
          modelValue: shortLink.is_active,
          "onUpdate:modelValue": () => handleToggleStatus(shortLink),
          disabled: true, // Disabled in trash
        }),
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
    header: "Created By",
    accessorKey: "user.name",
    cell: ({ row }) => {
      const user = row.original.user;
      if (!user) {
        return h("div", { class: "text-sm text-muted-foreground tracking-tight" }, "-");
      }
      return h("div", { class: "text-sm tracking-tight" }, user.name);
    },
    size: 120,
  },
  {
    header: "Deleted By",
    accessorKey: "deleter",
    cell: ({ row }) => {
      const deleter = row.getValue("deleter");
      if (!deleter) {
        return h("div", { class: "text-sm text-muted-foreground tracking-tight" }, "-");
      }
      return h("div", { class: "text-sm tracking-tight" }, deleter.name);
    },
    size: 120,
  },
  {
    header: "Deleted At",
    accessorKey: "deleted_at",
    cell: ({ row }) => {
      const date = row.getValue("deleted_at");
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
    cell: ({ row }) => h(RowActions, { shortLinkId: row.original.id }),
    size: 60,
    enableHiding: false,
  },
];

// Table ref
const tableRef = ref();

// Filter helpers
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

// Restore handlers
const restoreDialogOpen = ref(false);
const restorePending = ref(false);
const handleRestoreRows = async (selectedRows) => {
  const shortLinkIds = selectedRows.map((row) => row.original.id);
  try {
    restorePending.value = true;
    const client = useSanctumClient();
    const response = await client("/api/short-links/trash/restore/bulk", {
      method: "POST",
      body: { ids: shortLinkIds },
    });
    await refresh();
    restoreDialogOpen.value = false;
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }

    toast.success(response.message || "Short links restored successfully", {
      description:
        response.errors?.length > 0
          ? `${response.restored_count} restored, ${response.errors.length} failed`
          : `${response.restored_count} short link(s) restored`,
    });
  } catch (error) {
    console.error("Failed to restore short links:", error);
    toast.error("Failed to restore short links", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    restorePending.value = false;
  }
};

const handleRestoreSingleRow = async (shortLinkId) => {
  try {
    restorePending.value = true;
    const client = useSanctumClient();
    const response = await client(`/api/short-links/trash/${shortLinkId}/restore`, { method: "POST" });
    await refresh();

    // Reset row selection after restore
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }

    toast.success(response.message || "Short link restored successfully");
  } catch (error) {
    console.error("Failed to restore short link:", error);
    toast.error("Failed to restore short link", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    restorePending.value = false;
  }
};

// Delete handlers
const deleteDialogOpen = ref(false);
const deletePending = ref(false);
const handleDeleteRows = async (selectedRows) => {
  const shortLinkIds = selectedRows.map((row) => row.original.id);
  try {
    deletePending.value = true;
    const client = useSanctumClient();
    const response = await client("/api/short-links/trash/bulk", {
      method: "DELETE",
      body: { ids: shortLinkIds },
    });
    await refresh();
    deleteDialogOpen.value = false;
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }

    toast.success(response.message || "Short links permanently deleted", {
      description:
        response.errors?.length > 0
          ? `${response.deleted_count} deleted, ${response.errors.length} failed`
          : `${response.deleted_count} short link(s) permanently deleted`,
    });
  } catch (error) {
    console.error("Failed to permanently delete short links:", error);
    toast.error("Failed to permanently delete short links", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

const handleDeleteSingleRow = async (shortLinkId) => {
  try {
    deletePending.value = true;
    const client = useSanctumClient();
    const response = await client(`/api/short-links/trash/${shortLinkId}`, { method: "DELETE" });
    await refresh();

    // Reset row selection after delete
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }

    toast.success(response.message || "Short link permanently deleted");
  } catch (error) {
    console.error("Failed to permanently delete short link:", error);
    toast.error("Failed to permanently delete short link", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

// Row Actions Component
const RowActions = defineComponent({
  props: {
    shortLinkId: { type: Number, required: true },
  },
  setup(props) {
    const restoreDialogOpen = ref(false);
    const deleteDialogOpen = ref(false);
    const singleRestorePending = ref(false);
    const singleDeletePending = ref(false);
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
                              "button",
                              {
                                class:
                                  "hover:bg-muted rounded-md px-3 py-2 text-left text-sm tracking-tight flex items-center gap-x-1.5",
                                onClick: () => (restoreDialogOpen.value = true),
                              },
                              [
                                h(resolveComponent("Icon"), {
                                  name: "lucide:undo-2",
                                  class: "size-4 shrink-0",
                                }),
                                h("span", {}, "Restore"),
                              ]
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
                                onClick: () => (deleteDialogOpen.value = true),
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
            open: restoreDialogOpen.value,
            "onUpdate:open": (value) => (restoreDialogOpen.value = value),
          },
          {
            default: () =>
              h("div", { class: "px-4 pb-10 md:px-6 md:py-5" }, [
                h(
                  "div",
                  { class: "text-primary text-lg font-semibold tracking-tight" },
                  "Restore short link?"
                ),
                h(
                  "p",
                  { class: "text-body mt-1.5 text-sm tracking-tight" },
                  "This will restore this short link."
                ),
                h("div", { class: "mt-3 flex justify-end gap-2" }, [
                  h(
                    "button",
                    {
                      class:
                        "border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98",
                      onClick: () => (restoreDialogOpen.value = false),
                      disabled: singleRestorePending.value,
                    },
                    "Cancel"
                  ),
                  h(
                    "button",
                    {
                      class:
                        "bg-primary text-primary-foreground hover:bg-primary/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50",
                      disabled: singleRestorePending.value,
                      onClick: async () => {
                        singleRestorePending.value = true;
                        try {
                          await handleRestoreSingleRow(props.shortLinkId);
                          restoreDialogOpen.value = false;
                        } finally {
                          singleRestorePending.value = false;
                        }
                      },
                    },
                    singleRestorePending.value
                      ? h(resolveComponent("Spinner"), { class: "size-4 text-white" })
                      : "Restore"
                  ),
                ]),
              ]),
          }
        ),
        h(
          DialogResponsive,
          {
            open: deleteDialogOpen.value,
            "onUpdate:open": (value) => (deleteDialogOpen.value = value),
          },
          {
            default: () =>
              h("div", { class: "px-4 pb-10 md:px-6 md:py-5" }, [
                h(
                  "div",
                  { class: "text-primary text-lg font-semibold tracking-tight" },
                  "Are you absolutely sure?"
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
                      onClick: () => (deleteDialogOpen.value = false),
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
                          await handleDeleteSingleRow(props.shortLinkId);
                          deleteDialogOpen.value = false;
                        } finally {
                          singleDeletePending.value = false;
                        }
                      },
                    },
                    singleDeletePending.value
                      ? h(resolveComponent("Spinner"), { class: "size-4 text-white" })
                      : "Delete Permanently"
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
