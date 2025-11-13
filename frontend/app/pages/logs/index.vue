<template>
  <div class="mx-auto max-w-7xl space-y-6 pt-4 pb-16">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:analysis-text-link" class="size-5 sm:size-6" />
        <h1 class="page-title">Application Logs</h1>
      </div>

      <button
        v-if="user?.roles?.includes('master')"
        @click="clearLogs"
        :disabled="clearing"
        class="border-destructive bg-destructive/10 text-destructive hover:bg-destructive/20 flex items-center gap-2 rounded-lg border px-3 py-2 text-sm disabled:opacity-50"
      >
        <Icon name="hugeicons:delete-02" class="size-4" />
        Clear Logs
      </button>
    </div>

    <TableData
      :clientOnly="false"
      ref="tableRef"
      :data="data"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="logs"
      search-column="search"
      search-placeholder="Search in description, user, or event"
      error-title="Error loading logs"
      :show-add-button="false"
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
                title="Log Name"
                :options="logNames"
                :selected="selectedLogNames"
                @change="handleFilterChange('log_name', $event)"
              />
              <div class="border-t" />
              <FilterSection
                title="Event"
                :options="events"
                :selected="selectedEvents"
                @change="handleFilterChange('event', $event)"
              />
            </div>
          </PopoverContent>
        </Popover>
      </template>
    </TableData>
  </div>
</template>

<script setup>
import TableData from "@/components/TableData.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { resolveDirective, withDirectives } from "vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "admin-master"],
  layout: "app",
});

defineOptions({
  name: "logs",
});

usePageMeta("logs");

const { user } = useSanctumAuth();
const { $ } = useNuxtApp();

// Table state
const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 50 });
const sorting = ref([{ id: "created_at", desc: true }]);

// Data state
const data = ref([]);
const meta = ref({ current_page: 1, last_page: 1, per_page: 50, total: 0 });
const pending = ref(false);
const error = ref(null);

// Additional state
const logNames = ref([]);
const events = ref([]);
const clearing = ref(false);

// Build query params
const buildQueryParams = () => {
  const params = new URLSearchParams();

  // Server-side mode: add pagination, filters, and sorting
  params.append("page", pagination.value.pageIndex + 1);
  params.append("per_page", pagination.value.pageSize);

  // Filters
  const filters = {
    search: "search",
    log_name: "log_name",
    event: "event",
  };

  Object.entries(filters).forEach(([columnId, paramKey]) => {
    const filter = columnFilters.value.find((f) => f.id === columnId);
    if (filter?.value) {
      const value = Array.isArray(filter.value) ? filter.value.join(",") : filter.value;
      params.append(paramKey, value);
    }
  });

  // Sorting
  const sortField = sorting.value[0]?.id || "created_at";
  const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
  params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);

  return params.toString();
};

// Fetch logs
const fetchLogs = async () => {
  try {
    pending.value = true;
    error.value = null;
    const client = useSanctumClient();
    const response = await client(`/api/logs?${buildQueryParams()}`);
    data.value = response.data;
    meta.value = response.meta;
  } catch (err) {
    error.value = err;
    console.error("Failed to fetch logs:", err);
  } finally {
    pending.value = false;
  }
};

await fetchLogs();

// Watchers for server-side mode
const debouncedFetch = useDebounceFn(fetchLogs, 300);

watch(
  [columnFilters, sorting, pagination],
  () => {
    const hasSearchFilter = columnFilters.value.some((f) => f.id === "search");
    hasSearchFilter ? debouncedFetch() : fetchLogs();
  },
  { deep: true }
);

const refresh = fetchLogs;

// Load log names
async function loadLogNames() {
  try {
    const client = useSanctumClient();
    const response = await client("/api/logs/log-names");
    logNames.value = response.data;
  } catch (err) {
    console.error("Error loading log names:", err);
  }
}

// Load events
async function loadEvents() {
  try {
    const client = useSanctumClient();
    const response = await client("/api/logs/events");
    events.value = response.data;
  } catch (err) {
    console.error("Error loading events:", err);
  }
}

// Clear logs (master only)
async function clearLogs() {
  if (!confirm("Are you sure you want to clear all logs? This action cannot be undone.")) {
    return;
  }

  clearing.value = true;

  try {
    const client = useSanctumClient();
    await client("/api/logs/clear", {
      method: "DELETE",
    });

    await refresh();
    toast.success("Logs cleared successfully");
  } catch (err) {
    console.error("Error clearing logs:", err);
    toast.error("Failed to clear logs", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    clearing.value = false;
  }
}

// Table columns
const columns = [
  {
    header: "Activity",
    accessorKey: "human_description",
    cell: ({ row }) => {
      const log = row.original;
      return h("div", { class: "space-y-0.5" }, [
        h("div", { class: "text-sm font-medium tracking-tight" }, log.human_description),
        log.event
          ? h("div", { class: "text-muted-foreground text-xs" }, `Event: ${log.event}`)
          : null,
      ]);
    },
    size: 200,
    enableSorting: false,
    filterFn: (row, columnId, filterValue) => {
      if (!filterValue) return true;
      const searchValue = filterValue.toLowerCase();
      const description = row.original.human_description?.toLowerCase() || "";
      const event = row.original.event?.toLowerCase() || "";
      const causer = row.original.causer_name?.toLowerCase() || "";
      return (
        description.includes(searchValue) ||
        event.includes(searchValue) ||
        causer.includes(searchValue)
      );
    },
  },
  {
    header: "Causer",
    accessorKey: "causer_name",
    cell: ({ row }) => {
      const log = row.original;
      return h("div", { class: "space-y-0.5" }, [
        h("div", { class: "text-sm tracking-tight" }, log.causer_name || "System"),
        log.causer_id
          ? h("div", { class: "text-muted-foreground text-xs" }, `ID: ${log.causer_id}`)
          : null,
      ]);
    },
    size: 150,
    enableSorting: false,
  },
  {
    header: "Subject",
    accessorKey: "subject_info",
    cell: ({ row }) => {
      const log = row.original;
      return log.subject_info
        ? h("div", { class: "text-sm tracking-tight" }, log.subject_info)
        : h("div", { class: "text-muted-foreground text-sm" }, "—");
    },
    size: 240,
    enableSorting: false,
  },
  {
    header: "Time",
    accessorKey: "created_at",
    cell: ({ row }) => {
      const date = row.getValue("created_at");
      return withDirectives(
        h("div", { class: "text-sm text-muted-foreground tracking-tight" }, $(date).fromNow()),
        [[resolveDirective("tippy"), $(date).format("MMMM D, YYYY [at] h:mm A")]]
      );
    },
    size: 120,
    enableSorting: true,
  },
  {
    header: "Details",
    accessorKey: "properties",
    cell: ({ row }) => {
      const log = row.original;
      if (log.properties && Object.keys(log.properties).length > 0) {
        return h("details", { class: "cursor-pointer" }, [
          h(
            "summary",
            { class: "text-muted-foreground hover:text-foreground text-xs select-none" },
            "View Details"
          ),
          h("div", { class: "mt-2 max-w-xs" }, [
            h(
              "pre",
              { class: "bg-muted max-h-32 overflow-auto rounded p-2 text-xs whitespace-pre-wrap" },
              JSON.stringify(log.properties, null, 2)
            ),
          ]),
        ]);
      }
      return h("div", { class: "text-muted-foreground text-xs" }, "—");
    },
    size: 100,
    enableSorting: false,
  },
];

// Table ref
const tableRef = ref();

// Filter helpers - server mode
const getFilterValue = (columnId) => {
  return columnFilters.value.find((f) => f.id === columnId)?.value ?? [];
};

const selectedLogNames = computed(() => getFilterValue("log_name"));
const selectedEvents = computed(() => getFilterValue("event"));
const totalActiveFilters = computed(
  () => selectedLogNames.value.length + selectedEvents.value.length
);

const handleFilterChange = (columnId, { checked, value }) => {
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
  // Reset to first page when filter changes
  pagination.value.pageIndex = 0;
};

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

// Load data on mount
onMounted(async () => {
  await Promise.all([loadLogNames(), loadEvents()]);
});
</script>
