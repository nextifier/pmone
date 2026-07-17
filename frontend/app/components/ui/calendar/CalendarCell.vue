<script lang="ts" setup>
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { CalendarCellProps } from "reka-ui";
import { CalendarCell, RangeCalendarCell, useForwardProps } from "reka-ui";
import { computed, type HTMLAttributes } from "vue";
import { useCalendarMode } from "./context";

const props = defineProps<
  CalendarCellProps & { class?: HTMLAttributes["class"] }
>();

const delegatedProps = reactiveOmit(props, "class");

const forwardedProps = useForwardProps(delegatedProps);

const mode = useCalendarMode();
const isRange = computed(() => mode.value === "range");
</script>

<template>
  <component
    :is="isRange ? RangeCalendarCell : CalendarCell"
    data-slot="calendar-cell"
    :class="
      cn(
        'group/day relative aspect-square h-full w-full flex-1 rounded-(--cell-radius) p-0 text-center select-none',
        // The trailing ! lets these row-edge rules beat the trigger's own
        // `rounded-none` (its selected:not(start):not(end) selector is more
        // specific than this cell rule) so every selected row keeps rounded ends.
        '[&:first-child:has([data-selected])_button]:rounded-l-(--cell-radius)! [&:last-child:has([data-selected])_button]:rounded-r-(--cell-radius)!',
        isRange && [
          // The range reads as a muted track with a solid pill at each end. The
          // track is painted on the cell (z-0) and the pill on the trigger (z-10);
          // the ::after square fills the cell's rounded corner so the track does
          // not break at the seam between two cells.
          '[&:has([data-selection-start])]:bg-muted [&:has([data-selection-start])]:after:bg-muted [&:has([data-selection-start])]:isolate [&:has([data-selection-start])]:z-0 [&:has([data-selection-start])]:rounded-l-(--cell-radius) [&:has([data-selection-start])]:after:absolute [&:has([data-selection-start])]:after:inset-y-0 [&:has([data-selection-start])]:after:right-0 [&:has([data-selection-start])]:after:w-4',
          '[&:has([data-selection-end])]:bg-muted [&:has([data-selection-end])]:after:bg-muted [&:has([data-selection-end])]:isolate [&:has([data-selection-end])]:z-0 [&:has([data-selection-end])]:rounded-r-(--cell-radius) [&:has([data-selection-end])]:after:absolute [&:has([data-selection-end])]:after:inset-y-0 [&:has([data-selection-end])]:after:left-0 [&:has([data-selection-end])]:after:w-4',
          '[&:has([data-selected]):not(:has([data-selection-start])):not(:has([data-selection-end]))]:rounded-none',
          // A single-day range is both ends at once: keep it a lone pill, no track.
          '[&:has([data-selection-start][data-selection-end])]:bg-transparent [&:has([data-selection-start][data-selection-end])]:after:hidden',
        ],
        props.class,
      )
    "
    v-bind="forwardedProps"
  >
    <slot />
  </component>
</template>
