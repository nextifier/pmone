<template>
  <div class="flex flex-col gap-y-6">
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="space-y-1">
        <h3 class="page-title">Brands</h3>
        <p class="page-description">Manage exhibitor brands for this event.</p>
      </div>

      <div v-if="!hasSelectedRows" class="ml-auto flex flex-wrap gap-1 sm:gap-2">
        <!-- Import -->
        <BrandImportDialog
          v-if="event?.can_edit"
          :username="route.params.username"
          :event-slug="route.params.eventSlug"
          @imported="refresh()"
          @import-errors="handleImportErrors"
        >
          <template #trigger="{ open }">
            <button
              @click="open()"
              class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
            >
              <Icon name="hugeicons:file-import" class="size-4 shrink-0" />
              <span>Import</span>
            </button>
          </template>
        </BrandImportDialog>

        <!-- Export -->
        <button
          @click="handleExport"
          :disabled="exportPending"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Spinner v-if="exportPending" class="size-4 shrink-0" />
          <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
          <span>Export {{ totalActiveFilters > 0 ? "selected" : "all" }}</span>
        </button>

        <Button v-if="event?.can_edit" @click="showAddDialog = true" size="sm">
          <Icon name="hugeicons:add-01" class="size-4" />
          New Brand
          <KbdGroup>
            <Kbd>N</Kbd>
          </KbdGroup>
        </Button>
      </div>

      <div v-else-if="event?.can_edit" class="ml-auto flex flex-wrap gap-1 sm:gap-2">
        <button
          @click="clearSelection"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="lucide:x" class="size-4 shrink-0" />
          <span>Clear selection</span>
        </button>
      </div>
    </div>

    <!-- Import errors -->
    <div v-if="importResult" class="bg-card rounded-lg border p-4">
      <div class="flex items-start justify-between gap-3">
        <div class="flex items-start gap-3">
          <Icon
            name="lucide:triangle-alert"
            class="text-destructive-foreground mt-0.5 size-5 shrink-0"
          />
          <div class="space-y-1">
            <p class="text-base font-medium tracking-tight">Import completed with errors</p>
            <p class="text-muted-foreground text-sm tracking-tight">
              {{ importResult.importedCount }} brand(s) imported successfully,
              {{ importResult.grouped.length }} row(s) failed validation.<template
                v-if="importResult.skippedCount > 0"
              >
                {{ importResult.skippedCount }} row(s) skipped (already exist).</template
              >
            </p>
          </div>
        </div>
        <div class="flex shrink-0 items-center gap-1">
          <ButtonCopy :text="importErrorsText" />
          <button
            @click="importResult = null"
            class="text-muted-foreground hover:text-foreground flex size-7 items-center justify-center rounded-lg"
          >
            <Icon name="lucide:x" class="size-4" />
          </button>
        </div>
      </div>

      <div class="divide-border mt-3 divide-y">
        <div
          v-for="group in importResult.grouped"
          :key="group.row"
          class="px-3 py-2.5 text-sm tracking-tight"
        >
          <p class="font-medium">
            Row {{ group.row }}<template v-if="group.name"> - {{ group.name }}</template>
          </p>
          <ul class="text-destructive-foreground mt-1 list-inside list-disc space-y-0.5">
            <li v-for="msg in group.messages" :key="msg">{{ msg }}</li>
          </ul>
        </div>
      </div>
    </div>

    <TableData
      ref="tableRef"
      :client-only="true"
      :data="data"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="brands"
      label="Brand"
      search-column="brand_name"
      search-placeholder="Search brands..."
      error-title="Error loading brands"
      :initial-pagination="{ pageIndex: 0, pageSize: 50 }"
      :initial-sorting="[{ id: 'created_at', desc: true }]"
      :initial-column-visibility="{
        order_column: false,
      }"
      :show-add-button="false"
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
                  { label: 'Draft', value: 'draft' },
                  { label: 'Cancelled', value: 'cancelled' },
                ]"
                :selected="selectedStatuses"
                @change="handleFilterChange('status', $event)"
              />
            </div>
          </PopoverContent>
        </Popover>
      </template>

      <template #actions="{ selectedRows }">
        <template v-if="selectedRows.length > 0 && event?.can_edit">
          <!-- Bulk Status Update -->
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="outline" size="sm" :disabled="bulkStatusUpdating">
                <Spinner v-if="bulkStatusUpdating" class="size-4 shrink-0" />
                <Icon v-else name="hugeicons:task-edit-01" class="size-4 shrink-0" />
                Status
                <Icon name="lucide:chevron-down" class="size-3 opacity-60" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-40">
              <DropdownMenuItem
                v-for="s in brandEventStatuses"
                :key="s.value"
                :disabled="bulkStatusUpdating"
                class="gap-x-2"
                @click="handleBulkStatusUpdate(selectedRows, s.value)"
              >
                <span :class="s.dot" class="size-2 rounded-full" />
                {{ s.label }}
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <!-- Remove from event -->
          <DialogResponsive v-model:open="removeDialogOpen">
            <template #trigger="{ open }">
              <Button variant="outline" size="sm" @click="open()">
                <Icon name="hugeicons:unlink-02" class="size-4 shrink-0" />
                Remove from event
                <span
                  class="text-muted-foreground/80 -me-1 inline-flex h-5 max-h-full items-center rounded border px-1 font-[inherit] text-[0.625rem] font-medium"
                >
                  {{ selectedRows.length }}
                </span>
              </Button>
            </template>
            <template #default>
              <div class="px-4 pb-10 md:px-6 md:py-5">
                <div class="page-title">Remove brands from this event?</div>
                <p class="page-description mt-1.5">
                  This will remove {{ selectedRows.length }} selected
                  {{ selectedRows.length === 1 ? "brand" : "brands" }} from this event. The brands
                  will still exist globally and can be added back later.
                </p>
                <div class="mt-3 flex justify-end gap-2">
                  <button
                    class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                    @click="removeDialogOpen = false"
                    :disabled="removePending"
                  >
                    Cancel
                  </button>
                  <button
                    @click="handleRemoveRows(selectedRows)"
                    :disabled="removePending"
                    class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    <Spinner v-if="removePending" class="size-4 text-white" />
                    <span v-else>Remove</span>
                  </button>
                </div>
              </div>
            </template>
          </DialogResponsive>

          <!-- Delete permanently -->
          <DialogResponsive v-if="canDeletePermanently" v-model:open="permanentDeleteDialogOpen">
            <template #trigger="{ open }">
              <Button variant="outline" size="sm" @click="open()">
                <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
                Delete permanently
                <span
                  class="text-muted-foreground/80 -me-1 inline-flex h-5 max-h-full items-center rounded border px-1 font-[inherit] text-[0.625rem] font-medium"
                >
                  {{ selectedRows.length }}
                </span>
              </Button>
            </template>
            <template #default>
              <div class="px-4 pb-10 md:px-6 md:py-5">
                <div class="page-title">Delete brands permanently?</div>
                <p class="page-description mt-1.5">
                  This action can't be undone. This will permanently delete
                  {{ selectedRows.length }} selected
                  {{ selectedRows.length === 1 ? "brand" : "brands" }} from the system, including
                  all their data across all events.
                </p>

                <!-- Progress bar -->
                <div v-if="deleteJob.processing.value" class="mt-3 space-y-2">
                  <div class="flex items-center justify-between text-sm tracking-tight">
                    <span class="text-muted-foreground">{{
                      deleteJob.progress.value?.message
                    }}</span>
                    <span class="font-medium tabular-nums"
                      >{{ deleteJob.progress.value?.percentage ?? 0 }}%</span
                    >
                  </div>
                  <Progress
                    :model-value="deleteJob.progress.value?.percentage ?? 0"
                    indicator-class="bg-destructive"
                  />
                  <p
                    v-if="deleteJob.progress.value?.total > 0"
                    class="text-muted-foreground text-xs tracking-tight tabular-nums sm:text-sm"
                  >
                    {{ deleteJob.progress.value?.processed ?? 0 }} /
                    {{ deleteJob.progress.value?.total ?? 0 }}
                  </p>
                </div>

                <div v-else class="mt-3 flex justify-end gap-2">
                  <button
                    class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                    @click="permanentDeleteDialogOpen = false"
                  >
                    Cancel
                  </button>
                  <button
                    @click="handlePermanentDeleteRows(selectedRows)"
                    class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98"
                  >
                    Delete permanently
                  </button>
                </div>
              </div>
            </template>
          </DialogResponsive>
        </template>
      </template>
    </TableData>

    <!-- Add Brand Dialog -->
    <BrandFormAddBrandToEvent
      v-model:open="showAddDialog"
      :username="route.params.username"
      :event-slug="route.params.eventSlug"
      :members="project?.members || []"
      @success="refresh()"
    />
  </div>
</template>

<script setup>
import BrandEventStatusDropdown from "@/components/brand/EventStatusDropdown.vue";
import BrandImportDialog from "@/components/brand/EventBrandImportDialog.vue";
import BrandTableItem from "@/components/brand/TableItem.vue";
import TableData from "@/components/TableData.vue";
import { Checkbox } from "@/components/ui/checkbox";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Progress } from "@/components/ui/progress";
import { PopoverClose } from "reka-ui";
import { defineComponent, resolveComponent } from "vue";
import { toast } from "vue-sonner";

const props = defineProps({ event: Object, project: Object });

const route = useRoute();
const { $dayjs } = useNuxtApp();

const client = useSanctumClient();
const { hasAnyRole } = usePermission();
const canDeletePermanently = computed(() => hasAnyRole(["master", "admin"]));
const showAddDialog = ref(false);
const importResult = ref(null);

const importErrorsText = computed(() => {
  if (!importResult.value) return "";
  const r = importResult.value;
  let summary = `Import completed with errors\n\n${r.importedCount} brand(s) imported successfully, ${r.grouped.length} row(s) failed validation.`;
  if (r.skippedCount > 0) summary += ` ${r.skippedCount} row(s) skipped (already exist).`;
  const details = r.grouped
    .map((group) => {
      const label = `Row ${group.row}${group.name ? ` - ${group.name}` : ""}`;
      return `${label}\n${group.messages.map((m) => `  - ${m}`).join("\n")}`;
    })
    .join("\n\n");
  return `${summary}\n\n${details}`;
});

const handleImportErrors = ({ errors, importedCount, skippedCount = 0 }) => {
  const map = new Map();
  for (const error of errors) {
    if (!map.has(error.row)) {
      map.set(error.row, {
        row: error.row,
        name: error.values?.brand_name || error.values?.name || "",
        messages: [],
      });
    }
    map.get(error.row).messages.push(...error.errors);
  }
  importResult.value = { importedCount, skippedCount, grouped: Array.from(map.values()) };
};

defineShortcuts({
  n: {
    handler: () => {
      showAddDialog.value = true;
    },
  },
});

const baseUrl = computed(
  () => `/projects/${route.params.username}/events/${route.params.eventSlug}/brands`
);
const apiUrl = computed(
  () =>
    `/api/projects/${route.params.username}/events/${route.params.eventSlug}/brands?client_only=true`
);

// Data state
const brandsResponse = ref(null);
const pending = ref(false);
const error = ref(null);

const data = computed(() => brandsResponse.value?.data || []);
const meta = computed(
  () => brandsResponse.value?.meta || { current_page: 1, last_page: 1, per_page: 15, total: 0 }
);

async function refresh() {
  pending.value = true;
  error.value = null;
  try {
    brandsResponse.value = await client(apiUrl.value);
  } catch (e) {
    error.value = e;
  }
  pending.value = false;
}

onMounted(() => refresh());

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
  if (tableRef.value?.table) {
    return tableRef.value.table.getColumn(columnId)?.getFilterValue() ?? [];
  }
  return [];
};

const selectedStatuses = computed(() => getFilterValue("status"));
const totalActiveFilters = computed(() => selectedStatuses.value.length);

const handleFilterChange = (columnId, { checked, value }) => {
  if (tableRef.value?.table) {
    const column = tableRef.value.table.getColumn(columnId);
    if (!column) return;

    const current = column.getFilterValue() ?? [];
    const updated = checked ? [...current, value] : current.filter((item) => item !== value);

    column.setFilterValue(updated.length > 0 ? updated : undefined);
    tableRef.value.table.setPageIndex(0);
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
    cell: ({ row }) =>
      h(BrandTableItem, {
        brand: row.original,
        baseUrl: baseUrl.value,
      }),
    size: 300,
    enableHiding: false,
    filterFn: (row, columnId, filterValue) => {
      const searchValue = filterValue.toLowerCase();
      const name = row.original.brand_name?.toLowerCase() || "";
      const company = row.original.company_name?.toLowerCase() || "";
      return name.includes(searchValue) || company.includes(searchValue);
    },
  },
  {
    header: "Status",
    accessorKey: "status",
    cell: ({ row }) =>
      h(BrandEventStatusDropdown, {
        status: row.getValue("status"),
        disabled: statusUpdating.value === row.original.id,
        onUpdate: (newStatus) => handleStatusUpdate(row.original.id, newStatus),
      }),
    size: 120,
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      return filterValue.includes(row.getValue(columnId));
    },
  },
  {
    header: "Booth",
    accessorKey: "booth_number",
    cell: ({ row }) => {
      const booth = row.getValue("booth_number");
      if (!booth) return h("span", { class: "text-sm" }, "-");
      return h("div", { class: "text-sm tracking-tight" }, booth);
    },
    size: 100,
  },
  {
    header: "Categories",
    accessorKey: "business_categories",
    cell: ({ row }) => {
      const cats = row.getValue("business_categories") || [];
      if (!cats.length) return h("span", { class: "text-sm" }, "-");
      return h(
        "div",
        { class: "line-clamp-1 text-sm tracking-tight" },
        cats.join(", ")
      );
    },
    size: 150,
  },
  {
    header: "Promo Posts",
    accessorKey: "promotion_posts_count",
    cell: ({ row }) => {
      const count = row.getValue("promotion_posts_count") || 0;
      return h("div", { class: "text-sm tracking-tight" }, count.toLocaleString());
    },
    size: 100,
  },
  {
    accessorKey: "order_column",
    header: () => null,
    enableHiding: false,
  },
  {
    header: "Added",
    accessorKey: "created_at",
    cell: ({ row }) => {
      const date = row.getValue("created_at");
      if (!date) return h("span", { class: "text-sm" }, "-");
      return h(
        "div",
        { class: "text-sm tracking-tight" },
        $dayjs(date).fromNow()
      );
    },
    size: 100,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) => h(RowActions, { brand: row.original, canEdit: props.event?.can_edit }),
    size: 80,
    enableHiding: false,
  },
];

// Export
const exportPending = ref(false);
const columnFilters = ref([]);

// Watch table column filters to keep local ref in sync
watch(
  () => tableRef.value?.table?.getState()?.columnFilters,
  (val) => {
    if (val) columnFilters.value = val;
  },
  { deep: true }
);

const handleExport = async () => {
  try {
    exportPending.value = true;

    // Build query params
    const params = new URLSearchParams();

    // Add search filter
    const searchFilter = columnFilters.value.find((f) => f.id === "brand_name");
    if (searchFilter?.value) {
      params.append("filter_search", searchFilter.value);
    }

    // Add status filter
    const statusFilter = columnFilters.value.find((f) => f.id === "status");
    if (statusFilter?.value?.length) {
      params.append("filter_status", statusFilter.value.join(","));
    }

    // Add sorting
    const sorting = tableRef.value?.table?.getState()?.sorting;
    const sortField = sorting?.[0]?.id || "order_column";
    const sortDirection = sorting?.[0]?.desc ? "desc" : "asc";
    params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);

    // Fetch the file as blob
    const response = await client(
      `/api/projects/${route.params.username}/events/${route.params.eventSlug}/brands/export?${params.toString()}`,
      { responseType: "blob" }
    );

    // Create a download link and trigger download
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

    toast.success("Brands exported successfully");
  } catch (err) {
    console.error("Failed to export brands:", err);
    toast.error("Failed to export brands", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    exportPending.value = false;
  }
};

// Remove from event
const removeDialogOpen = ref(false);
const removePending = ref(false);

const handleRemoveRows = async (selectedRows) => {
  const slugs = selectedRows.map((row) => row.original.brand_slug);
  try {
    removePending.value = true;
    await client(
      `/api/projects/${route.params.username}/events/${route.params.eventSlug}/brands/bulk`,
      { method: "DELETE", body: { slugs } }
    );
    await refresh();
    removeDialogOpen.value = false;
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }
    toast.success(`${slugs.length} brand(s) removed from event`);
  } catch (err) {
    toast.error("Failed to remove brands", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    removePending.value = false;
  }
};

// Permanent delete (queued with progress)
const permanentDeleteDialogOpen = ref(false);
const deleteJob = useJobProgress();

// Prevent closing delete dialog while processing
watch(permanentDeleteDialogOpen, (open) => {
  if (!open && deleteJob.processing.value) {
    permanentDeleteDialogOpen.value = true;
  }
});

// Watch delete completion
watch(
  () => deleteJob.progress.value?.status,
  (status) => {
    if (status === "completed") {
      toast.success(deleteJob.progress.value?.message || "Brands permanently deleted");
      permanentDeleteDialogOpen.value = false;
      deleteJob.reset();
      refresh();
      if (tableRef.value) {
        tableRef.value.resetRowSelection();
      }
    }

    if (status === "failed") {
      toast.error("Failed to delete brands", {
        description: deleteJob.progress.value?.error_message || "An error occurred",
      });
      deleteJob.reset();
    }
  }
);

const handlePermanentDeleteRows = async (selectedRows) => {
  const slugs = selectedRows.map((row) => row.original.brand_slug);
  try {
    await deleteJob.startJob(
      `/api/projects/${route.params.username}/events/${route.params.eventSlug}/brands/bulk-permanent`,
      {
        method: "DELETE",
        body: { slugs },
      }
    );
  } catch (err) {
    toast.error("Failed to delete brands", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
    deleteJob.reset();
  }
};

// Status update handlers
const statusUpdating = ref(null);
const bulkStatusUpdating = ref(false);

const handleStatusUpdate = async (brandId, newStatus) => {
  const brand = data.value.find((b) => b.id === brandId);
  if (!brand) return;

  statusUpdating.value = brandId;
  try {
    await client(
      `/api/projects/${route.params.username}/events/${route.params.eventSlug}/brands/${brand.brand_slug}`,
      {
        method: "PUT",
        body: { status: newStatus },
      }
    );
    toast.success("Status updated");
    await refresh();
  } catch (err) {
    toast.error("Failed to update status", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    statusUpdating.value = null;
  }
};

const brandEventStatuses = [
  { value: "active", label: "Active", dot: "bg-success" },
  { value: "draft", label: "Draft", dot: "bg-warning" },
  { value: "cancelled", label: "Cancelled", dot: "bg-destructive" },
];

const handleBulkStatusUpdate = async (selectedRows, newStatus) => {
  bulkStatusUpdating.value = true;
  try {
    await Promise.all(
      selectedRows.map((row) =>
        client(
          `/api/projects/${route.params.username}/events/${route.params.eventSlug}/brands/${row.original.brand_slug}`,
          {
            method: "PUT",
            body: { status: newStatus },
          }
        )
      )
    );
    toast.success(`${selectedRows.length} brand(s) status updated`);
    await refresh();
  } catch (err) {
    toast.error("Failed to update status", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    bulkStatusUpdating.value = false;
  }
};

const handleDeleteSingleRow = async (brandSlug) => {
  try {
    await client(
      `/api/projects/${route.params.username}/events/${route.params.eventSlug}/brands/${brandSlug}`,
      { method: "DELETE" }
    );
    await refresh();
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }
    toast.success("Brand removed from event");
  } catch (err) {
    toast.error("Failed to remove brand", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  }
};

// Row Actions Component
const RowActions = defineComponent({
  props: {
    brand: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
  },
  setup(props) {
    const dialogOpen = ref(false);
    const singleDeletePending = ref(false);

    const instagramUrl = computed(() => {
      const links = props.brand.links || [];
      const igLink = links.find((l) => l.url?.includes("instagram.com"));
      return igLink?.url || null;
    });

    return () =>
      h("div", { class: "flex items-center justify-end gap-x-1" }, [
        // Instagram button
        instagramUrl.value
          ? h(
              "a",
              {
                href: instagramUrl.value,
                target: "_blank",
                rel: "noopener noreferrer",
                class:
                  "hover:bg-muted inline-flex size-8 items-center justify-center rounded-md transition",
              },
              [h(resolveComponent("Icon"), { name: "hugeicons:instagram", class: "size-4" })]
            )
          : null,
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
                          "hover:bg-muted data-[state=open]:bg-muted inline-flex size-8 items-center justify-center rounded-md transition",
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
                      // View
                      h(
                        PopoverClose,
                        { asChild: true },
                        {
                          default: () =>
                            h(
                              resolveComponent("NuxtLink"),
                              {
                                to: `${baseUrl.value}/${props.brand.brand_slug}`,
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
                              }
                            ),
                        }
                      ),
                      // Delete
                      ...(props.canEdit
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
                                      h("span", {}, "Remove"),
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
                h("div", { class: "page-title" }, "Are you sure?"),
                h(
                  "p",
                  { class: "page-description mt-1.5" },
                  "This action can't be undone. This will remove this brand from the event."
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
                          await handleDeleteSingleRow(props.brand.brand_slug);
                          dialogOpen.value = false;
                        } finally {
                          singleDeletePending.value = false;
                        }
                      },
                    },
                    singleDeletePending.value
                      ? h(resolveComponent("Spinner"), { class: "size-4 text-white" })
                      : "Remove"
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
