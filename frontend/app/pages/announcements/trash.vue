<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:delete-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Announcements Trash</h1>
      </div>

      <div v-if="!hasSelectedRows" class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <NuxtLink
          to="/announcements"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:notification-02" class="size-4 shrink-0" />
          <span>All Announcements</span>
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
      model="announcements-trash"
      label="Announcement"
      search-column="title"
      :show-add-button="false"
      search-placeholder="Search announcements..."
      error-title="Error loading trashed announcements"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
      @update:pagination="pagination = $event"
      @update:sorting="sorting = $event"
      @update:column-filters="columnFilters = $event"
      @refresh="refresh"
    >
      <template #actions="{ selectedRows }">
        <DialogResponsive
          v-if="selectedRows.length > 0"
          v-model:open="restoreDialogOpen"
          class="h-full"
        >
          <template #trigger="{ open }">
            <TableBulkAction icon="hugeicons:undo-02" label="Restore" @click="open()" />
          </template>
          <template #default>
            <div class="px-4 pb-10 md:px-6 md:py-5">
              <div class="text-primary text-lg font-semibold tracking-tight">
                Restore announcements?
              </div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This will restore {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "announcement" : "announcements" }}.
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
            <TableBulkAction
              icon="hugeicons:delete-01"
              label="Delete Permanently"
              destructive
              @click="open()"
            />
          </template>
          <template #default>
            <div class="px-4 pb-10 md:px-6 md:py-5">
              <div class="text-primary text-lg font-semibold tracking-tight">
                Are you absolutely sure?
              </div>
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
                  <span v-else>Delete Permanently</span>
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
import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import { DialogResponsive } from "@/components/ui/dialog-responsive";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { TableData, TableBulkAction } from "@/components/ui/table-data";
import { PopoverClose } from "reka-ui";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["announcements.delete"],
  layout: "app",
});

defineOptions({
  name: "announcements-trash",
});

usePageMeta(null, {
  title: "Announcements Trash",
});

// Table state
const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 10 });
const sorting = ref([{ id: "deleted_at", desc: true }]);

// Client-only mode flag
const clientOnly = ref(true);

// Build query params
const buildQueryParams = () => {
  const params = new URLSearchParams();

  if (clientOnly.value) {
    params.append("client_only", "true");
  } else {
    params.append("page", pagination.value.pageIndex + 1);
    params.append("per_page", pagination.value.pageSize);

    const filters = {
      title: "filter_search",
    };

    Object.entries(filters).forEach(([columnId, paramKey]) => {
      const filter = columnFilters.value.find((f) => f.id === columnId);
      if (filter?.value) {
        const value = Array.isArray(filter.value) ? filter.value.join(",") : filter.value;
        params.append(paramKey, value);
      }
    });

    const sortField = sorting.value[0]?.id || "deleted_at";
    const sortDir = sorting.value[0]?.desc ? "desc" : "asc";
    params.append("sort_by", sortField);
    params.append("sort_dir", sortDir);
  }

  return params.toString();
};

// Fetch trashed announcements with lazy loading
const {
  data: response,
  pending,
  error,
  refresh: fetchAnnouncements,
} = await useLazySanctumFetch(() => `/api/announcements/trash?${buildQueryParams()}`, {
  key: "announcements-trash-list",
  watch: false,
});

const data = computed(() => response.value?.data || []);
const meta = computed(
  () => response.value?.meta || { current_page: 1, last_page: 1, per_page: 10, total: 0 }
);

const refresh = fetchAnnouncements;

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

const formatDeletedAt = (value) =>
  value
    ? new Intl.DateTimeFormat("en-US", {
        month: "short",
        day: "numeric",
        year: "numeric",
        hour: "numeric",
        minute: "2-digit",
      }).format(new Date(value))
    : "-";

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
    header: "Announcement",
    accessorKey: "title",
    cell: ({ row }) =>
      h(
        resolveComponent("NuxtLink"),
        {
          to: `/announcements/${row.original.id}/show`,
          class: "line-clamp-2 text-sm font-medium tracking-tight hover:underline",
        },
        { default: () => row.original.title }
      ),
    size: 320,
    enableHiding: false,
    filterFn: (row, columnId, filterValue) => {
      const searchValue = filterValue.toLowerCase();
      const title = row.original.title?.toLowerCase() || "";
      return title.includes(searchValue);
    },
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
    header: "Deleted",
    accessorKey: "deleted_at",
    cell: ({ row }) =>
      h(
        "span",
        { class: "text-muted-foreground text-sm tracking-tight" },
        formatDeletedAt(row.original.deleted_at)
      ),
    size: 160,
    enableSorting: true,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) => h(RowActions, { announcementId: row.original.id }),
    size: 60,
    enableHiding: false,
  },
];

// Table ref
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

// Restore handlers
const restoreDialogOpen = ref(false);
const restorePending = ref(false);
const handleRestoreRows = async (selectedRows) => {
  const announcementIds = selectedRows.map((row) => row.original.id);
  try {
    restorePending.value = true;
    const client = useSanctumClient();
    const response = await client("/api/announcements/trash/restore/bulk", {
      method: "POST",
      body: { ids: announcementIds },
    });
    await refresh();
    restoreDialogOpen.value = false;
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }

    toast.success(response.message || "Announcements restored successfully");
  } catch (error) {
    console.error("Failed to restore announcements:", error);
    toast.error("Failed to restore announcements", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    restorePending.value = false;
  }
};

const handleRestoreSingleRow = async (announcementId) => {
  try {
    restorePending.value = true;
    const client = useSanctumClient();
    const response = await client(`/api/announcements/trash/${announcementId}/restore`, {
      method: "POST",
    });
    await refresh();

    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }

    toast.success(response.message || "Announcement restored successfully");
  } catch (error) {
    console.error("Failed to restore announcement:", error);
    toast.error("Failed to restore announcement", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    restorePending.value = false;
  }
};

// Delete handlers
const deleteDialogOpen = ref(false);
const deletePending = ref(false);
const handleDeleteRows = async (selectedRows) => {
  const announcementIds = selectedRows.map((row) => row.original.id);
  try {
    deletePending.value = true;
    const client = useSanctumClient();
    const response = await client("/api/announcements/trash/bulk", {
      method: "DELETE",
      body: { ids: announcementIds },
    });
    await refresh();
    deleteDialogOpen.value = false;
    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }

    toast.success(response.message || "Announcements permanently deleted");
  } catch (error) {
    console.error("Failed to permanently delete announcements:", error);
    toast.error("Failed to permanently delete announcements", {
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
    const response = await client(`/api/announcements/trash/${announcementId}`, {
      method: "DELETE",
    });
    await refresh();

    if (tableRef.value) {
      tableRef.value.resetRowSelection();
    }

    toast.success(response.message || "Announcement permanently deleted");
  } catch (error) {
    console.error("Failed to permanently delete announcement:", error);
    toast.error("Failed to permanently delete announcement", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    deletePending.value = false;
  }
};

// Row Actions Component
const RowActions = defineComponent({
  props: {
    announcementId: { type: Number, required: true },
  },
  setup(props) {
    const restoreDialogOpen = ref(false);
    const deleteDialogOpen = ref(false);
    const singleRestorePending = ref(false);
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
                                to: `/announcements/${props.announcementId}/show`,
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
                      h(
                        PopoverClose,
                        { asChild: true },
                        {
                          default: () =>
                            h(
                              "button",
                              {
                                class:
                                  "hover:bg-muted rounded-md px-3 py-2 text-left text-sm tracking-tight flex items-center gap-x-1.5",
                                onClick: () => (restoreDialogOpen.value = true),
                              },
                              [
                                h(resolveComponent("Icon"), {
                                  name: "lucide:undo-2",
                                  class: "size-4 shrink-0",
                                }),
                                h("span", {}, "Restore"),
                              ]
                            ),
                        }
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
                                  "hover:bg-destructive/10 text-destructive rounded-md px-3 py-2 text-left text-sm tracking-tight flex items-center gap-x-1.5",
                                onClick: () => (deleteDialogOpen.value = true),
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
                      ),
                    ]),
                }
              ),
            ],
          }
        ),
        h(
          DialogResponsive,
          {
            open: restoreDialogOpen.value,
            "onUpdate:open": (value) => (restoreDialogOpen.value = value),
          },
          {
            default: () =>
              h("div", { class: "px-4 pb-10 md:px-6 md:py-5" }, [
                h(
                  "div",
                  { class: "text-primary text-lg font-semibold tracking-tight" },
                  "Restore announcement?"
                ),
                h(
                  "p",
                  { class: "text-body mt-1.5 text-sm tracking-tight" },
                  "This will restore this announcement."
                ),
                h("div", { class: "mt-3 flex justify-end gap-2" }, [
                  h(
                    "button",
                    {
                      class:
                        "border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98",
                      onClick: () => (restoreDialogOpen.value = false),
                      disabled: singleRestorePending.value,
                    },
                    "Cancel"
                  ),
                  h(
                    "button",
                    {
                      class:
                        "bg-primary text-primary-foreground hover:bg-primary/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50",
                      disabled: singleRestorePending.value,
                      onClick: async () => {
                        singleRestorePending.value = true;
                        try {
                          await handleRestoreSingleRow(props.announcementId);
                          restoreDialogOpen.value = false;
                        } finally {
                          singleRestorePending.value = false;
                        }
                      },
                    },
                    singleRestorePending.value
                      ? h(resolveComponent("Spinner"), { class: "size-4 text-white" })
                      : "Restore"
                  ),
                ]),
              ]),
          }
        ),
        h(
          DialogResponsive,
          {
            open: deleteDialogOpen.value,
            "onUpdate:open": (value) => (deleteDialogOpen.value = value),
          },
          {
            default: () =>
              h("div", { class: "px-4 pb-10 md:px-6 md:py-5" }, [
                h(
                  "div",
                  { class: "text-primary text-lg font-semibold tracking-tight" },
                  "Are you absolutely sure?"
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
                      onClick: () => (deleteDialogOpen.value = false),
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
                          await handleDeleteSingleRow(props.announcementId);
                          deleteDialogOpen.value = false;
                        } finally {
                          singleDeletePending.value = false;
                        }
                      },
                    },
                    singleDeletePending.value
                      ? h(resolveComponent("Spinner"), { class: "size-4 text-white" })
                      : "Delete Permanently"
                  ),
                ]),
              ]),
          }
        ),
      ]);
  },
});
</script>
