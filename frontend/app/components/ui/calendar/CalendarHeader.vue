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
        'relative flex w-full items-center justify-center px-8 pt-1',
        props.class,
      )
    "
    v-bind="forwardedProps"
  >
    <slot />
  </component>
</template>
