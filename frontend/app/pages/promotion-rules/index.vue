<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex flex-col gap-y-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:discount-tag-02" class="size-5 sm:size-6" />
        <h1 class="page-title">Promotion Rules</h1>
      </div>

      <div class="flex shrink-0 gap-1 sm:gap-2">
        <NuxtLink
          to="/promotion-rules/guide"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:book-open-01" class="size-4 shrink-0" />
          <span>Panduan</span>
        </NuxtLink>

        <button
          @click="handleExport"
          :disabled="exportPending"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Spinner v-if="exportPending" class="size-4 shrink-0" />
          <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
          <span>Export</span>
        </button>

        <NuxtLink
          v-if="canDelete"
          to="/promotion-rules/trash"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
          <span>Trash</span>
        </NuxtLink>
      </div>
    </div>

    <TableData
      ref="tableRef"
      :client-only="false"
      :data="data"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="promotion-rules"
      label="Promotion Rule"
      search-column="name"
      search-placeholder="Search rules..."
      error-title="Error loading promotion rules"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
      :show-add-button="canCreate"
      @update:pagination="onPaginationUpdate"
      @update:sorting="onSortingUpdate"
      @update:column-filters="onColumnFiltersUpdate"
      @refresh="refresh"
    >
      <template #add-button>
        <Button v-if="canCreate" size="sm" @click="navigateTo('/promotion-rules/create')">
          <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
          New Rule
        </Button>
      </template>

      <template #filters>
        <Popover>
          <PopoverTrigger as-child>
            <button
              class="hover:bg-muted relative flex aspect-square h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border text-sm tracking-tight active:scale-98 sm:aspect-auto sm:px-2.5"
            >
              <Icon name="hugeicons:filter-horizontal" class="size-4 shrink-0" />
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
                title="Kind"
                :options="[
                  { label: 'Discount', value: 'discount' },
                  { label: 'Penalty', value: 'penalty' },
                ]"
                :selected="filterState.kind"
                @change="handleFilterChange('kind', $event)"
              />
              <FilterSection
                title="Status"
                :options="[
                  { label: 'Active', value: 'active' },
                  { label: 'Inactive', value: 'inactive' },
                ]"
                :selected="filterState.is_active"
                @change="handleFilterChange('is_active', $event)"
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
            <TableBulkAction icon="lucide:trash" label="Delete" destructive @click="open()" />
          </template>
          <template #default>
            <div class="px-4 pb-10 md:px-6 md:py-5">
              <div class="text-foreground text-lg font-semibold tracking-tight">Are you sure?</div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This will move {{ selectedRows.length }} selected promotion
                {{ selectedRows.length === 1 ? "rule" : "rules" }} to trash.
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
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { TableBulkAction } from "@/components/ui/table-data";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { PopoverClose } from "reka-ui";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["promotion_rules.read"],
  layout: "app",
});

usePageMeta(null, { title: "Promotion Rules" });

const { hasPermission } = usePermission();
const canCreate = computed(() => hasPermission("promotion_rules.create"));
const canUpdate = computed(() => hasPermission("promotion_rules.update"));
const canDelete = computed(() => hasPermission("promotion_rules.delete"));

const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 15 });
const sorting = ref([{ id: "created_at", desc: true }]);

// Structured filters (popover), kept separate from the TableData-owned search filter.
const filterState = reactive({
  kind: [],
  is_active: [],
});

const buildQueryParams = () => {
  const params = new URLSearchParams();
  params.append("page", pagination.value.pageIndex + 1);
  params.append("per_page", pagination.value.pageSize);

  const search = columnFilters.value.find((f) => f.id === "name")?.value;
  if (search) {
    params.append("filter_search", search);
  }

  if (filterState.kind.length === 1) {
    params.append("filter_kind", filterState.kind[0]);
  }
  if (filterState.is_active.length === 1) {
    params.append("filter_is_active", filterState.is_active[0] === "active");
  }

  const sortField = sorting.value[0]?.id || "created_at";
  const sortDir = sorting.value[0]?.desc ? "desc" : "asc";
  params.append("sort", sortDir === "desc" ? `-${sortField}` : sortField);
  return params.toString();
};

const {
  data: response,
  pending,
  error,
  refresh: fetchRules,
} = await useLazySanctumFetch(() => `/api/promotion-rules?${buildQueryParams()}`, {
  key: "promotion-rules-list",
  watch: false,
});

const data = computed(() => response.value?.data || []);
const meta = computed(
  () => response.value?.meta || { current_page: 1, last_page: 1, per_page: 15, total: 0 },
);

watch([columnFilters, sorting, pagination, filterState], () => fetchRules(), { deep: true });

const onPaginationUpdate = (val) => {
  pagination.value.pageIndex = val.pageIndex;
  pagination.value.pageSize = val.pageSize;
};
const onSortingUpdate = (val) => (sorting.value = val);
const onColumnFiltersUpdate = (val) => (columnFilters.value = val);
const refresh = fetchRules;

const tableRef = ref();

// Filter popover
const totalActiveFilters = computed(() => {
  let count = 0;
  if (filterState.kind.length === 1) count++;
  if (filterState.is_active.length === 1) count++;
  return count;
});

const resetToFirstPage = () => {
  pagination.value = { ...pagination.value, pageIndex: 0 };
  tableRef.value?.table?.setPageIndex(0);
};

const handleFilterChange = (key, { checked, value }) => {
  const current = filterState[key];
  filterState[key] = checked
    ? [...current, value]
    : current.filter((item) => item !== value);
  resetToFirstPage();
};

// Export
const exportPending = ref(false);
const handleExport = async () => {
  try {
    exportPending.value = true;
    const params = new URLSearchParams();

    const search = columnFilters.value.find((f) => f.id === "name")?.value;
    if (search) {
      params.append("filter_search", search);
    }
    if (filterState.kind.length === 1) {
      params.append("filter_kind", filterState.kind[0]);
    }
    if (filterState.is_active.length === 1) {
      params.append("filter_is_active", filterState.is_active[0] === "active");
    }

    const sortField = sorting.value[0]?.id || "created_at";
    const sortDir = sorting.value[0]?.desc ? "desc" : "asc";
    params.append("sort", sortDir === "desc" ? `-${sortField}` : sortField);

    const client = useSanctumClient();
    const fileResponse = await client(`/api/promotion-rules/export?${params.toString()}`, {
      responseType: "blob",
    });

    const blob = new Blob([fileResponse], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `promotion_rules_${new Date().toISOString().slice(0, 19).replace(/:/g, "-")}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);

    toast.success("Promotion rules exported");
  } catch (err) {
    toast.error("Failed to export promotion rules", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    exportPending.value = false;
  }
};

// Delete handlers
const deleteDialogOpen = ref(false);
const deletePending = ref(false);

const handleDeleteRows = async (selectedRows) => {
  const ids = selectedRows.map((row) => row.original.id);
  try {
    deletePending.value = true;
    const client = useSanctumClient();
    const result = await client("/api/promotion-rules/bulk", {
      method: "DELETE",
      body: { ids },
    });
    await refresh();
    deleteDialogOpen.value = false;
    tableRef.value?.resetRowSelection();
    toast.success(result.message || "Promotion rules deleted", {
      description: `${result.deleted_count} promotion rule(s) deleted`,
    });
  } catch (err) {
    toast.error("Failed to delete promotion rules", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

const handleDeleteSingleRow = async (ulid) => {
  try {
    deletePending.value = true;
    const client = useSanctumClient();
    const result = await client(`/api/promotion-rules/${ulid}`, { method: "DELETE" });
    await refresh();
    tableRef.value?.resetRowSelection();
    toast.success(result.message || "Promotion rule deleted");
  } catch (err) {
    toast.error("Failed to delete promotion rule", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);

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
    header: "Name",
    accessorKey: "name",
    cell: ({ row }) =>
      h(
        NuxtLink,
        {
          to: `/promotion-rules/${row.original.ulid}/show`,
          class: "font-medium tracking-tight hover:underline",
        },
        () => row.original.name,
      ),
    size: 250,
    enableHiding: false,
  },
  {
    header: "Kind",
    accessorKey: "kind",
    cell: ({ row }) => {
      const kind = row.original.kind;
      return h(
        Badge,
        {
          variant: kind === "discount" ? "success" : "warning",
        },
        { default: () => row.original.kind_label || kind },
      );
    },
    size: 120,
  },
  {
    header: "Value",
    accessorKey: "value",
    cell: ({ row }) => {
      const r = row.original;
      const display = r.value_type === "percentage" ? `${r.value}%` : `Rp${formatRupiah(r.value)}`;
      return h("span", { class: "text-sm tabular-nums tracking-tight" }, display);
    },
    size: 120,
  },
  {
    header: "Stacking",
    accessorKey: "stacking_mode",
    cell: ({ row }) =>
      h(
        "span",
        { class: "text-muted-foreground text-xs tracking-tight sm:text-sm" },
        row.original.stacking_mode_label ||
          row.original.stacking_mode?.replace(/_/g, " ") ||
          "-",
      ),
    size: 180,
  },
  {
    header: "Active",
    accessorKey: "is_active",
    cell: ({ row }) =>
      h(
        Badge,
        {
          variant: row.original.is_active ? "success" : "muted",
          withIcon: true,
          plain: true,
        },
        { default: () => (row.original.is_active ? "Active" : "Inactive") },
      ),
    size: 100,
  },
  {
    header: "Codes",
    accessorKey: "codes_count",
    cell: ({ row }) => {
      const n = row.original.codes_count ?? 0;
      return h(
        "span",
        {
          class: `tabular-nums tracking-tight text-xs sm:text-sm ${n === 0 ? "text-muted-foreground/50" : "text-muted-foreground"}`,
        },
        n,
      );
    },
    size: 80,
  },
  {
    header: "Used",
    accessorKey: "applied_count",
    cell: ({ row }) => {
      const n = row.original.applied_count ?? 0;
      return h(
        "span",
        {
          class: `tabular-nums tracking-tight text-xs sm:text-sm ${n === 0 ? "text-muted-foreground/50" : "text-muted-foreground"}`,
        },
        n,
      );
    },
    size: 80,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) => h(RowActions, { rule: row.original }),
    size: 60,
    enableHiding: false,
  },
];

// Row Actions Component
const RowActions = defineComponent({
  props: {
    rule: { type: Object, required: true },
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
                      [h(resolveComponent("Icon"), { name: "lucide:ellipsis", class: "size-4" })],
                    ),
                },
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
                                to: `/promotion-rules/${props.rule.ulid}/show`,
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
                              },
                            ),
                        },
                      ),
                      canUpdate.value
                        ? h(
                            PopoverClose,
                            { asChild: true },
                            {
                              default: () =>
                                h(
                                  resolveComponent("NuxtLink"),
                                  {
                                    to: `/promotion-rules/${props.rule.ulid}/edit`,
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
                                  },
                                ),
                            },
                          )
                        : null,
                      canDelete.value
                        ? h(
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
                                  ],
                                ),
                            },
                          )
                        : null,
                    ]),
                },
              ),
            ],
          },
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
                  { class: "text-foreground text-lg font-semibold tracking-tight" },
                  "Are you sure?",
                ),
                h(
                  "p",
                  { class: "text-body mt-1.5 text-sm tracking-tight" },
                  "This will move this promotion rule to trash.",
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
                    "Cancel",
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
                          await handleDeleteSingleRow(props.rule.ulid);
                          dialogOpen.value = false;
                        } finally {
                          singleDeletePending.value = false;
                        }
                      },
                    },
                    singleDeletePending.value
                      ? h(resolveComponent("Spinner"), { class: "size-4 text-white" })
                      : "Delete",
                  ),
                ]),
              ]),
          },
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
                { default: () => label },
              ),
            ]);
          }),
        ),
      ]);
  },
});
</script>
