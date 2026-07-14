<template>
  <div class="border-border rounded-xl border p-4">
    <h3 class="font-semibold tracking-tight">Order Summary</h3>

    <div v-if="!items.length" class="text-muted-foreground py-8 text-center text-sm tracking-tight">
      Add products to build the order.
    </div>

    <template v-else>
      <div class="divide-border mt-3 divide-y">
        <div
          v-for="item in items"
          :key="item.event_product_id"
          class="flex items-start justify-between gap-x-3 py-2 first:pt-0"
        >
          <span class="min-w-0 flex-1 truncate text-sm tracking-tight">
            {{ item.name }}
            <span class="text-muted-foreground">x {{ item.quantity }}</span>
          </span>
          <span class="shrink-0 text-sm tracking-tight">
            {{ formatPrice(item.price * item.quantity, currency) }}
          </span>
        </div>
      </div>

      <div class="border-border mt-3 space-y-1.5 border-t pt-3 text-sm">
        <div class="flex justify-between">
          <span class="text-muted-foreground tracking-tight">Subtotal</span>
          <span>{{ formatPrice(subtotal, currency) }}</span>
        </div>
        <div v-if="isOnsite" class="flex justify-between">
          <span class="text-warning-foreground tracking-tight">
            Onsite penalty ({{ penaltyRate }}%)
          </span>
          <span class="text-warning-foreground">+ {{ formatPrice(penaltyAmount, currency) }}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-muted-foreground tracking-tight">Tax ({{ taxRate }}%)</span>
          <span>{{ formatPrice(taxAmount, currency) }}</span>
        </div>
        <div class="border-border flex justify-between border-t pt-1.5 font-semibold">
          <span>Total</span>
          <span>{{ formatPrice(total, currency) }}</span>
        </div>
      </div>

      <p
        v-if="isOnsite"
        class="text-muted-foreground mt-3 text-sm tracking-tight"
      >
        Onsite period penalty is applied automatically and can be voided after creation.
      </p>
    </template>
  </div>
</template>

<script setup>
const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
  taxRate: {
    type: Number,
    default: 11,
  },
  penaltyRate: {
    type: Number,
    default: 0,
  },
  currency: {
    type: String,
    default: "IDR",
  },
});

const { formatPrice } = useFormatters();

const isOnsite = computed(() => props.penaltyRate > 0);

function roundMoney(value) {
  return props.currency === "USD" ? Math.round(value * 100) / 100 : Math.round(value);
}

const subtotal = computed(() =>
  props.items.reduce((sum, i) => sum + (i.price || 0) * (i.quantity || 0), 0)
);
const penaltyAmount = computed(() =>
  isOnsite.value ? roundMoney((subtotal.value * props.penaltyRate) / 100) : 0
);
const taxableBase = computed(() => subtotal.value + penaltyAmount.value);
const taxAmount = computed(() => roundMoney((taxableBase.value * props.taxRate) / 100));
const total = computed(() => taxableBase.value + taxAmount.value);
</script>
