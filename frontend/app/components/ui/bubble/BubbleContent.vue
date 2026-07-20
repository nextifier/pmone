<script setup lang="ts">
import type { PrimitiveProps } from "reka-ui";
import type { HTMLAttributes } from "vue";
import { Primitive } from "reka-ui";
import { cn } from "@/lib/utils";

const props = withDefaults(
  defineProps<PrimitiveProps & { class?: HTMLAttributes["class"] }>(),
  { as: "div" }
);
</script>

<template>
  <Primitive
    :as="as"
    :as-child="asChild"
    data-slot="bubble-content"
    :class="
      cn(
        'w-fit max-w-full min-w-0 overflow-hidden rounded-xl wrap-break-word px-3 py-2 text-sm tracking-tight [&_a]:underline [&_a]:underline-offset-2 [&_a,&_button]:transition-colors [&_button]:text-left',
        props.class
      )
    "
  >
    <slot />
  </Primitive>
</template>
