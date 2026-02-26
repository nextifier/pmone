<template>
  <div class="mx-auto max-w-2xl space-y-6 py-6">
    <!-- Header -->
    <div class="flex items-center justify-between gap-x-3">
      <div class="flex items-center gap-x-3">
        <NuxtLink
          :to="`/brands/${route.params.slug}`"
          class="text-muted-foreground hover:text-foreground flex size-8 items-center justify-center rounded-lg transition"
        >
          <Icon name="hugeicons:arrow-left-01" class="size-5" />
        </NuxtLink>
        <div class="min-w-0 flex-1">
          <h2 class="truncate text-lg font-bold tracking-tight">{{ $t('brands.myOrders') }}</h2>
        </div>
      </div>

      <NuxtLink
        :to="`/brands/${route.params.slug}/order-form/${route.params.brandEventId}`"
        class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center gap-x-1.5 rounded-lg px-3 py-1.5 text-sm font-medium tracking-tight transition active:scale-98"
      >
        <Icon name="hugeicons:add-01" class="size-4" />
        {{ $t('orders.newOrder') }}
      </NuxtLink>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <Icon name="svg-spinners:ring-resize" class="text-muted-foreground size-6" />
    </div>

    <!-- Orders list -->
    <template v-else-if="orders.length">
      <div class="space-y-2">
        <NuxtLink
          v-for="order in orders"
          :key="order.ulid"
          :to="`/brands/${route.params.slug}/orders/${route.params.brandEventId}/${order.ulid}`"
          class="hover:bg-muted/50 flex items-center justify-between rounded-lg border p-4 transition"
        >
          <div>
            <p class="font-mono text-sm font-medium">{{ order.order_number }}</p>
            <p class="text-muted-foreground mt-1 text-xs">
              {{ order.items_count }} {{ $t('common.item', order.items_count) }} &middot; {{ formatDate(order.submitted_at) }}
            </p>
          </div>
          <div class="flex items-center gap-x-3">
            <p class="text-sm font-semibold">{{ formatPrice(order.total) }}</p>
            <Badge :class="statusClass(order.status)" class="capitalize">
              {{ order.status }}
            </Badge>
          </div>
        </NuxtLink>
      </div>
    </template>

    <!-- Empty state -->
    <div
      v-else
      class="border-border flex flex-col items-center gap-3 rounded-xl border px-4 py-12"
    >
      <div class="bg-muted flex size-12 items-center justify-center rounded-full">
        <Icon name="hugeicons:shopping-bag-01" class="text-muted-foreground size-6" />
      </div>
      <div class="text-center">
        <p class="text-sm font-medium">{{ $t('orders.noOrdersYet') }}</p>
        <p class="text-muted-foreground mt-1 text-xs">
          {{ $t('orders.placeFirstOrder') }}
        </p>
      </div>
      <NuxtLink
        :to="`/brands/${route.params.slug}/order-form/${route.params.brandEventId}`"
        class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center gap-x-1.5 rounded-lg px-3 py-1.5 text-sm font-medium tracking-tight transition active:scale-98"
      >
        <Icon name="hugeicons:add-01" class="size-4" />
        {{ $t('orders.placeAnOrder') }}
      </NuxtLink>
    </div>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { toast } from "vue-sonner";

const { t } = useI18n();

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta(null, { title: t("brands.myOrders") });

const route = useRoute();
const client = useSanctumClient();
const orders = ref([]);
const loading = ref(true);

async function fetchOrders() {
  loading.value = true;
  try {
    const res = await client(
      `/api/exhibitor/brands/${route.params.slug}/events/${route.params.brandEventId}/orders`
    );
    orders.value = res.data;
  } catch {
    toast.error(t("orders.failedToLoad"));
  } finally {
    loading.value = false;
  }
}

const { formatPrice, formatDateId: formatDate, orderStatusClass: statusClass } = useFormatters();

onMounted(fetchOrders);
</script>
