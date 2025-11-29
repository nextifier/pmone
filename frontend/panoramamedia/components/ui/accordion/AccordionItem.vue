<script setup lang="ts">
import { type HTMLAttributes, computed } from "vue";
import {
  AccordionItem,
  type AccordionItemProps,
  useForwardProps,
} from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps<
  AccordionItemProps & { class?: HTMLAttributes["class"] }
>();

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;

  return delegated;
});

const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
  <AccordionItem
    v-bind="forwardedProps"
    :class="
      cn(
        'rounded-2xl border border-gray-200 px-4 dark:border-gray-800 lg:rounded-3xl lg:px-6',
        props.class,
      )
    "
  >
    <slot />
  </AccordionItem>
</template>
