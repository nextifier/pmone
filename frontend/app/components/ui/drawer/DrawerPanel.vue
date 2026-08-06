<script lang="ts" setup>
import type { HTMLAttributes } from "vue";
import { ScrollArea } from "@/components/ui/scroll-area";
import { ref, toRef } from "vue";
import { cn } from "@/lib/utils";
import { useDrawerSelectionArea } from "./useDrawerSelectionArea";

const props = withDefaults(
  defineProps<{
    class?: HTMLAttributes["class"];
    scrollable?: boolean;
    scrollFade?: boolean;
    allowSelection?: boolean;
  }>(),
  { scrollable: true, scrollFade: true, allowSelection: true }
);

const selectionArea = ref<HTMLElement | null>(null);
useDrawerSelectionArea(selectionArea, toRef(() => props.allowSelection));
</script>

<template>
  <!--
    Structure and classes copied from coss drawer.tsx (DrawerPanel): the panel is
    wrapped in a ScrollArea, so the scrolling happens in its viewport and this
    outer box stays `overflow: visible`, matching the reference.

    The popup and the viewport carry `touch-none` so the gesture belongs to JS;
    `touch-auto` here hands scrolling back to the browser for the panel only, and
    `useDrawerSwipeArbiter` settles which of the two owns each gesture.
  -->
  <ScrollArea
    v-if="scrollable"
    class="touch-auto"
    :viewport-class="cn('overscroll-contain', scrollFade && 'scroll-fade-y')"
  >
    <div
      ref="selectionArea"
      data-slot="drawer-panel"
      :class="
        cn(
          'p-6 in-[[data-slot=drawer-popup]:has([data-slot=drawer-header])]:pt-1 in-[[data-slot=drawer-popup]:has([data-slot=drawer-footer]:not(.border-t))]:pb-1',
          !allowSelection && 'cursor-default',
          props.class
        )
      "
    >
      <slot />
    </div>
  </ScrollArea>
  <div
    v-else
    ref="selectionArea"
    data-slot="drawer-panel"
    :class="
      cn(
        'p-6 in-[[data-slot=drawer-popup]:has([data-slot=drawer-header])]:pt-1 in-[[data-slot=drawer-popup]:has([data-slot=drawer-footer]:not(.border-t))]:pb-1',
        !allowSelection && 'cursor-default',
        props.class
      )
    "
  >
    <slot />
  </div>
</template>
