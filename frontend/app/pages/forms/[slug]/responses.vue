<template>
  <div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex items-center gap-x-2.5">
        <h2 class="text-base font-semibold tracking-tight">Responses</h2>
        <span class="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-xs font-medium">
          {{ meta.total || 0 }}
        </span>
      </div>

      <div v-if="!hasSelectedRows" class="ml-auto flex items-center gap-1 sm:gap-2">
        <button
          @click="handleExport"
          :disabled="exportPending || !data.length"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Spinner v-if="exportPending" class="size-4 shrink-0" />
          <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
          <span>Export</span>
        </button>
      </div>

      <div v-else class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <!-- Bulk status update -->
        <Popover>
          <PopoverTrigger asChild>
            <button
              class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
            >
              <Icon name="lucide:tag" class="size-4 shrink-0" />
              <span>Status</span>
              <span
                class="text-muted-foreground/80 -me-1 inline-flex h-5 max-h-full items-center rounded border px-1 font-[inherit] text-[0.625rem] font-medium"
              >
                {{ selectedRowIds.length }}
              </span>
            </button>
          </PopoverTrigger>
          <PopoverContent align="end" class="w-40 p-1">
            <div class="flex flex-col">
              <PopoverClose
                v-for="s in statusOptions"
                :key="s.value"
                asChild
              >
                <button
                  @click="handleBulkStatus(s.value)"
                  class="hover:bg-muted flex items-center gap-x-2 rounded-md px-3 py-2 text-left text-sm tracking-tight"
                >
                  <Icon :name="s.icon" class="size-4 shrink-0" :class="s.color" />
                  <span>{{ s.label }}</span>
                </button>
              </PopoverClose>
            </div>
          </PopoverContent>
        </Popover>

        <!-- Bulk delete -->
        <DialogResponsive v-model:open="deleteDialogOpen">
          <template #trigger="{ open }">
            <button
              class="hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
              @click="open()"
            >
              <Icon name="lucide:trash" class="size-4 shrink-0" />
              <span>Delete</span>
              <span
                class="text-muted-foreground/80 -me-1 inline-flex h-5 max-h-full items-center rounded border px-1 font-[inherit] text-[0.625rem] font-medium"
              >
                {{ selectedRowIds.length }}
              </span>
            </button>
          </template>
          <template #default>
            <div class="px-4 pb-10 md:px-6 md:py-5">
              <div class="text-primary text-lg font-semibold tracking-tight">Are you sure?</div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This action can't be undone. This will permanently delete
                {{ selectedRowIds.length }} selected
                {{ selectedRowIds.length === 1 ? "response" : "responses" }}.
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
                  @click="handleBulkDelete"
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

        <button
          @click="clearSelection"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="lucide:x" class="size-4 shrink-0" />
          <span>Clear</span>
        </button>
      </div>
    </div>

    <TableData
      ref="tableRef"
      :data="data"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="responses"
      label="Response"
      :searchable="false"
      :column-toggle="false"
      :show-add-button="false"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
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
                v-if="selectedStatusFilters.length > 0"
                class="bg-primary text-primary-foreground squircle absolute top-0 right-0 inline-flex size-4 translate-x-1/2 -translate-y-1/2 items-center justify-center text-[11px] font-medium tracking-tight"
              >
                {{ selectedStatusFilters.length }}
              </span>
            </button>
          </PopoverTrigger>
          <PopoverContent class="w-auto min-w-48 p-3 pb-4.5" align="end">
            <div class="space-y-4">
              <FilterSection
                title="Status"
                :options="statusFilterOptions"
                :selected="selectedStatusFilters"
                @change="handleFilterChange('status', $event)"
              />
            </div>
          </PopoverContent>
        </Popover>
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
import { PopoverClose } from "reka-ui";
import { resolveDirective, withDirectives } from "vue";
import { toast } from "vue-sonner";

const props = defineProps({
  form: { type: Object, required: true },
});

const emit = defineEmits(["refresh"]);

const { $dayjs } = useNuxtApp();
const route = useRoute();
const client = useSanctumClient();
const slug = computed(() => route.params.slug);

// Status config
const statusOptions = [
  { value: "new", label: "New", icon: "lucide:circle", color: "text-blue-500" },
  { value: "read", label: "Read", icon: "lucide:check", color: "text-muted-foreground" },
  { value: "starred", label: "Starred", icon: "lucide:star", color: "text-amber-500" },
  { value: "spam", label: "Spam", icon: "lucide:shield-alert", color: "text-destructive" },
];

const statusFilterOptions = statusOptions.map((s) => ({ label: s.label, value: s.value }));

const statusDisplay = (status) => {
  const found = statusOptions.find((s) => s.value === status);
  return found || { value: status, label: status, icon: "lucide:circle", color: "text-muted-foreground" };
};

// Table state
const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 15 });
const sorting = ref([{ id: "submitted_at", desc: true }]);

// Build query params
const buildQueryParams = () => {
  const params = new URLSearchParams();
  params.append("page", pagination.value.pageIndex + 1);
  params.append("per_page", pagination.value.pageSize);

  const statusFilter = columnFilters.value.find((f) => f.id === "status");
  if (statusFilter?.value?.length) {
    params.append("filter_status", statusFilter.value.join(","));
  }

  return params.toString();
};

// Fetch responses
const {
  data: responsesResponse,
  pending,
  error,
  refresh: fetchResponses,
} = await useLazySanctumFetch(
  () => `/api/forms/${slug.value}/responses?${buildQueryParams()}`,
  {
    key: `form-responses-${slug.value}`,
    watch: false,
  }
);

const data = computed(() => responsesResponse.value?.data || []);
const meta = computed(
  () =>
    responsesResponse.value?.meta || {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
    }
);

// Watch for changes and refetch
watch([columnFilters, sorting, pagination], () => fetchResponses(), { deep: true });

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

const refresh = fetchResponses;

// Format response value
const formatResponseValue = (value) => {
  if (value == null) return "-";
  if (Array.isArray(value)) return value.join(", ");
  if (typeof value === "object") return JSON.stringify(value);
  return String(value);
};

// Build dynamic columns
const columns = computed(() => {
  const cols = [];

  // Checkbox
  cols.push({
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
  });

  // Dynamic field columns
  const fields = props.form?.fields || [];
  const sortedFields = [...fields].sort((a, b) => (a.order_column || 0) - (b.order_column || 0));

  for (const field of sortedFields) {
    cols.push({
      id: `field_${field.ulid}`,
      header: field.label,
      accessorFn: (row) => row.response_data?.[field.ulid],
      cell: ({ getValue }) => {
        const val = getValue();
        return h(
          "span",
          { class: "text-sm tracking-tight max-w-[200px] truncate block" },
          formatResponseValue(val)
        );
      },
      size: 160,
      enableSorting: false,
    });
  }

  // Email
  cols.push({
    header: "Email",
    accessorKey: "respondent_email",
    cell: ({ getValue }) =>
      h("span", { class: "text-sm tracking-tight" }, getValue() || "-"),
    size: 180,
    enableSorting: false,
  });

  // Status
  cols.push({
    header: "Status",
    accessorKey: "status",
    cell: ({ row }) => {
      const s = statusDisplay(row.getValue("status"));
      return h("div", { class: "flex items-center gap-x-1.5" }, [
        h(resolveComponent("Icon"), { name: s.icon, class: `size-3.5 shrink-0 ${s.color}` }),
        h("span", { class: "text-sm tracking-tight capitalize" }, s.label),
      ]);
    },
    size: 100,
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      return filterValue.includes(row.getValue(columnId));
    },
  });

  // Submitted at
  cols.push({
    header: "Submitted",
    accessorKey: "submitted_at",
    cell: ({ getValue }) => {
      const date = getValue();
      if (!date) return h("span", { class: "text-muted-foreground text-sm" }, "-");
      return h(
        resolveComponent("ClientOnly"),
        {},
        {
          default: () =>
            withDirectives(
              h("div", { class: "text-sm text-muted-foreground tracking-tight" }, $dayjs(date).fromNow()),
              [[resolveDirective("tippy"), $dayjs(date).format("MMMM D, YYYY [at] h:mm A")]]
            ),
          fallback: () =>
            h("div", { class: "text-sm text-muted-foreground tracking-tight" }, $dayjs(date).format("MMM D, YYYY")),
        }
      );
    },
    size: 120,
  });

  // Actions
  cols.push({
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) => h(RowActions, { response: row.original }),
    size: 60,
    enableHiding: false,
  });

  return cols;
});

// Table ref & selection
const tableRef = ref();
const hasSelectedRows = computed(
  () => tableRef.value?.table?.getSelectedRowModel()?.rows?.length > 0
);
const selectedRowIds = computed(
  () =>
    tableRef.value?.table
      ?.getSelectedRowModel()
      ?.rows?.map((r) => r.original.id) || []
);

const clearSelection = () => {
  if (tableRef.value) tableRef.value.resetRowSelection();
};

// Filter helpers
const getFilterValue = (columnId) => {
  return columnFilters.value.find((f) => f.id === columnId)?.value ?? [];
};
const selectedStatusFilters = computed(() => getFilterValue("status"));

const handleFilterChange = (columnId, { checked, value }) => {
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
};

// Export
const exportPending = ref(false);
const handleExport = async () => {
  try {
    exportPending.value = true;
    const response = await client(`/api/forms/${slug.value}/responses/export`, {
      responseType: "blob",
    });

    const blob = new Blob([response], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `${props.form.slug}-responses-${new Date().toISOString().slice(0, 10)}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);

    toast.success("Responses exported");
  } catch (error) {
    toast.error("Failed to export responses", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    exportPending.value = false;
  }
};

// Bulk status update
const handleBulkStatus = async (status) => {
  try {
    await client(`/api/forms/${slug.value}/responses/bulk-status`, {
      method: "PUT",
      body: { ids: selectedRowIds.value, status },
    });
    toast.success(`${selectedRowIds.value.length} response(s) marked as ${status}`);
    clearSelection();
    await fetchResponses();
  } catch (error) {
    toast.error("Failed to update status", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  }
};

// Bulk delete
const deleteDialogOpen = ref(false);
const deletePending = ref(false);

const handleBulkDelete = async () => {
  try {
    deletePending.value = true;
    await client(`/api/forms/${slug.value}/responses/bulk`, {
      method: "DELETE",
      body: { ids: selectedRowIds.value },
    });
    toast.success(`${selectedRowIds.value.length} response(s) deleted`);
    deleteDialogOpen.value = false;
    clearSelection();
    await fetchResponses();
    emit("refresh");
  } catch (error) {
    toast.error("Failed to delete responses", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

// Single delete
const handleDeleteSingle = async (response) => {
  try {
    await client(`/api/forms/${slug.value}/responses/${response.ulid}`, {
      method: "DELETE",
    });
    toast.success("Response deleted");
    await fetchResponses();
    emit("refresh");
  } catch (error) {
    toast.error("Failed to delete response", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  }
};

// Single status update
const handleUpdateStatus = async (response, status) => {
  try {
    await client(`/api/forms/${slug.value}/responses/bulk-status`, {
      method: "PUT",
      body: { ids: [response.id], status },
    });
    toast.success(`Response marked as ${status}`);
    await fetchResponses();
  } catch (error) {
    toast.error("Failed to update status", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  }
};

// Row Actions Component
const RowActions = defineComponent({
  props: {
    response: { type: Object, required: true },
  },
  setup(actionProps) {
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
                { align: "end", class: "w-44 p-1" },
                {
                  default: () =>
                    h("div", { class: "flex flex-col" }, [
                      // Status submenu
                      ...statusOptions.map((s) =>
                        h(
                          PopoverClose,
                          { asChild: true, key: s.value },
                          {
                            default: () =>
                              h(
                                "button",
                                {
                                  class:
                                    "hover:bg-muted rounded-md px-3 py-2 text-left text-sm tracking-tight flex items-center gap-x-1.5",
                                  onClick: () => handleUpdateStatus(actionProps.response, s.value),
                                },
                                [
                                  h(resolveComponent("Icon"), {
                                    name: s.icon,
                                    class: `size-4 shrink-0 ${s.color}`,
                                  }),
                                  h("span", {}, s.label),
                                ]
                              ),
                          }
                        )
                      ),
                      // Separator
                      h("div", { class: "border-border my-1 border-t" }),
                      // Delete
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
                  "This action can't be undone. This will permanently delete this response."
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
                          await handleDeleteSingle(actionProps.response);
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
  setup(filterProps, { emit: filterEmit }) {
    return () =>
      h("div", { class: "space-y-2" }, [
        h("div", { class: "text-muted-foreground text-xs font-medium" }, filterProps.title),
        h(
          "div",
          { class: "space-y-2" },
          filterProps.options.map((option, i) => {
            const value = typeof option === "string" ? option : option.value;
            const label = typeof option === "string" ? option : option.label;
            return h("div", { key: value, class: "flex items-center gap-2" }, [
              h(Checkbox, {
                id: `${filterProps.title}-${i}`,
                modelValue: filterProps.selected.includes(value),
                "onUpdate:modelValue": (checked) =>
                  filterEmit("change", { checked: !!checked, value }),
              }),
              h(
                Label,
                {
                  for: `${filterProps.title}-${i}`,
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
