<template>
  <div class="space-y-6 pb-16">
    <div
      class="flex flex-col gap-x-2.5 gap-y-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
    >
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:hotel-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Hotels</h1>
      </div>

      <div class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <Button v-if="canCreate" variant="outline" @click="showAttachDialog = true">
          <Icon name="hugeicons:link-04" class="size-4 shrink-0" />
          <span>Attach hotel</span>
        </Button>
        <Button v-if="canCreate" as-child>
          <NuxtLink :to="`${eventBase}/hotels/create`">
            <Icon name="lucide:plus" class="size-4 shrink-0" />
            <span>New hotel</span>
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
          <Icon name="hugeicons:bed-single-01" />
        </div>
        <div>
          <Icon name="hugeicons:hotel-01" />
        </div>
        <div class="translate-y-1.5 rotate-6">
          <Icon name="hugeicons:link-04" />
        </div>
      </div>
      <div class="space-y-1">
        <h3 class="font-semibold tracking-tight">
          {{ isUnavailable ? "Hotel reservations unavailable" : "No hotels yet" }}
        </h3>
        <p class="text-muted-foreground max-w-sm text-sm tracking-tight">
          {{
            isUnavailable
              ? "Enable hotel reservations for this event and make sure the project has an active payment gateway."
              : "Add a new hotel with its rooms and rates, or attach a hotel that already exists in another event."
          }}
        </p>
      </div>
    </div>

    <TableData
      v-else
      ref="tableRef"
      :data="hotels"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="hotels"
      label="Hotel"
      :client-only="false"
      :show-add-button="false"
      search-column="name"
      search-placeholder="Search hotel name or city..."
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
      @update:pagination="(v) => (pagination = v)"
      @update:sorting="(v) => (sorting = v)"
      @update:column-filters="(v) => (columnFilters = v)"
      @refresh="refresh"
    >
      <template #filters="{ table }">
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
              <div class="space-y-3">
                <div class="text-muted-foreground text-xs font-medium">Status</div>
                <div class="space-y-2">
                  <div
                    v-for="opt in [
                      { label: 'Active', value: '1' },
                      { label: 'Inactive', value: '0' },
                    ]"
                    :key="opt.value"
                    class="flex items-center gap-2"
                  >
                    <Checkbox
                      :id="`hotels-status-${opt.value}`"
                      :model-value="selectedStatuses.includes(opt.value)"
                      @update:model-value="
                        (checked) => handleStatusToggle({ checked: !!checked, value: opt.value })
                      "
                    />
                    <Label
                      :for="`hotels-status-${opt.value}`"
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
          <div class="text-primary text-lg font-semibold tracking-tight">
            Detach hotel from event?
          </div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            "{{ deletingHotel?.name }}" will be detached from this event. The hotel record stays in
            the master list and remains attached to any other events.
          </p>
          <div class="mt-3 flex justify-end gap-2">
            <Button variant="outline" type="button" @click="deleteDialogOpen = false">
              Cancel
            </Button>
            <Button variant="destructive" :disabled="deleting" @click="handleDelete">
              <Spinner v-if="deleting" />
              {{ deleting ? "Detaching..." : "Detach" }}
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <HotelPicker
      v-if="event?.id"
      v-model:open="showAttachDialog"
      :event-id="event.id"
      @success="refresh()"
    />
  </div>
</template>

<script setup>
import HotelPicker from "@/components/hotel/HotelPicker.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Spinner } from "@/components/ui/spinner";
import { TableData } from "@/components/ui/table-data";
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
  permissions: ["hotels.read"],
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
  title: computed(() => `Hotels · ${props.event?.title || "Event"}`),
});

const client = useSanctumClient();
const { hasPermission } = usePermission();
const canCreate = computed(() => hasPermission("hotels.create"));
const canUpdate = computed(() => hasPermission("hotels.update"));
const canDelete = computed(() => hasPermission("hotels.delete"));

const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 15 });
const sorting = ref([{ id: "created_at", desc: true }]);

const buildQueryParams = () => {
  const params = new URLSearchParams();
  params.append("page", pagination.value.pageIndex + 1);
  params.append("per_page", pagination.value.pageSize);

  const search = columnFilters.value.find((f) => f.id === "name");
  if (search?.value) params.append("filter_search", search.value);

  const statusFilter = columnFilters.value.find((f) => f.id === "is_active");
  if (Array.isArray(statusFilter?.value) && statusFilter.value.length === 1) {
    params.append("filter_is_active", statusFilter.value[0]);
  }

  const sortField = sorting.value[0]?.id || "created_at";
  const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
  params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);

  return params.toString();
};

const {
  data: hotelsResponse,
  pending,
  error,
  refresh,
} = await useLazySanctumFetch(() => `/api/events/${props.event?.id}/hotels?${buildQueryParams()}`, {
  key: () => `hotels-admin-list-${props.event?.id}`,
  watch: false,
});

const hotels = computed(() => hotelsResponse.value?.data ?? []);
const meta = computed(
  () =>
    hotelsResponse.value?.meta || {
      current_page: 1,
      last_page: 1,
      per_page: 15,
      total: 0,
    }
);

// Feature is gated by the `hotel-reservation-enabled` middleware: the admin
// hotels endpoint 404s when the toggle is off or the project has no active
// payment gateway. Surface that as a clear state instead of a blank page.
const isUnavailable = computed(() => !pending.value && !!error.value);

// Full-page empty state only when the event genuinely has no hotels - not
// when a search/filter simply returned nothing (TableData handles that).
const isEmpty = computed(
  () =>
    !pending.value && !error.value && hotels.value.length === 0 && columnFilters.value.length === 0
);

const showEmptyState = computed(() => isUnavailable.value || isEmpty.value);

watch([columnFilters, sorting, pagination], () => refresh(), { deep: true });

const selectedStatuses = computed(() => {
  const filter = columnFilters.value.find((f) => f.id === "is_active");
  return Array.isArray(filter?.value) ? filter.value : [];
});

const totalActiveFilters = computed(() => selectedStatuses.value.length);

const handleStatusToggle = ({ checked, value }) => {
  const current = selectedStatuses.value;
  const updated = checked ? [...current, value] : current.filter((v) => v !== value);
  const existingIndex = columnFilters.value.findIndex((f) => f.id === "is_active");
  if (updated.length) {
    if (existingIndex >= 0) {
      columnFilters.value[existingIndex].value = updated;
    } else {
      columnFilters.value.push({ id: "is_active", value: updated });
    }
  } else if (existingIndex >= 0) {
    columnFilters.value.splice(existingIndex, 1);
  }
  pagination.value.pageIndex = 0;
};

const deleteDialogOpen = ref(false);
const deletingHotel = ref(null);
const deleting = ref(false);
const showAttachDialog = ref(false);

const confirmDelete = (hotel) => {
  deletingHotel.value = hotel;
  deleteDialogOpen.value = true;
};

const handleDelete = async () => {
  if (!deletingHotel.value) return;
  deleting.value = true;
  try {
    await client(`/api/events/${props.event.id}/hotels/${deletingHotel.value.slug}`, {
      method: "DELETE",
    });
    toast.success("Hotel detached from event");
    deleteDialogOpen.value = false;
    await refresh();
  } catch (err) {
    toast.error("Detach failed", { description: err?.data?.message || err?.message });
  } finally {
    deleting.value = false;
  }
};

const tableRef = ref();

const RowActions = defineComponent({
  props: { hotel: { type: Object, required: true } },
  setup(p) {
    const base = eventBase.value;
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
                                to: `${base}/hotels/${p.hotel.slug}`,
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
                                    to: `${base}/hotels/${p.hotel.slug}/edit`,
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
                                    onClick: () => confirmDelete(p.hotel),
                                  },
                                  [
                                    h(resolveComponent("Icon"), {
                                      name: "lucide:link-2-off",
                                      class: "size-4 shrink-0",
                                    }),
                                    h("span", {}, "Detach from event"),
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
    header: "Hotel",
    accessorKey: "name",
    cell: ({ row }) => {
      const hotel = row.original;
      const base = eventBase.value;
      return h(
        resolveComponent("NuxtLink"),
        {
          to: `${base}/hotels/${hotel.slug}`,
          class: "flex items-center gap-3 hover:opacity-80 transition-opacity",
        },
        {
          default: () => [
            h(
              "div",
              {
                class: "bg-muted relative size-12 shrink-0 overflow-hidden rounded-md border",
              },
              hotel.featured?.sm || hotel.featured?.md
                ? h("img", {
                    src: hotel.featured.sm || hotel.featured.md,
                    alt: hotel.name,
                    class: "size-full object-cover",
                    loading: "lazy",
                  })
                : h("div", {
                    class: "from-muted to-muted/40 size-full bg-gradient-to-br",
                  })
            ),
            h("div", { class: "min-w-0 flex flex-col gap-0.5" }, [
              h("div", { class: "font-medium tracking-tight truncate" }, hotel.name),
              h(
                "div",
                {
                  class: "text-muted-foreground flex items-center gap-x-1 text-sm tracking-tight",
                },
                [
                  hotel.star_rating
                    ? h("span", { class: "flex items-center gap-x-1 shrink-0" }, [
                        h(resolveComponent("Icon"), {
                          name: "material-symbols:star-rounded",
                          class: "text-foreground size-4 shrink-0",
                        }),
                        h("span", {}, `${hotel.star_rating}-star`),
                      ])
                    : null,
                  hotel.star_rating && hotel.city ? h("span", { class: "shrink-0" }, "·") : null,
                  h("span", { class: "truncate" }, hotel.city || (hotel.star_rating ? "" : "-")),
                ]
              ),
            ]),
          ],
        }
      );
    },
    size: 320,
    enableHiding: false,
    filterFn: (row, columnId, filterValue) => {
      const v = filterValue.toLowerCase();
      const name = row.original.name?.toLowerCase() || "";
      const city = row.original.city?.toLowerCase() || "";
      return name.includes(v) || city.includes(v);
    },
  },
  {
    header: "Status",
    accessorKey: "is_active",
    cell: ({ row }) =>
      h(
        Badge,
        {
          variant: row.original.is_active ? "success" : "muted",
          withIcon: true,
          plain: true,
        },
        { default: () => (row.original.is_active ? "Active" : "Inactive") }
      ),
    size: 100,
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      const isActive = row.getValue(columnId) ? "1" : "0";
      return filterValue.includes(isActive);
    },
  },
  {
    header: "Room types",
    accessorKey: "room_types_count",
    cell: ({ row }) => {
      const count = row.original.room_types_count ?? 0;
      return h(
        "span",
        { class: "tabular-nums text-sm tracking-tight" },
        `${count} type${count === 1 ? "" : "s"}`
      );
    },
    size: 100,
  },
  {
    header: "Allotment",
    accessorKey: "allotment_sold",
    cell: ({ row }) => {
      const sold = Number(row.original.allotment_sold ?? 0);
      const total = Number(row.original.allotment_total ?? 0);
      if (total === 0) {
        return h("span", { class: "text-muted-foreground text-sm tracking-tight" }, "-");
      }
      const ratio = sold / total;
      const variant = ratio >= 0.8 ? "destructive" : ratio >= 0.5 ? "warning" : "success";
      return h(Badge, { variant, plain: true }, { default: () => `${sold}/${total}` });
    },
    size: 110,
  },
  {
    header: "Bookings",
    accessorKey: "paid_reservations_count",
    cell: ({ row }) => {
      const count = Number(row.original.paid_reservations_count ?? 0);
      if (count === 0) {
        return h("span", { class: "text-muted-foreground text-sm tracking-tight" }, "-");
      }
      return h("span", { class: "tabular-nums text-sm tracking-tight" }, `${count} paid`);
    },
    size: 100,
  },
  {
    header: "Revenue",
    accessorKey: "revenue",
    cell: ({ row }) => {
      const amount = Number(row.original.revenue ?? 0);
      if (amount === 0) {
        return h("span", { class: "text-muted-foreground text-sm tracking-tight" }, "-");
      }
      // Indonesian compact: rb (ribu), jt (juta), M (miliar). No space
      // after `Rp` per the project's Rupiah formatting convention.
      const formatted =
        amount >= 1_000_000_000
          ? `Rp${(amount / 1_000_000_000).toFixed(1)}M`
          : amount >= 1_000_000
            ? `Rp${(amount / 1_000_000).toFixed(1)}jt`
            : amount >= 1_000
              ? `Rp${Math.round(amount / 1_000)}rb`
              : `Rp${amount}`;
      return h("span", { class: "tabular-nums text-sm tracking-tight font-medium" }, formatted);
    },
    size: 120,
  },
  {
    header: "Price range",
    accessorKey: "price_min",
    cell: ({ row }) => {
      const min = Number(row.original.price_min ?? 0);
      const max = Number(row.original.price_max ?? 0);
      if (min === 0 && max === 0) {
        return h("span", { class: "text-muted-foreground text-sm tracking-tight" }, "-");
      }
      const fmt = (n) =>
        n >= 1_000_000
          ? `${(n / 1_000_000).toFixed(1)}jt`
          : n >= 1_000
            ? `${Math.round(n / 1_000)}rb`
            : `${n}`;
      const label = min === max ? `Rp${fmt(min)}` : `Rp${fmt(min)} – ${fmt(max)}`;
      return h("span", { class: "tabular-nums text-sm tracking-tight" }, `${label} /night`);
    },
    size: 160,
  },
  {
    header: "Pricing",
    accessorKey: "has_dynamic_pricing",
    cell: ({ row }) => {
      const isDynamic = row.original.has_dynamic_pricing;
      return h(
        Badge,
        { variant: isDynamic ? "info" : "muted", plain: true },
        { default: () => (isDynamic ? "Dynamic" : "Flat") }
      );
    },
    size: 100,
  },
  {
    header: "Last booking",
    accessorKey: "last_booking_at",
    cell: ({ row }) => {
      const date = row.original.last_booking_at;
      if (!date) return h("span", { class: "text-muted-foreground text-sm tracking-tight" }, "-");
      return withDirectives(
        h(
          "span",
          { class: "text-muted-foreground text-sm tracking-tight" },
          $dayjs(date).fromNow()
        ),
        [[resolveDirective("tippy"), $dayjs(date).format("MMMM D, YYYY [at] h:mm A")]]
      );
    },
    size: 130,
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
        { default: () => h(RowActions, { hotel: row.original }) }
      ),
    size: 60,
    enableHiding: false,
  },
];
</script>
