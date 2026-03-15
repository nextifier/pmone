<template>
  <div class="mx-auto max-w-2xl space-y-6 py-6">
    <!-- Header -->
    <div class="flex items-end justify-between gap-x-3">
      <div class="flex flex-col items-start gap-y-6">
        <BackButton destination="/dashboard" />
        <div class="min-w-0 flex-1">
          <h2 class="truncate text-lg font-medium tracking-tight">{{ $t("brands.myOrders") }}</h2>
        </div>
      </div>

      <NuxtLink
        :to="`/brands/${route.params.slug}/order-form/${route.params.brandEventId}`"
        class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center gap-x-1.5 rounded-lg px-3 py-1.5 text-sm font-medium tracking-tight transition active:scale-98"
      >
        <Icon name="hugeicons:add-01" class="size-4" />
        {{ $t("orders.newOrder") }}
      </NuxtLink>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 3" :key="`skeleton-${i}`" class="rounded-lg border p-4">
        <div class="flex items-start justify-between gap-x-3">
          <div class="min-w-0 flex-1 space-y-2">
            <div class="flex items-center gap-x-2">
              <Skeleton class="h-4 w-28" />
              <Skeleton class="h-5 w-16 rounded-full" />
            </div>
            <Skeleton class="h-3.5 w-36" />
          </div>
          <Skeleton class="h-4 w-20 shrink-0" />
        </div>
        <div class="mt-3 space-y-1.5">
          <div v-for="j in 2" :key="j" class="flex items-center justify-between">
            <Skeleton class="h-3.5 w-40" />
            <Skeleton class="h-3.5 w-20" />
          </div>
        </div>
      </div>
    </div>

    <!-- Orders list -->
    <template v-else-if="orders.length">
      <div class="space-y-3">
        <NuxtLink
          v-for="order in orders"
          :key="order.ulid"
          :to="`/brands/${route.params.slug}/orders/${route.params.brandEventId}/${order.ulid}`"
          class="hover:bg-muted/50 block rounded-lg border p-4 transition"
        >
          <!-- Top row: order number, status, total -->
          <div class="flex items-start justify-between gap-x-3">
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-x-2">
                <p class="font-mono text-sm font-medium">{{ order.order_number }}</p>
                <Badge :class="statusClass(order.operational_status)" class="capitalize">
                  {{ order.operational_status_label || order.operational_status }}
                </Badge>
              </div>
              <p class="text-muted-foreground mt-1 text-xs tracking-tight sm:text-sm">
                {{ formatDate(order.submitted_at) }}
              </p>
            </div>
            <p class="shrink-0 text-sm font-medium tracking-tight">
              {{ formatPrice(order.total) }}
            </p>
          </div>

          <!-- Onsite order badge -->
          <div
            v-if="order.order_period === 'onsite_order'"
            class="text-muted-foreground mt-2 flex items-center gap-x-1.5 text-xs tracking-tight sm:text-sm"
          >
            <Icon name="hugeicons:alert-02" class="size-3.5 shrink-0 text-amber-500" />
            <span>Onsite Order - includes {{ order.applied_penalty_rate }}% surcharge</span>
          </div>

          <!-- Items list -->
          <div v-if="order.items?.length" class="mt-3 space-y-1">
            <div
              v-for="item in order.items"
              :key="item.id"
              class="text-muted-foreground flex items-center justify-between text-sm tracking-tight"
            >
              <span class="min-w-0 flex-1 truncate">{{ item.product_name }}</span>
              <span class="ml-3 shrink-0 tabular-nums">
                {{ item.quantity }} x {{ formatPrice(item.unit_price) }}
              </span>
            </div>
          </div>
        </NuxtLink>
      </div>
    </template>

    <!-- Empty state -->
    <div v-else class="border-border flex flex-col items-center gap-3 rounded-xl border px-4 py-12">
      <div class="bg-muted flex size-12 items-center justify-center rounded-full">
        <Icon name="hugeicons:shopping-bag-01" class="text-muted-foreground size-6" />
      </div>
      <div class="text-center">
        <p class="text-sm font-medium">{{ $t("orders.noOrdersYet") }}</p>
        <p class="text-muted-foreground mt-1 text-xs tracking-tight sm:text-sm">
          {{ $t("orders.placeFirstOrder") }}
        </p>
      </div>
      <NuxtLink
        :to="`/brands/${route.params.slug}/order-form/${route.params.brandEventId}`"
        class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center gap-x-1.5 rounded-lg px-3 py-1.5 text-sm font-medium tracking-tight transition active:scale-98"
      >
        <Icon name="hugeicons:add-01" class="size-4" />
        {{ $t("orders.placeAnOrder") }}
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
