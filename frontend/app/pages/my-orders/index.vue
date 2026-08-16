<template>
  <div class="mx-auto max-w-2xl space-y-6 py-6">
    <!-- Header -->
    <div class="flex flex-col items-start gap-y-6">
      <ButtonBack destination="/dashboard" force-destination />
      <h2 class="truncate text-lg font-medium tracking-tight">{{ $t("brands.myOrders") }}</h2>
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

    <!-- Orders, grouped by event then by brand + booth -->
    <template v-else-if="eventGroups.length">
      <section v-for="group in eventGroups" :key="group.id" class="space-y-4">
        <div>
          <h3 class="text-base font-medium tracking-tighter sm:text-lg">{{ group.title }}</h3>
          <p v-if="group.dateLabel" class="text-muted-foreground text-sm tracking-tight">
            {{ group.dateLabel }}
          </p>
        </div>

        <div v-for="booth in group.booths" :key="booth.id" class="space-y-2">
          <div class="flex flex-wrap items-center gap-x-2">
            <span class="text-sm font-medium tracking-tight">{{ booth.brandName }}</span>
            <Badge v-if="booth.boothNumber" variant="muted" plain>
              {{ $t("ed.booth.label") }} {{ booth.boothNumber }}
            </Badge>
          </div>

          <div class="space-y-3">
            <NuxtLink
              v-for="order in booth.orders"
              :key="order.ulid"
              :to="`/brands/${booth.brandSlug}/orders/${booth.id}/${order.ulid}`"
              class="hover:bg-muted/50 block rounded-lg border p-4 transition"
            >
              <!-- Top row: order number, status, total -->
              <div class="flex items-start justify-between gap-x-3">
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-x-2">
                    <p class="font-mono text-sm font-medium">{{ order.order_number }}</p>
                    <Badge :variant="statusVariant(order.operational_status)" class="capitalize">
                      {{ order.operational_status_label || order.operational_status }}
                    </Badge>
                  </div>
                  <p class="text-muted-foreground mt-1 text-xs tracking-tight sm:text-sm">
                    {{ formatDate(order.submitted_at) }}
                  </p>
                </div>
                <p class="shrink-0 text-sm font-medium tracking-tight">
                  {{ formatPrice(order.total, order.currency) }}
                </p>
              </div>

              <!-- Onsite surcharge already charged on this order -->
              <div
                v-if="order.order_period === 'onsite_order' && Number(order.penalty_amount) > 0"
                class="text-destructive-foreground mt-2 flex items-center gap-x-1.5 text-xs tracking-tight sm:text-sm"
              >
                <Icon name="hugeicons:alert-02" class="size-3.5 shrink-0" />
                <span>
                  {{
                    $t("orders.onsiteSurcharge", {
                      amount: formatPrice(order.penalty_amount, order.currency),
                    })
                  }}
                </span>
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
                    {{ item.quantity }} x {{ formatPrice(item.unit_price, order.currency) }}
                  </span>
                </div>
              </div>
            </NuxtLink>
          </div>
        </div>
      </section>
    </template>

    <!-- Empty state -->
    <Empty v-else>
      <EmptyHeader>
        <EmptyMedia variant="icon">
          <Icon name="hugeicons:shopping-bag-01" />
        </EmptyMedia>
        <EmptyTitle>{{ $t("orders.noOrdersYet") }}</EmptyTitle>
        <EmptyDescription>{{ $t("orders.howToOrderInfo") }}</EmptyDescription>
      </EmptyHeader>
      <EmptyContent>
        <Button to="/brands" size="sm">{{ $t("orders.goToBrands") }}</Button>
      </EmptyContent>
    </Empty>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { toast } from "vue-sonner";

const { t } = useI18n();

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta(null, { title: t("brands.myOrders") });

const client = useSanctumClient();
const orders = ref([]);
const loading = ref(true);

async function fetchOrders() {
  loading.value = true;
  try {
    const res = await client("/api/exhibitor/orders");
    orders.value = res.data ?? [];
  } catch {
    toast.error(t("orders.failedToLoad"));
  } finally {
    loading.value = false;
  }
}

// Two levels: event, then the booth the order belongs to. An exhibitor holding
// several booths in one event reads the list by hall position, not by a flat
// stream of order numbers.
const eventGroups = computed(() => {
  const events = new Map();

  for (const order of orders.value) {
    const brandEvent = order.brand_event;
    const event = brandEvent?.event;
    if (!brandEvent || !event) continue;

    if (!events.has(event.id)) {
      events.set(event.id, {
        id: event.id,
        title: event.title,
        dateLabel: event.date_label,
        startDate: event.start_date,
        booths: new Map(),
      });
    }

    const group = events.get(event.id);

    if (!group.booths.has(brandEvent.id)) {
      group.booths.set(brandEvent.id, {
        id: brandEvent.id,
        brandName: brandEvent.brand?.name,
        brandSlug: brandEvent.brand?.slug,
        boothNumber: brandEvent.booth_number,
        orders: [],
      });
    }

    group.booths.get(brandEvent.id).orders.push(order);
  }

  return [...events.values()]
    .sort((a, b) => String(b.startDate ?? "").localeCompare(String(a.startDate ?? "")))
    .map((group) => ({
      ...group,
      booths: [...group.booths.values()].sort((a, b) =>
        String(a.boothNumber ?? "￿").localeCompare(String(b.boothNumber ?? "￿"))
      ),
    }));
});

const { formatPrice, formatDateId: formatDate, orderStatusVariant: statusVariant } = useFormatters();

onMounted(fetchOrders);
</script>
