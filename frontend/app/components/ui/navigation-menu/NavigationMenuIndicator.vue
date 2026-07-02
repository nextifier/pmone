<script setup lang="ts">
import { type HTMLAttributes, computed } from "vue";
import {
  NavigationMenuIndicator,
  type NavigationMenuIndicatorProps,
  useForwardProps,
} from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps<
  NavigationMenuIndicatorProps & { class?: HTMLAttributes["class"] }
>();

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;

  return delegated;
});

const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
  <NavigationMenuIndicator
    v-bind="forwardedProps"
    data-slot="navigation-menu-indicator"
    :class="
      cn(
        'cn-navigation-menu-indicator top-full z-1 flex h-1.5 items-end justify-center overflow-hidden',
        props.class,
      )
    "
  >
    <div
      class="cn-navigation-menu-indicator-arrow relative top-[60%] h-2 w-2 rotate-45"
    />
  </NavigationMenuIndicator>
</template>
