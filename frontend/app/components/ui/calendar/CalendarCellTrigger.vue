<script lang="ts" setup>
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { CalendarCellTriggerProps } from "reka-ui";
import { CalendarCellTrigger, useForwardProps } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = withDefaults(
  defineProps<CalendarCellTriggerProps & { class?: HTMLAttributes["class"] }>(),
  {
    as: "button",
  }
);

const delegatedProps = reactiveOmit(props, "class");

const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
  <CalendarCellTrigger
    data-slot="calendar-cell-trigger"
    type="button"
    :class="
      cn(
        buttonVariants({ variant: 'ghost' }),
        'relative size-8 cursor-pointer p-0 font-normal aria-selected:opacity-100',
        '[&[data-today]:not([data-selected])]:bg-muted [&[data-today]:not([data-selected])]:text-foreground',
        // Selected
        'data-[selected]:bg-primary data-[selected]:text-primary-foreground data-[selected]:hover:bg-primary data-[selected]:hover:text-primary-foreground data-[selected]:focus:bg-primary data-[selected]:focus:text-primary-foreground data-[selected]:opacity-100',
        // Disabled
        'data-[disabled]:text-muted-foreground data-[disabled]:opacity-50',
        // Unavailable
        'data-[unavailable]:text-destructive-foreground data-[unavailable]:line-through',
        // Outside months
        'data-[outside-view]:text-muted-foreground',
        // Today dot indicator
        'data-[today]:after:bg-primary data-[today]:data-[selected]:after:bg-primary-foreground data-[today]:after:absolute data-[today]:after:bottom-[3px] data-[today]:after:left-1/2 data-[today]:after:size-[3px] data-[today]:after:-translate-x-1/2 data-[today]:after:rounded-full',
        props.class
      )
    "
    v-bind="forwardedProps"
  >
    <slot />
  </CalendarCellTrigger>
</template>
