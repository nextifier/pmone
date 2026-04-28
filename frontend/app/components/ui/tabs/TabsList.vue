<script setup lang="ts">
import { cn } from "@/lib/utils";
import { TabsList, type TabsListProps } from "reka-ui";
import { computed, inject, type HTMLAttributes } from "vue";
import { TABS_CONTEXT, TABS_DEFAULTS, tabsListClasses } from "./context";

const props = defineProps<TabsListProps & { class?: HTMLAttributes["class"] }>();

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;
  return delegated;
});

const ctx = inject(TABS_CONTEXT, null);
const variantClass = computed(
  () => tabsListClasses[ctx?.variant.value ?? TABS_DEFAULTS.variant],
);
</script>

<template>
  <TabsList
    v-bind="delegatedProps"
    :class="cn(variantClass, props.class)"
  >
    <slot />
  </TabsList>
</template>
