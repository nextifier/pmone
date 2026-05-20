<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex flex-col gap-y-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:discount-tag-02" class="size-5 sm:size-6" />
        <h1 class="page-title">Promotion Rules</h1>
      </div>

      <div class="flex shrink-0 gap-1 sm:gap-2">
        <NuxtLink
          v-if="canDelete"
          to="/promotion-rules/trash"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
        >
          <Icon name="hugeicons:delete-01" class="size-4 shrink-0" />
          <span>Trash</span>
        </NuxtLink>
      </div>
    </div>

    <TableData
      ref="tableRef"
      :data="data"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="promotion-rules"
      label="Promotion Rule"
      search-column="name"
      search-placeholder="Search rules..."
      error-title="Error loading promotion rules"
      :initial-pagination="pagination"
      :initial-sorting="sorting"
      :initial-column-filters="columnFilters"
      :show-add-button="canCreate"
      @update:pagination="onPaginationUpdate"
      @update:sorting="onSortingUpdate"
      @update:column-filters="onColumnFiltersUpdate"
      @refresh="refresh"
    >
      <template #add-button>
        <Button v-if="canCreate" size="sm" @click="navigateTo('/promotion-rules/create')">
          <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
          New Rule
        </Button>
      </template>
    </TableData>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["promotion_rules.read"],
  layout: "app",
});

usePageMeta(null, { title: "Promotion Rules" });

const { hasPermission } = usePermission();
const canCreate = computed(() => hasPermission("promotion_rules.create"));
const canDelete = computed(() => hasPermission("promotion_rules.delete"));

const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 15 });
const sorting = ref([{ id: "created_at", desc: true }]);

const buildQueryParams = () => {
  const params = new URLSearchParams();
  params.append("page", pagination.value.pageIndex + 1);
  params.append("per_page", pagination.value.pageSize);

  const filters = {
    name: "filter_search",
    kind: "filter_kind",
    is_active: "filter_is_active",
    trigger_type: "filter_trigger_type",
  };
  Object.entries(filters).forEach(([colId, key]) => {
    const filter = columnFilters.value.find((f) => f.id === colId);
    if (filter?.value !== undefined && filter?.value !== "") {
      const value = Array.isArray(filter.value) ? filter.value.join(",") : filter.value;
      params.append(key, value);
    }
  });

  const sortField = sorting.value[0]?.id || "created_at";
  const sortDir = sorting.value[0]?.desc ? "desc" : "asc";
  params.append("sort", sortDir === "desc" ? `-${sortField}` : sortField);
  return params.toString();
};

const {
  data: response,
  pending,
  error,
  refresh: fetchRules,
} = await useLazySanctumFetch(() => `/api/promotion-rules?${buildQueryParams()}`, {
  key: "promotion-rules-list",
  watch: false,
});

const data = computed(() => response.value?.data || []);
const meta = computed(
  () => response.value?.meta || { current_page: 1, last_page: 1, per_page: 15, total: 0 },
);

watch([columnFilters, sorting, pagination], () => fetchRules(), { deep: true });

const onPaginationUpdate = (val) => {
  pagination.value.pageIndex = val.pageIndex;
  pagination.value.pageSize = val.pageSize;
};
const onSortingUpdate = (val) => (sorting.value = val);
const onColumnFiltersUpdate = (val) => (columnFilters.value = val);
const refresh = fetchRules;

const tableRef = ref();

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);

const columns = [
  {
    header: "Name",
    accessorKey: "name",
    cell: ({ row }) =>
      h(
        NuxtLink,
        {
          to: `/promotion-rules/${row.original.ulid}/show`,
          class: "font-medium tracking-tight hover:underline",
        },
        () => row.original.name,
      ),
    size: 250,
    enableHiding: false,
  },
  {
    header: "Kind",
    accessorKey: "kind",
    cell: ({ row }) => {
      const kind = row.original.kind;
      return h(
        Badge,
        {
          variant: kind === "discount" ? "success" : "warning",
        },
        { default: () => row.original.kind_label || kind },
      );
    },
    size: 120,
  },
  {
    header: "Value",
    accessorKey: "value",
    cell: ({ row }) => {
      const r = row.original;
      const display = r.value_type === "percentage" ? `${r.value}%` : `Rp${formatRupiah(r.value)}`;
      return h("span", { class: "text-sm tabular-nums tracking-tight" }, display);
    },
    size: 120,
  },
  {
    header: "Stacking",
    accessorKey: "stacking_mode",
    cell: ({ row }) =>
      h(
        "span",
        { class: "text-muted-foreground text-xs tracking-tight sm:text-sm" },
        row.original.stacking_mode_label ||
          row.original.stacking_mode?.replace(/_/g, " ") ||
          "-",
      ),
    size: 180,
  },
  {
    header: "Active",
    accessorKey: "is_active",
    cell: ({ row }) =>
      h(
        Badge,
        {
          variant: row.original.is_active ? "success" : "muted",
          withIcon: true,
          plain: true,
        },
        { default: () => (row.original.is_active ? "Active" : "Inactive") },
      ),
    size: 100,
  },
  {
    header: "Codes",
    accessorKey: "codes_count",
    cell: ({ row }) => {
      const n = row.original.codes_count ?? 0;
      return h(
        "span",
        {
          class: `tabular-nums tracking-tight text-xs sm:text-sm ${n === 0 ? "text-muted-foreground/50" : "text-muted-foreground"}`,
        },
        n,
      );
    },
    size: 80,
  },
  {
    header: "Used",
    accessorKey: "applied_count",
    cell: ({ row }) => {
      const n = row.original.applied_count ?? 0;
      return h(
        "span",
        {
          class: `tabular-nums tracking-tight text-xs sm:text-sm ${n === 0 ? "text-muted-foreground/50" : "text-muted-foreground"}`,
        },
        n,
      );
    },
    size: 80,
  },
];
</script>
