<script lang="ts" setup>
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { CalendarHeaderProps } from "reka-ui";
import { CalendarHeader, RangeCalendarHeader, useForwardProps } from "reka-ui";
import { computed, type HTMLAttributes } from "vue";
import { useCalendarMode } from "./context";

const props = defineProps<
  CalendarHeaderProps & { class?: HTMLAttributes["class"] }
>();

const delegatedProps = reactiveOmit(props, "class");

const forwardedProps = useForwardProps(delegatedProps);

const mode = useCalendarMode();
const isRange = computed(() => mode.value === "range");
</script>

<template>
  <component
    :is="isRange ? RangeCalendarHeader : CalendarHeader"
    data-slot="calendar-header"
    :class="
      cn(
        // min-h matches the nav buttons' size-7: they are absolutely positioned,
        // so the caption row must be at least as tall or they overflow it.
        'relative flex min-h-7 w-full items-center justify-center px-8',
        props.class,
      )
    "
    v-bind="forwardedProps"
  >
    <slot />
  </component>
</template>
