<script setup lang="ts">
import { computed } from "vue";
import {
  getPaymentChannelLabel,
  getPaymentLogoUrl,
  getPaymentMethodLabel,
} from "@/lib/payment-method-logos";

const props = withDefaults(
  defineProps<{
    channel?: string | null;
    method?: string | null;
    size?: "sm" | "md";
    showLabel?: boolean;
  }>(),
  { size: "md", showLabel: true }
);

const logoUrl = computed(() => getPaymentLogoUrl(props.channel));
const label = computed(
  () =>
    getPaymentChannelLabel(props.channel) ??
    getPaymentMethodLabel(props.method) ??
    null
);
const iconClass = computed(() => (props.size === "sm" ? "h-4" : "h-5"));
</script>

<template>
  <span v-if="logoUrl || label" class="inline-flex items-center gap-1.5 tracking-tight">
    <img
      v-if="logoUrl"
      :src="logoUrl"
      :alt="label ?? 'Payment method'"
      :class="['shrink-0 object-contain', iconClass]"
      loading="lazy"
    />
    <span v-if="showLabel && label" class="text-sm">{{ label }}</span>
  </span>
  <span v-else class="text-muted-foreground text-sm">-</span>
</template>
