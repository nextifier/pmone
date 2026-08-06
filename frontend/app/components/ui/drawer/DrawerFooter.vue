<script lang="ts" setup>
import type { HTMLAttributes } from "vue";
import { ref, toRef } from "vue";
import { cn } from "@/lib/utils";
import { useDrawerSelectionArea } from "./useDrawerSelectionArea";

const props = withDefaults(
  defineProps<{
    class?: HTMLAttributes["class"];
    variant?: "default" | "bare";
    allowSelection?: boolean;
  }>(),
  { variant: "default", allowSelection: true }
);

const selectionArea = ref<HTMLElement | null>(null);
useDrawerSelectionArea(selectionArea, toRef(() => props.allowSelection));
</script>

<template>
  <!-- Classes copied verbatim from coss drawer.tsx (DrawerFooter). -->
  <div
    ref="selectionArea"
    data-slot="drawer-footer"
    :class="
      cn(
        'flex flex-col-reverse gap-2 px-6 pb-(--safe-area-inset-bottom,0px) sm:flex-row sm:justify-end',
        !allowSelection && 'cursor-default',
        variant === 'default' &&
          'border-t bg-muted/72 pt-4 pb-[calc(env(safe-area-inset-bottom,0px)+--spacing(4))]',
        variant === 'bare' &&
          'in-[[data-slot=drawer-popup]:has([data-slot=drawer-panel])]:pt-3 pt-4 pb-[calc(env(safe-area-inset-bottom,0px)+--spacing(6))]',
        props.class
      )
    "
  >
    <slot />
  </div>
</template>
