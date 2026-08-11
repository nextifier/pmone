<template>
  <div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <!-- No count pill: the page header above already carries the form's
           total, and the table's own footer reports the filtered count with a
           label that says which it is. -->
      <h2 class="text-base font-semibold tracking-tighter">Responses</h2>

      <div class="ml-auto flex shrink-0 items-center gap-1 sm:gap-2">
        <!-- Export (all or selected) -->
        <Popover>
          <PopoverTrigger asChild>
            <Button variant="outline" size="sm" :disabled="exportPending || (!data.length && !selectedRowIds.length)">
              <Spinner v-if="exportPending" class="size-4 shrink-0" />
              <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
              <span>Export</span>
              <span
                v-if="selectedRowIds.length"
                class="text-muted-foreground/80 -me-1 inline-flex h-5 max-h-full items-center rounded border px-1 font-[inherit] text-xs font-medium tabular-nums"
              >
                {{ selectedRowIds.length }}
              </span>
            </Button>
          </PopoverTrigger>
          <PopoverContent align="end" class="w-44 p-1">
            <div class="flex flex-col">
              <PopoverClose v-for="format in exportFormats" :key="format.value" asChild>
                <button
                  @click="handleExport(format.value, selectedRowIds.length ? selectedRowIds : null)"
                  class="hover:bg-muted flex items-center gap-x-2 rounded-md px-3 py-2 text-left text-sm tracking-tight"
                >
                  <Icon :name="format.icon" class="size-4 shrink-0" />
                  <span>{{ format.label }}</span>
                </button>
              </PopoverClose>
            </div>
          </PopoverContent>
        </Popover>

        <template v-if="hasSelectedRows">
          <!-- Bulk status update -->
          <Popover>
            <PopoverTrigger asChild>
              <Button variant="outline" size="sm">
                <Icon name="hugeicons:tag-01" class="size-4 shrink-0" />
                <span>Status</span>
                <span
                  class="text-muted-foreground/80 -me-1 inline-flex h-5 max-h-full items-center rounded border px-1 font-[inherit] text-xs font-medium tabular-nums"
                >
                  {{ selectedRowIds.length }}
                </span>
              </Button>
            </PopoverTrigger>
            <PopoverContent align="end" class="w-40 p-1">
              <div class="flex flex-col">
                <PopoverClose v-for="s in RESPONSE_STATUS_OPTIONS" :key="s.value" asChild>
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
          <ConfirmDialog
            v-model:open="deleteDialogOpen"
            title="Delete selected responses?"
            :description="bulkDeleteDescription"
            confirm-label="Delete"
            variant="destructive"
            :pending="deletePending"
            @confirm="handleBulkDelete"
          >
            <template #trigger="{ open }">
              <button
                class="hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
                @click="open()"
              >
                <Icon name="hugeicons:delete-02" class="size-4 shrink-0" />
                <span>Delete</span>
                <span
                  class="text-muted-foreground/80 -me-1 inline-flex h-5 max-h-full items-center rounded border px-1 font-[inherit] text-xs font-medium tabular-nums"
                >
                  {{ selectedRowIds.length }}
                </span>
              </button>
            </template>
          </ConfirmDialog>

          <Button variant="outline" size="sm" @click="clearSelection">
            <Icon name="hugeicons:cancel-01" class="size-4 shrink-0" />
            <span>Clear</span>
          </Button>
        </template>
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
      search-column="respondent_email"
      search-placeholder="Search responses"
      :column-toggle="true"
      :initial-column-visibility="initialColumnVisibility"
      :show-add-button="false"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
      @update:pagination="onPaginationUpdate"
      @update:sorting="onSortingUpdate"
      @update:column-filters="onColumnFiltersUpdate"
      @refresh="refresh"
    >
      <template #filters>
        <Popover>
          <TableFilterButton :count="selectedStatusFilters.length" />
          <PopoverContent class="w-auto min-w-48 p-3 pb-4.5" align="end">
            <div class="space-y-4">
              <TableFilterSection
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

    <ResponseDetailDialog
      v-model:open="detailOpen"
      :response="detailResponse"
      :form="form"
      @update-status="handleDetailStatusChange"
    />
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import TableFilterSection from "@/components/TableFilterSection.vue";
import ResponseDetailDialog from "@/components/form-builder/ResponseDetailDialog.vue";
import ResponseRowActions from "@/components/form-builder/ResponseRowActions.vue";
import { TableData, TableFilterButton } from "@/components/ui/table-data";
import { Checkbox } from "@/components/ui/checkbox";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { RESPONSE_STATUS_OPTIONS, responseStatusDisplay } from "@/lib/formBuilderStatus";
import { formatResponseValue as formatFieldValue } from "@/lib/formFieldTypes";
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

const statusFilterOptions = RESPONSE_STATUS_OPTIONS.map((s) => ({
  label: s.label,
  value: s.value,
}));

// Table state
/**
 * Seeded from `?status=` so the Analytics status pills can deep-link straight
 * into a filtered list instead of only counting.
 */
const columnFilters = ref(
  route.query.status
    ? [{ id: "status", value: String(route.query.status).split(",").filter(Boolean) }]
    : []
);
const pagination = ref({ pageIndex: 0, pageSize: 15 });
const sorting = ref([{ id: "submitted_at", desc: true }]);

/** Columns the API can order by. Answer columns live in a JSON blob and cannot. */
const SERVER_SORTABLE = ["submitted_at", "status", "respondent_email"];

/**
 * Show the first few answers and leave the rest to the column picker. A 30-field
 * form would otherwise open as a 5000px table that has to be scrolled sideways
 * before a single row can be identified.
 */
const VISIBLE_ANSWER_COLUMNS = 4;

const initialColumnVisibility = computed(() => {
  const answers = (props.form?.fields || [])
    .filter((field) => field.type !== "section")
    .sort((a, b) => (a.order_column || 0) - (b.order_column || 0));

  return Object.fromEntries(
    answers.slice(VISIBLE_ANSWER_COLUMNS).map((field) => [`field_${field.ulid}`, false])
  );
});

// Build query params
const buildQueryParams = () => {
  const params = new URLSearchParams();
  params.append("page", pagination.value.pageIndex + 1);
  params.append("per_page", pagination.value.pageSize);

  const statusFilter = columnFilters.value.find((f) => f.id === "status");
  if (statusFilter?.value?.length) {
    params.append("filter_status", statusFilter.value.join(","));
  }

  const searchFilter = columnFilters.value.find((f) => f.id === "respondent_email");
  if (searchFilter?.value) {
    params.append("filter_search", searchFilter.value);
  }

  // FormResponseController accepts sort_by / sort_order for these three; the
  // page used to disable sorting outright and never send them, so the headers
  // looked unsortable when the API had supported it all along.
  const sort = sorting.value?.[0];
  if (sort && SERVER_SORTABLE.includes(sort.id)) {
    params.append("sort_by", sort.id);
    params.append("sort_order", sort.desc ? "desc" : "asc");
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

// Refetch on filter/pagination changes (sorting is not sent to the API)
watch([columnFilters, pagination], () => fetchResponses(), { deep: true });

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
  pagination.value.pageIndex = 0;
};

const refresh = fetchResponses;

// Response detail dialog
const detailOpen = ref(false);
const detailResponse = ref(null);

const openDetail = (response) => {
  detailResponse.value = response;
  detailOpen.value = true;

  if (response.status === "new") {
    markAsRead(response);
  }
};

const markAsRead = async (response) => {
  try {
    await client(`/api/forms/${slug.value}/responses/bulk-status`, {
      method: "PUT",
      body: { ids: [response.id], status: "read" },
    });
    response.status = "read";
  } catch {
    // Non-blocking; the row keeps its current status on failure.
  }
};

const handleDetailStatusChange = async (response, status) => {
  await handleUpdateStatus(response, status);
  response.status = status;
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

  // Submitted at
  cols.push({
    header: "Submitted",
    accessorKey: "submitted_at",
    size: 130,
    cell: ({ getValue }) => {
      const date = getValue();
      if (!date) return h("span", { class: "text-muted-foreground text-sm" }, "-");
      return h(
        resolveComponent("ClientOnly"),
        {},
        {
          default: () =>
            withDirectives(
              h(
                "div",
                { class: "text-sm text-muted-foreground tracking-tight tabular-nums" },
                $dayjs(date).fromNow()
              ),
              [[resolveDirective("tippy"), $dayjs(date).format("MMMM D, YYYY [at] h:mm A")]]
            ),
          fallback: () =>
            h(
              "div",
              { class: "text-sm text-muted-foreground tracking-tight tabular-nums" },
              $dayjs(date).format("MMM D, YYYY")
            ),
        }
      );
    },
  });

  // Email
  cols.push({
    header: "Email",
    accessorKey: "respondent_email",
    cell: ({ getValue }) =>
      h("span", { class: "text-sm tracking-tight" }, getValue() || "-"),
    size: 180,
  });

  // Status
  cols.push({
    header: "Status",
    accessorKey: "status",
    cell: ({ row }) => {
      const s = responseStatusDisplay(row.getValue("status"));
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

  /**
   * Answers come after the identifying columns. Leading with them meant seven
   * headers of truncated question text ("How would you rate t…") and nothing to
   * recognise a row by; the column picker is on so a form with 30 questions
   * does not force a 5000px-wide table either.
   */
  const fields = props.form?.fields || [];
  const sortedFields = [...fields].sort((a, b) => (a.order_column || 0) - (b.order_column || 0));

  for (const field of sortedFields) {
    if (field.type === "section") continue;
    cols.push({
      id: `field_${field.ulid}`,
      header: () =>
        withDirectives(
          h("span", { class: "block truncate" }, field.label),
          [[resolveDirective("tippy"), field.label]]
        ),
      accessorFn: (row) => row.response_data?.[field.ulid],
      cell: ({ row, getValue }) =>
        h(
          "button",
          {
            type: "button",
            class:
              "text-sm tracking-tight max-w-[200px] truncate block text-left cursor-pointer",
            onClick: () => openDetail(row.original),
          },
          formatFieldValue(field, getValue())
        ),
      size: 160,
      enableSorting: false,
    });
  }

  // Actions
  cols.push({
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) =>
      h(ResponseRowActions, {
        response: row.original,
        onView: openDetail,
        onSetStatus: handleUpdateStatus,
        onDelete: handleDeleteSingle,
      }),
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

const exportFormats = [
  { value: "xlsx", label: "Export as XLSX", icon: "hugeicons:xls-01" },
  { value: "csv", label: "Export as CSV", icon: "hugeicons:csv-01" },
];

const exportMimeTypes = {
  xlsx: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
  csv: "text/csv",
};

const handleExport = async (format = "xlsx", ids = null) => {
  try {
    exportPending.value = true;

    const params = new URLSearchParams({ format });
    if (ids?.length) {
      params.append("ids", ids.join(","));
    }

    const response = await client(
      `/api/forms/${slug.value}/responses/export?${params.toString()}`,
      { responseType: "blob" }
    );

    const blob = new Blob([response], { type: exportMimeTypes[format] });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `${props.form.slug}-responses-${new Date().toISOString().slice(0, 10)}.${format}`;
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
    const count = selectedRowIds.value.length;
    await client(`/api/forms/${slug.value}/responses/bulk-status`, {
      method: "PUT",
      body: { ids: selectedRowIds.value, status },
    });
    toast.success(`${count} ${count === 1 ? "response" : "responses"} marked as ${status}`);
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

const bulkDeleteDescription = computed(() => {
  const count = selectedRowIds.value.length;
  return `This action can't be undone. ${count} selected ${count === 1 ? "response" : "responses"} will be permanently deleted.`;
});

const handleBulkDelete = async () => {
  try {
    deletePending.value = true;
    const count = selectedRowIds.value.length;
    await client(`/api/forms/${slug.value}/responses/bulk`, {
      method: "DELETE",
      body: { ids: selectedRowIds.value },
    });
    toast.success(`${count} ${count === 1 ? "response" : "responses"} deleted`);
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
</script>
