<script setup lang="ts">
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { RadioGroupItemProps } from "reka-ui";
import { RadioGroupIndicator, RadioGroupItem, useForwardProps } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = defineProps<RadioGroupItemProps & { class?: HTMLAttributes["class"] }>();

const delegatedProps = reactiveOmit(props, "class");

const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
  <RadioGroupItem
    data-slot="radio-group-item"
    v-bind="forwardedProps"
    :class="
      cn(
        'cn-radio-group-item group/radio-group-item peer relative aspect-square shrink-0 cursor-pointer border outline-none after:absolute after:-inset-x-3 after:-inset-y-2 disabled:cursor-not-allowed disabled:opacity-50',
        props.class
      )
    "
  >
    <RadioGroupIndicator
      data-slot="radio-group-indicator"
      class="cn-radio-group-indicator"
    >
      <svg
        class="cn-radio-group-indicator-icon"
        width="6"
        height="6"
        viewBox="0 0 6 6"
        fill="currentcolor"
        xmlns="http://www.w3.org/2000/svg"
      >
        <circle cx="3" cy="3" r="3" />
      </svg>
    </RadioGroupIndicator>
  </RadioGroupItem>
</template>
