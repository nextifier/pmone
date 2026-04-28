<script setup lang="ts">
import { cn } from "@/lib/utils";
import { TabsTrigger, type TabsTriggerProps, useForwardProps } from "reka-ui";
import { computed, inject, type HTMLAttributes } from "vue";
import {
  TABS_CONTEXT,
  TABS_DEFAULTS,
  tabsTriggerClasses,
  tabsTriggerSizeClasses,
} from "./context";

const props = defineProps<
  TabsTriggerProps & { class?: HTMLAttributes["class"] }
>();

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;
  return delegated;
});

const forwardedProps = useForwardProps(delegatedProps);

const ctx = inject(TABS_CONTEXT, null);
const variantClass = computed(
  () => tabsTriggerClasses[ctx?.variant.value ?? TABS_DEFAULTS.variant],
);
const sizeClass = computed(
  () => tabsTriggerSizeClasses[ctx?.size.value ?? TABS_DEFAULTS.size],
);
</script>

<template>
  <TabsTrigger
    v-bind="forwardedProps"
    :class="
      cn(
        'ring-offset-background focus-visible:ring-ring inline-flex items-center justify-center gap-1.5 font-medium whitespace-nowrap transition-colors outline-hidden select-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
        sizeClass,
        variantClass,
        props.class,
      )
    "
  >
    <slot />
  </TabsTrigger>
</template>
