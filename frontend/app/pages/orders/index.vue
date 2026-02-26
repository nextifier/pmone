<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:shopping-bag-01" class="size-5 sm:size-6" />
        <h1 class="page-title">{{ $t('orders.title') }}</h1>
      </div>
    </div>

    <div
      v-if="isExhibitor"
      class="border-border bg-muted/30 flex items-start gap-x-3 rounded-lg border p-4"
    >
      <Icon name="hugeicons:information-circle" class="text-muted-foreground mt-0.5 size-5 shrink-0" />
      <div class="min-w-0 space-y-1 text-sm tracking-tight">
        <p class="text-muted-foreground">
          {{ $t('orders.howToOrderInfo') }}
        </p>
        <NuxtLink
          to="/brands"
          class="text-primary inline-flex items-center gap-x-1 font-medium hover:underline"
        >
          {{ $t('orders.goToBrands') }}
          <Icon name="hugeicons:arrow-right-01" class="size-3.5" />
        </NuxtLink>
      </div>
    </div>

    <TableData
      ref="tableRef"
      :client-only="true"
      :data="data"
      :columns="columns"
      :meta="meta"
      :pending="pending"
      :error="error"
      model="orders"
      :label="$t('orders.order')"
      search-column="order_number"
      :search-placeholder="$t('orders.searchOrders')"
      :error-title="$t('orders.errorLoading')"
      :initial-pagination="{ pageIndex: 0, pageSize: 15 }"
      :initial-sorting="[{ id: 'submitted_at', desc: true }]"
      :show-add-button="false"
      @refresh="refresh"
    >
      <template #filters>
        <Popover>
          <PopoverTrigger asChild>
            <button
              class="hover:bg-muted relative flex aspect-square h-full shrink-0 items-center justify-center gap-x-1.5 rounded-md border text-sm tracking-tight active:scale-98 sm:aspect-auto sm:px-2.5"
            >
              <Icon name="lucide:list-filter" class="size-4 shrink-0" />
              <span class="hidden sm:flex">{{ $t('common.filter') }}</span>
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
                :title="$t('common.status')"
                :options="[
                  { label: $t('orders.statusSubmitted'), value: 'submitted' },
                  { label: $t('orders.statusConfirmed'), value: 'confirmed' },
                  { label: $t('orders.statusProcessing'), value: 'processing' },
                  { label: $t('orders.statusCompleted'), value: 'completed' },
                  { label: $t('orders.statusCancelled'), value: 'cancelled' },
                ]"
                :selected="selectedStatuses"
                @change="handleFilterChange('status', $event)"
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
import { defineComponent } from "vue";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const { t } = useI18n();

usePageMeta(null, { title: t('orders.title') });

const { $dayjs } = useNuxtApp();
const client = useSanctumClient();
const { hasRole, isStaffOrAbove } = usePermission();

const isExhibitor = computed(() => hasRole("exhibitor") && !isStaffOrAbove.value);

// Data state
const ordersResponse = ref(null);
const pending = ref(false);
const error = ref(null);

const data = computed(() => ordersResponse.value?.data || []);
const meta = computed(
  () => ordersResponse.value?.meta || { current_page: 1, last_page: 1, per_page: 15, total: 0 }
);

async function refresh() {
  pending.value = true;
  error.value = null;
  try {
    ordersResponse.value = await client("/api/orders?client_only=true");
  } catch (e) {
    error.value = e;
  }
  pending.value = false;
}

onMounted(() => refresh());

// Table ref
const tableRef = ref();

// Filter helpers
const getFilterValue = (columnId) => {
  if (tableRef.value?.table) {
    return tableRef.value.table.getColumn(columnId)?.getFilterValue() ?? [];
  }
  return [];
};

const selectedStatuses = computed(() => getFilterValue("status"));
const totalActiveFilters = computed(() => selectedStatuses.value.length);

const handleFilterChange = (columnId, { checked, value }) => {
  if (tableRef.value?.table) {
    const column = tableRef.value.table.getColumn(columnId);
    if (!column) return;

    const current = column.getFilterValue() ?? [];
    const updated = checked ? [...current, value] : current.filter((item) => item !== value);

    column.setFilterValue(updated.length > 0 ? updated : undefined);
    tableRef.value.table.setPageIndex(0);
  }
};

// Helper: build detail URL based on role
function orderDetailUrl(order) {
  const be = order.brand_event;
  if (isExhibitor.value) {
    return `/brands/${be?.brand?.slug}/orders/${order.brand_event_id}/${order.ulid}`;
  }
  // Staff: link to project-scoped detail page
  const project = be?.event?.project;
  if (project?.username && be?.event?.slug) {
    return `/projects/${project.username}/events/${be.event.slug}/orders/${order.ulid}`;
  }
  // Fallback
  return `/brands/${be?.brand?.slug}/orders/${order.brand_event_id}/${order.ulid}`;
}

// Helper: format price
function formatPrice(amount) {
  if (amount == null) return "-";
  return `Rp ${Number(amount).toLocaleString("id-ID")}`;
}

// Status color map
const statusClasses = {
  submitted: "text-blue-700 dark:text-blue-400",
  confirmed: "text-green-700 dark:text-green-400",
  processing: "text-amber-700 dark:text-amber-400",
  completed: "text-emerald-700 dark:text-emerald-400",
  cancelled: "text-red-600 dark:text-red-400",
};

// Table columns
const columns = computed(() => [
  {
    header: t('orders.order'),
    accessorKey: "order_number",
    cell: ({ row }) => {
      const order = row.original;
      const be = order.brand_event;
      const NuxtLink = resolveComponent("NuxtLink");
      return h("div", { class: "min-w-0" }, [
        h(
          NuxtLink,
          {
            to: orderDetailUrl(order),
            class: "font-mono text-sm font-medium tracking-tight hover:underline",
          },
          () => order.order_number
        ),
        h("div", { class: "text-muted-foreground mt-0.5 flex flex-wrap items-center gap-x-1 text-xs tracking-tight" }, [
          be?.brand?.name
            ? h("span", { class: "font-medium text-foreground" }, be.brand.name)
            : null,
          be?.event?.title ? h("span", {}, `· ${be.event.title}`) : null,
        ]),
      ]);
    },
    size: 300,
    enableHiding: false,
    filterFn: (row, columnId, filterValue) => {
      const searchValue = filterValue.toLowerCase();
      const orderNumber = row.original.order_number?.toLowerCase() || "";
      const brandName = row.original.brand_event?.brand?.name?.toLowerCase() || "";
      const companyName = row.original.brand_event?.brand?.company_name?.toLowerCase() || "";
      return (
        orderNumber.includes(searchValue) ||
        brandName.includes(searchValue) ||
        companyName.includes(searchValue)
      );
    },
  },
  {
    header: t('orders.status'),
    accessorKey: "status",
    cell: ({ row }) => {
      const status = row.getValue("status");
      const classes = statusClasses[status] || "text-muted-foreground";
      return h(
        "span",
        { class: `inline-flex items-center text-sm tracking-tight capitalize ${classes}` },
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
    header: t('orders.items'),
    accessorKey: "items_count",
    cell: ({ row }) => {
      const count = row.getValue("items_count") || 0;
      return h("div", { class: "text-sm tracking-tight" }, count.toLocaleString());
    },
    size: 70,
  },
  {
    header: t('orders.total'),
    accessorKey: "total",
    cell: ({ row }) => {
      return h(
        "div",
        { class: "text-sm font-medium tracking-tight" },
        formatPrice(row.getValue("total"))
      );
    },
    size: 130,
  },
  {
    header: t('orders.submitted'),
    accessorKey: "submitted_at",
    cell: ({ row }) => {
      const date = row.getValue("submitted_at");
      if (!date) return h("span", { class: "text-muted-foreground text-sm" }, "-");
      return h(
        "div",
        { class: "text-muted-foreground text-sm tracking-tight" },
        $dayjs(date).fromNow()
      );
    },
    size: 120,
  },
  {
    header: t('orders.createdBy'),
    accessorKey: "creator",
    cell: ({ row }) => {
      const creator = row.getValue("creator");
      if (!creator) return h("span", { class: "text-muted-foreground text-sm" }, "-");
      return h("div", { class: "text-sm tracking-tight" }, creator.name);
    },
    size: 140,
    enableSorting: false,
  },
]);

// Filter Section Component (same pattern as brands)
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
