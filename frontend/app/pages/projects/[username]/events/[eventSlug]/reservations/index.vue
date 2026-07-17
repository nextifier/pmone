<template>
  <div class="space-y-6 pb-16">
    <div
      class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
    >
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:calendar-02" class="size-5 sm:size-6" />
        <h1 class="page-title">Reservations</h1>
      </div>

      <div class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <Button variant="outline" size="sm" :disabled="exportPending" @click="handleExport">
          <Spinner v-if="exportPending" class="size-4 shrink-0" />
          <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
          <span>Export</span>
        </Button>
        <Button v-if="canDelete" variant="outline" size="sm" as-child>
          <NuxtLink :to="`${eventBase}/reservations/trash`">
            <Icon name="hugeicons:delete-02" class="size-4 shrink-0" />
            <span>Trash</span>
          </NuxtLink>
        </Button>
        <Button v-if="canCreate" as-child size="sm">
          <NuxtLink :to="`${eventBase}/reservations/create`">
            <Icon name="lucide:plus" class="size-4 shrink-0" />
            <span>Create</span>
          </NuxtLink>
        </Button>
      </div>
    </div>

    <!-- Empty / unavailable state -->
    <div
      v-if="showEmptyState"
      class="flex flex-col items-center justify-center gap-y-4 py-16 text-center"
    >
      <div
        class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
      >
        <div class="translate-y-1.5 -rotate-6">
          <Icon name="hugeicons:hotel-01" />
        </div>
        <div>
          <Icon name="hugeicons:calendar-02" />
        </div>
        <div class="translate-y-1.5 rotate-6">
          <Icon name="hugeicons:notebook-01" />
        </div>
      </div>
      <div class="space-y-1">
        <h3 class="font-semibold tracking-tight">
          {{ isUnavailable ? "Hotel reservations unavailable" : "No reservations yet" }}
        </h3>
        <p class="text-muted-foreground max-w-sm text-sm tracking-tight">
          {{
            isUnavailable
              ? "Enable hotel reservations for this event and make sure the project has an active payment gateway."
              : "Hotel bookings for this event will appear here once guests start reserving."
          }}
        </p>
      </div>
    </div>

    <template v-else>
      <ReservationAnalyticsSummary :event="event" :event-base="eventBase" />

      <TableData
        ref="tableRef"
        :data="items"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="reservations"
      label="Reservation"
      :client-only="false"
      :show-add-button="false"
      search-column="reservation_number"
      search-placeholder="Number, name, email"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
      @update:pagination="(v) => (pagination = v)"
      @update:sorting="(v) => (sorting = v)"
      @update:column-filters="(v) => (columnFilters = v)"
      @refresh="refresh"
    >
      <template #filters>
        <ClientOnly>
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
            <PopoverContent class="w-auto min-w-52 space-y-4 p-3" align="start">
              <div v-for="group in filterGroups" :key="group.id" class="space-y-2">
                <div class="text-muted-foreground text-xs font-medium">
                  {{ group.label }}
                </div>
                <div class="space-y-2">
                  <div
                    v-for="opt in group.options"
                    :key="opt.value"
                    class="flex items-center gap-2"
                  >
                    <Checkbox
                      :id="`reservations-${group.id}-${opt.value}`"
                      :model-value="selectedFilter(group.id).includes(opt.value)"
                      @update:model-value="
                        (checked) =>
                          handleFilterToggle(group.id, {
                            checked: !!checked,
                            value: opt.value,
                          })
                      "
                    />
                    <Label
                      :for="`reservations-${group.id}-${opt.value}`"
                      class="grow cursor-pointer font-normal tracking-tight"
                    >
                      {{ opt.label }}
                    </Label>
                  </div>
                </div>
              </div>
            </PopoverContent>
          </Popover>
        </ClientOnly>
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
                This will delete {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "reservation" : "reservations" }}.
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
    </template>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { PaymentMethodBadge } from "@/components/ui/payment-method-badge";
import { getPaymentChannelLabel } from "@/lib/payment-method-logos";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Spinner } from "@/components/ui/spinner";
import { TableData, TableBulkAction } from "@/components/ui/table-data";
import { PopoverClose } from "reka-ui";
import {
  computed,
  defineComponent,
  h,
  ref,
  resolveComponent,
  resolveDirective,
  watch,
  withDirectives,
} from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["reservations.read"],
  layout: "app",
});

const props = defineProps({
  event: Object,
  project: Object,
});

const route = useRoute();
const { $dayjs } = useNuxtApp();

const eventBase = computed(
  () => `/projects/${route.params.username}/events/${route.params.eventSlug}`
);

usePageMeta(null, {
  title: computed(() => `Reservations · ${props.event?.title || "Event"}`),
});

const client = useSanctumClient();
const { hasPermission } = usePermission();
const canCreate = computed(() => hasPermission("reservations.manual_entry"));
const canDelete = computed(() => hasPermission("reservations.delete"));

const statusOptions = [
  { label: "Pending Payment", value: "pending_payment" },
  { label: "Paid", value: "paid" },
  { label: "Voucher Sent", value: "voucher_sent" },
  { label: "Expired", value: "expired" },
  { label: "Cancelled", value: "cancelled" },
  { label: "Refunded", value: "refunded" },
];

const statusLabelMap = Object.fromEntries(statusOptions.map((s) => [s.value, s.label]));

const modeOptions = [
  { label: "Live", value: "live" },
  { label: "Test", value: "test" },
];

// Static `columnFilters` id → backend `filter_*` param mapping. Drives the list
// query and the export. Kept separate from `filterGroups` (which carries the
// rendered options) because the Payment options come from data loaded below.
const filterParams = [
  { id: "status", param: "filter_status" },
  { id: "payment", param: "filter_payment_channel" },
  { id: "mode", param: "filter_mode" },
];

const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 50 });
const sorting = ref([{ id: "created_at", desc: true }]);

const selectedFilter = (id) => {
  const filter = columnFilters.value.find((f) => f.id === id);
  return Array.isArray(filter?.value) ? filter.value : [];
};

// Shared by the list query and the export request so both honour the same
// active filters. Multi-select values go out comma-separated.
const appendFilters = (params) => {
  const search = columnFilters.value.find((f) => f.id === "reservation_number");
  if (search?.value) params.append("filter_search", search.value);
  for (const { id, param } of filterParams) {
    const values = selectedFilter(id);
    if (values.length) params.append(param, values.join(","));
  }
};

const buildQueryParams = () => {
  const params = new URLSearchParams();
  params.append("page", pagination.value.pageIndex + 1);
  params.append("per_page", pagination.value.pageSize);

  appendFilters(params);

  const sortField = sorting.value[0]?.id || "created_at";
  const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
  params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);

  return params.toString();
};

const { data, pending, error, refresh } = await useLazySanctumFetch(
  () => `/api/events/${props.event?.id}/reservations?${buildQueryParams()}`,
  {
    key: () => `reservations-list-${props.event?.id}`,
    watch: false,
  }
);

const items = computed(() => data.value?.data ?? []);
const meta = computed(
  () =>
    data.value?.meta || {
      current_page: 1,
      last_page: 1,
      per_page: 50,
      total: 0,
      payment_channels: [],
    }
);

// Payment filter options are data-driven: only the channels (BCA, QRIS, Visa,
// ...) actually present in this event's reservations are offered, so the list
// stays relevant and grows automatically as new channels are used.
const paymentChannelOptions = computed(() =>
  (meta.value.payment_channels ?? []).map((channel) => ({
    value: channel,
    label: getPaymentChannelLabel(channel) ?? channel,
  }))
);

// Rendered filter groups. The Payment group is omitted entirely when this event
// has no channelled payments yet, rather than showing an empty heading.
const filterGroups = computed(() => {
  const groups = [{ id: "status", label: "Status", options: statusOptions }];
  if (paymentChannelOptions.value.length) {
    groups.push({ id: "payment", label: "Payment", options: paymentChannelOptions.value });
  }
  groups.push({ id: "mode", label: "Mode", options: modeOptions });
  return groups;
});

// Feature is gated by the `hotel-reservation-enabled` middleware: the admin
// reservations endpoint 404s when the toggle is off or the project has no
// active payment gateway. Surface that clearly instead of a blank page.
const isUnavailable = computed(() => !pending.value && !!error.value);

// Full-page empty state only when the event genuinely has no reservations -
// not when a search/filter simply returned nothing (TableData handles that).
const isEmpty = computed(
  () =>
    !pending.value && !error.value && items.value.length === 0 && columnFilters.value.length === 0
);

const showEmptyState = computed(() => isUnavailable.value || isEmpty.value);

watch([columnFilters, sorting, pagination], () => refresh(), { deep: true });

// Bookings come in from the public site; poll to keep the list current — but
// not while the feature is gated (the endpoint 404s), to avoid polling a 404.
usePolling(() => {
  if (!isUnavailable.value) {
    return refresh();
  }
}, 30000);

const totalActiveFilters = computed(() =>
  filterParams.reduce((sum, { id }) => sum + selectedFilter(id).length, 0)
);

const handleFilterToggle = (id, { checked, value }) => {
  const current = selectedFilter(id);
  const updated = checked ? [...current, value] : current.filter((v) => v !== value);
  const existingIndex = columnFilters.value.findIndex((f) => f.id === id);
  if (updated.length) {
    if (existingIndex >= 0) {
      columnFilters.value[existingIndex].value = updated;
    } else {
      columnFilters.value.push({ id, value: updated });
    }
  } else if (existingIndex >= 0) {
    columnFilters.value.splice(existingIndex, 1);
  }
  pagination.value.pageIndex = 0;
};

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);

const statusVariant = (status) => {
  const map = {
    pending_payment: "warning",
    paid: "success",
    voucher_sent: "success",
    expired: "muted",
    cancelled: "destructive",
    refunded: "destructive",
  };
  return map[status] || "muted";
};

// Payment environment of the reservation, derived from the linked gateway's
// `mode`. Provider-agnostic, so a future Midtrans gateway resolves here too.
const modeMeta = {
  live: { label: "Live", variant: "success", icon: "hugeicons:rocket-01" },
  test: { label: "Test", variant: "warning", icon: "hugeicons:test-tube-01" },
};

const tableRef = ref();

const deleteDialogOpen = ref(false);
const deletePending = ref(false);

const handleDeleteRows = async (selectedRows) => {
  const ids = selectedRows.map((row) => row.original.id);
  try {
    deletePending.value = true;
    const result = await client(`/api/events/${props.event.id}/reservations/bulk`, {
      method: "DELETE",
      body: { ids },
    });
    await refresh();
    deleteDialogOpen.value = false;
    tableRef.value?.resetRowSelection();
    toast.success(result.message || "Reservations deleted", {
      description: `${result.deleted_count} reservation(s) deleted`,
    });
  } catch (err) {
    toast.error("Failed to delete reservations", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

const handleDeleteSingleRow = async (ulid) => {
  try {
    deletePending.value = true;
    const result = await client(`/api/events/${props.event.id}/reservations/${ulid}`, {
      method: "DELETE",
    });
    await refresh();
    tableRef.value?.resetRowSelection();
    toast.success(result.message || "Reservation deleted");
  } catch (err) {
    toast.error("Failed to delete reservation", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

const RowActions = defineComponent({
  props: { reservation: { type: Object, required: true } },
  setup(p) {
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
                      [
                        h(resolveComponent("Icon"), {
                          name: "lucide:ellipsis",
                          class: "size-4",
                        }),
                      ]
                    ),
                }
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
                                to: `${eventBase.value}/reservations/${p.reservation.ulid}`,
                                class:
                                  "hover:bg-muted rounded-md px-3 py-2 text-left text-sm tracking-tight flex items-center gap-x-1.5",
                              },
                              {
                                default: () => [
                                  h(resolveComponent("Icon"), {
                                    name: "hugeicons:eye",
                                    class: "size-4 shrink-0",
                                  }),
                                  h("span", {}, "View"),
                                ],
                              }
                            ),
                        }
                      ),
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
                                  ]
                                ),
                            }
                          )
                        : null,
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
                  { class: "text-foreground text-lg font-semibold tracking-tight" },
                  "Are you sure?"
                ),
                h(
                  "p",
                  { class: "text-body mt-1.5 text-sm tracking-tight" },
                  "This will delete this reservation."
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
                          await handleDeleteSingleRow(p.reservation.ulid);
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
    header: "Number",
    accessorKey: "reservation_number",
    cell: ({ row }) => {
      const r = row.original;
      return h(
        resolveComponent("NuxtLink"),
        {
          to: `${eventBase.value}/reservations/${r.ulid}`,
          class: "font-mono text-xs sm:text-sm tracking-tight text-primary hover:underline",
        },
        { default: () => r.reservation_number }
      );
    },
    size: 165,
    enableHiding: false,
    filterFn: (row, columnId, filterValue) => {
      const v = filterValue.toLowerCase();
      const r = row.original;
      return (
        r.reservation_number?.toLowerCase().includes(v) ||
        r.guest_name?.toLowerCase().includes(v) ||
        r.guest_email?.toLowerCase().includes(v)
      );
    },
  },
  {
    header: "Guest",
    accessorKey: "guest_name",
    cell: ({ row }) =>
      h("div", { class: "flex flex-col gap-0.5" }, [
        h("div", { class: "text-sm tracking-tight" }, row.original.guest_name),
        h(
          "div",
          { class: "text-muted-foreground text-xs tracking-tight" },
          row.original.guest_email
        ),
      ]),
    size: 230,
  },
  {
    header: "Hotel",
    accessorKey: "hotel",
    cell: ({ row }) =>
      h("span", { class: "text-sm tracking-tight" }, row.original.hotel?.name || "-"),
    size: 195,
  },
  {
    header: "Status",
    accessorKey: "status",
    cell: ({ row }) =>
      h(
        Badge,
        { variant: statusVariant(row.original.status), withIcon: true, plain: true },
        {
          default: () =>
            row.original.status_label || statusLabelMap[row.original.status] || row.original.status,
        }
      ),
    size: 165,
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      return filterValue.includes(row.getValue(columnId));
    },
  },
  {
    header: "Total",
    accessorKey: "total_amount",
    cell: ({ row }) =>
      h(
        "span",
        { class: "text-sm tabular-nums tracking-tight" },
        `Rp${formatRupiah(row.original.total_amount)}`
      ),
    size: 120,
  },
  {
    header: "Payment",
    accessorKey: "payment_channel",
    cell: ({ row }) =>
      h(PaymentMethodBadge, {
        channel: row.original.payment_channel,
        method: row.original.payment_method,
        size: "md",
        iconOnly: true,
      }),
    size: 105,
  },
  {
    header: "Mode",
    accessorKey: "payment_mode",
    cell: ({ row }) => {
      const mode = row.original.payment_mode;
      const meta = mode ? modeMeta[mode] : null;
      if (!meta) {
        return h("span", { class: "text-muted-foreground text-sm tracking-tight" }, "-");
      }
      const provider = row.original.payment_provider;
      const tooltip = provider
        ? `${meta.label} mode · ${provider.charAt(0).toUpperCase()}${provider.slice(1)}`
        : `${meta.label} mode`;
      return withDirectives(
        h(
          Badge,
          { variant: meta.variant, icon: meta.icon, plain: false },
          { default: () => meta.label }
        ),
        [[resolveDirective("tippy"), tooltip]]
      );
    },
    size: 110,
  },
  {
    header: "Created",
    accessorKey: "created_at",
    cell: ({ row }) => {
      const date = row.getValue("created_at");
      if (!date) return h("span", { class: "text-muted-foreground" }, "-");
      return withDirectives(
        h("div", { class: "text-muted-foreground text-sm tracking-tight" }, $dayjs(date).fromNow()),
        [[resolveDirective("tippy"), $dayjs(date).format("MMMM D, YYYY [at] h:mm A")]]
      );
    },
    size: 110,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) =>
      h(
        resolveComponent("ClientOnly"),
        {},
        { default: () => h(RowActions, { reservation: row.original }) }
      ),
    size: 56,
    enableHiding: false,
  },
];

const exportPending = ref(false);
const handleExport = async () => {
  exportPending.value = true;
  try {
    const params = new URLSearchParams();
    appendFilters(params);
    const blob = await client(
      `/api/events/${props.event.id}/reservations/export?${params.toString()}`,
      { responseType: "blob" }
    );
    const url = URL.createObjectURL(
      new Blob([blob], {
        type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      })
    );
    const a = document.createElement("a");
    a.href = url;
    a.download = `reservations_${new Date().toISOString().slice(0, 10)}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
    toast.success("Export downloaded");
  } catch (err) {
    toast.error("Export failed", { description: err?.data?.message || err?.message });
  } finally {
    exportPending.value = false;
  }
};
</script>
