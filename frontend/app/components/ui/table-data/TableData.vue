<template>
  <div class="space-y-4">
    <!-- Error State -->
    <div v-if="error" class="flex flex-col items-start gap-y-3 rounded-lg">
      <!-- <div class="text-destructive flex items-center gap-x-2">
        <Icon name="hugeicons:alert-circle" class="size-5" />
        <span class="font-medium tracking-tight">{{ errorTitle || "Error loading data" }}</span>
      </div>
      <p class="text-sm tracking-tight">
        {{ error?.message || "An error occurred while fetching data." }}
      </p> -->
    </div>

    <!-- Main Content -->
    <div v-else class="space-y-4">
      <!-- Toolbar -->
      <div
        v-if="
          searchable ||
          columnToggle ||
          $slots.filters ||
          hasActiveFilters ||
          (showRefreshButton && !displayOnly) ||
          (showAddButton && !displayOnly) ||
          $slots['add-button']
        "
        class="space-y-3"
      >
        <!-- Search and Filters -->
        <div
          v-if="searchable || columnToggle || $slots.filters"
          class="flex h-9 w-full gap-x-1 sm:gap-x-2"
        >
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
          <ClientOnly v-if="columnToggle">
            <Popover>
              <PopoverTrigger asChild>
                <button
                  class="hover:bg-muted flex aspect-square h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border text-sm tracking-tight active:scale-98 sm:aspect-auto sm:px-2.5"
                >
                  <Icon name="hugeicons:layout-3-column" class="size-4 shrink-0" />
                  <span class="hidden sm:flex">Columns</span>
                </button>
              </PopoverTrigger>
              <PopoverContent class="w-auto min-w-36 p-3" align="end">
                <div class="space-y-3">
                  <div class="text-muted-foreground text-xs font-medium">Toggle columns</div>
                  <div class="space-y-3">
                    <div
                      v-for="column in table
                        .getAllColumns()
                        .filter((column) => column.getCanHide())"
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
            <template #fallback>
              <button
                class="hover:bg-muted flex aspect-square h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border text-sm tracking-tight active:scale-98 sm:aspect-auto sm:px-2.5"
                disabled
              >
                <Icon name="hugeicons:layout-3-column" class="size-4 shrink-0" />
                <span class="hidden sm:flex">Columns</span>
              </button>
            </template>
          </ClientOnly>
        </div>

        <!-- Action Buttons (bulk actions live in the floating pill below) -->
        <div
          v-if="
            hasActiveFilters ||
            (showRefreshButton && !displayOnly) ||
            (showAddButton && !displayOnly) ||
            $slots['add-button'] ||
            $slots['toolbar-actions']
          "
          class="flex h-8 w-full items-center justify-between gap-1 sm:gap-x-2"
        >
          <div class="ml-auto flex h-full gap-x-1 sm:gap-x-2">
            <!-- Clear Filters Button -->
            <Button
              v-if="hasActiveFilters"
              variant="outline"
              size="sm"
              @click="table.resetColumnFilters()"
            >
              <Icon name="lucide:x" class="size-4 shrink-0" />
              <span class="hidden sm:flex">Clear filters</span>
            </Button>

            <!-- Custom toolbar actions (rendered before Refresh) -->
            <slot name="toolbar-actions" :table="table" />

            <!-- Refresh Button -->
            <Button
              v-if="showRefreshButton && !displayOnly"
              variant="outline"
              size="sm"
              @click="$emit('refresh')"
            >
              <Icon
                name="hugeicons:reload"
                class="size-4 shrink-0"
                :class="pending ? 'animate-spin' : ''"
              />
              <span class="hidden sm:flex">Refresh</span>
              <KbdGroup class="hidden sm:flex">
                <Kbd>R</Kbd>
              </KbdGroup>
            </Button>

            <slot name="add-button">
              <NuxtLink
                v-if="showAddButton && !displayOnly"
                :to="`/${props.model}/create`"
                class="hover:bg-primary/80 text-primary-foreground bg-primary flex items-center gap-x-1.5 rounded-md border px-3 py-1.5 text-sm font-medium tracking-tight active:scale-98"
              >
                <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
                <span
                  >Add <span v-if="props.label">{{ props.label }}</span></span
                >
              </NuxtLink>
            </slot>
          </div>
        </div>
      </div>

      <!-- Floating bulk-action pill — rendered for every page that provides an
           #actions slot. Teleported so it floats above the page; reveals when
           rows are selected (transitions-dev: panel reveal + badge pop). -->
      <ClientOnly>
        <Teleport to="body">
          <Transition
            enter-active-class="transition-[translate,opacity,filter] duration-[400ms] ease-[cubic-bezier(0.22,1,0.36,1)]"
            enter-from-class="translate-y-4 opacity-0 blur-[2px]"
            leave-active-class="transition-[translate,opacity,filter] duration-[350ms] ease-[cubic-bezier(0.22,1,0.36,1)]"
            leave-to-class="translate-y-4 opacity-0 blur-[2px]"
          >
            <div
              v-if="floatingActions && selectedRowsCount > 0 && $slots.actions"
              class="fixed bottom-4 left-1/2 z-40 w-fit max-w-[calc(100vw-1.5rem)] -translate-x-1/2 sm:bottom-6"
            >
              <!-- Static circle; only the value animates on change (NumberFlow),
                   so the circle itself never re-pops. -->
              <span
                class="bg-foreground text-background border-background absolute -top-2 -right-1 z-10 inline-flex size-6 items-center justify-center rounded-full border-2 text-xs font-medium tracking-tight tabular-nums shadow-sm"
              >
                <NumberFlow :value="selectedRowsCount" />
              </span>

              <div
                ref="pillEl"
                class="t-resize bg-foreground text-background flex items-center rounded-full p-1 shadow-lg"
              >
                <div class="no-scrollbar scroll-fade-x overflow-x-auto">
                  <div class="flex w-max items-center gap-x-0.5">
                    <TableBulkAction
                      icon="lucide:x"
                      label="Clear selection"
                      @click="resetRowSelection"
                    />
                    <div
                      v-if="$slots.actions"
                      class="bg-background/20 mx-0.5 h-5 w-px shrink-0"
                      aria-hidden="true"
                    />
                    <slot
                      name="actions"
                      :table="table"
                      :selected-rows="table.getSelectedRowModel().rows"
                    />
                  </div>
                </div>
              </div>
            </div>
          </Transition>
        </Teleport>
      </ClientOnly>

      <!-- Table -->
      <div class="frame">
        <div class="frame-panel bg-background -m-px overflow-hidden p-0!">
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
              <!-- Skeleton Loading Rows -->
              <template v-if="isInitialLoading">
                <slot name="loading">
                  <TableRow v-for="i in 25" :key="`skeleton-${i}`" class="tracking-tight">
                    <TableCell
                      v-for="(header, j) in table.getHeaderGroups()[0]?.headers || []"
                      :key="`skeleton-cell-${i}-${j}`"
                      :style="{ width: `${header.getSize()}px` }"
                      class="py-2.5"
                    >
                      <Skeleton
                        :class="[
                          'h-4 rounded',
                          j === 0 ? 'w-3/4' : j % 3 === 0 ? 'w-1/2' : j % 2 === 0 ? 'w-2/3' : 'w-full',
                        ]"
                      />
                    </TableCell>
                  </TableRow>
                </slot>
              </template>

              <template v-else-if="table.getRowModel().rows?.length">
                <template v-for="row in table.getRowModel().rows" :key="row.id">
                  <TableRow
                    :data-state="row.getIsSelected() && 'selected'"
                    class="group tracking-tight"
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
                  <TableRow
                    v-if="$slots['expanded-row'] && row.getIsExpanded()"
                    :key="`${row.id}-expanded`"
                    class="hover:bg-transparent"
                  >
                    <TableCell :colspan="row.getVisibleCells().length" class="bg-muted/30 p-0">
                      <slot name="expanded-row" :row="row" />
                    </TableCell>
                  </TableRow>
                </template>
              </template>
            </TableBody>
          </Table>

          <!-- Empty State -->
          <div
            v-if="!isInitialLoading && !hasRows"
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
                v-if="props.showAddButton && !props.displayOnly"
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
                    @click="goToFirstPage"
                    :disabled="!canGoPrevious"
                  >
                    <Icon name="lucide:chevron-first" class="size-4 shrink-0" />
                  </button>
                </PaginationFirst>
                <PaginationPrevious asChild>
                  <button
                    class="hover:bg-muted bg-background border-border flex size-8 shrink-0 items-center justify-center rounded-md border active:scale-98"
                    @click="goToPreviousPage"
                    :disabled="!canGoPrevious"
                  >
                    <Icon name="lucide:chevron-left" class="size-4 shrink-0" />
                  </button>
                </PaginationPrevious>
                <PaginationNext asChild>
                  <button
                    class="hover:bg-muted bg-background border-border flex size-8 shrink-0 items-center justify-center rounded-md border active:scale-98"
                    @click="goToNextPage"
                    :disabled="!canGoNext"
                  >
                    <Icon name="lucide:chevron-right" class="size-4 shrink-0" />
                  </button>
                </PaginationNext>
                <PaginationLast asChild>
                  <button
                    class="hover:bg-muted bg-background border-border flex size-8 shrink-0 items-center justify-center rounded-md border active:scale-98"
                    @click="goToLastPage"
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
import { Button } from "@/components/ui/button";
import TableBulkAction from "@/components/ui/table-data/TableBulkAction.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { Kbd, KbdGroup } from "@/components/ui/kbd";
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
  getExpandedRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  useVueTable,
} from "@tanstack/vue-table";

const slots = useSlots();

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
  showRefreshButton: {
    type: Boolean,
    default: true,
  },
  displayOnly: {
    type: Boolean,
    default: false,
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
  // Renders the #actions slot inside the floating bottom pill. On by default so
  // every table page gets the pill; set false to keep actions inline.
  floatingActions: {
    type: Boolean,
    default: true,
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

// Determine if we should use client-side processing
const isClientSideMode = computed(() => props.displayOnly || props.clientOnly);

// Table state
const rowSelection = ref(props.initialRowSelection);
const columnFilters = ref(props.initialColumnFilters);
const columnVisibility = ref(props.initialColumnVisibility);
const pagination = ref(props.initialPagination);
const sorting = ref(props.initialSorting);
const expanded = ref({});

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
  getFilteredRowModel: isClientSideMode.value ? getFilteredRowModel() : undefined,
  getSortedRowModel: isClientSideMode.value ? getSortedRowModel() : undefined,
  getPaginationRowModel: isClientSideMode.value ? getPaginationRowModel() : undefined,
  // Row expansion is opt-in: only enabled when the consumer provides an
  // #expanded-row slot, so existing tables are unaffected.
  getExpandedRowModel: getExpandedRowModel(),
  getRowCanExpand: () => !!slots["expanded-row"],
  manualPagination: !isClientSideMode.value,
  manualSorting: !isClientSideMode.value,
  manualFiltering: !isClientSideMode.value,
  pageCount: isClientSideMode.value ? undefined : props.meta.last_page,
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
    get expanded() {
      return expanded.value;
    },
  },
  onSortingChange: (updater) => valueUpdater(updater, sorting),
  onPaginationChange: (updater) => valueUpdater(updater, pagination),
  onColumnFiltersChange: (updater) => valueUpdater(updater, columnFilters),
  onColumnVisibilityChange: (updater) => valueUpdater(updater, columnVisibility),
  onRowSelectionChange: (updater) => valueUpdater(updater, rowSelection),
  onExpandedChange: (updater) => valueUpdater(updater, expanded),
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

// Pagination navigation functions that work for both client and server-side modes
const goToFirstPage = () => {
  if (isClientSideMode.value) {
    table.setPageIndex(0);
  } else {
    pagination.value = { ...pagination.value, pageIndex: 0 };
  }
};

const goToPreviousPage = () => {
  if (isClientSideMode.value) {
    table.previousPage();
  } else {
    const newIndex = Math.max(0, pagination.value.pageIndex - 1);
    pagination.value = { ...pagination.value, pageIndex: newIndex };
  }
};

const goToNextPage = () => {
  if (isClientSideMode.value) {
    table.nextPage();
  } else {
    const maxIndex = props.meta.last_page - 1;
    const newIndex = Math.min(maxIndex, pagination.value.pageIndex + 1);
    pagination.value = { ...pagination.value, pageIndex: newIndex };
  }
};

const goToLastPage = () => {
  if (isClientSideMode.value) {
    table.setPageIndex(table.getPageCount() - 1);
  } else {
    pagination.value = { ...pagination.value, pageIndex: props.meta.last_page - 1 };
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

// Debounced search handler (300ms delay to prevent fetch on every keystroke)
const debouncedSearch = useDebounceFn((value) => {
  table.getColumn(props.searchColumn)?.setFilterValue(value || undefined);
  // Reset to first page when search changes
  table.setPageIndex(0);
}, 300);

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
  r: {
    handler: () => {
      if (props.showRefreshButton && !props.displayOnly) {
        emit("refresh");
      }
    },
  },
});

// Computed properties for better readability
const isClientSidePagination = computed(() => isClientSideMode.value);
const hasRows = computed(() => table.getRowModel().rows?.length > 0);
const isInitialLoading = computed(() => props.pending && props.data.length === 0);
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
  isClientSidePagination.value ? table.getCanPreviousPage() : pagination.value.pageIndex > 0
);

const canGoNext = computed(() =>
  isClientSidePagination.value
    ? table.getCanNextPage()
    : pagination.value.pageIndex < props.meta.last_page - 1
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
  const pageSize = pagination.value.pageSize;
  // Only show "all" if page size is larger than all available options in pageSizes
  const maxPageSizeOption = Math.max(...props.pageSizes);
  const isAllSelected = pageSize > maxPageSizeOption;
  return isAllSelected ? "all" : `${pageSize}`;
});

const currentPageSizeDisplay = computed(() => {
  const pageSize = pagination.value.pageSize;
  const maxPageSizeOption = Math.max(...props.pageSizes);
  const isAllSelected = pageSize > maxPageSizeOption;
  return isAllSelected ? "All" : `${pageSize}`;
});

// Smoothly tween the floating pill's width when its action content changes
// width (e.g. a button label swaps "Mark as checked in" ↔ "Mark as not
// checked in"). CSS can't transition intrinsic (auto) widths, so we FLIP:
// lock the old px width, force a reflow, then animate to the new px width.
// The `.t-resize` class supplies the transition timing (transitions-dev 01).
const pillEl = ref(null);
let pillObserver = null;
let lastPillWidth = 0;
let pillAnimating = false;
let pillResetTimer = null;

const prefersReducedMotion = () =>
  import.meta.client &&
  window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;

const teardownPillObserver = () => {
  if (pillObserver) {
    pillObserver.disconnect();
    pillObserver = null;
  }
  if (pillResetTimer) {
    clearTimeout(pillResetTimer);
    pillResetTimer = null;
  }
  lastPillWidth = 0;
  pillAnimating = false;
};

const setupPillObserver = (el) => {
  lastPillWidth = 0;
  pillObserver = new ResizeObserver(() => {
    // Ignore the resize events caused by our own inline-width writes.
    if (pillAnimating) {
      return;
    }
    const newWidth = el.offsetWidth;
    if (!newWidth) {
      return;
    }
    // First measurement after the pill appears — record the baseline only;
    // the entrance itself is handled by the panel-reveal transition.
    if (lastPillWidth === 0 || newWidth === lastPillWidth) {
      lastPillWidth = newWidth;
      return;
    }
    const fromWidth = lastPillWidth;
    lastPillWidth = newWidth;
    if (prefersReducedMotion()) {
      return;
    }
    pillAnimating = true;
    el.style.width = `${fromWidth}px`;
    void el.offsetWidth; // force reflow so the next assignment transitions
    el.style.width = `${newWidth}px`;
    const finish = () => {
      el.style.width = "";
      pillAnimating = false;
      el.removeEventListener("transitionend", onTransitionEnd);
      if (pillResetTimer) {
        clearTimeout(pillResetTimer);
        pillResetTimer = null;
      }
    };
    const onTransitionEnd = (event) => {
      if (event.propertyName === "width") {
        finish();
      }
    };
    el.addEventListener("transitionend", onTransitionEnd);
    pillResetTimer = setTimeout(finish, 450); // fallback if transitionend misfires
  });
  pillObserver.observe(el);
};

watch(pillEl, (el, prevEl) => {
  if (prevEl) {
    teardownPillObserver();
  }
  if (el) {
    setupPillObserver(el);
  }
});

onBeforeUnmount(teardownPillObserver);

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
