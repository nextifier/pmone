<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div
      class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
    >
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:blockchain-01" class="size-5 sm:size-6" />
        <h1 class="page-title">{{ $t("brands.title") }}</h1>
      </div>

      <div v-if="!hasSelectedRows" class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <BrandImportDialog v-if="canCreate" @imported="refresh">
          <template #trigger="{ open }">
            <button
              @click="open()"
              class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
            >
              <Icon name="hugeicons:file-import" class="size-4 shrink-0" />
              <span>{{ $t("common.import") }}</span>
            </button>
          </template>
        </BrandImportDialog>

        <button
          @click="handleExport"
          :disabled="exportPending"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Spinner v-if="exportPending" class="size-4 shrink-0" />
          <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
          <span>{{
            totalActiveFilters > 0 ? $t("brands.exportSelected") : $t("brands.exportAll")
          }}</span>
        </button>
      </div>

      <div v-else class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <button
          @click="clearSelection"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="lucide:x" class="size-4 shrink-0" />
          <span>{{ $t("common.clearSelection") }}</span>
        </button>
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
      model="brands"
      :label="$t('brands.brand')"
      search-column="brand_name"
      :search-placeholder="$t('brands.searchBrands')"
      :error-title="$t('brands.errorLoading')"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
      :initial-column-visibility="{
        company_email: false,
        company_phone: false,
      }"
      :show-add-button="false"
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
              <span class="hidden sm:flex">{{ $t("common.filter") }}</span>
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
                :title="$t('common.status')"
                :options="[
                  { label: $t('common.active'), value: 'active' },
                  { label: $t('common.inactive'), value: 'inactive' },
                ]"
                :selected="selectedStatuses"
                @change="handleFilterChange('status', $event)"
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
              <span class="text-sm tracking-tight">{{ $t("common.delete") }}</span>
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
                {{ $t("common.areYouSure") }}
              </div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                {{
                  $t("brands.deleteConfirm", {
                    count: selectedRows.length,
                    noun: $t("common.brand", selectedRows.length),
                  })
                }}
              </p>
              <div class="mt-3 flex justify-end gap-2">
                <button
                  class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                  @click="deleteDialogOpen = false"
                  :disabled="deletePending"
                >
                  {{ $t("common.cancel") }}
                </button>
                <button
                  @click="handleDeleteRows(selectedRows)"
                  :disabled="deletePending"
                  class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  <Spinner v-if="deletePending" class="size-4 text-white" />
                  <span v-else>{{ $t("common.delete") }}</span>
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
import BrandImportDialog from "@/components/brand/BrandImportDialog.vue";
import BrandTableItem from "@/components/brand/TableItem.vue";
import DialogResponsive from "@/components/DialogResponsive.vue";
import TableData from "@/components/TableData.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { PopoverClose } from "reka-ui";
import { defineComponent, resolveComponent } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["brands.read"],
  layout: "app",
});

const { t } = useI18n();

usePageMeta(null, {
  title: t("brands.title"),
});

const { $dayjs } = useNuxtApp();
const client = useSanctumClient();
const { hasPermission } = usePermission();
const canCreate = computed(() => hasPermission("brands.create"));
const canDelete = computed(() => hasPermission("brands.delete"));
const baseUrl = "/brands";

// Table state
const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 15 });
const sorting = ref([{ id: "brand_name", desc: false }]);

// Build query params for server-side pagination
const buildQueryParams = () => {
  const params = new URLSearchParams();

  params.append("page", pagination.value.pageIndex + 1);
  params.append("per_page", pagination.value.pageSize);

  // Filters
  const filters = {
    brand_name: "filter_search",
    status: "filter_status",
  };

  Object.entries(filters).forEach(([columnId, paramKey]) => {
    const filter = columnFilters.value.find((f) => f.id === columnId);
    if (filter?.value) {
      const value = Array.isArray(filter.value) ? filter.value.join(",") : filter.value;
      params.append(paramKey, value);
    }
  });

  // Sorting
  const sortField = sorting.value[0]?.id || "brand_name";
  const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
  params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);

  return params.toString();
};

// Fetch brands with lazy loading
const {
  data: brandsResponse,
  pending,
  error,
  refresh: fetchBrands,
} = await useLazySanctumFetch(() => `/api/brands?${buildQueryParams()}`, {
  key: "brands-list",
  watch: false,
});

const data = computed(() => brandsResponse.value?.data || []);
const meta = computed(
  () => brandsResponse.value?.meta || { current_page: 1, last_page: 1, per_page: 15, total: 0 }
);

// Watch for changes and refetch
watch(
  [columnFilters, sorting, pagination],
  () => {
    fetchBrands();
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

const refresh = fetchBrands;

// Table ref
const tableRef = ref();

const hasSelectedRows = computed(() => {
  return tableRef.value?.table?.getSelectedRowModel()?.rows?.length > 0;
});

const clearSelection = () => {
  if (tableRef.value) {
    tableRef.value.resetRowSelection();
  }
};

// Filter helpers
const getFilterValue = (columnId) => {
  return columnFilters.value.find((f) => f.id === columnId)?.value ?? [];
};

const selectedStatuses = computed(() => getFilterValue("status"));
const totalActiveFilters = computed(() => selectedStatuses.value.length);

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

// Table columns
const columns = computed(() => [
  ...(canDelete.value
    ? [
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
      ]
    : []),
  {
    header: t("brands.brand"),
    accessorKey: "brand_name",
    cell: ({ row }) =>
      h(BrandTableItem, {
        brand: row.original,
        baseUrl: baseUrl,
        linkSuffix: "/edit",
      }),
    size: 300,
    enableHiding: false,
  },
  {
    header: t("common.status"),
    accessorKey: "status",
    cell: ({ row }) => {
      const status = row.getValue("status");
      const classes = {
        active: "text-success-foreground",
        inactive: "text-muted-foreground",
      };
      return h(
        "span",
        {
          class: `inline-flex items-center text-sm tracking-tight capitalize ${classes[status] || "text-muted-foreground"}`,
        },
        status
      );
    },
    size: 100,
  },
  {
    header: t("brands.events"),
    accessorKey: "events",
    cell: ({ row }) => {
      const events = row.getValue("events") || [];
      if (!events.length) return h("span", { class: "text-muted-foreground text-sm" }, "-");
      const visible = events.slice(0, 2);
      const remaining = events.length - visible.length;
      return h("div", { class: "flex flex-wrap gap-1" }, [
        ...visible.map((e) =>
          h(
            "span",
            {
              class:
                "bg-muted text-muted-foreground inline-flex truncate rounded px-1.5 py-0.5 text-xs tracking-tight",
            },
            e.title
          )
        ),
        remaining > 0
          ? h(
              "span",
              {
                class:
                  "bg-muted text-muted-foreground inline-flex rounded px-1.5 py-0.5 text-xs tracking-tight",
              },
              `+${remaining}`
            )
          : null,
      ]);
    },
    size: 200,
    enableSorting: false,
  },
  {
    header: t("brands.members"),
    accessorKey: "members_count",
    cell: ({ row }) => {
      const count = row.getValue("members_count") || 0;
      return h("div", { class: "text-sm tracking-tight" }, count.toLocaleString());
    },
    size: 80,
  },
  {
    header: t("brands.categories"),
    accessorKey: "business_categories",
    cell: ({ row }) => {
      const cats = row.getValue("business_categories") || [];
      if (!cats.length) return h("span", { class: "text-muted-foreground text-sm" }, "-");
      return h(
        "div",
        { class: "text-muted-foreground line-clamp-1 text-xs tracking-tight" },
        cats.join(", ")
      );
    },
    size: 150,
  },
  {
    header: t("brands.email"),
    accessorKey: "company_email",
    cell: ({ row }) => {
      const email = row.getValue("company_email");
      if (!email) return h("span", { class: "text-muted-foreground text-sm" }, "-");
      return h("span", { class: "text-sm tracking-tight" }, email);
    },
    size: 180,
  },
  {
    header: t("brands.phone"),
    accessorKey: "company_phone",
    cell: ({ row }) => {
      const phone = row.getValue("company_phone");
      if (!phone) return h("span", { class: "text-muted-foreground text-sm" }, "-");
      return h("span", { class: "text-sm tracking-tight" }, phone);
    },
    size: 120,
  },
  {
    header: t("brands.created"),
    accessorKey: "created_at",
    cell: ({ row }) => {
      const date = row.getValue("created_at");
      if (!date) return h("span", { class: "text-muted-foreground text-sm" }, "-");
      return h(
        "div",
        { class: "text-muted-foreground text-sm tracking-tight" },
        $dayjs(date).fromNow()
      );
    },
    size: 100,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) => h(RowActions, { brand: row.original }),
    size: 60,
    enableHiding: false,
  },
]);

// Export
const exportPending = ref(false);

const handleExport = async () => {
  try {
    exportPending.value = true;

    // Build query params from current filters
    const params = new URLSearchParams();

    columnFilters.value.forEach((filter) => {
      const filterMapping = { brand_name: "filter_search", status: "filter_status" };
      const paramKey = filterMapping[filter.id];
      if (paramKey && filter.value) {
        const paramValue = Array.isArray(filter.value) ? filter.value.join(",") : filter.value;
        params.append(paramKey, paramValue);
      }
    });

    const sortField = sorting.value[0]?.id || "brand_name";
    const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
    params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);

    const response = await client(`/api/brands/export?${params.toString()}`, {
      responseType: "blob",
    });

    const blob = new Blob([response], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `brands_${new Date().toISOString().slice(0, 19).replace(/:/g, "-")}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);

    toast.success(t("brands.exportedSuccess"));
  } catch (err) {
    console.error("Failed to export brands:", err);
    toast.error(t("brands.failedToExport"), {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    exportPending.value = false;
  }
};

// Delete
const deleteDialogOpen = ref(false);
const deletePending = ref(false);

const handleDeleteRows = async (selectedRows) => {
  const slugs = selectedRows.map((row) => row.original.brand_slug);
  try {
    deletePending.value = true;
    await Promise.all(slugs.map((slug) => client(`/api/brands/${slug}`, { method: "DELETE" })));
    await refresh();
    deleteDialogOpen.value = false;
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }
    toast.success(t("brands.deletedSuccess", { count: slugs.length }));
  } catch (err) {
    toast.error(t("brands.failedToDelete"), {
      description: err?.data?.message || err?.message,
    });
  } finally {
    deletePending.value = false;
  }
};

const handleDeleteSingleRow = async (brandSlug) => {
  try {
    deletePending.value = true;
    await client(`/api/brands/${brandSlug}`, { method: "DELETE" });
    await refresh();
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }
    toast.success(t("brands.deletedSingleSuccess"));
  } catch (err) {
    toast.error(t("brands.failedToDeleteSingle"), {
      description: err?.data?.message || err?.message,
    });
  } finally {
    deletePending.value = false;
  }
};

// Row Actions Component
const RowActions = defineComponent({
  props: {
    brand: { type: Object, required: true },
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
                      // Edit
                      h(
                        PopoverClose,
                        { asChild: true },
                        {
                          default: () =>
                            h(
                              resolveComponent("NuxtLink"),
                              {
                                to: `${baseUrl}/${props.brand.brand_slug}/edit`,
                                class:
                                  "hover:bg-muted rounded-md px-3 py-2 text-left text-sm tracking-tight flex items-center gap-x-1.5",
                              },
                              {
                                default: () => [
                                  h(resolveComponent("Icon"), {
                                    name: "lucide:pencil",
                                    class: "size-4 shrink-0",
                                  }),
                                  h("span", {}, t("common.edit")),
                                ],
                              }
                            ),
                        }
                      ),
                      // Delete (only if user has brands.delete permission)
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
                                      h("span", {}, t("common.delete")),
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
                  t("common.areYouSure")
                ),
                h(
                  "p",
                  { class: "text-body mt-1.5 text-sm tracking-tight" },
                  t("brands.deleteSingleConfirm")
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
                    t("common.cancel")
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
                          await handleDeleteSingleRow(props.brand.brand_slug);
                          dialogOpen.value = false;
                        } finally {
                          singleDeletePending.value = false;
                        }
                      },
                    },
                    singleDeletePending.value
                      ? h(resolveComponent("Spinner"), { class: "size-4 text-white" })
                      : t("common.delete")
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
