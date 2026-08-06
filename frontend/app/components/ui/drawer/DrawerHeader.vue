<script lang="ts" setup>
import type { HTMLAttributes } from "vue";
import { ref, toRef } from "vue";
import { cn } from "@/lib/utils";
import { useDrawerSelectionArea } from "./useDrawerSelectionArea";

const props = withDefaults(
  defineProps<{
    class?: HTMLAttributes["class"];
    /** Let the user select the header text instead of using it as drag surface. */
    allowSelection?: boolean;
  }>(),
  { allowSelection: false }
);

const selectionArea = ref<HTMLElement | null>(null);
useDrawerSelectionArea(selectionArea, toRef(() => props.allowSelection));
</script>

<template>
  <!-- Classes copied verbatim from coss drawer.tsx (DrawerHeader). -->
  <div
    ref="selectionArea"
    data-slot="drawer-header"
    :class="
      cn(
        'flex flex-col gap-2 p-6 in-[[data-slot=drawer-popup]:has([data-slot=drawer-panel])]:pb-3 max-sm:pb-4',
        !allowSelection && 'cursor-default',
        props.class
      )
    "
  >
    <slot />
  </div>
</template>
