<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex items-center justify-between gap-x-2.5">
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:delete-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Contact Trash</h1>
      </div>

      <div v-if="!hasSelectedRows" class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <button
          v-if="meta.total > 0"
          @click="deleteAllDialogOpen = true"
          class="border-border hover:bg-muted text-destructive-foreground flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:delete-02" class="size-4 shrink-0" />
          <span>Delete All</span>
        </button>
        <NuxtLink
          to="/contacts"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:contact-01" class="size-4 shrink-0" />
          <span>All Contacts</span>
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
      model="contacts-trash"
      search-column="name"
      :show-add-button="false"
      search-placeholder="Search contacts..."
      error-title="Error loading trashed contacts"
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
          <PopoverContent class="w-auto min-w-48 p-3 pb-4.5" align="end">
            <div class="space-y-4">
              <FilterSection
                title="Status"
                :options="[
                  { label: 'Active', value: 'active' },
                  { label: 'Inactive', value: 'inactive' },
                  { label: 'Archived', value: 'archived' },
                ]"
                :selected="selectedStatuses"
                @change="handleFilterChange('status', $event)"
              />
              <FilterSection
                title="Source"
                :options="[
                  { label: 'Event', value: 'event' },
                  { label: 'Referral', value: 'referral' },
                  { label: 'Website', value: 'website' },
                  { label: 'Website Inquiries', value: 'website inquiries' },
                  { label: 'Import', value: 'import' },
                  { label: 'Manual', value: 'manual' },
                ]"
                :selected="selectedSources"
                @change="handleFilterChange('source', $event)"
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
              <div class="text-primary text-lg font-semibold tracking-tight">Restore contacts?</div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This will restore {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "contact" : "contacts" }}.
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
                {{ selectedRows.length === 1 ? "contact" : "contacts" }}.
              </p>

              <!-- Progress bar -->
              <div v-if="deleteJob.processing.value" class="mt-3 space-y-2">
                <div class="flex items-center justify-between text-sm tracking-tight">
                  <span class="text-muted-foreground">{{ deleteJob.progress.value?.message }}</span>
                  <span class="font-medium tabular-nums">{{ deleteJob.progress.value?.percentage ?? 0 }}%</span>
                </div>
                <Progress :model-value="deleteJob.progress.value?.percentage ?? 0" indicator-class="bg-destructive" />
                <p v-if="deleteJob.progress.value?.total > 0" class="text-muted-foreground text-xs sm:text-sm tracking-tight tabular-nums">
                  {{ deleteJob.progress.value?.processed ?? 0 }} / {{ deleteJob.progress.value?.total ?? 0 }}
                </p>
              </div>

              <div v-else class="mt-3 flex justify-end gap-2">
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
                  Delete Permanently
                </button>
              </div>
            </div>
          </template>
        </DialogResponsive>
      </template>
    </TableData>

    <!-- Delete All Dialog -->
    <DialogResponsive v-model:open="deleteAllDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">
            Delete all trashed contacts?
          </div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            This action can't be undone. This will permanently delete
            <span class="text-primary font-medium">all {{ meta.total }}</span>
            trashed {{ meta.total === 1 ? "contact" : "contacts" }}.
          </p>

          <!-- Progress bar -->
          <div v-if="deleteAllJob.processing.value" class="mt-3 space-y-2">
            <div class="flex items-center justify-between text-sm tracking-tight">
              <span class="text-muted-foreground">{{ deleteAllJob.progress.value?.message }}</span>
              <span class="font-medium tabular-nums">{{ deleteAllJob.progress.value?.percentage ?? 0 }}%</span>
            </div>
            <Progress :model-value="deleteAllJob.progress.value?.percentage ?? 0" indicator-class="bg-destructive" />
            <p v-if="deleteAllJob.progress.value?.total > 0" class="text-muted-foreground text-xs sm:text-sm tracking-tight tabular-nums">
              {{ deleteAllJob.progress.value?.processed ?? 0 }} / {{ deleteAllJob.progress.value?.total ?? 0 }}
            </p>
          </div>

          <div v-else class="mt-3 flex justify-end gap-2">
            <button
              class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              @click="deleteAllDialogOpen = false"
            >
              Cancel
            </button>
            <button
              @click="handleDeleteAll"
              class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98"
            >
              Delete All Permanently
            </button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import ContactTableItem from "@/components/contact/ContactTableItem.vue";
import ContactTrashRowActions from "@/components/contact/TrashRowActions.vue";
import DialogResponsive from "@/components/DialogResponsive.vue";
import TableData from "@/components/TableData.vue";
import FilterSection from "@/components/user/FilterSection.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Progress } from "@/components/ui/progress";
import { resolveDirective, withDirectives } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["contacts.delete"],
  layout: "app",
});

defineOptions({
  name: "contacts-trash",
});

usePageMeta(null, { title: "Contact Trash" });

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
    name: "filter_search",
    status: "filter_status",
    source: "filter_source",
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

// Fetch trashed contacts
const {
  data: contactsResponse,
  pending,
  error,
  refresh: fetchContacts,
} = await useLazySanctumFetch(() => `/api/contacts-trash?${buildQueryParams()}`, {
  key: "contacts-trash-list",
  watch: false,
});

const data = computed(() => contactsResponse.value?.data || []);
const meta = computed(
  () => contactsResponse.value?.meta || { current_page: 1, last_page: 1, per_page: 15, total: 0 },
);

// Watch for changes and refetch
watch([columnFilters, sorting, pagination], () => fetchContacts(), { deep: true });

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

const refresh = fetchContacts;

// Table ref & selection
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
const selectedSources = computed(() => getFilterValue("source"));
const totalActiveFilters = computed(
  () => selectedStatuses.value.length + selectedSources.value.length,
);

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

// Bulk restore
const restoreDialogOpen = ref(false);
const restorePending = ref(false);

const handleRestoreRows = async (selectedRows) => {
  const ids = selectedRows.map((row) => row.original.id);
  restorePending.value = true;
  try {
    const response = await client("/api/contacts-trash/restore/bulk", {
      method: "POST",
      body: { ids },
    });
    await refresh();
    restoreDialogOpen.value = false;
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }
    toast.success(response.message || `${ids.length} contact(s) restored`);
  } catch (err) {
    toast.error("Failed to restore contacts", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    restorePending.value = false;
  }
};

// Bulk delete permanently (queued with progress)
const deleteDialogOpen = ref(false);
const deleteJob = useJobProgress();

// Prevent closing delete dialog while processing
watch(deleteDialogOpen, (open) => {
  if (!open && deleteJob.processing.value) {
    deleteDialogOpen.value = true;
  }
});

// Watch delete completion
watch(
  () => deleteJob.progress.value?.status,
  (status) => {
    if (status === "completed") {
      toast.success(deleteJob.progress.value?.message || "Contacts permanently deleted");
      deleteDialogOpen.value = false;
      deleteJob.reset();
      refresh();
      if (tableRef.value) {
        tableRef.value.resetRowSelection();
      }
    }

    if (status === "failed") {
      toast.error("Failed to delete contacts", {
        description: deleteJob.progress.value?.error_message || "An error occurred",
      });
      deleteJob.reset();
    }
  },
);

const handleDeleteRows = async (selectedRows) => {
  const ids = selectedRows.map((row) => row.original.id);
  try {
    await deleteJob.startJob("/api/contacts-trash/bulk", {
      method: "DELETE",
      body: { ids },
    });
  } catch (err) {
    toast.error("Failed to delete contacts", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
    deleteJob.reset();
  }
};

// Delete all trashed contacts (queued with progress)
const deleteAllDialogOpen = ref(false);
const deleteAllJob = useJobProgress();

// Prevent closing delete all dialog while processing
watch(deleteAllDialogOpen, (open) => {
  if (!open && deleteAllJob.processing.value) {
    deleteAllDialogOpen.value = true;
  }
});

// Watch delete all completion
watch(
  () => deleteAllJob.progress.value?.status,
  (status) => {
    if (status === "completed") {
      toast.success(deleteAllJob.progress.value?.message || "All trashed contacts permanently deleted");
      deleteAllDialogOpen.value = false;
      deleteAllJob.reset();
      refresh();
    }

    if (status === "failed") {
      toast.error("Failed to delete contacts", {
        description: deleteAllJob.progress.value?.error_message || "An error occurred",
      });
      deleteAllJob.reset();
    }
  },
);

const handleDeleteAll = async () => {
  try {
    await deleteAllJob.startJob("/api/contacts-trash/empty", {
      method: "DELETE",
    });
  } catch (err) {
    toast.error("Failed to delete contacts", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
    deleteAllJob.reset();
  }
};

// Contact type labels
const contactTypeLabels = {
  exhibitor: "Exhibitor",
  "media-partner": "Media Partner",
  sponsor: "Sponsor",
  speaker: "Speaker",
  vendor: "Vendor",
  visitor: "Visitor",
  other: "Other",
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
    header: "Name",
    accessorKey: "name",
    cell: ({ row }) => h(ContactTableItem, { contact: row.original, linked: false }),
    size: 220,
    enableHiding: false,
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
    header: "Type",
    accessorKey: "contact_types",
    cell: ({ row }) => {
      const types = row.original.contact_types || [];
      if (!types.length) return h("span", { class: "text-muted-foreground text-sm" }, "-");
      return h(
        "span",
        { class: "text-sm tracking-tight" },
        types.map((t) => contactTypeLabels[t] || t).join(", "),
      );
    },
    size: 140,
    enableSorting: false,
  },
  {
    header: "Status",
    accessorKey: "status",
    cell: ({ row }) => {
      const status = row.original.status;
      const colorMap = {
        green: "text-success-foreground",
        yellow: "text-warning-foreground",
        gray: "text-muted-foreground",
      };
      return h(
        "span",
        {
          class: `inline-flex items-center text-sm tracking-tight ${colorMap[status?.color] || "text-muted-foreground"}`,
        },
        status?.label || status?.value || "-",
      );
    },
    size: 100,
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
      h(ContactTrashRowActions, {
        contactId: row.original.id,
        onRefresh: () => refresh(),
      }),
    size: 60,
    enableHiding: false,
  },
];
</script>
