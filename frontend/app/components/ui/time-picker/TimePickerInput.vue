<script lang="ts" setup>
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { TimeFieldInputProps } from "reka-ui";
import { TimeFieldInput, useForwardProps } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = withDefaults(
  defineProps<TimeFieldInputProps & {
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
  <TimeFieldInput
    data-slot="time-picker-input"
    :class="
      cn(
        'tracking-tight tabular-nums transition-colors outline-none',
        'selection:bg-primary selection:text-primary-foreground',
        isLiteral
          ? 'text-muted-foreground inline px-px'
          : 'border-border bg-muted hover:bg-muted/70 focus:bg-accent focus:text-accent-foreground data-[placeholder]:text-muted-foreground data-[invalid]:border-destructive/50 data-[invalid]:bg-destructive/10 data-[invalid]:text-destructive squircle inline-flex size-7 items-center justify-center rounded-sm border',
        showCaret && !isLiteral && 'caret-foreground! focus:caret-accent-foreground!',
        'aria-disabled:cursor-not-allowed aria-disabled:opacity-50',
        props.class
      )
    "
    v-bind="forwardedProps"
  >
    <slot />
  </TimeFieldInput>
</template>
