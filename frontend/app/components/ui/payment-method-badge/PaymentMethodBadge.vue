<script setup lang="ts">
import {
  getPaymentChannelLabel,
  getPaymentLogoUrl,
  getPaymentMethodLabel,
} from "@/lib/payment-method-logos";
import { computed } from "vue";

const props = withDefaults(
  defineProps<{
    channel?: string | null;
    method?: string | null;
    size?: "sm" | "md" | "lg";
    showLabel?: boolean;
    iconOnly?: boolean;
  }>(),
  { size: "md", showLabel: true, iconOnly: false }
);

const logoUrl = computed(() => getPaymentLogoUrl(props.channel));
const label = computed(
  () => getPaymentChannelLabel(props.channel) ?? getPaymentMethodLabel(props.method) ?? null
);
const iconClass = computed(() => ({ sm: "h-6", md: "h-8", lg: "h-10" })[props.size]);
const showText = computed(() => (props.iconOnly ? !logoUrl.value : props.showLabel));
</script>

<template>
  <span
    v-if="logoUrl || label"
    class="inline-flex items-center gap-1.5 align-middle tracking-tight"
  >
    <img
      v-if="logoUrl"
      v-tippy="label"
      :src="logoUrl"
      :alt="label ?? 'Payment method'"
      :class="[
        'shrink-0 object-contain dark:brightness-90 dark:contrast-200 dark:grayscale dark:invert-[75%]',
        iconClass,
      ]"
      loading="lazy"
    />
    <span v-if="showText && label" class="text-sm">{{ label }}</span>
  </span>
  <span v-else class="text-muted-foreground text-sm">-</span>
</template>
