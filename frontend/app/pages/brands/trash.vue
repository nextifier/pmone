<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex items-center justify-between gap-x-2.5">
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:delete-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Brand Trash</h1>
      </div>

      <div class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <NuxtLink
          to="/brands"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:store-02" class="size-4 shrink-0" />
          <span>All Brands</span>
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
      model="brands-trash"
      search-column="brand_name"
      :show-add-button="false"
      search-placeholder="Search brands..."
      error-title="Error loading trashed brands"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
      @update:pagination="onPaginationUpdate"
      @update:sorting="onSortingUpdate"
      @update:column-filters="onColumnFiltersUpdate"
      @refresh="refresh"
    >
      <template #actions="{ selectedRows }">
        <DialogResponsive
          v-if="selectedRows.length > 0"
          v-model:open="restoreDialogOpen"
          class="h-full"
        >
          <template #trigger="{ open }">
            <TableBulkAction icon="hugeicons:undo-02" label="Restore" @click="open()" />
          </template>
          <template #default>
            <div class="px-4 pb-10 md:px-6 md:py-5">
              <div class="page-title">Restore brands?</div>
              <p class="page-description mt-1.5">
                This will restore {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "brand" : "brands" }}.
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
            <TableBulkAction
              icon="hugeicons:delete-01"
              label="Delete Permanently"
              destructive
              @click="open()"
            />
          </template>
          <template #default>
            <div class="px-4 pb-10 md:px-6 md:py-5">
              <div class="page-title">Are you absolutely sure?</div>
              <p class="page-description mt-1.5">
                This action can't be undone. This will permanently delete
                {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "brand" : "brands" }}.
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
import BrandTrashRowActions from "@/components/brand/TrashRowActions.vue";
import { TableData, TableBulkAction } from "@/components/ui/table-data";
import { Checkbox } from "@/components/ui/checkbox";
import { resolveComponent, resolveDirective, withDirectives } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["brands.delete"],
  layout: "app",
});

defineOptions({
  name: "brands-trash",
});

usePageMeta(null, { title: "Brand Trash" });

const { $dayjs } = useNuxtApp();
const client = useSanctumClient();

// Table state
const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 15 });
const sorting = ref([{ id: "deleted_at", desc: true }]);

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
  const sortField = sorting.value[0]?.id || "deleted_at";
  const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
  params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);

  return params.toString();
};

// Fetch trashed brands
const {
  data: brandsResponse,
  pending,
  error,
  refresh: fetchBrands,
} = await useLazySanctumFetch(() => `/api/brands-trash?${buildQueryParams()}`, {
  key: "brands-trash-list",
  watch: false,
});

const data = computed(() => brandsResponse.value?.data || []);
const meta = computed(
  () => brandsResponse.value?.meta || { current_page: 1, last_page: 1, per_page: 15, total: 0 },
);

// Watch for changes and refetch
watch([columnFilters, sorting, pagination], () => fetchBrands(), { deep: true });

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

// Table ref & selection
const tableRef = ref();

// Bulk restore
const restoreDialogOpen = ref(false);
const restorePending = ref(false);

const handleRestoreRows = async (selectedRows) => {
  const ids = selectedRows.map((row) => row.original.id);
  restorePending.value = true;
  try {
    const response = await client("/api/brands-trash/restore/bulk", {
      method: "POST",
      body: { ids },
    });
    await refresh();
    restoreDialogOpen.value = false;
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }
    toast.success(response.message || `${ids.length} brand(s) restored`);
  } catch (err) {
    toast.error("Failed to restore brands", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    restorePending.value = false;
  }
};

// Bulk delete permanently
const deleteDialogOpen = ref(false);
const deletePending = ref(false);

const handleDeleteRows = async (selectedRows) => {
  const ids = selectedRows.map((row) => row.original.id);
  deletePending.value = true;
  try {
    const response = await client("/api/brands-trash/bulk", {
      method: "DELETE",
      body: { ids },
    });
    await refresh();
    deleteDialogOpen.value = false;
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }
    toast.success(response.message || `${ids.length} brand(s) permanently deleted`);
  } catch (err) {
    toast.error("Failed to delete brands", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
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
    header: "Brand",
    accessorKey: "brand_name",
    cell: ({ row }) => {
      const brand = row.original;
      return h("div", { class: "flex items-center gap-x-2" }, [
        h(resolveComponent("Avatar"), {
          model: { name: brand.brand_name, profile_image: brand.brand_logo },
          class: "size-10",
          rounded: "rounded-lg",
        }),
        h("div", { class: "flex flex-col items-start gap-y-0.5 overflow-hidden" }, [
          h("p", { class: "truncate" }, brand.brand_name),
          brand.company_name
            ? h(
                "p",
                { class: "text-muted-foreground truncate text-xs tracking-tight" },
                brand.company_name,
              )
            : null,
        ]),
      ]);
    },
    size: 300,
    enableHiding: false,
  },
  {
    header: "Status",
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
        status,
      );
    },
    size: 100,
  },
  {
    header: "Company",
    accessorKey: "company_name",
    cell: ({ row }) => {
      const company = row.getValue("company_name");
      if (!company) return h("span", { class: "text-muted-foreground text-sm" }, "-");
      return h("span", { class: "text-sm tracking-tight" }, company);
    },
    size: 160,
  },
  {
    header: "Deleted By",
    accessorKey: "deleter",
    cell: ({ row }) => {
      const deleter = row.getValue("deleter");
      if (!deleter) {
        return h("div", { class: "text-muted-foreground text-sm tracking-tight" }, "-");
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
      if (!date) return h("span", { class: "text-muted-foreground text-sm" }, "-");
      return withDirectives(
        h("div", { class: "text-muted-foreground text-sm tracking-tight" }, $dayjs(date).fromNow()),
        [[resolveDirective("tippy"), $dayjs(date).format("MMMM D, YYYY [at] h:mm A")]],
      );
    },
    size: 100,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) =>
      h(BrandTrashRowActions, {
        brandId: row.original.id,
        onRefresh: () => refresh(),
      }),
    size: 60,
    enableHiding: false,
  },
];
</script>
