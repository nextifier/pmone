<template>
  <div class="mx-auto space-y-6 px-4 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:notification-02" class="size-5 sm:size-6" />
        <h1 class="page-title">Announcements</h1>
      </div>

      <div class="ml-auto flex shrink-0 gap-1 sm:gap-2">
        <NuxtLink
          v-if="canDelete"
          to="/announcements/trash"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
          <span>Trash</span>
        </NuxtLink>
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
    </TableData>
  </div>
</template>

<script setup>
import AnnouncementTableItem from "@/components/announcement/TableItem.vue";
import { Button } from "@/components/ui/button";
import { TableData } from "@/components/ui/table-data";

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

const columns = [
  {
    header: "Announcement",
    accessorKey: "title",
    cell: ({ row }) => h(AnnouncementTableItem, { announcement: row.original }),
    size: 400,
    enableHiding: false,
  },
  {
    header: "Schedule",
    accessorKey: "start_time",
    cell: ({ row }) => {
      const start = row.original.start_time;
      const end = row.original.end_time;
      if (!start && !end) {
        return h(
          "span",
          { class: "text-muted-foreground text-xs tracking-tight" },
          "Always on"
        );
      }
      const formatter = new Intl.DateTimeFormat("en-US", {
        month: "short",
        day: "numeric",
        year: "numeric",
      });
      const text =
        (start ? formatter.format(new Date(start)) : "—") +
        " → " +
        (end ? formatter.format(new Date(end)) : "—");
      return h("span", { class: "text-xs tracking-tight" }, text);
    },
    size: 200,
    enableSorting: true,
  },
  {
    header: "Order",
    accessorKey: "order_column",
    cell: ({ row }) =>
      h(
        "span",
        { class: "text-muted-foreground text-xs tracking-tight" },
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
        { class: "text-muted-foreground text-xs tracking-tight" },
        row.original.dismissals_count ?? 0
      ),
    size: 100,
  },
];
</script>
