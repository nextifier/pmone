<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:coupon-03" class="size-5 sm:size-6" />
        <h1 class="page-title">Promo Codes</h1>
      </div>

      <div class="ml-auto flex shrink-0 items-center gap-1 sm:gap-2">
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
          to="/promo-codes/trash"
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
      model="promo-codes"
      label="Promo Code"
      search-column="code"
      search-placeholder="Search by code or email..."
      error-title="Error loading promo codes"
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
        <Button v-if="canCreate" size="sm" @click="navigateTo('/promo-codes/create')">
          <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
          New Code
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
                title="Status"
                :options="[
                  { label: 'Active', value: 'active' },
                  { label: 'Inactive', value: 'inactive' },
                ]"
                :selected="filterState.is_active"
                @change="handleFilterChange('is_active', $event)"
              />
              <div class="space-y-2">
                <div class="text-muted-foreground text-xs font-medium">Usage</div>
                <div class="flex items-center gap-2">
                  <Checkbox
                    id="filter-fully-used"
                    :model-value="filterState.fullyUsed"
                    @update:model-value="handleFullyUsedChange"
                  />
                  <Label
                    for="filter-fully-used"
                    class="grow cursor-pointer font-normal tracking-tight"
                  >
                    Fully used only
                  </Label>
                </div>
              </div>
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
              <div class="text-primary text-lg font-semibold tracking-tight">Are you sure?</div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This will move {{ selectedRows.length }} selected promo
                {{ selectedRows.length === 1 ? "code" : "codes" }} to trash.
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
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { TableBulkAction } from "@/components/ui/table-data";
import { PopoverClose } from "reka-ui";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["promo_codes.read"],
  layout: "app",
});

usePageMeta(null, { title: "Promo Codes" });

const { hasPermission } = usePermission();
const canCreate = computed(() => hasPermission("promo_codes.create"));
const canUpdate = computed(() => hasPermission("promo_codes.update"));
const canDelete = computed(() => hasPermission("promo_codes.delete"));
const route = useRoute();

const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 15 });
const sorting = ref([{ id: "created_at", desc: true }]);

// Structured filters (popover + deep-link), kept separate from the TableData-owned search filter.
const filterState = reactive({
  rule_id: route.query.filter_rule_id || null,
  event_id: null,
  is_active: [],
  fullyUsed: false,
});

const buildQueryParams = () => {
  const params = new URLSearchParams();
  params.append("page", pagination.value.pageIndex + 1);
  params.append("per_page", pagination.value.pageSize);

  const search = columnFilters.value.find((f) => f.id === "code")?.value;
  if (search) {
    params.append("filter_search", search);
  }

  if (filterState.rule_id) {
    params.append("filter_rule_id", filterState.rule_id);
  }
  if (filterState.event_id) {
    params.append("filter_event_id", filterState.event_id);
  }
  if (filterState.is_active.length === 1) {
    params.append("filter_is_active", filterState.is_active[0] === "active");
  }
  if (filterState.fullyUsed) {
    params.append("filter_fully_used", "true");
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
  refresh: fetchCodes,
} = await useLazySanctumFetch(() => `/api/promo-codes?${buildQueryParams()}`, {
  key: "promo-codes-list",
  watch: false,
});

const data = computed(() => response.value?.data || []);
const meta = computed(
  () => response.value?.meta || { current_page: 1, last_page: 1, per_page: 15, total: 0 },
);

watch([columnFilters, sorting, pagination, filterState], () => fetchCodes(), { deep: true });

const onPaginationUpdate = (val) => {
  pagination.value.pageIndex = val.pageIndex;
  pagination.value.pageSize = val.pageSize;
};
const onSortingUpdate = (val) => (sorting.value = val);
const onColumnFiltersUpdate = (val) => (columnFilters.value = val);
const refresh = fetchCodes;

const tableRef = ref();

// Filter popover
const totalActiveFilters = computed(() => {
  let count = 0;
  if (filterState.is_active.length === 1) count++;
  if (filterState.fullyUsed) count++;
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

const handleFullyUsedChange = (checked) => {
  filterState.fullyUsed = !!checked;
  resetToFirstPage();
};

// Export
const exportPending = ref(false);
const handleExport = async () => {
  try {
    exportPending.value = true;
    const params = new URLSearchParams();

    const search = columnFilters.value.find((f) => f.id === "code")?.value;
    if (search) {
      params.append("filter_search", search);
    }
    if (filterState.rule_id) {
      params.append("filter_rule_id", filterState.rule_id);
    }
    if (filterState.event_id) {
      params.append("filter_event_id", filterState.event_id);
    }
    if (filterState.is_active.length === 1) {
      params.append("filter_is_active", filterState.is_active[0] === "active");
    }
    if (filterState.fullyUsed) {
      params.append("filter_fully_used", "true");
    }

    const sortField = sorting.value[0]?.id || "created_at";
    const sortDir = sorting.value[0]?.desc ? "desc" : "asc";
    params.append("sort", sortDir === "desc" ? `-${sortField}` : sortField);

    const client = useSanctumClient();
    const fileResponse = await client(`/api/promo-codes/export?${params.toString()}`, {
      responseType: "blob",
    });

    const blob = new Blob([fileResponse], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `promo_codes_${new Date().toISOString().slice(0, 19).replace(/:/g, "-")}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);

    toast.success("Promo codes exported");
  } catch (err) {
    toast.error("Failed to export promo codes", {
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
    const result = await client("/api/promo-codes/bulk", {
      method: "DELETE",
      body: { ids },
    });
    await refresh();
    deleteDialogOpen.value = false;
    tableRef.value?.resetRowSelection();
    toast.success(result.message || "Promo codes deleted", {
      description: `${result.deleted_count} promo code(s) deleted`,
    });
  } catch (err) {
    toast.error("Failed to delete promo codes", {
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
    const result = await client(`/api/promo-codes/${ulid}`, { method: "DELETE" });
    await refresh();
    tableRef.value?.resetRowSelection();
    toast.success(result.message || "Promo code deleted");
  } catch (err) {
    toast.error("Failed to delete promo code", {
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
    header: "Code",
    accessorKey: "code",
    cell: ({ row }) =>
      h(
        NuxtLink,
        {
          to: `/promo-codes/${row.original.ulid}/show`,
          class: "font-mono text-sm hover:underline",
        },
        () => row.original.code,
      ),
    size: 180,
    enableHiding: false,
  },
  {
    header: "Rule",
    accessorKey: "promotion_rule.name",
    cell: ({ row }) => {
      const rule = row.original.promotion_rule;
      if (!rule)
        return h(
          "span",
          { class: "text-muted-foreground text-xs tracking-tight sm:text-sm" },
          "-",
        );
      const display =
        rule.value_type === "percentage" ? `${rule.value}%` : `Rp${formatRupiah(rule.value)}`;
      return h(
        NuxtLink,
        {
          to: `/promotion-rules/${rule.ulid}/show`,
          class: "text-sm tracking-tight hover:underline",
        },
        () => `${rule.name} (${display})`,
      );
    },
    size: 250,
  },
  {
    header: "Usage",
    accessorKey: "usage_count",
    cell: ({ row }) => {
      const used = row.original.usage_count ?? 0;
      const limit = row.original.usage_limit ?? "∞";
      return h(
        "span",
        { class: "text-sm tabular-nums tracking-tight" },
        `${used} / ${limit}`,
      );
    },
    size: 100,
  },
  {
    header: "Valid Until",
    accessorKey: "valid_until",
    cell: ({ row }) => {
      const v = row.original.valid_until;
      if (!v)
        return h(
          "span",
          { class: "text-muted-foreground/70 text-xs tracking-tight sm:text-sm" },
          "No expiry",
        );
      return h(
        "span",
        { class: "text-xs tracking-tight tabular-nums sm:text-sm" },
        new Date(v).toLocaleDateString("id-ID"),
      );
    },
    size: 130,
  },
  {
    header: "Status",
    accessorKey: "is_active",
    cell: ({ row }) => {
      const r = row.original;
      const isFullyUsed = r.is_fully_used;
      const label = !r.is_active ? "Inactive" : isFullyUsed ? "Fully Used" : "Active";
      const variant = !r.is_active ? "muted" : isFullyUsed ? "warning" : "success";
      return h(
        Badge,
        { variant, withIcon: true, plain: true },
        { default: () => label },
      );
    },
    size: 110,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) => h(RowActions, { code: row.original }),
    size: 60,
    enableHiding: false,
  },
];

// Row Actions Component
const RowActions = defineComponent({
  props: {
    code: { type: Object, required: true },
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
                                to: `/promo-codes/${props.code.ulid}/show`,
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
                                    to: `/promo-codes/${props.code.ulid}/edit`,
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
                  { class: "text-primary text-lg font-semibold tracking-tight" },
                  "Are you sure?",
                ),
                h(
                  "p",
                  { class: "text-body mt-1.5 text-sm tracking-tight" },
                  "This will move this promo code to trash.",
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
                          await handleDeleteSingleRow(props.code.ulid);
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
