<script setup lang="ts">
interface OrderItem {
  ulid: string;
  order_number: string;
  brand_name: string | null;
  event_title: string | null;
  event_slug: string | null;
  project_username: string | null;
  total: number | string | null;
  submitted_at: string | null;
}

defineProps<{
  orders: OrderItem[];
  loading?: boolean;
}>();

const formatPrice = (amount: number | string | null) => {
  if (amount == null) return "-";
  return `Rp ${Number(amount).toLocaleString("id-ID")}`;
};

const formatDate = (dateStr: string | null) => {
  if (!dateStr) return "-";
  return new Date(dateStr).toLocaleDateString("id-ID", {
    day: "numeric",
    month: "short",
  });
};

const getOrderLink = (order: OrderItem) => {
  if (order.project_username && order.event_slug) {
    return `/projects/${order.project_username}/events/${order.event_slug}/orders/${order.ulid}`;
  }
  return `/orders`;
};
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h3 class="page-title text-lg!">Pending Orders</h3>
      <NuxtLink
        to="/orders"
        class="text-muted-foreground hover:text-foreground flex items-center gap-x-1 text-sm tracking-tight"
      >
        <span>View all</span>
        <Icon name="hugeicons:arrow-right-02" class="size-4 shrink-0" />
      </NuxtLink>
    </div>

    <!-- Loading -->
    <template v-if="loading">
      <div class="space-y-3">
        <div v-for="i in 3" :key="i" class="flex items-center justify-between gap-3">
          <Skeleton class="h-3.5 w-28" />
          <Skeleton class="h-3.5 w-20" />
        </div>
      </div>
    </template>

    <!-- Empty -->
    <template v-else-if="!orders || orders.length === 0">
      <div class="flex items-center gap-2 py-4">
        <Icon name="hugeicons:shopping-bag-01" class="text-muted-foreground size-4" />
        <p class="text-muted-foreground text-sm tracking-tight">All caught up</p>
      </div>
    </template>

    <!-- Orders List -->
    <div v-else class="space-y-1">
      <NuxtLink
        v-for="order in orders"
        :key="order.ulid"
        :to="getOrderLink(order)"
        class="flex items-center justify-between gap-3 transition-opacity hover:opacity-80"
      >
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-x-1.5">
            <p class="font-mono text-sm font-medium tracking-tight">
              {{ order.order_number }}
            </p>
            <span class="text-muted-foreground text-xs">·</span>
            <span v-if="order.brand_name" class="text-muted-foreground truncate text-xs tracking-tight">
              {{ order.brand_name }}
            </span>
          </div>
        </div>
        <div class="flex shrink-0 items-center gap-2.5">
          <span class="text-foreground text-sm font-semibold tabular-nums tracking-tight">
            {{ formatPrice(order.total) }}
          </span>
          <span class="text-muted-foreground text-xs tabular-nums tracking-tight">{{ formatDate(order.submitted_at) }}</span>
        </div>
      </NuxtLink>
    </div>
  </div>
</template>
