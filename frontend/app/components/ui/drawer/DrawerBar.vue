<script lang="ts" setup>
import type { HTMLAttributes } from "vue";
import { computed } from "vue";
import { injectDrawerRootContext } from "reka-ui";
import { cn } from "@/lib/utils";

type DrawerPosition = "top" | "right" | "bottom" | "left";

const SWIPE_DIRECTION_TO_POSITION = {
  up: "top",
  down: "bottom",
  left: "left",
  right: "right",
} as const;

const props = defineProps<{
  class?: HTMLAttributes["class"];
  position?: DrawerPosition;
}>();

const rootContext = injectDrawerRootContext();
const position = computed<DrawerPosition>(
  () => props.position ?? SWIPE_DIRECTION_TO_POSITION[rootContext.swipeDirection.value]
);
const horizontal = computed(() => position.value === "left" || position.value === "right");
</script>

<template>
  <!-- Classes copied verbatim from coss drawer.tsx (DrawerBar). -->
  <div
    aria-hidden="true"
    data-slot="drawer-bar"
    :class="
      cn(
        'absolute flex touch-none items-center justify-center p-3 before:rounded-full before:bg-input',
        horizontal ? 'inset-y-0 before:h-12 before:w-1' : 'inset-x-0 before:h-1 before:w-12',
        position === 'top' && 'bottom-0',
        position === 'bottom' && 'top-0',
        position === 'left' && 'right-0',
        position === 'right' && 'left-0',
        props.class
      )
    "
  />
</template>
