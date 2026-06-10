<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex items-center justify-between gap-x-2.5">
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:delete-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Partner Trash</h1>
      </div>

      <div v-if="!hasSelectedRows" class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <NuxtLink
          to="/partners"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:dim-sum-02" class="size-4 shrink-0" />
          <span>All Partners</span>
        </NuxtLink>
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
      ref="tableRef"
      :client-only="false"
      :data="data"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="partners-trash"
      search-column="partner_name"
      :show-add-button="false"
      search-placeholder="Search partners..."
      error-title="Error loading trashed partners"
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
              <div class="page-title">Restore partners?</div>
              <p class="page-description mt-1.5">
                This will restore {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "partner" : "partners" }}.
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
              <div class="page-title">Are you absolutely sure?</div>
              <p class="page-description mt-1.5">
                This action can't be undone. This will permanently delete
                {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "partner" : "partners" }}.
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
import PartnerTrashRowActions from "@/components/partner/TrashRowActions.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { TableData } from "@/components/ui/table-data";
import { resolveComponent, resolveDirective, withDirectives } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["partners.delete"],
  layout: "app",
});

defineOptions({
  name: "partners-trash",
});

usePageMeta(null, { title: "Partner Trash" });

const { $dayjs } = useNuxtApp();
const client = useSanctumClient();

const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 15 });
const sorting = ref([{ id: "deleted_at", desc: true }]);

const buildQueryParams = () => {
  const params = new URLSearchParams();
  params.append("page", pagination.value.pageIndex + 1);
  params.append("per_page", pagination.value.pageSize);

  const filters = { partner_name: "filter_search" };

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

  return params.toString();
};

const {
  data: partnersResponse,
  pending,
  error,
  refresh: fetchPartners,
} = await useLazySanctumFetch(() => `/api/partners-trash?${buildQueryParams()}`, {
  key: "partners-trash-list",
  watch: false,
});

const data = computed(() => partnersResponse.value?.data || []);
const meta = computed(
  () => partnersResponse.value?.meta || { current_page: 1, last_page: 1, per_page: 15, total: 0 }
);

watch([columnFilters, sorting, pagination], () => fetchPartners(), { deep: true });

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

const refresh = fetchPartners;

const tableRef = ref();
const hasSelectedRows = computed(() => {
  return tableRef.value?.table?.getSelectedRowModel()?.rows?.length > 0;
});
const clearSelection = () => {
  if (tableRef.value) tableRef.value.resetRowSelection();
};

// Bulk restore
const restoreDialogOpen = ref(false);
const restorePending = ref(false);

const handleRestoreRows = async (selectedRows) => {
  const ids = selectedRows.map((row) => row.original.id);
  restorePending.value = true;
  try {
    const response = await client("/api/partners-trash/restore/bulk", {
      method: "POST",
      body: { ids },
    });
    await refresh();
    restoreDialogOpen.value = false;
    if (tableRef.value) tableRef.value.resetRowSelection();
    toast.success(response.message || `${ids.length} partner(s) restored`);
  } catch (err) {
    toast.error("Failed to restore partners", {
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
    const response = await client("/api/partners-trash/bulk", {
      method: "DELETE",
      body: { ids },
    });
    await refresh();
    deleteDialogOpen.value = false;
    if (tableRef.value) tableRef.value.resetRowSelection();
    toast.success(response.message || `${ids.length} partner(s) permanently deleted`);
  } catch (err) {
    toast.error("Failed to delete partners", {
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
    header: "Partner",
    accessorKey: "partner_name",
    cell: ({ row }) => {
      const partner = row.original;
      return h("div", { class: "flex items-center gap-x-2" }, [
        h(resolveComponent("Avatar"), {
          model: { name: partner.partner_name, profile_image: partner.partner_logo },
          class: "size-10",
          rounded: "rounded-lg",
        }),
        h("div", { class: "flex flex-col items-start gap-y-0.5 overflow-hidden" }, [
          h("p", { class: "truncate" }, partner.partner_name),
          partner.website_url
            ? h(
                "p",
                { class: "text-muted-foreground truncate text-xs tracking-tight" },
                partner.website_url.replace(/^https?:\/\//, "")
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
        status
      );
    },
    size: 100,
  },
  {
    header: "Deleted By",
    accessorKey: "deleter",
    cell: ({ row }) => {
      const deleter = row.getValue("deleter");
      if (!deleter) return h("div", { class: "text-muted-foreground text-sm tracking-tight" }, "-");
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
        [[resolveDirective("tippy"), $dayjs(date).format("MMMM D, YYYY [at] h:mm A")]]
      );
    },
    size: 100,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) =>
      h(PartnerTrashRowActions, {
        partnerId: row.original.id,
        onRefresh: () => refresh(),
      }),
    size: 60,
    enableHiding: false,
  },
];
</script>
