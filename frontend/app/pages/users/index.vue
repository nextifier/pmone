<template>
  <div class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-center gap-x-2.5">
      <Icon name="hugeicons:user-group" class="size-5 sm:size-6" />
      <h1 class="page-title">User Management</h1>
    </div>

    <div v-if="error" class="flex flex-col items-start gap-y-3 rounded-lg">
      <div class="text-destructive flex items-center gap-x-2">
        <Icon name="hugeicons:alert-circle" class="size-5" />
        <span class="font-medium tracking-tight">Error loading users</span>
      </div>
      <p class="text-sm tracking-tight">
        {{ error?.message || "An error occurred while fetching users." }}
      </p>
      <button
        class="border-border hover:bg-primary hover:text-primary-foreground flex items-center gap-x-1.5 rounded-lg border px-3 py-2 text-sm tracking-tight active:scale-98"
        @click="refresh"
      >
        <Icon name="lucide:refresh-cw" class="size-4 shrink-0" />
        <span>Try again</span>
      </button>
    </div>

    <div v-else class="grid gap-y-4">
      <div class="flex flex-col gap-y-3">
        <div class="flex h-9 gap-x-1 sm:gap-x-2">
          <div class="relative h-full grow">
            <input
              ref="searchInputEl"
              type="text"
              class="input-base peer h-full px-9 py-1 text-sm tracking-tight"
              :value="table.getColumn('name')?.getFilterValue() ?? ''"
              @input="(event) => table.getColumn('name')?.setFilterValue(event.target.value)"
              placeholder="Search name, email, or username"
            />

            <Icon
              name="lucide:search"
              class="text-muted-foreground/80 absolute top-1/2 left-3 size-4 -translate-y-1/2 peer-disabled:opacity-50"
            />

            <span
              id="shortcut-key"
              class="pointer-events-none absolute top-1/2 right-3 hidden -translate-y-1/2 items-center justify-center gap-x-0.5 peer-placeholder-shown:flex peer-focus-within:hidden"
            >
              <kbd class="keyboard-symbol">{{ metaSymbol }} K</kbd>
            </span>

            <button
              v-if="Boolean(table.getColumn('name')?.getFilterValue())"
              class="bg-muted hover:bg-border absolute top-1/2 right-3 flex size-6 -translate-y-1/2 items-center justify-center rounded-full peer-placeholder-shown:hidden"
              aria-label="Clear filter"
              @click="
                () => {
                  table.getColumn('name')?.setFilterValue('');
                }
              "
            >
              <Icon name="lucide:x" class="size-3 shrink-0" />
            </button>
          </div>

          <Popover>
            <PopoverTrigger asChild>
              <button
                class="hover:bg-muted relative flex aspect-square h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border text-sm tracking-tight active:scale-98 sm:aspect-auto sm:px-2.5"
              >
                <Icon name="lucide:list-filter" class="size-4 shrink-0" :size="16" />
                <span class="hidden sm:flex">Filter</span>
                <span
                  v-if="selectedStatuses.length > 0"
                  class="bg-primary text-primary-foreground squircle absolute top-0 right-0 inline-flex size-4 translate-x-1/2 -translate-y-1/2 items-center justify-center text-[11px] font-medium tracking-tight"
                >
                  {{ selectedStatuses.length }}
                </span>
              </button>
            </PopoverTrigger>
            <PopoverContent class="w-auto min-w-36 p-3" align="start">
              <div class="space-y-3">
                <div class="text-muted-foreground text-xs font-medium">Status</div>
                <div class="space-y-3">
                  <div
                    v-for="(value, i) in uniqueStatusValues"
                    :key="value"
                    class="flex items-center gap-2"
                  >
                    <Checkbox
                      :id="`status-${i}`"
                      :model-value="selectedStatuses.includes(value)"
                      @update:model-value="(checked) => handleStatusChange(!!checked, value)"
                    />
                    <Label
                      :for="`status-${i}`"
                      class="flex grow justify-between gap-2 font-normal tracking-tight capitalize"
                    >
                      {{ value }}
                      <span class="text-muted-foreground ms-2 text-xs">
                        {{ statusCounts.get(value) }}
                      </span>
                    </Label>
                  </div>
                </div>
              </div>
            </PopoverContent>
          </Popover>

          <Popover>
            <PopoverTrigger asChild>
              <button
                class="hover:bg-muted flex aspect-square h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border text-sm tracking-tight active:scale-98 sm:aspect-auto sm:px-2.5"
              >
                <Icon name="lucide:columns-3" class="size-4 shrink-0" />
                <span class="hidden sm:flex">Columns</span>
              </button>
            </PopoverTrigger>
            <PopoverContent class="w-auto min-w-36 p-3" align="end">
              <div class="space-y-3">
                <div class="text-muted-foreground text-xs font-medium">Toggle columns</div>
                <div class="space-y-3">
                  <div
                    v-for="column in table.getAllColumns().filter((column) => column.getCanHide())"
                    :key="column.id"
                    class="flex items-center gap-2"
                  >
                    <Checkbox
                      :id="`column-${column.id}`"
                      :model-value="column.getIsVisible()"
                      @update:model-value="(value) => column.toggleVisibility(!!value)"
                    />
                    <Label
                      :for="`column-${column.id}`"
                      class="grow font-normal tracking-tight capitalize"
                    >
                      {{ column.id }}
                    </Label>
                  </div>
                </div>
              </div>
            </PopoverContent>
          </Popover>
        </div>

        <div class="flex h-9 w-full items-center justify-between gap-x-1 sm:gap-x-2">
          <DialogResponsive
            v-if="table.getSelectedRowModel().rows.length > 0"
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
                  {{ table.getSelectedRowModel().rows.length }}
                </span>
              </button>
            </template>

            <template #default="{ data }">
              <div class="px-4 pb-6 md:px-6 md:py-5">
                <div class="text-primary text-lg font-semibold tracking-tight">Are you sure?</div>
                <p class="text-body mt-1.5 text-sm tracking-tight">
                  This action can't be undone. This will permanently delete
                  {{ table.getSelectedRowModel().rows.length }} selected
                  {{ table.getSelectedRowModel().rows.length === 1 ? "row" : "rows" }}.
                </p>

                <div class="mt-3 flex justify-end gap-2">
                  <button
                    class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                    @click="deleteDialogOpen = false"
                  >
                    Cancel
                  </button>

                  <button
                    @click="handleDeleteRows"
                    class="bg-primary text-primary-foreground hover:bg-primary/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                  >
                    Delete
                  </button>
                </div>
              </div>
            </template>
          </DialogResponsive>

          <div class="ml-auto flex h-full gap-x-1 sm:gap-x-2">
            <button
              v-if="table.getState().columnFilters.length > 0"
              class="hover:bg-muted flex aspect-square h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border text-sm tracking-tight active:scale-98 sm:aspect-auto sm:px-2.5"
              @click="table.resetColumnFilters()"
            >
              <Icon name="lucide:x" class="size-4 shrink-0" />
              <span class="hidden sm:flex">Clear filters</span>
            </button>

            <button
              class="hover:bg-muted flex aspect-square h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border text-sm tracking-tight active:scale-98 sm:aspect-auto sm:px-2.5"
              @click="refresh"
            >
              <Icon
                name="lucide:refresh-cw"
                class="size-4 shrink-0"
                :class="pending ? 'animate-spin' : ''"
              />
              <span class="hidden sm:flex">Refresh</span>
            </button>

            <NuxtLink
              to="/users/create"
              class="hover:bg-muted flex items-center gap-x-1.5 rounded-md border px-3 py-1.5 text-sm tracking-tight active:scale-98"
            >
              <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
              <span>Add user</span>
            </NuxtLink>
          </div>
        </div>
      </div>

      <div class="overflow-hidden rounded-md border">
        <Table class="table-fixed">
          <TableHeader>
            <TableRow
              v-for="headerGroup in table.getHeaderGroups()"
              :key="headerGroup.id"
              class="hover:bg-transparent"
            >
              <TableHead
                v-for="header in headerGroup.headers"
                :key="header.id"
                :style="{ width: `${header.getSize()}px` }"
                class="h-11"
              >
                <template v-if="!header.isPlaceholder">
                  <div
                    v-if="header.column.getCanSort()"
                    class="flex h-full cursor-pointer items-center justify-between gap-2 select-none"
                    @click="header.column.getToggleSortingHandler()?.($event)"
                    @keydown="
                      (e) => {
                        if (header.column.getCanSort() && (e.key === 'Enter' || e.key === ' ')) {
                          e.preventDefault();
                          header.column.getToggleSortingHandler()?.(e);
                        }
                      }
                    "
                    tabindex="0"
                  >
                    <FlexRender
                      :render="header.column.columnDef.header"
                      :props="header.getContext()"
                    />
                    <Icon
                      v-if="header.column.getIsSorted() === 'asc'"
                      name="lucide:chevron-up"
                      class="size-4 shrink-0 opacity-60"
                    />
                    <Icon
                      v-else-if="header.column.getIsSorted() === 'desc'"
                      name="lucide:chevron-down"
                      class="size-4 shrink-0 opacity-60"
                    />
                  </div>
                  <FlexRender
                    v-else
                    :render="header.column.columnDef.header"
                    :props="header.getContext()"
                  />
                </template>
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody v-if="table.getRowModel().rows?.length">
            <TableRow
              v-for="row in table.getRowModel().rows"
              :key="row.id"
              :data-state="row.getIsSelected() && 'selected'"
            >
              <TableCell v-for="cell in row.getVisibleCells()" :key="cell.id" class="last:py-0">
                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>

        <div
          v-if="!table.getRowModel().rows?.length"
          class="mx-auto flex w-full max-w-md flex-col items-center gap-4 py-6 text-center"
        >
          <div
            class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
          >
            <div class="translate-y-1.5 -rotate-6">
              <Icon name="hugeicons:file-empty-01" />
            </div>
            <div>
              <Icon name="hugeicons:search-remove" />
            </div>
            <div class="translate-y-1.5 rotate-6">
              <Icon name="hugeicons:user" />
            </div>
          </div>
          <div class="flex flex-col gap-y-1.5">
            <h6 class="text-lg font-semibold tracking-tight">No data found</h6>
            <p class="text-muted-foreground text-sm">
              It looks like there's no data in this page. You can create a new one or clear the
              filters.
            </p>
          </div>
          <div class="flex items-center gap-2">
            <NuxtLink
              to="/users/create"
              class="hover:bg-primary/80 bg-primary text-primary-foreground flex items-center gap-x-1.5 rounded-lg border px-3 py-2 text-sm font-medium tracking-tight active:scale-98"
            >
              <Icon name="lucide:plus" class="size-4 shrink-0" />
              <span>Create new</span>
            </NuxtLink>
            <button
              class="border-border hover:bg-muted text-primary flex items-center gap-x-1.5 rounded-lg border px-3 py-2 text-sm font-medium tracking-tight active:scale-98"
              @click="table.resetColumnFilters()"
            >
              <Icon name="lucide:x" class="size-4 shrink-0" />
              <span>Clear filters</span>
            </button>
          </div>
        </div>
      </div>

      <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-2">
          <Label class="max-sm:sr-only">Rows per page</Label>
          <Select
            :model-value="table.getState().pagination.pageSize.toString()"
            @update:model-value="(value) => table.setPageSize(Number(value))"
          >
            <SelectTrigger class="w-fit whitespace-nowrap" size="sm">
              <SelectValue />
            </SelectTrigger>
            <SelectContent
              class="[&_*[role=option]]:ps-2 [&_*[role=option]]:pe-8 [&_*[role=option]>span]:start-auto [&_*[role=option]>span]:end-2"
            >
              <SelectItem
                v-for="pageSize in [10, 20, 50, 100]"
                :key="pageSize"
                :value="pageSize.toString()"
              >
                {{ pageSize }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="flex grow items-center justify-end gap-x-2.5 whitespace-nowrap sm:gap-x-3">
          <div
            v-if="pending"
            class="size-4 animate-spin rounded-full border border-current border-r-transparent"
          ></div>

          <p class="text-muted-foreground text-xs whitespace-nowrap sm:text-sm">
            <span class="text-foreground">
              {{ (meta.current_page - 1) * meta.per_page + 1 }}-{{
                Math.min(meta.current_page * meta.per_page, meta.total)
              }}
            </span>
            of
            <span class="text-foreground">
              {{ meta.total }}
            </span>
          </p>

          <div>
            <Pagination
              :default-page="meta.current_page"
              :items-per-page="meta.per_page"
              :total="meta.total"
            >
              <PaginationContent>
                <PaginationFirst asChild>
                  <button
                    class="hover:bg-muted bg-background border-border flex size-8 shrink-0 items-center justify-center rounded-md border active:scale-98"
                    @click="table.firstPage"
                    :disabled="meta.current_page <= 1"
                  >
                    <Icon name="lucide:chevron-first" class="size-4 shrink-0" />
                  </button>
                </PaginationFirst>
                <PaginationPrevious asChild>
                  <button
                    class="hover:bg-muted bg-background border-border flex size-8 shrink-0 items-center justify-center rounded-md border active:scale-98"
                    @click="table.previousPage"
                    :disabled="meta.current_page <= 1"
                  >
                    <Icon name="lucide:chevron-left" class="size-4 shrink-0" />
                  </button>
                </PaginationPrevious>
                <PaginationNext asChild>
                  <button
                    class="hover:bg-muted bg-background border-border flex size-8 shrink-0 items-center justify-center rounded-md border active:scale-98"
                    @click="table.nextPage"
                    :disabled="meta.current_page >= meta.last_page"
                  >
                    <Icon name="lucide:chevron-right" class="size-4 shrink-0" />
                  </button>
                </PaginationNext>
                <PaginationLast asChild>
                  <button
                    class="hover:bg-muted bg-background border-border flex size-8 shrink-0 items-center justify-center rounded-md border active:scale-98"
                    @click="() => table.setPageIndex(meta.last_page - 1)"
                    :disabled="meta.current_page >= meta.last_page"
                  >
                    <Icon name="lucide:chevron-last" class="size-4 shrink-0" />
                  </button>
                </PaginationLast>
              </PaginationContent>
            </Pagination>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import DialogResponsive from "@/components/DialogResponsive.vue";
import AuthUserInfo from "@/components/auth/UserInfo.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { valueUpdater } from "@/components/ui/table/utils";
import {
  FlexRender,
  getCoreRowModel,
  getFacetedUniqueValues,
  getFilteredRowModel,
  getSortedRowModel,
  useVueTable,
} from "@tanstack/vue-table";
import { PopoverClose } from "reka-ui";

definePageMeta({
  middleware: ["sanctum:auth", "staff-admin-master"],
  layout: "app",
});

defineOptions({
  name: "users",
});

usePageMeta("users");

const { $dayjs } = useNuxtApp();

// Custom filter function for multi-column searching
const multiColumnFilterFn = (row, _columnId, filterValue) => {
  const searchableRowContent =
    `${row.original.name || ""} ${row.original.email || ""} ${row.original.username || ""}`.toLowerCase();
  const searchTerm = (filterValue ?? "").toLowerCase();
  return searchableRowContent.includes(searchTerm);
};

const statusFilterFn = (row, _columnId, filterValue) => {
  if (!filterValue?.length) return true;
  const status = row.getValue("status");
  return filterValue.includes(status);
};

// Define pagination state first
const rowSelection = ref({});
const columnFilters = ref([]);
const columnVisibility = ref({});
const pagination = ref({
  pageIndex: 0,
  pageSize: 20,
});

// Reactive query parameters that will trigger refetch when changed
const queryParams = reactive({
  page: pagination.value.pageIndex + 1, // Laravel uses 1-based page indexing
  per_page: pagination.value.pageSize,
});

// Initial fetch with server-side pagination
const {
  data: response,
  pending,
  error,
  refresh,
} = await useSanctumFetch(
  "/api/users",
  {
    method: "GET",
    query: queryParams, // Use reactive query params
  },
  "fetch-users"
);

// Update pagination and refresh data
const updatePagination = async (pageIndex, pageSize) => {
  queryParams.page = pageIndex + 1;
  queryParams.per_page = pageSize;
  await refresh();
};

// Extract users data and meta from response
const data = computed(() => response.value?.data || []);
const meta = computed(
  () =>
    response.value?.meta || {
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: 0,
    }
);

const sorting = ref([
  {
    id: "name",
    desc: false,
  },
]);

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
    cell: ({ row }) => h(AuthUserInfo, { user: row.original }),
    size: 280,
    filterFn: multiColumnFilterFn,
    enableHiding: false,
    sortUndefined: "last",
    sortDescFirst: false,
  },
  {
    header: "Status",
    accessorKey: "status",
    cell: ({ row }) => {
      const status = row.getValue("status");
      return h(
        "div",
        { class: "text-sm tracking-tight capitalize" },
        status
      );
    },
    size: 100,
    filterFn: statusFilterFn,
    sortUndefined: "last",
    sortDescFirst: false,
  },
  {
    header: "Roles",
    accessorKey: "roles",
    cell: ({ row }) => {
      const roles = row.getValue("roles") || [];
      return h(
        "div",
        { class: "text-sm tracking-tight capitalize" },
        roles.join(", ")
      );
    },
    sortingFn: (rowA, rowB) => {
      const rolesA = rowA.getValue("roles") || [];
      const rolesB = rowB.getValue("roles") || [];
      return rolesA.join(", ").localeCompare(rolesB.join(", "));
    },
    size: 140,
    sortUndefined: "last",
    sortDescFirst: false,
  },
  {
    header: "Joined",
    accessorKey: "created_at",
    cell: ({ row }) => {
      const date = row.getValue("created_at");
      return h(
        "div",
        { class: "text-sm text-muted-foreground" },
        $dayjs(date).format("MMM D, YYYY")
      );
    },
    size: 100,
    sortUndefined: "last",
    sortDescFirst: false,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) => h(RowActions, { userId: row.original.id, username: row.original.username }),
    size: 60,
    enableHiding: false,
  },
];

const table = useVueTable({
  get data() {
    return data.value || [];
  },
  get columns() {
    return columns;
  },
  getCoreRowModel: getCoreRowModel(),
  getSortedRowModel: getSortedRowModel(),
  manualPagination: true, // Tell table that data is already paginated by server
  pageCount: -1, // We'll handle pagination manually with backend meta
  autoResetPageIndex: false, // Don't reset page index when data changes
  getFilteredRowModel: getFilteredRowModel(),
  getFacetedUniqueValues: getFacetedUniqueValues(),
  state: {
    get rowSelection() {
      return rowSelection.value;
    },
    get pagination() {
      return pagination.value;
    },
    get sorting() {
      return sorting.value;
    },
    get columnFilters() {
      return columnFilters.value;
    },
    get columnVisibility() {
      return columnVisibility.value;
    },
  },
  onSortingChange: (updater) => valueUpdater(updater, sorting),
  onPaginationChange: (updater) => {
    const newPagination = typeof updater === "function" ? updater(pagination.value) : updater;
    pagination.value = newPagination;
    updatePagination(newPagination.pageIndex, newPagination.pageSize);
  },
  onColumnFiltersChange: (updater) => valueUpdater(updater, columnFilters),
  onColumnVisibilityChange: (updater) => valueUpdater(updater, columnVisibility),
  onRowSelectionChange: (updater) => valueUpdater(updater, rowSelection),
  enableSortingRemoval: false,
});

// Status filter computed properties
const uniqueStatusValues = computed(() => {
  const statusColumn = table.getColumn("status");
  return statusColumn ? Array.from(statusColumn.getFacetedUniqueValues().keys()).sort() : [];
});

const statusCounts = computed(() => {
  const statusColumn = table.getColumn("status");
  return statusColumn ? statusColumn.getFacetedUniqueValues() : new Map();
});

const selectedStatuses = computed(() => table.getColumn("status")?.getFilterValue() ?? []);

const handleStatusChange = (checked, value) => {
  const current = table.getColumn("status")?.getFilterValue() ?? [];
  const updated = checked ? [...current, value] : current.filter((item) => item !== value);

  table.getColumn("status")?.setFilterValue(updated.length ? updated : undefined);
};

const deleteDialogOpen = ref(false);

const handleDeleteRows = async () => {
  const userIds = table.getSelectedRowModel().rows.map((row) => row.original.id);

  try {
    await Promise.all(
      userIds.map((id) => useSanctumFetch(`/api/users/${id}`, { method: "DELETE" }))
    );
    await refresh();
    table.resetRowSelection();
    deleteDialogOpen.value = false;
  } catch (error) {
    console.error("Failed to delete users:", error);
  }
};

const handleDeleteSingleRow = async (userId) => {
  try {
    await useSanctumFetch(`/api/users/${userId}`, { method: "DELETE" });
    await refresh();
  } catch (error) {
    console.error("Failed to delete user:", error);
  }
};

// RowActions component
const RowActions = defineComponent({
  props: {
    userId: {
      type: Number,
      required: true,
    },
    username: {
      type: String,
      required: true,
    },
  },
  setup(props) {
    const dialogOpen = ref(false);

    const onDeleteConfirm = async () => {
      await handleDeleteSingleRow(props.userId);
      dialogOpen.value = false;
    };

    const onDeleteClick = () => {
      dialogOpen.value = true;
    };

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
                                to: `/users/${props.username}/edit`,
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
                              "button",
                              {
                                class:
                                  "hover:bg-destructive/10 text-destructive rounded-md px-3 py-2 text-left text-sm tracking-tight flex items-center gap-x-1.5",
                                onClick: onDeleteClick,
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
            open: dialogOpen.value,
            "onUpdate:open": (value) => {
              dialogOpen.value = value;
            },
          },
          {
            default: () =>
              h("div", { class: "px-4 pb-6 md:px-6 md:py-5" }, [
                h(
                  "div",
                  { class: "text-primary text-lg font-semibold tracking-tight" },
                  "Are you sure?"
                ),
                h(
                  "p",
                  { class: "text-body mt-1.5 text-sm tracking-tight" },
                  "This action can't be undone. This will permanently delete this user."
                ),
                h("div", { class: "mt-3 flex justify-end gap-2" }, [
                  h(
                    "button",
                    {
                      class:
                        "border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98",
                      onClick: () => {
                        dialogOpen.value = false;
                      },
                    },
                    "Cancel"
                  ),
                  h(
                    "button",
                    {
                      class:
                        "bg-primary text-primary-foreground hover:bg-primary/80 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98",
                      onClick: onDeleteConfirm,
                    },
                    "Delete"
                  ),
                ]),
              ]),
          }
        ),
      ]);
  },
});

const searchInputEl = ref();
const { metaSymbol } = useShortcuts();
defineShortcuts({
  meta_k: {
    handler: async () => {
      searchInputEl.value?.focus();
    },
  },
});
</script>
