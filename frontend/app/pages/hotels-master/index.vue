<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex flex-col gap-y-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:hotel-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Hotels</h1>
      </div>

      <div class="flex shrink-0 gap-1 sm:gap-2">
        <NuxtLink
          v-if="canDelete"
          to="/hotels-master/trash"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
          <span>Trash</span>
        </NuxtLink>
        <Button v-if="canCreate" as-child size="sm">
          <NuxtLink to="/hotels-master/create">
            <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
            <span>Add Hotel</span>
          </NuxtLink>
        </Button>
      </div>
    </div>

    <TableData
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
                      :id="`hotels-master-status-${opt.value}`"
                      :model-value="selectedStatuses.includes(opt.value)"
                      @update:model-value="
                        (checked) => handleStatusToggle({ checked: !!checked, value: opt.value })
                      "
                    />
                    <Label
                      :for="`hotels-master-status-${opt.value}`"
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
          <div class="text-primary text-lg font-semibold tracking-tighter">
            Move hotel to trash?
          </div>
          <p class="text-muted-foreground mt-2 text-sm tracking-tight">
            "{{ deletingHotel?.name }}" will be moved to trash globally. It will be detached from
            every event it is currently attached to.
          </p>
          <div class="mt-3 flex justify-end gap-2">
            <Button variant="outline" type="button" @click="deleteDialogOpen = false">
              Cancel
            </Button>
            <Button variant="destructive" :disabled="deleting" @click="handleDelete">
              <Spinner v-if="deleting" />
              {{ deleting ? "Deleting..." : "Move to trash" }}
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import DialogResponsive from "@/components/ui/dialog-responsive/DialogResponsive.vue";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
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

usePageMeta(null, { title: "Hotels · Master" });

const { $dayjs } = useNuxtApp();
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
} = await useLazySanctumFetch(() => `/api/hotels?${buildQueryParams()}`, {
  key: "hotels-master-list",
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

const confirmDelete = (hotel) => {
  deletingHotel.value = hotel;
  deleteDialogOpen.value = true;
};

const handleDelete = async () => {
  if (!deletingHotel.value) return;
  deleting.value = true;
  try {
    await client(`/api/hotels/${deletingHotel.value.slug}`, { method: "DELETE" });
    toast.success("Hotel moved to trash");
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
  props: { hotel: { type: Object, required: true } },
  setup(p) {
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
                { align: "end", class: "w-44 p-1" },
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
                                to: `/hotels-master/${p.hotel.slug}`,
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
                                    to: `/hotels-master/${p.hotel.slug}/edit`,
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
                                      name: "lucide:trash",
                                      class: "size-4 shrink-0",
                                    }),
                                    h("span", {}, "Move to trash"),
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
      return h(
        resolveComponent("NuxtLink"),
        {
          to: `/hotels-master/${hotel.slug}`,
          class: "flex items-center gap-3 hover:opacity-80 transition-opacity",
        },
        {
          default: () => [
            h(
              "div",
              {
                class:
                  "bg-muted relative size-12 shrink-0 overflow-hidden rounded-md border",
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
            h("div", { class: "flex min-w-0 flex-col gap-0.5" }, [
              h("div", { class: "truncate font-medium tracking-tight" }, hotel.name),
              h(
                "div",
                {
                  class: "text-muted-foreground truncate text-xs tracking-tight sm:text-sm",
                },
                hotel.city || "-"
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
        "span",
        {
          class: [
            "inline-flex items-center rounded-full px-2 py-0.5 text-xs tracking-tight sm:text-sm",
            row.original.is_active
              ? "bg-success/15 text-success-foreground"
              : "bg-muted text-muted-foreground",
          ],
        },
        row.original.is_active ? "Active" : "Inactive"
      ),
    size: 100,
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      const isActive = row.getValue(columnId) ? "1" : "0";
      return filterValue.includes(isActive);
    },
  },
  {
    header: "Events attached",
    accessorKey: "events",
    cell: ({ row }) => {
      const events = row.original.events || [];
      if (!events.length) {
        return h(
          "span",
          { class: "text-muted-foreground/70 text-xs tracking-tight sm:text-sm" },
          "Not attached"
        );
      }
      return h("div", { class: "flex flex-wrap gap-1" }, [
        ...events.slice(0, 3).map((ev) =>
          h(
            "span",
            {
              class:
                "bg-muted inline-flex items-center gap-x-1 rounded-full px-2 py-0.5 text-xs tracking-tight sm:text-sm",
            },
            ev.title || ev.name || ev.slug
          )
        ),
        events.length > 3
          ? h(
              "span",
              { class: "text-muted-foreground text-xs tracking-tight sm:text-sm" },
              `+${events.length - 3} more`
            )
          : null,
      ]);
    },
    size: 240,
  },
  {
    header: "Rooms",
    accessorKey: "room_types_count",
    cell: ({ row }) => {
      const n = row.original.room_types_count ?? 0;
      return h(
        "span",
        {
          class: `tabular-nums text-sm tracking-tight ${n === 0 ? "text-muted-foreground/50" : ""}`,
        },
        n
      );
    },
    size: 80,
  },
  {
    header: "Created",
    accessorKey: "created_at",
    cell: ({ row }) => {
      const date = row.getValue("created_at");
      if (!date)
        return h(
          "span",
          { class: "text-muted-foreground/70 text-xs tracking-tight sm:text-sm" },
          "-"
        );
      return withDirectives(
        h(
          "div",
          { class: "text-muted-foreground text-xs tracking-tight sm:text-sm" },
          $dayjs(date).fromNow()
        ),
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
