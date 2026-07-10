<template>
  <div class="mx-auto space-y-6 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div
      class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
    >
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:ticket-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Tickets</h1>
        <span
          v-if="!pending && !isDisabled && meta?.total"
          class="text-muted-foreground text-sm tracking-tight tabular-nums"
        >
          {{ meta.total }}
        </span>
      </div>

      <div v-if="!isDisabled" class="flex flex-wrap gap-2 sm:ml-auto sm:shrink-0 sm:flex-nowrap">
        <Button v-if="canUpdate" as-child variant="outline">
          <NuxtLink :to="`${ticketsBase}/settings`">
            <Icon name="hugeicons:settings-02" class="size-4 shrink-0" />
            <span>Ticket Settings</span>
          </NuxtLink>
        </Button>
        <Button v-if="canBulkGenerate && event?.id" variant="outline" @click="bulkOpen = true">
          <Icon name="hugeicons:ticket-02" class="size-4 shrink-0" />
          <span>Bulk generate</span>
        </Button>
        <Button v-if="canViewAccessCodes" as-child variant="outline">
          <NuxtLink :to="accessCodesBase">
            <Icon name="hugeicons:ticket-star" class="size-4 shrink-0" />
            <span>Access Codes</span>
          </NuxtLink>
        </Button>
        <Button v-if="canCreate" as-child>
          <NuxtLink :to="`${ticketsBase}/create`">
            <Icon name="lucide:plus" class="size-4 shrink-0" />
            <span>New ticket</span>
          </NuxtLink>
        </Button>
      </div>
    </div>

    <!-- Feature disabled: enable in Settings -->
    <div
      v-if="isDisabled"
      class="flex flex-col items-center justify-center gap-y-4 py-16 text-center"
    >
      <div
        class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
      >
        <div class="translate-y-1.5 -rotate-6">
          <Icon name="hugeicons:ticket-02" />
        </div>
        <div>
          <Icon name="hugeicons:ticket-01" />
        </div>
        <div class="translate-y-1.5 rotate-6">
          <Icon name="hugeicons:calendar-03" />
        </div>
      </div>
      <div class="space-y-1">
        <h3 class="font-semibold tracking-tighter">Tickets are disabled</h3>
        <p class="text-muted-foreground max-w-sm text-sm tracking-tight">
          Turn on ticketing for this event to create tickets, price phases, and sessions. The
          public website only shows tickets once the feature is enabled.
        </p>
      </div>
      <Button as-child variant="outline" size="sm">
        <NuxtLink :to="`${ticketsBase}/settings`">
          <Icon name="hugeicons:settings-02" class="size-4 shrink-0" />
          <span>Go to Settings</span>
        </NuxtLink>
      </Button>
    </div>

    <!-- Genuine empty state (enabled, no tickets, no filters) -->
    <div
      v-else-if="isEmpty"
      class="flex flex-col items-center justify-center gap-y-4 py-16 text-center"
    >
      <div
        class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
      >
        <div class="translate-y-1.5 -rotate-6">
          <Icon name="hugeicons:ticket-02" />
        </div>
        <div>
          <Icon name="hugeicons:ticket-01" />
        </div>
        <div class="translate-y-1.5 rotate-6">
          <Icon name="hugeicons:tag-01" />
        </div>
      </div>
      <div class="space-y-1">
        <h3 class="font-semibold tracking-tighter">No tickets yet</h3>
        <p class="text-muted-foreground max-w-sm text-sm tracking-tight">
          Create entry tickets for event admission, or add-on tickets for sessions and extras.
        </p>
      </div>
      <Button v-if="canCreate" as-child size="sm">
        <NuxtLink :to="`${ticketsBase}/create`">
          <Icon name="lucide:plus" class="size-4 shrink-0" />
          <span>New ticket</span>
        </NuxtLink>
      </Button>
    </div>

    <TableData
      v-else
      ref="tableRef"
      :data="tickets"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="tickets"
      label="Ticket"
      :client-only="false"
      :show-add-button="false"
      search-column="title"
      search-placeholder="Search ticket title"
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
            <PopoverContent class="w-auto min-w-48 p-3" align="start">
              <div class="space-y-4">
                <div class="space-y-2">
                  <div class="text-muted-foreground text-xs font-medium sm:text-sm">Kind</div>
                  <div
                    v-for="opt in [
                      { label: 'Entry', value: 'entry' },
                      { label: 'Add-on', value: 'add_on' },
                    ]"
                    :key="opt.value"
                    class="flex items-center gap-2"
                  >
                    <Checkbox
                      :id="`tickets-kind-${opt.value}`"
                      :model-value="selectedKinds.includes(opt.value)"
                      @update:model-value="
                        (checked) => handleKindToggle({ checked: !!checked, value: opt.value })
                      "
                    />
                    <Label
                      :for="`tickets-kind-${opt.value}`"
                      class="grow cursor-pointer font-normal tracking-tight"
                    >
                      {{ opt.label }}
                    </Label>
                  </div>
                </div>

                <div class="space-y-2">
                  <div class="text-muted-foreground text-xs font-medium sm:text-sm">Status</div>
                  <div
                    v-for="opt in [
                      { label: 'Active', value: '1' },
                      { label: 'Inactive', value: '0' },
                    ]"
                    :key="opt.value"
                    class="flex items-center gap-2"
                  >
                    <Checkbox
                      :id="`tickets-status-${opt.value}`"
                      :model-value="selectedStatuses.includes(opt.value)"
                      @update:model-value="
                        (checked) => handleStatusToggle({ checked: !!checked, value: opt.value })
                      "
                    />
                    <Label
                      :for="`tickets-status-${opt.value}`"
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
    </TableData>

    <DialogResponsive v-model:open="deleteDialogOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-foreground text-lg font-semibold tracking-tighter">Delete ticket?</div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            "{{ deletingTicket?.title }}" will be moved to trash. Its price phases and sessions go
            with it.
          </p>
          <div class="mt-3 flex justify-end gap-2">
            <Button variant="outline" type="button" @click="deleteDialogOpen = false">
              Cancel
            </Button>
            <Button variant="destructive" :disabled="deleting" @click="handleDelete">
              <Spinner v-if="deleting" />
              {{ deleting ? "Deleting..." : "Delete" }}
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <TicketBulkGenerateDialog v-if="event?.id" v-model:open="bulkOpen" :event="event" @generated="refresh" />
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import TicketBulkGenerateDialog from "@/components/ticket/TicketBulkGenerateDialog.vue";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Spinner } from "@/components/ui/spinner";
import { TableData } from "@/components/ui/table-data";
import { PopoverClose } from "reka-ui";
import { computed, defineComponent, h, ref, resolveComponent, watch } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["tickets.read"],
  layout: "app",
});

const props = defineProps({
  event: Object,
  project: Object,
});

const route = useRoute();

const ticketsBase = computed(
  () => `/projects/${route.params.username}/events/${route.params.eventSlug}/tickets`
);

const accessCodesBase = computed(
  () => `/projects/${route.params.username}/events/${route.params.eventSlug}/access-codes`
);

usePageMeta(null, {
  title: computed(() => `Tickets · ${props.event?.title || "Event"}`),
});

const client = useSanctumClient();
const { hasPermission } = usePermission();
const canCreate = computed(() => hasPermission("tickets.create"));
const canUpdate = computed(() => hasPermission("tickets.update"));
const canDelete = computed(() => hasPermission("tickets.delete"));
const canBulkGenerate = computed(() => hasPermission("tickets.bulk_generate"));
const canViewAccessCodes = computed(() => hasPermission("access_codes.read"));
const bulkOpen = ref(false);

const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 15 });
const sorting = ref([{ id: "order_column", desc: false }]);

const buildQueryParams = () => {
  const params = new URLSearchParams();
  params.append("page", pagination.value.pageIndex + 1);
  params.append("per_page", pagination.value.pageSize);

  const search = columnFilters.value.find((f) => f.id === "title");
  if (search?.value) params.append("search", search.value);

  const kindFilter = columnFilters.value.find((f) => f.id === "kind");
  if (Array.isArray(kindFilter?.value) && kindFilter.value.length === 1) {
    params.append("kind", kindFilter.value[0]);
  }

  const statusFilter = columnFilters.value.find((f) => f.id === "is_active");
  if (Array.isArray(statusFilter?.value) && statusFilter.value.length === 1) {
    params.append("is_active", statusFilter.value[0]);
  }

  return params.toString();
};

const {
  data: ticketsResponse,
  pending,
  error,
  refresh,
} = await useLazySanctumFetch(
  () => `/api/events/${props.event?.id}/tickets?${buildQueryParams()}`,
  {
    key: () => `tickets-admin-list-${props.event?.id}`,
    watch: false,
  }
);

const tickets = computed(() => ticketsResponse.value?.data ?? []);
const meta = computed(
  () =>
    ticketsResponse.value?.meta || {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
    }
);

// The tickets list endpoint is feature-gated: it returns 404 with
// error_code TICKETS_DISABLED when the toggle is off. Surface that as a clear
// "enable in Settings" state rather than a blank table.
const isDisabled = computed(
  () => !pending.value && error.value?.data?.error_code === "TICKETS_DISABLED"
);

const isEmpty = computed(
  () =>
    !pending.value &&
    !error.value &&
    tickets.value.length === 0 &&
    columnFilters.value.length === 0
);

watch([columnFilters, sorting, pagination], () => refresh(), { deep: true });

const selectedKinds = computed(() => {
  const filter = columnFilters.value.find((f) => f.id === "kind");
  return Array.isArray(filter?.value) ? filter.value : [];
});

const selectedStatuses = computed(() => {
  const filter = columnFilters.value.find((f) => f.id === "is_active");
  return Array.isArray(filter?.value) ? filter.value : [];
});

const totalActiveFilters = computed(
  () => selectedKinds.value.length + selectedStatuses.value.length
);

const toggleFilter = (id, value, checked) => {
  const current = (columnFilters.value.find((f) => f.id === id)?.value ?? []).slice();
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

const handleKindToggle = ({ checked, value }) => toggleFilter("kind", value, checked);
const handleStatusToggle = ({ checked, value }) => toggleFilter("is_active", value, checked);

const deleteDialogOpen = ref(false);
const deletingTicket = ref(null);
const deleting = ref(false);

const confirmDelete = (ticket) => {
  deletingTicket.value = ticket;
  deleteDialogOpen.value = true;
};

const handleDelete = async () => {
  if (!deletingTicket.value) return;
  deleting.value = true;
  try {
    await client(`/api/events/${props.event.id}/tickets/${deletingTicket.value.slug}`, {
      method: "DELETE",
    });
    toast.success("Ticket deleted");
    deleteDialogOpen.value = false;
    await refresh();
  } catch (err) {
    toast.error("Delete failed", { description: err?.data?.message || err?.message });
  } finally {
    deleting.value = false;
  }
};

const tableRef = ref();

const RowActions = defineComponent({
  props: { ticket: { type: Object, required: true } },
  setup(p) {
    const base = ticketsBase.value;
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
                { align: "end", class: "w-auto min-w-40 p-1 whitespace-nowrap" },
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
                                to: `${base}/${p.ticket.slug}`,
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
                      canUpdate.value
                        ? h(
                            PopoverClose,
                            { asChild: true },
                            {
                              default: () =>
                                h(
                                  resolveComponent("NuxtLink"),
                                  {
                                    to: `${base}/${p.ticket.slug}`,
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
                                  }
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
                                      "hover:bg-destructive/10 text-destructive rounded-md px-3 py-2 text-left text-sm tracking-tight flex items-center gap-x-1.5",
                                    onClick: () => confirmDelete(p.ticket),
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
      ]);
  },
});

const columns = [
  {
    header: "Ticket",
    accessorKey: "title",
    cell: ({ row }) => {
      const ticket = row.original;
      const base = ticketsBase.value;
      const poster = Array.isArray(ticket.poster) ? ticket.poster[0] : ticket.poster;
      const thumb = poster?.sm || poster?.md || poster?.url || null;
      return h(
        resolveComponent("NuxtLink"),
        {
          to: `${base}/${ticket.slug}`,
          class: "flex items-center gap-3 hover:opacity-80 transition-opacity",
        },
        {
          default: () => [
            h(
              "div",
              {
                class: "bg-muted relative size-12 shrink-0 overflow-hidden rounded-md border",
              },
              thumb
                ? h("img", {
                    src: thumb,
                    alt: ticket.title,
                    class: "size-full object-cover",
                    loading: "lazy",
                  })
                : h(
                    "div",
                    {
                      class:
                        "text-muted-foreground flex size-full items-center justify-center",
                    },
                    [h(resolveComponent("Icon"), { name: "hugeicons:ticket-01", class: "size-5" })]
                  )
            ),
            h("div", { class: "min-w-0 flex flex-col gap-0.5" }, [
              h("div", { class: "font-medium tracking-tight truncate" }, ticket.title || "-"),
              ticket.tier
                ? h(
                    "div",
                    { class: "text-muted-foreground text-sm tracking-tight truncate" },
                    ticket.tier
                  )
                : null,
            ]),
          ],
        }
      );
    },
    size: 280,
    enableHiding: false,
  },
  {
    header: "Kind",
    accessorKey: "kind",
    cell: ({ row }) =>
      h(
        Badge,
        { variant: row.original.kind === "entry" ? "info" : "muted", plain: true },
        { default: () => (row.original.kind === "entry" ? "Entry" : "Add-on") }
      ),
    size: 90,
  },
  {
    header: "Pricing",
    accessorKey: "price_phases_count",
    cell: ({ row }) => {
      const count = Number(row.original.price_phases_count ?? 0);
      if (count === 0) {
        return h(
          "span",
          { class: "text-muted-foreground text-sm tracking-tight" },
          "No phases"
        );
      }
      return h(
        "span",
        { class: "text-sm tracking-tight" },
        `${count} phase${count === 1 ? "" : "s"}`
      );
    },
    size: 110,
  },
  {
    header: "Sessions",
    accessorKey: "sessions_count",
    cell: ({ row }) => {
      const count = Number(row.original.sessions_count ?? 0);
      if (count === 0) {
        return h("span", { class: "text-muted-foreground text-sm tracking-tight" }, "-");
      }
      return h("span", { class: "tabular-nums text-sm tracking-tight" }, count);
    },
    size: 90,
  },
  {
    header: "Stock",
    accessorKey: "stock",
    cell: ({ row }) => {
      const stock = row.original.stock;
      const sold = Number(row.original.sold_count ?? 0);
      if (stock === null || stock === undefined) {
        return h(
          "span",
          { class: "text-muted-foreground text-sm tracking-tight" },
          `${sold} sold · unlimited`
        );
      }
      const total = Number(stock);
      const ratio = total > 0 ? sold / total : 0;
      const variant = ratio >= 0.8 ? "destructive" : ratio >= 0.5 ? "warning" : "success";
      return h(Badge, { variant, plain: true }, { default: () => `${sold}/${total}` });
    },
    size: 130,
  },
  {
    header: "Status",
    accessorKey: "is_active",
    cell: ({ row }) =>
      h(
        Badge,
        { variant: row.original.is_active ? "success" : "muted", plain: true },
        { default: () => (row.original.is_active ? "Active" : "Inactive") }
      ),
    size: 100,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) =>
      h(
        resolveComponent("ClientOnly"),
        {},
        { default: () => h(RowActions, { ticket: row.original }) }
      ),
    size: 60,
    enableHiding: false,
  },
];
</script>
