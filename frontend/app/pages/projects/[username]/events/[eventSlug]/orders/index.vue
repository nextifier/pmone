<template>
  <div class="flex flex-col gap-y-6">
    <!-- Page Header -->
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex items-center gap-x-2.5">
        <h3 class="text-lg font-semibold tracking-tight">Orders</h3>
        <span
          v-if="meta.total"
          class="bg-muted text-muted-foreground inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
        >
          {{ meta.total }}
        </span>
      </div>
    </div>

    <!-- Filters Row -->
    <div class="flex flex-wrap items-center gap-2">
      <!-- Search -->
      <div class="relative min-w-48 flex-1">
        <Icon
          name="lucide:search"
          class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
        />
        <input
          v-model="search"
          type="text"
          placeholder="Search by order # or brand..."
          class="border-border bg-background placeholder:text-muted-foreground focus:ring-ring h-9 w-full rounded-md border pr-3 pl-9 text-sm tracking-tight outline-none focus:ring-1"
        />
      </div>

      <!-- Status Filter Tabs -->
      <div class="flex flex-wrap items-center gap-1">
        <button
          v-for="tab in statusTabs"
          :key="tab.value"
          @click="setStatusFilter(tab.value)"
          class="rounded-md px-3 py-1.5 text-sm tracking-tight transition active:scale-98"
          :class="
            statusFilter === tab.value
              ? 'bg-primary text-primary-foreground font-medium'
              : 'hover:bg-muted text-muted-foreground'
          "
        >
          {{ tab.label }}
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-16">
      <div class="flex items-center gap-x-2">
        <Spinner class="size-4 shrink-0" />
        <span class="text-sm tracking-tight">Loading orders...</span>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-else-if="!loading && orders.length === 0"
      class="flex flex-col items-center justify-center py-16 text-center"
    >
      <Icon name="hugeicons:shopping-cart-01" class="text-muted-foreground size-10" />
      <h4 class="mt-3 font-semibold tracking-tight">No orders found</h4>
      <p class="text-muted-foreground mt-1 text-sm tracking-tight">
        {{
          search || statusFilter
            ? "Try adjusting your search or filter."
            : "Orders will appear here once submitted."
        }}
      </p>
    </div>

    <!-- Table -->
    <div v-else class="border-border overflow-hidden rounded-lg border">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-border border-b">
              <th class="text-muted-foreground px-4 py-3 text-left text-xs font-medium tracking-tight">
                Order #
              </th>
              <th class="text-muted-foreground px-4 py-3 text-left text-xs font-medium tracking-tight">
                Brand
              </th>
              <th class="text-muted-foreground px-4 py-3 text-left text-xs font-medium tracking-tight">
                Items
              </th>
              <th class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-tight">
                Total
              </th>
              <th class="text-muted-foreground px-4 py-3 text-left text-xs font-medium tracking-tight">
                Status
              </th>
              <th class="text-muted-foreground px-4 py-3 text-left text-xs font-medium tracking-tight">
                Submitted
              </th>
              <th class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-tight">
                <span class="sr-only">Actions</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="order in orders"
              :key="order.ulid"
              @click="navigateTo(orderDetailUrl(order))"
              class="border-border hover:bg-muted/50 cursor-pointer border-b transition last:border-0"
            >
              <td class="px-4 py-3">
                <span class="font-mono text-sm font-medium tracking-tight">
                  {{ order.order_number }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex flex-col">
                  <span class="font-medium tracking-tight">{{ order.brand_event?.brand?.name }}</span>
                  <span
                    v-if="order.brand_event?.brand?.company_name"
                    class="text-muted-foreground text-xs tracking-tight"
                  >
                    {{ order.brand_event.brand.company_name }}
                  </span>
                </div>
              </td>
              <td class="px-4 py-3">
                <span class="text-muted-foreground tracking-tight">
                  {{ order.items_count ?? order.items?.length ?? 0 }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <span class="font-medium tracking-tight">
                  {{ formatPrice(order.total) }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                  :class="statusBadgeClass(order.status)"
                >
                  {{ order.status }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span class="text-muted-foreground text-xs tracking-tight">
                  {{ formatDate(order.submitted_at || order.created_at) }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <Icon name="lucide:chevron-right" class="text-muted-foreground size-4" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <div
      v-if="meta.last_page > 1"
      class="flex items-center justify-between gap-4"
    >
      <span class="text-muted-foreground text-sm tracking-tight">
        Page {{ meta.current_page }} of {{ meta.last_page }}
      </span>
      <div class="flex items-center gap-1">
        <button
          :disabled="meta.current_page <= 1"
          @click="goToPage(meta.current_page - 1)"
          class="border-border hover:bg-muted flex h-8 w-8 items-center justify-center rounded-md border text-sm transition disabled:cursor-not-allowed disabled:opacity-40"
        >
          <Icon name="lucide:chevron-left" class="size-4" />
        </button>
        <button
          :disabled="meta.current_page >= meta.last_page"
          @click="goToPage(meta.current_page + 1)"
          class="border-border hover:bg-muted flex h-8 w-8 items-center justify-center rounded-md border text-sm transition disabled:cursor-not-allowed disabled:opacity-40"
        >
          <Icon name="lucide:chevron-right" class="size-4" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

const props = defineProps({ event: Object, project: Object });

usePageMeta(null, {
  title: computed(() => `Orders · ${props.event?.title || "Event"}`),
});

const route = useRoute();
const client = useSanctumClient();

const orders = ref([]);
const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
const loading = ref(true);
const search = ref("");
const statusFilter = ref("");
const page = ref(1);

const statusTabs = [
  { label: "All", value: "" },
  { label: "Submitted", value: "submitted" },
  { label: "Confirmed", value: "confirmed" },
  { label: "Processing", value: "processing" },
  { label: "Completed", value: "completed" },
  { label: "Cancelled", value: "cancelled" },
];

const apiBase = computed(
  () =>
    `/api/projects/${route.params.username}/events/${route.params.eventSlug}/orders`
);

async function fetchOrders() {
  loading.value = true;
  try {
    const params = new URLSearchParams();
    if (search.value) {
      params.set("filter[search]", search.value);
    }
    if (statusFilter.value) {
      params.set("filter[status]", statusFilter.value);
    }
    params.set("page", page.value);

    const res = await client(`${apiBase.value}?${params}`);
    orders.value = res.data;
    meta.value = res.meta;
  } catch (err) {
    toast.error("Failed to load orders", {
      description: err?.data?.message || err?.message || "An error occurred",
    });
  } finally {
    loading.value = false;
  }
}

let searchDebounce = null;

watch([search, statusFilter], () => {
  page.value = 1;
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(fetchOrders, 300);
});

function setStatusFilter(value) {
  statusFilter.value = value;
}

function goToPage(newPage) {
  page.value = newPage;
  fetchOrders();
}

function orderDetailUrl(order) {
  return `/projects/${route.params.username}/events/${route.params.eventSlug}/orders/${order.ulid}`;
}

function formatPrice(amount) {
  if (amount == null) return "-";
  return `Rp ${Number(amount).toLocaleString("id-ID")}`;
}

function formatDate(dateStr) {
  if (!dateStr) return "-";
  return new Date(dateStr).toLocaleDateString("id-ID", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

function statusBadgeClass(status) {
  const map = {
    submitted: "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400",
    confirmed: "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400",
    processing: "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400",
    completed: "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400",
    cancelled: "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400",
  };
  return map[status] ?? "bg-muted text-muted-foreground";
}

onMounted(fetchOrders);
</script>
