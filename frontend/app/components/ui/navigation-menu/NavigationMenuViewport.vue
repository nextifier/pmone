<script setup lang="ts">
import { type HTMLAttributes, computed } from "vue";
import {
  NavigationMenuViewport,
  type NavigationMenuViewportProps,
  useForwardProps,
} from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps<
  NavigationMenuViewportProps & { class?: HTMLAttributes["class"] }
>();

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;

  return delegated;
});

const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
  <div
    class="cn-navigation-menu-viewport-wrapper absolute top-full left-0 isolate z-50 flex justify-center"
  >
    <NavigationMenuViewport
      v-bind="forwardedProps"
      data-slot="navigation-menu-viewport"
      :class="
        cn(
          'cn-navigation-menu-viewport origin-top-center relative mt-1.5 h-(--reka-navigation-menu-viewport-height) w-full overflow-hidden md:w-(--reka-navigation-menu-viewport-width)',
          props.class,
        )
      "
    />
  </div>
</template>
