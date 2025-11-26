<template>
  <div class="mx-auto max-w-4xl space-y-6 pt-4 pb-16">
    <div
      class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
    >
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:analytics-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Google Analytics Properties</h1>
      </div>

      <div v-if="!hasSelectedRows" class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <ImportDialog @imported="refresh">
          <template #trigger="{ open }">
            <button
              @click="open()"
              class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
            >
              <Icon name="hugeicons:file-import" class="size-4 shrink-0" />
              <span>Import</span>
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
          <span>Export {{ columnFilters?.length ? "selected" : "all" }}</span>
        </button>
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
      model="ga-properties"
      label="GA Property"
      search-column="name"
      search-placeholder="Search name or property ID"
      error-title="Error loading GA properties"
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
                {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "property" : "properties" }}.
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
import ImportDialog from "@/components/ga-property/ImportDialog.vue";
import GaPropertyProfile from "@/components/ga-property/Profile.vue";
import TableData from "@/components/TableData.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Switch } from "@/components/ui/switch";
import { PopoverClose } from "reka-ui";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "role"],
  roles: ["admin", "master"],
  layout: "app",
});

defineOptions({
  name: "ga-properties",
});

const title = "Google Analytics Properties";
const description = "Manage your Google Analytics 4 properties";

usePageMeta("", {
  title: title,
  description: description,
});

const { user } = useSanctumAuth();
const { $dayjs } = useNuxtApp();

// Table state
const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 20 });
const sorting = ref([{ id: "last_synced_at", desc: true }]);

// Data state
const data = ref([]);
const meta = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0 });
const pending = ref(false);
const error = ref(null);

// Client-only mode flag
const clientOnly = ref(true);

// Export state
const exportPending = ref(false);

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
      name: "filter.search",
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
    const sortField = sorting.value[0]?.id || "last_synced_at";
    const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
    params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);
  }

  return params.toString();
};

// Fetch GA properties
const fetchGaProperties = async () => {
  try {
    pending.value = true;
    error.value = null;
    const client = useSanctumClient();
    const response = await client(`/api/google-analytics/ga-properties?${buildQueryParams()}`);
    data.value = response.data;
    meta.value = response.meta;
  } catch (err) {
    error.value = err;
    console.error("Failed to fetch GA properties:", err);
  } finally {
    pending.value = false;
  }
};

await fetchGaProperties();

// Watchers for server-side mode only
const debouncedFetch = useDebounceFn(fetchGaProperties, 300);

watch(
  [columnFilters, sorting, pagination],
  () => {
    if (!clientOnly.value) {
      const hasNameFilter = columnFilters.value.some((f) => f.id === "name");
      hasNameFilter ? debouncedFetch() : fetchGaProperties();
    }
  },
  { deep: true }
);

const refresh = fetchGaProperties;

// Toggle status handler
const handleToggleStatus = async (property) => {
  const newStatus = !property.is_active;
  const originalStatus = property.is_active;

  // Optimistic update
  property.is_active = newStatus;

  try {
    const client = useSanctumClient();
    const response = await client(`/api/google-analytics/ga-properties/${property.id}`, {
      method: "PUT",
      body: {
        is_active: newStatus,
      },
    });

    // Update with server response
    if (response.data) {
      const updated = data.value.find((p) => p.id === property.id);
      if (updated) {
        updated.is_active = response.data.is_active;
      }
    }

    toast.success(`Property ${newStatus ? "activated" : "deactivated"} successfully`);
  } catch (error) {
    // Revert on error
    property.is_active = originalStatus;

    console.error("Failed to update property status:", error);
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
  //   {
  //     header: "",
  //     accessorKey: "profile_image",
  //     cell: ({ row }) => {
  //       const property = row.original;
  //       const profileImage = property.profile_image?.md || property.profile_image?.original;

  //       if (profileImage) {
  //         return h("img", {
  //           src: profileImage,
  //           alt: property.name,
  //           class: "size-8 rounded-md object-cover",
  //         });
  //       }

  //       return h(
  //         "div",
  //         {
  //           class: "flex size-8 items-center justify-center rounded-md bg-muted text-muted-foreground",
  //         },
  //         h(resolveComponent("Icon"), { name: "hugeicons:analytics-01", class: "size-4" })
  //       );
  //     },
  //     size: 48,
  //     enableSorting: false,
  //   },
  {
    header: "Property",
    accessorKey: "name",
    // cell: ({ row }) => {
    //   const property = row.original;
    //   return h("div", { class: "flex flex-col gap-y-1" }, [
    //     h("div", { class: "text-sm font-medium tracking-tight" }, property.name),
    //     h("div", { class: "text-xs text-muted-foreground" }, `ID: ${property.property_id}`),
    //   ]);
    // },

    cell: ({ row }) => {
      const property = row.original;
      return h(
        resolveComponent("NuxtLink"),
        {
          to: `/ga-properties/${property.id}/edit`,
          class: "block hover:opacity-80 transition-opacity",
        },
        {
          default: () => h(GaPropertyProfile, { model: property }),
        }
      );
    },
    size: 250,
    enableHiding: false,
    filterFn: (row, columnId, filterValue) => {
      const searchValue = filterValue.toLowerCase();
      const name = row.original.name?.toLowerCase() || "";
      const propertyId = row.original.property_id?.toLowerCase() || "";
      return name.includes(searchValue) || propertyId.includes(searchValue);
    },
  },
  {
    header: "Tags",
    accessorKey: "tags",
    cell: ({ row }) => {
      const tags = row.getValue("tags") || [];
      if (tags.length === 0) {
        return h("div", { class: "text-xs text-muted-foreground tracking-tight" }, "-");
      }
      return h(
        "div",
        { class: "flex flex-wrap gap-1" },
        tags.slice(0, 3).map((tag) => {
          // Tags are now plain strings from backend
          return h(
            "span",
            {
              class:
                "inline-flex items-center rounded-full border px-2 py-0.5 text-xs tracking-tight",
            },
            tag
          );
        })
      );
    },
    size: 180,
  },
  {
    header: "Sync Freq.",
    accessorKey: "sync_frequency",
    cell: ({ row }) => {
      const freq = row.getValue("sync_frequency");
      return h("div", { class: "text-sm tracking-tight" }, `${freq} min`);
    },
    size: 100,
  },
  {
    header: "Status",
    accessorKey: "is_active",
    cell: ({ row }) => {
      const property = row.original;
      return h("div", { class: "flex items-center gap-x-2" }, [
        h(Switch, {
          modelValue: property.is_active,
          "onUpdate:modelValue": () => handleToggleStatus(property),
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
    header: "Sync Status",
    accessorKey: "last_synced_at",
    cell: ({ row }) => {
      const property = row.original;
      const lastSynced = property.last_synced_at;
      const nextSync = property.next_sync_at;

      if (!lastSynced) {
        return h("div", { class: "text-sm text-muted-foreground tracking-tight" }, "Never synced");
      }

      return h("div", { class: "flex flex-col gap-y-0.5" }, [
        h(
          "div",
          {
            class: "text-xs text-muted-foreground tracking-tight",
            title: $dayjs(lastSynced).format("MMMM D, YYYY [at] h:mm A"),
          },
          `Last: ${$dayjs(lastSynced).fromNow()}`
        ),
        nextSync && property.is_active
          ? h(
              "div",
              {
                class: "text-xs text-muted-foreground tracking-tight",
                title: $dayjs(nextSync).format("MMMM D, YYYY [at] h:mm A"),
              },
              `Next: ${$dayjs(nextSync).fromNow()}`
            )
          : null,
      ]);
    },
    size: 150,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) => h(RowActions, { property: row.original }),
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

// Delete handlers
const deleteDialogOpen = ref(false);
const deletePending = ref(false);

const handleDeleteRows = async (selectedRows) => {
  const propertyIds = selectedRows.map((row) => row.original.id);
  try {
    deletePending.value = true;
    const client = useSanctumClient();

    // Delete each property individually
    for (const id of propertyIds) {
      await client(`/api/google-analytics/ga-properties/${id}`, {
        method: "DELETE",
      });
    }

    await refresh();
    deleteDialogOpen.value = false;
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }

    toast.success(
      `${propertyIds.length} propert${propertyIds.length === 1 ? "y" : "ies"} deleted successfully`
    );
  } catch (error) {
    console.error("Failed to delete properties:", error);
    toast.error("Failed to delete properties", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

const handleDeleteSingleRow = async (id) => {
  try {
    deletePending.value = true;
    const client = useSanctumClient();
    await client(`/api/google-analytics/ga-properties/${id}`, { method: "DELETE" });
    await refresh();

    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }

    toast.success("Property deleted successfully");
  } catch (error) {
    console.error("Failed to delete property:", error);
    toast.error("Failed to delete property", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

// Export handler
const handleExport = async () => {
  try {
    exportPending.value = true;

    // Build query params
    const params = new URLSearchParams();

    // Add filters
    const searchFilter = columnFilters.value.find((f) => f.id === "name");
    if (searchFilter?.value) {
      params.append("filter_search", searchFilter.value);
    }

    const statusFilter = columnFilters.value.find((f) => f.id === "is_active");
    if (statusFilter?.value) {
      const statuses = statusFilter.value.map((val) => (val === "active" ? "active" : "inactive"));
      params.append("filter_status", statuses.join(","));
    }

    // Add sorting
    const sortField = sorting.value[0]?.id || "last_synced_at";
    const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
    params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);

    const client = useSanctumClient();

    // Fetch the file as blob
    const response = await client(
      `/api/google-analytics/ga-properties/export?${params.toString()}`,
      {
        responseType: "blob",
      }
    );

    // Create a download link and trigger download
    const blob = new Blob([response], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `ga_properties_${new Date().toISOString().slice(0, 19).replace(/:/g, "-")}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);

    toast.success("GA properties exported");
  } catch (error) {
    console.error("Failed to export GA properties:", error);
    toast.error("Failed to export GA properties", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    exportPending.value = false;
  }
};

// Row Actions Component
const RowActions = defineComponent({
  props: {
    property: { type: Object, required: true },
  },
  setup(props) {
    const dialogOpen = ref(false);
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
                              resolveComponent("NuxtLink"),
                              {
                                to: `/ga-properties/${props.property.id}/edit`,
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
                  "This action can't be undone. This will permanently delete this property."
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
                          await handleDeleteSingleRow(props.property.id);
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
</script>
