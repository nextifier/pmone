<template>
  <div class="space-y-4">
    <!-- Order Period Info -->
    <div v-if="be.normal_order_opens_at || be.onsite_order_opens_at" class="space-y-2">
      <!-- Normal Order Period -->
      <div
        v-if="be.normal_order_opens_at"
        class="flex items-start gap-2 text-sm tracking-tight"
      >
        <Icon
          :name="normalStatus.icon"
          :class="['size-4.5 shrink-0 mt-0.5', normalStatus.color]"
        />
        <div>
          <p>
            <span class="font-medium">Normal Order:</span>
            {{ formatDateTime(be.normal_order_opens_at) }}
            <template v-if="be.normal_order_closes_at">
              - {{ formatDateTime(be.normal_order_closes_at) }}
            </template>
          </p>
          <p v-if="normalStatus.label" :class="['text-xs sm:text-sm', normalStatus.color]">
            {{ normalStatus.label }}
          </p>
        </div>
      </div>

      <!-- Onsite Order Period -->
      <div
        v-if="be.onsite_order_opens_at"
        class="flex items-start gap-2 text-sm tracking-tight"
      >
        <Icon
          :name="onsiteStatus.icon"
          :class="['size-4.5 shrink-0 mt-0.5', onsiteStatus.color]"
        />
        <div>
          <p>
            <span class="font-medium">Onsite Order:</span>
            {{ formatDateTime(be.onsite_order_opens_at) }}
            <template v-if="be.onsite_order_closes_at">
              - {{ formatDateTime(be.onsite_order_closes_at) }}
            </template>
          </p>
          <p class="text-muted-foreground text-xs sm:text-sm">
            +{{ be.onsite_penalty_rate }}% surcharge applied to onsite orders.
          </p>
          <p v-if="onsiteStatus.label" :class="['text-xs sm:text-sm', onsiteStatus.color]">
            {{ onsiteStatus.label }}
          </p>
        </div>
      </div>
    </div>

    <!-- Action buttons -->
    <div class="flex flex-wrap items-center gap-2">
      <NuxtLink
        v-if="canOrder"
        :to="`/brands/${be.brand.slug}/order-form/${be.brand_event_id}`"
        class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center gap-x-1.5 rounded-lg px-3 py-1.5 text-xs font-medium tracking-tight transition-colors sm:text-sm"
      >
        <Icon name="hugeicons:shopping-cart-01" class="size-3.5" />
        {{ be.orders_count > 0 ? "New Order" : "Open Order Form" }}
      </NuxtLink>
      <p v-else class="text-muted-foreground text-sm tracking-tight">
        {{ closedMessage }}
      </p>
      <NuxtLink
        v-if="be.orders_count > 0"
        :to="`/brands/${be.brand.slug}/orders/${be.brand_event_id}`"
        class="border-border hover:bg-muted inline-flex items-center gap-x-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium tracking-tight transition-colors sm:text-sm"
      >
        <Icon name="hugeicons:shopping-bag-01" class="size-3.5" />
        View Orders
      </NuxtLink>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  be: { type: Object, required: true },
});

function formatDateTime(dateStr) {
  if (!dateStr) return "";
  const d = new Date(dateStr);
  return (
    d.toLocaleDateString("id-ID", { day: "numeric", month: "short", year: "numeric" }) +
    " " +
    String(d.getHours()).padStart(2, "0") +
    ":" +
    String(d.getMinutes()).padStart(2, "0")
  );
}

function getPeriodStatus(opensAt, closesAt) {
  if (!opensAt) return { icon: "hugeicons:circle", color: "text-muted-foreground", label: "" };
  const now = new Date();
  const opens = new Date(opensAt);
  const closes = closesAt ? new Date(closesAt) : null;

  if (now < opens) {
    return { icon: "hugeicons:clock-01", color: "text-muted-foreground", label: "Not yet open" };
  }
  if (!closes || now <= closes) {
    return {
      icon: "hugeicons:checkmark-circle-02",
      color: "text-success-foreground",
      label: "Open now",
    };
  }
  return { icon: "hugeicons:cancel-circle", color: "text-muted-foreground", label: "Closed" };
}

const normalStatus = computed(() =>
  getPeriodStatus(props.be.normal_order_opens_at, props.be.normal_order_closes_at),
);
const onsiteStatus = computed(() =>
  getPeriodStatus(props.be.onsite_order_opens_at, props.be.onsite_order_closes_at),
);

const canOrder = computed(() => {
  const be = props.be;
  // Legacy: no periods configured, always allow
  if (!be.normal_order_opens_at && !be.onsite_order_opens_at) return true;
  return normalStatus.value.label === "Open now" || onsiteStatus.value.label === "Open now";
});

const closedMessage = computed(() => {
  const be = props.be;
  if (!be.normal_order_opens_at && !be.onsite_order_opens_at) return "";
  const now = new Date();
  if (be.normal_order_opens_at && now < new Date(be.normal_order_opens_at)) {
    return "Order form will be available when the order period opens.";
  }
  if (be.onsite_order_opens_at && now < new Date(be.onsite_order_opens_at)) {
    return "Normal order period has closed. Onsite order period has not yet started.";
  }
  return "All order periods have closed.";
});
</script>
