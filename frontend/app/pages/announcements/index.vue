<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:notification-02" class="size-5 sm:size-6" />
        <h1 class="page-title">Announcements</h1>
      </div>

      <div v-if="!hasSelectedRows" class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <NuxtLink
          v-if="canDelete"
          to="/announcements/trash"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
          <span>Trash</span>
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
      :clientOnly="clientOnly"
      ref="tableRef"
      :data="data"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="announcements"
      label="Announcement"
      search-column="title"
      search-placeholder="Search announcements..."
      error-title="Error loading announcements"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
      :show-add-button="canCreate"
      add-button-label="New Announcement"
      add-button-route="/announcements/create"
      @update:pagination="onPaginationUpdate"
      @update:sorting="onSortingUpdate"
      @update:column-filters="onColumnFiltersUpdate"
      @refresh="refresh"
    >
      <template #add-button>
        <Button v-if="canCreate" size="sm" @click="navigateTo('/announcements/create')">
          <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
          New Announcement
        </Button>
      </template>

      <template #actions="{ selectedRows }">
        <DialogResponsive
          v-if="canDelete && selectedRows.length > 0"
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
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This action can't be undone. This will permanently delete
                {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "announcement" : "announcements" }}.
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
  </div>
</template>

<script setup>
import AnnouncementTableItem from "@/components/announcement/TableItem.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { TableData } from "@/components/ui/table-data";
import { PopoverClose } from "reka-ui";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["announcements.read"],
  layout: "app",
});

usePageMeta(null, { title: "Announcements" });

defineOptions({ name: "announcements" });

const { hasPermission } = usePermission();
const canCreate = computed(() => hasPermission("announcements.create"));
const canDelete = computed(() => hasPermission("announcements.delete"));

const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 15 });
const sorting = ref([{ id: "order_column", desc: false }]);
const clientOnly = ref(false);

const buildQueryParams = () => {
  const params = new URLSearchParams();
  if (clientOnly.value) {
    params.append("client_only", "true");
  } else {
    params.append("page", pagination.value.pageIndex + 1);
    params.append("per_page", pagination.value.pageSize);

    const filters = {
      title: "filter_search",
      status: "filter_status",
      type: "filter_type",
    };
    Object.entries(filters).forEach(([colId, key]) => {
      const filter = columnFilters.value.find((f) => f.id === colId);
      if (filter?.value) {
        const value = Array.isArray(filter.value) ? filter.value.join(",") : filter.value;
        params.append(key, value);
      }
    });

    const sortField = sorting.value[0]?.id || "order_column";
    const sortDir = sorting.value[0]?.desc ? "desc" : "asc";
    params.append("sort_by", sortField);
    params.append("sort_dir", sortDir);
  }
  return params.toString();
};

const {
  data: response,
  pending,
  error,
  refresh: fetchAnnouncements,
} = await useLazySanctumFetch(() => `/api/announcements?${buildQueryParams()}`, {
  key: "announcements-list",
  watch: false,
});

const data = computed(() => response.value?.data || []);
const meta = computed(
  () => response.value?.meta || { current_page: 1, last_page: 1, per_page: 15, total: 0 }
);

watch(
  [columnFilters, sorting, pagination],
  () => {
    if (!clientOnly.value) fetchAnnouncements();
  },
  { deep: true }
);

const onPaginationUpdate = (val) => {
  pagination.value.pageIndex = val.pageIndex;
  pagination.value.pageSize = val.pageSize;
};
const onSortingUpdate = (val) => (sorting.value = val);
const onColumnFiltersUpdate = (val) => (columnFilters.value = val);

const refresh = fetchAnnouncements;

const tableRef = ref();

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

const STATUS_VARIANT = {
  published: "success",
  draft: "warning",
  archived: "muted",
};

const formatScheduleDate = (value) =>
  new Intl.DateTimeFormat("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  }).format(new Date(value));

const scheduleLabel = (start, end) => {
  if (!start && !end) return "Always on";
  if (start && end) return `${formatScheduleDate(start)} - ${formatScheduleDate(end)}`;
  if (start) return `Starts ${formatScheduleDate(start)}`;
  return `Ends ${formatScheduleDate(end)}`;
};

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
    header: "Announcement",
    accessorKey: "title",
    cell: ({ row }) => h(AnnouncementTableItem, { announcement: row.original }),
    size: 320,
    enableHiding: false,
  },
  {
    header: "Status",
    accessorKey: "status",
    cell: ({ row }) =>
      h(
        Badge,
        { variant: STATUS_VARIANT[row.original.status] || "muted", class: "capitalize" },
        { default: () => row.original.status }
      ),
    size: 120,
    enableSorting: true,
  },
  {
    header: "Type",
    accessorKey: "type",
    cell: ({ row }) =>
      h(Badge, { variant: "outline", class: "capitalize" }, { default: () => row.original.type }),
    size: 110,
    enableSorting: true,
  },
  {
    header: "Audience",
    accessorKey: "is_global",
    cell: ({ row }) =>
      h(
        Badge,
        { variant: row.original.is_global ? "info" : "muted" },
        { default: () => (row.original.is_global ? "Global" : "Targeted") }
      ),
    size: 120,
    enableSorting: false,
  },
  {
    header: "Schedule",
    accessorKey: "start_time",
    cell: ({ row }) =>
      h(
        "span",
        { class: "text-muted-foreground text-sm tracking-tight" },
        scheduleLabel(row.original.start_time, row.original.end_time)
      ),
    size: 190,
    enableSorting: true,
  },
  {
    header: "Order",
    accessorKey: "order_column",
    cell: ({ row }) =>
      h(
        "span",
        { class: "text-muted-foreground text-sm tracking-tight" },
        row.original.order_column ?? 0
      ),
    size: 80,
    enableSorting: true,
  },
  {
    header: "Dismissed",
    accessorKey: "dismissals_count",
    cell: ({ row }) =>
      h(
        "span",
        { class: "text-muted-foreground text-sm tracking-tight" },
        row.original.dismissals_count ?? 0
      ),
    size: 100,
    enableSorting: false,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) => h(RowActions, { announcement: row.original }),
    size: 60,
    enableHiding: false,
  },
];

// Delete handlers
const deleteDialogOpen = ref(false);
const deletePending = ref(false);

const handleDeleteRows = async (selectedRows) => {
  const announcementIds = selectedRows.map((row) => row.original.id);
  try {
    deletePending.value = true;
    const client = useSanctumClient();
    const response = await client("/api/announcements/bulk", {
      method: "DELETE",
      body: { ids: announcementIds },
    });
    await refresh();
    deleteDialogOpen.value = false;
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }

    toast.success(response.message || "Announcements deleted");
  } catch (error) {
    console.error("Failed to delete announcements:", error);
    toast.error("Failed to delete announcements", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

const handleDeleteSingleRow = async (announcementId) => {
  try {
    deletePending.value = true;
    const client = useSanctumClient();
    const response = await client(`/api/announcements/${announcementId}`, { method: "DELETE" });
    await refresh();

    // Reset row selection after delete
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }

    toast.success(response.message || "Announcement deleted successfully");
  } catch (error) {
    console.error("Failed to delete announcement:", error);
    toast.error("Failed to delete announcement", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

// Row Actions Component
const RowActions = defineComponent({
  props: {
    announcement: { type: Object, required: true },
  },
  setup(props) {
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
                      h(
                        PopoverClose,
                        { asChild: true },
                        {
                          default: () =>
                            h(
                              resolveComponent("NuxtLink"),
                              {
                                to: `/announcements/${props.announcement.id}/edit`,
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
                      ),

                      h(
                        PopoverClose,
                        { asChild: true },
                        {
                          default: () =>
                            h(
                              resolveComponent("NuxtLink"),
                              {
                                to: `/announcements/${props.announcement.id}/show`,
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
                  { class: "text-primary text-lg font-semibold tracking-tight" },
                  "Are you sure?"
                ),
                h(
                  "p",
                  { class: "text-body mt-1.5 text-sm tracking-tight" },
                  "This action can't be undone. This will permanently delete this announcement."
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
                          await handleDeleteSingleRow(props.announcement.id);
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
</script>
