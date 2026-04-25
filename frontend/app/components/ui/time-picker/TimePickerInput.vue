<script lang="ts" setup>
import type { TimeFieldInputProps } from "reka-ui";
import type { HTMLAttributes } from "vue";
import { reactiveOmit } from "@vueuse/core";
import { TimeFieldInput, useForwardProps } from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps<TimeFieldInputProps & { class?: HTMLAttributes["class"] }>();

const delegatedProps = reactiveOmit(props, "class");

const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
  <TimeFieldInput
    data-slot="time-picker-input"
    :class="
      cn(
        'inline rounded-sm px-px py-px tabular-nums tracking-tight caret-transparent outline-none',
        'selection:bg-primary selection:text-primary-foreground',
        'focus:bg-accent focus:text-accent-foreground',
        'data-[placeholder]:text-muted-foreground',
        'data-[invalid]:text-destructive',
        'aria-disabled:cursor-not-allowed aria-disabled:opacity-50',
        props.class,
      )
    "
    v-bind="forwardedProps"
  >
    <slot />
  </TimeFieldInput>
</template>
