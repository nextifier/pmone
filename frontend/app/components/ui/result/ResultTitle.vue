<script setup lang="ts">
import type { PrimitiveProps } from "reka-ui";
import type { HTMLAttributes } from "vue";
import type { ResultContext, ResultVariants } from ".";
import { Primitive } from "reka-ui";
import { computed, inject } from "vue";
import { cn } from "@/lib/utils";
import { RESULT_CONTEXT } from ".";

const props = withDefaults(
  defineProps<
    PrimitiveProps & {
      class?: HTMLAttributes["class"];
      size?: ResultVariants["size"];
    }
  >(),
  {
    /* Real heading, not a div: an outcome screen is the page's main message.
       Pass as="h1" on a dedicated success page, keep h2 for in-page heroes. */
    as: "h2",
  },
);

const context = inject<ResultContext | null>(RESULT_CONTEXT, null);

const size = computed(() => props.size ?? context?.size ?? "default");

const sizeClass = {
  sm: "text-lg sm:text-xl",
  default: "text-xl sm:text-2xl",
  lg: "text-2xl sm:text-3xl",
};
</script>

<template>
  <Primitive
    data-slot="result-title"
    data-result-reveal
    style="--result-i: 1"
    :as="as"
    :as-child="asChild"
    :class="
      cn(
        'cn-font-heading text-foreground leading-[1.2]! font-medium tracking-tighter text-balance',
        sizeClass[size],
        props.class,
      )
    "
  >
    <slot />
  </Primitive>
</template>
