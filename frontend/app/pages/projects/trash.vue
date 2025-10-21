<template>
  <div class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-center justify-between gap-x-2.5">
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:delete-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Project Trash</h1>
      </div>

      <nuxt-link
        to="/projects"
        class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
      >
        <Icon name="hugeicons:layers-01" class="size-4 shrink-0" />
        <span>All projects</span>
      </nuxt-link>
    </div>

    <TableData
      :clientOnly="clientOnly"
      ref="tableRef"
      :data="data"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="projects-trash"
      search-column="name"
      :show-add-button="false"
      search-placeholder="Search name, email, or username"
      error-title="Error loading trashed projects"
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
              <Icon name="lucide:list-filter" class="size-4 shrink-0" />
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
              <FilterSection
                title="Status"
                :options="['active', 'draft']"
                :selected="selectedStatuses"
                @change="handleFilterChange('status', $event)"
              />
            </div>
          </PopoverContent>
        </Popover>
      </template>

      <template #actions="{ selectedRows }">
        <DialogResponsive
          v-if="selectedRows.length > 0"
          v-model:open="restoreDialogOpen"
          class="h-full"
        >
          <template #trigger="{ open }">
            <button
              class="hover:bg-muted flex h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border px-2.5 text-sm tracking-tight active:scale-98"
              @click="open()"
            >
              <Icon name="hugeicons:undo-02" class="size-4 shrink-0" />
              <span class="text-sm tracking-tight">Restore</span>
              <span
                class="text-muted-foreground/80 -me-1 inline-flex h-5 max-h-full items-center rounded border px-1 font-[inherit] text-[0.625rem] font-medium"
              >
                {{ selectedRows.length }}
              </span>
            </button>
          </template>
          <template #default>
            <div class="px-4 pb-10 md:px-6 md:py-5">
              <div class="text-primary text-lg font-semibold tracking-tight">Restore projects?</div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This will restore {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "project" : "projects" }}.
              </p>
              <div class="mt-3 flex justify-end gap-2">
                <button
                  class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                  @click="restoreDialogOpen = false"
                >
                  Cancel
                </button>
                <button
                  @click="handleRestoreRows(selectedRows)"
                  class="bg-primary text-primary-foreground hover:bg-primary/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                >
                  Restore
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
            <button
              class="hover:bg-muted flex h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border px-2.5 text-sm tracking-tight active:scale-98"
              @click="open()"
            >
              <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
              <span class="text-sm tracking-tight">Delete Permanently</span>
              <span
                class="text-muted-foreground/80 -me-1 inline-flex h-5 max-h-full items-center rounded border px-1 font-[inherit] text-[0.625rem] font-medium"
              >
                {{ selectedRows.length }}
              </span>
            </button>
          </template>
          <template #default>
            <div class="px-4 pb-10 md:px-6 md:py-5">
              <div class="text-primary text-lg font-semibold tracking-tight">
                Are you absolutely sure?
              </div>
              <p class="text-body mt-1.5 text-sm tracking-tight">
                This action can't be undone. This will permanently delete
                {{ selectedRows.length }} selected
                {{ selectedRows.length === 1 ? "project" : "projects" }}.
              </p>
              <div class="mt-3 flex justify-end gap-2">
                <button
                  class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                  @click="deleteDialogOpen = false"
                >
                  Cancel
                </button>
                <button
                  @click="handleDeleteRows(selectedRows)"
                  class="bg-destructive text-destructive-foreground hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                >
                  Delete Permanently
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
import DialogResponsive from "@/components/DialogResponsive.vue";
import TableData from "@/components/TableData.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { PopoverClose } from "reka-ui";
import { resolveDirective, withDirectives } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "admin-master"],
  layout: "app",
});

defineOptions({
  name: "projects-trash",
});

usePageMeta("projectTrash");

const { $dayjs } = useNuxtApp();

// Table state
const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 10 });
const sorting = ref([{ id: "deleted_at", desc: true }]);

// Data state
const data = ref([]);
const meta = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0 });
const pending = ref(false);
const error = ref(null);

// Client-only mode flag (true = client-side pagination, false = server-side)
const clientOnly = ref(true);

// Build query params
const buildQueryParams = () => {
  const params = new URLSearchParams();

  if (clientOnly.value) {
    params.append("client_only", "true");
  } else {
    // Server-side mode: add pagination, filters, and sorting
    params.append("page", pagination.value.pageIndex + 1);
    params.append("per_page", pagination.value.pageSize);

    // Filters
    const filters = {
      name: "filter.search",
      status: "filter.status",
    };

    Object.entries(filters).forEach(([columnId, paramKey]) => {
      const filter = columnFilters.value.find((f) => f.id === columnId);
      if (filter?.value) {
        const value = Array.isArray(filter.value) ? filter.value.join(",") : filter.value;
        params.append(paramKey, value);
      }
    });

    // Sorting
    const sortField = sorting.value[0]?.id || "deleted_at";
    const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
    params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);
  }

  return params.toString();
};

// Fetch trashed projects
const fetchprojects = async () => {
  try {
    pending.value = true;
    error.value = null;
    const client = useSanctumClient();
    const response = await client(`/api/projects/trash?${buildQueryParams()}`);
    data.value = response.data;
    meta.value = response.meta;
  } catch (err) {
    error.value = err;
    console.error("Failed to fetch trashed projects:", err);
  } finally {
    pending.value = false;
  }
};

await fetchprojects();

// Watchers for server-side mode only
const debouncedFetch = useDebounceFn(fetchprojects, 300);

watch(
  [columnFilters, sorting, pagination],
  () => {
    if (!clientOnly.value) {
      const hasNameFilter = columnFilters.value.some((f) => f.id === "name");
      hasNameFilter ? debouncedFetch() : fetchprojects();
    }
  },
  { deep: true }
);

const refresh = fetchprojects;

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
  //   {
  //     header: "Name",
  //     accessorKey: "name",
  //     cell: ({ row }) => h(AuthprojectInfo, { project: row.original }),
  //     size: 280,
  //     enableHiding: false,
  //     filterFn: (row, columnId, filterValue) => {
  //       const searchValue = filterValue.toLowerCase();
  //       const name = row.original.name?.toLowerCase() || "";
  //       const email = row.original.email?.toLowerCase() || "";
  //       const username = row.original.username?.toLowerCase() || "";
  //       return (
  //         name.includes(searchValue) || email.includes(searchValue) || username.includes(searchValue)
  //       );
  //     },
  //   },
  {
    header: "Status",
    accessorKey: "status",
    cell: ({ row }) => {
      const status = row.getValue("status");
      const statusColors = {
        active: "bg-success",
        inactive: "bg-destructive",
        draft: "bg-primary/50",
        pending: "bg-warning",
      };
      return h("div", { class: "flex items-center gap-x-1.5 capitalize text-sm tracking-tight" }, [
        h("span", { class: ["rounded-full size-2", statusColors[status.toLowerCase()]] }),
        status,
      ]);
    },
    size: 80,
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      const status = row.getValue(columnId);
      return filterValue.includes(status);
    },
  },
  {
    header: "Deleted By",
    accessorKey: "deleter",
    cell: ({ row }) => {
      const deleter = row.getValue("deleter");
      if (!deleter) {
        return h("div", { class: "text-sm text-muted-foreground tracking-tight" }, "-");
      }
      return h("div", { class: "text-sm tracking-tight" }, deleter.name);
    },
    size: 120,
  },
  {
    header: "Deleted At",
    accessorKey: "deleted_at",
    cell: ({ row }) => {
      const date = row.getValue("deleted_at");
      return withDirectives(
        h("div", { class: "text-sm text-muted-foreground tracking-tight" }, $dayjs(date).fromNow()),
        [[resolveDirective("tippy"), $dayjs(date).format("MMMM D, YYYY [at] h:mm A")]]
      );
    },
    size: 100,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) => h(RowActions, { projectId: row.original.id }),
    size: 60,
    enableHiding: false,
  },
];

// Table ref
const tableRef = ref();

// Filter helpers - handle both client and server mode
const getFilterValue = (columnId) => {
  if (clientOnly.value && tableRef.value?.table) {
    return tableRef.value.table.getColumn(columnId)?.getFilterValue() ?? [];
  }
  return columnFilters.value.find((f) => f.id === columnId)?.value ?? [];
};

const selectedStatuses = computed(() => getFilterValue("status"));
const totalActiveFilters = computed(() => selectedStatuses.value.length);

const handleFilterChange = (columnId, { checked, value }) => {
  if (clientOnly.value && tableRef.value?.table) {
    // Client-side mode: use table instance
    const column = tableRef.value.table.getColumn(columnId);
    if (!column) return;

    const current = column.getFilterValue() ?? [];
    const updated = checked ? [...current, value] : current.filter((item) => item !== value);

    column.setFilterValue(updated.length > 0 ? updated : undefined);
    // Reset to first page when filter changes
    tableRef.value.table.setPageIndex(0);
  } else {
    // Server-side mode: update columnFilters ref
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
    // Reset to first page when filter changes (server-side)
    pagination.value.pageIndex = 0;
  }
};

// Restore handlers
const restoreDialogOpen = ref(false);
const handleRestoreRows = async (selectedRows) => {
  const projectIds = selectedRows.map((row) => row.original.id);
  try {
    const client = useSanctumClient();
    const response = await client("/api/projects/trash/restore/bulk", {
      method: "POST",
      body: { ids: projectIds },
    });
    await refresh();
    restoreDialogOpen.value = false;
    if (tableRef.value?.table) {
      tableRef.value.table.resetRowSelection();
    }

    // Show success toast
    toast.success(response.message || "projects restored successfully", {
      description:
        response.errors?.length > 0
          ? `${response.restored_count} restored, ${response.errors.length} failed`
          : `${response.restored_count} project(s) restored`,
    });
  } catch (error) {
    console.error("Failed to restore projects:", error);
    toast.error("Failed to restore projects", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  }
};

const handleRestoreSingleRow = async (projectId) => {
  try {
    const client = useSanctumClient();
    const response = await client(`/api/projects/trash/${projectId}/restore`, { method: "POST" });
    await refresh();

    // Show success toast
    toast.success(response.message || "project restored successfully");
  } catch (error) {
    console.error("Failed to restore project:", error);
    toast.error("Failed to restore project", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  }
};

// Delete handlers
const deleteDialogOpen = ref(false);
const handleDeleteRows = async (selectedRows) => {
  const projectIds = selectedRows.map((row) => row.original.id);
  try {
    const client = useSanctumClient();
    const response = await client("/api/projects/trash/bulk", {
      method: "DELETE",
      body: { ids: projectIds },
    });
    await refresh();
    deleteDialogOpen.value = false;
    if (tableRef.value?.table) {
      tableRef.value.table.resetRowSelection();
    }

    // Show success toast
    toast.success(response.message || "projects permanently deleted", {
      description:
        response.errors?.length > 0
          ? `${response.deleted_count} deleted, ${response.errors.length} failed`
          : `${response.deleted_count} project(s) permanently deleted`,
    });
  } catch (error) {
    console.error("Failed to permanently delete projects:", error);
    toast.error("Failed to permanently delete projects", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  }
};

const handleDeleteSingleRow = async (projectId) => {
  try {
    const client = useSanctumClient();
    const response = await client(`/api/projects/trash/${projectId}`, { method: "DELETE" });
    await refresh();

    // Show success toast
    toast.success(response.message || "project permanently deleted");
  } catch (error) {
    console.error("Failed to permanently delete project:", error);
    toast.error("Failed to permanently delete project", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  }
};

// Row Actions Component
const RowActions = defineComponent({
  props: {
    projectId: { type: Number, required: true },
  },
  setup(props) {
    const restoreDialogOpen = ref(false);
    const deleteDialogOpen = ref(false);
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
                  "Restore project?"
                ),
                h(
                  "p",
                  { class: "text-body mt-1.5 text-sm tracking-tight" },
                  "This will restore this project."
                ),
                h("div", { class: "mt-3 flex justify-end gap-2" }, [
                  h(
                    "button",
                    {
                      class:
                        "border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98",
                      onClick: () => (restoreDialogOpen.value = false),
                    },
                    "Cancel"
                  ),
                  h(
                    "button",
                    {
                      class:
                        "bg-primary text-primary-foreground hover:bg-primary/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98",
                      onClick: async () => {
                        await handleRestoreSingleRow(props.projectId);
                        restoreDialogOpen.value = false;
                      },
                    },
                    "Restore"
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
                  "This action can't be undone. This will permanently delete this project."
                ),
                h("div", { class: "mt-3 flex justify-end gap-2" }, [
                  h(
                    "button",
                    {
                      class:
                        "border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98",
                      onClick: () => (deleteDialogOpen.value = false),
                    },
                    "Cancel"
                  ),
                  h(
                    "button",
                    {
                      class:
                        "bg-destructive text-destructive-foreground hover:bg-destructive/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98",
                      onClick: async () => {
                        await handleDeleteSingleRow(props.projectId);
                        deleteDialogOpen.value = false;
                      },
                    },
                    "Delete Permanently"
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
