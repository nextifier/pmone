<script lang="ts" setup>
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { TimeRangeFieldInputProps } from "reka-ui";
import { TimeRangeFieldInput, useForwardProps } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = withDefaults(
  defineProps<TimeRangeFieldInputProps & {
    class?: HTMLAttributes["class"];
    showCaret?: boolean;
  }>(),
  { showCaret: false },
);

const delegatedProps = reactiveOmit(props, "class", "showCaret");

const forwardedProps = useForwardProps(delegatedProps);

const isLiteral = computed(() => props.part === "literal");
</script>

<template>
  <TimeRangeFieldInput
    data-slot="time-range-picker-input"
    :class="
      cn(
        'tracking-tight tabular-nums transition-colors outline-none',
        'selection:bg-primary selection:text-primary-foreground',
        isLiteral
          ? 'text-muted-foreground inline px-px'
          : 'cn-input hover:bg-muted/50 focus:bg-muted focus:text-foreground not-focus:data-[placeholder]:text-muted-foreground data-[invalid]:border-destructive/50 data-[invalid]:bg-destructive/10 data-[invalid]:text-destructive squircle inline-flex size-8 items-center justify-center rounded-sm p-0 shadow-none',
        showCaret && !isLiteral && 'caret-foreground! focus:caret-accent-foreground!',
        'aria-disabled:cursor-not-allowed aria-disabled:opacity-50',
        props.class
      )
    "
    v-bind="forwardedProps"
  >
    <slot />
  </TimeRangeFieldInput>
</template>
