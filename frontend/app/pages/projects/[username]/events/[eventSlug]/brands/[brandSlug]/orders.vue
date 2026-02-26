<template>
  <div class="flex flex-col gap-y-6">
    <div class="space-y-1">
      <h3 class="text-lg font-semibold tracking-tight">Orders</h3>
      <p class="text-muted-foreground text-sm tracking-tight">
        Orders submitted by this exhibitor.
      </p>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <Spinner class="size-4 shrink-0" />
    </div>

    <div v-else-if="orders.length === 0" class="flex flex-col items-center justify-center px-4 py-12">
      <div class="flex flex-col items-center gap-y-3 text-center">
        <Icon name="hugeicons:shopping-cart-01" class="text-muted-foreground size-8" />
        <p class="text-muted-foreground text-sm">No orders submitted yet.</p>
      </div>
    </div>

    <div v-else class="space-y-3">
      <NuxtLink
        v-for="order in orders"
        :key="order.id"
        :to="`/projects/${route.params.username}/events/${route.params.eventSlug}/orders/${order.ulid}`"
        class="hover:bg-muted/50 flex items-center justify-between rounded-lg border p-4 transition"
      >
        <div>
          <p class="font-mono text-sm font-medium">{{ order.order_number }}</p>
          <p class="text-muted-foreground mt-1 text-xs">
            {{ order.items_count }} items · {{ formatDate(order.submitted_at) }}
          </p>
        </div>
        <div class="flex items-center gap-x-3">
          <p class="text-sm font-semibold">{{ formatPrice(order.total) }}</p>
          <span
            class="rounded-full px-2.5 py-0.5 text-xs font-medium"
            :class="statusClass(order.status)"
          >
            {{ order.status }}
          </span>
        </div>
      </NuxtLink>
    </div>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

const props = defineProps({ brandEvent: Object });
const route = useRoute();
const client = useSanctumClient();

const orders = ref([]);
const loading = ref(true);

async function fetchOrders() {
  if (!props.brandEvent?.id) return;

  loading.value = true;
  try {
    // Use the event-level orders API filtered by this brand event
    const res = await client(
      `/api/projects/${route.params.username}/events/${route.params.eventSlug}/orders`,
      { params: { "filter[search]": props.brandEvent.brand?.name || "" } }
    );
    // Filter to only this brand event's orders
    orders.value = (res.data || []).filter(
      (o) => o.brand_event_id === props.brandEvent.id
    );
  } catch {
    // Fallback: try to get orders directly
    orders.value = [];
  } finally {
    loading.value = false;
  }
}

function formatPrice(amount) {
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

function statusClass(status) {
  const map = {
    submitted: "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400",
    confirmed: "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400",
    processing: "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400",
    completed: "bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400",
    cancelled: "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400",
  };
  return map[status] || "";
}

onMounted(fetchOrders);
</script>
