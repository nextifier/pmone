<template>
  <div class="mx-auto max-w-6xl space-y-6 pt-4 pb-16">
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:api" class="size-5 sm:size-6" />
        <h1 class="page-title">API Consumers</h1>
      </div>

      <div class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <nuxt-link
          to="/api-consumers/create"
          class="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-x-1 rounded-md px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
        >
          <Icon name="lucide:plus" class="size-4 shrink-0" />
          <span>Add Consumer</span>
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
      model="api-consumers"
      label="API Consumer"
      search-column="name"
      search-placeholder="Search consumers..."
      error-title="Error loading API consumers"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
      @update:pagination="pagination = $event"
      @update:sorting="sorting = $event"
      @update:column-filters="columnFilters = $event"
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
          <PopoverContent class="w-auto min-w-48 p-3" align="start">
            <div class="space-y-4">
              <FilterSection
                title="Status"
                :options="[
                  { label: 'Active', value: 'true' },
                  { label: 'Inactive', value: 'false' },
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
                {{ selectedRows.length === 1 ? "consumer" : "consumers" }}.
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
import TableData from "@/components/TableData.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Switch } from "@/components/ui/switch";
import { PopoverClose } from "reka-ui";
import { resolveDirective, withDirectives } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "admin-master"],
  layout: "app",
});

defineOptions({
  name: "api-consumers",
});

const title = "API Consumers";
const description = "Manage API consumers for your application";

usePageMeta("", {
  title: title,
  description: description,
});

const { user } = useSanctumAuth();
const { $dayjs } = useNuxtApp();

// Table state
const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 15 });
const sorting = ref([{ id: "created_at", desc: true }]);

// Data state
const data = ref([]);
const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
const pending = ref(false);
const error = ref(null);

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
      name: "search",
      is_active: "is_active",
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

// Fetch API consumers
const fetchApiConsumers = async () => {
  try {
    pending.value = true;
    error.value = null;
    const client = useSanctumClient();
    const response = await client(`/api/api-consumers?${buildQueryParams()}`);
    data.value = response.data;
    meta.value = response.meta;
  } catch (err) {
    error.value = err;
    console.error("Failed to fetch API consumers:", err);
  } finally {
    pending.value = false;
  }
};

await fetchApiConsumers();

// Watchers for server-side mode only
const debouncedFetch = useDebounceFn(fetchApiConsumers, 300);

watch(
  [columnFilters, sorting, pagination],
  () => {
    if (!clientOnly.value) {
      const hasNameFilter = columnFilters.value.some((f) => f.id === "name");
      hasNameFilter ? debouncedFetch() : fetchApiConsumers();
    }
  },
  { deep: true }
);

const refresh = fetchApiConsumers;

// Toggle status handler
const handleToggleStatus = async (consumer) => {
  const newStatus = !consumer.is_active;
  const originalStatus = consumer.is_active;

  // Optimistic update
  consumer.is_active = newStatus;

  try {
    const client = useSanctumClient();
    const response = await client(`/api/api-consumers/${consumer.id}/toggle-status`, {
      method: "POST",
    });

    // Update with server response
    if (response.data) {
      const updated = data.value.find((c) => c.id === consumer.id);
      if (updated) {
        updated.is_active = response.data.is_active;
      }
    }

    toast.success(`Consumer ${newStatus ? "activated" : "deactivated"} successfully`);
  } catch (error) {
    // Revert on error
    consumer.is_active = originalStatus;

    console.error("Failed to update consumer status:", error);
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
    header: "Consumer",
    accessorKey: "name",
    cell: ({ row }) => {
      const consumer = row.original;
      return h("div", { class: "flex flex-col gap-1" }, [
        h(
          "div",
          { class: "font-medium tracking-tight" },
          consumer.name
        ),
        h(
          "div",
          { class: "text-muted-foreground text-xs tracking-tight" },
          consumer.website_url
        ),
      ]);
    },
    size: 280,
    enableHiding: false,
    filterFn: (row, columnId, filterValue) => {
      const searchValue = filterValue.toLowerCase();
      const name = row.original.name?.toLowerCase() || "";
      const url = row.original.website_url?.toLowerCase() || "";
      return name.includes(searchValue) || url.includes(searchValue);
    },
  },
  {
    header: "Description",
    accessorKey: "description",
    cell: ({ row }) => {
      const description = row.getValue("description");
      if (!description) {
        return h("span", { class: "text-muted-foreground text-sm" }, "-");
      }
      return h(
        "div",
        { class: "text-muted-foreground max-w-xs truncate text-sm tracking-tight" },
        description
      );
    },
    size: 220,
  },
  {
    header: "Rate Limit",
    accessorKey: "rate_limit",
    cell: ({ row }) => {
      const limit = row.getValue("rate_limit") || 60;
      return h("div", { class: "text-sm tracking-tight" }, `${limit}/min`);
    },
    size: 100,
    enableSorting: true,
  },
  {
    header: "Status",
    accessorKey: "is_active",
    cell: ({ row }) => {
      const consumer = row.original;
      return h(Switch, {
        modelValue: consumer.is_active,
        "onUpdate:modelValue": () => handleToggleStatus(consumer),
        disabled: false,
      });
    },
    size: 80,
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      const isActive = row.getValue(columnId);
      return filterValue.includes(String(isActive));
    },
  },
  {
    header: "Last Used",
    accessorKey: "last_used_at",
    cell: ({ row }) => {
      const date = row.getValue("last_used_at");
      if (!date) {
        return h("span", { class: "text-muted-foreground text-sm" }, "Never");
      }
      return withDirectives(
        h(
          "div",
          { class: "text-muted-foreground text-sm tracking-tight" },
          $dayjs(date).fromNow()
        ),
        [[resolveDirective("tippy"), $dayjs(date).format("MMMM D, YYYY [at] h:mm A")]]
      );
    },
    size: 120,
  },
  {
    header: "Created",
    accessorKey: "created_at",
    cell: ({ row }) => {
      const date = row.getValue("created_at");
      return withDirectives(
        h(
          "div",
          { class: "text-muted-foreground text-sm tracking-tight" },
          $dayjs(date).fromNow()
        ),
        [[resolveDirective("tippy"), $dayjs(date).format("MMMM D, YYYY [at] h:mm A")]]
      );
    },
    size: 100,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) => h(RowActions, { consumer: row.original }),
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

// Delete handlers
const deleteDialogOpen = ref(false);
const deletePending = ref(false);

const handleDeleteRows = async (selectedRows) => {
  const consumerIds = selectedRows.map((row) => row.original.id);
  try {
    deletePending.value = true;
    const client = useSanctumClient();
    await Promise.all(
      consumerIds.map((id) => client(`/api/api-consumers/${id}`, { method: "DELETE" }))
    );
    await refresh();
    deleteDialogOpen.value = false;
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }
    toast.success(`${consumerIds.length} consumer(s) deleted successfully`);
  } catch (error) {
    console.error("Failed to delete consumers:", error);
    toast.error("Failed to delete consumers", {
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
    await client(`/api/api-consumers/${id}`, { method: "DELETE" });
    await refresh();

    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }

    toast.success("API Consumer deleted successfully");
  } catch (error) {
    console.error("Failed to delete consumer:", error);
    toast.error("Failed to delete consumer", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

// Row Actions Component
const RowActions = defineComponent({
  props: {
    consumer: { type: Object, required: true },
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
                                to: `/api-consumers/${props.consumer.id}/edit`,
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
                  "This action can't be undone. This will permanently delete this API consumer."
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
                          await handleDeleteSingleRow(props.consumer.id);
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
