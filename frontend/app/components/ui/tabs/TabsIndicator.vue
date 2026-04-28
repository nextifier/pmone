<script setup lang="ts">
import { cn } from "@/lib/utils";
import { TabsIndicator, type TabsIndicatorProps } from "reka-ui";
import { computed, inject, type HTMLAttributes } from "vue";
import { TABS_CONTEXT, TABS_DEFAULTS, tabsIndicatorClasses } from "./context";

const props = defineProps<
  TabsIndicatorProps & { class?: HTMLAttributes["class"] }
>();

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;
  return delegated;
});

const ctx = inject(TABS_CONTEXT, null);
const variant = computed(() => ctx?.variant.value ?? TABS_DEFAULTS.variant);
const variantClass = computed(() => tabsIndicatorClasses[variant.value]);

const indicatorStyle = computed(() => ({
  width: "var(--reka-tabs-indicator-size)",
  transform: "translateX(var(--reka-tabs-indicator-position))",
}));
</script>

<template>
  <TabsIndicator
    v-bind="delegatedProps"
    :class="cn(variantClass, props.class)"
    :style="indicatorStyle"
  >
    <slot />
  </TabsIndicator>
</template>
