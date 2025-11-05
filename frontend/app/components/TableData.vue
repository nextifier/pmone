<template>
  <div class="space-y-4">
    <!-- Error State -->
    <div v-if="error" class="flex flex-col items-start gap-y-3 rounded-lg">
      <div class="text-destructive flex items-center gap-x-2">
        <Icon name="hugeicons:alert-circle" class="size-5" />
        <span class="font-medium tracking-tight">{{ errorTitle || "Error loading data" }}</span>
      </div>
      <p class="text-sm tracking-tight">
        {{ error?.message || "An error occurred while fetching data." }}
      </p>
    </div>

    <!-- Main Content -->
    <div v-else class="space-y-4">
      <!-- Toolbar -->
      <div class="space-y-3">
        <!-- Search and Filters -->
        <div class="flex h-9 w-full gap-x-1 sm:gap-x-2">
          <!-- Search Input -->
          <div v-if="searchable" class="relative flex h-full grow items-center">
            <Icon
              name="lucide:search"
              class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
            />
            <input
              ref="searchInputEl"
              type="text"
              :placeholder="searchPlaceholder || 'Search..'"
              class="peer placeholder:text-muted-foreground h-full w-full rounded-md border bg-transparent px-9 py-1.5 text-sm tracking-tight focus:outline-hidden"
              :value="searchValue"
              @input="handleSearchInput"
            />
            <span
              v-if="!searchValue"
              class="text-muted-foreground/60 pointer-events-none absolute top-1/2 right-3 hidden -translate-y-1/2 items-center gap-x-1 text-xs font-medium peer-placeholder-shown:flex"
            >
              <kbd class="keyboard-symbol">{{ metaSymbol }} K</kbd>
            </span>
            <button
              v-if="searchValue"
              class="bg-muted hover:bg-border absolute top-1/2 right-3 flex size-6 -translate-y-1/2 items-center justify-center rounded-full peer-placeholder-shown:hidden"
              aria-label="Clear search"
              @click="clearSearch"
            >
              <Icon name="lucide:x" class="size-3 shrink-0" />
            </button>
          </div>

          <!-- Filters Slot -->
          <slot name="filters" :table="table" />

          <!-- Column Toggle -->
          <Popover v-if="columnToggle">
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
                      :id="column.id"
                      :model-value="column.getIsVisible()"
                      @update:model-value="(value) => column.toggleVisibility(!!value)"
                    />
                    <Label
                      :for="column.id"
                      class="grow cursor-pointer font-normal tracking-tight capitalize"
                    >
                      {{ column.columnDef.header }}
                    </Label>
                  </div>
                </div>
              </div>
            </PopoverContent>
          </Popover>
        </div>

        <!-- Action Buttons -->
        <div class="flex h-9 w-full items-center justify-between gap-x-1 sm:gap-x-2">
          <!-- Actions Slot (for bulk actions like delete) -->
          <slot name="actions" :table="table" :selected-rows="table.getSelectedRowModel().rows" />

          <div class="ml-auto flex h-full gap-x-1 sm:gap-x-2">
            <!-- Clear Filters Button -->
            <button
              v-if="hasActiveFilters"
              class="hover:bg-muted flex aspect-square h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border text-sm tracking-tight active:scale-98 sm:aspect-auto sm:px-2.5"
              @click="table.resetColumnFilters()"
            >
              <Icon name="lucide:x" class="size-4 shrink-0" />
              <span class="hidden sm:flex">Clear filters</span>
            </button>

            <!-- Refresh Button -->
            <button
              class="hover:bg-muted flex aspect-square h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border text-sm tracking-tight active:scale-98 sm:aspect-auto sm:px-2.5"
              @click="$emit('refresh')"
            >
              <Icon
                name="lucide:refresh-cw"
                class="size-4 shrink-0"
                :class="pending ? 'animate-spin' : ''"
              />
              <span class="hidden sm:flex">Refresh</span>
            </button>

            <NuxtLink
              v-if="showAddButton"
              :to="`/${props.model}/create`"
              class="hover:bg-primary/80 text-primary-foreground bg-primary flex items-center gap-x-1.5 rounded-md border px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
            >
              <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
              <span
                >Add <span v-if="props.label">{{ props.label }}</span></span
              >
            </NuxtLink>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="frame">
        <div class="frame-panel bg-background -m-px overflow-hidden !p-0">
          <Table class="[&_td]:scroll-fade-x table-fixed [&_td]:overflow-hidden">
            <TableHeader>
              <TableRow
                v-for="headerGroup in table.getHeaderGroups()"
                :key="headerGroup.id"
                class="tracking-tight hover:bg-transparent"
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
                      class="flex h-full cursor-pointer items-center gap-x-3 select-none"
                      @click="header.column.getToggleSortingHandler()?.($event)"
                      @keydown="
                        (event) => {
                          if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            header.column.getToggleSortingHandler()?.(event);
                          }
                        }
                      "
                      tabindex="0"
                      role="button"
                    >
                      <FlexRender
                        :render="header.column.columnDef.header"
                        :props="header.getContext()"
                      />
                      <Icon
                        v-if="header.column.getIsSorted() === 'desc'"
                        name="lucide:chevron-down"
                        class="text-muted-foreground size-3.5 shrink-0"
                      />
                      <Icon
                        v-else-if="header.column.getIsSorted() === 'asc'"
                        name="lucide:chevron-up"
                        class="text-muted-foreground size-3.5 shrink-0"
                      />
                      <Icon
                        v-else
                        name="lucide:chevron-up"
                        class="text-muted-foreground/30 size-3.5 shrink-0"
                      />
                    </div>
                    <div v-else>
                      <FlexRender
                        :render="header.column.columnDef.header"
                        :props="header.getContext()"
                      />
                    </div>
                  </template>
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <template v-if="table.getRowModel().rows?.length">
                <TableRow
                  v-for="row in table.getRowModel().rows"
                  :key="row.id"
                  :data-state="row.getIsSelected() && 'selected'"
                  class="tracking-tight"
                >
                  <TableCell
                    v-for="cell in row.getVisibleCells()"
                    :key="cell.id"
                    :style="{ width: `${cell.column.getSize()}px` }"
                    class="py-2.5"
                  >
                    <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                  </TableCell>
                </TableRow>
              </template>
            </TableBody>
          </Table>

          <!-- Empty State -->
          <div
            v-if="!hasRows"
            class="mx-auto flex w-full max-w-md flex-col items-center gap-4 py-10 text-center"
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
                It looks like there's no data in this page.
              </p>
            </div>
            <div class="flex items-center gap-2">
              <NuxtLink
                v-if="props.showAddButton"
                :to="`/${props.model}/create`"
                class="hover:bg-primary/80 bg-primary text-primary-foreground flex items-center gap-x-1.5 rounded-lg border px-3 py-2 text-sm font-medium tracking-tight active:scale-98"
              >
                <Icon name="lucide:plus" class="size-4 shrink-0" />
                <span>Create new</span>
              </NuxtLink>
              <button
                v-if="hasActiveFilters"
                class="border-border hover:bg-muted text-primary flex items-center gap-x-1.5 rounded-lg border px-3 py-2 text-sm font-medium tracking-tight active:scale-98"
                @click="table.resetColumnFilters()"
              >
                <Icon name="lucide:x" class="size-4 shrink-0" />
                <span>Clear filters</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="hasRows" class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <div class="flex items-center justify-between gap-x-4">
          <div class="text-muted-foreground text-sm tracking-tight">
            <template v-if="hasSelectedRows">
              {{ selectedRowsCount }} of {{ totalItems }} row<template v-if="selectedRowsCount > 1"
                >s</template
              >
              selected.
            </template>
            <template v-else>
              Showing {{ paginationInfo.from }} to {{ paginationInfo.to }} of
              {{ paginationInfo.total }} results.
            </template>
          </div>

          <Spinner v-if="pending" />
        </div>

        <div class="flex items-center justify-between gap-x-4">
          <div class="flex items-center gap-x-2">
            <p
              class="text-muted-foreground hidden text-sm tracking-tight whitespace-nowrap sm:block"
            >
              Rows per page
            </p>
            <Select :model-value="currentPageSizeValue" @update:model-value="handlePageSizeChange">
              <SelectTrigger size="sm">
                <SelectValue :placeholder="currentPageSizeDisplay" />
              </SelectTrigger>
              <SelectContent side="top">
                <SelectItem v-for="pageSize in pageSizes" :key="pageSize" :value="`${pageSize}`">
                  {{ pageSize }}
                </SelectItem>
                <SelectItem value="all"> All </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div>
            <Pagination
              :default-page="currentPage"
              :items-per-page="itemsPerPage"
              :total="totalItems"
            >
              <PaginationContent>
                <PaginationFirst asChild>
                  <button
                    class="hover:bg-muted bg-background border-border flex size-8 shrink-0 items-center justify-center rounded-md border active:scale-98"
                    @click="() => table.setPageIndex(0)"
                    :disabled="!canGoPrevious"
                  >
                    <Icon name="lucide:chevron-first" class="size-4 shrink-0" />
                  </button>
                </PaginationFirst>
                <PaginationPrevious asChild>
                  <button
                    class="hover:bg-muted bg-background border-border flex size-8 shrink-0 items-center justify-center rounded-md border active:scale-98"
                    @click="() => table.previousPage()"
                    :disabled="!canGoPrevious"
                  >
                    <Icon name="lucide:chevron-left" class="size-4 shrink-0" />
                  </button>
                </PaginationPrevious>
                <PaginationNext asChild>
                  <button
                    class="hover:bg-muted bg-background border-border flex size-8 shrink-0 items-center justify-center rounded-md border active:scale-98"
                    @click="() => table.nextPage()"
                    :disabled="!canGoNext"
                  >
                    <Icon name="lucide:chevron-right" class="size-4 shrink-0" />
                  </button>
                </PaginationNext>
                <PaginationLast asChild>
                  <button
                    class="hover:bg-muted bg-background border-border flex size-8 shrink-0 items-center justify-center rounded-md border active:scale-98"
                    @click="() => table.setPageIndex(lastPageIndex)"
                    :disabled="!canGoNext"
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
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import {
  Pagination,
  PaginationContent,
  PaginationFirst,
  PaginationLast,
  PaginationNext,
  PaginationPrevious,
} from "@/components/ui/pagination";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { valueUpdater } from "@/components/ui/table/utils";
import {
  FlexRender,
  getCoreRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  useVueTable,
} from "@tanstack/vue-table";

const props = defineProps({
  // Data
  data: {
    type: Array,
    required: true,
  },
  columns: {
    type: Array,
    required: true,
  },
  meta: {
    type: Object,
    required: true,
  },
  pending: {
    type: Boolean,
    default: false,
  },
  error: {
    type: [Object, String],
    default: null,
  },
  model: {
    type: String,
    required: true,
  },
  label: {
    type: String,
  },
  clientOnly: {
    type: Boolean,
    default: true,
  },
  searchable: {
    type: Boolean,
    default: true,
  },
  searchColumn: {
    type: String,
    default: "name",
  },
  searchPlaceholder: {
    type: String,
    default: null,
  },
  columnToggle: {
    type: Boolean,
    default: true,
  },
  showAddButton: {
    type: Boolean,
    default: true,
  },
  pageSizes: {
    type: Array,
    default: () => [10, 20, 30, 40, 50],
  },

  // Messages
  errorTitle: {
    type: String,
    default: null,
  },

  // Initial state
  initialRowSelection: {
    type: Object,
    default: () => ({}),
  },
  initialColumnFilters: {
    type: Array,
    default: () => [],
  },
  initialColumnVisibility: {
    type: Object,
    default: () => ({}),
  },
  initialPagination: {
    type: Object,
    default: () => ({ pageIndex: 0, pageSize: 10 }),
  },
  initialSorting: {
    type: Array,
    default: () => [{ id: "created_at", desc: true }],
  },
});

const emit = defineEmits([
  "refresh",
  "update:rowSelection",
  "update:columnFilters",
  "update:columnVisibility",
  "update:pagination",
  "update:sorting",
]);

// Table state
const rowSelection = ref(props.initialRowSelection);
const columnFilters = ref(props.initialColumnFilters);
const columnVisibility = ref(props.initialColumnVisibility);
const pagination = ref(props.initialPagination);
const sorting = ref(props.initialSorting);

// Watch for state changes and emit to parent
watch(rowSelection, (value) => emit("update:rowSelection", value), { deep: true });
watch(columnFilters, (value) => emit("update:columnFilters", value), { deep: true });
watch(columnVisibility, (value) => emit("update:columnVisibility", value), { deep: true });
watch(pagination, (value) => emit("update:pagination", value), { deep: true });
watch(sorting, (value) => emit("update:sorting", value), { deep: true });

// Table instance
const table = useVueTable({
  get data() {
    return props.data || [];
  },
  get columns() {
    return props.columns;
  },
  getCoreRowModel: getCoreRowModel(),
  getFilteredRowModel: props.clientOnly ? getFilteredRowModel() : undefined,
  getSortedRowModel: props.clientOnly ? getSortedRowModel() : undefined,
  getPaginationRowModel: props.clientOnly ? getPaginationRowModel() : undefined,
  manualPagination: !props.clientOnly,
  manualSorting: !props.clientOnly,
  manualFiltering: !props.clientOnly,
  pageCount: props.clientOnly ? undefined : props.meta.last_page,
  autoResetPageIndex: false,
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
  onPaginationChange: (updater) => valueUpdater(updater, pagination),
  onColumnFiltersChange: (updater) => valueUpdater(updater, columnFilters),
  onColumnVisibilityChange: (updater) => valueUpdater(updater, columnVisibility),
  onRowSelectionChange: (updater) => valueUpdater(updater, rowSelection),
  enableSortingRemoval: false,
});

// Handle page size change
const handlePageSizeChange = (value) => {
  // If "all" is selected, set page size to total items
  const newPageSize = value === "all" ? totalItems.value : Number(value);
  const currentPageSize = pagination.value.pageSize;

  // Only update if page size actually changed
  if (currentPageSize !== newPageSize) {
    // Update pagination state atomically - reset to page 1 when page size changes
    pagination.value = {
      pageIndex: 0,
      pageSize: newPageSize,
    };
  }
};

// Search with debounce
const searchInputEl = ref();
const searchValue = ref("");
const { metaSymbol } = useShortcuts();

// Initialize search value from initial filters
onMounted(() => {
  const initialSearchFilter = props.initialColumnFilters.find((f) => f.id === props.searchColumn);
  if (initialSearchFilter?.value) {
    searchValue.value = initialSearchFilter.value;
  }
});

// Debounced search handler
const debouncedSearch = useDebounceFn((value) => {
  table.getColumn(props.searchColumn)?.setFilterValue(value || undefined);
  // Reset to first page when search changes
  table.setPageIndex(0);
}, 0);

const handleSearchInput = (event) => {
  searchValue.value = event.target.value;
  debouncedSearch(event.target.value);
};

const clearSearch = () => {
  searchValue.value = "";
  table.getColumn(props.searchColumn)?.setFilterValue(undefined);
  // Reset to first page when search is cleared
  table.setPageIndex(0);
};

defineShortcuts({
  meta_k: {
    usingInput: true,
    handler: () => {
      searchInputEl.value?.focus();
    },
  },
});

// Computed properties for better readability
const isClientSidePagination = computed(() => props.clientOnly);
const hasRows = computed(() => table.getRowModel().rows?.length > 0);
const hasActiveFilters = computed(() => table.getState().columnFilters.length > 0);
const selectedRowsCount = computed(() => table.getSelectedRowModel().rows.length);
const hasSelectedRows = computed(() => selectedRowsCount.value > 0);

const paginationInfo = computed(() => {
  if (isClientSidePagination.value) {
    const pageIndex = table.getState().pagination.pageIndex;
    const pageSize = table.getState().pagination.pageSize;
    const totalRows = table.getFilteredRowModel().rows.length;

    return {
      from: pageIndex * pageSize + 1,
      to: Math.min((pageIndex + 1) * pageSize, totalRows),
      total: totalRows,
    };
  } else {
    return {
      from: (props.meta.current_page - 1) * props.meta.per_page + 1,
      to: Math.min(props.meta.current_page * props.meta.per_page, props.meta.total),
      total: props.meta.total,
    };
  }
});

const canGoPrevious = computed(() =>
  isClientSidePagination.value ? table.getCanPreviousPage() : props.meta.current_page > 1
);

const canGoNext = computed(() =>
  isClientSidePagination.value
    ? table.getCanNextPage()
    : props.meta.current_page < props.meta.last_page
);

const lastPageIndex = computed(() =>
  isClientSidePagination.value ? table.getPageCount() - 1 : props.meta.last_page - 1
);

const currentPage = computed(() =>
  isClientSidePagination.value ? table.getState().pagination.pageIndex + 1 : props.meta.current_page
);

const itemsPerPage = computed(() =>
  isClientSidePagination.value ? table.getState().pagination.pageSize : props.meta.per_page
);

const totalItems = computed(() =>
  isClientSidePagination.value ? table.getFilteredRowModel().rows.length : props.meta.total
);

const currentPageSizeValue = computed(() => {
  const pageSize = table.getState().pagination.pageSize;
  // Check if current page size equals total items (which means "All" is selected)
  return pageSize === totalItems.value ? "all" : `${pageSize}`;
});

const currentPageSizeDisplay = computed(() => {
  const pageSize = table.getState().pagination.pageSize;
  return pageSize === totalItems.value ? "All" : `${pageSize}`;
});

// Method to reset row selection
const resetRowSelection = () => {
  table.resetRowSelection();
};

// Expose table instance and methods for parent
defineExpose({
  table,
  resetRowSelection,
});
</script>
