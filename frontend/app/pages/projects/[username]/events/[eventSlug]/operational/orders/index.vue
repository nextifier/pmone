<template>
  <div class="flex flex-col gap-y-6">
    <!-- Page Header -->
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex items-center gap-x-2.5">
        <h3 class="text-lg font-semibold tracking-tight">Orders</h3>
        <span
          v-if="meta.total"
          class="bg-muted text-muted-foreground inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
        >
          {{ meta.total }}
        </span>
      </div>

      <div v-if="!hasSelectedRows" class="ml-auto flex shrink-0 items-center gap-1 sm:gap-2">
        <button
          @click="handleExport"
          :disabled="exportPending"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Spinner v-if="exportPending" class="size-4 shrink-0" />
          <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
          <span>Export {{ totalActiveFilters > 0 ? "filtered" : "all" }}</span>
        </button>
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
      :clientOnly="false"
      ref="tableRef"
      :data="data"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="orders"
      label="Order"
      search-column="order_number"
      search-placeholder="Search by order # or brand..."
      error-title="Error loading orders"
      :show-add-button="false"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
      @update:pagination="pagination = $event"
      @update:sorting="sorting = $event"
      @update:column-filters="columnFilters = $event"
      @refresh="refresh"
    >
      <template #filters="{ table }">
        <!-- Filter Popover -->
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
          <PopoverContent class="w-auto min-w-48 p-3" align="end">
            <div class="space-y-4">
              <FilterSection
                title="Status"
                :options="operationalStatusOptions"
                :selected="selectedOperationalStatuses"
                @change="handleFilterChange('operational_status', $event)"
              />
              <FilterSection
                title="Payment"
                :options="paymentStatusOptions"
                :selected="selectedPaymentStatuses"
                @change="handleFilterChange('payment_status', $event)"
              />
            </div>
          </PopoverContent>
        </Popover>
      </template>

      <template #actions="{ selectedRows }">
        <!-- Bulk Status Dropdown -->
        <DropdownMenu v-if="selectedRows.length > 0">
          <DropdownMenuTrigger asChild>
            <button
              :disabled="bulkUpdating"
              class="hover:bg-muted flex h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border px-2.5 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <Spinner v-if="bulkUpdating" class="size-4 shrink-0" />
              <Icon v-else name="hugeicons:task-edit-01" class="size-4 shrink-0" />
              <span class="text-sm tracking-tight">Status</span>
              <Icon name="lucide:chevron-down" class="size-3 opacity-60" />
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="start" class="w-40">
            <DropdownMenuItem
              v-for="s in operationalStatuses"
              :key="s.value"
              :disabled="bulkUpdating"
              class="gap-x-2"
              @click="handleBulkOperationalStatus(selectedRows, s.value)"
            >
              <span :class="s.dot" class="size-2 rounded-full" />
              {{ s.label }}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <!-- Bulk Payment Dropdown -->
        <DropdownMenu v-if="selectedRows.length > 0">
          <DropdownMenuTrigger asChild>
            <button
              :disabled="bulkUpdating"
              class="hover:bg-muted flex h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border px-2.5 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <Spinner v-if="bulkUpdating" class="size-4 shrink-0" />
              <Icon v-else name="hugeicons:invoice-03" class="size-4 shrink-0" />
              <span class="text-sm tracking-tight">Payment</span>
              <Icon name="lucide:chevron-down" class="size-3 opacity-60" />
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="start" class="w-40">
            <DropdownMenuItem
              v-for="s in paymentStatuses"
              :key="s.value"
              :disabled="bulkUpdating"
              class="gap-x-2"
              @click="handleBulkPaymentStatus(selectedRows, s.value)"
            >
              <span :class="s.dot" class="size-2 rounded-full" />
              {{ s.label }}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <!-- Bulk Delete -->
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
              <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
                This action can't be undone. This will permanently delete
                {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "order" : "orders" }}.
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

    <!-- Cancellation Reason Dialog -->
    <DialogResponsive v-model:open="cancelDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-primary text-lg font-semibold tracking-tight">Cancel Order</div>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            Provide a reason for cancelling this order.
          </p>
          <textarea
            v-model="cancellationReason"
            rows="3"
            placeholder="Cancellation reason"
            class="border-border bg-background placeholder:text-muted-foreground focus:ring-ring mt-3 w-full rounded-md border px-3 py-2 text-sm tracking-tight outline-none focus:ring-1"
          />
          <div class="mt-3 flex justify-end gap-2">
            <button
              class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              @click="cancelDialogOpen = false"
              :disabled="statusUpdating"
            >
              Cancel
            </button>
            <button
              @click="confirmCancellation"
              :disabled="statusUpdating || !cancellationReason.trim()"
              class="bg-destructive hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <Spinner v-if="statusUpdating" class="size-4 text-white" />
              <span v-else>Confirm Cancellation</span>
            </button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import DialogResponsive from "@/components/DialogResponsive.vue";
import FilterSection from "@/components/inbox/FilterSection.vue";
import OrderStatusDropdown from "@/components/order/StatusDropdown.vue";
import TableData from "@/components/TableData.vue";
import { Checkbox } from "@/components/ui/checkbox";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { resolveDirective, withDirectives } from "vue";
import { toast } from "vue-sonner";

const props = defineProps({ event: Object, project: Object });

usePageMeta(null, {
  title: computed(() => `Orders · ${props.event?.title || "Event"}`),
});

const { $dayjs } = useNuxtApp();
const route = useRoute();
const client = useSanctumClient();

// Filter options
const operationalStatusOptions = [
  { label: "Submitted", value: "submitted" },
  { label: "Confirmed", value: "confirmed" },
  { label: "Processing", value: "processing" },
  { label: "Completed", value: "completed" },
  { label: "Cancelled", value: "cancelled" },
];

const paymentStatusOptions = [
  { label: "Not Invoiced", value: "not_invoiced" },
  { label: "Unpaid", value: "invoiced" },
  { label: "Paid", value: "paid" },
];

// Status configs for dropdowns
const operationalStatuses = [
  { value: "submitted", label: "Submitted", dot: "bg-blue-500" },
  { value: "confirmed", label: "Confirmed", dot: "bg-green-500" },
  { value: "processing", label: "Processing", dot: "bg-yellow-500" },
  { value: "completed", label: "Completed", dot: "bg-emerald-500" },
  { value: "cancelled", label: "Cancelled", dot: "bg-red-500" },
];

const paymentStatuses = [
  { value: "not_invoiced", label: "Not Invoiced", dot: "bg-gray-400" },
  { value: "invoiced", label: "Unpaid", dot: "bg-orange-500" },
  { value: "paid", label: "Paid", dot: "bg-green-500" },
];

// Table state
const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 20 });
const sorting = ref([{ id: "submitted_at", desc: true }]);

// API base
const apiBase = computed(
  () => `/api/projects/${route.params.username}/events/${route.params.eventSlug}/orders`
);

// Build query params for server-side mode
const buildQueryParams = () => {
  const params = new URLSearchParams();

  params.append("page", pagination.value.pageIndex + 1);
  params.append("per_page", pagination.value.pageSize);

  const filters = {
    order_number: "filter[search]",
    operational_status: "filter[operational_status]",
    payment_status: "filter[payment_status]",
  };

  Object.entries(filters).forEach(([columnId, paramKey]) => {
    const filter = columnFilters.value.find((f) => f.id === columnId);
    if (filter?.value) {
      const value = Array.isArray(filter.value) ? filter.value.join(",") : filter.value;
      params.append(paramKey, value);
    }
  });

  const sortField = sorting.value[0]?.id || "submitted_at";
  const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
  params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);

  return params.toString();
};

// Fetch orders
const {
  data: ordersResponse,
  pending,
  error,
  refresh: fetchOrders,
} = await useLazySanctumFetch(() => `${apiBase.value}?${buildQueryParams()}`, {
  key: "event-orders-list",
  watch: false,
});

const data = computed(() => ordersResponse.value?.data || []);
const meta = computed(
  () => ordersResponse.value?.meta || { current_page: 1, last_page: 1, per_page: 20, total: 0 }
);

// Watch for changes and refetch
watch(
  [columnFilters, sorting, pagination],
  () => {
    fetchOrders();
  },
  { deep: true }
);

const refresh = fetchOrders;

// Check if there are any selected rows
const hasSelectedRows = computed(() => {
  return tableRef.value?.table?.getSelectedRowModel()?.rows?.length > 0;
});

// Clear selection
const clearSelection = () => {
  if (tableRef.value) {
    tableRef.value.resetRowSelection();
  }
};

// Status update handlers
const statusUpdating = ref(null);

// Cancellation dialog state
const cancelDialogOpen = ref(false);
const cancellationReason = ref("");
const cancellingUlid = ref(null);

async function handleOperationalStatusUpdate(ulid, newStatus) {
  if (newStatus === "cancelled") {
    cancellingUlid.value = ulid;
    cancellationReason.value = "";
    cancelDialogOpen.value = true;
    return;
  }
  await doOperationalStatusUpdate(ulid, newStatus);
}

async function confirmCancellation() {
  if (bulkCancelRows.value.length > 0) {
    await confirmBulkCancellation();
    return;
  }
  await doOperationalStatusUpdate(cancellingUlid.value, "cancelled", cancellationReason.value || null);
  cancelDialogOpen.value = false;
}

async function doOperationalStatusUpdate(ulid, newStatus, reason = null) {
  statusUpdating.value = `op-${ulid}`;
  try {
    const body = { operational_status: newStatus };
    if (reason) body.cancellation_reason = reason;

    await client(`${apiBase.value}/${ulid}/operational-status`, {
      method: "PATCH",
      body,
    });
    toast.success("Status updated");
    await refresh();
  } catch (err) {
    toast.error("Failed to update status", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    statusUpdating.value = null;
  }
}

async function handlePaymentStatusUpdate(ulid, newStatus) {
  statusUpdating.value = `pay-${ulid}`;
  try {
    await client(`${apiBase.value}/${ulid}/payment-status`, {
      method: "PATCH",
      body: { payment_status: newStatus },
    });
    toast.success("Payment status updated");
    await refresh();
  } catch (err) {
    toast.error("Failed to update payment status", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    statusUpdating.value = null;
  }
}

// Bulk status update handlers
const bulkUpdating = ref(false);
const bulkCancelRows = ref([]);

async function handleBulkOperationalStatus(selectedRows, newStatus) {
  if (newStatus === "cancelled") {
    bulkCancelRows.value = selectedRows;
    cancellationReason.value = "";
    cancelDialogOpen.value = true;
    return;
  }
  await doBulkOperationalStatus(selectedRows, newStatus);
}

async function doBulkOperationalStatus(selectedRows, newStatus, reason = null) {
  const ulids = selectedRows.map((row) => row.original.ulid);
  bulkUpdating.value = true;
  try {
    const body = { operational_status: newStatus };
    if (reason) body.cancellation_reason = reason;
    await Promise.all(
      ulids.map((ulid) =>
        client(`${apiBase.value}/${ulid}/operational-status`, { method: "PATCH", body })
      )
    );
    toast.success(`${ulids.length} order(s) status updated`);
    await refresh();
    if (tableRef.value) tableRef.value.resetRowSelection();
  } catch (err) {
    toast.error("Failed to update status", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    bulkUpdating.value = false;
  }
}

async function confirmBulkCancellation() {
  await doBulkOperationalStatus(bulkCancelRows.value, "cancelled", cancellationReason.value || null);
  cancelDialogOpen.value = false;
  bulkCancelRows.value = [];
}

async function handleBulkPaymentStatus(selectedRows, newStatus) {
  const ulids = selectedRows.map((row) => row.original.ulid);
  bulkUpdating.value = true;
  try {
    await Promise.all(
      ulids.map((ulid) =>
        client(`${apiBase.value}/${ulid}/payment-status`, {
          method: "PATCH",
          body: { payment_status: newStatus },
        })
      )
    );
    toast.success(`${ulids.length} order(s) payment status updated`);
    await refresh();
    if (tableRef.value) tableRef.value.resetRowSelection();
  } catch (err) {
    toast.error("Failed to update payment status", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    bulkUpdating.value = false;
  }
}

// Delete handlers
const deletePending = ref(false);
const deleteDialogOpen = ref(false);
async function handleDeleteRows(selectedRows) {
  const ulids = selectedRows.map((row) => row.original.ulid);
  try {
    deletePending.value = true;
    await Promise.all(ulids.map((ulid) => client(`${apiBase.value}/${ulid}`, { method: "DELETE" })));
    await refresh();
    deleteDialogOpen.value = false;
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }
    toast.success(`${ulids.length} order(s) deleted successfully`);
  } catch (err) {
    toast.error("Failed to delete order(s)", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
}

// Format helpers
function formatPrice(amount) {
  if (amount == null) return "-";
  return `Rp ${Number(amount).toLocaleString("id-ID")}`;
}

function orderDetailUrl(order) {
  return `/projects/${route.params.username}/events/${route.params.eventSlug}/operational/orders/${order.ulid}`;
}

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
    header: "Order",
    accessorKey: "order_number",
    cell: ({ row }) => {
      const order = row.original;
      const brand = order.brand_event?.brand;
      return h(
        "div",
        {
          class: "cursor-pointer",
          onClick: () => navigateTo(orderDetailUrl(order)),
        },
        [
          h(
            "div",
            { class: "font-mono text-sm font-medium tracking-tight" },
            order.order_number
          ),
          brand
            ? h(
                "div",
                { class: "text-muted-foreground text-xs sm:text-sm tracking-tight" },
                brand.company_name && brand.company_name !== brand.name
                  ? `${brand.name} · ${brand.company_name}`
                  : brand.name
              )
            : null,
        ]
      );
    },
    size: 250,
    enableHiding: false,
  },
  {
    header: "Items",
    accessorKey: "items_count",
    cell: ({ row }) =>
      h(
        "span",
        { class: "text-muted-foreground text-sm tracking-tight" },
        row.original.items_count ?? 0
      ),
    size: 70,
  },
  {
    header: "Total",
    accessorKey: "total",
    cell: ({ row }) =>
      h(
        "span",
        { class: "text-sm font-medium tracking-tight tabular-nums" },
        formatPrice(row.original.total)
      ),
    size: 140,
  },
  {
    header: "Status",
    accessorKey: "operational_status",
    cell: ({ row }) => {
      const order = row.original;
      const dropdown = h(OrderStatusDropdown, {
        status: order.operational_status,
        statuses: operationalStatuses,
        disabled: statusUpdating.value === `op-${order.ulid}`,
        onUpdate: (newStatus) =>
          handleOperationalStatusUpdate(order.ulid, newStatus),
      });

      // Show cancellation reason as tooltip if cancelled
      if (order.operational_status === "cancelled" && order.cancellation_reason) {
        return withDirectives(
          h("div", {}, [dropdown]),
          [[resolveDirective("tippy"), order.cancellation_reason]]
        );
      }
      return dropdown;
    },
    size: 140,
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      return filterValue.includes(row.getValue(columnId));
    },
  },
  {
    header: "Payment",
    accessorKey: "payment_status",
    cell: ({ row }) =>
      h(OrderStatusDropdown, {
        status: row.original.payment_status,
        statuses: paymentStatuses,
        disabled: statusUpdating.value === `pay-${row.original.ulid}`,
        onUpdate: (newStatus) =>
          handlePaymentStatusUpdate(row.original.ulid, newStatus),
      }),
    size: 140,
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      return filterValue.includes(row.getValue(columnId));
    },
  },
  {
    header: "Submitted",
    accessorKey: "submitted_at",
    cell: ({ row }) => {
      const date = row.original.submitted_at || row.original.created_at;
      if (!date) return h("span", { class: "text-muted-foreground text-sm" }, "-");
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
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) =>
      h(
        "div",
        { class: "flex items-center justify-end" },
        [
          h(
            "button",
            {
              class: "hover:bg-muted inline-flex size-8 items-center justify-center rounded-md",
              onClick: () => navigateTo(orderDetailUrl(row.original)),
            },
            [
              h(resolveComponent("Icon"), {
                name: "lucide:chevron-right",
                class: "text-muted-foreground size-4",
              }),
            ]
          ),
        ]
      ),
    size: 50,
    enableHiding: false,
    enableSorting: false,
  },
];

// Table ref
const tableRef = ref();

// Filter helpers
const getFilterValue = (columnId) => {
  return columnFilters.value.find((f) => f.id === columnId)?.value ?? [];
};

const selectedOperationalStatuses = computed(() => getFilterValue("operational_status"));
const selectedPaymentStatuses = computed(() => getFilterValue("payment_status"));
const totalActiveFilters = computed(
  () => selectedOperationalStatuses.value.length + selectedPaymentStatuses.value.length
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

// Export handler
const exportPending = ref(false);
const handleExport = async () => {
  try {
    exportPending.value = true;

    const params = new URLSearchParams();

    // Add search filter
    const searchFilter = columnFilters.value.find((f) => f.id === "order_number");
    if (searchFilter?.value) {
      params.append("filter_search", searchFilter.value);
    }

    // Add operational status filter
    if (selectedOperationalStatuses.value.length > 0) {
      params.append("filter_operational_status", selectedOperationalStatuses.value.join(","));
    }

    // Add payment status filter
    if (selectedPaymentStatuses.value.length > 0) {
      params.append("filter_payment_status", selectedPaymentStatuses.value.join(","));
    }

    // Add sorting
    const sortField = sorting.value[0]?.id || "submitted_at";
    const sortDirection = sorting.value[0]?.desc ? "-" : "";
    params.append("sort", `${sortDirection}${sortField}`);

    const response = await client(`${apiBase.value}/export?${params.toString()}`, {
      responseType: "blob",
    });

    const blob = new Blob([response], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `orders_${new Date().toISOString().slice(0, 19).replace(/:/g, "-")}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);

    toast.success("Orders exported successfully");
  } catch (err) {
    toast.error("Failed to export orders", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    exportPending.value = false;
  }
};
</script>
