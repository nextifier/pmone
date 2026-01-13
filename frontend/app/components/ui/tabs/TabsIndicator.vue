<script setup lang="ts">
import { cn } from "@/lib/utils";
import { TabsIndicator, type TabsIndicatorProps } from "reka-ui";
import { computed, type HTMLAttributes } from "vue";

const props = defineProps<
  TabsIndicatorProps & { class?: HTMLAttributes["class"] }
>();

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;

  return delegated;
});
</script>

<template>
  <TabsIndicator
    v-bind="delegatedProps"
    :class="
      cn(
        'absolute left-0 h-full rounded-full bg-gray-100 transition-[width,transform] duration-200 ease-out dark:bg-gray-800',
        props.class,
      )
    "
    :style="{
      width: 'var(--reka-tabs-indicator-size)',
      transform: 'translateX(var(--reka-tabs-indicator-position))',
    }"
  />
</template>
