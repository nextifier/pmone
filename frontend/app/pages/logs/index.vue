<template>
  <div class="mx-auto max-w-6xl space-y-6 pt-4 pb-16">
    <!-- Page Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:analytics-02" class="text-primary size-5 sm:size-6" />
        <h1 class="page-title">Activity Logs</h1>
      </div>

      <button
        v-if="user?.roles?.includes('master')"
        @click="confirmClearLogs"
        :disabled="clearing || pending"
        class="border-destructive bg-destructive/10 text-destructive hover:bg-destructive/20 flex items-center gap-x-1.5 rounded-lg border px-3 py-2 text-sm font-medium tracking-tight transition active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
      >
        <Icon name="hugeicons:delete-02" class="size-4 shrink-0" />
        <span>Clear All Logs</span>
      </button>
    </div>

    <!-- Main Table -->
    <TableData
      ref="tableRef"
      :clientOnly="false"
      :data="data"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="logs"
      search-column="search"
      search-placeholder="Search in description, user, or event"
      error-title="Error loading activity logs"
      :show-add-button="false"
      :show-refresh-button="true"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
      @update:pagination="pagination = $event"
      @update:sorting="sorting = $event"
      @update:column-filters="columnFilters = $event"
      @refresh="refresh"
    >
      <template #filters="{ table }">
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
          <PopoverContent class="w-auto min-w-52 p-3" align="start">
            <div class="space-y-4">
              <FilterSection
                v-if="logNames.length > 0"
                title="Log Name"
                :options="logNames"
                :selected="selectedLogNames"
                @change="handleFilterChange('log_name', $event)"
              />
              <div v-if="logNames.length > 0 && events.length > 0" class="border-t" />
              <FilterSection
                v-if="events.length > 0"
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
  middleware: ["sanctum:auth", "role"],
  roles: ["admin", "master"],
  layout: "app",
});

defineOptions({
  name: "ActivityLogs",
});

usePageMeta("logs");

const { user } = useSanctumAuth();
const { $dayjs } = useNuxtApp();

// Table state
const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 50 });
const sorting = ref([{ id: "created_at", desc: true }]);

// Data state
const data = ref([]);
const meta = ref({ current_page: 1, last_page: 1, per_page: 50, total: 0 });
const pending = ref(false);
const error = ref(null);

// Filter options
const logNames = ref([]);
const events = ref([]);
const clearing = ref(false);

// Build query params for API
const buildQueryParams = () => {
  const params = new URLSearchParams();

  // Pagination
  params.append("page", pagination.value.pageIndex + 1);
  params.append("per_page", pagination.value.pageSize);

  // Search filter
  const searchFilter = columnFilters.value.find((f) => f.id === "search");
  if (searchFilter?.value) {
    params.append("search", searchFilter.value);
  }

  // Log name filter
  const logNameFilter = columnFilters.value.find((f) => f.id === "log_name");
  if (logNameFilter?.value && Array.isArray(logNameFilter.value)) {
    params.append("log_name", logNameFilter.value.join(","));
  }

  // Event filter
  const eventFilter = columnFilters.value.find((f) => f.id === "event");
  if (eventFilter?.value && Array.isArray(eventFilter.value)) {
    params.append("event", eventFilter.value.join(","));
  }

  // Sorting
  const sortField = sorting.value[0]?.id || "created_at";
  const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
  params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);

  return params.toString();
};

// Fetch logs from API
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
    toast.error("Failed to load activity logs", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    pending.value = false;
  }
};

// Initial fetch
await fetchLogs();

// Watch for changes and refetch
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

// Load filter options
const loadLogNames = async () => {
  try {
    const client = useSanctumClient();
    const response = await client("/api/logs/log-names");
    logNames.value = response.data || [];
  } catch (err) {
    console.error("Error loading log names:", err);
  }
};

const loadEvents = async () => {
  try {
    const client = useSanctumClient();
    const response = await client("/api/logs/events");
    events.value = response.data || [];
  } catch (err) {
    console.error("Error loading events:", err);
  }
};

// Clear all logs (master only)
const confirmClearLogs = () => {
  if (
    confirm(
      "Are you sure you want to clear all activity logs? This action cannot be undone and will permanently delete all log entries."
    )
  ) {
    clearLogs();
  }
};

const clearLogs = async () => {
  clearing.value = true;

  try {
    const client = useSanctumClient();
    const response = await client("/api/logs/clear", {
      method: "DELETE",
    });

    toast.success("Activity logs cleared successfully", {
      description: `${response.deleted_count || 0} log entries deleted`,
    });

    await refresh();
  } catch (err) {
    console.error("Error clearing logs:", err);
    toast.error("Failed to clear logs", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    clearing.value = false;
  }
};

// Table columns definition
const columns = [
  {
    header: "Activity",
    accessorKey: "human_description",
    cell: ({ row }) => {
      const log = row.original;
      return h("div", { class: "space-y-1" }, [
        h(
          "div",
          { class: "text-foreground text-sm font-medium tracking-tight" },
          log.human_description || log.description
        ),
        log.event
          ? h("div", { class: "text-muted-foreground flex items-center gap-x-1 text-xs" }, [
              h("span", { class: "capitalize" }, log.event),
              log.log_name
                ? h("span", {}, [
                    h("span", { class: "text-muted-foreground/50" }, " • "),
                    h("span", { class: "capitalize" }, log.log_name),
                  ])
                : null,
            ])
          : null,
      ]);
    },
    size: 300,
    enableSorting: false,
  },
  {
    header: "User",
    accessorKey: "causer_name",
    cell: ({ row }) => {
      const log = row.original;
      return h("div", { class: "space-y-0.5" }, [
        h("div", { class: "text-foreground text-sm tracking-tight" }, log.causer_name || "System"),
        log.causer_id
          ? h("div", { class: "text-muted-foreground text-xs" }, `ID: ${log.causer_id}`)
          : null,
      ]);
    },
    size: 160,
    enableSorting: false,
  },
  {
    header: "Subject",
    accessorKey: "subject_info",
    cell: ({ row }) => {
      const log = row.original;
      return log.subject_info
        ? h("div", { class: "text-foreground text-sm tracking-tight" }, log.subject_info)
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
        h("div", { class: "text-muted-foreground text-sm tracking-tight" }, $dayjs(date).fromNow()),
        [[resolveDirective("tippy"), $dayjs(date).format("MMMM D, YYYY [at] h:mm A")]]
      );
    },
    size: 140,
    enableSorting: true,
  },
  {
    header: "Details",
    accessorKey: "properties",
    cell: ({ row }) => {
      const log = row.original;
      const hasProperties = log.properties && Object.keys(log.properties).length > 0;

      if (!hasProperties) {
        return h("div", { class: "text-muted-foreground text-xs" }, "—");
      }

      return h("details", { class: "cursor-pointer" }, [
        h(
          "summary",
          {
            class:
              "text-primary hover:text-primary/80 text-xs font-medium tracking-tight select-none",
          },
          "View Details"
        ),
        h("div", { class: "mt-2 max-w-md" }, [
          h(
            "pre",
            {
              class:
                "bg-muted/50 text-foreground max-h-48 overflow-auto rounded-lg border p-3 text-xs whitespace-pre-wrap",
            },
            JSON.stringify(log.properties, null, 2)
          ),
        ]),
      ]);
    },
    size: 120,
    enableSorting: false,
  },
];

// Table ref
const tableRef = ref();

// Filter helpers
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
      h("div", { class: "space-y-2.5" }, [
        h(
          "div",
          { class: "text-muted-foreground text-xs font-semibold uppercase tracking-wider" },
          props.title
        ),
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

// Load filter options on mount
onMounted(async () => {
  await Promise.all([loadLogNames(), loadEvents()]);
});
</script>
