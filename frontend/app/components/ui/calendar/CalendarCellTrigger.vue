<script lang="ts" setup>
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { CalendarCellTriggerProps } from "reka-ui";
import {
  CalendarCellTrigger,
  RangeCalendarCellTrigger,
  useForwardProps,
} from "reka-ui";
import { computed, type HTMLAttributes } from "vue";
import { useCalendarMode } from "./context";

const props = withDefaults(
  defineProps<CalendarCellTriggerProps & { class?: HTMLAttributes["class"] }>(),
  {
    as: "button",
  },
);

const delegatedProps = reactiveOmit(props, "class");

const forwardedProps = useForwardProps(delegatedProps);

const mode = useCalendarMode();
const isRange = computed(() => mode.value === "range");
</script>

<template>
  <component
    :is="isRange ? RangeCalendarCellTrigger : CalendarCellTrigger"
    data-slot="calendar-cell-trigger"
    type="button"
    :class="
      cn(
        buttonVariants({ variant: 'ghost' }),
        'cn-calendar-day-button group relative isolate z-10 flex aspect-square size-auto w-full min-w-(--cell-size) cursor-pointer flex-col gap-1 rounded-(--cell-radius) border-0 p-0 leading-none font-normal select-none',
        '[&>span]:text-xs [&>span]:opacity-70',
        '[&[data-today]:not([data-selected])]:bg-muted [&[data-today]:not([data-selected])]:text-foreground',
        isRange
          ? [
              // Endpoints are solid; everything between them fills the muted
              // track edge-to-edge so the range is one continuous bar.
              'data-[selection-start]:bg-primary data-[selection-start]:text-primary-foreground data-[selection-start]:hover:bg-primary data-[selection-start]:hover:text-primary-foreground data-[selection-start]:focus:bg-primary data-[selection-start]:focus:text-primary-foreground',
              'data-[selection-end]:bg-primary data-[selection-end]:text-primary-foreground data-[selection-end]:hover:bg-primary data-[selection-end]:hover:text-primary-foreground data-[selection-end]:focus:bg-primary data-[selection-end]:focus:text-primary-foreground',
              '[&[data-selected]:not([data-selection-start]):not([data-selection-end])]:bg-primary/12 [&[data-selected]:not([data-selection-start]):not([data-selection-end])]:text-foreground [&[data-selected]:not([data-selection-start]):not([data-selection-end])]:rounded-none',
              'data-[selected]:opacity-100',
              // Outside months
              'data-[outside-view]:text-muted-foreground data-[outside-view]:opacity-50',
            ]
          : [
              // Selected
              'data-[selected]:bg-primary data-[selected]:text-primary-foreground data-[selected]:hover:bg-primary data-[selected]:hover:text-primary-foreground data-[selected]:focus:bg-primary data-[selected]:focus:text-primary-foreground data-[selected]:opacity-100',
              // Outside months
              'data-[outside-view]:text-muted-foreground',
            ],
        // Disabled
        'data-[disabled]:text-muted-foreground data-[disabled]:cursor-not-allowed data-[disabled]:opacity-50',
        // Unavailable — struck through at full strength, so a booked date still
        // reads clearly even when it is disabled too.
        'data-[unavailable]:text-muted-foreground data-[unavailable]:line-through data-[unavailable]:opacity-100',
        // Today dot indicator
        'data-[today]:after:bg-primary data-[today]:data-[selected]:after:bg-primary-foreground data-[today]:after:absolute data-[today]:after:bottom-1 data-[today]:after:left-1/2 data-[today]:after:size-1 data-[today]:after:-translate-x-1/2 data-[today]:after:rounded-full',
        props.class,
      )
    "
    v-bind="forwardedProps"
  >
    <slot />
  </component>
</template>
