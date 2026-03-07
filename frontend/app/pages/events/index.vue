<template>
  <ExhibitorMyEvents v-if="isExhibitor" />
  <div v-else class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:calendar-03" class="size-5 sm:size-6" />
        <h1 class="page-title">Events</h1>
      </div>

      <div class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <Button v-if="canCreate" size="sm" @click="navigateTo('/events/create')">
          <Icon name="hugeicons:add-01" class="size-4 shrink-0" />
          Create Event
          <KbdGroup>
            <Kbd>C</Kbd>
          </KbdGroup>
        </Button>
      </div>
    </div>

    <TableData
      ref="tableRef"
      :data="data"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="events"
      label="Event"
      search-column="title"
      search-placeholder="Search events..."
      error-title="Error loading events"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
      :initial-column-visibility="{ status: false }"
      :show-add-button="false"
      @update:pagination="onPaginationUpdate"
      @update:sorting="onSortingUpdate"
      @update:column-filters="onColumnFiltersUpdate"
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
          <PopoverContent class="w-auto min-w-48 p-3 pb-4.5" align="end">
            <div class="space-y-4">
              <FilterSection
                title="Status"
                :options="[
                  { label: 'Draft', value: 'draft' },
                  { label: 'Published', value: 'published' },
                  { label: 'Archived', value: 'archived' },
                  { label: 'Cancelled', value: 'cancelled' },
                ]"
                :selected="selectedStatuses"
                @change="handleFilterChange('status', $event)"
              />
              <FilterSection
                v-if="projectOptions.length > 0"
                title="Project"
                :options="projectOptions"
                :selected="selectedProjects"
                @change="handleFilterChange('project', $event)"
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
import { PopoverClose } from "reka-ui";
import { resolveDirective, withDirectives } from "vue";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["events.read"],
  permissionsExemptRoles: ["exhibitor"],
  layout: "app",
});

usePageMeta(null, {
  title: "Events",
});

defineOptions({
  name: "events",
});

const { hasRole, isStaffOrAbove, hasPermission } = usePermission();
const isExhibitor = computed(() => hasRole("exhibitor") && !isStaffOrAbove.value);

const { $dayjs } = useNuxtApp();

const canCreate = computed(() => hasPermission("events.create"));

// Table state
const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 15 });
const sorting = ref([{ id: "start_date", desc: true }]);

// Build query params
const buildQueryParams = () => {
  const params = new URLSearchParams();

  params.append("page", pagination.value.pageIndex + 1);
  params.append("per_page", pagination.value.pageSize);

  // Filters
  const filters = {
    title: "filter_search",
    status: "filter_status",
    project: "filter_project",
  };

  Object.entries(filters).forEach(([columnId, paramKey]) => {
    const filter = columnFilters.value.find((f) => f.id === columnId);
    if (filter?.value) {
      const value = Array.isArray(filter.value) ? filter.value.join(",") : filter.value;
      params.append(paramKey, value);
    }
  });

  // Sorting
  const sortField = sorting.value[0]?.id || "start_date";
  const sortDirection = sorting.value[0]?.desc ? "desc" : "asc";
  params.append("sort", sortDirection === "desc" ? `-${sortField}` : sortField);

  return params.toString();
};

// Fetch events
const {
  data: eventsResponse,
  pending,
  error,
  refresh: fetchEvents,
} = await useLazySanctumFetch(() => `/api/events?${buildQueryParams()}`, {
  key: "events-all-list",
  watch: false,
});

const data = computed(() => eventsResponse.value?.data || []);
const meta = computed(
  () => eventsResponse.value?.meta || { current_page: 1, last_page: 1, per_page: 15, total: 0 }
);

// Fetch projects for filter
const { data: projectsResponse } = await useLazySanctumFetch("/api/projects?client_only=true", {
  key: "events-projects-filter",
});
const projectOptions = computed(() =>
  (projectsResponse.value?.data || []).map((p) => ({
    label: p.name,
    value: p.id,
  }))
);

// Watch for changes and refetch
watch(
  [columnFilters, sorting, pagination],
  () => {
    fetchEvents();
  },
  { deep: true }
);

// Update handlers
const onPaginationUpdate = (newValue) => {
  pagination.value.pageIndex = newValue.pageIndex;
  pagination.value.pageSize = newValue.pageSize;
};

const onSortingUpdate = (newValue) => {
  sorting.value = newValue;
};

const onColumnFiltersUpdate = (newValue) => {
  columnFilters.value = newValue;
};

// Handle keepalive reactivation
const { getRefreshSignal, clearRefreshSignal } = useDataRefresh();
onActivated(async () => {
  const refreshSignal = getRefreshSignal("events-all-list");
  if (refreshSignal > 0) {
    await fetchEvents();
    clearRefreshSignal("events-all-list");
  }
});

const refresh = fetchEvents;

// Status badge config
const statusConfig = {
  ongoing: { label: "Ongoing", class: "text-destructive-foreground" },
  upcoming: { label: "Upcoming", class: "text-info-foreground" },
  completed: { label: "Completed", class: "text-success-foreground" },
  no_date: { label: "No date", class: "text-muted-foreground" },
};

// Table columns
const columns = [
  {
    header: "Event",
    accessorKey: "title",
    cell: ({ row }) => {
      const event = row.original;
      const projectUsername = event.project_username;
      const link = projectUsername
        ? `/projects/${projectUsername}/events/${event.slug}`
        : "#";

      return h("div", { class: "flex items-center gap-x-3" }, [
        // Poster image
        h(
          resolveComponent("NuxtLink"),
          {
            to: link,
            class:
              "bg-muted border-border aspect-4/5 w-12 shrink-0 overflow-hidden rounded-md border",
          },
          {
            default: () =>
              event.poster_image?.sm
                ? [
                    h("img", {
                      src: event.poster_image.sm,
                      alt: event.title,
                      class: "size-full object-cover select-none",
                      loading: "lazy",
                    }),
                  ]
                : [],
          }
        ),
        // Info
        h("div", { class: "flex flex-col items-start gap-y-0.5 overflow-hidden" }, [
          // Status badges
          h("div", { class: "flex items-center gap-x-2" }, [
            // Draft badge
            ...(event.status === "draft"
              ? [
                  h(
                    "span",
                    {
                      class:
                        "text-warning-foreground text-xs font-medium tracking-tight",
                    },
                    "Draft"
                  ),
                ]
              : []),
            // Time status
            ...(event.time_status
              ? [
                  h(
                    "span",
                    {
                      class: `text-xs font-medium tracking-tight ${statusConfig[event.time_status]?.class || statusConfig.no_date.class}`,
                    },
                    event.time_status === "upcoming" && event.start_date
                      ? `Starts ${$dayjs(event.start_date).fromNow()}`
                      : statusConfig[event.time_status]?.label || "No date"
                  ),
                ]
              : []),
            // Active badge
            ...(event.is_active
              ? [
                  h(
                    "span",
                    {
                      class:
                        "shrink-0 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[11px] font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400",
                    },
                    "Active"
                  ),
                ]
              : []),
          ]),
          // Title as link
          h(
            resolveComponent("NuxtLink"),
            {
              to: link,
              class: "line-clamp-1 font-medium tracking-tight transition hover:opacity-80",
            },
            { default: () => event.title }
          ),
          // Date & Location
          h("div", { class: "text-muted-foreground flex items-center gap-x-3 text-xs tracking-tight" }, [
            ...(event.date_label
              ? [
                  h("span", { class: "flex items-center gap-x-1" }, [
                    h(resolveComponent("Icon"), {
                      name: "hugeicons:calendar-03",
                      class: "size-3.5 shrink-0",
                    }),
                    h("span", { class: "line-clamp-1" }, event.date_label),
                  ]),
                ]
              : []),
            ...(event.location
              ? [
                  h("span", { class: "flex items-center gap-x-1" }, [
                    h(resolveComponent("Icon"), {
                      name: "hugeicons:location-01",
                      class: "size-3.5 shrink-0",
                    }),
                    h("span", { class: "line-clamp-1" }, event.location),
                  ]),
                ]
              : []),
          ]),
        ]),
      ]);
    },
    size: 400,
    enableHiding: false,
    filterFn: (row, columnId, filterValue) => {
      const searchValue = filterValue.toLowerCase();
      const title = row.original.title?.toLowerCase() || "";
      const location = row.original.location?.toLowerCase() || "";
      return title.includes(searchValue) || location.includes(searchValue);
    },
  },
  {
    header: "Status",
    accessorKey: "status",
    cell: ({ row }) => {
      const status = row.getValue("status");
      return h(
        "span",
        { class: "text-muted-foreground text-sm tracking-tight capitalize" },
        status
      );
    },
    size: 100,
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      return filterValue.includes(row.getValue(columnId));
    },
  },
  {
    header: "Project",
    accessorKey: "project",
    cell: ({ row }) => {
      const event = row.original;
      const projectName = event.project_name;
      const projectUsername = event.project_username;
      if (!projectName) {
        return h("span", { class: "text-muted-foreground text-sm" }, "-");
      }
      return h(
        resolveComponent("NuxtLink"),
        {
          to: `/projects/${projectUsername}`,
          class: "text-muted-foreground text-sm tracking-tight transition hover:underline",
        },
        { default: () => projectName }
      );
    },
    size: 150,
    enableSorting: false,
    filterFn: (row, columnId, filterValue) => {
      if (!Array.isArray(filterValue) || filterValue.length === 0) return true;
      return filterValue.includes(row.original.project_id);
    },
  },
  {
    header: "Exhibitors",
    accessorKey: "brand_events_count",
    cell: ({ row }) => {
      const count = row.getValue("brand_events_count") || 0;
      return h("div", { class: "text-sm tracking-tight" }, count.toLocaleString());
    },
    size: 90,
    enableSorting: false,
  },
  {
    header: "Start Date",
    accessorKey: "start_date",
    cell: ({ row }) => {
      const date = row.getValue("start_date");
      if (!date) {
        return h("span", { class: "text-muted-foreground text-sm" }, "-");
      }
      return withDirectives(
        h(
          "div",
          { class: "text-muted-foreground text-sm tracking-tight" },
          $dayjs(date).format("MMM D, YYYY")
        ),
        [[resolveDirective("tippy"), $dayjs(date).format("MMMM D, YYYY [at] h:mm A")]]
      );
    },
    size: 120,
  },
  {
    id: "actions",
    header: () => h("span", { class: "sr-only" }, "Actions"),
    cell: ({ row }) => {
      const event = row.original;
      const projectUsername = event.project_username;
      if (!projectUsername) return null;

      return h("div", { class: "flex justify-end" }, [
        h(
          resolveComponent("NuxtLink"),
          {
            to: `/projects/${projectUsername}/events/${event.slug}`,
            class:
              "hover:bg-muted inline-flex size-8 items-center justify-center rounded-md",
          },
          {
            default: () =>
              h(resolveComponent("Icon"), {
                name: "lucide:arrow-right",
                class: "size-4",
              }),
          }
        ),
      ]);
    },
    size: 60,
    enableHiding: false,
  },
];

// Table ref
const tableRef = ref();

// Filter helpers
const getFilterValue = (columnId) => {
  return columnFilters.value.find((f) => f.id === columnId)?.value ?? [];
};

const selectedStatuses = computed(() => getFilterValue("status"));
const selectedProjects = computed(() => getFilterValue("project"));
const totalActiveFilters = computed(
  () => selectedStatuses.value.length + selectedProjects.value.length
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

const isPageActive = ref(true);
onActivated(() => { isPageActive.value = true; });
onDeactivated(() => { isPageActive.value = false; });

defineShortcuts({
  c: {
    handler: () => {
      if (canCreate.value) {
        navigateTo("/events/create");
      }
    },
    whenever: [isPageActive],
  },
});
</script>
