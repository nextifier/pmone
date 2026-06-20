<template>
  <div class="space-y-6 pb-16">
    <div
      class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
    >
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:user-multiple-02" class="size-5 sm:size-6" />
        <h1 class="page-title">Attendees</h1>
      </div>

      <div v-if="!hasSelectedRows" class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <Button
          v-if="canExport"
          variant="outline"
          size="sm"
          :disabled="exportPending"
          @click="handleExport"
        >
          <Spinner v-if="exportPending" class="size-4 shrink-0" />
          <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
          <span>Export</span>
        </Button>
        <Button v-if="canDelete" variant="outline" size="sm" as-child>
          <NuxtLink :to="`${eventBase}/attendees/trash`">
            <Icon name="hugeicons:delete-02" class="size-4 shrink-0" />
            <span>Trash</span>
          </NuxtLink>
        </Button>
      </div>

      <div v-else class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <Button variant="outline" size="sm" @click="clearSelection">
          <Icon name="lucide:x" class="size-4 shrink-0" />
          <span>Clear selection</span>
        </Button>
      </div>
    </div>

    <!-- Empty state -->
    <div
      v-if="showEmptyState"
      class="flex flex-col items-center justify-center gap-y-4 py-16 text-center"
    >
      <div
        class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
      >
        <div class="translate-y-1.5 -rotate-6">
          <Icon name="hugeicons:ticket-01" />
        </div>
        <div>
          <Icon name="hugeicons:user-multiple-02" />
        </div>
        <div class="translate-y-1.5 rotate-6">
          <Icon name="hugeicons:qr-code" />
        </div>
      </div>
      <div class="space-y-1">
        <h3 class="font-semibold tracking-tight">No attendees yet</h3>
        <p class="text-muted-foreground max-w-sm text-sm tracking-tight">
          Ticket holders for this event will appear here once people start registering.
        </p>
      </div>
    </div>

    <AttendeeAnalyticsSummary v-if="!showEmptyState" :event="event" :event-base="eventBase" />

    <TableData
      v-if="!showEmptyState"
      ref="tableRef"
      :data="items"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="attendees"
      label="Attendee"
      :client-only="false"
      :show-add-button="false"
      search-column="name"
      search-placeholder="Search name, email, phone, order…"
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
                      :id="`attendees-${group.id}-${opt.value}`"
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
                      :for="`attendees-${group.id}-${opt.value}`"
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
        <TableBulkAction
          v-if="canUpdate"
          :icon="
            selectedRows.every((r) => r.original.is_checked_in)
              ? 'hugeicons:cancel-circle'
              : 'hugeicons:checkmark-circle-02'
          "
          :label="
            selectedRows.every((r) => r.original.is_checked_in)
              ? 'Mark as not checked in'
              : 'Mark as checked in'
          "
          :loading="bulkCheckInPending"
          @click="bulkCheckIn(selectedRows)"
        />

        <DialogResponsive v-if="canUpdate" v-model:open="bulkResendDialogOpen">
          <template #trigger="{ open }">
            <TableBulkAction icon="hugeicons:mail-02" label="Resend e-ticket" @click="open()" />
          </template>
          <template #default>
            <div class="px-4 pb-10 md:px-6 md:py-5">
              <div class="text-primary text-lg font-semibold tracking-tight">Resend e-ticket?</div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This emails the e-ticket to the {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "attendee" : "attendees" }} who have an email address.
              </p>
              <div class="mt-3 flex justify-end gap-2">
                <button
                  class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                  :disabled="bulkResendPending"
                  @click="bulkResendDialogOpen = false"
                >
                  Cancel
                </button>
                <button
                  class="bg-primary text-primary-foreground hover:bg-primary/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
                  :disabled="bulkResendPending"
                  @click="bulkResend(selectedRows)"
                >
                  <Spinner v-if="bulkResendPending" class="size-4" />
                  <span v-else>Send</span>
                </button>
              </div>
            </div>
          </template>
        </DialogResponsive>

        <DialogResponsive v-if="canDelete" v-model:open="deleteDialogOpen">
          <template #trigger="{ open }">
            <TableBulkAction icon="lucide:trash" label="Delete" destructive @click="open()" />
          </template>
          <template #default>
            <div class="px-4 pb-10 md:px-6 md:py-5">
              <div class="text-primary text-lg font-semibold tracking-tight">Are you sure?</div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This will delete {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "attendee" : "attendees" }}.
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

    <AttendeeEditDialog
      v-if="event?.id"
      v-model:open="editOpen"
      :event="event"
      :attendee="editing"
      @saved="onAttendeeSaved"
    />
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
import TicketQr from "@/components/TicketQr.vue";
import AttendeeAnalyticsSummary from "@/components/AttendeeAnalyticsSummary.vue";
import { useAttendeesChangedSignal } from "@/composables/useAttendeeAnalytics";
import AttendeeEditDialog from "@/components/ticket/AttendeeEditDialog.vue";
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
  permissions: ["attendees.read"],
  layout: "app",
});

const props = defineProps({
  event: Object,
  project: Object,
});

const route = useRoute();
const { $dayjs } = useNuxtApp();
const config = useRuntimeConfig();
const apiBase = config.public.apiUrl;

const eventBase = computed(
  () => `/projects/${route.params.username}/events/${route.params.eventSlug}`
);

// Bumped after any mutation that changes attendee counts/check-ins so the live
// Overview analytics refetch instantly (see useAttendeeAnalytics).
const attendeesChanged = useAttendeesChangedSignal(props.event?.id);

usePageMeta(null, {
  title: computed(() => `Attendees · ${props.event?.title || "Event"}`),
});

const client = useSanctumClient();
const { hasPermission } = usePermission();
const canDelete = computed(() => hasPermission("attendees.delete"));
const canUpdate = computed(() => hasPermission("attendees.update"));
const canExport = computed(() => hasPermission("attendees.export"));
const canViewDocs = computed(() => hasPermission("attendees.view_documents"));

const checkInOptions = [
  { label: "Checked in", value: "in" },
  { label: "Not checked in", value: "out" },
];

const orderStatusOptions = [
  { label: "Pending Payment", value: "pending_payment" },
  { label: "Confirmed", value: "confirmed" },
  { label: "Cancelled", value: "cancelled" },
  { label: "Expired", value: "expired" },
];

const modeOptions = [
  { label: "Live", value: "live" },
  { label: "Test", value: "test" },
];

// Static `columnFilters` id → backend `filter_*` param mapping. Drives the list
// query and the export. Kept separate from `filterGroups` (which carries the
// rendered options) because the Payment options come from data loaded below.
const filterParams = [
  { id: "checked_in", param: "filter_checked_in" },
  { id: "order_status", param: "filter_order_status" },
  { id: "payment_channel", param: "filter_payment_channel" },
  { id: "mode", param: "filter_mode" },
];

const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 50 });
const sorting = ref([{ id: "id", desc: true }]);

const selectedFilter = (id) => {
  const filter = columnFilters.value.find((f) => f.id === id);
  return Array.isArray(filter?.value) ? filter.value : [];
};

// Shared by the list query and the export request so both honour the same
// active filters. Multi-select values go out comma-separated.
const appendFilters = (params) => {
  const search = columnFilters.value.find((f) => f.id === "name");
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

  const sortField = sorting.value[0]?.id || "id";
  const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
  params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);

  return params.toString();
};

const { data, pending, error, refresh } = await useLazySanctumFetch(
  () => `/api/events/${props.event?.id}/attendees?${buildQueryParams()}`,
  {
    key: () => `attendees-list-${props.event?.id}`,
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
// ...) actually present in this event's attendees are offered, so the list
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
  const groups = [
    { id: "checked_in", label: "Check-in", options: checkInOptions },
    { id: "order_status", label: "Order status", options: orderStatusOptions },
  ];
  if (paymentChannelOptions.value.length) {
    groups.push({
      id: "payment_channel",
      label: "Payment",
      options: paymentChannelOptions.value,
    });
  }
  groups.push({ id: "mode", label: "Mode", options: modeOptions });
  return groups;
});

// Full-page empty state only when the event genuinely has no attendees - not
// when a search/filter simply returned nothing (TableData handles that).
const showEmptyState = computed(
  () =>
    !pending.value && !error.value && items.value.length === 0 && columnFilters.value.length === 0
);

watch([columnFilters, sorting, pagination], () => refresh(), { deep: true });

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

function dayLabel(d) {
  const label = d?.label;
  let text;
  if (label && typeof label === "object") {
    text = label.en || label.id || Object.values(label)[0] || `Day ${d.day_number}`;
  } else {
    text = label || (d?.day_number ? `Day ${d.day_number}` : "");
  }
  return appendDayDate(text, d?.date);
}

// Payment environment of the attendee's order, derived from the linked
// gateway's `mode`. Provider-agnostic, so a future Midtrans gateway resolves
// here too.
const modeMeta = {
  live: { label: "Live", variant: "success", icon: "hugeicons:rocket-01" },
  test: { label: "Test", variant: "warning", icon: "hugeicons:test-tube-01" },
};

const tableRef = ref();

const hasSelectedRows = computed(
  () => tableRef.value?.table?.getSelectedRowModel()?.rows?.length > 0
);
const clearSelection = () => tableRef.value?.resetRowSelection();

const editOpen = ref(false);
const editing = ref(null);

function edit(attendee) {
  editing.value = attendee;
  editOpen.value = true;
}

const deleteDialogOpen = ref(false);
const deletePending = ref(false);

const onAttendeeSaved = async () => {
  await refresh();
  attendeesChanged.value++;
};

const handleDeleteRows = async (selectedRows) => {
  const ids = selectedRows.map((row) => row.original.id);
  try {
    deletePending.value = true;
    const result = await client(`/api/events/${props.event.id}/attendees/bulk`, {
      method: "DELETE",
      body: { ids },
    });
    await refresh();
    attendeesChanged.value++;
    deleteDialogOpen.value = false;
    tableRef.value?.resetRowSelection();
    toast.success(result.message || "Attendees deleted", {
      description: `${result.deleted_count} attendee(s) deleted`,
    });
  } catch (err) {
    toast.error("Failed to delete attendees", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

const handleDeleteSingleRow = async (id) => {
  try {
    deletePending.value = true;
    const result = await client(`/api/events/${props.event.id}/attendees/${id}`, {
      method: "DELETE",
    });
    await refresh();
    attendeesChanged.value++;
    tableRef.value?.resetRowSelection();
    toast.success(result.message || "Attendee deleted");
  } catch (err) {
    toast.error("Failed to delete attendee", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

async function toggleCheckIn(attendee) {
  const wasCheckedIn = attendee.is_checked_in;
  try {
    await client(`/api/events/${props.event.id}/attendees/${attendee.id}`, {
      method: "PATCH",
      body: { checked_in: !wasCheckedIn },
    });
    await refresh();
    attendeesChanged.value++;
    toast.success(wasCheckedIn ? "Marked as not checked in" : "Checked in");
  } catch (err) {
    toast.error("Failed to update check-in", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  }
}

async function resendETicket(attendee) {
  try {
    const result = await client(
      `/api/events/${props.event.id}/attendees/${attendee.id}/resend-eticket`,
      { method: "POST" }
    );
    toast.success(result.message || "E-ticket email is being sent", {
      description: attendee.email,
    });
  } catch (err) {
    toast.error("Failed to resend e-ticket", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  }
}

// The shareable e-ticket page lives on the event's public website (pmone-events),
// not the admin app — so the link is built from the event's Website URL. Hidden
// entirely when the event has no public website configured.
const eticketBase = computed(() => (props.event?.website_url || "").replace(/\/+$/, ""));
const eticketUrlFor = (attendee) => `${eticketBase.value}/tickets/${attendee.ulid}`;

async function copyEticketLink(attendee) {
  try {
    await navigator.clipboard.writeText(eticketUrlFor(attendee));
    toast.success("E-ticket link copied");
  } catch {
    toast.error("Could not copy link");
  }
}

const bulkCheckInPending = ref(false);
const bulkCheckIn = async (selectedRows) => {
  const ids = selectedRows.map((row) => row.original.id);
  const allChecked = selectedRows.every((row) => row.original.is_checked_in);
  try {
    bulkCheckInPending.value = true;
    const result = await client(`/api/events/${props.event.id}/attendees/bulk-check-in`, {
      method: "POST",
      body: { ids, checked_in: !allChecked },
    });
    await refresh();
    attendeesChanged.value++;
    tableRef.value?.resetRowSelection();
    toast.success(result.message || "Attendees updated");
  } catch (err) {
    toast.error("Failed to update check-in", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    bulkCheckInPending.value = false;
  }
};

const bulkResendDialogOpen = ref(false);
const bulkResendPending = ref(false);
const bulkResend = async (selectedRows) => {
  const ids = selectedRows.map((row) => row.original.id);
  try {
    bulkResendPending.value = true;
    const result = await client(`/api/events/${props.event.id}/attendees/bulk-resend-eticket`, {
      method: "POST",
      body: { ids },
    });
    await refresh();
    tableRef.value?.resetRowSelection();
    bulkResendDialogOpen.value = false;
    toast.success(result.message || "E-ticket emails are being sent");
  } catch (err) {
    toast.error("Failed to resend e-tickets", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    bulkResendPending.value = false;
  }
};

const RowActions = defineComponent({
  props: { attendee: { type: Object, required: true } },
  setup(p) {
    const qrOpen = ref(false);
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
                { align: "end", class: "w-56 p-1" },
                {
                  default: () =>
                    h("div", { class: "flex flex-col" }, [
                      h(
                        "button",
                        {
                          class:
                            "hover:bg-muted rounded-md px-3 py-2 text-left text-sm tracking-tight whitespace-nowrap flex items-center gap-x-1.5",
                          onClick: () => (qrOpen.value = true),
                        },
                        [
                          h(resolveComponent("Icon"), {
                            name: "hugeicons:qr-code",
                            class: "size-4 shrink-0",
                          }),
                          h("span", {}, "Show QR"),
                        ]
                      ),
                      h(
                        PopoverClose,
                        { asChild: true },
                        {
                          default: () =>
                            h(
                              "button",
                              {
                                class:
                                  "hover:bg-muted rounded-md px-3 py-2 text-left text-sm tracking-tight whitespace-nowrap flex items-center gap-x-1.5",
                                onClick: () => edit(p.attendee),
                              },
                              [
                                h(resolveComponent("Icon"), {
                                  name: "hugeicons:pencil-edit-02",
                                  class: "size-4 shrink-0",
                                }),
                                h("span", {}, "Edit"),
                              ]
                            ),
                        }
                      ),
                      canUpdate.value
                        ? h(
                            PopoverClose,
                            { asChild: true },
                            {
                              default: () =>
                                h(
                                  "button",
                                  {
                                    class:
                                      "hover:bg-muted rounded-md px-3 py-2 text-left text-sm tracking-tight whitespace-nowrap flex items-center gap-x-1.5",
                                    onClick: () => toggleCheckIn(p.attendee),
                                  },
                                  [
                                    h(resolveComponent("Icon"), {
                                      name: p.attendee.is_checked_in
                                        ? "hugeicons:cancel-circle"
                                        : "hugeicons:checkmark-circle-02",
                                      class: "size-4 shrink-0",
                                    }),
                                    h(
                                      "span",
                                      {},
                                      p.attendee.is_checked_in
                                        ? "Mark as not checked in"
                                        : "Mark as checked in"
                                    ),
                                  ]
                                ),
                            }
                          )
                        : null,
                      canUpdate.value && p.attendee.email
                        ? h(
                            PopoverClose,
                            { asChild: true },
                            {
                              default: () =>
                                h(
                                  "button",
                                  {
                                    class:
                                      "hover:bg-muted rounded-md px-3 py-2 text-left text-sm tracking-tight whitespace-nowrap flex items-center gap-x-1.5",
                                    onClick: () => resendETicket(p.attendee),
                                  },
                                  [
                                    h(resolveComponent("Icon"), {
                                      name: "hugeicons:mail-02",
                                      class: "size-4 shrink-0",
                                    }),
                                    h("span", {}, "Resend e-ticket"),
                                  ]
                                ),
                            }
                          )
                        : null,
                      eticketBase.value
                        ? h(
                            PopoverClose,
                            { asChild: true },
                            {
                              default: () =>
                                h(
                                  "button",
                                  {
                                    class:
                                      "hover:bg-muted rounded-md px-3 py-2 text-left text-sm tracking-tight whitespace-nowrap flex items-center gap-x-1.5",
                                    onClick: () => copyEticketLink(p.attendee),
                                  },
                                  [
                                    h(resolveComponent("Icon"), {
                                      name: "hugeicons:link-01",
                                      class: "size-4 shrink-0",
                                    }),
                                    h("span", {}, "Copy e-ticket link"),
                                  ]
                                ),
                            }
                          )
                        : null,
                      eticketBase.value
                        ? h(
                            PopoverClose,
                            { asChild: true },
                            {
                              default: () =>
                                h(
                                  "a",
                                  {
                                    href: eticketUrlFor(p.attendee),
                                    target: "_blank",
                                    rel: "noopener",
                                    class:
                                      "hover:bg-muted rounded-md px-3 py-2 text-left text-sm tracking-tight whitespace-nowrap flex items-center gap-x-1.5",
                                  },
                                  [
                                    h(resolveComponent("Icon"), {
                                      name: "hugeicons:ticket-02",
                                      class: "size-4 shrink-0",
                                    }),
                                    h("span", {}, "View e-ticket"),
                                  ]
                                ),
                            }
                          )
                        : null,
                      p.attendee.can_view_documents
                        ? h(
                            PopoverClose,
                            { asChild: true },
                            {
                              default: () =>
                                h(
                                  "a",
                                  {
                                    href: `${apiBase}/api/events/${props.event.id}/ticket-orders/${p.attendee.order?.ulid}/invoice.pdf`,
                                    target: "_blank",
                                    rel: "noopener",
                                    class:
                                      "hover:bg-muted rounded-md px-3 py-2 text-left text-sm tracking-tight whitespace-nowrap flex items-center gap-x-1.5",
                                  },
                                  [
                                    h(resolveComponent("Icon"), {
                                      name: "hugeicons:invoice-03",
                                      class: "size-4 shrink-0",
                                    }),
                                    h("span", {}, "Invoice"),
                                  ]
                                ),
                            }
                          )
                        : null,
                      p.attendee.can_view_documents &&
                      p.attendee.order?.status === "confirmed" &&
                      p.attendee.paid_at
                        ? h(
                            PopoverClose,
                            { asChild: true },
                            {
                              default: () =>
                                h(
                                  "a",
                                  {
                                    href: `${apiBase}/api/events/${props.event.id}/ticket-orders/${p.attendee.order?.ulid}/receipt.pdf`,
                                    target: "_blank",
                                    rel: "noopener",
                                    class:
                                      "hover:bg-muted rounded-md px-3 py-2 text-left text-sm tracking-tight whitespace-nowrap flex items-center gap-x-1.5",
                                  },
                                  [
                                    h(resolveComponent("Icon"), {
                                      name: "hugeicons:invoice-04",
                                      class: "size-4 shrink-0",
                                    }),
                                    h("span", {}, "Receipt"),
                                  ]
                                ),
                            }
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
                                      "hover:bg-destructive/10 text-destructive rounded-md px-3 py-2 text-left text-sm tracking-tight whitespace-nowrap flex items-center gap-x-1.5",
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
            open: qrOpen.value,
            "onUpdate:open": (value) => (qrOpen.value = value),
            dialogMaxWidth: "360px",
          },
          {
            default: () =>
              h("div", { class: "space-y-4 px-4 pb-10 md:px-6 md:py-5" }, [
                h("div", { class: "space-y-1 text-center" }, [
                  h(
                    "h2",
                    { class: "text-lg font-semibold tracking-tighter" },
                    p.attendee.name || "Attendee"
                  ),
                  p.attendee.ticket?.title
                    ? h(
                        "p",
                        { class: "text-muted-foreground text-sm tracking-tight" },
                        p.attendee.ticket.title
                      )
                    : null,
                ]),
                h(TicketQr, {
                  token: p.attendee.qr_token,
                  containerClass: "w-full max-w-[220px] mx-auto",
                }),
              ]),
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
                  "Are you sure?"
                ),
                h(
                  "p",
                  { class: "text-body mt-1.5 text-sm tracking-tight" },
                  "This will delete this attendee."
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
                          await handleDeleteSingleRow(p.attendee.id);
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
    header: "Name",
    accessorKey: "name",
    cell: ({ row }) => {
      const a = row.original;
      const indicators = [];
      if (a.is_personalized) {
        indicators.push(
          withDirectives(
            h(resolveComponent("Icon"), {
              name: "hugeicons:user-check-01",
              class: "text-muted-foreground size-3.5 shrink-0",
            }),
            [[resolveDirective("tippy"), "Personalized by attendee"]]
          )
        );
      }
      if (a.has_account) {
        indicators.push(
          withDirectives(
            h(resolveComponent("Icon"), {
              name: "hugeicons:user-circle",
              class: "text-muted-foreground size-3.5 shrink-0",
            }),
            [[resolveDirective("tippy"), "Has linked account"]]
          )
        );
      }
      return h("div", { class: "min-w-0 flex flex-col gap-0.5" }, [
        h("div", { class: "flex items-center gap-x-1" }, [
          h("span", { class: "text-sm tracking-tight truncate" }, a.name || "Unnamed"),
          ...indicators,
        ]),
        h(
          "div",
          { class: "text-muted-foreground text-xs tracking-tight truncate" },
          a.email || a.phone || "-"
        ),
      ]);
    },
    size: 230,
    enableHiding: false,
  },
  {
    header: "Ticket",
    accessorKey: "ticket",
    enableSorting: false,
    cell: ({ row }) => {
      const a = row.original;
      return h("div", { class: "min-w-0 flex flex-col gap-0.5" }, [
        h("div", { class: "text-sm tracking-tight truncate" }, a.ticket?.title || "-"),
        h("div", { class: "flex items-center gap-x-1.5" }, [
          a.ticket?.tier
            ? h(
                "span",
                { class: "text-muted-foreground text-xs tracking-tight truncate" },
                a.ticket.tier
              )
            : null,
          a.order?.number
            ? h(
                "span",
                { class: "text-muted-foreground font-mono text-xs tracking-tight truncate" },
                a.order.number
              )
            : null,
        ]),
      ]);
    },
    size: 210,
  },
  {
    header: "Day / Session",
    id: "day_session",
    enableSorting: false,
    cell: ({ row }) => {
      const a = row.original;
      const parts = [];
      if (a.day) parts.push(dayLabel(a.day));
      if (a.session) parts.push(a.session.label);
      if (!parts.length) {
        return h("span", { class: "text-muted-foreground text-sm tracking-tight" }, "-");
      }
      return h("span", { class: "text-sm tracking-tight" }, parts.join(" · "));
    },
    size: 160,
  },
  {
    header: "Check-in",
    accessorKey: "is_checked_in",
    cell: ({ row }) => {
      const a = row.original;
      const badge = h(
        Badge,
        {
          variant: a.is_checked_in ? "success" : "muted",
          withIcon: true,
          plain: true,
        },
        { default: () => (a.is_checked_in ? "Checked in" : "Not checked in") }
      );
      if (!a.is_checked_in || !a.checked_in_at) return badge;
      const rel = $dayjs(a.checked_in_at).fromNow();
      const by = a.checked_in_by_name ? ` by ${a.checked_in_by_name}` : "";
      return withDirectives(badge, [[resolveDirective("tippy"), `Checked in ${rel}${by}`]]);
    },
    size: 140,
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      const wantIn = filterValue.includes("in");
      const wantOut = filterValue.includes("out");
      const checked = !!row.original.is_checked_in;
      return (wantIn && checked) || (wantOut && !checked);
    },
  },
  {
    header: "Payment",
    accessorKey: "payment_channel",
    cell: ({ row }) => {
      const a = row.original;
      if (a.is_free || !a.payment_channel) {
        return h("span", { class: "text-muted-foreground text-sm tracking-tight" }, "-");
      }
      return h(PaymentMethodBadge, {
        channel: a.payment_channel,
        size: "md",
        iconOnly: true,
      });
    },
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
        { default: () => h(RowActions, { attendee: row.original }) }
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
      `/api/events/${props.event.id}/attendees/export?${params.toString()}`,
      { responseType: "blob" }
    );
    const url = URL.createObjectURL(
      new Blob([blob], {
        type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      })
    );
    const a = document.createElement("a");
    a.href = url;
    a.download = `attendees_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
