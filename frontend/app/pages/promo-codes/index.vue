<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex items-center gap-x-2.5">
      <Icon name="hugeicons:coupon-03" class="size-5 sm:size-6" />
      <h1 class="page-title">Promo Codes</h1>
    </div>

    <TableData
      ref="tableRef"
      :data="data"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="promo-codes"
      label="Promo Code"
      search-column="code"
      search-placeholder="Search by code or email..."
      error-title="Error loading promo codes"
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
        <Button v-if="canCreate" size="sm" @click="navigateTo('/promo-codes/create')">
          <Icon name="lucide:plus" class="-ml-1 size-4 shrink-0" />
          New Code
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
  permissions: ["promo_codes.read"],
  layout: "app",
});

usePageMeta(null, { title: "Promo Codes" });

const { hasPermission } = usePermission();
const canCreate = computed(() => hasPermission("promo_codes.create"));
const route = useRoute();

const columnFilters = ref([]);
const pagination = ref({ pageIndex: 0, pageSize: 15 });
const sorting = ref([{ id: "created_at", desc: true }]);

// Support deep-link filter (e.g. ?filter_rule_id=12)
if (route.query.filter_rule_id) {
  columnFilters.value.push({ id: "rule_id", value: route.query.filter_rule_id });
}

const buildQueryParams = () => {
  const params = new URLSearchParams();
  params.append("page", pagination.value.pageIndex + 1);
  params.append("per_page", pagination.value.pageSize);

  const filters = {
    code: "filter_search",
    rule_id: "filter_rule_id",
    event_id: "filter_event_id",
    is_active: "filter_is_active",
    exhausted: "filter_exhausted",
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
  refresh: fetchCodes,
} = await useLazySanctumFetch(() => `/api/promo-codes?${buildQueryParams()}`, {
  key: "promo-codes-list",
  watch: false,
});

const data = computed(() => response.value?.data || []);
const meta = computed(
  () => response.value?.meta || { current_page: 1, last_page: 1, per_page: 15, total: 0 },
);

watch([columnFilters, sorting, pagination], () => fetchCodes(), { deep: true });

const onPaginationUpdate = (val) => {
  pagination.value.pageIndex = val.pageIndex;
  pagination.value.pageSize = val.pageSize;
};
const onSortingUpdate = (val) => (sorting.value = val);
const onColumnFiltersUpdate = (val) => (columnFilters.value = val);
const refresh = fetchCodes;

const tableRef = ref();

const formatRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);

const columns = [
  {
    header: "Code",
    accessorKey: "code",
    cell: ({ row }) =>
      h(
        NuxtLink,
        {
          to: `/promo-codes/${row.original.ulid}/show`,
          class: "font-mono text-sm hover:underline",
        },
        () => row.original.code,
      ),
    size: 180,
    enableHiding: false,
  },
  {
    header: "Rule",
    accessorKey: "promotion_rule.name",
    cell: ({ row }) => {
      const rule = row.original.promotion_rule;
      if (!rule)
        return h(
          "span",
          { class: "text-muted-foreground text-xs tracking-tight sm:text-sm" },
          "-",
        );
      const display =
        rule.value_type === "percentage" ? `${rule.value}%` : `Rp${formatRupiah(rule.value)}`;
      return h(
        NuxtLink,
        {
          to: `/promotion-rules/${rule.ulid}/show`,
          class: "text-sm tracking-tight hover:underline",
        },
        () => `${rule.name} (${display})`,
      );
    },
    size: 250,
  },
  {
    header: "Usage",
    accessorKey: "usage_count",
    cell: ({ row }) => {
      const used = row.original.usage_count ?? 0;
      const limit = row.original.usage_limit ?? "∞";
      return h(
        "span",
        { class: "text-sm tabular-nums tracking-tight" },
        `${used} / ${limit}`,
      );
    },
    size: 100,
  },
  {
    header: "Valid Until",
    accessorKey: "valid_until",
    cell: ({ row }) => {
      const v = row.original.valid_until;
      if (!v)
        return h(
          "span",
          { class: "text-muted-foreground/70 text-xs tracking-tight sm:text-sm" },
          "No expiry",
        );
      return h(
        "span",
        { class: "text-xs tracking-tight tabular-nums sm:text-sm" },
        new Date(v).toLocaleDateString("id-ID"),
      );
    },
    size: 130,
  },
  {
    header: "Status",
    accessorKey: "is_active",
    cell: ({ row }) => {
      const r = row.original;
      const isExhausted = r.is_exhausted;
      const label = !r.is_active ? "Inactive" : isExhausted ? "Exhausted" : "Active";
      const variant = !r.is_active ? "muted" : isExhausted ? "warning" : "success";
      return h(
        Badge,
        { variant, withIcon: true, plain: true },
        { default: () => label },
      );
    },
    size: 110,
  },
];
</script>
